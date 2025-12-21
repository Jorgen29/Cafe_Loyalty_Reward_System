<?php
// Endpoint: public/actions/auth/reset_password.php
// Accepts POST: token, newPassword, confirmPassword

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$token = $_POST['token'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';
$confirm = $_POST['confirmPassword'] ?? '';

if (empty($token) || empty($newPassword) || empty($confirm)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}
if ($newPassword !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}
if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

require_once __DIR__ . '/db_config.php';

// Find token
$stmt = $conn->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit;
}

$expires = $row['expires_at'];
if (strtotime($expires) < time()) {
    echo json_encode(['success' => false, 'message' => 'Token has expired']);
    exit;
}

$userId = (int)$row['user_id'];

// Update user password
$hashed = password_hash($newPassword, PASSWORD_DEFAULT);
$up = $conn->prepare("UPDATE `user` SET password = ? WHERE user_id = ?");
if (!$up) {
    echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    exit;
}
$up->bind_param('si', $hashed, $userId);
if (!$up->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    $up->close();
    exit;
}
$up->close();

// Remove all reset tokens for this user
$del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
if ($del) {
    $del->bind_param('i', $userId);
    $del->execute();
    $del->close();
}

echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
exit;
