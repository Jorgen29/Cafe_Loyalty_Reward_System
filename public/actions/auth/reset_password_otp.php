<?php
// Endpoint: public/actions/auth/reset_password_otp.php
// POST: newPassword, confirmPassword

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$newPassword = $_POST['newPassword'] ?? '';
$confirm = $_POST['confirmPassword'] ?? '';

if (empty($newPassword) || empty($confirm)) {
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

if (empty($_SESSION['otp_verified_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No verified OTP session found']);
    exit;
}

$userId = (int)$_SESSION['otp_verified_user_id'];

require_once __DIR__ . '/db_config.php';

$hashed = password_hash($newPassword, PASSWORD_DEFAULT);
$up = $conn->prepare("UPDATE `user` SET password = ? WHERE user_id = ?");
if (!$up) { echo json_encode(['success' => false, 'message' => 'Failed to update password']); exit; }
$up->bind_param('si', $hashed, $userId);
if (!$up->execute()) { $up->close(); echo json_encode(['success' => false, 'message' => 'Failed to update password']); exit; }
$up->close();

// Remove any leftover otp rows for this user
$del = $conn->prepare("DELETE FROM otp_codes WHERE user_id = ?");
if ($del) { $del->bind_param('i', $userId); $del->execute(); $del->close(); }

// Clear session verification flag
unset($_SESSION['otp_verified_user_id']);

echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
exit;

?>
