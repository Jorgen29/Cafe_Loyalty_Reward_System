<?php
/**
 * Claim Reward Handler
 * Accepts POST: customer_id, reward_id
 * Validates customer has enough points for the reward, then inserts into customerrewards table
 */

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../auth/db_config.php';

$input = $_POST;
$raw = file_get_contents('php://input');
if (empty($input) && $raw) {
    $json = json_decode($raw, true);
    if ($json) $input = $json;
}

$customer_id = isset($input['customer_id']) ? (int)$input['customer_id'] : 0;
$reward_id = isset($input['reward_id']) ? (int)$input['reward_id'] : 0;

if (!$customer_id || !$reward_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing customer_id or reward_id']);
    exit;
}

try {
    // Verify customer owns this account (via session)
    $cstmt = $conn->prepare("SELECT customer_id FROM customer WHERE customer_id = ? AND user_id = ? LIMIT 1");
    if (!$cstmt) throw new Exception('Prepare failed: ' . $conn->error);
    $cstmt->bind_param('ii', $customer_id, $_SESSION['user_id']);
    $cstmt->execute();
    $cres = $cstmt->get_result();
    if (!$cres->fetch_assoc()) {
        $cstmt->close();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Customer mismatch']);
        exit;
    }
    $cstmt->close();

    // Fetch reward points required
    $rstmt = $conn->prepare("SELECT reward_id, points FROM reward WHERE reward_id = ? LIMIT 1");
    if (!$rstmt) throw new Exception('Prepare failed: ' . $conn->error);
    $rstmt->bind_param('i', $reward_id);
    $rstmt->execute();
    $rres = $rstmt->get_result();
    if (!($rrow = $rres->fetch_assoc())) {
        $rstmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Reward not found']);
        exit;
    }
    $required_points = (int)($rrow['points'] ?? 0);
    $rstmt->close();

    // Get customer's points from customer table
    $ostmt = $conn->prepare("SELECT COALESCE(points, 0) as customer_points FROM customer WHERE customer_id = ?");
    if (!$ostmt) throw new Exception('Prepare failed: ' . $conn->error);
    $ostmt->bind_param('i', $customer_id);
    $ostmt->execute();
    $ores = $ostmt->get_result();
    $orow = $ores->fetch_assoc();
    $customer_points = (int)($orow['customer_points'] ?? 0);
    $ostmt->close();

    // Check if customer already claimed this reward
    $dupchk = $conn->prepare("SELECT 1 FROM customerrewards WHERE customer_id = ? AND reward_id = ? LIMIT 1");
    if (!$dupchk) throw new Exception('Prepare failed: ' . $conn->error);
    $dupchk->bind_param('ii', $customer_id, $reward_id);
    $dupchk->execute();
    $dupres = $dupchk->get_result();
    if ($dupres->num_rows > 0) {
        $dupchk->close();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Reward already claimed']);
        exit;
    }
    $dupchk->close();

    // Validate customer has enough points
    if ($customer_points < $required_points) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Insufficient points (have ' . $customer_points . ', need ' . $required_points . ')']);
        exit;
    }

    // Insert into customerrewards
    $insstmt = $conn->prepare("INSERT INTO customerrewards (customer_id, reward_id) VALUES (?, ?)");
    if (!$insstmt) throw new Exception('Prepare failed: ' . $conn->error);
    $insstmt->bind_param('ii', $customer_id, $reward_id);
    if (!$insstmt->execute()) throw new Exception('Insert failed: ' . $insstmt->error);
    $insstmt->close();

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Reward claimed successfully']);
} catch (Exception $e) {
    error_log('Claim reward error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
