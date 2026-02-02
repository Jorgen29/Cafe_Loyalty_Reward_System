<?php
session_start();
require_once '../../public/actions/auth/db_config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=Please+log+in+first');
    exit;
}

$customerId = null;
$customerName = 'Member';
$customerPoints = 0;
$coffeeCount = 0;

// Detect which coffee-count column exists to avoid referencing non-existent columns
$coffeeCol = null;
$colChk = $conn->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer' AND column_name IN ('coffee_count','count_coffee') ORDER BY FIELD(column_name,'coffee_count','count_coffee') LIMIT 1");
if ($colChk) {
    $colChk->execute();
    $cres = $colChk->get_result();
    $crow = $cres ? $cres->fetch_assoc() : null;
    if ($crow && !empty($crow['column_name'])) $coffeeCol = $crow['column_name'];
    $colChk->close();
}

// Build SELECT depending on whether a coffee column was found
if ($coffeeCol) {
    $sql = "SELECT c.customer_id, c.first_name, c.last_name, COALESCE(c.points,0) AS points, COALESCE(c.{$coffeeCol},0) AS coffee_count FROM customer c WHERE c.user_id = ? LIMIT 1";
} else {
    $sql = "SELECT c.customer_id, c.first_name, c.last_name, COALESCE(c.points,0) AS points FROM customer c WHERE c.user_id = ? LIMIT 1";
}

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $customerId = (int)$row['customer_id'];
        $customerName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Member';
        $customerPoints = (int)$row['points'];
        if ($coffeeCol) {
            $coffeeCount = (int)($row['coffee_count'] ?? 0);
        }
    }
    $stmt->close();
}

// Fetch active rewards
$activeRewards = [];
$rstmt = $conn->prepare("SELECT reward_id, reward_name, reward_type, start_date, expiration_date, points, COALESCE(discount_percent,0) AS discount_percent FROM reward r WHERE (r.start_date IS NULL OR r.start_date <= CURDATE()) AND (r.expiration_date IS NULL OR r.expiration_date >= CURDATE()) ORDER BY r.expiration_date IS NULL, r.expiration_date ASC");
if ($rstmt) {
    $rstmt->execute();
    $rres = $rstmt->get_result();
    while ($rw = $rres->fetch_assoc()) {
        $activeRewards[] = $rw;
    }
    $rstmt->close();
}

// Fetch redeem history (orders where a reward was applied)
$redeemHistory = [];
if ($customerId) {
    $hstmt = $conn->prepare("SELECT o.order_id, o.order_date, o.order_time, r.reward_id, r.reward_name FROM `order` o JOIN reward r ON o.reward_id = r.reward_id WHERE o.customer_id = ? AND o.reward_id IS NOT NULL ORDER BY o.order_date DESC, o.order_time DESC LIMIT 50");
    if ($hstmt) {
        $hstmt->bind_param('i', $customerId);
        $hstmt->execute();
        $hres = $hstmt->get_result();
        while ($hr = $hres->fetch_assoc()) {
            $redeemHistory[] = $hr;
        }
        $hstmt->close();
    }
}

$rewards_cover_image = null;
$rewards_cover_text = '';


if (isset($conn)) {
    $stmt = $conn->prepare("SELECT cover_image, cover_text FROM home_page_assets WHERE category ='Rewards' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $rewards_cover_image = !empty($row['cover_image']) ? $row['cover_image'] : null;
            $rewards_cover_text = !empty($row['cover_text']) ? $row['cover_text'] : '';
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards - Cafe Loyalty Reward</title>
    <link rel="stylesheet" href="../../public/assets/css/user-styles.css">
    <style>
        .rewards-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .progress-section {
            background: linear-gradient(135deg, #6b4423 0%, #8b6f47 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 40px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .progress-title {
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .progress-bar-container {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .progress-bar-bg {
            flex: 1;
            height: 12px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background-color: #e8ddd0;
            transition: width 0.3s ease;
            border-radius: 10px;
        }

        .progress-claim-btn {
            padding: 10px 25px;
            background-color: #e8ddd0;
            color: #6b4423;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Georgia', serif;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .progress-claim-btn:hover {
            background-color: white;
            transform: translateY(-2px);
        }

        .progress-info {
            display: inline-block;
            width: 24px;
            height: 24px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            font-weight: 600;
            cursor: pointer;
            color: white;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .progress-info:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .rewards-list-section,
        .history-section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .reward-item {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            display: grid;
            grid-template-columns: 50px 1fr 150px;
            gap: 20px;
            align-items: center;
            margin-bottom: 15px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .reward-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .reward-icon {
            font-size: 32px;
            text-align: center;
        }

        .reward-details {
            font-size: 14px;
            color: #333;
            line-height: 1.6;
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

        .reward-btn {
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

        .reward-btn:hover {
            background-color: #5a3a1e;
            transform: translateY(-2px);
        }

        .reward-points {
            display: inline-block;
            padding: 10px 15px;
            background-color: #e8ddd0;
            color: #6b4423;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
        }

        .history-list {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .history-date {
            font-size: 13px;
            color: #999;
            font-weight: 600;
            margin-top: 15px;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0ebe5;
        }

        .history-date:first-child {
            margin-top: 0;
        }

        .history-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f5f1ed;
            font-size: 14px;
            color: #333;
        }

        .history-row:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .rewards-container {
                padding: 20px;
            }

            .reward-item {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .reward-action {
                text-align: left;
            }

            .reward-icon {
                font-size: 28px;
            }

            .section-title {
                font-size: 20px;
            }

            .progress-bar-container {
                flex-direction: column;
            }

            .progress-claim-btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .rewards-container {
                padding: 15px;
            }

            .progress-section {
                padding: 20px;
            }

            .progress-title {
                font-size: 16px;
            }

            .section-title {
                font-size: 18px;
            }

            .reward-item {
                padding: 15px;
            }

            .reward-details {
                font-size: 13px;
            }

            .reward-btn,
            .progress-claim-btn {
                font-size: 13px;
                padding: 8px 15px;
            }

            .history-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
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
                if(window.innerWidth > 768) {
                    navLinks.classList.remove('show');
                }
            });

            // Close menu when clicking on a link
            const navLinks_items = document.querySelectorAll('#nav-links a');
            navLinks_items.forEach(link => {
                link.addEventListener('click', function() {
                    if(window.innerWidth <= 768) {
                        navLinks.classList.remove('show');
                    }
                });
            });

            // Claim button functionality
            document.querySelectorAll('.reward-btn, .progress-claim-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    claimReward(this);
                });
            });
        });

        function claimReward(button) {
            const customerId = <?php echo $customerId ?? 0; ?>;
            let rewardId = null;

            // Check if this is the progress claim button (coffee count reward)
            if (button.classList.contains('progress-claim-btn')) {
                const coffeeCount = <?php echo $coffeeCount; ?>;
                const goal = 10;

                if (coffeeCount < goal) {
                    alert('You need ' + (goal - coffeeCount) + ' more coffee(s) to claim this reward!');
                    return;
                }

                // Look for the free refill reward (0 points = special reward)
                rewardId = null; // Will be fetched or use special handling
                
                // For now, create/use a special "Free Refill" reward with reward_id = 0 or special handling
                // OR fetch all rewards and find one marked as "free refill"
                // For this implementation, we'll assume there's a free refill reward with special logic
                
                // Make a direct claim with coffee count verification
                const formData = new FormData();
                formData.append('customer_id', customerId);
                formData.append('is_coffee_reward', 1); // Special flag for coffee reward

                fetch('../../public/actions/customer/claim_coffee_reward.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reward claimed successfully! You have earned a free refill.');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Could not claim reward'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error claiming reward');
                });
            } else {
                // Regular reward claim from the list
                const row = button.closest('.reward-item');
                rewardId = row.querySelector('[data-reward-id]').getAttribute('data-reward-id');

                if (!rewardId) {
                    alert('Error: Reward ID not found');
                    return;
                }

                const formData = new FormData();
                formData.append('customer_id', customerId);
                formData.append('reward_id', rewardId);

                fetch('../../public/actions/customer/claim_reward.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reward claimed successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Could not claim reward'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error claiming reward');
                });
            }
        }
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
            <button class="hamburger" id="hamburger-btn" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>

    <div class="banner-section">
        <img src="../../<?php echo htmlspecialchars($rewards_cover_image); ?>" alt="Rewards Banner" class="banner">
        <div class="serif banner-text"><?php echo htmlspecialchars($rewards_cover_text); ?></div>
    </div>

    <div class="rewards-container">
        <!-- Progress Section -->
        <div class="progress-section">
            <a class="progress-info" href="faqs.php" title="How to earn points?">?</a>
                <div class="serif progress-title">
                    ☕ Order 10 Coffee to get free refill
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar-bg">
                        <?php
                            // compute progress toward 10 coffees
                            $goal = 10;
                            $percent = $goal > 0 ? min(100, intval(($coffeeCount / $goal) * 100)) : 0;
                            echo '<div class="progress-bar-fill" style="width:' . $percent . '%"></div>';
                        ?>
                    </div>
                    <button class="serif progress-claim-btn">Claim</button>
                </div>
        </div>

        <!-- Redeem Rewards Section -->
        <div class="rewards-list-section">
            <h2 class="serif section-title">Redeem Rewards</h2>
            
            <?php if (count($activeRewards) === 0): ?>
                <p style="color:#666;">No rewards available at this time.</p>
            <?php else: ?>
                <?php foreach ($activeRewards as $rw):
                    $rId = (int)$rw['reward_id'];
                    $rName = htmlspecialchars($rw['reward_name']);
                    $rPoints = (int)($rw['points'] ?? 0);
                    $rExp = $rw['expiration_date'] ? date('F d, Y', strtotime($rw['expiration_date'])) : 'N/A';
                    $canClaim = ($customerPoints >= $rPoints && $rPoints > 0) || $rPoints == 0;
                ?>
                    <div class="reward-item">
                        <span class="reward-icon">🎟️</span>
                        <div class="reward-details">
                            <?php echo $rName; ?><br>
                            <span class="reward-valid">Valid until: <?php echo $rExp; ?></span>
                        </div>
                        <div class="reward-action">
                            <?php if ($rPoints > 0): ?>
                                <span class="reward-points"><?php echo $rPoints; ?> points</span>
                            <?php else: ?>
                                <button class="serif reward-btn">Claim</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- History Section -->
        <div class="history-section">
            <h2 class="serif section-title">Redeem Rewards History</h2>
            <div class="history-list">
                <?php if (count($redeemHistory) === 0): ?>
                    <div style="color:#666;">No redeem history yet.</div>
                <?php else: ?>
                    <?php foreach ($redeemHistory as $h):
                        $dateLabel = date('F j, Y', strtotime($h['order_date']));
                        $timeLabel = date('g:i A', strtotime($h['order_time']));
                    ?>
                        <div class="history-date"><?php echo $dateLabel; ?></div>
                        <div class="history-row">
                            <span><?php echo htmlspecialchars($h['reward_name']); ?></span>
                            <span><?php echo $timeLabel; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
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
                <span >CUPS & Stories CAFE</span>
                </a>
            </div>
        </div>
    </footer>
</body>
</html>
