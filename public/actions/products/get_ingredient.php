<?php
/**
 * Get Ingredient Handler (cashier action to take / deduct ingredient qty)
 * POST: ingredient_id, take_qty
 */

session_start();
header('Content-Type: application/json');

// Allow staff and admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../auth/db_config.php';

$ingredient_id = isset($_POST['ingredient_id']) ? (int)$_POST['ingredient_id'] : 0;
$take_qty = isset($_POST['take_qty']) ? floatval($_POST['take_qty']) : 0;

if ($ingredient_id <= 0 || $take_qty <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Fetch current qty
$stmt = $conn->prepare("SELECT COALESCE(ingredient_qty,0) AS qty, IFNULL(ingredient_unit, '') AS unit FROM ingredient WHERE ingredient_id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param('i', $ingredient_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

$current = isset($row['qty']) ? floatval($row['qty']) : 0;

$newQty = $current - $take_qty;

$stmt = $conn->prepare("SELECT ingredient_unit FROM ingredient WHERE ingredient_id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param('i', $ingredient_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

$unit = isset($row['ingredient_unit']) ? trim($row['ingredient_unit']) : '';



// Update ingredient qty (allow negative if business allows)
$up = $conn->prepare("UPDATE ingredient SET ingredient_qty = ? WHERE ingredient_id = ?");
if (!$up) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$up->bind_param('di', $newQty, $ingredient_id);
if (!$up->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update ingredient qty']);
    $up->close();
    exit;
}
$up->close();

// Record transaction in ingredienttransaction table
// Find cashier_id for current user if available
$cashierId = 0;
if (isset($_SESSION['user_id'])) {
    $cstmt = $conn->prepare("SELECT cashier_id FROM cashier WHERE user_id = ? LIMIT 1");
    if ($cstmt) {
        $cstmt->bind_param('i', $_SESSION['user_id']);
        $cstmt->execute();
        $cres = $cstmt->get_result();
        $crow = $cres ? $cres->fetch_assoc() : null;
        if ($crow && isset($crow['cashier_id'])) $cashierId = (int)$crow['cashier_id'];
        $cstmt->close();
    }
}




$tr = $conn->prepare("INSERT INTO ingredienttransaction (ingredient_id, cashier_id, ingredient_unit, quantity) VALUES (?, ?, ?, ?)");
if ($tr) {
    $tr->bind_param('iisd', $ingredient_id, $cashierId, $unit, $take_qty);
    if (!$tr->execute()) {
        // log but don't fail the whole request
        error_log('Failed to insert ingredienttransaction: ' . $tr->error);
    }
    $tr->close();
} else {
    error_log('Failed to prepare ingredienttransaction insert: ' . $conn->error);
}

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Ingredient updated', 'new_qty' => $newQty]);

?>
