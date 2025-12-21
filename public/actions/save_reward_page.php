<?php
/**
 * Reward Page Assets Save Handler
 * Saves reward page content and images to home_page_assets table with category='Rewards'
 */

session_start();
header('Content-Type: application/json');

// Set error handling to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include database configuration
require_once 'auth/db_config.php';

// Check if connection failed
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

// Get POST data
$cover_text = isset($_POST['cover_text']) ? trim($_POST['cover_text']) : '';
$cover_image = isset($_POST['cover_image']) ? trim($_POST['cover_image']) : null;
$category = 'Rewards';

// Handle image uploads
if (isset($_FILES['cover_image_file']) && $_FILES['cover_image_file']['error'] === UPLOAD_ERR_OK) {
    $cover_image = uploadImage($_FILES['cover_image_file'], 'reward_page');
    if (!$cover_image) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to upload cover image']);
        exit;
    }
}

// Check if record exists for Rewards category
$checkQuery = $conn->prepare("SELECT h_assets_id, cover_image FROM home_page_assets WHERE category = 'Rewards' LIMIT 1");
if (!$checkQuery) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$checkQuery->execute();
$result = $checkQuery->get_result();
$exists = $result->num_rows > 0;
$existingData = null;

if ($exists) {
    $existingData = $result->fetch_assoc();
}

$checkQuery->close();

if ($exists) {
    // Update existing record - preserve old images if new ones not provided
    if (!$cover_image && $existingData['cover_image']) {
        $cover_image = $existingData['cover_image'];
    } else if ($cover_image && $existingData['cover_image']) {
        // Delete old cover image if a new one is being uploaded
        deleteImage($existingData['cover_image']);
    }

    // Update existing record
    $updateQuery = $conn->prepare("UPDATE home_page_assets SET cover_text = ?, cover_image = ? WHERE category = 'Rewards'");
    if (!$updateQuery) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $updateQuery->bind_param('ss', $cover_text, $cover_image);
    $result = $updateQuery->execute();

    if (!$result) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update reward page assets']);
        exit;
    }

    $updateQuery->close();
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Reward page updated successfully']);
} else {
    // Insert new record
    $insertQuery = $conn->prepare("INSERT INTO home_page_assets (cover_image, cover_text, category) VALUES (?, ?, ?)");
    if (!$insertQuery) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $insertQuery->bind_param('sss', $cover_image, $cover_text, $category);
    $result = $insertQuery->execute();

    if (!$result) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create reward page assets']);
        exit;
    }

    $insertQuery->close();
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Reward page created successfully']);
}

/**
 * Upload image and return relative path
 */
function uploadImage($file, $page_type) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        error_log("Invalid file type: " . $file['type']);
        return false;
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        error_log("File size too large: " . $file['size']);
        return false;
    }

    // Create upload directory if not exists
    // Path from this file (public/actions/) to public/assets/
    $upload_dir = dirname(__DIR__) . '/assets/page_image/' . $page_type . '/';
    
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create directory: " . $upload_dir);
            return false;
        }
    }

    // Generate unique filename
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'reward_' . time() . '_' . uniqid() . '.' . $file_ext;
    $upload_path = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        error_log("Failed to upload image: " . $file['name'] . " to " . $upload_path);
        return false;
    }

    // Return relative path from web root (assets/)
    return 'public/assets/page_image/' . $page_type . '/' . $filename;
}

/**
 * Delete image file from filesystem
 */
function deleteImage($imagePath) {
    if (!$imagePath) {
        return false;
    }

    // If path starts with a slash, assume it's web-root relative and prepend document root
    if (strpos($imagePath, '/') === 0) {
        $absolutePath = rtrim($_SERVER['DOCUMENT_ROOT'], "\/\\") . str_replace('/', DIRECTORY_SEPARATOR, $imagePath);
    } else {
        // Otherwise treat it as relative to project root
        $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath), DIRECTORY_SEPARATOR);
    }

    // Normalize path separators
    $absolutePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absolutePath);

    // Check if file exists and delete it
    if (file_exists($absolutePath)) {
        if (@unlink($absolutePath)) {
            error_log("Deleted image: " . $absolutePath);
            return true;
        } else {
            error_log("Failed to delete image: " . $absolutePath);
            return false;
        }
    }

    // File does not exist — nothing to delete
    return true;
}

?>
