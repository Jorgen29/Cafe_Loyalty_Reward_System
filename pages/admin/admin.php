<?php

/**
 * Admin Dashboard - Protected (renamed from admin.html)
 */
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=Please+log+in+first');
    exit;
}

// Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php?error=Unauthorized+access');
    exit;
}

// Admin display name
$adminName = htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);

// Optional: get total number of customers (for internal use, not displayed)
// Ensure DB config is available
require_once '../../public/actions/auth/db_config.php';
$totalCustomers = 0;
$totalRewards = 0;
$totalProducts = 0;
$totalIngredients = 0;
try {
    $cstmt = $conn->prepare("SELECT COUNT(*) AS total FROM customer");
    if ($cstmt) {
        $cstmt->execute();
        $cres = $cstmt->get_result();
        if ($crow = $cres->fetch_assoc()) {
            $totalCustomers = intval($crow['total'] ?? 0);
        }
        $cstmt->close();
    }
} catch (Exception $e) {
    // ignore — keep $totalCustomers at 0 on error
}

// Fetch recent transactions (latest 5)
$recentTransactions = [];
try {
    $rt = $conn->prepare("SELECT o.order_id, o.order_date, o.order_time, SUM(od.qty * od.price) AS total_amount
        FROM `order` o
        JOIN orderdetails od ON o.order_id = od.order_id
        GROUP BY o.order_id
        ORDER BY o.order_date DESC, o.order_time DESC
        LIMIT 5");
    if ($rt) {
        $rt->execute();
        $rres = $rt->get_result();
        while ($r = $rres->fetch_assoc()) {
            $recentTransactions[] = $r;
        }
        $rt->close();
    }
} catch (Exception $e) {
    // ignore
}

// Today's sales summary: earnings, items sold, distinct customers
$todayEarnings = 0.0;
$todayItems = 0;
$todayCustomers = 0;
try {
    $today = date('Y-m-d');
    $ds = $conn->prepare("SELECT SUM(od.qty * od.price) AS earnings, SUM(od.qty) AS items_sold, COUNT(DISTINCT CASE WHEN o.customer_id IS NOT NULL THEN o.customer_id END) AS customers
        FROM `order` o
        JOIN orderdetails od ON o.order_id = od.order_id
        WHERE o.order_date = ?");
    if ($ds) {
        $ds->bind_param('s', $today);
        $ds->execute();
        $dres = $ds->get_result();
        if ($dr = $dres->fetch_assoc()) {
            $todayEarnings = (float)($dr['earnings'] ?? 0);
            $todayItems = intval($dr['items_sold'] ?? 0);
            $todayCustomers = intval($dr['customers'] ?? 0);
        }
        $ds->close();
    }
} catch (Exception $e) {
    // ignore
}

try {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM reward");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $totalRewards = intval($row['total'] ?? 0);
        }
        $stmt->close();
    }
} catch (Exception $e) {
    // ignore — keep $totalRewards at 0 on error
}

try {
    $pstmt = $conn->prepare("SELECT COUNT(*) AS total FROM product");
    if ($pstmt) {
        $pstmt->execute();
        $pres = $pstmt->get_result();
        if ($prow = $pres->fetch_assoc()) {
            $totalProducts = intval($prow['total'] ?? 0);
        }
        $pstmt->close();
    }
} catch (Exception $e) {
    // ignore — keep $totalProducts at 0 on error
}


try {
    $ipstmt = $conn->prepare("SELECT COUNT(*) AS total FROM ingredient");
    if ($ipstmt) {
        $ipstmt->execute();
        $ipres = $ipstmt->get_result();
        if ($iprow = $ipres->fetch_assoc()) {
            $totalIngredients = intval($iprow['total'] ?? 0);
        }
        $ipstmt->close();
    }
} catch (Exception $e) {
    // ignore — keep $totalIngredients at 0 on error
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cafe Loyalty Reward</title>
    <link rel="stylesheet" href="../../public/assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.getElementById('hamburger-menu-btn');
            const sidebarCloseBtn = document.getElementById('sidebar-close-btn');
            const sidebar = document.querySelector('.sidebar');

            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.toggle('active');
                });
            }

            if (sidebarCloseBtn) {
                sidebarCloseBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.remove('active');
                });
            }

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                }
            });

            // Logout functionality
            const logoutBtn = document.querySelector('.admin-profile');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to logout?')) {
                        window.location.href = '../../public/actions/auth/logout.php';
                    }
                });
            }
        });
    </script>
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="cafe-logo">
                    <img src="../../public/assets/css/images/logo images/whitelogo.png" alt="Cafe Logo" class="logo-icon">
                </div>
                <!-- <div class="cafe-logo-name">
                    <img src="../../public/assets/css/images/logo images/whitelogo.png" alt="Cafe Logo Name" class="logo-icon-name">
                </div> -->
                <button class="close-btn" id="sidebar-close-btn">✕</button>
            </div>

            <nav class="sidebar-nav">
                <a href="admin.php" class="nav-link active">
                    <span class="nav-icon material-icons">dashboard</span>
                    <span class="nav-text">Dashboard</span>
                </a>
               
                <a href="menu.php" class="nav-link">
                    <span class="nav-icon material-icons">restaurant</span>
                    <span class="nav-text">Menu</span>
                </a>
                <a href="transactions.php" class="nav-link">
                    <span class="nav-icon material-icons">payment</span>
                    <span class="nav-text">Transactions</span>
                </a>
                <a href="rewards.php" class="nav-link">
                    <span class="nav-icon material-icons">confirmation_number</span>
                    <span class="nav-text">Rewards</span>
                </a>
                <a href="inventory.php" class="nav-link">
                    <span class="nav-icon material-icons">inventory_2</span>
                    <span class="nav-text">Inventory</span>
                </a>

                <a href="inventory_reports.php" class="nav-link">
                    <span class="nav-icon material-icons">inventory_2</span>
                    <span class="nav-text">Inventory Transactions</span>

                </a>
                <a href="members_list.php" class="nav-link">
                    <span class="nav-icon material-icons">people</span>
                    <span class="nav-text">Members</span>
                </a>
                <a href="cashiers_list.php" class="nav-link">
                    <span class="nav-icon material-icons">people</span>
                    <span class="nav-text">Cashiers</span>
                </a>
                <a href="reports.php" class="nav-link">
                    <span class="nav-icon material-icons">assessment</span>
                    <span class="nav-text">Reports</span>
                </a>
                 <a href="page_view.php" class="nav-link">
                    <span class="nav-icon material-icons">description</span>
                    <span class="nav-text">Pages Settings</span>
                </a>
                <a href="settings.php" class="nav-link">
                    <span class="nav-icon material-icons">settings</span>
                    <span class="nav-text">My Account</span>
                </a>
                
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="hamburger-btn" id="hamburger-menu-btn">☰</button>
                    <h1 class="serif page-title">Dashboard</h1>
                </div>
                <div class="header-right">

                    <div class="admin-profile">
                        <span class="admin-label"><?php echo $adminName ?: 'Admin'; ?></span>
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User" class="profile-img">

                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <a href="menu.php" style="text-decoration: none;">
                            <div class="stat-icon menu-icon">🍳</div>
                            <div class="stat-value"><?php echo $totalProducts; ?></div>
                            <div class="stat-label">Menu</div>
                        </a>
                    </div>

                    <div class="stat-card">
                        <a href="inventory.php" style="text-decoration: none;">
                            <div class="stat-icon inventory-icon">📦</div>
                            <div class="stat-value"><?php echo $totalIngredients; ?></div>
                            <div class="stat-label">Inventory</div>
                        </a>
                    </div>

                    <div class="stat-card">
                        <a href="rewards.php" style="text-decoration: none;">
                            <div class="stat-icon staff-icon">🎟️</div>
                            <div class="stat-value"><?php echo $totalRewards; ?></div>
                            <div class="stat-label">Rewards</div>
                        </a>
                    </div>

                    <div class="stat-card">
                        <a href="members_list.php" style="text-decoration: none;">
                            <div class="stat-icon membership-icon">👥</div>
                            <div class="stat-value"><?php echo $totalCustomers; ?></div>
                            <div class="stat-label">Membership</div>
                        </a>
                    </div>
                </div>

                <!-- Sales Section -->
                <div class="sales-section">
                    <!-- Recent Transactions -->
                    <a href="transactions.php" style="text-decoration: none;">
                        <div class="transaction-card">

                            <div class="card-header">
                                <div class="receipt-icon">📋</div>
                                <div class="receipt-date"><?php echo date('F , Y'); ?> Transactions</div>
                            </div>
                            <table class="transaction-table">
                                <thead>
                                    <tr>
                                        <th>Receipt #</th>
                                        <th>Total</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php if (!empty($recentTransactions)): ?>
                                        <?php foreach ($recentTransactions as $t): ?>

                                            <tr>

                                                <td>#<?php echo htmlspecialchars($t['order_id']); ?></td>
                                                <td>₱<?php echo number_format((float)($t['total_amount'] ?? 0), 2); ?></td>
                                                <td><?php echo htmlspecialchars(date('g:i A', strtotime($t['order_time'] ?? '00:00:00'))); ?></td>

                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" style="text-align:center;padding:20px;">No recent transactions.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>

                            </table>
                        </div>
                    </a>

                    <!-- Today's Sales Summary -->
                    <div class="sales-summary">
                        <a href="reports.php" style="text-decoration: none;">
                            <div class="summary-header">
                                <span class="summary-title">Today's Sales</span>
                                <div class="sales-chart-icon">📈</div>
                            </div>
                            <div class="summary-content">
                                <div class="summary-row">
                                    <span class="summary-label">Total Earnings:</span>
                                    <span class="summary-value">₱<?php echo number_format($todayEarnings, 2); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Amt. of Items Sold:</span>
                                    <span class="summary-value"><?php echo $todayItems; ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Amt. of Customers:</span>
                                    <span class="summary-value"><?php echo $todayCustomers; ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>