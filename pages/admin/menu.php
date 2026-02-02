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

            // Render menu grid FIRST
            renderMenuGrid();
            setupMenuItemActions();
            
            // Setup category dropdown AFTER menu items are rendered
            setupCategoryDropdown();
        });

        function renderMenuGrid() {
            const menuGrid = document.getElementById('menu-grid');
            if (!menuGrid) return;
            
            menuGrid.innerHTML = '';
            const products = <?php echo json_encode($products); ?>;
            
            products.forEach(product => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'menu-item';
                itemDiv.dataset.category = product.product_category || 'Uncategorized';
                itemDiv.dataset.productId = product.product_id;
                itemDiv.dataset.productName = product.product_name;
                itemDiv.dataset.productPrice = product.product_price;
                itemDiv.dataset.productSize = product.product_size || 'None';
                itemDiv.dataset.productTemperature = product.product_temperature || 'None';
                itemDiv.dataset.productPoints = product.product_points;
                itemDiv.dataset.productCategory = product.product_category || 'Uncategorized';
                itemDiv.dataset.imagePath = product.image_path || '';
                
                const imageUrl = product.image_path ? product.image_path : '../../public/assets/images/Default.jpg';
                
                itemDiv.innerHTML = `
                    <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(product.product_name)}" class="menu-item-image">
                    <div class="menu-item-name">${escapeHtml(product.product_name)}</div>
                    <div class="menu-item-price">₱${parseFloat(product.product_price).toFixed(2)}</div>
                    <div class="menu-item-info">
                        <span class="info-badge">${escapeHtml(product.product_category || 'Uncategorized')}</span>
                    </div>
                    <div class="menu-item-actions">
                        <button class="edit-btn" title="Edit">✎ Edit</button>
                        <button class="delete-btn" title="Delete">🗑️ Delete</button>
                    </div>
                `;
                menuGrid.appendChild(itemDiv);
            });
        }

        function setupMenuItemActions() {
            const menuGrid = document.getElementById('menu-grid');
            if (!menuGrid) return;
            
            // Use event delegation for edit/delete buttons
            menuGrid.addEventListener('click', function(e) {
                if (e.target.classList.contains('edit-btn')) {
                    e.preventDefault();
                    const item = e.target.closest('.menu-item');
                    openEditModal(item);
                } else if (e.target.classList.contains('delete-btn')) {
                    e.preventDefault();
                    const item = e.target.closest('.menu-item');
                    deleteMenuItem(item);
                }
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, function(m) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[m];
            });
        }

        function filterTable(searchTerm) {
            const menuGrid = document.getElementById('menu-grid');
            const items = menuGrid.querySelectorAll('.menu-item');

            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
            });
        }

        function sortTable(sortBy) {
            const menuGrid = document.getElementById('menu-grid');
            const items = Array.from(menuGrid.querySelectorAll('.menu-item'));

            items.sort((a, b) => {
                const aId = parseInt(a.dataset.productId || 0);
                const bId = parseInt(b.dataset.productId || 0);
                const aName = a.dataset.productName.toLowerCase();
                const bName = b.dataset.productName.toLowerCase();
                
                if (sortBy === 'latest') {
                    return bId - aId;
                } else if (sortBy === 'oldest') {
                    return aId - bId;
                } else if (sortBy === 'name-asc') {
                    return aName.localeCompare(bName);
                } else if (sortBy === 'name-desc') {
                    return bName.localeCompare(aName);
                }
            });

            items.forEach(item => menuGrid.appendChild(item));
        }

        function setupCategoryDropdown() {
            const buttonsContainer = document.getElementById('category-buttons-container');
            if (!buttonsContainer) return;
            
            // Get all unique categories from menu items
            const items = document.querySelectorAll('.menu-item');
            const categories = new Set();
            items.forEach(item => {
                const cat = item.dataset.category;
                if (cat) categories.add(cat);
            });
            
            buttonsContainer.innerHTML = '';
            
            // Add "All" button
            const allBtn = document.createElement('button');
            allBtn.className = 'category-btn active';
            allBtn.dataset.category = 'all';
            allBtn.textContent = 'All Categories';
            allBtn.style.cssText = 'padding: 8px 12px; border-radius: 4px; border: none; background: rgba(255, 255, 255, 0.1); color: #fff; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.3s; width: 100%; text-align: left;';
            allBtn.addEventListener('click', function() {
                filterByCategory('all');
                updateCategoryButtons('all');
            });
            buttonsContainer.appendChild(allBtn);
            
            // Add category buttons
            Array.from(categories).sort().forEach(cat => {
                const btn = document.createElement('button');
                btn.className = 'category-btn';
                btn.dataset.category = cat;
                btn.textContent = cat;
                btn.style.cssText = 'padding: 8px 12px; border-radius: 4px; border: none; background: #6b4423; color: #fff; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.3s; width: 100%; text-align: left;';
                btn.addEventListener('click', function() {
                    filterByCategory(cat);
                    updateCategoryButtons(cat);
                });
                btn.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('active')) {
                        this.style.background = 'rgba(255,255,255,0.2)';
                    }
                });
                btn.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('active')) {
                        this.style.background = '#6b4423';
                    }
                });
                buttonsContainer.appendChild(btn);
            });
        }

        function filterByCategory(category) {
            const items = document.querySelectorAll('.menu-item');
            items.forEach(item => {
                if (category === 'all' || item.dataset.category === category) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function updateCategoryButtons(selected) {
            const buttons = document.querySelectorAll('.category-btn');
            buttons.forEach(btn => {
                if (btn.dataset.category === selected) {
                    btn.classList.add('active');
                    btn.style.background = 'rgba(255, 255, 255, 0.1)';
                } else {
                    btn.classList.remove('active');
                    btn.style.background = '#6b4423';
                }
            });
        }

        function openAddMenuModal() {
               const qtyInput = document.getElementById('priceInput');

                qtyInput.addEventListener('keydown', (e) => {
                    if (e.key === '-' || e.key === 'e') {
                        e.preventDefault(); // block minus and "e" for exponential
                    }
                });
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

        function openEditModal(item) {
            const modal = document.getElementById('addMenuModal');
            const id = item.dataset.productId;
            const name = item.dataset.productName;
            const price = item.dataset.productPrice;
            const temperature = item.dataset.productTemperature;
            const size = item.dataset.productSize;
            const points = item.dataset.productPoints;
            const category = item.dataset.productCategory;

            document.getElementById('modalTitle').textContent = 'Update Menu';
            document.getElementById('menuIdDisplay').style.display = 'block';
            document.getElementById('menuIdDisplay').textContent = 'Menu ID: ' + id;
            document.getElementById('menuIdInput').value = id;
            document.getElementById('menuNameInput').value = name;
            document.getElementById('priceInput').value = price;
            document.getElementById('categorySelect').value = category;
            // Apply visibility rules after prefilling category
            updateFieldsVisibility(category);
            // Set temperature value if visible
            const tempGroup = document.getElementById('temperatureGroup');
            if (tempGroup && tempGroup.style.display !== 'none') {
                document.getElementById('temperatureInput').value = temperature === 'None' ? 'None' : temperature;
            }
            document.getElementById('sizeInput').value = size === 'None' ? 'None' : size;
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

        function deleteMenuItem(item) {
            if (confirm('Are you sure you want to delete this menu item?')) {
                const productId = item.dataset.productId;

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
                    <img src="../../public/assets/css/images/logo images/whitelogo.png" alt="Cafe Logo" class="logo-icon">
                </div>
                <button class="close-btn" id="hamburger-btn">✕</button>
            </div>

           
                <nav class="serif sidebar-nav">
                <a href="admin.php" class="nav-link">
                    <span class="nav-icon material-icons">dashboard</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="menu.php" class="nav-link active">
                    <span class="nav-icon material-icons">restaurant</span>
                    <span class="nav-text">Menu</span>
                </a>
                <!-- Category Filter Buttons -->
                <div class="category-buttons-container" id="category-buttons-container" style="display: flex; flex-direction: column; gap: 6px; padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <!-- Category buttons will be inserted here -->
                </div>
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
                    <h1 class="serif page-title">Menu</h1>
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
                        <div class="search-container">
                            <input type="text" class="search-input" placeholder="Search products...">
                        </div>
                    </div>
                    <button class="add-btn" title="Add new menu item">➕ Add Menu Item</button>
                </div>

                <!-- Menu Grid -->
                <div id="menu-grid" class="menu-grid">
                    <!-- Menu items will be rendered here via JavaScript -->
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
                    <input type="number" id="priceInput" class="form-input" placeholder="100" step="0.01" min="0" required>
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
