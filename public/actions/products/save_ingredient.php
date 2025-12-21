<?php
/**
 * Save Ingredient Handler (Create/Update)
 * POST endpoint for creating or updating ingredients
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
$ingredient_id = isset($_POST['ingredient_id']) ? trim($_POST['ingredient_id']) : '';
$ingredient_name = isset($_POST['ingredient_name']) ? trim($_POST['ingredient_name']) : '';
$ingredient_qty = isset($_POST['ingredient_qty']) ? trim($_POST['ingredient_qty']) : '';
$ingredient_unit = isset($_POST['ingredient_unit']) ? trim($_POST['ingredient_unit']) : '';

// Validation
if (empty($ingredient_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ingredient name is required']);
    exit;
}

if (empty($ingredient_qty)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Quantity is required']);
    exit;
}

// Check if updating or creating
if (!empty($ingredient_id)) {
    // Update existing ingredient
    $query = $conn->prepare("UPDATE ingredient SET ingredient_name = ?, ingredient_qty = ?, ingredient_unit = ? WHERE ingredient_id = ?");
    if (!$query) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $query->bind_param("sssi", $ingredient_name, $ingredient_qty, $ingredient_unit, $ingredient_id);
    $result = $query->execute();
    
    if (!$result) {
        error_log("Execute failed: " . $query->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update ingredient']);
        exit;
    }
    
    $query->close();
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Ingredient updated successfully']);
} else {
    // Create new ingredient
    $query = $conn->prepare("INSERT INTO ingredient (ingredient_name, ingredient_qty, ingredient_unit) VALUES (?, ?, ?)");
    if (!$query) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $query->bind_param("sss", $ingredient_name, $ingredient_qty, $ingredient_unit);
    $result = $query->execute();
    
    if (!$result) {
        error_log("Execute failed: " . $query->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create ingredient']);
        exit;
    }
    
    $query->close();
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Ingredient created successfully']);
}
?>
