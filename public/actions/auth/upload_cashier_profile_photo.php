<?php
/**
 * Upload Cashier Profile Photo
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

// Check if file was uploaded
if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['profile_photo'];
$user_id = intval($_SESSION['user_id']);

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit;
}

// Validate file size (5MB max)
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File too large']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = __DIR__ . '/../../assets/images/profiles/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'profile_' . $user_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

// Store relative path for web access
$relative_path = '../../public/assets/images/profiles/' . $filename;

// Check if image_path column exists in user table
$check_column = $conn->query("SHOW COLUMNS FROM user LIKE 'image_path'");
if ($check_column->num_rows === 0) {
    // Add column if it doesn't exist
    $alter_query = "ALTER TABLE user ADD COLUMN image_path VARCHAR(255) DEFAULT NULL";
    if (!$conn->query($alter_query)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

// Update user table with image path
$update_query = $conn->prepare("UPDATE user SET image_path = ? WHERE user_id = ?");
if (!$update_query) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$update_query->bind_param('si', $relative_path, $user_id);
if (!$update_query->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    $update_query->close();
    exit;
}
$update_query->close();

// Update session
$_SESSION['profile_image'] = $relative_path;

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Profile photo uploaded successfully',
    'file_path' => $relative_path
]);
?>
