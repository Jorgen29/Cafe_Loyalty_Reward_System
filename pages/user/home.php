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
                <a href="../../public/actions/auth/logout.php">Logout</a>
                <a href="">QR Code</a>
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