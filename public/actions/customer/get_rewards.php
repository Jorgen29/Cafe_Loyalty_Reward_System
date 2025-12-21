<?php
/**
 * Get rewards for a customer
 * POST: customer_id
 */

session_start();
header('Content-Type: application/json');

// Allow only logged in staff/admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../auth/db_config.php';

$customer_id = isset($_POST['customer_id']) ? trim($_POST['customer_id']) : '';

if (empty($customer_id) || !is_numeric($customer_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid customer id']);
    exit;
}

$query = $conn->prepare(
        "SELECT r.reward_id, r.reward_name, r.reward_type, r.start_date, r.expiration_date, r.points, COALESCE(r.discount_percent,0) AS discount_percent
         FROM customerrewards cr
         JOIN reward r ON cr.reward_id = r.reward_id
         WHERE cr.customer_id = ?
             AND (r.start_date IS NULL OR r.start_date <= CURDATE())
             AND (r.expiration_date IS NULL OR r.expiration_date >= CURDATE())
             AND r.reward_id NOT IN (
                     SELECT DISTINCT reward_id FROM `order` WHERE customer_id = ? AND reward_id IS NOT NULL
             )"
);
if (!$query) {
    error_log('Prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$query->bind_param('ii', $customer_id, $customer_id);
$query->execute();
$res = $query->get_result();
$rewards = [];
while ($row = $res->fetch_assoc()) {
    $rewards[] = $row;
}
$query->close();

http_response_code(200);
echo json_encode(['success' => true, 'rewards' => $rewards]);
?>