<?php
session_start();
require_once '../../public/actions/auth/db_config.php';

// Fetch stores for filter dropdown
$stores = [];
$storeQuery = $conn->prepare("SELECT store_id, location FROM store ORDER BY store_id ASC");
if ($storeQuery) {
    $storeQuery->execute();
    $sres = $storeQuery->get_result();
    while ($sr = $sres->fetch_assoc()) {
        $stores[] = $sr;
    }
    $storeQuery->close();
}

// Selected store filter via GET (store id) - null means all
$selectedStore = null;
if (isset($_GET['store']) && is_numeric($_GET['store'])) {
    $selectedStore = intval($_GET['store']);
}

// Prepare sales by month (last 9 months)
$monthlyLabels = [];
$monthlyData = [];
 

// Prepare sales by month (last 9 months) with optional store filter
try {
    if ($selectedStore) {
        $sql = "SELECT DATE_FORMAT(o.order_date, '%b %Y') AS m, SUM(od.qty * od.price) AS total
            FROM `order` o
            JOIN orderdetails od ON o.order_id = od.order_id
            WHERE o.store_id = ?
            GROUP BY YEAR(o.order_date), MONTH(o.order_date)
            ORDER BY YEAR(o.order_date) DESC, MONTH(o.order_date) DESC
            LIMIT 9";
        $monthQuery = $conn->prepare($sql);
        if ($monthQuery) {
            $monthQuery->bind_param('i', $selectedStore);
            $monthQuery->execute();
            $mres = $monthQuery->get_result();
            $rows = [];
            while ($r = $mres->fetch_assoc()) {
                $rows[] = $r;
            }
            $monthQuery->close();
        }
    } else {
        $monthQuery = $conn->prepare("SELECT DATE_FORMAT(o.order_date, '%b %Y') AS m, SUM(od.qty * od.price) AS total
            FROM `order` o
            JOIN orderdetails od ON o.order_id = od.order_id
            GROUP BY YEAR(o.order_date), MONTH(o.order_date)
            ORDER BY YEAR(o.order_date) DESC, MONTH(o.order_date) DESC
            LIMIT 9");
        if ($monthQuery) {
            $monthQuery->execute();
            $mres = $monthQuery->get_result();
            $rows = [];
            while ($r = $mres->fetch_assoc()) {
                $rows[] = $r;
            }
            $monthQuery->close();
        }
    }

    if (!empty($rows)) {
        // reverse to chronological order
        $rows = array_reverse($rows);
        foreach ($rows as $r) {
            $monthlyLabels[] = $r['m'];
            $monthlyData[] = (float)$r['total'];
        }
    }
} catch (Exception $e) {}

// Daily summary (today) - split by member vs non-member
$dailyMemberSales = 0.0;
$dailyNonMemberSales = 0.0;
$dailyMemberCount = 0;
$dailyNonMemberCount = 0;
try {
    $today = date('Y-m-d');
    if ($selectedStore) {
        $dstmt = $conn->prepare("SELECT 
            SUM(CASE WHEN o.customer_id IS NOT NULL THEN od.qty * od.price ELSE 0 END) AS member_total,
            SUM(CASE WHEN o.customer_id IS NULL THEN od.qty * od.price ELSE 0 END) AS nonmember_total,
            COUNT(DISTINCT CASE WHEN o.customer_id IS NOT NULL THEN o.customer_id END) AS member_count,
            COUNT(DISTINCT CASE WHEN o.customer_id IS NULL THEN o.order_id END) AS nonmember_orders
            FROM `order` o
            JOIN orderdetails od ON o.order_id = od.order_id
            WHERE o.order_date = ? AND o.store_id = ?");
        if ($dstmt) {
            $dstmt->bind_param('si', $today, $selectedStore);
            $dstmt->execute();
            $dres = $dstmt->get_result();
            if ($dr = $dres->fetch_assoc()) {
                $dailyMemberSales = (float)($dr['member_total'] ?? 0);
                $dailyNonMemberSales = (float)($dr['nonmember_total'] ?? 0);
                $dailyMemberCount = (int)($dr['member_count'] ?? 0);
                $dailyNonMemberCount = (int)($dr['nonmember_orders'] ?? 0);
            }
            $dstmt->close();
        }
    } else {
        $dstmt = $conn->prepare("SELECT 
            SUM(CASE WHEN o.customer_id IS NOT NULL THEN od.qty * od.price ELSE 0 END) AS member_total,
            SUM(CASE WHEN o.customer_id IS NULL THEN od.qty * od.price ELSE 0 END) AS nonmember_total,
            COUNT(DISTINCT CASE WHEN o.customer_id IS NOT NULL THEN o.customer_id END) AS member_count,
            COUNT(DISTINCT CASE WHEN o.customer_id IS NULL THEN o.order_id END) AS nonmember_orders
            FROM `order` o
            JOIN orderdetails od ON o.order_id = od.order_id
            WHERE o.order_date = ?");
        if ($dstmt) {
            $dstmt->bind_param('s', $today);
            $dstmt->execute();
            $dres = $dstmt->get_result();
            if ($dr = $dres->fetch_assoc()) {
                $dailyMemberSales = (float)($dr['member_total'] ?? 0);
                $dailyNonMemberSales = (float)($dr['nonmember_total'] ?? 0);
                $dailyMemberCount = (int)($dr['member_count'] ?? 0);
                $dailyNonMemberCount = (int)($dr['nonmember_orders'] ?? 0);
            }
            $dstmt->close();
        }
    }
} catch (Exception $e) {}

// Categories sales (top categories)
$catLabels = [];
$catData = [];
try {
    if ($selectedStore) {
        $cstmt = $conn->prepare("SELECT p.product_category AS cat, SUM(od.qty) AS qty_sold
            FROM orderdetails od
            JOIN product p ON od.product_id = p.product_id
            JOIN `order` o ON od.order_id = o.order_id
            WHERE o.store_id = ?
            GROUP BY p.product_category
            ORDER BY qty_sold DESC
            LIMIT 11");
        if ($cstmt) {
            $cstmt->bind_param('i', $selectedStore);
            $cstmt->execute();
            $cres = $cstmt->get_result();
            while ($cr = $cres->fetch_assoc()) {
                $catLabels[] = $cr['cat'];
                $catData[] = (int)$cr['qty_sold'];
            }
            $cstmt->close();
        }
    } else {
        $cstmt = $conn->prepare("SELECT p.product_category AS cat, SUM(od.qty) AS qty_sold
            FROM orderdetails od
            JOIN product p ON od.product_id = p.product_id
            JOIN `order` o ON od.order_id = o.order_id
            GROUP BY p.product_category
            ORDER BY qty_sold DESC
            LIMIT 11");
        if ($cstmt) {
            $cstmt->execute();
            $cres = $cstmt->get_result();
            while ($cr = $cres->fetch_assoc()) {
                $catLabels[] = $cr['cat'];
                $catData[] = (int)$cr['qty_sold'];
            }
            $cstmt->close();
        }
    }
} catch (Exception $e) {}

// Export JSON for JS
$monthlyLabelsJson = json_encode($monthlyLabels);
$monthlyDataJson = json_encode($monthlyData);
$dailyJson = json_encode([$dailyMemberSales, $dailyNonMemberSales]);
$catLabelsJson = json_encode($catLabels);
$catDataJson = json_encode($catData);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Cafe Loyalty Reward</title>
    <link rel="stylesheet" href="../../public/assets/css/admin-styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
                if(window.innerWidth > 768) {
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

            // Initialize charts
            initSalesChart();
            initDailySalesChart();
            initCategoriesChart();

            // Print and Download button handlers
            const printBtn = document.getElementById('print-all-btn');
            const downloadBtn = document.getElementById('download-all-btn');
            
            if (printBtn) {
                printBtn.addEventListener('click', function() {
                    convertChartsToImages().then(() => {
                        window.print();
                    });
                });
            }

            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    downloadAsImage();
                });
            }

            // Store filter change handler (reload with store param)
            const storeSelect = document.getElementById('store-filter');
            if (storeSelect) {
                storeSelect.addEventListener('change', function() {
                    const val = this.value;
                    const url = new URL(window.location.href);
                    if (val) {
                        url.searchParams.set('store', val);
                    } else {
                        url.searchParams.delete('store');
                    }
                    window.location.href = url.toString();
                });
            }

            // Report range filter change handler (reload with range param)
            const reportSelect = document.getElementById('report-filter');
            if (reportSelect) {
                reportSelect.addEventListener('change', function() {
                    const val = this.value;
                    const url = new URL(window.location.href);
                    if (val) {
                        url.searchParams.set('range', val);
                    } else {
                        url.searchParams.delete('range');
                    }
                    window.location.href = url.toString();
                });
            }

            function convertChartsToImages() {
                return new Promise((resolve) => {
                    const canvases = document.querySelectorAll('.chart-container canvas');
                    let converted = 0;

                    if (canvases.length === 0) {
                        resolve();
                        return;
                    }

                    canvases.forEach(canvas => {
                        try {
                            const imageData = canvas.toDataURL('image/png');
                            const img = document.createElement('img');
                            img.src = imageData;
                            img.style.maxWidth = '100%';
                            img.style.height = 'auto';
                            img.style.display = 'block';
                            
                            // Replace canvas with image
                            const container = canvas.parentElement;
                            container.innerHTML = '';
                            container.appendChild(img);
                        } catch (e) {
                            console.error('Error converting chart:', e);
                        }
                        
                        converted++;
                        if (converted === canvases.length) {
                            resolve();
                        }
                    });
                });
            }

            function downloadAsImage() {
                // Export reports as CSV (Monthly, Daily, Categories)
                const timestamp = new Date().toISOString().split('T')[0];

                const monthlyLabels = <?php echo $monthlyLabelsJson; ?>;
                const monthlyData = <?php echo $monthlyDataJson; ?>;
                const dailyData = <?php echo $dailyJson; ?>;
                const catLabels = <?php echo $catLabelsJson; ?>;
                const catData = <?php echo $catDataJson; ?>;

                function esc(val) {
                    if (val === null || val === undefined) return '';
                    return '"' + String(val).replace(/"/g, '""') + '"';
                }

                const rows = [];
                rows.push('Generated:,' + esc(new Date().toLocaleString()));
                rows.push('');

                // Monthly
                rows.push('Monthly Sales Summary');
                rows.push('Month,Sales');
                monthlyLabels.forEach((label, idx) => {
                    const sale = (monthlyData[idx] !== undefined) ? Number(monthlyData[idx]).toFixed(2) : '0.00';
                    rows.push([esc(label), esc(sale)].join(','));
                });
                rows.push('');

                // Daily summary
                rows.push('Daily Summary (Today)');
                rows.push('Metric,Value');
                rows.push([esc('Member Sales'), esc((Number(dailyData[0] || 0)).toFixed(2))].join(','));
                rows.push([esc('Non-member Sales'), esc((Number(dailyData[1] || 0)).toFixed(2))].join(','));
                rows.push('');

                // Categories
                rows.push('Top Categories');
                rows.push('Category,Units Sold');
                catLabels.forEach((label, idx) => {
                    rows.push([esc(label), esc(catData[idx] !== undefined ? catData[idx] : 0)].join(','));
                });

                const csv = rows.join('\r\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'cafe-reports-' + timestamp + '.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
            }
        });

        function initSalesChart() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                        labels: <?php echo $monthlyLabelsJson; ?>,
                        datasets: [{
                            label: 'Sales',
                            data: <?php echo $monthlyDataJson; ?>,
                            backgroundColor: '#6b4423',
                            borderColor: '#6b4423',
                            borderWidth: 0,
                            borderRadius: 4
                        }]
                    },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: undefined,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 200,
                                font: {
                                    size: 12,
                                    family: "'Georgia', serif"
                                }
                            },
                            grid: {
                                color: '#f0ebe5'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 12,
                                    family: "'Georgia', serif"
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        function initDailySalesChart() {
            const ctx = document.getElementById('dailySalesChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                        data: {
                            labels: ['Member', 'Non-member'],
                            datasets: [{
                                data: <?php echo $dailyJson; ?>,
                                backgroundColor: ['#6b4423', '#c9b5a0'],
                                borderColor: '#fff',
                                borderWidth: 2
                            }]
                        },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 12,
                                    family: "'Georgia', serif"
                                },
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }

        function initCategoriesChart() {
            const ctx = document.getElementById('categoriesChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo $catLabelsJson; ?>,
                    datasets: [{
                        label: 'Sales',
                        data: <?php echo $catDataJson; ?>,
                        backgroundColor: '#6b4423',
                        borderColor: '#6b4423',
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 50,
                                font: {
                                    size: 10,
                                    family: "'Georgia', serif"
                                }
                            },
                            grid: {
                                color: '#f0ebe5'
                            }
                        },
                        y: {
                            ticks: {
                                font: {
                                    size: 11,
                                    family: "'Georgia', serif"
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    </script>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="cafe-logo">
                    <img src="../../public/assets/css/images/logo images/cups and stories logo.png" alt="Cafe Logo" class="logo-icon">
                </div>
                <button class="close-btn" id="sidebar-close-btn">✕</button>
            </div>

           <nav class="sidebar-nav">
                <a href="admin.php" class="nav-link">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="menu.php" class="nav-link">
                    <span class="nav-icon">🍽️</span>
                    <span class="nav-text">Menu</span>
                </a>
                <a href="transactions.php" class="nav-link">
                    <span class="nav-icon">💳</span>
                    <span class="nav-text">Transactions</span>
                </a>
                <a href="inventory.php" class="nav-link">
                    <span class="nav-icon">📦</span>
                    <span class="nav-text">Inventory</span>
                </a>
                <a href="inventory_reports.php" class="nav-link">
                    <span class="nav-icon">📦</span>
                   <span class="nav-text">Inventory Transactions</span>

                </a>
             <a href="members_list.php" class="nav-link">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Members</span>
                </a>
                 <a href="cashiers_list.php" class="nav-link">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Cashiers</span>
                </a>
                <a href="reports.php" class="nav-link active">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Reports</span>
                </a>
               <a href="settings.php" class="nav-link">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text">Settings</span>
                </a>
                <a href="page_view.php" class="nav-link">
                    <span class="nav-icon">📄</span>
                    <span class="nav-text">Edit Pages</span>
                </a>
                <a href="rewards.php" class="nav-link">
                    <span class="nav-icon">📄</span>
                    <span class="nav-text">Rewards</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="hamburger-btn" id="hamburger-menu-btn">☰</button>
                    <h1 class="page-title">Sales Report</h1>
                </div>
                <div class="header-right">
                    
                    
                   
                    <div class="admin-profile">
                        <span class="admin-label">Admin</span>
                        <!--                         <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User" class="profile-img">
 -->
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User" class="profile-img">
                    </div>
                </div>
            </header>

            <!-- Reports Content -->
            <div class="reports-content">
                <!-- Sales Summary Report -->
                <div class="report-card full-width">
                    <div class="report-header">
                        <div class="store-filter">
                            <div class="store-filter-divider">
                               
                                    <button class="action-btn print-action" id="print-all-btn">🖨️ Print</button>
                                    <button class="action-btn download-action" id="download-all-btn">⬇️ Download</button>
                                      <select id="store-filter" class="form-select">
                            <option value="">All Stores</option>
                            <?php foreach ($stores as $st): ?>
                                <option value="<?php echo $st['store_id']; ?>" <?php echo ($selectedStore === intval($st['store_id'])) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st['location']); ?></option>
                            <?php endforeach; ?>
                        </select>


                            </div>
                            
                      
                    </div>
                        <h3>Sales Summary Report</h3>
                        
                        <select id="report-filter" class="form-select report-filter">
                            <option value="yesterday">Yesterday</option>
                            <option value="last_week">Last Week</option>
                            <option value="this_month" selected>This Month</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="charts-row">
                    <!-- Daily Sales -->
                    <div class="report-card">
                        <h3 style="text-align: center;">Daily Sales 📊</h3>
                        <div class="chart-container">
                            <canvas id="dailySalesChart"></canvas>
                        </div>
                    </div>

                    <!-- Most Popular Categories -->
                    <div class="report-card">
                        <h3 style="text-align: center;">Most Popular Categories</h3>
                        <div class="chart-container">
                            <canvas id="categoriesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
