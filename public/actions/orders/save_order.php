<?php
/**
 * Save Order Handler
 * Accepts POST form-data or JSON:
 * - items: JSON array [{product_id, qty, price}]
 * - customer_id (optional)
 * - payment_method
 * - reward_id (optional)
 */

// Start output buffering to prevent stray output from corrupting JSON response
ob_start();

// Set error handling to throw exceptions
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    session_start();
    header('Content-Type: application/json');

    // Require staff or admin
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'])) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    require_once '../auth/db_config.php';
    
    // Clear any buffered output
    ob_end_clean();
    ob_start();

    /**
     * Determine tier level based on customer points
     * Points: < 10 = Normal, 10-24 = Cappuccino, 25-49 = Latte, 50+ = Macchiato
     */
    function getTierLevel($totalPoints) {
        $totalPoints = (int)$totalPoints;
        if ($totalPoints >= 50) return 'Macchiato Level';
        if ($totalPoints >= 25) return 'Latte Level';
        if ($totalPoints >= 10) return 'Cappuccino Level';
        return 'Normal';
    }

    // Accept JSON payload or form datay
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
    $discount_amount = isset($input['discount_amount']) ? floatval($input['discount_amount']) : 0.0;
    $discount_type = isset($input['discount_type']) ? trim($input['discount_type']) : 'none';
    
    // Extract digital payment details (for GCash/PayMaya)
    $payment_details = isset($input['payment_details']) ? $input['payment_details'] : [];
    $reference_number = isset($payment_details['reference_number']) ? trim($payment_details['reference_number']) : null;
    $payment_datetime = isset($payment_details['payment_datetime']) ? trim($payment_details['payment_datetime']) : null;
    
    // Check if this is a free refill order (payment_method = 'none')
    $isFreeRefill = ($payment_method === 'none');
    
    if (!$items) {
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
    
    $order_date = date('Y-m-d');
    $order_time = date('H:i:s');

    // Check if payment_reference and payment_datetime columns exist, create if needed
    $colChkRef = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'order' AND column_name = 'payment_reference'");
    $hasRefCol = false;
    if ($colChkRef) {
        $colChkRef->execute();
        $cres = $colChkRef->get_result();
        $crow = $cres ? $cres->fetch_assoc() : null;
        $hasRefCol = (int)($crow['c'] ?? 0) > 0;
        $colChkRef->close();
    }

    if (!$hasRefCol) {
        try {
            $conn->query("ALTER TABLE `order` ADD COLUMN payment_reference VARCHAR(255) NULL");
        } catch (Exception $e) {
            error_log('Failed to add payment_reference column: ' . $e->getMessage());
        }
    }

    $colChkDt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'order' AND column_name = 'payment_datetime'");
    $hasDtCol = false;
    if ($colChkDt) {
        $colChkDt->execute();
        $cres = $colChkDt->get_result();
        $crow = $cres ? $cres->fetch_assoc() : null;
        $hasDtCol = (int)($crow['c'] ?? 0) > 0;
        $colChkDt->close();
    }

    if (!$hasDtCol) {
        try {
            $conn->query("ALTER TABLE `order` ADD COLUMN payment_datetime TIMESTAMP NULL");
        } catch (Exception $e) {
            error_log('Failed to add payment_datetime column: ' . $e->getMessage());
        }
    }

    // Build INSERT query with or without payment columns
    $insertCols = "order_date, order_time, payment_method, customer_id, reward_id";
    $insertVals = "?, ?, ?, ?, ?";
    $bindTypes = 'sssii';
    $bindVars = [$order_date, $order_time, $payment_method, $customer_id, $reward_id];

    // Add payment details if this is a digital payment
    if (($reference_number || $payment_datetime) && ($payment_method === 'paymaya' || $payment_method === 'gcash')) {
        $insertCols .= ", payment_reference, payment_datetime";
        $insertVals .= ", ?, ?";
        $bindTypes .= 'ss';
        $bindVars[] = $reference_number;
        $bindVars[] = $payment_datetime;
    }

    $stmt = $conn->prepare("INSERT INTO `order` ($insertCols) VALUES ($insertVals)");
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    // Dynamically bind parameters
    $stmt->bind_param($bindTypes, ...$bindVars);
    $exec = $stmt->execute();
    if (!$exec) throw new Exception('Order insert failed: ' . $stmt->error);
    $order_id = $stmt->insert_id;
    $stmt->close();

    // Insert order details
    // price column will store: unit price after discount applied
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
            // Calculate discounted price based on discount type
            $total = $unit_price;
            
            if ($discount_type === 'percent' && $discount_percent > 0) {
                // Apply percentage discount
                $total = $unit_price * (1 - ($discount_percent / 100));
            } else if ($discount_type === 'amount' && $discount_amount > 0) {
                // Apply fixed amount discount (per unit)
                $total = $unit_price - ($discount_amount / $qty);
                // Ensure price doesn't go below 0
                if ($total < 0) $total = 0;
            }
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

                    $delStmt = $conn->prepare("DELETE FROM customerrewards WHERE reward_id = ? AND customer_id = ?");
                    if ($delStmt) {
                        $delStmt->bind_param('ii', $reward_id, $customer_id);
                        $delStmt->execute();
                        $delStmt->close();
                    }
                }
            } catch (Exception $e) {
                error_log('Error resetting coffee count for free refill: ' . $e->getMessage());
            }
        }

    $conn->commit();
    
    // Send receipt email to customer if they provided a customer_id
    $emailStatus = 'skipped';
    $emailMessage = '';
    if ($customer_id) {
        try {
            // Fetch customer email
            $custStmt = $conn->prepare("SELECT COALESCE(u.email, '') AS email, COALESCE(c.first_name, 'Valued Customer') AS first_name FROM customer c LEFT JOIN `user` u ON c.user_id = u.user_id WHERE c.customer_id = ? LIMIT 1");
            $customerEmail = '';
            $customerName = 'Valued Customer';
            if ($custStmt) {
                $custStmt->bind_param('i', $customer_id);
                $custStmt->execute();
                $custres = $custStmt->get_result();
                if ($custrow = $custres->fetch_assoc()) {
                    $customerEmail = $custrow['email'] ?? '';
                    $customerName = $custrow['first_name'] ?? 'Valued Customer';
                }
                $custStmt->close();
            }
            
            // Only send email if customer has an email address
            if (!empty($customerEmail)) {
                error_log('[EMAIL-DEBUG] Attempting to send receipt email to: ' . $customerEmail);
                $emailResult = sendReceiptEmail($order_id, $customerEmail, $customerName, $items, $discount_percent, $discount_amount, $discount_type, $isFreeRefill);
                $emailStatus = $emailResult['status'];
                $emailMessage = $emailResult['message'];
                error_log('[EMAIL-DEBUG] Email status: ' . $emailStatus . ' | Message: ' . $emailMessage);
            } else {
                $emailStatus = 'skipped';
                $emailMessage = 'No customer email found';
                error_log('[EMAIL-DEBUG] Skipped - no customer email');
            }
        } catch (Exception $e) {
            $emailStatus = 'error';
            $emailMessage = $e->getMessage();
            error_log('[EMAIL-DEBUG] Error preparing email: ' . $e->getMessage());
        }
    } else {
        $emailStatus = 'skipped';
        $emailMessage = 'Non Member';
        error_log('[EMAIL-DEBUG] Skipped - no customer ID');
    }
    
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Order saved', 'order_id' => $order_id, 'email_status' => $emailStatus, 'email_message' => $emailMessage]);
    ob_end_flush();
} catch (Exception $e) {
    if (isset($conn)) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackErr) {
            error_log('Rollback error: ' . $rollbackErr->getMessage());
        }
    }
    ob_end_clean();
    error_log('[ORDER-ERROR-EXCEPTION] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('[ORDER-ERROR-TRACE] ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save order: ' . $e->getMessage()]);
} finally {
    restore_error_handler();
}

/**
 * Send receipt email to customer
 * Returns array with 'status' and 'message'
 */
function sendReceiptEmail($order_id, $email, $name, $items, $discount_percent, $discount_amount, $discount_type, $isFreeRefill) {
    global $conn;
    
    try {
        // Verify PHPMailer is available
        if (!file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
            error_log('[EMAIL-ERROR] PHPMailer not available');
            return ['status' => 'error', 'message' => 'PHPMailer library not found'];
        }
        
        require_once __DIR__ . '/../../../vendor/autoload.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        error_log('[EMAIL-DEBUG] PHPMailer loaded successfully');
        
        require_once __DIR__ . '/../auth/mail_config.php';
        
        error_log('[EMAIL-DEBUG] Mail config loaded. SMTP_HOST: ' . (defined('SMTP_HOST') ? SMTP_HOST : 'NOT_DEFINED'));
        
        if (defined('SMTP_HOST') && SMTP_HOST && SMTP_HOST !== 'smtp.example.com') {
            error_log('[EMAIL-DEBUG] Configuring SMTP...');
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $secure = SMTP_SECURE ?? 'tls';
            if ($secure === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port = SMTP_PORT;
            error_log('[EMAIL-DEBUG] SMTP configured. Host: ' . SMTP_HOST . ', Port: ' . SMTP_PORT);
        } else {
            error_log('[EMAIL-DEBUG] SMTP not properly configured - using sendmail');
        }
        
        $fromEmail = defined('FROM_EMAIL') ? FROM_EMAIL : 'no-reply@example.com';
        $fromName = defined('FROM_NAME') ? FROM_NAME : 'Cups & Stories Cafe';
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your Receipt #' . $order_id . ' - Cups & Stories Cafe';
        
        error_log('[EMAIL-DEBUG] Email headers set. Recipient: ' . $email);
        
        // Build receipt items HTML
        $itemsHtml = '';
        $subtotal = 0;
        foreach ($items as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? $item['qty'] ?? 1);
            $price = floatval($item['price'] ?? 0);
            
            // Fetch product name
            $productName = 'Unknown Item';
            $pStmt = $conn->prepare("SELECT product_name FROM product WHERE product_id = ? LIMIT 1");
            if ($pStmt) {
                $pStmt->bind_param('i', $product_id);
                $pStmt->execute();
                $pres = $pStmt->get_result();
                if ($prow = $pres->fetch_assoc()) {
                    $productName = $prow['product_name'] ?? 'Unknown Item';
                }
                $pStmt->close();
            }
            
            $itemTotal = $price * $qty;
            $subtotal += $itemTotal;
            $itemsHtml .= '<tr style="border-bottom:1px solid #eee;">'
                . '<td style="padding:10px; text-align:left;">' . htmlspecialchars($productName) . '</td>'
                . '<td style="padding:10px; text-align:center;">x' . $qty . '</td>'
                . '<td style="padding:10px; text-align:right;">' . number_format($price, 2, '.', '') . '</td>'
                . '<td style="padding:10px; text-align:right;">' . number_format($itemTotal, 2, '.', '') . '</td>'
                . '</tr>';
        }
        
        // Calculate final total with discount
        $discountDisplay = '';
        $finalDiscount = 0;
        if ($discount_type === 'percent' && $discount_percent > 0) {
            $finalDiscount = $subtotal * ($discount_percent / 100);
            $discountDisplay = $discount_percent . '% (' . htmlspecialchars($discount_percent) . '%)';
        } else if ($discount_type === 'amount' && $discount_amount > 0) {
            $finalDiscount = $discount_amount;
            $discountDisplay = number_format($discount_amount, 2, '.', '') . ' (Fixed Amount)';
        }
        
        $total = $subtotal - $finalDiscount;
        if ($isFreeRefill) {
            $discountDisplay = 'Free Refill';
            $total = 0;
        }
        
        error_log('[EMAIL-DEBUG] Email body built. Subtotal: ' . $subtotal . ', Discount: ' . $finalDiscount . ', Total: ' . $total);
        
        // Embedded logo
        $localLogo = __DIR__ . '/../../assets/css/images/logo images/logoName.png';
        $imgTag = '';
        if (file_exists($localLogo)) {
            $mail->addEmbeddedImage($localLogo, 'logo_cid');
            $imgTag = '<img src="cid:logo_cid" alt="Cups & Stories Cafe" class="logo" style="max-width:180px; height:auto;">';
            error_log('[EMAIL-DEBUG] Logo embedded from local file');
        } else {
            $remote = 'https://cupsandstoriescafe.shop/public/assets/css/images/logo%20images/logoName.png';
            $imgTag = '<img src="' . htmlspecialchars($remote) . '" alt="Cups & Stories Cafe" class="logo" style="max-width:180px; height:auto;">';
            error_log('[EMAIL-DEBUG] Using remote logo URL');
        }
        
        $mail->Body = '<!doctype html>'
            . '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>body{font-family:Arial,Helvetica,sans-serif;background:#f6f6f6;margin:0;padding:0} .container{max-width:600px;margin:24px auto;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e9e9e9} .header{background:#fff;padding:18px;text-align:center} .logo{max-width:180px;height:auto} .content{padding:24px;color:#333} .receipt-header{border-bottom:2px solid #6b4423;padding-bottom:12px;margin-bottom:16px} .receipt-no{color:#6b4423;font-weight:700;font-size:16px} .items-table{width:100%;border-collapse:collapse;margin:16px 0} .items-table th{background:#faf7f3;padding:10px;text-align:left;font-weight:700;color:#333;border-bottom:2px solid #eee} .items-table td{padding:10px} .totals{margin-top:16px;padding-top:16px;border-top:2px solid #eee} .total-row{display:flex;justify-content:space-between;padding:8px 0;font-size:14px} .total-final{display:flex;justify-content:space-between;padding:12px 0;font-size:18px;font-weight:700;color:#6b4423;border-top:2px solid #6b4423} .footer{padding:16px;text-align:center;color:#999;font-size:13px;background:#faf7f3;border-top:1px solid #f0f0f0}</style>'
            . '</head><body><div class="container"><div class="header">' . $imgTag . '</div>'
            . '<div class="content">'
            . '<div class="receipt-header"><div class="receipt-no">Receipt #' . htmlspecialchars($order_id) . '</div><div style="color:#666;font-size:14px;margin-top:4px;">' . date('M d, Y - g:i A') . '</div></div>'
            . '<p>Hello ' . htmlspecialchars($name) . ',</p>'
            . '<p>Thank you for your purchase! Here is your receipt:</p>'
            . '<table class="items-table"><thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>' . $itemsHtml . '</tbody></table>'
            . '<div class="totals">'
            . '<div class="total-row"><span>Subtotal:</span><span>' . number_format($subtotal, 2, '.', '') . '</span></div>';
        
        if (!empty($discountDisplay)) {
            $mail->Body .= '<div class="total-row"><span>Discount (' . $discountDisplay . '):</span><span>-' . number_format($finalDiscount, 2, '.', '') . '</span></div>';
        }
        
        $mail->Body .= '<div class="total-final"><span>Total:</span><span>' . number_format($total, 2, '.', '') . '</span></div>'
            . '</div>'
            . '<p style="color:#666;font-size:13px;margin-top:16px;">We appreciate your business and hope to see you again soon!</p>'
            . '</div><div class="footer">Cups & Stories Cafe &middot; <a href="https://cupsandstoriescafe.shop" style="color:#999;text-decoration:none;">cupsandstoriescafe.shop</a></div></div></body></html>';
        
        $mail->AltBody = "Receipt #" . $order_id . "\n\nHello " . $name . ",\n\nThank you for your purchase!\n\n";
        foreach ($items as $item) {
            $qty = (int)($item['quantity'] ?? $item['qty'] ?? 1);
            $price = floatval($item['price'] ?? 0);
            $mail->AltBody .= "- " . $qty . "x " . number_format($price, 2, '.', '') . "\n";
        }
        $mail->AltBody .= "\nSubtotal: " . number_format($subtotal, 2, '.', '') . "\n";
        if (!empty($discountDisplay)) {
            $mail->AltBody .= "Discount: -" . number_format($finalDiscount, 2, '.', '') . "\n";
        }
        $mail->AltBody .= "Total: " . number_format($total, 2, '.', '') . "\n\nThank you!";
        
        error_log('[EMAIL-DEBUG] Attempting to send email...');
        $mail->send();
        error_log('[EMAIL-SUCCESS] Receipt email sent for order #' . $order_id . ' to ' . $email);
        return ['status' => 'sent', 'message' => 'Receipt email sent successfully to ' . $email];
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        error_log('[EMAIL-ERROR] Receipt email error for order #' . $order_id . ': ' . $errorMsg);
        return ['status' => 'error', 'message' => 'Email error: ' . $errorMsg];
    }
}
?>