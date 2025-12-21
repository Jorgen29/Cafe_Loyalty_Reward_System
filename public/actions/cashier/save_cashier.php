<?php
/**
 * Save Cashier Handler (Create/Update)
 * POST endpoint for creating or updating cashiers
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
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$store_id = isset($_POST['store_id']) && $_POST['store_id'] !== '' ? (int)$_POST['store_id'] : null;

// Validation
if (empty($first_name) || empty($last_name) || empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Check if updating or creating
if (!empty($cashier_id)) {
    // Update existing cashier
    if ($store_id !== null) {
        $query = $conn->prepare("UPDATE cashier SET first_name = ?, last_name = ?, store_id = ? WHERE cashier_id = ?");
    } else {
        $query = $conn->prepare("UPDATE cashier SET first_name = ?, last_name = ? WHERE cashier_id = ?");
    }
    if (!$query) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    if ($store_id !== null) {
        $query->bind_param("ssii", $first_name, $last_name, $store_id, $cashier_id);
    } else {
        $query->bind_param("ssi", $first_name, $last_name, $cashier_id);
    }
    $result = $query->execute();
    
    if (!$result) {
        error_log("Execute failed: " . $query->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update cashier']);
        exit;
    }
    $query->close();

    // Update user email if provided
    if (!empty($email)) {
        // Get user_id from cashier
        $getUserQuery = $conn->prepare("SELECT user_id FROM cashier WHERE cashier_id = ?");
        $getUserQuery->bind_param("i", $cashier_id);
        $getUserQuery->execute();
        $userResult = $getUserQuery->get_result();
        $userRow = $userResult->fetch_assoc();
        $getUserQuery->close();

        if ($userRow) {
            $userId = $userRow['user_id'];
            
            // Update password if provided
            if (!empty($password)) {
                $hashedPassword = hash('sha256', $password);
                $updateUserQuery = $conn->prepare("UPDATE user SET email = ?, password = ? WHERE user_id = ?");
                $updateUserQuery->bind_param("ssi", $email, $hashedPassword, $userId);
            } else {
                $updateUserQuery = $conn->prepare("UPDATE user SET email = ? WHERE user_id = ?");
                $updateUserQuery->bind_param("si", $email, $userId);
            }
            
            $updateUserQuery->execute();
            $updateUserQuery->close();
        }
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Cashier updated successfully']);
} else {
    // Create new cashier
    if (empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password is required for new cashiers']);
        exit;
    }

    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkResult = $checkEmail->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkEmail->close();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    $checkEmail->close();

    // Create user first
    $hashedPassword = hash('sha256', $password);
    $role = 'staff';
    
    $createUserQuery = $conn->prepare("INSERT INTO user (email, password, role) VALUES (?, ?, ?)");
    if (!$createUserQuery) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $createUserQuery->bind_param("sss", $email, $hashedPassword, $role);
    $result = $createUserQuery->execute();
    
    if (!$result) {
        error_log("Execute failed: " . $createUserQuery->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create user account']);
        exit;
    }
    
    $userId = $createUserQuery->insert_id;
    $createUserQuery->close();

    // Create cashier
    if ($store_id !== null) {
        $createCashierQuery = $conn->prepare("INSERT INTO cashier (user_id, first_name, last_name, store_id) VALUES (?, ?, ?, ?)");
    } else {
        $createCashierQuery = $conn->prepare("INSERT INTO cashier (user_id, first_name, last_name) VALUES (?, ?, ?)");
    }
    if (!$createCashierQuery) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    if ($store_id !== null) {
        $createCashierQuery->bind_param("issi", $userId, $first_name, $last_name, $store_id);
    } else {
        $createCashierQuery->bind_param("iss", $userId, $first_name, $last_name);
    }
    $result = $createCashierQuery->execute();
    
    if (!$result) {
        error_log("Execute failed: " . $createCashierQuery->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create cashier']);
        exit;
    }
    
    $createCashierQuery->close();
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Cashier created successfully']);
}
?>
