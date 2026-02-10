<?php

/**
 * Admin Cashiers List Page
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

// Fetch all cashiers from database
$cashiers = [];
$query = $conn->prepare("SELECT c.cashier_id, c.first_name, c.last_name, IFNULL(c.store_id, 0) AS store_id, u.email FROM cashier c JOIN user u ON c.user_id = u.user_id ORDER BY c.cashier_id DESC");
if ($query) {
    $query->execute();
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) {
        $cashiers[] = $row;
    }
    $query->close();
}
// Fetch stores for dropdown
$stores = [];
$squery = $conn->prepare("SELECT store_id, location FROM store ORDER BY store_id ASC");
if ($squery) {
    $squery->execute();
    $sres = $squery->get_result();
    while ($s = $sres->fetch_assoc()) {
        $stores[] = $s;
    }
    $squery->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashiers List - Cups & Stories Cafe</title>
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

            // View cashier details - using event delegation
            const tableBody = document.querySelector('.members-table tbody');
            if (tableBody) {
                tableBody.addEventListener('click', function(e) {
                    if (e.target.classList.contains('view-btn')) {
                        openCashierDetail(e.target);
                    } else if (e.target.classList.contains('edit-btn')) {
                        openEditCashierModal(e.target);
                    } else if (e.target.classList.contains('delete-btn')) {
                        deleteCashier(e.target);
                    }
                });
            }

            // Add cashier button
            const addBtn = document.querySelector('.add-btn');
            if (addBtn) {
                addBtn.addEventListener('click', openAddCashierModal);
            }

            // Close modals on outside click
            const formModal = document.getElementById('cashierFormModal');
            if (formModal) {
                formModal.addEventListener('click', function(e) {
                    if (e.target === formModal) {
                        closeCashierFormModal();
                    }
                });
            }
        });

        function filterTable(searchTerm) {
            const table = document.querySelector('.members-table');
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
            });
        }

        function sortTable(sortBy) {
            const table = document.querySelector('.members-table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                let aVal, bVal;

                if (sortBy === 'latest') {
                    aVal = a.querySelector('td:nth-child(1)').textContent;
                    bVal = b.querySelector('td:nth-child(1)').textContent;
                    return parseInt(bVal) - parseInt(aVal);
                } else if (sortBy === 'oldest') {
                    aVal = a.querySelector('td:nth-child(1)').textContent;
                    bVal = b.querySelector('td:nth-child(1)').textContent;
                    return parseInt(aVal) - parseInt(bVal);
                } else if (sortBy === 'name-asc') {
                    aVal = a.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    bVal = b.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    return aVal.localeCompare(bVal);
                } else if (sortBy === 'name-desc') {
                    aVal = a.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    bVal = b.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    return bVal.localeCompare(aVal);
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        function openCashierDetail(button) {
            const row = button.closest('tr');
            const cashierId = row.querySelector('td:nth-child(1)').textContent;
            const cashierName = row.querySelector('td:nth-child(2)').textContent;
            const email = row.querySelector('td:nth-child(3)').textContent;

            // Populate modal with cashier details
            document.getElementById('cashierName').textContent = cashierName;
            document.getElementById('cashierId').textContent = 'ID: ' + cashierId;
            document.getElementById('cashierEmail').textContent = 'Email: ' + email;

            const modal = document.getElementById('cashierDetailModal');
            if (modal) {
                modal.style.display = 'block';
            }
        }

        function openAddCashierModal() {
            document.getElementById('modalTitle').textContent = 'Add Cashier';
            document.getElementById('cashierIdDisplay').style.display = 'none';
            document.getElementById('cashierIdInput').value = '';
            document.getElementById('firstNameInput').value = '';
            document.getElementById('lastNameInput').value = '';
            document.getElementById('emailInput').value = '';
            document.getElementById('passwordInput').value = '';
            document.getElementById('passwordInput').style.display = 'block';
            document.querySelector('label[for="passwordInput"]').style.display = 'block';
            // reset store select
            const storeSelect = document.getElementById('storeSelect');
            if (storeSelect) storeSelect.value = '';

            const modal = document.getElementById('cashierFormModal');
            if (modal) {
                modal.style.display = 'block';
            }
        }

        function openEditCashierModal(button) {
            const row = button.closest('tr');
            const cashierId = row.querySelector('td:nth-child(1)').textContent;
            const cashierName = row.querySelector('td:nth-child(2)').textContent;
            const email = row.querySelector('td:nth-child(3)').textContent;

            const nameParts = cashierName.split(' ');
            const firstName = nameParts[0];
            const lastName = nameParts.slice(1).join(' ');

            document.getElementById('modalTitle').textContent = 'Edit Cashier';
            document.getElementById('cashierIdDisplay').style.display = 'block';
            document.getElementById('cashierIdDisplay').textContent = 'Cashier ID: ' + cashierId;
            document.getElementById('cashierIdInput').value = cashierId;
            document.getElementById('firstNameInput').value = firstName;
            document.getElementById('lastNameInput').value = lastName;
            document.getElementById('emailInput').value = email;
            document.getElementById('passwordInput').value = '';
            document.getElementById('passwordInput').style.display = 'none';
            document.querySelector('label[for="passwordInput"]').style.display = 'none';
            // try to set store if available in row
            const storeCell = row.querySelector('td[data-store-id]');
            const storeSelect = document.getElementById('storeSelect');
            if (storeSelect) {
                if (storeCell) storeSelect.value = storeCell.getAttribute('data-store-id') || '';
                else storeSelect.value = '';
            }

            const modal = document.getElementById('cashierFormModal');
            if (modal) {
                modal.style.display = 'block';
            }
        }

        function deleteCashier(button) {
            const row = button.closest('tr');
            const cashierId = row.querySelector('td:nth-child(1)').textContent;

            if (!confirm('Are you sure you want to delete this cashier?')) {
                return;
            }

            const formData = new FormData();
            formData.append('cashier_id', cashierId);

            fetch('../../public/actions/cashier/delete_cashier.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cashier deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting cashier');
                });
        }

        function saveCashier() {
            const cashierId = document.getElementById('cashierIdInput').value;
            const firstName = document.getElementById('firstNameInput').value;
            const lastName = document.getElementById('lastNameInput').value;
            const email = document.getElementById('emailInput').value;
            const password = document.getElementById('passwordInput').value;
            const storeId = document.getElementById('storeSelect') ? document.getElementById('storeSelect').value : '';

            if (!firstName || !lastName || !email) {
                alert('Please fill in all required fields');
                return;
            }

            if (!cashierId && !password) {
                alert('Password is required for new cashiers');
                return;
            }

            const formData = new FormData();
            formData.append('cashier_id', cashierId);
            formData.append('first_name', firstName);
            formData.append('last_name', lastName);
            formData.append('email', email);
            formData.append('store_id', storeId);
            if (password) {
                formData.append('password', password);
            }

            fetch('../../public/actions/cashier/save_cashier.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cashier saved successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving cashier');
                });
        }

        function closeCashierModal() {
            const modal = document.getElementById('cashierDetailModal');
            modal.style.display = 'none';
        }

        function closeCashierFormModal() {
            const modal = document.getElementById('cashierFormModal');
            modal.style.display = 'none';
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('cashierDetailModal');
            if (event.target === modal) {
                closeCashierModal();
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

            <nav class="serif sidebar-nav">
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
                    <span class="nav-text">Inventory Usage</span>
                </a>

                <a href="members_list.php" class="nav-link">
                    <span class="nav-icon material-icons">people</span>
                    <span class="nav-text">Members</span>
                </a>

                <a href="cashiers_list.php" class="nav-link active">
                    <span class="nav-icon material-icons">people</span>
                    <span class="nav-text">Cashiers</span>
                </a>

                <a href="reports.php" class="nav-link">
                    <span class="nav-icon material-icons">assessment</span>
                    <span class="nav-text">Reports</span>
                </a>

                <a href="page_view.php" class="nav-link">
                    <span class="nav-icon material-icons">description</span>
                    <span class="nav-text">Page Settings</span>
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
                    <h1 class="serif page-title">Cashiers List</h1>
                </div>
                <div class="header-right">
                    <div class="admin-profile">
                        <span class="admin-label"><?php echo $adminName; ?></span>
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User" class="profile-img">

                    </div>

                </div>
            </header>

            <!-- Cashiers Content -->
            <div class="members-content">
                <!-- Controls Section -->
                <div class="members-controls">
                    <div class="left-controls">
                        <div class="sort-container">
                            <label for="sort-dropdown" class="sort-label">Sort by:</label>
                            <select id="sort-dropdown" class="sort-dropdown">
                                <option value="latest">Latest</option>
                                <option value="oldest">Oldest</option>
                                <option value="name-asc">Name (A-Z)</option>
                                <option value="name-desc">Name (Z-A)</option>
                            </select>
                        </div>
                        <div class="search-container">
                            <input type="text" class="search-input" placeholder="Search">
                            <span class="search-icon">🔍</span>
                        </div>

                    </div>
                    <button class="serif add-btn" title="Add new menu item"><span class="material-icons">add</span> Add Cashier</button>
                    <!-- <button class="add-btn" title="Add new cashier">➕</button> -->
                </div>

                <!-- Cashiers Table -->
                <div class="table-wrapper">
                    <table class="members-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($cashiers) > 0): ?>
                                <?php foreach ($cashiers as $cashier): ?>
                                    <tr>
                                        <td><?php echo str_pad($cashier['cashier_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($cashier['first_name'] . ' ' . $cashier['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($cashier['email']); ?></td>
                                        <td data-store-id="<?php echo isset($cashier['store_id']) ? (int)$cashier['store_id'] : ''; ?>">

                                            <button class="action-btn edit-btn" title="Edit">✎</button>
                                            <button class="action-btn delete-btn" title="Delete">🗑️</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 20px;">No cashiers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Cashier Detail Modal -->
    <div id="cashierDetailModal" class="modal">
        <div class="modal-content member-detail-modal">
            <div class="modal-header" style="flex-direction: column; align-items: flex-end; border-bottom: none; padding-bottom: 0;">
                <button class="modal-close" onclick="closeCashierModal()">✕</button>
            </div>

            <div class="modal-body member-details">
                <!-- Cashier Avatar and Name -->
                <div style="text-align: center; margin-bottom: 25px;">
                    <div style="width: 120px; height: 120px; background-color: #999; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                        <div style="width: 80px; height: 80px; background-color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #999;">👤</div>
                    </div>
                    <h2 id="cashierName" style="margin: 0 0 15px 0; color: #333; font-size: 24px;">Cashier Name</h2>
                    <div style="border-bottom: 2px solid #333; margin: 0 auto; width: 80%; height: 0;"></div>
                </div>

                <!-- Cashier Information -->
                <div class="member-info-section">
                    <p id="cashierId" style="margin: 12px 0; font-size: 15px; color: #333;">ID: 000001</p>
                    <p id="cashierEmail" style="margin: 12px 0; font-size: 15px; color: #333;">Email: cashier@email.com</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Cashier Form Modal -->
    <div id="cashierFormModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Cashier</h2>
                <button class="modal-close" onclick="closeCashierFormModal()">✕</button>
            </div>
            <form id="cashierForm" class="modal-body">
                <div class="form-group">
                    <p id="cashierIdDisplay" style="display:none;"></p>
                    <input type="hidden" id="cashierIdInput" value="">
                </div>

                <div class="form-group">
                    <label for="firstNameInput">First Name:</label>
                    <input type="text" id="firstNameInput" class="form-input san-serif" placeholder="First Name" required>
                </div>

                <div class="form-group">
                    <label for="lastNameInput">Last Name:</label>
                    <input type="text" id="lastNameInput" class="form-input san-serif" placeholder="Last Name" required>
                </div>

                <div class="form-group">
                    <label for="emailInput">Email:</label>
                    <input type="email" id="emailInput" class="form-input san-serif" placeholder="Email" required>
                </div>

                <div class="form-group">
                    <label for="passwordInput">Password:</label>
                    <input type="password" id="passwordInput" class="form-input san-serif" placeholder="Password">
                </div>

                <div class="form-group">
                    <label for="storeSelect">Store / Branch:</label>
                    <select id="storeSelect" class="form-input san-serif">
                        <option value="">-- Select Store --</option>
                        <?php foreach ($stores as $st): ?>
                            <option value="<?php echo (int)$st['store_id']; ?>"><?php echo htmlspecialchars($st['location']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="button" class="save-btn" onclick="saveCashier()">Save</button>
            </form>
        </div>
    </div>
</body>

</html>