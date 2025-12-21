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

// Delete reward
$query = $conn->prepare("DELETE FROM reward WHERE reward_id = ?");
if (!$query) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$query->bind_param("i", $reward_id);
$result = $query->execute();

if (!$result) {
    error_log("Execute failed: " . $query->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete reward']);
    exit;
}

$query->close();
http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Reward deleted successfully']);
?>