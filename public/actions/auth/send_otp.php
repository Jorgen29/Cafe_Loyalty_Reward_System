<?php
// Endpoint: public/actions/auth/send_otp.php
// POST: email
// Generates a 6-digit OTP, stores a hashed value in `otp_codes`, and sends the OTP via email using PHPMailer.

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address']);
    exit;
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mail_config.php';

// Find user by email
$userStmt = $conn->prepare("SELECT user_id, first_name, email FROM `user` WHERE email = ? LIMIT 1");
if (!$userStmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$userStmt->bind_param('s', $email);
$userStmt->execute();
$ures = $userStmt->get_result();
$user = $ures->fetch_assoc();
$userStmt->close();

// To avoid account enumeration, respond success even if user not found.
if (!$user) {
    // But do not attempt sending mail
    echo json_encode(['success' => true, 'message' => 'If that email exists, an OTP has been sent.']);
    exit;
}

$userId = (int)$user['user_id'];
$firstName = $user['first_name'] ?? '';

// Ensure otp_codes table exists
$createSql = "CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

// Generate 6-digit OTP
$otp = random_int(0, 999999);
$otp = str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
$expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes expiry
$codeHash = password_hash($otp, PASSWORD_DEFAULT);

// Store hashed otp
$ins = $conn->prepare("INSERT INTO otp_codes (user_id, code_hash, expires_at) VALUES (?, ?, ?)");
if ($ins) {
    $ins->bind_param('iss', $userId, $codeHash, $expiresAt);
    $ins->execute();
    $ins->close();
}

// Attempt to send email via PHPMailer
try {
    if (!file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
        // PHPMailer not installed; log and return success message
        error_log('PHPMailer missing: vendor/autoload.php');
        echo json_encode(['success' => true, 'message' => 'OTP generated. Install PHPMailer to enable sending emails.']);
        exit;
    }

    require_once __DIR__ . '/../../../vendor/autoload.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    // SMTP configuration from mail_config.php (placeholders)
    // If your SMTP provider requires different options, edit mail_config.php
    if (defined('SMTP_HOST') && SMTP_HOST && SMTP_HOST !== 'smtp.example.com') {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $secure = SMTP_SECURE ?? 'tls';
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = SMTP_PORT;
    }

    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress($user['email'], $firstName ?: '');
    $mail->isHTML(true);
    $mail->Subject = 'Your verification code';
    $mail->Body = '<p>Hello ' . htmlspecialchars($firstName) . ',</p>'
        . '<p>Your verification code is <strong>' . htmlspecialchars($otp) . '</strong>. It expires in 5 minutes.</p>'
        . '<p>If you did not request this, please ignore this email.</p>';

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'OTP sent to email if it exists']);
    exit;
} catch (Exception $e) {
    error_log('send_otp email error: ' . $e->getMessage());
    // Still return success to avoid enumeration
    echo json_encode(['success' => true, 'message' => 'OTP generated. Email sending failed - check SMTP settings.']);
    exit;
}
