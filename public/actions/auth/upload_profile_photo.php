<?php
/**
 * Upload profile photo for logged-in user
 * - saves file to public/assets/images/profiles/
 * - ensures `image_path` column exists on `customer` table (adds if missing)
 * - updates `customer.image_path` WHERE user_id = $_SESSION['user_id']
 * - returns JSON { success, message, file_path }
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$image = $_FILES['profile_photo'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

if (!in_array($image['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed: JPG, PNG, GIF, WEBP']);
    exit;
}

if ($image['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Image file too large (max 5MB)']);
    exit;
}

// Create upload dir (resolve from this auth directory to public/assets/images/profiles)
// __DIR__ is .../public/actions/auth so go up two levels to reach .../public
$upload_dir = __DIR__ . '/../../assets/images/profiles/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
}

// Generate filename
$ext = pathinfo($image['name'], PATHINFO_EXTENSION);
$filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$target = $upload_dir . $filename;

if (!move_uploaded_file($image['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    exit;
}

// Relative web path used in frontend
$relative_path = '../../public/assets/images/profiles/' . $filename;

// Update DB (customer.image_path) if possible
// db_config.php lives in the same `auth` directory
require_once __DIR__ . '/db_config.php';

$userId = $_SESSION['user_id'];

// Check if customer table has image_path column
$hasColumn = false;
$colStmt = $conn->prepare("SHOW COLUMNS FROM customer LIKE 'image_path'");
if ($colStmt) {
    $colStmt->execute();
    $colStmt->store_result();
    if ($colStmt->num_rows > 0) $hasColumn = true;
    $colStmt->close();
}

if (!$hasColumn) {
    // Try to add column
    $alter = "ALTER TABLE customer ADD COLUMN image_path VARCHAR(255) DEFAULT NULL";
    $conn->query($alter);
}

// Update customer record if exists
$update = $conn->prepare("UPDATE customer SET image_path = ? WHERE user_id = ?");
if ($update) {
    $update->bind_param('si', $relative_path, $userId);
    if (!$update->execute()) {
        // Not fatal — return success for file save but warn
        error_log('Failed to update customer image_path: ' . $conn->error);
        echo json_encode(['success' => true, 'message' => 'Photo uploaded but failed to save to profile', 'file_path' => $relative_path]);
        $update->close();
        $conn->close();
        // Update session anyway
        $_SESSION['profile_image'] = $relative_path;
        exit;
    }
    $update->close();
} else {
    // Could not prepare update — continue but warn
    error_log('Failed to prepare customer update: ' . $conn->error);
}

$conn->close();

// Update session so pages can show immediately
$_SESSION['profile_image'] = $relative_path;

echo json_encode(['success' => true, 'message' => 'Profile photo uploaded', 'file_path' => $relative_path]);
exit;
?>