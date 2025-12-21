<?php
/**
 * Fetch Reward Page Assets
 * Retrieves existing reward page content from database
 */

session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include database configuration
require_once 'auth/db_config.php';

// Fetch reward page assets
$query = $conn->prepare("SELECT h_assets_id, cover_image, cover_text FROM home_page_assets WHERE category = 'Rewards' LIMIT 1");
if (!$query) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$query->execute();
$result = $query->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'h_assets_id' => $data['h_assets_id'],
            'cover_image' => $data['cover_image'] ? $data['cover_image'] : null,
            'cover_text' => $data['cover_text'] ? $data['cover_text'] : ''
        ]
    ]);
} else {
    // No data exists yet
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => null
    ]);
}

$query->close();
?>
