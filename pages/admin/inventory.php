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

            // Add new ingredient
            const addBtn = document.querySelector('.add-btn');
            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    openAddInventoryModal();
                });
            }

            // Edit buttons
            const editBtns = document.querySelectorAll('.edit-btn');
            editBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    openEditModal(this);
                });
            });

            // Delete buttons
            const deleteBtns = document.querySelectorAll('.delete-btn');
            deleteBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    deleteItem(this);
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

        function openAddInventoryModal() {
            const modal = document.getElementById('addInventoryModal');
            const form = document.getElementById('inventoryForm');
            form.reset();
            document.getElementById('modalTitle').textContent = 'Add Ingredient';
            document.getElementById('ingredientIdDisplay').style.display = 'none';
            document.getElementById('ingredientIdInput').value = '';
            document.getElementById('ingredientNameInput').value = '';
            document.getElementById('ingredientQtyInput').value = '';
            document.getElementById('ingredientUnitInput').value = 'piece';
            modal.style.display = 'block';
        }

        function openEditModal(button) {
            const modal = document.getElementById('addInventoryModal');
            const row = button.closest('tr');
            const id = row.querySelector('td:nth-child(1)').textContent;
            const name = row.querySelector('td:nth-child(2)').textContent;
            const qty = row.querySelector('td:nth-child(3)').textContent;
            const unit = row.querySelector('td:nth-child(4)').textContent;

            document.getElementById('modalTitle').textContent = 'Update Ingredient';
            document.getElementById('ingredientIdDisplay').style.display = 'block';
            document.getElementById('ingredientIdDisplay').textContent = 'Ingredient ID: ' + id;
            document.getElementById('ingredientIdInput').value = id;
            document.getElementById('ingredientNameInput').value = name;
            document.getElementById('ingredientQtyInput').value = qty;
            document.getElementById('ingredientUnitInput').value = unit;

            modal.style.display = 'block';
        }

        function closeModal() {
            const modal = document.getElementById('addInventoryModal');
            modal.style.display = 'none';
        }

        function saveIngredient() {
            const ingredientId = document.getElementById('ingredientIdInput').value;
            const ingredientName = document.getElementById('ingredientNameInput').value;
            const ingredientQty = document.getElementById('ingredientQtyInput').value;
            const ingredientUnit = document.getElementById('ingredientUnitInput').value;

            if (!ingredientName || !ingredientQty) {
                alert('Please fill in all required fields');
                return;
            }

            // Create form data for AJAX
            const formData = new FormData();
            formData.append('ingredient_id', ingredientId);
            formData.append('ingredient_name', ingredientName);
            formData.append('ingredient_qty', ingredientQty);
            formData.append('ingredient_unit', ingredientUnit);

            // Send to backend
            fetch('../../public/actions/products/save_ingredient.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Ingredient saved successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving ingredient');
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
                    <img src="../../public/assets/css/images/logo images/cups and stories logo.png" alt="Cafe Logo" class="logo-icon">
                </div>
                <button class="close-btn" id="sidebar-close-btn">✕</button>
            </div>

            <nav class="sidebar-nav">
                <a href="admin.php" class="nav-link">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="page_view.php" class="nav-link">
                    <span class="nav-icon">📄</span>
                    <span class="nav-text">Pages Settings</span>
                </a>
                <a href="menu.php" class="nav-link">
                    <span class="nav-icon">🍽️</span>
                    <span class="nav-text">Menu</span>
                </a>
                <a href="transactions.php" class="nav-link">
                    <span class="nav-icon">💳</span>
                    <span class="nav-text">Transactions</span>
                </a>
                <a href="rewards.php" class="nav-link">
                    <span class="nav-icon">🎟️</span>
                    <span class="nav-text">Rewards</span>
                </a>
                <a href="inventory.php" class="nav-link active">
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
                <a href="reports.php" class="nav-link">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Reports</span>
                </a>
                <a href="settings.php" class="nav-link">
                    <span class="nav-icon">⚙️</span>
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
                    <h1 class="page-title">Inventory</h1>
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
                    <button class="add-btn" title="Add new ingredient">➕</button>
                </div>

                <!-- Inventory Table -->
                <div class="table-wrapper">
                    <table class="menu-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>QTY</th>
                                <th>Unit</th>
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
                                        <td><?php echo htmlspecialchars($ingredient['ingredient_unit'] ?? 'N/A'); ?></td>
                                        <td><button class="action-btn edit-btn" title="Edit">✎</button><button class="action-btn delete-btn" title="Delete">🗑️</button></td>
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
                    <input type="text" id="ingredientNameInput" class="form-input" placeholder="e.g., Chicken Wings Fresh" required>
                </div>

                <div class="form-group">
                    <label for="ingredientQtyInput">Quantity (QTY):</label>
                    <input type="number" id="ingredientQtyInput" class="form-input" placeholder="e.g., 614" min="0" value="0" required>
                </div>

                <div class="form-group">
                    <label for="ingredientUnitInput">Unit:</label>
                    <select id="ingredientUnitInput" class="form-input" required>
                        <option value="piece">Piece</option>
                        <option value="KG (Kilogram)">KG (Kilogram)</option>
                        <option value="ML (Milliliter)">ML (Milliliter)</option>
                        <option value="L (Liter)">L (Liter)</option>
                        <option value="g (Grams)">grams</option>
                    </select>
                </div>

                <button type="button" class="save-btn" onclick="saveIngredient()">Save</button>
            </form>
        </div>
    </div>
</body>

</html>
<script>
    const qtyInput = document.getElementById('ingredientQtyInput');

<<<<<<< Updated upstream
qtyInput.addEventListener('keydown', (e) => {
    if (e.key === '-' || e.key === 'e') {
        e.preventDefault(); // block minus and "e" for exponential
    }
});

=======
    qtyInput.addEventListener('keydown', (e) => {
        if (e.key === '-' || e.key === 'e') {
            e.preventDefault(); // block minus and "e" for exponential
        }
    });
>>>>>>> Stashed changes
</script>