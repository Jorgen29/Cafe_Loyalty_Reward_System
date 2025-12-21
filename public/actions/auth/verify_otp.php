<?php
// Endpoint: public/actions/auth/verify_otp.php
// POST: email OR user_id, otp

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$otp = trim($_POST['otp'] ?? '');

if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid 6-digit code']);
    exit;
}

require_once __DIR__ . '/db_config.php';

if (!$userId) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Missing email or user id']);
        exit;
    }
    $s = $conn->prepare("SELECT user_id FROM `user` WHERE email = ? LIMIT 1");
    if (!$s) { echo json_encode(['success'=>false,'message'=>'Database error']); exit; }
    $s->bind_param('s', $email);
    $s->execute();
    $r = $s->get_result();
    $u = $r->fetch_assoc();
    $s->close();
    if (!$u) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $userId = (int)$u['user_id'];
}

// Find most recent non-expired OTP for this user
$q = $conn->prepare("SELECT id, code_hash, expires_at FROM otp_codes WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
if (!$q) { echo json_encode(['success'=>false,'message'=>'Database error']); exit; }
$q->bind_param('i', $userId);
$q->execute();
$res = $q->get_result();
$row = $res->fetch_assoc();
$q->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Code not found or expired']);
    exit;
}

if (strtotime($row['expires_at']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Code has expired']);
    exit;
}

if (!password_verify($otp, $row['code_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid code']);
    exit;
}

// Delete all OTPs for this user (single-use)
$del = $conn->prepare("DELETE FROM otp_codes WHERE user_id = ?");
if ($del) { $del->bind_param('i', $userId); $del->execute(); $del->close(); }

// Mark session as verified so subsequent reset step can proceed
$_SESSION['otp_verified_user_id'] = $userId;

echo json_encode(['success' => true, 'message' => 'Code verified']);
exit;

?>
