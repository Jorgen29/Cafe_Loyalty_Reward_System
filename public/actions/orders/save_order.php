<?php
/**
 * Save Order Handler
 * Accepts POST form-data or JSON:
 * - items: JSON array [{product_id, qty, price}]
 * - customer_id (optional)
 * - payment_method
 * - reward_id (optional)
 */

session_start();
header('Content-Type: application/json');

// Require staff or admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../auth/db_config.php';

/**
 * Determine tier level based on customer points
 * Points: < 10 = Normal, 10-24 = Cappuccino, 25-49 = Latte, 50+ = Macchiato
 */
function getTierLevel($totalPoints) {
    $totalPoints = (int)$totalPoints;
    if ($totalPoints >= 50) return 'Macchiato Level';
    if ($totalPoints >= 25) return 'Latte Level';
    if ($totalPoints >= 3) return 'Cappuccino Level';
    return 'Normal';
}

// Accept JSON payload or form data
$input = $_POST;
$raw = file_get_contents('php://input');
if (empty($input) && $raw) {
    $json = json_decode($raw, true);
    if ($json) $input = $json;
}

$items = isset($input['items']) ? $input['items'] : null;
    $customer_id = isset($input['customer_id']) && $input['customer_id'] !== '' ? (int)$input['customer_id'] : null;
    $payment_method = isset($input['payment_method']) ? trim($input['payment_method']) : 'cash';
    $reward_id = isset($input['reward_id']) && $input['reward_id'] !== '' ? (int)$input['reward_id'] : null;
    $discount_percent = isset($input['discount_percent']) ? floatval($input['discount_percent']) : 0.0;
    
    // Check if this is a free refill order (payment_method = 'none')
    $isFreeRefill = ($payment_method === 'none');if (!$items) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No items provided']);
    exit;
}

// items may be JSON string
if (is_string($items)) {
    $items = json_decode($items, true);
}

if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid items']);
    exit;
}

// Start transaction
$conn->begin_transaction();
try {
    $order_date = date('Y-m-d');
    $order_time = date('H:i:s');

    $stmt = $conn->prepare("INSERT INTO `order` (order_date, order_time, payment_method, customer_id, store_id, reward_id) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

   
    // Determine store_id: prefer input value (sent from cashier page), otherwise lookup from cashier table using session user_id
    $store_id = null;
    if (isset($input['store_id']) && $input['store_id'] !== '') {
        $store_id = (int)$input['store_id'];
    } else {
        if (isset($_SESSION['user_id'])) {
            $cs = $conn->prepare("SELECT store_id FROM cashier WHERE user_id = ? LIMIT 1");
            if ($cs) {
                $uid = (int)$_SESSION['user_id'];
                $cs->bind_param('i', $uid);
                $cs->execute();
                $cres = $cs->get_result();
                if ($crow = $cres->fetch_assoc()) {
                    $store_id = isset($crow['store_id']) ? (int)$crow['store_id'] : null;
                }
                $cs->close();
            }
        }
    }

    // payment_method is a string (e.g. 'cash','card','online'), so use 's' for it.
    // types: order_date(s), order_time(s), order_type(s), payment_method(s), customer_id(i), store_id(i), reward_id(i)
    $stmt->bind_param('sssiii', $order_date, $order_time, $payment_method, $customer_id, $store_id, $reward_id);
    $exec = $stmt->execute();
    if (!$exec) throw new Exception('Order insert failed: ' . $stmt->error);
    $order_id = $stmt->insert_id;
    $stmt->close();

    // Insert order details
    // price column will store: qty * base_price * (1 - discount_percent/100)
    $detailStmt = $conn->prepare("INSERT INTO orderdetails (order_id, product_id, qty, price) VALUES (?, ?, ?, ?)");
    if (!$detailStmt) throw new Exception('Prepare failed: ' . $conn->error);

    foreach ($items as $it) {
        $product_id = isset($it['product_id']) ? (int)$it['product_id'] : 0;
        $qty = isset($it['quantity']) ? (int)$it['quantity'] : (isset($it['qty']) ? (int)$it['qty'] : 1);
        $unit_price = isset($it['price']) ? floatval($it['price']) : 0.0;
        
        // If free refill order, set total to 0
        if ($isFreeRefill) {
            $total = 0.0;
        } else {
            // Store unit_price (no qty multiplication here, price column stores PER-UNIT price after discount)
            $total = $unit_price * (1 - ($discount_percent / 100));
        }
        $detailStmt->bind_param('iiid', $order_id, $product_id, $qty, $total);
        if (!$detailStmt->execute()) throw new Exception('Detail insert failed: ' . $detailStmt->error);
    }
    $detailStmt->close();

    // Compute earned loyalty points for this order and add to customer (if any)
    $earnedPoints = 0;
    // Prepare a statement to fetch product_points for a product
    $ppStmt = $conn->prepare("SELECT COALESCE(product_points, 0) AS product_points FROM product WHERE product_id = ? LIMIT 1");
    if ($ppStmt) {
        foreach ($items as $it) {
            $product_id = isset($it['product_id']) ? (int)$it['product_id'] : 0;
            $qty = isset($it['quantity']) ? (int)$it['quantity'] : (isset($it['qty']) ? (int)$it['qty'] : 1);
            if ($product_id <= 0) continue;
            $ppStmt->bind_param('i', $product_id);
            $ppStmt->execute();
            $pres = $ppStmt->get_result();
            $prow = $pres ? $pres->fetch_assoc() : null;
            $pp = (int)($prow['product_points'] ?? 0);
            $earnedPoints += $qty;
        }
        $ppStmt->close();
    }

    // Count coffee items in this order (is_coffee flag on product)
    $coffeeCount = 0;
    $isCoffeeStmt = $conn->prepare("SELECT COALESCE(is_coffee,0) AS is_coffee FROM product WHERE product_id = ? LIMIT 1");
    if ($isCoffeeStmt) {
        foreach ($items as $it) {
            $product_id = isset($it['product_id']) ? (int)$it['product_id'] : 0;
            $qty = isset($it['quantity']) ? (int)$it['quantity'] : (isset($it['qty']) ? (int)$it['qty'] : 1);
            if ($product_id <= 0) continue;
            $isCoffeeStmt->bind_param('i', $product_id);
            $isCoffeeStmt->execute();
            $cres = $isCoffeeStmt->get_result();
            $crow = $cres ? $cres->fetch_assoc() : null;
            // Normalize various representations of truthy values (e.g. 'yes','true','1')
            $isCoffeeRaw = $crow['is_coffee'] ?? 0;
            $isCoffee = 0;
            if (is_string($isCoffeeRaw)) {
                $lower = strtolower(trim($isCoffeeRaw));
                if (in_array($lower, ['1','true','yes','y','t'], true)) {
                    $isCoffee = 1;
                }
            } else {
                $isCoffee = intval($isCoffeeRaw) ? 1 : 0;
            }
            if ($isCoffee) {
                $coffeeCount += $qty;
            }
        }
        $isCoffeeStmt->close();
    }

    if ($customer_id && $earnedPoints > 0) {
        // Ensure `points` column exists on `customer` table; if not, attempt to add it.
        $colChk = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer' AND column_name = 'points'");
        if ($colChk) {
            $colChk->execute();
            $cres = $colChk->get_result();
            $crow = $cres ? $cres->fetch_assoc() : null;
            $hasPointsCol = (int)($crow['c'] ?? 0) > 0;
            $colChk->close();
        } else {
            $hasPointsCol = false;
        }

        if (!$hasPointsCol) {
            // Try to add the column. If this fails due to permissions, we log and continue without failing the order.
            try {
                $conn->query("ALTER TABLE customer ADD COLUMN points INT(11) DEFAULT 0");
                $hasPointsCol = true;
            } catch (Exception $e) {
                error_log('Failed to add points column: ' . $e->getMessage());
                $hasPointsCol = false;
            }
        }

        if ($hasPointsCol) {
            $upStmt = $conn->prepare("UPDATE customer SET points = COALESCE(points,0) + ? WHERE customer_id = ?");
            if ($upStmt) {
                $upStmt->bind_param('ii', $earnedPoints, $customer_id);
                if (!$upStmt->execute()) {
                    // Log but do not abort the whole order if points update fails
                    error_log('Failed to update customer points: ' . $upStmt->error);
                }
                $upStmt->close();
            }
        }
    }

        // Update customer's coffee count and last_order datetime
        if ($customer_id) {
            try {
                $now = date('Y-m-d H:i:s');

                // Detect which coffee count column exists: prefer 'coffee_count', then 'count_coffee'
                $coffeeCol = null;
                $colChk = $conn->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer' AND column_name IN ('coffee_count','count_coffee') ORDER BY FIELD(column_name,'coffee_count','count_coffee') LIMIT 1");
                if ($colChk) {
                    $colChk->execute();
                    $cres = $colChk->get_result();
                    $crow = $cres ? $cres->fetch_assoc() : null;
                    if ($crow && !empty($crow['column_name'])) {
                        $coffeeCol = $crow['column_name'];
                    }
                    $colChk->close();
                }

                // Fallback to 'count_coffee' if none found
                if (!$coffeeCol) $coffeeCol = 'count_coffee';

                if ($coffeeCount > 0) {
                    // Build dynamic update SQL using the detected column name
                    $sql = "UPDATE customer SET {$coffeeCol} = COALESCE({$coffeeCol},0) + ?, last_order = ? WHERE customer_id = ?";
                    $ccStmt = $conn->prepare($sql);
                    if ($ccStmt) {
                        $ccStmt->bind_param('isi', $coffeeCount, $now, $customer_id);
                        if (!$ccStmt->execute()) {
                            error_log('Failed to update customer ' . $coffeeCol . '/last_order: ' . $ccStmt->error);
                        }
                        $ccStmt->close();
                    } else {
                        error_log('Prepare failed for coffee count update: ' . $conn->error);
                    }
                } else {
                    // Still update last_order even if no coffee items were in this order
                    $loStmt = $conn->prepare("UPDATE customer SET last_order = ? WHERE customer_id = ?");
                    if ($loStmt) {
                        $loStmt->bind_param('si', $now, $customer_id);
                        if (!$loStmt->execute()) {
                            error_log('Failed to update customer last_order: ' . $loStmt->error);
                        }
                        $loStmt->close();
                    } else {
                        error_log('Prepare failed for last_order update: ' . $conn->error);
                    }
                }
            } catch (Exception $e) {
                error_log('Error updating customer coffee count/last_order: ' . $e->getMessage());
            }
        }

    // Update tier level based on total points
    if ($customer_id) {
        // Fetch current total points from customer
        $ptStmt = $conn->prepare("SELECT COALESCE(points, 0) AS total_pts FROM customer WHERE customer_id = ? LIMIT 1");
        if ($ptStmt) {
            $ptStmt->bind_param('i', $customer_id);
            $ptStmt->execute();
            $ptres = $ptStmt->get_result();
            $ptrow = $ptres ? $ptres->fetch_assoc() : null;
            $totalPoints = (int)($ptrow['total_pts'] ?? 0);
            $ptStmt->close();

            $newTier = getTierLevel($totalPoints);
            $tierStmt = $conn->prepare("UPDATE customer SET tier_level = ? WHERE customer_id = ?");
            if ($tierStmt) {
                $tierStmt->bind_param('si', $newTier, $customer_id);
                if (!$tierStmt->execute()) {
                    error_log('Failed to update customer tier_level: ' . $tierStmt->error);
                }
                $tierStmt->close();
            }
        }
    }

        // If a reward/voucher was applied, deduct its points from the customer's balance
        // BUT: Skip point deduction for FREE REFILL orders
        if ($customer_id && $reward_id && !$isFreeRefill) {
            // Check/add points column if needed
            $colChk2 = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer' AND column_name = 'points'");
            if ($colChk2) {
                $colChk2->execute();
                $cres2 = $colChk2->get_result();
                $crow2 = $cres2 ? $cres2->fetch_assoc() : null;
                $hasPointsCol2 = (int)($crow2['c'] ?? 0) > 0;
                $colChk2->close();
            } else {
                $hasPointsCol2 = false;
            }

            if (!$hasPointsCol2) {
                try {
                    $conn->query("ALTER TABLE customer ADD COLUMN points INT(11) DEFAULT 0");
                    $hasPointsCol2 = true;
                } catch (Exception $e) {
                    error_log('Failed to add points column (deduction): ' . $e->getMessage());
                    $hasPointsCol2 = false;
                }
            }

            if ($hasPointsCol2) {
                // Lookup reward.points
                $rstmt = $conn->prepare("SELECT COALESCE(points,0) AS reward_points FROM reward WHERE reward_id = ? LIMIT 1");
                if ($rstmt) {
                    $rstmt->bind_param('i', $reward_id);
                    $rstmt->execute();
                    $rres = $rstmt->get_result();
                    $rrow = $rres ? $rres->fetch_assoc() : null;
                    $rewardPoints = (int)($rrow['reward_points'] ?? 0);
                    $rstmt->close();

                    if ($rewardPoints > 0) {
                        $dedStmt = $conn->prepare("UPDATE customer SET points = GREATEST(COALESCE(points,0) - ?, 0) WHERE customer_id = ?");
                        if ($dedStmt) {
                            $dedStmt->bind_param('ii', $rewardPoints, $customer_id);
                            if (!$dedStmt->execute()) {
                                error_log('Failed to deduct customer points for reward: ' . $dedStmt->error);
                            }
                            $dedStmt->close();
                        }
                    }
                }
            }
        }
        
        // If free refill order was used, reset the customer's coffee_count to 0
        if ($customer_id && $isFreeRefill) {
            try {
                // Detect which coffee count column exists
                $coffeeColReset = null;
                $colChk3 = $conn->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer' AND column_name IN ('coffee_count','count_coffee') ORDER BY FIELD(column_name,'coffee_count','count_coffee') LIMIT 1");
                if ($colChk3) {
                    $colChk3->execute();
                    $cres3 = $colChk3->get_result();
                    $crow3 = $cres3 ? $cres3->fetch_assoc() : null;
                    if ($crow3 && !empty($crow3['column_name'])) {
                        $coffeeColReset = $crow3['column_name'];
                    }
                    $colChk3->close();
                }
                
                if ($coffeeColReset) {
                    $resetSql = "UPDATE customer SET {$coffeeColReset} = 0 WHERE customer_id = ?";
                    $resetStmt = $conn->prepare($resetSql);
                    if ($resetStmt) {
                        $resetStmt->bind_param('i', $customer_id);
                        if (!$resetStmt->execute()) {
                            error_log('Failed to reset coffee_count for free refill: ' . $resetStmt->error);
                        }
                        $resetStmt->close();
                    }
                }
            } catch (Exception $e) {
                error_log('Error resetting coffee count for free refill: ' . $e->getMessage());
            }
        }

    $conn->commit();
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Order saved', 'order_id' => $order_id]);
} catch (Exception $e) {
    $conn->rollback();
    error_log('Order save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save order']);
}
?>