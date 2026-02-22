<?php

/**
 * Admin Transactions Page
 * Protected page - only admins can access
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=Please+log+in+first');
    exit;
}

// Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../../index.php?error=Unauthorized+access');
    exit;
}

// Include database configuration
require_once '../../public/actions/auth/db_config.php';

// Get admin name from session
$adminName = htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);

// Fetch all orders with customer details
$orders = [];
$query = $conn->prepare("
    SELECT o.order_id, o.order_date, o.order_time, o.payment_method, o.payment_reference, o.payment_datetime,
           c.first_name, c.last_name, c.customer_id,
           r.discount_percent, r.discount_amount, r.reward_name
    FROM `order` o
    LEFT JOIN customer c ON o.customer_id = c.customer_id
    LEFT JOIN reward r ON o.reward_id = r.reward_id
    ORDER BY o.order_date DESC, o.order_time DESC
");
if ($query) {
    $query->execute();
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $query->close();
}

// Function to calculate total amount for an order
function getOrderTotal($conn, $orderId)
{
    $query = $conn->prepare("SELECT SUM(qty * price) as total FROM orderdetails WHERE order_id = ?");
    if ($query) {
        $query->bind_param("i", $orderId);
        $query->execute();
        $result = $query->get_result();
        $row = $result->fetch_assoc();
        $query->close();
        return $row['total'] ?? 0;
    }
    return 0;
}

// Function to get order details (items)
function getOrderDetails($conn, $orderId)
{
    $details = [];
    $query = $conn->prepare("
        SELECT od.product_id, od.qty, od.price, p.product_name
        FROM orderdetails od
        JOIN product p ON od.product_id = p.product_id
        WHERE od.order_id = ?
    ");
    if ($query) {
        $query->bind_param("i", $orderId);
        $query->execute();
        $result = $query->get_result();
        while ($row = $result->fetch_assoc()) {
            $details[] = $row;
        }
        $query->close();
    }
    return $details;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Transactions - Cups & Stories Cafe</title>
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

            // Search functionality
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    filterTable(e.target.value);
                });
            }

            // Sort dropdown functionality
            const sortDropdown = document.querySelector('.sort-dropdown');
            if (sortDropdown) {
                sortDropdown.addEventListener('change', function(e) {
                    sortTable(e.target.value);
                });
            }

            // View detail buttons - using event delegation
            const tableBody = document.querySelector('.transactions-table tbody');
            if (tableBody) {
                tableBody.addEventListener('click', function(e) {
                    if (e.target.classList.contains('view-detail-btn')) {
                        openTransactionDetail(e.target);
                    }
                });
            }
        });

        function filterTable(searchTerm) {
            const table = document.querySelector('.transactions-table');
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
            });
        }

        function sortTable(sortBy) {
            const table = document.querySelector('.transactions-table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr:not(.date-header-row)'));
            const dateHeaders = Array.from(tbody.querySelectorAll('tr.date-header-row'));

            rows.sort((a, b) => {
                let aVal, bVal;

                if (sortBy === 'latest') {
                    aVal = a.querySelector('td:nth-child(1)').textContent.replace('#', '');
                    bVal = b.querySelector('td:nth-child(1)').textContent.replace('#', '');
                    return parseInt(bVal) - parseInt(aVal);
                } else if (sortBy === 'oldest') {
                    aVal = a.querySelector('td:nth-child(1)').textContent.replace('#', '');
                    bVal = b.querySelector('td:nth-child(1)').textContent.replace('#', '');
                    return parseInt(aVal) - parseInt(bVal);
                } else if (sortBy === 'highest') {
                    aVal = a.querySelector('td:nth-child(3)').textContent.replace('₱', '').trim();
                    bVal = b.querySelector('td:nth-child(3)').textContent.replace('₱', '').trim();
                    return parseFloat(bVal) - parseFloat(aVal);
                } else if (sortBy === 'lowest') {
                    aVal = a.querySelector('td:nth-child(3)').textContent.replace('₱', '').trim();
                    bVal = b.querySelector('td:nth-child(3)').textContent.replace('₱', '').trim();
                    return parseFloat(aVal) - parseFloat(bVal);
                }
            });

            // Clear tbody and add sorted rows
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
        }

        function openTransactionDetail(button) {
            const row = button.closest('tr');
            if (!row) {
                console.error('No row found');
                return;
            }

            try {
                const orderId = row.getAttribute('data-order-id');
                const receiptId = '#' + orderId;
                const customer = row.querySelector('td:nth-child(2)').textContent;
                const amount = row.querySelector('td:nth-child(3)').textContent;
                const time = row.querySelector('td:nth-child(4)').textContent;
                const orderDate = row.getAttribute('data-order-date');
                const paymentMethod = row.getAttribute('data-payment-method');
                const paymentReference = row.getAttribute('data-payment-reference');
                const paymentDatetime = row.getAttribute('data-payment-datetime');
                const paymentDiscount = row.getAttribute('data-payment-discount');
                const customerId = row.getAttribute('data-customer-id');
                const orderDetails = JSON.parse(row.getAttribute('data-order-details'));

                // Populate modal with transaction details - use null safe method
                const setElementText = (id, value) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = value;
                };

                setElementText('detailReceiptId', receiptId);
                setElementText('detailDate', orderDate);
                setElementText('detailCustomer', customer || 'Guest');
                setElementText('detailAmount', amount);
                setElementText('detailTime', time);
                setElementText('detailPaymentMethod', paymentMethod || 'N/A');
                setElementText('detailPaymentReference', (paymentReference && paymentReference !== 'N/A' && paymentReference !== 'null') ? paymentReference : 'N/A');

                // Handle discount display
                const discountEl = document.getElementById('detailPaymentDiscount');
                if (discountEl) {
                    if (paymentDiscount > 0) {
                        discountEl.textContent = '-₱' + parseFloat(paymentDiscount).toFixed(2) + (rewardName ? ' (' + rewardName + ')' : '');
                    } else {
                        discountEl.textContent = '₱0.00';
                    }
                }

                const paymentDtEl = document.getElementById('detailPaymentDatetime');
                if (paymentDtEl) {
                    if (paymentDatetime && paymentDatetime !== 'N/A' && paymentDatetime !== 'null') {
                        try {
                            const dt = new Date(paymentDatetime);
                            paymentDtEl.textContent = isNaN(dt.getTime()) ? 'N/A' : dt.toLocaleString();
                        } catch (e) {
                            paymentDtEl.textContent = 'N/A';
                        }
                    } else {
                        paymentDtEl.textContent = 'N/A';
                    }
                }

                // Populate order items
                const orderItemsContainer = document.getElementById('orderItemsContainer');
                if (orderItemsContainer) {
                    orderItemsContainer.innerHTML = '';

                    orderDetails.forEach(item => {
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'order-item';
                        itemDiv.innerHTML = `
                            <div>
                                <p class="item-name">${item.product_name}</p>
                                <p class="item-qty">${item.qty} x ₱${parseFloat(item.price).toFixed(2)}</p>
                            </div>
                            <p class="item-price">₱${(item.qty * item.price).toFixed(2)}</p>
                        `;
                        orderItemsContainer.appendChild(itemDiv);
                    });
                }

                const modal = document.getElementById('transactionDetailModal');
                if (modal) {
                    modal.style.display = 'block';
                }
            } catch (e) {
                console.error('Error opening transaction detail:', e);
                alert('Error opening transaction details. Please try again.');
            }
        }

        function closeModal() {
            const modal = document.getElementById('transactionDetailModal');
            modal.style.display = 'none';
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('transactionDetailModal');
            if (event.target === modal) {
                closeModal();
            }
        });

        function openTransactionDetail(button) {
            const row = button.closest('tr');
            if (!row) {
                console.error('No row found');
                return;
            }

            try {
                const orderId = row.getAttribute('data-order-id');
                const receiptId = '#' + orderId;
                const customer = row.querySelector('td:nth-child(2)').textContent;
                const amount = row.querySelector('td:nth-child(3)').firstChild.textContent.trim();
                const time = row.querySelector('td:nth-child(4)').textContent;
                const orderDate = row.getAttribute('data-order-date');
                const paymentMethod = row.getAttribute('data-payment-method');
                const paymentReference = row.getAttribute('data-payment-reference');
                const paymentDatetime = row.getAttribute('data-payment-datetime');
                const paymentDiscount = row.getAttribute('data-payment-discount');
                const rewardName = row.getAttribute('data-reward-name');
                const customerId = row.getAttribute('data-customer-id');
                const orderDetails = JSON.parse(row.getAttribute('data-order-details'));

                const setElementText = (id, value) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = value;
                };

                setElementText('detailReceiptId', receiptId);
                setElementText('detailDate', orderDate);
                setElementText('detailCustomer', customer || 'Guest');
                setElementText('detailAmount', amount);
                setElementText('detailTime', time);
                setElementText('detailPaymentMethod', paymentMethod || 'N/A');
                setElementText('detailPaymentReference', (paymentReference && paymentReference !== 'N/A' && paymentReference !== 'null') ? paymentReference : 'N/A');

                // Handle discount display
                const discountEl = document.getElementById('detailPaymentDiscount');
                if (discountEl) {
                    if (paymentDiscount > 0) {
                        discountEl.textContent = '-₱' + parseFloat(paymentDiscount).toFixed(2) + (rewardName ? ' (' + rewardName + ')' : '');
                    } else {
                        discountEl.textContent = '₱0.00';
                    }
                }

                const paymentDtEl = document.getElementById('detailPaymentDatetime');
                if (paymentDtEl) {
                    if (paymentDatetime && paymentDatetime !== 'N/A' && paymentDatetime !== 'null') {
                        try {
                            const dt = new Date(paymentDatetime);
                            paymentDtEl.textContent = isNaN(dt.getTime()) ? 'N/A' : dt.toLocaleString();
                        } catch (e) {
                            paymentDtEl.textContent = 'N/A';
                        }
                    } else {
                        paymentDtEl.textContent = 'N/A';
                    }
                }

                // Populate order items
                const orderItemsContainer = document.getElementById('orderItemsContainer');
                if (orderItemsContainer) {
                    orderItemsContainer.innerHTML = '';
                    orderDetails.forEach(item => {
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'order-item';
                        itemDiv.innerHTML = `
                    <div>
                        <p class="item-name">${item.product_name}</p>
                        <p class="item-qty">${item.qty} x ₱${parseFloat(item.price).toFixed(2)}</p>
                    </div>
                    <p class="item-price">₱${(item.qty * item.price).toFixed(2)}</p>
                `;
                        orderItemsContainer.appendChild(itemDiv);
                    });
                }

                const modal = document.getElementById('transactionDetailModal');
                if (modal) modal.style.display = 'block';

            } catch (e) {
                console.error('Error opening transaction detail:', e);
                alert('Error opening transaction details. Please try again.');
            }
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

            <nav class="serif sidebar-nav">
                <a href="cashier.php" class="nav-link ">
                    <span class="nav-icon material-icons">restaurant</span>
                    <span class="nav-text">Menu</span>
                </a>
                <a href="transactions.php" class="nav-link active">
                    <span class="nav-icon material-icons">payment</span>
                    <span class="nav-text">POS Transactions</span>
                </a>

                <a href="inventory.php" class="nav-link">
                    <span class="nav-icon material-icons">kitchen</span>
                    <span class="nav-text">Get Ingredients</span>
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
                    <h1 class="serif page-title">POS Transactions</h1>
                </div>
                <div class="header-right">
                    <div class="admin-profile">
                        <span class="admin-label"><?php echo $adminName; ?></span>
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User" class="profile-img">

                    </div>
                </div>
            </header>

            <!-- Transactions Content -->
            <div class="transactions-content">
                <!-- Controls Section -->
                <div class="transactions-controls">
                    <div class="sort-container">
                        <label for="sort-dropdown" class="sort-label">Sort by:</label>
                        <select id="sort-dropdown" class="sort-dropdown">
                            <option value="latest">Latest</option>
                            <option value="oldest">Oldest</option>
                            <option value="highest">Highest Amount</option>
                            <option value="lowest">Lowest Amount</option>
                        </select>
                    </div>
                    <!--<div class="search-container">
                        <input type="text" class="search-input" placeholder="Search">
                        <span class="search-icon">🔍</span>
                    </div>-->
                </div>

                <!-- Transactions Table -->
                <div class="table-wrapper">
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (count($orders) > 0) {
                                $currentDate = null;
                                foreach ($orders as $order) {
                                    $orderDate = new DateTime($order['order_date']);
                                    $displayDate = $orderDate->format('F d, Y');

                                    // Add date header if date changed
                                    if ($currentDate !== $displayDate) {
                                        $currentDate = $displayDate;
                                        echo '<tr class="date-header-row"><td colspan="5">' . htmlspecialchars($displayDate) . '</td></tr>';
                                    }

                                    $orderTotal = getOrderTotal($conn, $order['order_id']);
                                    $orderDetails = getOrderDetails($conn, $order['order_id']);
                                    $customerName = $order['first_name'] && $order['last_name']
                                        ? htmlspecialchars($order['first_name'] . ' ' . $order['last_name'])
                                        : 'Guest';
                                    $orderTime = $order['order_time'] ? (new DateTime($order['order_time']))->format('g:i A') : 'N/A';
                                    $paymentMethod = htmlspecialchars($order['payment_method'] ?? 'N/A');
                                    $paymentReference = htmlspecialchars($order['payment_reference'] ?? 'N/A');
                                    $paymentDatetime = $order['payment_datetime'] ?? 'N/A';
                                    $customerId = $order['customer_id'] ?? 'N/A';

                                    // Calculate discount
                                    $discountPercent = $order['discount_percent'] ?? 0;
                                    $discountAmount = $order['discount_amount'] ?? 0;
                                    $rewardName = $order['reward_name'] ?? '';

                                    if ($discountPercent > 0) {
                                        $paymentDiscount = $orderTotal * ($discountPercent / 100);
                                    } elseif ($discountAmount > 0) {
                                        $paymentDiscount = $discountAmount;
                                    } else {
                                        $paymentDiscount = 0;
                                    }

                                    $finalTotal = $orderTotal - $paymentDiscount;

                                    echo '<tr data-order-id="' . htmlspecialchars($order['order_id']) . '" 
                                        data-order-date="' . htmlspecialchars($displayDate) . '"
                                        data-order-type=""
                                        data-payment-method="' . $paymentMethod . '"
                                        data-payment-reference="' . $paymentReference . '"
                                        data-payment-datetime="' . htmlspecialchars($paymentDatetime) . '"
                                        data-payment-discount="' . htmlspecialchars($paymentDiscount) . '"
                                        data-reward-name="' . htmlspecialchars($rewardName) . '"
                                        data-customer-id="' . $customerId . '"
                                        data-order-details="' . htmlspecialchars(json_encode($orderDetails)) . '">
                                        <td>#' . htmlspecialchars($order['order_id']) . '</td>
                                        <td>' . $customerName . '</td>
                                        <td>₱' . number_format($finalTotal, 2) . '</td>
                                        <td>' . $orderTime . '</td>
                                        <td><button class="action-btn" title="View Details"><img id="eyeIcon" class="view-detail-btn" src="../../public/icons/eye-open.png" width="20" alt="Show/Hide"></button></td>
                                    </tr>';
                                }
                            } else {
                                echo '<tr><td colspan="5" style="text-align: center; padding: 20px;">No transactions found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Transaction Detail Modal -->
    <div id="transactionDetailModal" class="modal">
        <div class="modal-content transaction-detail-modal">
            <div class="modal-header">
                <div>
                    <h3 id="detailReceiptId">#12456</h3>
                    <p id="detailDate" style="margin: 0; color: #666; font-size: 14px;">April 2, 2025</p>
                </div>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>

            <div class="modal-body transaction-details">
                <!-- Transaction Info -->
                <div class="detail-section" style="display: flex; justify-content: space-between; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e8ddd0;">
                    <div>
                        <p style="margin: 0; font-size: 14px; color: #666;">Customer: <span id="detailCustomer">Customer</span></p>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Time: <span id="detailTime">3:14 PM</span></p>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Payment: <span id="detailPaymentMethod">N/A</span></p>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Reference #: <span id="detailPaymentReference">N/A</span></p>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Payment Date/Time: <span id="detailPaymentDatetime">N/A</span></p>
                    </div>
                    <div style="text-align: right;">
                        <p style="margin: 0; font-size: 12px; color: #666;">Amount</p>
                        <p style="margin: 5px 0 0 0; font-size: 18px; color: #6b4423; font-weight: 600;" id="detailAmount">₱520</p>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="detail-section">
                    <h4>Order Items</h4>
                    <div id="orderItemsContainer" class="order-items">
                        <div class="order-item">
                            <div>
                                <p class="item-name">Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>