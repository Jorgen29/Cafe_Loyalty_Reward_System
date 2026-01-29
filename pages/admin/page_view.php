<?php

/**
 * Admin Dashboard - Protected (renamed from admin.html)
 */
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=Please+log+in+first');
    exit;
}



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pages - Admin</title>
    <link rel="stylesheet" href="../../public/assets/css/admin-styles.css">
    <style>
        .page-view-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .page-selector-container {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 40px;
            padding: 20px;
            background-color: #fafaf8;
            border-radius: 8px;
            border: 1px solid #e8ddd0;
        }

        .page-selector-container label {
            font-size: 15px;
            color: #333;
            font-weight: 600;
            white-space: nowrap;
        }

        .page-selector-container select {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Georgia', serif;
            font-size: 15px;
            background-color: white;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 200px;
        }

        .page-selector-container select:focus {
            outline: none;
            border-color: #6b4423;
            box-shadow: 0 0 0 3px rgba(107, 68, 35, 0.1);
        }

        .form-card {
            background: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .form-card.active {
            display: block;
        }

        .form-title {
            font-size: 24px;
            color: #333;
            margin: 0 0 30px 0;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-size: 15px;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="date"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Georgia', serif;
            font-size: 15px;
            background-color: white;
            color: #333;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            font-family: 'Georgia', serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #6b4423;
            box-shadow: 0 0 0 3px rgba(107, 68, 35, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .image-upload-group {
            border: 2px dashed #ddd;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            background-color: #fafaf8;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .image-upload-group:hover {
            border-color: #6b4423;
            background-color: #f5f1ed;
        }

        .image-upload-group input[type="file"] {
            display: none;
        }

        .image-upload-label {
            display: block;
            cursor: pointer;
            font-size: 14px;
            color: #666;
        }

        .upload-btn {
            display: inline-block;
            padding: 8px 20px;
            background-color: #6b4423;
            color: white;
            border-radius: 4px;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .upload-btn:hover {
            background-color: #5a3a1e;
        }

        .preview-image {
            max-width: 100%;
            max-height: 300px;
            margin-top: 15px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-buttons {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e8ddd0;
        }

        .save-btn {
            padding: 12px 40px;
            background-color: #6b4423;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            font-family: 'Georgia', serif;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .save-btn:hover {
            background-color: #5a3a1e;
            transform: translateY(-2px);
        }

        .cancel-btn {
            padding: 12px 40px;
            background-color: #e8ddd0;
            color: #333;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            font-family: 'Georgia', serif;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cancel-btn:hover {
            background-color: #ddd;
        }

        .preview-image {
            max-width: 100%;
            max-height: 300px;
            margin-top: 15px;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .page-view-container {
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .form-card {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-buttons {
                flex-direction: column;
            }

            .save-btn,
            .cancel-btn {
                width: 100%;
            }

            .page-selector {
                width: 100%;
            }

            .page-selector select {
                min-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-view-container {
                padding: 15px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .form-card {
                padding: 15px;
            }

            .form-title {
                font-size: 20px;
                margin-bottom: 20px;
            }

            .form-group label {
                font-size: 14px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 14px;
                padding: 10px 12px;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.getElementById('hamburger-menu-btn');
            const sidebarCloseBtn = document.getElementById('sidebar-close-btn');
            const sidebar = document.querySelector('.sidebar');
            const pageSelector = document.getElementById('page-selector');
            const forms = document.querySelectorAll('.form-card');

            // Hamburger menu toggle
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

            // Page selector toggle
            pageSelector.addEventListener('change', function(e) {
                const selectedPage = e.target.value;

                forms.forEach(form => {
                    form.classList.remove('active');
                });

                const selectedForm = document.getElementById(selectedPage + '-form');
                if (selectedForm) {
                    selectedForm.classList.add('active');
                }
            });

            // Handle image uploads for all forms
            document.querySelectorAll('.image-upload-group').forEach(group => {
                group.addEventListener('click', function() {
                    this.querySelector('input[type="file"]').click();
                });

                group.querySelector('input[type="file"]').addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            let preview = group.querySelector('.preview-image');
                            if (!preview) {
                                preview = document.createElement('img');
                                preview.className = 'preview-image';
                                group.appendChild(preview);
                            }
                            preview.src = event.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            });

            // Load existing home page data
            function loadHomePageData() {
                fetch('../../public/actions/get_home_page.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const homeData = data.data;
                            // Populate form fields with existing data
                            if (homeData.cover_text) {
                                document.getElementById('home-cover-text').value = homeData.cover_text;
                            }
                            if (homeData.menu_teaser_title) {
                                document.getElementById('home-menu-title').value = homeData.menu_teaser_title;
                            }
                            if (homeData.menu_teaser_description) {
                                document.getElementById('home-menu-desc').value = homeData.menu_teaser_description;
                            }
                            // Load existing images
                            if (homeData.cover_image) {
                                const homeForm = document.getElementById('home-form-submit');
                                const imageGroups = homeForm.querySelectorAll('.image-upload-group');
                                if (imageGroups.length > 0) {
                                    const coverImageGroup = imageGroups[0];
                                    let preview = coverImageGroup.querySelector('.preview-image');
                                    if (!preview) {
                                        preview = document.createElement('img');
                                        preview.className = 'preview-image';
                                        coverImageGroup.appendChild(preview);
                                    }
                                    preview.src = '../../' + homeData.cover_image;
                                }
                            }
                            if (homeData.menu_teaser_image) {
                                const homeForm = document.getElementById('home-form-submit');
                                const imageGroups = homeForm.querySelectorAll('.image-upload-group');
                                if (imageGroups.length > 1) {
                                    const menuImageGroup = imageGroups[1];
                                    let preview = menuImageGroup.querySelector('.preview-image');
                                    if (!preview) {
                                        preview = document.createElement('img');
                                        preview.className = 'preview-image';
                                        menuImageGroup.appendChild(preview);
                                    }
                                    preview.src = '../../' + homeData.menu_teaser_image;
                                }
                            }
                        }
                    })
                    .catch(error => console.error('Error loading home page data:', error));
            }

            // Load home page data on page load
            loadHomePageData();

            // Load existing menu page data
            function loadMenuPageData() {
                fetch('../../public/actions/get_menu_page.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const menuData = data.data;
                            // Populate form fields with existing data
                            if (menuData.cover_text) {
                                document.getElementById('menu-cover-text').value = menuData.cover_text;
                            }
                            // Load existing image
                            if (menuData.cover_image) {
                                const menuForm = document.getElementById('menu-form-submit');
                                const imageGroups = menuForm.querySelectorAll('.image-upload-group');
                                if (imageGroups.length > 0) {
                                    const coverImageGroup = imageGroups[0];
                                    let preview = coverImageGroup.querySelector('.preview-image');
                                    if (!preview) {
                                        preview = document.createElement('img');
                                        preview.className = 'preview-image';
                                        coverImageGroup.appendChild(preview);
                                    }
                                    preview.src = '../../' + menuData.cover_image;
                                }
                            }
                        }
                    })
                    .catch(error => console.error('Error loading menu page data:', error));
            }

            // Load menu page data on page load
            loadMenuPageData();

            // Load existing reward page data
            function loadRewardPageData() {
                fetch('../../public/actions/get_reward_page.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const rewardData = data.data;
                            // Populate form fields with existing data
                            if (rewardData.cover_text) {
                                document.getElementById('reward-cover-text').value = rewardData.cover_text;
                            }
                            // Load existing image
                            if (rewardData.cover_image) {
                                const rewardForm = document.getElementById('reward-form-submit');
                                const imageGroups = rewardForm.querySelectorAll('.image-upload-group');
                                if (imageGroups.length > 0) {
                                    const coverImageGroup = imageGroups[0];
                                    let preview = coverImageGroup.querySelector('.preview-image');
                                    if (!preview) {
                                        preview = document.createElement('img');
                                        preview.className = 'preview-image';
                                        coverImageGroup.appendChild(preview);
                                    }
                                    preview.src = '../../' + rewardData.cover_image;
                                }
                            }
                        }
                    })
                    .catch(error => console.error('Error loading reward page data:', error));
            }

            // Load reward page data on page load
            loadRewardPageData();

            // Handle home page form submission with database save
            const homeForm = document.getElementById('home-form-submit');
            if (homeForm) {
                homeForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);

                    fetch('../../public/actions/save_home_page.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Home page saved successfully!');
                                location.reload(); // Refresh the entire page
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error saving home page');
                        });
                });
            }

            // Handle menu page form submission with database save
            const menuForm = document.getElementById('menu-form-submit');
            if (menuForm) {
                menuForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);

                    fetch('../../public/actions/save_menu_page.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Menu page saved successfully!');
                                location.reload(); // Refresh the entire page
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error saving menu page');
                        });
                });
            }

            // Handle reward page form submission with database save
            const rewardForm = document.getElementById('reward-form-submit');
            if (rewardForm) {
                rewardForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);

                    fetch('../../public/actions/save_reward_page.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Reward page saved successfully!');
                                location.reload(); // Refresh the entire page
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error saving reward page');
                        });
                });
            }

            // Handle form submissions for other pages
            document.querySelectorAll('form').forEach(form => {
                if (form.id !== 'home-form-submit' && form.id !== 'menu-form-submit' && form.id !== 'reward-form-submit') {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        alert('Changes saved successfully!');
                    });
                }
            });

            // Handle cancel buttons
            document.querySelectorAll('.cancel-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (confirm('Discard changes?')) {
                        this.closest('form').reset();
                    }
                });
            });

            // Set default form to home
            pageSelector.value = 'home';
            document.getElementById('home-form').classList.add('active');
        });
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
                <a href="page_view.php" class="nav-link active">
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
                    <h1 class="page-title">Edit Pages</h1>
                </div>
                <div class="header-right">
                    <div class="admin-profile">
                        <span class="admin-label">Admin</span>
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User" class="profile-img">


                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="dashboard-content">
                <div class="page-selector-container">
                    <label for="page-selector">Select Page to Edit:</label>
                    <select id="page-selector">
                        <option value="home">Home Page</option>
                        <option value="menu">Menu Page</option>
                        <option value="reward">Reward Page</option>
                    </select>
                </div>

                <!-- Home Page Form -->
                <div class="form-card" id="home-form">
                    <h2 class="form-title">Edit Home Page</h2>
                    <form id="home-form-submit">
                        <div class="form-group">
                            <label>Cover Photo</label>
                            <div class="image-upload-group">
                                <span class="upload-btn">Upload Image</span>
                                <input type="file" name="cover_image_file" accept="image/*">
                                <label class="image-upload-label">Click to upload or drag and drop</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="home-cover-text">Cover Text</label>
                            <input type="text" id="home-cover-text" name="cover_text" placeholder="Enter cover text">
                        </div>

                        <div class="form-group">
                            <label for="home-menu-title">Menu Teaser Title</label>
                            <input type="text" id="home-menu-title" name="menu_teaser_title" placeholder="e.g., Featured Menu">
                        </div>

                        <div class="form-group">
                            <label>Menu Teaser Image</label>
                            <div class="image-upload-group">
                                <span class="upload-btn">Upload Image</span>
                                <input type="file" name="menu_teaser_image_file" accept="image/*">
                                <label class="image-upload-label">Click to upload or drag and drop</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="home-menu-desc">Menu Description</label>
                            <textarea id="home-menu-desc" name="menu_teaser_description" placeholder="Enter menu description..."></textarea>
                        </div>

                        <div class="form-buttons">
                            <button type="submit" class="save-btn">Save</button>
                            <button type="button" class="cancel-btn">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Menu Page Form -->
                <div class="form-card" id="menu-form">
                    <h2 class="form-title">Edit Menu Page</h2>
                    <form id="menu-form-submit">
                        <div class="form-group">
                            <label>Cover Photo</label>
                            <div class="image-upload-group">
                                <span class="upload-btn">Upload Image</span>
                                <input type="file" name="cover_image_file" accept="image/*">
                                <label class="image-upload-label">Click to upload or drag and drop</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="menu-cover-text">Cover Text</label>
                            <input type="text" id="menu-cover-text" name="cover_text" placeholder="Enter cover text">
                        </div>

                        <div class="form-buttons">
                            <button type="submit" class="save-btn">Save</button>
                            <button type="button" class="cancel-btn">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Reward Page Form -->
                <div class="form-card" id="reward-form">
                    <h2 class="form-title">Edit Reward Page</h2>
                    <form id="reward-form-submit">
                        <div class="form-group">
                            <label>Cover Photo</label>
                            <div class="image-upload-group">
                                <span class="upload-btn">Upload Image</span>
                                <input type="file" name="cover_image_file" accept="image/*">
                                <label class="image-upload-label">Click to upload or drag and drop</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reward-cover-text">Cover Text</label>
                            <input type="text" id="reward-cover-text" name="cover_text" placeholder="Enter cover text">
                        </div>

                        <div class="form-buttons">
                            <button type="submit" class="save-btn">Save</button>
                            <button type="button" class="cancel-btn">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>

</html>