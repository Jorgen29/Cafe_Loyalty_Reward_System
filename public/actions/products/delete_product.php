<?php
/**
 * Delete Product Handler
 * POST endpoint for deleting products
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
$product_id = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';

// Validation
if (empty($product_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

// Delete product
$query = $conn->prepare("DELETE FROM product WHERE product_id = ?");
if (!$query) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$query->bind_param("i", $product_id);
$result = $query->execute();

if (!$result) {
    error_log("Execute failed: " . $query->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
    exit;
}

// Check if any rows were affected
if ($conn->affected_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

$query->close();
http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
?>
