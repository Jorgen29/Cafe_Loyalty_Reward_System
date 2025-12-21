<?php
/**
 * Save Product Handler (Create/Update)
 * POST endpoint for creating or updating products
 */

session_start();
header('Content-Type: application/json');

// Custom error handler - return JSON instead of HTML
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[$errno] $errstr in $errfile:$errline");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
});

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include database configuration
require_once '../auth/db_config.php';

// Get POST data
$product_id = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';
$product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
$product_price = isset($_POST['product_price']) ? floatval($_POST['product_price']) : 0;
$product_temperature = isset($_POST['product_temperature']) ? trim($_POST['product_temperature']) : 'None';
$product_size = isset($_POST['product_size']) ? trim($_POST['product_size']) : 'None';
$product_points = isset($_POST['product_points']) ? intval($_POST['product_points']) : 0;
$product_category = isset($_POST['product_category']) ? trim($_POST['product_category']) : '';
$image_path = null;
$is_coffee = 'no';

// Handle image upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $image_file = $_FILES['image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // Validate file type
    if (!in_array($image_file['type'], $allowed_types)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid image format. Only JPG, PNG, GIF are allowed']);
        exit;
    }
    
    // Validate file size
    if ($image_file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Image file too large. Maximum 2MB allowed']);
        exit;
    }
    
    // Create uploads directory if not exists
    $upload_dir = __DIR__ . '/../../assets/images/products/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_ext = pathinfo($image_file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_ext;
    $upload_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($image_file['tmp_name'], $upload_path)) {
        error_log("Failed to upload image: " . $image_file['name']);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
        exit;
    }
    
    // Store relative path for database
    $image_path = '../../public/assets/images/products/' . $filename;
}

// Validation
if (empty($product_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product name is required']);
    exit;
}

if ($product_price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product price must be greater than 0']);
    exit;
}

// If category empty, default to 'Uncategorized'
if (empty($product_category)) {
    $product_category = 'Uncategorized';
}

// Set is_coffee flag based on category (case-insensitive)
$is_coffee = (strcasecmp($product_category, 'Coffee') === 0) ? 'yes' : 'no';

if ($product_points < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product points cannot be negative']);
    exit;
}

// Check if updating or creating
if (!empty($product_id)) {
    // Update existing product
    if ($image_path) {
        // Update with new image, include category and is_coffee flag
        $query = $conn->prepare("UPDATE product SET product_name = ?, product_price = ?, product_category = ?, product_temperature = ?, product_size = ?, product_points = ?, is_coffee = ?, image_path = ? WHERE product_id = ?");
        if (!$query) {
            error_log("Prepare failed: " . $conn->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        
        $query->bind_param("sdsssissi", $product_name, $product_price, $product_category, $product_temperature, $product_size, $product_points, $is_coffee, $image_path, $product_id);
    } else {
        // Update without image change, include category
        $query = $conn->prepare("UPDATE product SET product_name = ?, product_price = ?, product_category = ?, product_temperature = ?, product_size = ?, product_points = ?, is_coffee = ? WHERE product_id = ?");
        if (!$query) {
            error_log("Prepare failed: " . $conn->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        
        $query->bind_param("sdsssisi", $product_name, $product_price, $product_category, $product_temperature, $product_size, $product_points, $is_coffee, $product_id);
    }
    
    $result = $query->execute();
    
    if (!$result) {
        error_log("Execute failed: " . $query->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update product']);
        exit;
    }
    
    $query->close();
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
} else {
    // Create new product
    $query = $conn->prepare("INSERT INTO product (product_name, product_price, product_category, product_temperature, product_size, product_points, is_coffee, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$query) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $query->bind_param("sdsssiss", $product_name, $product_price, $product_category, $product_temperature, $product_size, $product_points, $is_coffee, $image_path);
    $result = $query->execute();
    
    if (!$result) {
        error_log("Execute failed: " . $query->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create product']);
        exit;
    }
    
    $query->close();
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Product created successfully']);
}
?>
