<?php
/**
 * Save Reward Handler (Create/Update)
 * POST endpoint for creating or updating rewards
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
$reward_name = isset($_POST['reward_name']) ? trim($_POST['reward_name']) : '';
$reward_type = isset($_POST['reward_type']) ? trim($_POST['reward_type']) : '';
$start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
$expiration_date = isset($_POST['expiration_date']) ? trim($_POST['expiration_date']) : '';
$points = isset($_POST['points']) ? trim($_POST['points']) : '';
$discount_type = isset($_POST['discount_type']) ? trim($_POST['discount_type']) : 'Percentage';
$discount_percent = isset($_POST['discount_percent']) ? trim($_POST['discount_percent']) : '0';
$discount_amount = isset($_POST['discount_amount']) ? trim($_POST['discount_amount']) : '0';

// Basic validation
if (empty($reward_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Reward name is required']);
    exit;
}

if ($points !== '' && !is_numeric($points)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Points must be a number']);
    exit;
}

if (!is_numeric($discount_percent)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Discount percent must be a number']);
    exit;
}

if (!is_numeric($discount_amount)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Discount amount must be a number']);
    exit;
}

$pointsVal = ($points === '') ? 0 : (int)$points;
$discount_percentage_val = (float)$discount_percent;
$discount_amount_val = (float)$discount_amount;

// Normalize dates
$start_date_val = ($start_date === '') ? null : $start_date;
$expiration_date_val = ($expiration_date === '') ? null : $expiration_date;

if (!empty($reward_id)) {
    // Update existing reward
    // Ensure columns exist (runtime migration)
    $checkCol = $conn->query("SHOW COLUMNS FROM reward LIKE 'discount_percent'");
    if ($checkCol && $checkCol->num_rows === 0) {
        $conn->query("ALTER TABLE reward ADD COLUMN discount_percent FLOAT DEFAULT NULL");
    }
    $checkCol = $conn->query("SHOW COLUMNS FROM reward LIKE 'discount_amount'");
    if ($checkCol && $checkCol->num_rows === 0) {
        $conn->query("ALTER TABLE reward ADD COLUMN discount_amount FLOAT DEFAULT NULL");
    }

    $query = $conn->prepare("UPDATE reward SET reward_name = ?, reward_type = ?, start_date = ?, expiration_date = ?, points = ?, discount_percent = ?, discount_amount = ? WHERE reward_id = ?");
    if (!$query) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    $query->bind_param("ssssiddi", $reward_name, $reward_type, $start_date_val, $expiration_date_val, $pointsVal, $discount_percentage_val, $discount_amount_val, $reward_id);
    $result = $query->execute();

    if (!$result) {
        error_log("Execute failed: " . $query->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update reward']);
        exit;
    }
    $query->close();

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Reward updated successfully']);
} else {
    // Create new reward
    // Ensure columns exist (runtime migration)
    $checkCol = $conn->query("SHOW COLUMNS FROM reward LIKE 'discount_percent'");
    if ($checkCol && $checkCol->num_rows === 0) {
        $conn->query("ALTER TABLE reward ADD COLUMN discount_percent FLOAT DEFAULT NULL");
    }
    $checkCol = $conn->query("SHOW COLUMNS FROM reward LIKE 'discount_amount'");
    if ($checkCol && $checkCol->num_rows === 0) {
        $conn->query("ALTER TABLE reward ADD COLUMN discount_amount FLOAT DEFAULT NULL");
    }

    $query = $conn->prepare("INSERT INTO reward (reward_name, reward_type, start_date, expiration_date, points, discount_percent, discount_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$query) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    $query->bind_param("ssssidd", $reward_name, $reward_type, $start_date_val, $expiration_date_val, $pointsVal, $discount_percentage_val, $discount_amount_val);
    $result = $query->execute();

    if (!$result) {
        error_log("Execute failed: " . $query->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create reward']);
        exit;
    }

    $query->close();
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Reward created successfully']);
}
?>