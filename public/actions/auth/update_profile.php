<?php
/**
 * Update User Profile Handler
 * Updates customer profile information (excluding email)
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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$firstName = trim($_POST['firstName'] ?? '');
$lastName = trim($_POST['lastName'] ?? '');
$birthday = trim($_POST['birthday'] ?? '');
$address = trim($_POST['address'] ?? '');
$sex = trim($_POST['sex'] ?? '');
$occupation = trim($_POST['occupation'] ?? '');
$newPassword = $_POST['newPassword'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

// Track whether password was changed so frontend can react
$passwordChanged = false;

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

// Validate birthday (optional but if provided, must be valid date)
if (!empty($birthday)) {
    if (!strtotime($birthday)) {
        $errors['birthday'] = 'Please enter a valid date';
    }
}

// Validate address
if (empty($address)) {
    $errors['address'] = 'Address is required';
} elseif (strlen($address) < 5) {
    $errors['address'] = 'Address must be at least 5 characters';
}

// Validate sex
if (!empty($sex) && !in_array($sex, ['Male', 'Female', 'Prefer not to say'])) {
    $errors['sex'] = 'Invalid sex selection';
}

// Validate occupation
if (!empty($occupation) && strlen($occupation) < 2) {
    $errors['occupation'] = 'Occupation must be at least 2 characters if provided';
}

// Validate password change (both or neither)
if (!empty($newPassword) || !empty($confirmPassword)) {
    if (strlen($newPassword) < 8) {
        $errors['newPassword'] = 'New password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $errors['newPassword'] = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $errors['newPassword'] = 'Password must contain at least one number';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
        $errors['newPassword'] = 'Password must contain at least one special character';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match';
    }
}

// Return validation errors
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Update customer profile
$updateQuery = "UPDATE customer SET first_name = ?, last_name = ?, birthday = ?, address = ?, sex = ?, occupation = ? WHERE user_id = ?";
$updateCustomer = $conn->prepare($updateQuery);
$updateCustomer->bind_param("ssssssi", $firstName, $lastName, $birthday, $address, $sex, $occupation, $_SESSION['user_id']);

if (!$updateCustomer->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error updating profile']);
    $updateCustomer->close();
    exit;
}

$updateCustomer->close();

// Update password if provided
if (!empty($newPassword)) {
    $hashedPassword = hash('sha256', $newPassword);
    $updatePassword = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
    $updatePassword->bind_param("si", $hashedPassword, $_SESSION['user_id']);
    
    if (!$updatePassword->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating password']);
        $updatePassword->close();
        exit;
    }
    
    $updatePassword->close();

    // mark as changed
    $passwordChanged = true;
}

// Update session variables
$_SESSION['first_name'] = $firstName;
$_SESSION['last_name'] = $lastName;
// Update birthday in session if provided (keep blank if empty)
$_SESSION['birthday'] = $birthday ?? '';
// Update other profile fields in session
$_SESSION['address'] = $address ?? '';
$_SESSION['sex'] = $sex ?? '';
$_SESSION['occupation'] = $occupation ?? '';

// Success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Profile updated successfully!',
    'user' => [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'birthday' => $birthday,
        'address' => $address,
        'sex' => $sex,
        'occupation' => $occupation
    ]
    , 'password_changed' => $passwordChanged
]);

$conn->close();
?>
