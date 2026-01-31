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

// Date range filter
$selectedRange = isset($_GET['range']) ? trim($_GET['range']) : 'this_month';
$rangeStart = null;
$rangeEnd = date('Y-m-d'); // today

if ($selectedRange === 'today') {
    $rangeStart = date('Y-m-d');
    $rangeEnd = date('Y-m-d');
} elseif ($selectedRange === 'yesterday') {
    $rangeStart = date('Y-m-d', strtotime('-1 day'));
    $rangeEnd = date('Y-m-d', strtotime('-1 day'));
} elseif ($selectedRange === 'last_week') {
    $rangeStart = date('Y-m-d', strtotime('-7 days'));
    $rangeEnd = date('Y-m-d');
} elseif ($selectedRange === 'last_month') {
    // First day of last month to last day of last month
    $rangeStart = date('Y-m-01', strtotime('first day of last month'));
    $rangeEnd = date('Y-m-d', strtotime('last day of last month'));
} elseif ($selectedRange === 'this_month') {
    $rangeStart = date('Y-m-01'); // First day of this month
    $rangeEnd = date('Y-m-d');
}

// Prepare sales by month (last 9 months)
$monthlyLabels = [];
$monthlyData = [];

// Prepare sales by day with optional store filter and date range
try {
    if ($selectedStore) {
        $sql = "SELECT DATE_FORMAT(o.order_date, '%M %d, %Y') AS m, SUM(od.qty * od.price) AS total
            FROM `order` o
            JOIN orderdetails od ON o.order_id = od.order_id
            WHERE o.store_id = ? AND o.order_date >= ? AND o.order_date <= ?
            GROUP BY DATE(o.order_date)
            ORDER BY o.order_date ASC";
        $monthQuery = $conn->prepare($sql);
        if ($monthQuery) {
            $monthQuery->bind_param('iss', $selectedStore, $rangeStart, $rangeEnd);
            $monthQuery->execute();
            $mres = $monthQuery->get_result();
            $rows = [];
            while ($r = $mres->fetch_assoc()) {
                $rows[] = $r;
            }
            $monthQuery->close();
        }
    } else {
        $monthQuery = $conn->prepare("SELECT DATE_FORMAT(o.order_date, '%M %d, %Y') AS m, SUM(od.qty * od.price) AS total
            FROM `order` o
            JOIN orderdetails od ON o.order_id = od.order_id
            WHERE o.order_date >= ? AND o.order_date <= ?
            GROUP BY DATE(o.order_date)
            ORDER BY o.order_date ASC");
        if ($monthQuery) {
            $monthQuery->bind_param('ss', $rangeStart, $rangeEnd);
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

// Daily summary - split by member vs non-member with date range filter
$dailyMemberSales = 0.0;
$dailyNonMemberSales = 0.0;
$dailyMemberCount = 0;
$dailyNonMemberCount = 0;
try {
    if ($selectedStore) {
        $dstmt = $conn->prepare("SELECT 
            SUM(CASE WHEN o.customer_id IS NOT NULL THEN od.qty * od.price ELSE 0 END) AS member_total,
            SUM(CASE WHEN o.customer_id IS NULL THEN od.qty * od.price ELSE 0 END) AS nonmember_total,
            COUNT(DISTINCT CASE WHEN o.customer_id IS NOT NULL THEN o.customer_id END) AS member_count,
            COUNT(DISTINCT CASE WHEN o.customer_id IS NULL THEN o.order_id END) AS nonmember_orders
            FROM `order` o
            JOIN orderdetails od ON o.order_id = od.order_id
            WHERE o.order_date >= ? AND o.order_date <= ? AND o.store_id = ?");
        if ($dstmt) {
            $dstmt->bind_param('ssi', $rangeStart, $rangeEnd, $selectedStore);
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
            WHERE o.order_date >= ? AND o.order_date <= ?");
        if ($dstmt) {
            $dstmt->bind_param('ss', $rangeStart, $rangeEnd);
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

// Categories sales (top categories) with date range filter
$catLabels = [];
$catData = [];
try {
    if ($selectedStore) {
        $cstmt = $conn->prepare("SELECT p.product_category AS cat, SUM(od.qty) AS qty_sold
            FROM orderdetails od
            JOIN product p ON od.product_id = p.product_id
            JOIN `order` o ON od.order_id = o.order_id
            WHERE o.store_id = ? AND o.order_date >= ? AND o.order_date <= ?
            GROUP BY p.product_category
            ORDER BY qty_sold DESC
            LIMIT 11");
        if ($cstmt) {
            $cstmt->bind_param('iss', $selectedStore, $rangeStart, $rangeEnd);
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
            WHERE o.order_date >= ? AND o.order_date <= ?
            GROUP BY p.product_category
            ORDER BY qty_sold DESC
            LIMIT 11");
        if ($cstmt) {
            $cstmt->bind_param('ss', $rangeStart, $rangeEnd);
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

// Fetch most sold items
$mostSoldItems = [];
try {
    if ($selectedStore) {
        $msi = $conn->prepare("SELECT p.product_id, p.product_name, SUM(od.qty) AS quantity_sold, SUM(od.qty * od.price) AS total_sales
            FROM orderdetails od
            JOIN product p ON od.product_id = p.product_id
            JOIN `order` o ON od.order_id = o.order_id
            WHERE o.store_id = ?
            GROUP BY od.product_id
            ORDER BY quantity_sold DESC
            LIMIT 5");
        if ($msi) {
            $msi->bind_param('i', $selectedStore);
            $msi->execute();
            $msires = $msi->get_result();
            while ($msirow = $msires->fetch_assoc()) {
                $mostSoldItems[] = $msirow;
            }
            $msi->close();
        }
    } else {
        $msi = $conn->prepare("SELECT p.product_id, p.product_name, SUM(od.qty) AS quantity_sold, SUM(od.qty * od.price) AS total_sales
            FROM orderdetails od
            JOIN product p ON od.product_id = p.product_id
            GROUP BY od.product_id
            ORDER BY quantity_sold DESC
            LIMIT 5");
        if ($msi) {
            $msi->execute();
            $msires = $msi->get_result();
            while ($msirow = $msires->fetch_assoc()) {
                $mostSoldItems[] = $msirow;
            }
            $msi->close();
        }
    }
} catch (Exception $e) {
}

// Fetch most loyal customers (top 5) with date range filter
$mostLoyalCustomers = [];
try {
    if ($selectedStore) {
        $mlc = $conn->prepare("SELECT c.customer_id, c.first_name, c.last_name, COUNT(o.order_id) AS total_orders, SUM(od.qty * od.price) AS total_spent
            FROM customer c
            JOIN `order` o ON c.customer_id = o.customer_id
            JOIN orderdetails od ON o.order_id = od.order_id
            WHERE o.store_id = ? AND o.order_date >= ? AND o.order_date <= ?
            GROUP BY c.customer_id
            ORDER BY total_spent DESC
            LIMIT 5");
        if ($mlc) {
            $mlc->bind_param('iss', $selectedStore, $rangeStart, $rangeEnd);
            $mlc->execute();
            $mlcres = $mlc->get_result();
            while ($mlcrow = $mlcres->fetch_assoc()) {
                $mostLoyalCustomers[] = $mlcrow;
            }
            $mlc->close();
        }
    } else {
        $mlc = $conn->prepare("SELECT c.customer_id, c.first_name, c.last_name, COUNT(o.order_id) AS total_orders, SUM(od.qty * od.price) AS total_spent
            FROM customer c
            JOIN `order` o ON c.customer_id = o.customer_id
            JOIN orderdetails od ON o.order_id = od.order_id
            WHERE o.order_date >= ? AND o.order_date <= ?
            GROUP BY c.customer_id
            ORDER BY total_spent DESC
            LIMIT 5");
        if ($mlc) {
            $mlc->bind_param('ss', $rangeStart, $rangeEnd);
            $mlc->execute();
            $mlcres = $mlc->get_result();
            while ($mlcrow = $mlcres->fetch_assoc()) {
                $mostLoyalCustomers[] = $mlcrow;
            }
            $mlc->close();
        }
    }
} catch (Exception $e) {
}

// Fetch sales by product (all products) with date range filter
$salesByProduct = [];
try {
    if ($selectedStore) {
        $sbp = $conn->prepare("SELECT p.product_id, p.product_name, SUM(od.qty) AS quantity_sold, SUM(od.qty * od.price) AS total_sales
            FROM orderdetails od
            JOIN product p ON od.product_id = p.product_id
            JOIN `order` o ON od.order_id = o.order_id
            WHERE o.store_id = ? AND o.order_date >= ? AND o.order_date <= ?
            GROUP BY od.product_id
            ORDER BY total_sales DESC");
        if ($sbp) {
            $sbp->bind_param('iss', $selectedStore, $rangeStart, $rangeEnd);
            $sbp->execute();
            $sbpres = $sbp->get_result();
            while ($sbprow = $sbpres->fetch_assoc()) {
                $salesByProduct[] = $sbprow;
            }
            $sbp->close();
        }
    } else {
        $sbp = $conn->prepare("SELECT p.product_id, p.product_name, SUM(od.qty) AS quantity_sold, SUM(od.qty * od.price) AS total_sales
            FROM orderdetails od
            JOIN product p ON od.product_id = p.product_id
            JOIN `order` o ON od.order_id = o.order_id
            WHERE o.order_date >= ? AND o.order_date <= ?
            GROUP BY od.product_id
            ORDER BY total_sales DESC");
        if ($sbp) {
            $sbp->bind_param('ss', $rangeStart, $rangeEnd);
            $sbp->execute();
            $sbpres = $sbp->get_result();
            while ($sbprow = $sbpres->fetch_assoc()) {
                $salesByProduct[] = $sbprow;
            }
            $sbp->close();
        }
    }
} catch (Exception $e) {
}

// Fetch most sold items
$mostSoldItems = [];
try {
    if ($selectedStore) {
        $msi = $conn->prepare("SELECT p.product_id, p.product_name, SUM(od.qty) AS quantity_sold, SUM(od.qty * od.price) AS total_sales
            FROM orderdetails od
            JOIN product p ON od.product_id = p.product_id
            JOIN `order` o ON od.order_id = o.order_id
            WHERE o.store_id = ?
            GROUP BY od.product_id
            ORDER BY quantity_sold DESC
            LIMIT 5");
        if ($msi) {
            $msi->bind_param('i', $selectedStore);
            $msi->execute();
            $msires = $msi->get_result();
            while ($msirow = $msires->fetch_assoc()) {
                $mostSoldItems[] = $msirow;
            }
            $msi->close();
        }
    } else {
        $msi = $conn->prepare("SELECT p.product_id, p.product_name, SUM(od.qty) AS quantity_sold, SUM(od.qty * od.price) AS total_sales
            FROM orderdetails od
            JOIN product p ON od.product_id = p.product_id
            GROUP BY od.product_id
            ORDER BY quantity_sold DESC
            LIMIT 5");
        if ($msi) {
            $msi->execute();
            $msires = $msi->get_result();
            while ($msirow = $msires->fetch_assoc()) {
                $mostSoldItems[] = $msirow;
            }
            $msi->close();
        }
    }
} catch (Exception $e) {
}

// Fetch most loyal customers (top 5) with date range filter
$mostLoyalCustomers = [];
try {
    if ($selectedStore) {
        $mlc = $conn->prepare("SELECT c.customer_id, c.first_name, c.last_name, COUNT(o.order_id) AS total_orders, SUM(od.qty * od.price) AS total_spent
            FROM customer c
            JOIN `order` o ON c.customer_id = o.customer_id
            JOIN orderdetails od ON o.order_id = od.order_id
            WHERE o.store_id = ? AND o.order_date >= ? AND o.order_date <= ?
            GROUP BY c.customer_id
            ORDER BY total_spent DESC
            LIMIT 5");
        if ($mlc) {
            $mlc->bind_param('iss', $selectedStore, $rangeStart, $rangeEnd);
            $mlc->execute();
            $mlcres = $mlc->get_result();
            while ($mlcrow = $mlcres->fetch_assoc()) {
                $mostLoyalCustomers[] = $mlcrow;
            }
            $mlc->close();
        }
    } else {
        $mlc = $conn->prepare("SELECT c.customer_id, c.first_name, c.last_name, COUNT(o.order_id) AS total_orders, SUM(od.qty * od.price) AS total_spent
            FROM customer c
            JOIN `order` o ON c.customer_id = o.customer_id
            JOIN orderdetails od ON o.order_id = od.order_id
            WHERE o.order_date >= ? AND o.order_date <= ?
            GROUP BY c.customer_id
            ORDER BY total_spent DESC
            LIMIT 5");
        if ($mlc) {
            $mlc->bind_param('ss', $rangeStart, $rangeEnd);
            $mlc->execute();
            $mlcres = $mlc->get_result();
            while ($mlcrow = $mlcres->fetch_assoc()) {
                $mostLoyalCustomers[] = $mlcrow;
            }
            $mlc->close();
        }
    }
} catch (Exception $e) {
}

// Fetch sales by product (all products) with date range filter
$salesByProduct = [];
try {
    if ($selectedStore) {
        $sbp = $conn->prepare("SELECT p.product_id, p.product_name, SUM(od.qty) AS quantity_sold, SUM(od.qty * od.price) AS total_sales
            FROM orderdetails od
            JOIN product p ON od.product_id = p.product_id
            JOIN `order` o ON od.order_id = o.order_id
            WHERE o.store_id = ? AND o.order_date >= ? AND o.order_date <= ?
            GROUP BY od.product_id
            ORDER BY total_sales DESC");
        if ($sbp) {
            $sbp->bind_param('iss', $selectedStore, $rangeStart, $rangeEnd);
            $sbp->execute();
            $sbpres = $sbp->get_result();
            while ($sbprow = $sbpres->fetch_assoc()) {
                $salesByProduct[] = $sbprow;
            }
            $sbp->close();
        }
    } else {
        $sbp = $conn->prepare("SELECT p.product_id, p.product_name, SUM(od.qty) AS quantity_sold, SUM(od.qty * od.price) AS total_sales
            FROM orderdetails od
            JOIN product p ON od.product_id = p.product_id
            JOIN `order` o ON od.order_id = o.order_id
            WHERE o.order_date >= ? AND o.order_date <= ?
            GROUP BY od.product_id
            ORDER BY total_sales DESC");
        if ($sbp) {
            $sbp->bind_param('ss', $rangeStart, $rangeEnd);
            $sbp->execute();
            $sbpres = $sbp->get_result();
            while ($sbprow = $sbpres->fetch_assoc()) {
                $salesByProduct[] = $sbprow;
            }
            $sbp->close();
        }
    }
} catch (Exception $e) {
}

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
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
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
                const blob = new Blob([csv], {
                    type: 'text/csv;charset=utf-8;'
                });
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
                    <img src="../../public/assets/css/images/logo images/whitelogo.png" alt="Cafe Logo" class="logo-icon">
                </div>
                <button class="close-btn" id="sidebar-close-btn">✕</button>
            </div>

            <nav class="sidebar-nav">
                <a href="admin.php" class="nav-link">
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
                <a href="reports.php" class="nav-link active">
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
                            <option value="today" <?php echo $selectedRange === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $selectedRange === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="last_week" <?php echo $selectedRange === 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                            <option value="last_month" <?php echo $selectedRange === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="this_month" <?php echo $selectedRange === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                        <div style="margin-top: 10px; font-size: 12px; color: #666;">
                            <strong>Date Range:</strong> <?php echo date('M d, Y', strtotime($rangeStart)); ?> to <?php echo date('M d, Y', strtotime($rangeEnd)); ?>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Most Loyal Customers and Most Popular Categories Row -->
                <div style="display: flex; gap: 20px; margin-top: 30px;">
                    <!-- Most Loyal Customers Section -->
                    <div class="report-card" style="flex: 1;">
                        <h3>Most Loyal Customers</h3>
                        <div class="table-wrapper" style="margin-top: 15px;">
                            <table class="menu-table" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background-color: #f5f1ed; border-bottom: 2px solid #ddd;">
                                        <th style="padding: 12px; text-align: left; color: #333; font-weight: 600;">Customer Name</th>
                                        <th style="padding: 12px; text-align: center; color: #333; font-weight: 600;">Orders</th>
                                        <th style="padding: 12px; text-align: right; color: #333; font-weight: 600;">Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($mostLoyalCustomers)): ?>
                                        <?php foreach ($mostLoyalCustomers as $customer): ?>
                                            <tr style="border-bottom: 1px solid #eee;">
                                                <td style="padding: 12px; font-size: 12px;"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                                                <td style="padding: 12px; text-align: center; font-size: 12px;"><?php echo intval($customer['total_orders']); ?></td>
                                                <td style="padding: 12px; text-align: right; font-size: 12px;">₱<?php echo number_format((float)($customer['total_spent'] ?? 0), 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center; padding: 20px; color: #999;">No customer data available.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Most Popular Categories -->
                    <div class="report-card" style="flex: 1;">
                        <h3 style="text-align: center;">Most Popular Categories</h3>
                        <div class="chart-container">
                            <canvas id="categoriesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Daily Sales and All Products Sales Row -->
                <div style="display: flex; gap: 20px; margin-top: 30px;">
                    <!-- Daily Sales -->
                    <div class="report-card" style="flex: 1;">
                        <h3 style="text-align: center;">Daily Sales 📊</h3>
                        <div class="chart-container">
                            <canvas id="dailySalesChart"></canvas>
                        </div>
                    </div>

                    <!-- All Products Sales Section -->
                    <div class="report-card" style="flex: 1;">
                        <h3>All Products Sales</h3>
                        <div class="table-wrapper" style="margin-top: 15px;">
                            <table class="menu-table" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background-color: #f5f1ed; border-bottom: 2px solid #ddd;">
                                        <th style="padding: 12px; text-align: left; color: #333; font-weight: 600;">Product Name</th>
                                        <th style="padding: 12px; text-align: center; color: #333; font-weight: 600;">Qty Sold</th>
                                        <th style="padding: 12px; text-align: right; color: #333; font-weight: 600;">Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($salesByProduct)): ?>
                                        <?php $count = 0;
                                        foreach ($salesByProduct as $product): ?>
                                            <?php if ($count >= 5) break; ?>
                                            <tr style="border-bottom: 1px solid #eee;">
                                                <td style="padding: 12px; font-size: 12px;"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                <td style="padding: 12px; text-align: center; font-size: 12px;"><?php echo intval($product['quantity_sold']); ?></td>
                                                <td style="padding: 12px; text-align: right; font-size: 12px;">₱<?php echo number_format((float)($product['total_sales'] ?? 0), 2); ?></td>
                                            </tr>
                                            <?php $count++; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center; padding: 20px; color: #999;">No product sales data available.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>