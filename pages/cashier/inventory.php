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
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../../index.php?error=Unauthorized+access');
    exit;
}

// Include database configuration
require_once '../../public/actions/auth/db_config.php';

// Get admin name from session
$adminName = htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);

// Fetch all ingredients from database
$ingredients = [];
$query = $conn->prepare("SELECT ingredient_id, ingredient_name, ingredient_qty, ingredient_unit FROM ingredient ORDER BY ingredient_id DESC");
if ($query) {
    $query->execute();
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) {
        $ingredients[] = $row;
    }
    $query->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Cafe Loyalty Reward</title>
    <link rel="stylesheet" href="../../public/assets/css/admin-styles.css">
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

            // Get ingredient buttons (cashier will take quantity)
            const getBtns = document.querySelectorAll('.get-btn');
            getBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    openGetModal(this);
                });
            });
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

        function openGetModal(button) {
            const modal = document.getElementById('addInventoryModal');
            const row = button.closest('tr');
            const id = row.querySelector('td:nth-child(1)').textContent;
            const name = row.querySelector('td:nth-child(2)').textContent;
            const qty = row.querySelector('td:nth-child(3)').textContent;

            document.getElementById('modalTitle').textContent = 'Get Ingredient';
            document.getElementById('ingredientIdDisplay').style.display = 'block';
            document.getElementById('ingredientIdDisplay').textContent = 'Ingredient ID: ' + id;
            document.getElementById('ingredientIdInput').value = id;
            // name read-only
            document.getElementById('ingredientNameInput').value = name;
            document.getElementById('ingredientNameInput').readOnly = true;
            // show current qty
            document.getElementById('ingredientCurrentQty').value = qty;
            document.getElementById('takeQtyInput').value = '';
            modal.style.display = 'block';
        }

        function closeModal() {
            const modal = document.getElementById('addInventoryModal');
            modal.style.display = 'none';
        }

        function takeIngredient() {
            const ingredientId = document.getElementById('ingredientIdInput').value;
            const takeQty = parseFloat(document.getElementById('takeQtyInput').value || '0');
            const currentQty = parseFloat(document.getElementById('ingredientCurrentQty').value || '0');

            if (!ingredientId || isNaN(takeQty) || takeQty <= 0) {
                alert('Please enter a valid quantity to take');
                return;
            }

            if (takeQty > currentQty) {
                if (!confirm('The requested quantity exceeds current stock. Proceed and allow negative stock?')) return;
            }

            const formData = new FormData();
            formData.append('ingredient_id', ingredientId);
            formData.append('take_qty', takeQty);

            fetch('../../public/actions/products/get_ingredient.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert('Ingredient quantity updated');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (json.message || 'Failed to update'));
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Error updating ingredient');
                });
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('addInventoryModal');
            if (event.target === modal) {
                closeModal();
            }
        });

        function deleteItem(button) {
            if (confirm('Are you sure you want to delete this ingredient?')) {
                const row = button.closest('tr');
                const ingredientId = row.querySelector('td:nth-child(1)').textContent;

                // Send delete request to backend
                const formData = new FormData();
                formData.append('ingredient_id', ingredientId);

                fetch('../../public/actions/products/delete_ingredient.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Ingredient deleted successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error deleting ingredient');
                    });
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
                <a href="transactions.php" class="nav-link ">
                    <span class="nav-icon material-icons">payment</span>
                    <span class="nav-text">Transactions</span>
                </a>

                <a href="inventory.php" class="nav-link active">
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
                    <h1 class="serif page-title">Inventory</h1>
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
                        <!-- <div class="search-container">
                            <input type="text" class="search-input" placeholder="Search">
                            <span class="search-icon">🔍</span>
                        </div> -->
                    </div>
                    <!-- Add button removed for cashier role; cashiers should only get ingredients -->
                </div>

                <!-- Inventory Table -->
                <div class="table-wrapper">
                    <table class="menu-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>QTY</th>

                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($ingredients) > 0): ?>
                                <?php foreach ($ingredients as $ingredient): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ingredient['ingredient_id']); ?></td>
                                        <td><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($ingredient['ingredient_qty'] ?? '0'); ?></td>

                                        <td><button class="action-btn get-btn" title="Get">Get</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px;">No ingredients found. <a href="#" onclick="openAddInventoryModal(); return false;">Add one now</a></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="addInventoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Ingredient</h2>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <form id="inventoryForm" class="modal-body">
                <div class="form-group">
                    <p id="ingredientIdDisplay" style="display:none;"></p>
                    <input type="hidden" id="ingredientIdInput" value="">
                </div>

                <div class="form-group">
                    <label for="ingredientNameInput">Ingredient Name:</label>
                    <input type="text" id="ingredientNameInput" class="form-input" placeholder="e.g., Chicken Wings Fresh" readonly>
                </div>

                <div class="form-group">
                    <label for="ingredientCurrentQty">Current Quantity:</label>
                    <input type="number" id="ingredientCurrentQty" class="form-input" readonly>
                </div>

                <div class="form-group">
                    <label for="takeQtyInput">Quantity to Take:</label>
                    <input type="number" id="takeQtyInput" class="form-input" placeholder="e.g., 5" min="0" step="any">
                </div>

                <button type="button" class="save-btn" onclick="takeIngredient()">Take</button>
            </form>
        </div>
    </div>
</body>

</html>