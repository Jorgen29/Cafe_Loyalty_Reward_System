<?php
/**
 * Page Image Upload Handler
 * Uploads images to different directories based on page type
 */

session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get page type
$page_type = isset($_POST['page_type']) ? trim($_POST['page_type']) : 'home';

// Validate page type
$valid_pages = ['home', 'menu', 'reward'];
if (!in_array($page_type, $valid_pages)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid page type']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image file uploaded or upload error']);
    exit;
}

$image_file = $_FILES['image'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

// Validate file type
if (!in_array($image_file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid image format. Only JPG, PNG, GIF, WEBP are allowed']);
    exit;
}

// Validate file size
if ($image_file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Image file too large. Maximum 5MB allowed']);
    exit;
}

// Map page type to directory
$page_dir_map = [
    'home' => 'home_page',
    'menu' => 'menu_page',
    'reward' => 'reward_page'
];

$page_dir = $page_dir_map[$page_type];

// Create upload directory if not exists
$upload_dir = __DIR__ . '/../../assets/page_image/' . $page_dir . '/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_ext = pathinfo($image_file['name'], PATHINFO_EXTENSION);
$filename = 'page_' . $page_type . '_' . time() . '_' . uniqid() . '.' . $file_ext;
$upload_path = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($image_file['tmp_name'], $upload_path)) {
    error_log("Failed to upload page image: " . $image_file['name']);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
    exit;
}

// Return relative path for frontend
$relative_path = '../../public/assets/page_image/' . $page_dir . '/' . $filename;

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Image uploaded successfully',
    'file_path' => $relative_path,
    'file_name' => $filename
]);
?>
