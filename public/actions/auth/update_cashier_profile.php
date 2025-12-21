<?php
/**
 * Update Cashier Profile
 */
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Only allow staff/cashier
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'db_config.php';

$user_id = intval($_SESSION['user_id']);
$errors = [];

// Get input data
$firstName = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
$lastName = isset($_POST['lastName']) ? trim($_POST['lastName']) : '';
$newPassword = isset($_POST['newPassword']) ? $_POST['newPassword'] : '';
$confirmPassword = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';

// Validate
if (empty($firstName)) {
    $errors['firstName'] = 'First name is required';
}
if (empty($lastName)) {
    $errors['lastName'] = 'Last name is required';
}

// Check password if provided
if (!empty($newPassword) || !empty($confirmPassword)) {
    if (empty($newPassword) || empty($confirmPassword)) {
        $errors['password'] = 'Both password fields are required';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['password'] = 'Passwords do not match';
    } elseif (strlen($newPassword) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }
}

// Return errors if any
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Update cashier table
$updateCashier = $conn->prepare("UPDATE cashier SET first_name = ?, last_name = ? WHERE user_id = ?");
if (!$updateCashier) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$updateCashier->bind_param('ssi', $firstName, $lastName, $user_id);
if (!$updateCashier->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update cashier profile']);
    $updateCashier->close();
    exit;
}
$updateCashier->close();

// Update user table password if provided
if (!empty($newPassword)) {
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $updatePassword = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
    if (!$updatePassword) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $updatePassword->bind_param('si', $hashedPassword, $user_id);
    if (!$updatePassword->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update password']);
        $updatePassword->close();
        exit;
    }
    $updatePassword->close();
}

// Update session
$_SESSION['first_name'] = $firstName;
$_SESSION['last_name'] = $lastName;

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Profile updated successfully',
    'user' => [
        'firstName' => $firstName,
        'lastName' => $lastName
    ]
]);
?>
