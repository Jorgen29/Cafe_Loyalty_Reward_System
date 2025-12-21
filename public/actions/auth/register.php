<?php
/**
 * User Registration Handler
 * Validates and creates new user accounts
 */

// Prevent display of errors - return JSON instead
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Custom error handler to return JSON errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: $errstr in $errfile on line $errline");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
});

// Custom handler for fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        error_log("Fatal PHP Error: " . $error['message']);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }
});

session_start();

// Include database configuration
require_once 'db_config.php';

// Set content type to JSON for AJAX responses
header('Content-Type: application/json');

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$firstName = trim($_POST['firstName'] ?? '');
$lastName = trim($_POST['lastName'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

// Validation array
$errors = [];

// Validate first name
if (empty($firstName)) {
    $errors['firstName'] = 'First name is required';
} elseif (strlen($firstName) < 2) {
    $errors['firstName'] = 'First name must be at least 2 characters';
} elseif (!preg_match('/^[a-zA-Z\s\'-]+$/', $firstName)) {
    $errors['firstName'] = 'First name can only contain letters, spaces, hyphens, and apostrophes';
}

// Validate last name
if (empty($lastName)) {
    $errors['lastName'] = 'Last name is required';
} elseif (strlen($lastName) < 2) {
    $errors['lastName'] = 'Last name must be at least 2 characters';
} elseif (!preg_match('/^[a-zA-Z\s\'-]+$/', $lastName)) {
    $errors['lastName'] = 'Last name can only contain letters, spaces, hyphens, and apostrophes';
}

// Validate email
if (empty($email)) {
    $errors['email'] = 'Email address is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address';
}

// Validate password
if (empty($password)) {
    $errors['password'] = 'Password is required';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters';
} elseif (!preg_match('/[A-Z]/', $password)) {
    $errors['password'] = 'Password must contain at least one uppercase letter';
} elseif (!preg_match('/[0-9]/', $password)) {
    $errors['password'] = 'Password must contain at least one number';
} elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
    $errors['password'] = 'Password must contain at least one special character';
}

// Validate confirm password
if (empty($confirmPassword)) {
    $errors['confirmPassword'] = 'Please confirm your password';
} elseif ($password !== $confirmPassword) {
    $errors['confirmPassword'] = 'Passwords do not match';
}

// Return validation errors
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Check if email already exists
$checkEmail = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
$checkEmail->bind_param("s", $email);
$checkEmail->execute();
$result = $checkEmail->get_result();

if ($result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Email address already registered']);
    exit;
}

$checkEmail->close();

// Hash password with SHA256
$hashedPassword = hash('sha256', $password);

// Insert new user into user table
$insertUser = $conn->prepare("INSERT INTO user (email, password, role) VALUES (?, ?, 'user')");
$insertUser->bind_param("ss", $email, $hashedPassword);

if (!$insertUser->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error creating user account']);
    $insertUser->close();
    exit;
}

$userId = $conn->insert_id;
$insertUser->close();

// Insert customer profile
$insertCustomer = $conn->prepare("INSERT INTO customer (user_id, first_name, last_name, email, tier_level, date_joined) VALUES (?, ?, ?, ?, 'Normal', CURDATE())");
$insertCustomer->bind_param("isss", $userId, $firstName, $lastName, $email);

if (!$insertCustomer->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error creating customer profile']);
    $insertCustomer->close();
    exit;
}

$insertCustomer->close();

// Success response
http_response_code(201);
echo json_encode([
    'success' => true,
    'message' => 'Account created successfully! Redirecting to login...',
    'redirect' => 'index.html'
]);

$conn->close();
?>
