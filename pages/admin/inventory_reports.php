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
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php?error=Unauthorized+access');
    exit;
}

// Include database configuration
require_once '../../public/actions/auth/db_config.php';

// Get admin name from session
$adminName = htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);

// Fetch recent ingredient transactions to display in the table
$ingredientTransactions = [];
$query = $conn->prepare("SELECT it.transaction_id, it.ingredient_id, IFNULL(i.ingredient_name, '') AS ingredient_name,
    IFNULL(it.ingredient_unit, '') AS ingredient_unit, it.quantity, it.transaction_date, 
    it.cashier_id, IFNULL(c.first_name, '') AS cashier_first, IFNULL(c.last_name, '') AS cashier_last
    FROM ingredienttransaction it
    LEFT JOIN ingredient i ON it.ingredient_id = i.ingredient_id
    LEFT JOIN cashier c ON it.cashier_id = c.cashier_id
    ORDER BY it.transaction_date DESC
    LIMIT 500");
if ($query) {
    $query->execute();
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) {
        $ingredientTransactions[] = $row;
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

// Fetch recent ingredient transactions for inventory reports
$ingredientTransactions = [];
$itQuery = $conn->prepare("SELECT it.transaction_id, it.ingredient_id, IFNULL(i.ingredient_name, '') AS ingredient_name,
    it.cashier_id, IFNULL(c.first_name, '') AS cashier_first, IFNULL(c.last_name, '') AS cashier_last, IFNULL(c.store_id, 0) AS cashier_store_id,
    IFNULL(s.location, '') AS store_location,
    IFNULL(it.ingredient_unit, '') AS ingredient_unit, it.quantity, it.transaction_date
    FROM ingredienttransaction it
    LEFT JOIN ingredient i ON it.ingredient_id = i.ingredient_id
    LEFT JOIN cashier c ON it.cashier_id = c.cashier_id
    LEFT JOIN store s ON c.store_id = s.store_id
    ORDER BY it.transaction_date DESC
    LIMIT 200");
if ($itQuery) {
    $itQuery->execute();
    $itRes = $itQuery->get_result();
    while ($r = $itRes->fetch_assoc()) {
        $ingredientTransactions[] = $r;
    }
    $itQuery->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Cafe Loyalty Reward</title>
    <link rel="stylesheet" href="../../public/assets/css/admin-styles.css">    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">    <script>
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

            const orderId = row.getAttribute('data-order-id');
            const receiptId = '#' + orderId;
            const customer = row.querySelector('td:nth-child(2)').textContent;
            const amount = row.querySelector('td:nth-child(3)').textContent;
            const time = row.querySelector('td:nth-child(4)').textContent;
            const orderDate = row.getAttribute('data-order-date');
            const orderType = row.getAttribute('data-order-type');
            const paymentMethod = row.getAttribute('data-payment-method');
            const customerId = row.getAttribute('data-customer-id');
            const orderDetails = JSON.parse(row.getAttribute('data-order-details'));

            // Populate modal with transaction details
            document.getElementById('detailReceiptId').textContent = receiptId;
            document.getElementById('detailDate').textContent = orderDate;
            document.getElementById('detailCustomer').textContent = customer || 'Guest';
            document.getElementById('detailAmount').textContent = amount;
            document.getElementById('detailTime').textContent = time;
            document.getElementById('detailOrderType').textContent = orderType || 'N/A';
            document.getElementById('detailPaymentMethod').textContent = paymentMethod || 'N/A';

            // Populate order items
            const orderItemsContainer = document.getElementById('orderItemsContainer');
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

            const modal = document.getElementById('transactionDetailModal');
            if (modal) {
                modal.style.display = 'block';
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

                <a href="inventory_reports.php" class="nav-link active">
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
                 <a href="settings.php" class="nav-link">
                    <span class="nav-icon material-icons">settings</span>
                    <span class="nav-text">My Account</span>
                </a>
                 <a href="page_view.php" class="nav-link">
                    <span class="nav-icon material-icons">description</span>
                    <span class="nav-text">Pages Settings</span>
                </a>
               
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="hamburger-btn" id="hamburger-menu-btn">☰</button>
                    <h1 class="serif page-title">Inventory Transactions</h1>
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
                        <label for="sort-dropdown" class="sort-label">Latest</label>
                        <select id="sort-dropdown" class="sort-dropdown">
                            <option value="latest">Latest</option>
                            <option value="oldest">Oldest</option>
                            <option value="highest">Highest Amount</option>
                            <option value="lowest">Lowest Amount</option>
                        </select>
                    </div>
                    <!-- <div class="search-container">
                        <input type="text" class="search-input" placeholder="Search">
                        <span class="search-icon">🔍</span>
                    </div> -->
                </div>

                <!-- Transactions Table (Inventory Transactions) -->
                <div class="table-wrapper">
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ingredient</th>
                                <th>Unit</th>
                                <th>Quantity</th>
                                <th>Cashier</th>
                                <th>Date</th>
                                <th>Branch</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (count($ingredientTransactions) > 0) {
                                foreach ($ingredientTransactions as $tx) {
                                    $cashierName = trim(($tx['cashier_first'] . ' ' . $tx['cashier_last']));
                                    echo '<tr>' .
                                        '<td>' . htmlspecialchars($tx['transaction_id']) . '</td>' .
                                        '<td>' . htmlspecialchars($tx['ingredient_name'] ?: ('#' . $tx['ingredient_id'])) . '</td>' .
                                        '<td>' . htmlspecialchars($tx['ingredient_unit'] ?: '') . '</td>' .
                                        '<td>' . htmlspecialchars($tx['quantity']) . '</td>' .
                                        '<td>' . htmlspecialchars($cashierName ?: 'System') . '</td>' .
                                        '<td>' . htmlspecialchars($tx['transaction_date']) . '</td>' .
                                        '<td>' . htmlspecialchars($tx['store_location'] ?: ($tx['notes'] ?? '')) . '</td>' .
                                        '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="7" style="text-align: center; padding: 20px;">No inventory transactions found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Inventory Transactions Content -->

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
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Type: <span id="detailOrderType">N/A</span></p>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Payment: <span id="detailPaymentMethod">N/A</span></p>
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