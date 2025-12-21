<?php
/**
 * Claim Coffee Reward Handler
 * Accepts POST: customer_id, is_coffee_reward
 * For the "10 Coffee = Free Refill" reward
 * Creates an entry in customerrewards table with a special reward_id
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

if (!$customer_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing customer_id']);
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

    // Detect which coffee-count column exists
    $coffeeCol = null;
    $colChk = $conn->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer' AND column_name IN ('coffee_count','count_coffee') ORDER BY FIELD(column_name,'coffee_count','count_coffee') LIMIT 1");
    if ($colChk) {
        $colChk->execute();
        $cres = $colChk->get_result();
        $crow = $cres ? $cres->fetch_assoc() : null;
        if ($crow && !empty($crow['column_name'])) $coffeeCol = $crow['column_name'];
        $colChk->close();
    }

    // Get customer's coffee count
    if ($coffeeCol) {
        $cstmt = $conn->prepare("SELECT {$coffeeCol} as coffee_count FROM customer WHERE customer_id = ? LIMIT 1");
    } else {
        $cstmt = $conn->prepare("SELECT 0 as coffee_count FROM customer WHERE customer_id = ? LIMIT 1");
    }
    if (!$cstmt) throw new Exception('Prepare failed: ' . $conn->error);
    $cstmt->bind_param('i', $customer_id);
    $cstmt->execute();
    $cres = $cstmt->get_result();
    $crow = $cres->fetch_assoc();
    $coffee_count = (int)($crow['coffee_count'] ?? 0);
    $cstmt->close();

    // Check if customer has at least 10 coffees
    if ($coffee_count < 10) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Insufficient coffee count (have ' . $coffee_count . ', need 10)']);
        exit;
    }

    // First, ensure the "Free Refill" reward exists in the reward table
    $reward_name = 'Free Refill';
    $frstmt = $conn->prepare("SELECT reward_id FROM reward WHERE reward_name = ? LIMIT 1");
    if (!$frstmt) throw new Exception('Prepare failed: ' . $conn->error);
    $frstmt->bind_param('s', $reward_name);
    $frstmt->execute();
    $frres = $frstmt->get_result();
    $frrow = $frres->fetch_assoc();
    $reward_id = $frrow ? (int)$frrow['reward_id'] : null;
    $frstmt->close();

    // If "Free Refill" reward doesn't exist, create it
    if (!$reward_id) {
        $createstmt = $conn->prepare("INSERT INTO reward (reward_name, reward_type, points) VALUES (?, 'free_item', 0)");
        if (!$createstmt) throw new Exception('Prepare failed: ' . $conn->error);
        $createstmt->bind_param('s', $reward_name);
        if (!$createstmt->execute()) throw new Exception('Insert failed: ' . $createstmt->error);
        $reward_id = $createstmt->insert_id;
        $createstmt->close();
    }

    // Check if customer already claimed this reward (within unclaimed status)
    // For coffee rewards, we might allow multiple claims, but let's check current logic
    // For now, check if already claimed but not used
    $dupchk = $conn->prepare("SELECT 1 FROM customerrewards WHERE customer_id = ? AND reward_id = ? LIMIT 1");
    if (!$dupchk) throw new Exception('Prepare failed: ' . $conn->error);
    $dupchk->bind_param('ii', $customer_id, $reward_id);
    $dupchk->execute();
    $dupres = $dupchk->get_result();
    if ($dupres->num_rows > 0) {
        $dupchk->close();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This reward has already been claimed']);
        exit;
    }
    $dupchk->close();

    // Insert into customerrewards
    $insstmt = $conn->prepare("INSERT INTO customerrewards (customer_id, reward_id) VALUES (?, ?)");
    if (!$insstmt) throw new Exception('Prepare failed: ' . $conn->error);
    $insstmt->bind_param('ii', $customer_id, $reward_id);
    if (!$insstmt->execute()) throw new Exception('Insert failed: ' . $insstmt->error);
    $insstmt->close();

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Free Refill reward claimed successfully']);
} catch (Exception $e) {
    error_log('Claim coffee reward error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
