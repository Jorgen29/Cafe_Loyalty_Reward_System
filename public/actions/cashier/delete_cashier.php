<?php
/**
 * Delete Cashier Handler
 * POST endpoint for deleting cashiers
 */

session_start();
header('Content-Type: application/json');

// Custom error handler - return JSON instead of HTML
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[$errno] $errstr in $errfile:$errline");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
});

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include database configuration
require_once '../auth/db_config.php';

// Get POST data
$cashier_id = isset($_POST['cashier_id']) ? trim($_POST['cashier_id']) : '';

if (empty($cashier_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cashier ID is required']);
    exit;
}

// Get user_id from cashier first
$getCashierQuery = $conn->prepare("SELECT user_id FROM cashier WHERE cashier_id = ?");
if (!$getCashierQuery) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$getCashierQuery->bind_param("i", $cashier_id);
$getCashierQuery->execute();
$cashierResult = $getCashierQuery->get_result();

if ($cashierResult->num_rows === 0) {
    $getCashierQuery->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Cashier not found']);
    exit;
}

$cashierRow = $cashierResult->fetch_assoc();
$userId = $cashierRow['user_id'];
$getCashierQuery->close();

// Start transaction for atomic operation
$conn->begin_transaction();

try {
    // Delete cashier record
    $deleteCashierQuery = $conn->prepare("DELETE FROM cashier WHERE cashier_id = ?");
    $deleteCashierQuery->bind_param("i", $cashier_id);
    
    if (!$deleteCashierQuery->execute()) {
        throw new Exception("Failed to delete cashier: " . $deleteCashierQuery->error);
    }
    $deleteCashierQuery->close();

    // Delete user record
    $deleteUserQuery = $conn->prepare("DELETE FROM user WHERE user_id = ?");
    $deleteUserQuery->bind_param("i", $userId);
    
    if (!$deleteUserQuery->execute()) {
        throw new Exception("Failed to delete user: " . $deleteUserQuery->error);
    }
    $deleteUserQuery->close();

    // Commit transaction
    $conn->commit();
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Cashier deleted successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Transaction failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete cashier']);
}
?>
