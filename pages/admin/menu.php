<?php
/**
 * Admin Menu Management Page
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

// Fetch all products from database
$products = [];
$query = $conn->prepare("SELECT product_id, product_name, product_price, product_size, product_temperature, product_points, image_path, product_category FROM product ORDER BY product_id DESC");
if ($query) {
    $query->execute();
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $query->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - Cafe Loyalty Reward</title>
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

            // Add new menu item
            const addBtn = document.querySelector('.add-btn');
            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    openAddMenuModal();
                });
            }

            // Category select visibility rules
            const categorySelect = document.getElementById('categorySelect');
            if (categorySelect) {
                categorySelect.addEventListener('change', function(e) {
                    updateFieldsVisibility(e.target.value);
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
                    deleteMenuItem(this);
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

        function openAddMenuModal() {
            const modal = document.getElementById('addMenuModal');
            const form = document.getElementById('menuForm');
            form.reset();
            document.getElementById('modalTitle').textContent = 'Add Menu';
            document.getElementById('menuIdDisplay').style.display = 'none';
            document.getElementById('menuIdInput').value = '';
            document.getElementById('menuNameInput').value = '';
            document.getElementById('priceInput').value = '';
            document.getElementById('categorySelect').value = '';
            document.getElementById('temperatureInput').value = 'None';
            document.getElementById('sizeInput').value = 'None';
            document.getElementById('pointsInput').value = '1';
            // Ensure visibility matches default/selected category
            const sel = document.getElementById('categorySelect');
            updateFieldsVisibility(sel ? sel.value : '');
            modal.style.display = 'block';
        }

        function openEditModal(button) {
            const modal = document.getElementById('addMenuModal');
            const row = button.closest('tr');
            const id = row.querySelector('td:nth-child(1)').textContent;
            const name = row.querySelector('td:nth-child(2)').textContent;
            const price = row.querySelector('td:nth-child(5)').textContent.replace('₱', '').trim();
            let temperature = row.querySelector('td:nth-child(6)').textContent;
            let size = row.querySelector('td:nth-child(7)').textContent;
            const points = row.querySelector('td:nth-child(8)').textContent;

            // Convert "N/A" back to "None" for dropdowns
            if (temperature === 'N/A' || !temperature.trim()) temperature = 'None';
            if (size === 'N/A' || !size.trim()) size = 'None';

            document.getElementById('modalTitle').textContent = 'Update Menu';
            document.getElementById('menuIdDisplay').style.display = 'block';
            document.getElementById('menuIdDisplay').textContent = 'Menu ID: ' + id;
            document.getElementById('menuIdInput').value = id;
            document.getElementById('menuNameInput').value = name;
            document.getElementById('priceInput').value = price;
            // Prefill category from the table row
            const catCell = row.querySelector('.product-category');
            const currentCategory = catCell ? catCell.textContent.trim() : '';
            document.getElementById('categorySelect').value = currentCategory;
            // Apply visibility rules after prefilling category
            updateFieldsVisibility(currentCategory);
            // Only set temperature value if the temperature group is visible; otherwise keep default set by updateFieldsVisibility
            const tempGroup = document.getElementById('temperatureGroup');
            if (tempGroup && tempGroup.style.display !== 'none') {
                document.getElementById('temperatureInput').value = temperature === 'N/A' || !temperature.trim() ? 'None' : temperature;
            }
            document.getElementById('sizeInput').value = (size === 'N/A' || !size.trim()) ? 'None' : size;
            document.getElementById('pointsInput').value = points;
            document.getElementById('imagePathInput').value = '';
            
            modal.style.display = 'block';
        }

        function closeModal() {
            const modal = document.getElementById('addMenuModal');
            modal.style.display = 'none';
        }

        function saveMenu() {
            const productId = document.getElementById('menuIdInput').value;
            const productName = document.getElementById('menuNameInput').value;
            const productPrice = document.getElementById('priceInput').value;
            const productCategory = document.getElementById('categorySelect').value;
            const productTemperature = document.getElementById('temperatureInput').value;
            const productSize = document.getElementById('sizeInput').value;
            const productPoints = document.getElementById('pointsInput').value;
            const imageFile = document.getElementById('imagePathInput').files[0];

            if (!productName || !productPrice || !productCategory) {
                alert('Please fill in all required fields (Name, Price, Category)');
                return;
            }

            // Create form data for AJAX
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('product_name', productName);
            formData.append('product_price', productPrice);
            formData.append('product_category', productCategory);
            formData.append('product_temperature', productTemperature);
            formData.append('product_size', productSize);
            formData.append('product_points', productPoints);
            
            // Append image file if selected
            if (imageFile) {
                formData.append('image', imageFile);
            }

            // Send to backend
            fetch('../../public/actions/products/save_product.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Product saved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving product');
            });
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('addMenuModal');
            if (event.target === modal) {
                closeModal();
            }
        });

        function deleteMenuItem(button) {
            if (confirm('Are you sure you want to delete this menu item?')) {
                const row = button.closest('tr');
                const productId = row.querySelector('td:nth-child(1)').textContent;

                // Send delete request to backend
                const formData = new FormData();
                formData.append('product_id', productId);

                fetch('../../public/actions/products/delete_product.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Menu item deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting product');
                });
            }
        }

        // Update visibility of temperature and size based on selected category
        function updateFieldsVisibility(category) {
            const tempGroup = document.getElementById('temperatureGroup');
            const sizeGroup = document.getElementById('sizeGroup');
            const tempSelect = document.getElementById('temperatureInput');
            const sizeSelect = document.getElementById('sizeInput');

            if (!tempGroup || !sizeGroup) return;

            // Default: hide both
            tempGroup.style.display = 'none';
            sizeGroup.style.display = 'none';

            if (!category) {
                // nothing selected - reset to None
                if (tempSelect) tempSelect.value = 'None';
                if (sizeSelect) sizeSelect.value = 'None';
                return;
            }

            const c = category.toLowerCase().trim();

            // Coffee and Non-Coffee: show temperature, hide size, set temp='Hot'
            if (c === 'coffee' || c === 'non-coffee' || c === 'non coffee') {
                tempGroup.style.display = '';
                sizeGroup.style.display = 'none';
                if (tempSelect) tempSelect.value = 'Hot';
                if (sizeSelect) sizeSelect.value = 'None';
                return;
            }

            // Milktea: show size, hide temperature, set temp='Cold'
            if (c === 'milktea' || c === 'milk tea' || c.includes('milk')) {
                sizeGroup.style.display = '';
                tempGroup.style.display = 'none';
                if (tempSelect) tempSelect.value = 'Cold';
                return;
            }

            // For all other categories keep both hidden and reset to None
            tempGroup.style.display = 'none';
            sizeGroup.style.display = 'none';
            if (tempSelect) tempSelect.value = 'None';
            if (sizeSelect) sizeSelect.value = 'None';
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
                <button class="close-btn" id="hamburger-btn">✕</button>
            </div>

           
                <nav class="sidebar-nav">
                <a href="admin.php" class="nav-link">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="menu.php" class="nav-link active">
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
                <a href="reports.php" class="nav-link">
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
                    <h1 class="page-title">Menu</h1>
                </div>
                <div class="header-right">
                    <div class="admin-profile">
                        <span class="admin-label"><?php echo $adminName; ?></span>
                                                <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User" class="profile-img">

                    </div>
                </div>
            </header>

            <!-- Menu Content -->
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
                    <button class="add-btn" title="Add new menu item">➕</button>
                </div>

                <!-- Menu Table -->
                <div class="table-wrapper">
                    <table class="menu-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Image</th>
                                <th>Price</th>
                                <th>Temperature</th>
                                <th>Size</th>
                                <th>Points</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): ?>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td class="product-category"><?php echo htmlspecialchars($product['product_category'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <?php if (!empty($product['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="height: 40px; width: 40px; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <span style="color: #999;">No image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>₱<?php echo number_format($product['product_price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($product['product_temperature'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['product_size'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['product_points'] ?? '0'); ?></td>
                                    <td><button class="action-btn edit-btn" title="Edit">✎</button><button class="action-btn delete-btn" title="Delete">🗑️</button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 20px;">No products found. <a href="#" onclick="openAddMenuModal(); return false;">Add one now</a></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="addMenuModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Menu</h2>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <form id="menuForm" class="modal-body">
                <div class="form-group">
                    <p id="menuIdDisplay" style="display:none;"></p>
                    <input type="hidden" id="menuIdInput" value="">
                </div>

                <div class="form-group">
                    <label for="menuNameInput">Product Name:</label>
                    <input type="text" id="menuNameInput" class="form-input" placeholder="Americano" required>
                </div>

                <div class="form-group">
                    <label for="priceInput">Price (₱):</label>
                    <input type="number" id="priceInput" class="form-input" placeholder="100" step="0.01" required>
                </div>

                <div class="form-group">
                    <label for="categorySelect">Category:</label>
                    <select id="categorySelect" class="form-select">
                        <option value="Coffee">Coffee</option>
                        <option value="Non-Coffee">Non-Coffee</option>
                        <option value="Frappe">Frappe</option>
                        <option value="PICA PICA">PICA PICA</option>
                        <option value="Chicken Wings">Chicken Wings</option>
                        <option value="Pasta">Pasta</option>
                        <option value="Rice Meal">Rice Meal</option>
                        <option value="Sandwich">Sandwich</option>
                        <option value="Milktea">Milktea</option>
                        <option value="Extra">Extra</option>
                    </select>
                </div>

                <!-- 
                <div class="form-group">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <label style="margin: 0;">Or Add New Category:</label>
                        <button type="button" class="action-btn" onclick="addCategoryInput()" style="padding: 4px 8px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">+ Add</button>
                    </div>
                    <div id="categoryInputsContainer"></div>
                </div>
                -->

                <div class="form-group">
                    <div id="temperatureGroup" style="display: none;">
                        <label for="temperatureInput">Temperature:</label>
                        <select id="temperatureInput" class="form-select">
                            <option value="None">None</option>
                            <option value="Hot">Hot</option>
                            <option value="Cold">Cold</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <div id="sizeGroup" style="display: none;">
                        <label for="sizeInput">Size:</label>
                        <select id="sizeInput" class="form-select">
                            <option value="None">None</option>
                            <option value="Small">Small</option>
                            <option value="Medium">Medium</option>
                            <option value="Large">Large</option>
                        </select>
                    </div>
                </div>

                <div class="form-group hidden">
                    <label for="pointsInput">Loyalty Points:</label>
                    <input type="number" id="pointsInput" class="form-input" value="1">
                </div>

                <div class="form-group">
                    <label for="imagePathInput">Product Image:</label>
                    <input type="file" id="imagePathInput" class="form-input" accept="image/*">
                    <small style="color: #666; display: block; margin-top: 5px;">Supported formats: JPG, PNG, GIF (Max 2MB)</small>
                </div>

                <button type="button" class="save-btn" onclick="saveMenu()">Save</button>
            </form>
        </div>
    </div>
</body>
</html>
