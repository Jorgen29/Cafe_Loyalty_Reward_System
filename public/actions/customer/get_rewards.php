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

// Fetch customer birthday
$birthday_query = $conn->prepare("SELECT birthday FROM customer WHERE customer_id = ? LIMIT 1");
$customer_birthday = null;
if ($birthday_query) {
    $birthday_query->bind_param('i', $customer_id);
    $birthday_query->execute();
    $birthday_result = $birthday_query->get_result();
    if ($birthday_row = $birthday_result->fetch_assoc()) {
        $customer_birthday = $birthday_row['birthday'];
    }
    $birthday_query->close();
}

$points_query = $conn->prepare("SELECT points FROM customer WHERE customer_id = ? LIMIT 1");
$customer_points = 0;

if ($points_query) {
    $points_query->bind_param('i', $customer_id);
    $points_query->execute();
    $points_result = $points_query->get_result();
    if ($points_row = $points_result->fetch_assoc()) {
        $customer_points = $points_row['points'];
    }
    $points_query->close();
}


$query = $conn->prepare(
        "SELECT r.reward_id, r.reward_name, r.reward_type, r.start_date, r.expiration_date, r.points, COALESCE(r.discount_percent,0) AS discount_percent, COALESCE(r.discount_amount,0) AS discount_amount
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

// Check if today is customer's birthday
$is_birthday = false;
if ($customer_birthday) {
    // Extract month and day from birthday (YYYY-MM-DD format)
    $birthday_parts = explode('-', $customer_birthday);
    if (count($birthday_parts) === 3) {
        $birthday_month = $birthday_parts[1];
        $birthday_day = $birthday_parts[2];
        $today_month = date('m');
        $today_day = date('d');
        
        if ($birthday_month === $today_month && $birthday_day === $today_day) {
            $is_birthday = true;
        }
    }
}

http_response_code(200);
echo json_encode(['success' => true, 'rewards' => $rewards, 'is_birthday' => $is_birthday, 'birthday' => $customer_birthday]);
?>