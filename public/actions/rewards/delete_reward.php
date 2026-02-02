<?php
/**
 * Delete Reward Handler
 * POST endpoint for deleting rewards
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
$reward_id = isset($_POST['reward_id']) ? trim($_POST['reward_id']) : '';

if (empty($reward_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Reward ID is required']);
    exit;
}

// Check if reward exists
$check_query = $conn->prepare("SELECT reward_id FROM reward WHERE reward_id = ?");
if (!$check_query) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$check_query->bind_param("i", $reward_id);
$check_query->execute();
$check_result = $check_query->get_result();

if ($check_result->num_rows === 0) {
    $check_query->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Reward not found']);
    exit;
}

$check_query->close();

// Start transaction to ensure all deletes succeed or all fail
$conn->begin_transaction();

try {
    // Delete from orderdetails first (child records of order)
    $delete_order_details = $conn->prepare("DELETE FROM orderdetails WHERE order_id IN (SELECT order_id FROM `order` WHERE reward_id = ?)");
    if (!$delete_order_details) {
        throw new Exception("Prepare failed for orderdetails: " . $conn->error);
    }
    $delete_order_details->bind_param("i", $reward_id);
    if (!$delete_order_details->execute()) {
        throw new Exception("Failed to delete from orderdetails: " . $delete_order_details->error);
    }
    $delete_order_details->close();

    // Delete from order table (orders using this reward)
    $delete_orders = $conn->prepare("DELETE FROM `order` WHERE reward_id = ?");
    if (!$delete_orders) {
        throw new Exception("Prepare failed for order: " . $conn->error);
    }
    $delete_orders->bind_param("i", $reward_id);
    if (!$delete_orders->execute()) {
        throw new Exception("Failed to delete from order: " . $delete_orders->error);
    }
    $delete_orders->close();

    // Delete from customerrewards table (reward assignments to customers)
    $delete_customer_rewards = $conn->prepare("DELETE FROM customerrewards WHERE reward_id = ?");
    if (!$delete_customer_rewards) {
        throw new Exception("Prepare failed for customerrewards: " . $conn->error);
    }
    $delete_customer_rewards->bind_param("i", $reward_id);
    if (!$delete_customer_rewards->execute()) {
        throw new Exception("Failed to delete from customerrewards: " . $delete_customer_rewards->error);
    }
    $delete_customer_rewards->close();

    // Delete reward from reward table
    $delete_reward = $conn->prepare("DELETE FROM reward WHERE reward_id = ?");
    if (!$delete_reward) {
        throw new Exception("Prepare failed for reward: " . $conn->error);
    }
    $delete_reward->bind_param("i", $reward_id);
    if (!$delete_reward->execute()) {
        throw new Exception("Failed to delete from reward: " . $delete_reward->error);
    }
    $delete_reward->close();

    // Commit transaction
    $conn->commit();
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Reward and related records deleted successfully']);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Transaction failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete reward: ' . $e->getMessage()]);
}
?>