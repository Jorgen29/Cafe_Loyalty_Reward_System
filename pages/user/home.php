<?php

/**
 * User Home Page
 * Protected page - requires authentication
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=Please+log+in+first');
    exit;
}

// Check if user has the correct role (user or admin)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'user' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../index.php?error=Unauthorized+access');
    exit;
}

// Get user info from session
$userName = htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
$userEmail = htmlspecialchars($_SESSION['email']);
// Load home page assets from database
require_once '../../public/actions/auth/db_config.php';

$home_cover_image = null;
$home_cover_text = '';
$home_menu_title = '';
$home_menu_image = null;
$home_menu_description = '';

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT cover_image, cover_text, menu_teaser_title, menu_teaser_image, menu_teaser_description FROM home_page_assets LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $home_cover_image = !empty($row['cover_image']) ? $row['cover_image'] : null;
            $home_cover_text = !empty($row['cover_text']) ? $row['cover_text'] : '';
            $home_menu_title = !empty($row['menu_teaser_title']) ? $row['menu_teaser_title'] : '';
            $home_menu_image = !empty($row['menu_teaser_image']) ? $row['menu_teaser_image'] : null;
            $home_menu_description = !empty($row['menu_teaser_description']) ? $row['menu_teaser_description'] : '';
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
    <title>Homepage - Cups & Stories Cafe</title>
    <link rel="stylesheet" href="../../public/assets/css/user-styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=logout" />
    <style>
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
    </style>

    <!-- QR generation library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.getElementById('hamburger-btn');
            const navLinks = document.getElementById('nav-links');

            hamburgerBtn.addEventListener('click', function() {
                navLinks.classList.toggle('show');
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    navLinks.classList.remove('show');
                }
            });

            // Close menu when clicking on a link
            const navLinks_items = document.querySelectorAll('#nav-links a');
            navLinks_items.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        navLinks.classList.remove('show');
                    }
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
        <img src="../../<?php echo $home_cover_image; ?>" alt="Banner" class="banner">
        <div class="serif banner-text"><?php echo $home_cover_text; ?></div>
    </div>

    <div class="main-section">
        <div class="offer-section">
            <div>
                <div class="serif offer-title">We Offer</div>
                <div class="serif offer-desc">Blueberry<br>Cheesecake</div>
            </div>
            <img src="../../<?php echo $home_menu_image; ?>" alt="Blueberry Cheesecake" class="offer-img">
        </div>
    </div>

    <div class="rewards-section">
        <div>
            <div class="serif rewards-title">Enjoy more rewards</div>
            <div class="serif discount-ticket">%<br>Discount</div>
        </div>
        <div class="rewards-desc">
            Redeem your points to<br>claim exciting rewards
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