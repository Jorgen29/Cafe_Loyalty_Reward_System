<?php
/**
 * Delete Product Handler
 * POST endpoint for deleting products
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

// Validation
if (empty($product_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

// Start transaction for cascade delete
$conn->begin_transaction();
try {
    // 1. Get all order_ids that contain this product
    $get_orders = $conn->prepare("SELECT DISTINCT order_id FROM orderdetails WHERE product_id = ?");
    if (!$get_orders) {
        throw new Exception("Prepare failed for getting orders: " . $conn->error);
    }
    $get_orders->bind_param("i", $product_id);
    $get_orders->execute();
    $result = $get_orders->get_result();
    
    $order_ids = [];
    while ($row = $result->fetch_assoc()) {
        $order_ids[] = $row['order_id'];
    }
    $get_orders->close();

    // 2. Delete from orderdetails first (all items in those orders)
    if (!empty($order_ids)) {
        $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
        $delete_orderdetails = $conn->prepare("DELETE FROM orderdetails WHERE order_id IN ($placeholders)");
        if (!$delete_orderdetails) {
            throw new Exception("Prepare failed for orderdetails: " . $conn->error);
        }
        
        // Bind all order_ids
        $types = str_repeat('i', count($order_ids));
        $delete_orderdetails->bind_param($types, ...$order_ids);
        $delete_orderdetails->execute();
        $delete_orderdetails->close();

        // 3. Delete from order table using those order_ids
        $delete_orders = $conn->prepare("DELETE FROM `order` WHERE order_id IN ($placeholders)");
        if (!$delete_orders) {
            throw new Exception("Prepare failed for order: " . $conn->error);
        }
        $delete_orders->bind_param($types, ...$order_ids);
        $delete_orders->execute();
        $delete_orders->close();
    }

    // 4. Delete product
    $delete_product = $conn->prepare("DELETE FROM product WHERE product_id = ?");
    if (!$delete_product) {
        throw new Exception("Prepare failed for product: " . $conn->error);
    }
    $delete_product->bind_param("i", $product_id);
    $delete_product->execute();
    
    // Check if product was found and deleted
    if ($conn->affected_rows === 0) {
        $delete_product->close();
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    $delete_product->close();

    // Commit transaction
    $conn->commit();
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Product and related records deleted successfully']);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Transaction failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete product: ' . $e->getMessage()]);
}
?>
