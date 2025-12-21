<?php
// Endpoint: public/actions/auth/forgot_password.php
// Accepts POST: email
// Creates a password reset token, stores it in password_resets table, and sends an email via PHPMailer

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email']);
    exit;
}

require_once __DIR__ . '/db_config.php';

// Look up user by email
$userStmt = $conn->prepare("SELECT user_id, email FROM `user` WHERE email = ? LIMIT 1");
if (!$userStmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$userStmt->bind_param('s', $email);
$userStmt->execute();
$ures = $userStmt->get_result();
$user = $ures->fetch_assoc();
$userStmt->close();

if (!$user) {
    // Don't reveal that the email doesn't exist. Respond success.
    echo json_encode(['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);
    exit;
}

// Switch to OTP flow: generate 6-digit code, store hashed value in otp_codes, and email only the code
$userId = (int)$user['user_id'];

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

// Send email via PHPMailer with only the OTP (no reset link)
try {
    if (!file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
        error_log('PHPMailer missing: vendor/autoload.php');
        echo json_encode(['success' => true, 'message' => 'OTP generated. Install PHPMailer to enable sending emails.']);
        exit;
    }

    require_once __DIR__ . '/../../../vendor/autoload.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    require_once __DIR__ . '/mail_config.php';

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

    $fromEmail = defined('FROM_EMAIL') ? FROM_EMAIL : 'no-reply@example.com';
    $fromName = defined('FROM_NAME') ? FROM_NAME : 'Cups & Stories Cafe';

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Cups & Stories Cafe Recovery Code';
    // Styled HTML message with logo
  
    // preferred local path (absolute)
    $localLogo = __DIR__ . '/../../assets/css/images/logo images/logoName.png';
    
    // Try to embed the local image; fallback to remote URL
    $imgTag = '';
    if (file_exists($localLogo)) {
        // embed with content id
        $mail->addEmbeddedImage($localLogo, 'logo_cid');
        $imgTag = '<img src="cid:logo_cid" alt="Cups & Stories Cafe" class="logo">';
    } else {
        // fallback to a public URL (encode spaces as %20 or rename file to remove spaces)
        $remote = 'https://cupsandstoriescafe.shop/public/assets/css/images/logo%20images/logoName.png';
        $imgTag = '<img src="' . htmlspecialchars($remote) . '" alt="Cups & Stories Cafe" class="logo">';
    }
    
    // then use $imgTag in your HTML body:
    $mail->Body =  /* rest of HTML */ 
         '<!doctype html>'
        . '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<style>body{font-family:Arial,Helvetica,sans-serif;background:#f6f6f6;margin:0;padding:0} .container{max-width:520px;margin:24px auto;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e9e9e9} .header{background:#fff;padding:18px;text-align:center} .logo{max-width:180px;height:auto} .content{padding:24px;color:#333} .otp{display:block;margin:18px auto;padding:12px 18px;border-radius:6px;background:#faf7f3;color:#6b4423;font-weight:700;font-size:28px;text-align:center;letter-spacing:4px;width:fit-content} .small{color:#666;font-size:14px} .footer{padding:16px;text-align:center;color:#999;font-size:13px;background:#fff;border-top:1px solid #f0f0f0}</style>'
        . '</head><body><div class="container"><div class="header">' . $imgTag . '</div>'
        . '<div class="content">'
        . '<p class="small">Hello ' . htmlspecialchars($user['first_name'] ?? '') . ',</p>'
        . '<p class="small">Use the verification code below to reset your password. The code will expire in 5 minutes.</p>'
        . '<div class="otp">' . htmlspecialchars($otp) . '</div>'
        . '<p class="small">If you did not request a password reset, you can safely ignore this email.</p>'
        . '</div><div class="footer">Cups & Stories Cafe &middot; <a href="https://cupsandstoriescafe.shop" style="color:#999;text-decoration:none;">cupsandstoriescafe.shop</a></div></div></body></html>';
    // Plain-text alternative
    $mail->AltBody = 'Hello ' . ($user['first_name'] ?? '') . ",\n\nYour verification code is: " . $otp . "\nThis code expires in 5 minutes.\n\nIf you did not request this, ignore this message.\n\nCups & Stories Cafe - cupsandstoriescafe.shop";

    $debugMode = (isset($_POST['debug']) && $_POST['debug'] === '1');
    if ($debugMode) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("[PHPMailer DEBUG] level={$level} msg=" . $str);
        };
    }

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'If that email exists, an OTP has been sent.']);
    exit;
} catch (Exception $e) {
    error_log('Forgot password email error: ' . $e->getMessage());
    echo json_encode(['success' => true, 'message' => 'OTP generated. Email sending failed - check PHPMailer setup.']);
    exit;
}
