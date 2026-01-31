<?php
session_start();
require_once '../../public/actions/auth/db_config.php';

// Fetch stores for filter dropdown
$stores = [];
$storeQuery = $conn->prepare("SELECT store_id, location FROM store WHERE store_id = 2 ORDER BY store_id ASC");
if ($storeQuery) {
    $storeQuery->execute();
    $sres = $storeQuery->get_result();
    while ($sr = $sres->fetch_assoc()) {
        $stores[] = $sr;
    }
    $storeQuery->close();
}

// Selected store filter via GET (store id) - null means all
// Selected store filter via GET (store id) - 2 means Branch 2
$selectedStore = 2;
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
} catch (Exception $e) {
}

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
} catch (Exception $e) {
}

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
} catch (Exception $e) {
}

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
                // Produce a styled HTML file and save with .xls extension so Excel opens it with styling
                const timestamp = new Date().toISOString().split('T')[0];

                const monthlyLabels = <?php echo $monthlyLabelsJson; ?>;
                const monthlyData = <?php echo $monthlyDataJson; ?>;
                const dailyData = <?php echo $dailyJson; ?>;
                const catLabels = <?php echo $catLabelsJson; ?>;
                const catData = <?php echo $catDataJson; ?>;

                const style = `
                    <style>
                        body { font-family: 'Georgia', serif; color: #333; }
                        .report-title { font-size:18px; font-weight:700; color:#6b4423; margin-bottom:8px; }
                        table { border-collapse: collapse; width: 100%; margin-bottom: 18px; }
                        th { background: #6b4423; color: #fff; padding: 8px 10px; text-align: left; }
                        td { padding: 8px 10px; border: 1px solid #e6dcd1; }
                        .section { margin-bottom: 22px; }
                        .meta { font-size:12px; color:#666; margin-bottom:10px; }
                    </style>
                `;

                let html = '<html><head><meta charset="utf-8">' + style + '</head><body>';
                html += '<div class="meta">Generated: ' + new Date().toLocaleString() + '</div>';

                // Monthly
                html += '<div class="section"><div class="report-title">Monthly Sales Summary</div>';
                html += '<table><tr><th>Month</th><th>Sales</th></tr>';
                monthlyLabels.forEach((label, idx) => {
                    html += '<tr><td>' + label + '</td><td>' + (monthlyData[idx] !== undefined ? Number(monthlyData[idx]).toFixed(2) : '0.00') + '</td></tr>';
                });
                html += '</table></div>';

                // Daily summary
                html += '<div class="section"><div class="report-title">Daily Summary (Today)</div>';
                html += '<table><tr><th>Metric</th><th>Value</th></tr>';
                html += '<tr><td>Member Sales</td><td>' + (Number(dailyData[0] || 0)).toFixed(2) + '</td></tr>';
                html += '<tr><td>Non-member Sales</td><td>' + (Number(dailyData[1] || 0)).toFixed(2) + '</td></tr>';
                html += '</table></div>';

                // Categories
                html += '<div class="section"><div class="report-title">Top Categories</div>';
                html += '<table><tr><th>Category</th><th>Units Sold</th></tr>';
                catLabels.forEach((label, idx) => {
                    html += '<tr><td>' + label + '</td><td>' + (catData[idx] !== undefined ? catData[idx] : 0) + '</td></tr>';
                });
                html += '</table></div>';

                html += '</body></html>';

                const blob = new Blob([html], {
                    type: 'application/vnd.ms-excel'
                });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'cafe-reports-' + timestamp + '.xls';
                link.click();
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
                    <img src="../../public/assets/css/images/logo images/whitelogo.png" alt="Cafe Logo" class="logo-icon">
                </div>
                <button class="close-btn" id="sidebar-close-btn">✕</button>
            </div>

            <nav class="sidebar-nav">
                <a href="cashier.php" class="nav-link ">
                    <span class="nav-icon material-icons">restaurant</span>
                    <span class="nav-text">Menu</span>
                </a>
                <a href="transactions.php" class="nav-link ">
                    <span class="nav-icon material-icons">payment</span>
                    <span class="nav-text">Transactions</span>
                </a>

                <a href="inventory.php" class="nav-link ">
                    <span class="nav-icon material-icons">kitchen</span>
                    <span class="nav-text">Ingredients</span>
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
                    <h1 class="page-title">Sales Report</h1>
                </div>
                <div class="header-right">
                    <div class="admin-profile">
                        <span class="admin-label"><?php echo htmlspecialchars($cashierName); ?></span>
                        <img src="../../public/icons/logo.png" alt="Cashier Profile" class="profile-img">
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

                        <select class="report-filter">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
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