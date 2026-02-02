<?php

/**
 * Admin Inventory Management Page
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

// Fetch all rewards from database
$rewards = [];
$query = $conn->prepare("SELECT reward_id, reward_name, reward_type, start_date, expiration_date, points, COALESCE(discount_percent,0) AS discount_percent, discount_amount FROM reward ORDER BY reward_id DESC");
if ($query) {
    $query->execute();
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) {
        $rewards[] = $row;
    }
    $query->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards - Cafe Loyalty Reward</title>
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

            // Reward type change handler
            const rewardTypeInput = document.getElementById('rewardTypeInput');
            if (rewardTypeInput) {
                rewardTypeInput.addEventListener('change', handleRewardTypeChange);
            }

            // Discount type change handler
            const discountTypeInput = document.getElementById('discountTypeInput');
            if (discountTypeInput) {
                discountTypeInput.addEventListener('change', handleDiscountTypeChange);
            }

            // Use event delegation for add/edit/delete to avoid duplicate bindings
            const controls = document.querySelector('.menu-controls');
            const tableWrapper = document.querySelector('.table-wrapper');

            const addBtn = controls && controls.querySelector('.add-btn');
            if (addBtn) addBtn.addEventListener('click', openAddRewardModal);

            if (tableWrapper) {
                tableWrapper.addEventListener('click', function(e) {
                    const target = e.target;
                    if (target.classList.contains('edit-btn')) {
                        openEditModal(target);
                    } else if (target.classList.contains('delete-btn')) {
                        deleteReward(target);
                    }
                });
            }
        });

        function filterTable(searchTerm) {
            const table = document.querySelector('.menu-table');
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
            });
        }

        function sortTable(sortBy) {
            const table = document.querySelector('.menu-table');
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

        function handleRewardTypeChange() {
            const rewardType = document.getElementById('rewardTypeInput').value;
            const startDateGroup = document.getElementById('startDateInput').closest('.form-group');
            const expirationDateGroup = document.getElementById('expirationDateInput').closest('.form-group');
            const pointsGroup = document.getElementById('pointsInput').closest('.form-group');
            const pointsInput = document.getElementById('pointsInput');

            if (rewardType === 'Birthday Voucher') {
                // Hide date fields and points
                startDateGroup.style.display = 'none';
                expirationDateGroup.style.display = 'none';
                pointsGroup.style.display = 'none';
                // Set points to 0
                pointsInput.value = '0';
            } else {
                // Show all fields
                startDateGroup.style.display = 'block';
                expirationDateGroup.style.display = 'block';
                pointsGroup.style.display = 'block';
            }
        }

        function handleDiscountTypeChange() {
            const discountType = document.getElementById('discountTypeInput').value;
            const discountPercentGroup = document.getElementById('discountPercentGroup');
            const discountAmountGroup = document.getElementById('discountAmountGroup');

            if (discountType === 'Percentage') {
                discountPercentGroup.style.display = 'block';
                discountAmountGroup.style.display = 'none';
            } else if (discountType === 'Amount') {
                discountPercentGroup.style.display = 'none';
                discountAmountGroup.style.display = 'block';
            }
        }

        function openAddRewardModal() {
            const modal = document.getElementById('addRewardModal');
            const form = document.getElementById('rewardForm');
            form.reset();
            document.getElementById('modalTitle').textContent = 'Add Reward';
            document.getElementById('rewardIdDisplay').style.display = 'none';
            document.getElementById('rewardIdInput').value = '';
            // ensure discount inputs default to 0
            const dp = document.getElementById('discountPercentInput');
            if (dp) dp.value = '0';
            const da = document.getElementById('discountAmountInput');
            if (da) da.value = '0';
            // set discount type default to Percentage
            const dt = document.getElementById('discountTypeInput');
            if (dt) dt.value = 'Percentage';
            handleDiscountTypeChange();
            modal.style.display = 'block';
            // Reset reward type to show all fields
            handleRewardTypeChange();
        }

        function openEditModal(button) {
            const modal = document.getElementById('addRewardModal');
            const row = button.closest('tr');
            const id = row.querySelector('td[data-col="id"]').textContent;
            const name = row.querySelector('td[data-col="name"]').textContent;
            const type = row.querySelector('td[data-col="type"]').textContent;
            const start = row.querySelector('td[data-col="start"]').textContent;
            const end = row.querySelector('td[data-col="end"]').textContent;
            const points = row.querySelector('td[data-col="points"]').textContent;
            const discountText = row.querySelector('td[data-col="discount"]').textContent || '0';

            document.getElementById('modalTitle').textContent = 'Update Reward';
            document.getElementById('rewardIdDisplay').style.display = 'block';
            document.getElementById('rewardIdDisplay').textContent = 'Reward ID: ' + id;
            document.getElementById('rewardIdInput').value = id;
            document.getElementById('rewardNameInput').value = name;
            document.getElementById('rewardTypeInput').value = type;
            document.getElementById('startDateInput').value = (start === '--') ? '' : start;
            document.getElementById('expirationDateInput').value = (end === '--') ? '' : end;
            document.getElementById('pointsInput').value = (points === '--') ? '' : points;
            // set discount (strip trailing % if present)
            const dp = discountText.toString().replace('%', '').trim();
            document.getElementById('discountPercentInput').value = dp;
            document.getElementById('discountAmountInput').value = dp;
            // set discount type to default Percentage (could be enhanced to fetch from DB later)
            document.getElementById('discountTypeInput').value = 'Percentage';
            handleDiscountTypeChange();

            modal.style.display = 'block';
        }

        function closeModal() {
            const modal = document.getElementById('addRewardModal');
            if (modal) modal.style.display = 'none';
        }

        function saveReward() {
            const rewardId = document.getElementById('rewardIdInput').value;
            const rewardName = document.getElementById('rewardNameInput').value;
            const rewardType = document.getElementById('rewardTypeInput').value;
            const startDate = document.getElementById('startDateInput').value;
            const expirationDate = document.getElementById('expirationDateInput').value;
            const points = document.getElementById('pointsInput').value;
            const discountType = document.getElementById('discountTypeInput').value;

            if (!rewardName) {
                alert('Please fill in the reward name');
                return;
            }

            // Get the discount value based on the selected type
            let discountPercentValue = '0';
            let discountAmountValue = '0';
            if (discountType === 'Percentage') {
                discountPercentValue = document.getElementById('discountPercentInput').value || '0';
                discountAmountValue = '0';
            } else if (discountType === 'Amount') {
                discountAmountValue = document.getElementById('discountAmountInput').value || '0';
                discountPercentValue = '0';
            }

            const formData = new FormData();
            formData.append('reward_id', rewardId);
            formData.append('reward_name', rewardName);
            formData.append('reward_type', rewardType);
            formData.append('start_date', startDate);
            formData.append('expiration_date', expirationDate);
            formData.append('points', points);
            formData.append('discount_percent', discountPercentValue);
            formData.append('discount_amount', discountAmountValue);
            formData.append('discount_type', discountType);

            fetch('../../public/actions/rewards/save_reward.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reward saved successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving reward');
                });
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('addRewardModal');
            if (event.target === modal) {
                closeModal();
            }
        });

        function deleteReward(button) {
            if (!confirm('Are you sure you want to delete this reward?')) return;
            const row = button.closest('tr');
            const rewardId = row.querySelector('td[data-col="id"]').textContent;

            const formData = new FormData();
            formData.append('reward_id', rewardId);

            fetch('../../public/actions/rewards/delete_reward.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reward deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting reward');
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

            <nav class="serif sidebar-nav">
                <a href="admin.php" class="nav-link">
                    <span class="nav-icon material-icons">dashboard</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="page_view.php" class="nav-link">
                    <span class="nav-icon material-icons">description</span>
                    <span class="nav-text">Pages Settings</span>
                </a>
                <a href="menu.php" class="nav-link">
                    <span class="nav-icon material-icons">restaurant</span>
                    <span class="nav-text">Menu</span>
                </a>
                <a href="transactions.php" class="nav-link">
                    <span class="nav-icon material-icons">payment</span>
                    <span class="nav-text">Transactions</span>
                </a>
                <a href="rewards.php" class="nav-link active">
                    <span class="nav-icon material-icons">confirmation_number</span>
                    <span class="nav-text">Rewards</span>
                </a>
                <a href="inventory.php" class="nav-link ">
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
                    <h1 class="serif page-title">Rewards</h1>
                </div>
                <div class="header-right">
                    <div class="admin-profile">
                        <span class="admin-label"><?php echo $adminName; ?></span>
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User" class="profile-img">

                    </div>
                </div>
            </header>

            <!-- Inventory Content -->
            <div class="menu-content">
                <!-- Controls Section -->
                <div class="menu-controls">
                    <div class="left-controls">
                        <div class="sort-container">
                            <label for="sort-dropdown" class="sort-label">Latest</label>
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
                    <button class="add-btn" title="Add new reward"><span class="material-icons">add</span></button>
                </div>

                <!-- Rewards Table -->
                <div class="table-wrapper">
                    <table class="menu-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Start Date</th>
                                <th>Expiration</th>
                                <th>Points</th>
                                <th>Discount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rewards) > 0): ?>
                                <?php foreach ($rewards as $reward): ?>
                                    <tr>
                                        <td data-col="id"><?php echo htmlspecialchars($reward['reward_id']); ?></td>
                                        <td data-col="name"><?php echo htmlspecialchars($reward['reward_name'] ?? ''); ?></td>
                                        <td data-col="type"><?php echo htmlspecialchars($reward['reward_type'] ?? ''); ?></td>
                                        <td data-col="start"><?php echo htmlspecialchars($reward['start_date'] ?? '--'); ?></td>
                                        <td data-col="end"><?php echo htmlspecialchars($reward['expiration_date'] ?? '--'); ?></td>
                                        <td data-col="points"><?php echo htmlspecialchars($reward['points'] ?? '--'); ?></td>
                                        <td data-col="discount">
                                            <?php
                                            if (!empty($reward['discount_amount'])) {
                                                echo '₱' . htmlspecialchars($reward['discount_amount']);
                                            } else {
                                                echo htmlspecialchars($reward['discount_percent'] ?? '0') . '%';
                                            }
                                            ?>
                                        </td>
                                        <td><button class="action-btn edit-btn" title="Edit">✎</button><button class="action-btn delete-btn" title="Delete">🗑️</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px;">No rewards found. <a href="#" onclick="openAddRewardModal(); return false;">Add one now</a></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="addRewardModal" class="modal">
        <div class="modal-content" style="max-height:80vh; overflow-y:auto;">
            <div class="modal-header">
                <h2 class="serif" id="modalTitle">Add Reward</h2>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <form id="rewardForm" class="modal-body">
                <div class="form-group">
                    <p id="rewardIdDisplay" style="display:none;"></p>
                    <input type="hidden" id="rewardIdInput" value="">
                </div>

                <div class="form-group">
                    <label for="rewardNameInput">Reward Name:</label>
                    <input type="text" id="rewardNameInput" class="form-input san-serif" placeholder="e.g., Free Coffee" required>
                </div>

                <div class="form-group">
                    <label for="rewardTypeInput">Reward Type:</label>
                    <select id="rewardTypeInput" class="serif form-select san-serif">
                        <option value="">-- Select Type --</option>
                        <option value="Discount Voucher">Discount Voucher</option>
                        <option value="Birthday Voucher">Birthday Voucher</option>
                        <option value="Event Voucher">Event Voucher</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="startDateInput">Start Date:</label>
                    <input type="date" id="startDateInput" class="serif form-input san-serif">
                </div>

                <div class="form-group">
                    <label for="expirationDateInput">Expiration Date:</label>
                    <input type="date" id="expirationDateInput" class="serif form-input san-serif">
                </div>

                <div class="form-group">
                    <label for="pointsInput">Points Required / Awarded:</label>
                    <input type="number" id="pointsInput" class="serif form-input san-serif" placeholder="e.g., 100" min="0">
                </div>

                <div class="form-group">
                    <label for="discountTypeInput">Discount Type:</label>
                    <select id="discountTypeInput" class="serif form-select san-serif">
                        <option value="Percentage">Percentage</option>
                        <option value="Amount">Amount</option>
                    </select>
                </div>

                <div class="form-group" id="discountPercentGroup">
                    <label for="discountPercentInput">Discount Value (Percentage):</label>
                    <input type="number" id="discountPercentInput" class="serif form-input san-serif" placeholder="e.g., 10" min="0">
                </div>

                <div class="form-group" id="discountAmountGroup" style="display: none;">
                    <label for="discountAmountInput">Discount Value (Amount):</label>
                    <input type="number" id="discountAmountInput" class="serif form-input san-serif" placeholder="e.g., 50" min="0" step="0.01">
                </div>

                <button type="button" class="serif save-btn" onclick="saveReward()">Save</button>
            </form>
        </div>
    </div>
</body>

</html>