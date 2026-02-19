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

// Fetch claimed rewards for the current customer
$claimedRewardIds = [];
$coffeeRewardId = null;
$coffeeRewardClaimed = false;
if ($customerId) {
    $cstmt = $conn->prepare("SELECT reward_id FROM customerrewards WHERE customer_id = ?");
    if ($cstmt) {
        $cstmt->bind_param('i', $customerId);
        $cstmt->execute();
        $cres = $cstmt->get_result();
        while ($crow = $cres->fetch_assoc()) {
            $claimedRewardIds[] = (int)$crow['reward_id'];
        }
        $cstmt->close();
    }
}

// Fetch the coffee/free refill reward (0 points reward)
$frstmt = $conn->prepare("SELECT reward_id FROM reward WHERE points = 0 AND (reward_type = 'Free Refill' OR reward_name LIKE '%free%refill%') AND (start_date IS NULL OR start_date <= CURDATE()) AND (expiration_date IS NULL OR expiration_date >= CURDATE()) LIMIT 1");
if ($frstmt) {
    $frstmt->execute();
    $frres = $frstmt->get_result();
    if ($frrow = $frres->fetch_assoc()) {
        $coffeeRewardId = (int)$frrow['reward_id'];
        // Check if this reward has been claimed
        $coffeeRewardClaimed = in_array($coffeeRewardId, $claimedRewardIds);
    }
    $frstmt->close();
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
    <title>Rewards - Cups & Stories Cafe</title>
    <link rel="stylesheet" href="../../public/assets/css/user-styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=logout" />
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

        .progress-claim-btn.claimed,
        .progress-claim-btn:disabled {
            background-color: #ccc;
            color: #666;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .progress-claim-btn.claimed:hover,
        .progress-claim-btn:disabled:hover {
            background-color: #ccc;
            transform: none;
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

        .reward-btn.claimed,
        .reward-btn:disabled {
            background-color: #ccc;
            color: #666;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .reward-btn.claimed:hover,
        .reward-btn:disabled:hover {
            background-color: #ccc;
            transform: none;
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

            // Close menu when clicking on a link
            const navLinks_items = document.querySelectorAll('#nav-links a');
            navLinks_items.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        navLinks.classList.remove('show');
                    }
                });
            });

            // Claim button functionality
            document.querySelectorAll('.reward-btn, .progress-claim-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    claimReward(this);

                    // Change button to claimed state
                    this.classList.remove('activate-btn', 'reward-btn');
                    this.classList.add('progress-claim-btn', 'claimed');
                    this.textContent = 'Claimed';
                    this.disabled = true;
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
            <nav id="nav-links">
                <a href="../../public/actions/auth/logout.php" class="logout">
                    <span class="material-symbols-outlined" alt="logout">logout</span>
                </a>
                <button id="qr-show-btn" class="profile-actions" title="Show QR" style="background:none;border:none;cursor:pointer;padding:0;margin-right:8px;">
                    <!-- simple QR SVG icon -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="3" width="6" height="6" stroke="#ffffff" fill="none" stroke-width="1.5" />
                        <rect x="15" y="3" width="6" height="6" stroke="#ffffff" fill="none" stroke-width="1.5" />
                        <rect x="3" y="15" width="6" height="6" stroke="#ffffff" fill="none" stroke-width="1.5" />
                        <rect x="11" y="11" width="2" height="2" fill="#ffffff" />
                        <rect x="14" y="11" width="2" height="2" fill="#ffffff" />
                        <rect x="11" y="14" width="2" height="2" fill="#ffffff" />
                    </svg>
                </button>
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
            </nav>
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
                <?php if ($coffeeRewardClaimed): ?>
                    <button class="serif progress-claim-btn claimed" disabled>Claimed</button>
                <?php else: ?>
                    <button class="serif progress-claim-btn">Claim</button>
                <?php endif; ?>
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
                    $isClaimed = in_array($rId, $claimedRewardIds);
                    $canClaim = ($customerPoints >= $rPoints && $rPoints > 0) || $rPoints == 0;

                ?>
                    <div class="reward-item" data-reward-id="<?php echo $rId; ?>">
                        <span class="reward-icon">🎟️</span>
                        <div class="reward-details">
                            <?php echo $rName; ?><br>
                            <span class="reward-valid">Valid until: <?php echo $rExp; ?></span>
                        </div>
                        <div class="reward-action">
                            <?php if ($rPoints > 0): ?>
                                <span class="reward-points"><?php echo $rPoints; ?> points</span>
                            <?php else: ?>
                                <?php if ($isClaimed): ?>
                                    <button class="serif reward-btn claimed" disabled>Claimed</button>
                                <?php else: ?>
                                    <button class="serif reward-btn">Claim</button>
                                <?php endif; ?>
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
                    <span>CUPS & Stories CAFE</span>
                </a>
            </div>
        </div>
    </footer>
</body>

</html>