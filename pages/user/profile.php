<?php
session_start();
require_once '../../public/actions/auth/db_config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=Please+log+in+first');
    exit;
}

require_once '../../public/actions/bday_auto/bday_auto_refresh.php';

// Fetch customer record by session user_id (including tier_level and points and birthday)
$customer = null;
$customerId = null;
$customerName = 'Guest';
$customerTierLevel = 'Normal';
$customerPoints = 0;
$customerBirthday = null;
$isBirthdayToday = false;
$stmt = $conn->prepare("SELECT customer_id, first_name, last_name, COALESCE(tier_level, 'Normal') AS tier_level, COALESCE(points, 0) AS points, last_order, birthday FROM customer WHERE user_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $customer = $row;
        $customerId = (int)$row['customer_id'];
        $customerName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Member';
        $customerTierLevel = $row['tier_level'];
        $customerPoints = (int)$row['points'];
        $customerLastOrder = $row['last_order'] ?? null;
        $customerBirthday = $row['birthday'] ?? null;

        // Check if today is customer's birthday
        if ($customerBirthday) {
            $birthday_parts = explode('-', $customerBirthday);
            if (count($birthday_parts) === 3) {
                $birthday_month = $birthday_parts[1];
                $birthday_day = $birthday_parts[2];
                $today_month = date('m');
                $today_day = date('d');

                if ($birthday_month === $today_month && $birthday_day === $today_day) {
                    $isBirthdayToday = true;
                }
            }
        }
    }
    $stmt->close();
}

// If no customer record, attempt to use session names
if (!$customer) {
    $customerName = trim((isset($_SESSION['first_name']) ? $_SESSION['first_name'] : '') . ' ' . (isset($_SESSION['last_name']) ? $_SESSION['last_name'] : '')) ?: 'Member';
}

// If customer exists, check inactivity: if last_order is older than 30 days, reset points, tier, and coffee count
if ($customerId) {
    try {
        // only proceed if we have a last_order value
        if (!empty($customerLastOrder)) {
            $lastTs = strtotime($customerLastOrder);
            if ($lastTs !== false) {
                $threshold = strtotime('-30 days');
                if ($lastTs < $threshold) {
                    // detect coffee count column name
                    $coffeeCol = null;
                    $colChk = $conn->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer' AND column_name IN ('coffee_count','count_coffee') ORDER BY FIELD(column_name,'coffee_count','count_coffee') LIMIT 1");
                    if ($colChk) {
                        $colChk->execute();
                        $cres = $colChk->get_result();
                        $crow = $cres ? $cres->fetch_assoc() : null;
                        if ($crow && !empty($crow['column_name'])) $coffeeCol = $crow['column_name'];
                        $colChk->close();
                    }

                    // build update SQL (do NOT reset coffee count here)
                    $sets = ["points = 0", "tier_level = 'Normal'"];
                    $sql = "UPDATE customer SET " . implode(', ', $sets) . " WHERE customer_id = ?";
                    $u = $conn->prepare($sql);
                    if ($u) {
                        $u->bind_param('i', $customerId);
                        $u->execute();
                        $u->close();
                        // update local variables to reflect reset
                        $customerPoints = 0;
                        $customerTierLevel = 'Normal';
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Inactivity reset check failed: ' . $e->getMessage());
    }
}


// Fetch available rewards for this customer (active)
$availableRewards = [];
if ($customerId) {
    $rstmt = $conn->prepare("SELECT r.reward_id, r.reward_name, r.reward_type, r.start_date, r.expiration_date, r.points, COALESCE(r.discount_percent,0) AS discount_percent
        FROM customerrewards cr
        JOIN reward r ON cr.reward_id = r.reward_id
        WHERE cr.customer_id = ?
          AND (r.start_date IS NULL OR r.start_date <= CURDATE())
          AND (r.expiration_date IS NULL OR r.expiration_date >= CURDATE())
          AND r.reward_id NOT IN (SELECT DISTINCT reward_id FROM `order` WHERE customer_id = ? AND reward_id IS NOT NULL)");
    if ($rstmt) {
        $rstmt->bind_param('ii', $customerId, $customerId);
        $rstmt->execute();
        $rres = $rstmt->get_result();
        while ($r = $rres->fetch_assoc()) {
            $availableRewards[] = $r;
        }
        $rstmt->close();
    }
}

// Fetch all unclaimed rewards (active, non-expired) that customer may be eligible to claim
$claimableRewards = [];
if ($customerId) {
    $crstmt = $conn->prepare("SELECT r.reward_id, r.reward_name, r.reward_type, r.start_date, r.expiration_date, r.points, COALESCE(r.discount_percent,0) AS discount_percent
        FROM reward r
        WHERE (r.start_date IS NULL OR r.start_date <= CURDATE())
          AND (r.expiration_date IS NULL OR r.expiration_date >= CURDATE())
          AND r.reward_id NOT IN (SELECT reward_id FROM customerrewards WHERE customer_id = ?)
          AND r.reward_id NOT IN (SELECT DISTINCT reward_id FROM `order` WHERE customer_id = ? AND reward_id IS NOT NULL)");
    if ($crstmt) {
        $crstmt->bind_param('ii', $customerId, $customerId);
        $crstmt->execute();
        $crres = $crstmt->get_result();
        while ($cr = $crres->fetch_assoc()) {
            $claimableRewards[] = $cr;
        }
        $crstmt->close();
    }
}

// Fetch rewards that have been used (applied to an order) and deduct their points from customerPoints
$usedRewardsPoints = 0;
if ($customerId) {
    $urewardstmt = $conn->prepare("SELECT SUM(COALESCE(r.points, 0)) as total_deducted FROM `order` o JOIN reward r ON o.reward_id = r.reward_id WHERE o.customer_id = ? AND o.reward_id IS NOT NULL");
    if ($urewardstmt) {
        $urewardstmt->bind_param('i', $customerId);
        $urewardstmt->execute();
        $urewardres = $urewardstmt->get_result();
        if ($urewardrow = $urewardres->fetch_assoc()) {
            $usedRewardsPoints = (int)($urewardrow['total_deducted'] ?? 0);
        }
        $urewardstmt->close();
    }
}

// Fetch order history
$orderHistory = [];
$ordersCount = 0;
if ($customerId) {
    $ostmt = $conn->prepare("SELECT o.order_id, o.payment_datetime, o.payment_method, o.payment_reference, SUM(od.price) as total_price, SUM(od.qty * COALESCE(p.product_points,0)) as points_earned FROM `order` o JOIN orderdetails od ON o.order_id = od.order_id JOIN product p ON od.product_id = p.product_id WHERE o.customer_id = ? GROUP BY o.order_id, o.payment_datetime, o.payment_method, o.payment_reference ORDER BY o.payment_datetime DESC");
    if ($ostmt) {
        $ostmt->bind_param('i', $customerId);
        $ostmt->execute();
        $ores = $ostmt->get_result();
        while ($orow = $ores->fetch_assoc()) {
            $orderHistory[] = $orow;
            $ordersCount++;
        }
        $ostmt->close();
    }
}

// Use customerPoints directly from customer table (same as displayed in current balance)
$totalPoints = $customerPoints;

// Determine level and next level thresholds (based on points)
$tierThresholds = [
    'Normal' => 0,
    'Cappuccino Level' => 10,
    'Latte Level' => 25,
    'Macchiato Level' => 50
];

$levelName = $customerTierLevel;
$currentThreshold = $tierThresholds[$levelName] ?? 0;

// Determine next level and points needed
$nextLevel = 'Macchiato Level';
$nextThreshold = 50;
if ($currentThreshold < 10) {
    $nextLevel = 'Cappuccino Level';
    $nextThreshold = 10;
} elseif ($currentThreshold < 25) {
    $nextLevel = 'Latte Level';
    $nextThreshold = 25;
} elseif ($currentThreshold < 50) {
    $nextLevel = 'Macchiato Level';
    $nextThreshold = 50;
}

$pointsNeeded = max(0, $nextThreshold - $customerPoints);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Cups & Stories Cafe</title>
    <link rel="stylesheet" href="../../public/assets/css/user-styles.css">
    <style>
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .profile-card {
            background: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .profile-header {
            display: grid;
            grid-template-columns: 120px 1fr 80px;
            gap: 30px;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #f0ebe5;
        }

        .profile-avatar {
            text-align: center;
        }

        .profile-avatar img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #6b4423;
        }

        .profile-user-info h2 {
            font-size: 22px;
            color: #333;
            margin: 0 0 10px 0;
            font-weight: 600;
        }

        .profile-level {
            font-size: 14px;
            color: #999;
            margin: 0;
        }

        .profile-level-sub {
            font-size: 13px;
            color: #bbb;
            margin-top: 5px;
        }

        .profile-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .profile-actions img {
            width: 24px;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .profile-actions img:hover {
            opacity: 1;
        }

        .cups-section,
        .rewards-section,
        .history-section {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 20px;
            color: #333;
            margin: 0 0 25px 0;
            font-weight: 600;
        }

        .cups-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }

        .cups-item {
            background: linear-gradient(135deg, #6b4423 0%, #8b6f47 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .cups-item-label {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .cups-item-value {
            font-size: 28px;
            font-weight: 700;
        }

        .reward-item {
            background-color: #fafaf8;
            padding: 20px;
            border-radius: 8px;
            display: grid;
            grid-template-columns: 1fr 150px;
            gap: 20px;
            align-items: center;
            margin-bottom: 15px;
            border-left: 4px solid #6b4423;
            transition: all 0.3s ease;
        }

        .reward-item:hover {
            background-color: #f5f1ed;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .reward-text {
            font-size: 16px;
            color: #333;
            line-height: 1.5;
        }

        .reward-valid {
            display: block;
            font-size: 12px;
            color: #999;
            margin-top: 8px;
        }

        .reward-action {
            text-align: right;
        }

        .activate-btn {
            padding: 10px 25px;
            background-color: #6b4423;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Georgia', serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .activate-btn:hover {
            background-color: #5a3a1e;
            transform: translateY(-2px);
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table thead {
            background-color: #f5f1ed;
        }

        .history-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 14px;
            border-bottom: 2px solid #e8ddd0;
        }

        .history-table td {
            padding: 15px;
            border-bottom: 1px solid #f0ebe5;
            font-size: 14px;
            color: #333;
        }

        .history-table tr:hover {
            background-color: #fafaf8;
        }

        @media (max-width: 768px) {
            .profile-container {
                padding: 20px;
            }

            .profile-card,
            .cups-section,
            .rewards-section,
            .history-section {
                padding: 20px;
            }

            .profile-header {
                grid-template-columns: 80px 1fr;
                gap: 15px;
                margin-bottom: 20px;
                padding-bottom: 20px;
            }

            .profile-actions {
                grid-column: 1 / -1;
                justify-content: flex-start;
            }

            .profile-avatar img {
                width: 80px;
                height: 80px;
            }

            .cups-info {
                grid-template-columns: 1fr;
            }

            .reward-item {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .reward-action {
                text-align: left;
            }

            .history-table {
                font-size: 13px;
            }

            .history-table th,
            .history-table td {
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .profile-container {
                padding: 15px;
            }

            .profile-card,
            .cups-section,
            .rewards-section,
            .history-section {
                padding: 15px;
            }

            .profile-avatar img {
                width: 70px;
                height: 70px;
            }

            .profile-user-info h2 {
                font-size: 18px;
            }

            .section-title {
                font-size: 18px;
                margin-bottom: 15px;
            }

            .reward-text {
                font-size: 14px;
            }

            .activate-btn {
                font-size: 13px;
                padding: 8px 15px;
            }

            .history-table {
                font-size: 12px;
            }

            .history-table th,
            .history-table td {
                padding: 8px;
            }

            .cups-item-value {
                font-size: 22px;
            }
        }

        /* QR modal default styling (profile page did not include cashier modal CSS) */
        .qr-modal {
            position: fixed;
            inset: 0;
            display: none;
            /* toggled via inline style/show */
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.45);
            z-index: 9999;
            padding: 20px;
        }

        .qr-modal .qr-modal-content {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.2);
            max-width: 480px;
            width: 100%;
        }

        /* Responsive QR modal layout */
        .qr-modal .qr-modal-body {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .qr-modal #profile-qr-code {
            width: 220px;
            height: 220px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid #eee;
            box-sizing: border-box;
            flex: 0 0 auto;
        }

        .qr-modal #profile-qr-info {
            font-size: 13px;
            color: #666;
        }

        .qr-modal #profile-qr-logo img {
            max-width: 140px;
            max-height: 80px;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid #eee;
            background: #fff;
            padding: 6px;
        }

        @media (max-width: 480px) {
            .qr-modal .qr-modal-content {
                padding: 14px;
            }

            .qr-modal .qr-modal-body {
                flex-direction: column;
                align-items: center;
            }

            .qr-modal #profile-qr-code {
                width: 160px;
                height: 160px;
            }

            .qr-modal #profile-qr-logo img {
                max-width: 120px;
                max-height: 60px;
            }
        }

        /* Claim reward modal styling */
        .claim-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            padding: 20px;
        }

        .claim-modal.active {
            display: flex;
        }

        .claim-modal-content {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 100%;
            padding: 30px;
            text-align: center;
        }

        .claim-modal-content h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 22px;
        }

        .claim-modal-content p {
            margin: 0 0 25px 0;
            color: #666;
            font-size: 15px;
            line-height: 1.6;
        }

        .claim-modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .claim-modal-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .claim-modal-btn-primary {
            background-color: #6b4423;
            color: white;
        }

        .claim-modal-btn-primary:hover {
            background-color: #5a3a1e;
        }

        .claim-modal-btn-secondary {
            background-color: #e8ddd0;
            color: #333;
        }

        .claim-modal-btn-secondary:hover {
            background-color: #ddd0c0;
        }

        .claim-modal-success {
            color: #28a745;
        }

        .logout-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-section:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .logout-section img {
            width: 24px;
            height: 24px;
        }
    </style>
    <!-- QR generation library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.getElementById('hamburger-btn');
            const navLinks = document.getElementById('nav-links');

            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', function() {
                    navLinks.classList.toggle('show');
                });
            }

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    navLinks.classList.remove('show');
                }
            });

            const navLinks_items = document.querySelectorAll('#nav-links a');
            navLinks_items.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        navLinks.classList.remove('show');
                    }
                });
            });

            // Reward claim modal setup
            const claimModal = document.getElementById('claim-modal');
            const claimModalContent = document.getElementById('claim-modal-content');
            const claimModalConfirm = document.getElementById('claim-modal-confirm');
            const claimModalCancel = document.getElementById('claim-modal-cancel');
            let currentRewardId = null;
            let currentCustomerId = '<?php echo $customerId; ?>';

            function showClaimModal(rewardId, rewardName) {
                currentRewardId = rewardId;
                claimModalContent.innerHTML = `
                    <h3>Claim Reward</h3>
                    <p>Are you sure you want to claim this reward?<br><strong>${rewardName}</strong></p>
                    <div class="claim-modal-buttons">
                        <button class="claim-modal-btn claim-modal-btn-primary" id="claim-modal-confirm">Claim</button>
                        <button class="claim-modal-btn claim-modal-btn-secondary" id="claim-modal-cancel">Cancel</button>
                    </div>
                `;
                claimModal.classList.add('active');

                // Re-attach event listeners after content update
                document.getElementById('claim-modal-confirm').addEventListener('click', confirmClaim);
                document.getElementById('claim-modal-cancel').addEventListener('click', closeClaim);
            }

            function confirmClaim() {
                if (!currentRewardId || !currentCustomerId) return;

                fetch('../../public/actions/customer/claim_reward.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            customer_id: currentCustomerId,
                            reward_id: currentRewardId
                        })
                    })
                    .then(r => r.json())
                    .then(json => {
                        if (json.success) {
                            claimModalContent.innerHTML = `
                            <h3 class="claim-modal-success">✓ Success</h3>
                            <p>Reward claimed successfully!</p>
                            <div class="claim-modal-buttons">
                                <button class="claim-modal-btn claim-modal-btn-primary" id="claim-modal-done">OK</button>
                            </div>
                        `;
                            document.getElementById('claim-modal-done').addEventListener('click', function() {
                                closeClaim();
                                window.location.reload();
                            });
                        } else {
                            claimModalContent.innerHTML = `
                            <h3 style="color:#d9534f;">Error</h3>
                            <p>${json.message || 'Failed to claim reward'}</p>
                            <div class="claim-modal-buttons">
                                <button class="claim-modal-btn claim-modal-btn-primary" id="claim-modal-done">OK</button>
                            </div>
                        `;
                            document.getElementById('claim-modal-done').addEventListener('click', closeClaim);
                        }
                    })
                    .catch(err => {
                        console.error('Claim error', err);
                        claimModalContent.innerHTML = `
                        <h3 style="color:#d9534f;">Error</h3>
                        <p>An error occurred while claiming the reward.</p>
                        <div class="claim-modal-buttons">
                            <button class="claim-modal-btn claim-modal-btn-primary" id="claim-modal-done">OK</button>
                        </div>
                    `;
                        document.getElementById('claim-modal-done').addEventListener('click', closeClaim);
                    });
            }

            function closeClaim() {
                claimModal.classList.remove('active');
                currentRewardId = null;
            }

            // Handle reward claim buttons - show modal instead of alert
            document.querySelectorAll('.claim-reward-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const rewardId = this.dataset.rewardId;
                    const rewardName = this.closest('.reward-item').querySelector('.reward-text').textContent;
                    showClaimModal(rewardId, rewardName);
                });
            });

            // Close modal when clicking outside
            claimModal.addEventListener('click', function(e) {
                if (e.target === claimModal) closeClaim();
            });

            // Remove old activate button listener that was showing "Reward activated successfully"
            // (Keep only for My Rewards section, not for claimable rewards)
            document.querySelectorAll('.rewards-section:first-of-type .activate-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    alert('Reward claimed successfully!');
                });
            });

            // QR modal handling for showing user's QR code to cashier
            const qrBtn = document.getElementById('qr-show-btn');
            const qrModal = document.getElementById('profile-qr-modal');
            const qrClose = document.getElementById('profile-qr-close');
            const qrContainer = document.getElementById('profile-qr-code');
            const qrInfo = document.getElementById('profile-qr-info');

            if (qrBtn && qrModal && qrClose && qrContainer) {
                qrBtn.addEventListener('click', function() {
                    // Clear any previous QR content
                    qrContainer.innerHTML = '';
                    if (qrInfo) qrInfo.innerHTML = ''; // remove displaying of available discounts

                    // Build payload: customer_id|first_name|last_name
                    const customerId = '<?php echo $customerId ? (int)$customerId : 0; ?>';
                    const customerName = '<?php echo addslashes($customerName); ?>';
                    const payload = customerId + '|' + customerName;

                    // Create a temporary hidden container so qrcode lib produces an <img>
                    const tmpDiv = document.createElement('div');
                    tmpDiv.style.position = 'absolute';
                    tmpDiv.style.left = '-9999px';
                    document.body.appendChild(tmpDiv);

                    try {
                        new QRCode(tmpDiv, {
                            text: payload,
                            width: 220,
                            height: 220
                        });
                    } catch (e) {
                        // If QR generation fails, fall back to plain text display
                        qrContainer.textContent = payload;
                        document.body.removeChild(tmpDiv);
                        qrModal.style.display = 'flex';
                        return;
                    }

                    // Allow a short timeout for the library to insert the image
                    setTimeout(function() {
                        // Attempt to find generated image
                        const tmpImg = tmpDiv.querySelector('img');
                        // Create canvas for final QR; size adapts to container width
                        const containerRect = qrContainer.getBoundingClientRect();
                        // subtract some padding so canvas fits comfortably
                        let computedSize = Math.floor(Math.min(320, containerRect.width - 20));
                        if (computedSize < 140) computedSize = 140;
                        const canvas = document.createElement('canvas');
                        const size = computedSize;
                        canvas.width = size;
                        canvas.height = size;
                        const ctx = canvas.getContext('2d');

                        if (tmpImg && tmpImg.src) {
                            const img = new Image();
                            img.crossOrigin = 'anonymous';
                            img.onload = function() {
                                // Draw QR onto canvas sized to modal container
                                try {
                                    ctx.drawImage(img, 0, 0, size, size);
                                } catch (e) {
                                    ctx.fillStyle = '#fff';
                                    ctx.fillRect(0, 0, size, size);
                                }

                                // Draw a white rounded background for the logo (smaller so it doesn't obscure finder patterns)
                                const logoSize = Math.floor(size * 0.18); // ~18% of QR size
                                const logoX = Math.floor((size - logoSize) / 2);
                                const logoY = Math.floor((size - logoSize) / 2);

                                // White rounded box
                                const radius = 8;
                                ctx.fillStyle = '#ffffff';
                                ctx.beginPath();
                                ctx.moveTo(logoX + radius, logoY);
                                ctx.arcTo(logoX + logoSize, logoY, logoX + logoSize, logoY + logoSize, radius);
                                ctx.arcTo(logoX + logoSize, logoY + logoSize, logoX, logoY + logoSize, radius);
                                ctx.arcTo(logoX, logoY + logoSize, logoX, logoY, radius);
                                ctx.arcTo(logoX, logoY, logoX + logoSize, logoY, radius);
                                ctx.closePath();
                                ctx.fill();

                                // Draw logo image centered
                                const logo = new Image();
                                logo.crossOrigin = 'anonymous';
                                logo.src = '../../public/assets/css/images/logo images/logoName.png';
                                logo.onload = function() {
                                    // Fit logo into the white box with small padding
                                    const padding = Math.floor(logoSize * 0.12);
                                    const lw = logoSize - padding * 2;
                                    const lh = logoSize - padding * 2;
                                    ctx.drawImage(logo, logoX + padding, logoY + padding, lw, lh);

                                    // Style canvas for display
                                    canvas.style.display = 'block';
                                    canvas.style.background = '#ffffff';
                                    canvas.style.borderRadius = '8px';
                                    canvas.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';

                                    // Place canvas and download button into a centered wrapper to avoid overlap
                                    const wrapper = document.createElement('div');
                                    wrapper.style.display = 'flex';
                                    wrapper.style.flexDirection = 'column';
                                    wrapper.style.alignItems = 'center';
                                    wrapper.style.gap = '8px';
                                    wrapper.appendChild(canvas);

                                    const dl = document.createElement('a');
                                    dl.textContent = 'Download QR';
                                    dl.href = canvas.toDataURL('image/png');
                                    dl.download = 'cafe_qr_<?php echo ($customerId ? (int)$customerId : 0); ?>.png';
                                    dl.style.display = 'inline-block';
                                    dl.style.marginTop = '4px';
                                    dl.style.padding = '8px 12px';
                                    dl.style.background = '#6b4423';
                                    dl.style.color = '#fff';
                                    dl.style.borderRadius = '6px';
                                    dl.style.textDecoration = 'none';
                                    dl.style.fontFamily = "'Georgia', serif";
                                    dl.style.fontWeight = '600';
                                    dl.style.zIndex = '10';
                                    wrapper.appendChild(dl);
                                    qrContainer.appendChild(wrapper);

                                    // Cleanup
                                    if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                                };

                                logo.onerror = function() {
                                    // If logo fails, still show canvas and download inside wrapper
                                    canvas.style.display = 'block';
                                    canvas.style.background = '#ffffff';
                                    canvas.style.borderRadius = '8px';
                                    canvas.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';
                                    const wrapper2 = document.createElement('div');
                                    wrapper2.style.display = 'flex';
                                    wrapper2.style.flexDirection = 'column';
                                    wrapper2.style.alignItems = 'center';
                                    wrapper2.style.gap = '8px';
                                    wrapper2.appendChild(canvas);
                                    const dl2 = document.createElement('a');
                                    dl2.textContent = 'Download QR';
                                    dl2.href = canvas.toDataURL('image/png');
                                    dl2.download = 'cafe_qr_<?php echo ($customerId ? (int)$customerId : 0); ?>.png';
                                    dl2.style.display = 'inline-block';
                                    dl2.style.marginTop = '4px';
                                    dl2.style.padding = '8px 12px';
                                    dl2.style.background = '#6b4423';
                                    dl2.style.color = '#fff';
                                    dl2.style.borderRadius = '6px';
                                    dl2.style.textDecoration = 'none';
                                    dl2.style.fontFamily = "'Georgia', serif";
                                    dl2.style.fontWeight = '600';
                                    dl2.style.zIndex = '10';
                                    wrapper2.appendChild(dl2);
                                    qrContainer.appendChild(wrapper2);
                                    if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                                };
                            };
                            img.onerror = function() {
                                // Fallback: if generated image cannot be loaded, fallback to simple text
                                qrContainer.textContent = payload;
                                if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                            };
                            img.src = tmpImg.src;
                        } else {
                            // If no image produced (some environments produce a table), fallback to direct QR rendering into container
                            qrContainer.innerHTML = '';
                            try {
                                // Render directly into container and wrap the image if possible
                                new QRCode(qrContainer, {
                                    text: payload,
                                    width: 220,
                                    height: 220
                                });
                                // If qrcodejs placed an img directly, wrap it
                                const directImg = qrContainer.querySelector('img');
                                if (directImg) {
                                    const canvas2 = document.createElement('canvas');
                                    const size2 = 220;
                                    canvas2.width = size2;
                                    canvas2.height = size2;
                                    const ctx2 = canvas2.getContext('2d');
                                    const tmpDirect = new Image();
                                    tmpDirect.crossOrigin = 'anonymous';
                                    tmpDirect.onload = function() {
                                        ctx2.drawImage(tmpDirect, 0, 0, size2, size2);
                                        canvas2.style.display = 'block';
                                        canvas2.style.background = '#ffffff';
                                        canvas2.style.borderRadius = '8px';
                                        canvas2.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';
                                        const wrapper3 = document.createElement('div');
                                        wrapper3.style.display = 'flex';
                                        wrapper3.style.flexDirection = 'column';
                                        wrapper3.style.alignItems = 'center';
                                        wrapper3.style.gap = '8px';
                                        wrapper3.appendChild(canvas2);
                                        const dl3 = document.createElement('a');
                                        dl3.textContent = 'Download QR';
                                        dl3.href = canvas2.toDataURL('image/png');
                                        dl3.download = 'cafe_qr_<?php echo ($customerId ? (int)$customerId : 0); ?>.png';
                                        dl3.style.display = 'inline-block';
                                        dl3.style.marginTop = '4px';
                                        dl3.style.padding = '8px 12px';
                                        dl3.style.background = '#6b4423';
                                        dl3.style.color = '#fff';
                                        dl3.style.borderRadius = '6px';
                                        dl3.style.textDecoration = 'none';
                                        dl3.style.fontFamily = "'Georgia', serif";
                                        dl3.style.fontWeight = '600';
                                        dl3.style.zIndex = '10';
                                        wrapper3.appendChild(dl3);
                                        qrContainer.innerHTML = '';
                                        qrContainer.appendChild(wrapper3);
                                    };
                                    tmpDirect.onerror = function() {
                                        // If we cannot load the direct image, leave the rendered table or image as-is
                                    };
                                    tmpDirect.src = directImg.src;
                                }
                            } catch (e) {
                                qrContainer.textContent = payload;
                            }
                            if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                        }

                        // Show modal after we've started generation
                        qrModal.style.display = 'flex';
                    }, 60);
                });

                qrClose.addEventListener('click', function() {
                    qrModal.style.display = 'none';
                });
                qrModal.addEventListener('click', function(e) {
                    if (e.target === qrModal) qrModal.style.display = 'none';
                });
            }
        });
    </script>
</head>

<body>
    <header class="serif header">
        <a href="home.php">
            <div class="header-left">

                <img src="../../public/assets/css/images/logo images/cups and stories logo.png" alt="Cafe Logo" class="logo">

            </div>
        </a>

        <div class="header-right">
            <nav id="nav-links">
                <a href="home.php">Home</a>
                <a href="menu.php">Menu</a>
                <a href="rewards.php">Rewards</a>

                <a href="faqs.php">FAQs</a>
            </nav>
            <div class="profile">

                <a href="profile.php">
                    <img src="<?php echo !empty($_SESSION['profile_image'])
                                    ? htmlspecialchars($_SESSION['profile_image'])
                                    : '../../public/icons/logo.png'; ?>"
                        alt="User">
                </a>
            </div>
            <nav id="nav-links">
                <a href="../../public/actions/auth/logout.php" class="logout-section"><img src="../../public/icons/logout.jpg" alt="Logout"></a>
                <a href="">QR Code</a>
            </nav>


            <button class="hamburger" id="hamburger-btn" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>

    <!-- Profile QR Modal -->
    <div class="qr-modal" id="profile-qr-modal" style="display:none;">
        <div class="qr-modal-content" style="max-width:480px;padding:20px;">
            <div style="display:flex;justify-content: flex-end;align-items:center;margin-bottom:12px;">

                <button id="profile-qr-close" style="background:none;border:none;font-size:20px;cursor:pointer;">&times;</button>
            </div>
            <div class="qr-modal-body">
                <div id="profile-qr-code"></div>
                <div style="flex:1;min-width:180px;">


                    <div id="profile-qr-logo" style="margin-top:12px;display:flex;align-items:center;justify-content: center;">
                        <img id="profile-qr-logo-img" src="../../public/assets/css/images/logo images/logoName.png" alt="Cafe Logo" style="max-width:140px;max-height:100%;object-fit:contain;border-radius:6px;border:1px solid #eee;background:#fff;padding:6px;" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Claim Reward Modal -->
    <div class="claim-modal" id="claim-modal">
        <div class="claim-modal-content" id="claim-modal-content">
            <h3>Claim Reward</h3>
            <p>Loading...</p>
        </div>
    </div>

    <div class="banner-section">
        <img src="../../public/assets/profile-page.jpg" alt="Profile Banner" class="banner">
        <div class="serif banner-text">Profile</div>
    </div>

    <div class="profile-container">
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <a href="profile.php">
                        <img src="<?php echo !empty($_SESSION['profile_image'])
                                        ? htmlspecialchars($_SESSION['profile_image'])
                                        : '../../public/icons/logo.png'; ?>"
                            alt="User">
                    </a>
                </div>
                <div class="serif profile-user-info">
                    <h2><?php echo htmlspecialchars($customerName); ?></h2>
                    <div class="profile-level"><?php echo htmlspecialchars($levelName); ?></div>
                    <div class="profile-level-sub"><?php echo $pointsNeeded > 0 ? $pointsNeeded . ' points needed to reach ' . htmlspecialchars($nextLevel) : 'Maximum level reached!'; ?></div>
                </div>
                <div class="profile-actions">
                    <button id="qr-show-btn" title="Show QR" style="background:none;border:none;cursor:pointer;padding:0;margin-right:8px;">
                        <!-- simple QR SVG icon -->
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="3" width="6" height="6" stroke="#6b4423" fill="none" stroke-width="1.5" />
                            <rect x="15" y="3" width="6" height="6" stroke="#6b4423" fill="none" stroke-width="1.5" />
                            <rect x="3" y="15" width="6" height="6" stroke="#6b4423" fill="none" stroke-width="1.5" />
                            <rect x="11" y="11" width="2" height="2" fill="#6b4423" />
                            <rect x="14" y="11" width="2" height="2" fill="#6b4423" />
                            <rect x="11" y="14" width="2" height="2" fill="#6b4423" />
                        </svg>
                    </button>
                    <a href="profile_info.php">
                        <img src="../../public/icons/settings_icon.jpg" alt="Settings" title="Settings">
                    </a>
                </div>
            </div>
        </div>

        <!-- Coffee Cups Section -->
        <div class="cups-section">
            <h3 class="serif section-title">My Coffee Cups</h3>
            <div class="cups-info">
                <div class="cups-item">
                    <div class="cups-item-label">Current Balance</div>
                    <div class="cups-item-value"><?php echo (int)$customerPoints; ?></div>
                </div>
                <div class="cups-item">
                    <div class="cups-item-label">Expiry Date</div>
                    <?php
                    // Expiration is customer's last_order + 30 days if available, otherwise fall back to first available reward expiration
                    $expiryDisplay = 'N/A';
                    if (!empty($customerLastOrder)) {
                        $ts = strtotime($customerLastOrder);
                        if ($ts !== false) {
                            $expiryTs = strtotime($customerLastOrder . ' +30 days');
                            if ($expiryTs !== false) {
                                if ($expiryTs <= strtotime('today')) {
                                    $expiryDisplay = 'Expired on ' . date('M d, Y', $expiryTs);
                                } else {
                                    $expiryDisplay = date('M d, Y', $expiryTs);
                                }
                            }
                        }
                    } elseif (isset($availableRewards[0]['expiration_date']) && $availableRewards[0]['expiration_date']) {
                        $expiryDisplay = date('M d, Y', strtotime($availableRewards[0]['expiration_date']));
                    }
                    ?>
                    <div class="cups-item-value"><?php echo htmlspecialchars($expiryDisplay); ?></div>
                </div>
            </div>
        </div>

        <!-- My Rewards Section -->
        <div class="rewards-section">
            <h3 class="serif section-title">My Rewards</h3>

            <?php if (count($availableRewards) === 0): ?>
                <p style="color:#666;">No active rewards available.</p>
            <?php else: ?>
                <?php foreach ($availableRewards as $rw): ?>
                    <div class="reward-item">
                        <div>
                            <div class="reward-text"><?php echo htmlspecialchars($rw['reward_name']); ?></div>
                            <span class="reward-valid">Valid until: <?php echo htmlspecialchars(date('F d, Y', strtotime($rw['expiration_date']))); ?></span>
                        </div>
                        <div class="reward-action">
                            <button class="activate-btn" data-reward-id="<?php echo (int)$rw['reward_id']; ?>">Claimed</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Available Rewards to Claim Section -->
        <div class="rewards-section">
            <h3 class="serif section-title">Available Rewards to Claim</h3>

            <?php if (count($claimableRewards) === 0): ?>
                <p style="color:#666;">No rewards available to claim at this time.</p>
            <?php else: ?>
                <?php
                // Fetch list of reward_ids that have been used by this customer
                $usedRewardIds = [];
                if ($customerId) {
                    $usedstmt = $conn->prepare("SELECT DISTINCT reward_id FROM `order` WHERE customer_id = ? AND reward_id IS NOT NULL");
                    if ($usedstmt) {
                        $usedstmt->bind_param('i', $customerId);
                        $usedstmt->execute();
                        $usedres = $usedstmt->get_result();
                        while ($usedrow = $usedres->fetch_assoc()) {
                            $usedRewardIds[] = (int)$usedrow['reward_id'];
                        }
                        $usedstmt->close();
                    }
                }
                ?>
                <?php foreach ($claimableRewards as $cr): ?>
                    <?php
                    $rewardId = (int)$cr['reward_id'];
                    $pointsRequired = (int)($cr['points'] ?? 0);
                    $rewardType = $cr['reward_type'] ?? '';
                    $isUsed = in_array($rewardId, $usedRewardIds);

                    // Birthday vouchers can be claimed on customer's birthday, regardless of points
                    $isBirthdayVoucher = stripos($rewardType, 'Birthday') !== false || stripos($cr['reward_name'], 'Birthday') !== false;
                    $canClaim = !$isUsed && (($isBirthdayVoucher && $isBirthdayToday) || (!$isBirthdayVoucher && $totalPoints >= $pointsRequired));

                    $buttonText = $isUsed ? 'Already Used' : ($canClaim ? 'Claim' : 'Not Eligible');
                    ?>
                    <div class="reward-item" style="<?php echo $canClaim ? '' : 'opacity:0.6;'; ?>">
                        <div>
                            <div class="reward-text"><?php echo htmlspecialchars($cr['reward_name']); ?></div>
                            <?php if ($isBirthdayVoucher): ?>
                                <?php if ($isBirthdayToday): ?>
                                    <span class="reward-valid" style="color:#28a745;">🎉 Happy Birthday! This reward is available today!</span>
                                <?php else: ?>
                                    <span class="reward-valid">🎂 Available on your birthday</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="reward-valid">Points required: <strong><?php echo $pointsRequired; ?></strong> (You have: <strong><?php echo $totalPoints; ?></strong>)</span>
                            <?php endif; ?>
                            <?php if ($isUsed): ?>
                                <span class="reward-valid" style="color:#d9534f;">✓ Already used on an order</span>
                            <?php endif; ?>
                            <?php if ($cr['expiration_date']): ?>
                                <span class="reward-valid">Valid until: <?php echo htmlspecialchars(date('F d, Y', strtotime($cr['expiration_date']))); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="reward-action">
                            <button class="serif activate-btn claim-reward-btn" data-reward-id="<?php echo $rewardId; ?>" <?php if (!$canClaim) {
                                                                                                                                echo 'disabled style="opacity:0.5;cursor:not-allowed;"';
                                                                                                                            } ?>>
                                <?php echo $buttonText; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Points History Section -->
        <div class="history-section">
            <h3 class="serif section-title">Coffee Cups History</h3>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Receipt Number</th>
                        <th>Date & Time</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Reference Number</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orderHistory) === 0): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color:#666;">No orders yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orderHistory as $oh): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($oh['order_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($oh['payment_datetime']))); ?></td>
                                <td>₱<?php echo number_format((float)$oh['total_price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($oh['payment_method'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($oh['payment_reference'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- <div class="form-section">
            <!-- Logout Section -->
        <!-- <div class="logout-section">
            <img src="../../public/icons/logout.jpg" alt="Logout">
            <span class="logout-text">Log out</span>
        </div> 
        </div> -->
    </div>

    <footer class="footer">
        <div style="text-align:center; color:#fff; font-size:20px; margin-bottom:10px;">Contact us:</div>
        <div class="footer-contact-row">
            <div class="footer-contact-item">
                <img src="../../public/icons/call.png" alt="Phone">
                <span>0906 377 9569</span>
            </div>
            <div class="footer-contact-item">
                <img src="../../public/icons/placeholder.png" alt="Location">
                <span>Barangay Pinagtipunan, General Trias, Cavite</span>
            </div>
            <div class="footer-contact-item">
                <a href="https://web.facebook.com/profile.php?id=100095680143645" style="text-decoration: none;color:white;text-align:center;display: flex;align-items: center; gap: 12px;">
                    <img src="../../public/icons/communication.png" alt="Facebook">
                    <span>CUPS & Stories CAFE</span>
                </a>
            </div>
        </div>
    </footer>
</body>

</html>