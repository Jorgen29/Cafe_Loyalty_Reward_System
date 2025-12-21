<?php
/**
 * User Login Handler
 * Authenticates users and creates sessions
 */

// Prevent display of errors - return JSON instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Create a log file for debugging
$logFile = __DIR__ . '/../../logs/auth_error.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}

// Custom error handler to return JSON errors
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logFile) {
    $msg = "PHP Error [$errno]: $errstr in $errfile on line $errline";
    error_log($msg . "\n", 3, $logFile);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

// Custom handler for fatal errors
register_shutdown_function(function() use ($logFile) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $msg = "Fatal PHP Error [{$error['type']}]: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
        error_log($msg . "\n", 3, $logFile);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $error['message']]);
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
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validation
$errors = [];

if (empty($email)) {
    $errors['email'] = 'Email address is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address';
}

if (empty($password)) {
    $errors['password'] = 'Password is required';
}

// Return validation errors
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Query database for user
$query = $conn->prepare("SELECT user_id, email, password, role FROM user WHERE email = ?");
$query->bind_param("s", $email);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    $query->close();
    exit;
}

$user = $result->fetch_assoc();
$query->close();

// Verify password.
// Support both legacy SHA256-stored passwords and modern password_hash() values.
$storedHash = $user['password'];
$passwordOk = false;

// If stored hash looks like a password_hash() output, try password_verify()
if (password_get_info($storedHash)['algo'] !== 0) {
    if (password_verify($password, $storedHash)) {
        $passwordOk = true;
    }
}

// Fallback: legacy SHA256 comparison
if (!$passwordOk) {
    if (hash('sha256', $password) === $storedHash) {
        $passwordOk = true;
        // Migrate to password_hash() for better security
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
            if ($up) {
                $up->bind_param('si', $newHash, $user['user_id']);
                $up->execute();
                $up->close();
            }
        } catch (Exception $e) {
            // Log but don't prevent login
            error_log('Password migration failed for user_id ' . $user['user_id'] . ': ' . $e->getMessage());
        }
    }
}

if (!$passwordOk) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit;
}

// Get customer profile for user (only for 'user' role)
$customer = null;
if (($user['role'] ?? null) === 'user') {
    $getCustomer = $conn->prepare("SELECT customer_id, first_name, last_name, date_joined, birthday, address, sex, occupation, image_path FROM customer WHERE user_id = ?");
    $getCustomer->bind_param("i", $user['user_id']);
    $getCustomer->execute();
    $customerResult = $getCustomer->get_result();
    $customer = $customerResult->fetch_assoc();
    $getCustomer->close();
    
    // If no customer profile exists for a user, create default one
    if ($customer === null) {
        $customer = [
            'customer_id' => null,
            'first_name' => '',
            'last_name' => '',
            'date_joined' => date('Y-m-d'),
            'birthday' => '',
            'address' => '',
            'sex' => '',
            'occupation' => '',
            'image_path' => ''
        ];
    }
} else {
    // For admin/staff, get image_path from user table
    $getAdmin = $conn->prepare("SELECT image_path FROM user WHERE user_id = ?");
    $getAdmin->bind_param("i", $user['user_id']);
    $getAdmin->execute();
    $adminResult = $getAdmin->get_result();
    $adminData = $adminResult->fetch_assoc();
    $getAdmin->close();
    
    $customer = [
        'customer_id' => null,
        'first_name' => '',
        'last_name' => '',
        'date_joined' => date('Y-m-d'),
        'birthday' => '',
        'address' => '',
        'sex' => '',
        'occupation' => '',
        'image_path' => $adminData['image_path'] ?? ''
    ];
}

// Set session variables
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];
$_SESSION['customer_id'] = $customer['customer_id'] ?? null;
$_SESSION['first_name'] = $customer['first_name'] ?? '';
$_SESSION['last_name'] = $customer['last_name'] ?? '';
// Store additional profile fields in session for convenience
$_SESSION['date_joined'] = $customer['date_joined'] ?? '';
$_SESSION['birthday'] = $customer['birthday'] ?? '';
// Store address/sex/occupation in session so profile page can populate fields
$_SESSION['address'] = $customer['address'] ?? '';
$_SESSION['sex'] = $customer['sex'] ?? '';
$_SESSION['occupation'] = $customer['occupation'] ?? '';
$_SESSION['profile_image'] = $customer['image_path'] ?? '';


// Determine redirect based on role
$redirect = 'pages/user/home.php';
if ($user['role'] === 'admin') {
    $redirect = 'pages/admin/admin.php';
} elseif ($user['role'] === 'staff') {
    $redirect = 'pages/cashier/cashier.php';
}

// Success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Login successful!',
    'redirect' => $redirect,
    'user' => [
        'id' => $user['user_id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'name' => $customer['first_name'] . ' ' . $customer['last_name']
    ]
]);

$conn->close();
?>
