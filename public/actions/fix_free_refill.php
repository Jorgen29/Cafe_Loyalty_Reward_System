<?php
/**
 * Fix Free Refill Reward Points
 * Updates the Free Refill reward to have 0 points instead of 10
 */

require_once 'auth/db_config.php';

try {
    // Update existing Free Refill reward to have 0 points
    $stmt = $conn->prepare("UPDATE reward SET points = 0 WHERE reward_name = 'Free Refill'");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => "Fixed! Updated $affected record(s). Free Refill reward now has 0 points."
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
