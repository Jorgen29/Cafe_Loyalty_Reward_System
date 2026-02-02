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

// Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php?error=Unauthorized+access');
    exit;
}

$userName = htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
$userEmail = htmlspecialchars($_SESSION['email']);
$date_joined = htmlspecialchars($_SESSION['date_joined'] ?? ''); // date_joined stored in session (no leading space)
$address = htmlspecialchars($_SESSION['address'] ?? '');

// Admin display name
$adminName = htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cafe Loyalty Reward</title>
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
        });

        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            const eyeIcon = document.getElementById('eyeIcon');

            if (passwordInput && passwordToggle && eyeIcon) {
                passwordToggle.addEventListener('click', () => {
                    const isPassword = passwordInput.type === "password";
                    passwordInput.type = isPassword ? "text" : "password";
                    eyeIcon.src = isPassword ? "../../public/icons/eye-open.png" : "../../public/icons/eye-close.png";
                });
            }

            const confirmInput = document.getElementById('confirmPassword');
            const passwordToggle2 = document.getElementById('passwordToggle2');
            const eyeIcon2 = document.getElementById('eyeIcon2');

            if (confirmInput && passwordToggle2 && eyeIcon2) {
                passwordToggle2.addEventListener('click', () => {
                    const isPassword = confirmInput.type === "password";
                    confirmInput.type = isPassword ? "text" : "password";
                    eyeIcon2.src = isPassword ? "../../public/icons/eye-open.png" : "../../public/icons/eye-close.png";
                });
            }
        });
    </script>
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .profile-card {
            background: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .profile-header {
            display: flex;
            gap: 30px;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #f0ebe5;
        }

        .profile-avatar {
            text-align: center;
            flex-shrink: 0;
        }

        .profile-avatar img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #6b4423;
            margin-bottom: 15px;
        }

        .profile-edit-btn {
            padding: 8px 16px;
            background-color: #6b4423;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Georgia', serif;
            font-size: 13px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .profile-edit-btn:hover {
            background-color: #5a3a1e;
        }

        .profile-user-info h2 {
            font-size: 22px;
            color: #333;
            margin: 0 0 10px 0;
            font-weight: 600;
        }

        .profile-level {
            font-size: 14px;
            color: #999;
            margin: 0;
        }

        .form-section {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .form-title {
            font-size: 20px;
            color: #333;
            margin: 0 0 25px 0;
            font-weight: 600;
        }

        .form-row {
            display: flex;
            /* grid-template-columns: 150px 1fr; */
            gap: 20px;
            margin-bottom: 20px;
            align-items: center;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-input,
        .form-select {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Georgia', serif;
            font-size: 14px;
            background-color: white;
            color: #333;
            transition: all 0.3s ease;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #6b4423;
            box-shadow: 0 0 0 3px rgba(107, 68, 35, 0.1);
        }

        .form-password-container {
            display: flex;
            gap: 10px;
            width: 100%;
        }

        .form-input-password {
            flex: 1;
        }

        .show-password-btn {
            padding: 12px 15px;
            background-color: #f5f1ed;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .show-password-btn:hover {
            background-color: #e8ddd0;
        }

        .save-btn {
            padding: 12px 40px;
            background-color: #6b4423;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Georgia', serif;
            font-size: 15px;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .save-btn:hover {
            background-color: #5a3a1e;
            transform: translateY(-2px);
        }

        .logout-section {
            background: white;
            border-radius: 8px;
            padding: 20px 30px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-section:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .logout-section img {
            width: 24px;
            height: 24px;
        }

        .logout-text {
            color: #d32f2f;
            font-weight: 600;
            font-size: 15px;
        }

        @media (max-width: 768px) {
            .profile-container {
                padding: 20px;
            }

            .profile-card,
            .form-section,
            .logout-section {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 10px;
                align-items: flex-start;
            }

            .form-label {
                display: block;
                margin-bottom: 5px;
            }

            .profile-header {
                flex-direction: column;
                gap: 20px;
                margin-bottom: 30px;
                padding-bottom: 20px;
            }

            .profile-avatar {
                width: 100%;
            }

            .profile-edit-btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .profile-container {
                padding: 15px;
            }

            .profile-card,
            .form-section,
            .logout-section {
                padding: 15px;
            }

            .profile-avatar img {
                width: 80px;
                height: 80px;
            }

            .profile-user-info h2 {
                font-size: 18px;
            }

            .form-title {
                font-size: 18px;
                margin-bottom: 15px;
            }

            .form-input,
            .form-select,
            .show-password-btn {
                font-size: 14px;
                padding: 10px 12px;
            }

            .save-btn {
                font-size: 14px;
                padding: 10px 20px;
            }

            .logout-text {
                font-size: 14px;
            }
        }
    </style>
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
                <a href="reports.php" class="nav-link">
                    <span class="nav-icon material-icons">assessment</span>
                    <span class="nav-text">Reports</span>
                </a>
                <a href="page_view.php" class="nav-link">
                    <span class="nav-icon material-icons">description</span>
                    <span class="nav-text">Pages Settings</span>
                </a>
                <a href="settings.php" class="nav-link active">
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
                    <h1 class="serif page-title">Dashboard</h1>
                </div>
                <div class="header-right">

                    <div class="admin-profile">
                        <span class="admin-label"><?php echo $adminName ?: 'Admin'; ?></span>
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User" class="profile-img">

                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Stats Cards -->
                <div class="profile-container">
                    <!-- Profile Card -->
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <a href="profile.php">
                                    <img id="profileImage" src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User">
                                </a>
                                <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/*" style="display:none">
                                <button type="button" class="profile-edit-btn" id="uploadPhotoBtn">Upload Photo</button>
                                <div id="uploadStatus" style="margin-top:8px; font-size:13px; display:none"></div>
                            </div>
                            <div class="profile-user-info">
                                <h2><?php echo $userName; ?></h2>

                            </div>
                        </div>
                    </div>

                    <!-- Edit Form Section -->
                    <div class="form-section">
                        <h3 class="form-title">Edit Account</h3>
                        <div id="successMessage" class="alert alert-success" style="display: none;"></div>
                        <div id="errorMessage" class="alert alert-error" style="display: none;"></div>
                        <form id="profileForm">
                            <div class="form-row">
                                <label class="form-label">Email:</label>
                                <input class="form-input" type="email" value="<?php echo $userEmail; ?>" readonly>
                            </div>



                            <div class="form-row">
                                <label class="form-label">Change Password (optional):</label>
                                <div class="form-password-container">
                                    <input id="password" class="form-input form-input-password" type="password" name="newPassword" placeholder="New password">

                                    <button type="button" id="passwordToggle" class="show-password-btn"><img id="eyeIcon" src="../../public/icons/eye-close.png" width="20" alt="Show/Hide"></button>

                                </div>
                            </div>

                            <div class="form-row">
                                <label class="form-label">Confirm Password:</label>
                                <input id="confirmPassword" class="form-input" type="password" name="confirmPassword" placeholder="Confirm password">
                                <button type="button" id="passwordToggle2" class="show-password-btn"><img id="eyeIcon2" src="../../public/icons/eye-close.png" width="20" alt="Show/Hide"></button>

                            </div>

                            <button class="save-btn" type="submit">Save Changes</button>
                        </form>
                    </div>

                    <!-- Logout Section -->
                    <a href="../../public/actions/auth/logout.php" style="text-decoration: none;">
                        <div class="logout-section">

                            <img src="../../public/icons/logout.jpg" alt="Logout">
                            <span class="logout-text">Log out</span>

                        </div>
                    </a>
                </div>
            </div>
        </main>
    </div>
    <script>
        (function() {
            const form = document.getElementById('profileForm');
            const successEl = document.getElementById('successMessage');
            const errorEl = document.getElementById('errorMessage');

            function showSuccess(msg) {
                if (successEl) {
                    successEl.style.display = 'block';
                    successEl.textContent = msg;
                    setTimeout(() => successEl.style.display = 'none', 3000);
                }
            }

            function showError(msg) {
                if (errorEl) {
                    errorEl.style.display = 'block';
                    errorEl.textContent = msg;
                    setTimeout(() => errorEl.style.display = 'none', 5000);
                }
            }

            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const fd = new FormData(form);

                    fetch('../../public/actions/auth/update_profile.php', {
                        method: 'POST',
                        body: fd
                    }).then(r => r.json()).then(json => {
                        if (!json) {
                            showError('No response from server');
                            return;
                        }

                        if (json.success) {
                            showSuccess(json.message || 'Profile updated');
                            // update visible name in header
                            if (json.user && json.user.firstName) {
                                try {
                                    document.querySelector('.admin-label').textContent = json.user.firstName + (json.user.lastName ? ' ' + json.user.lastName : '');
                                } catch (e) {}
                            }
                            // Always refresh the page shortly after successful save so session and UI reflect changes
                            setTimeout(() => window.location.reload(), 700);
                            return;
                        } else if (json.errors) {
                            // display first error
                            const firstKey = Object.keys(json.errors)[0];
                            showError(json.errors[firstKey]);
                        } else {
                            showError(json.message || 'Failed to update');
                        }
                    }).catch(err => {
                        console.error('Update profile error', err);
                        showError('Server error while updating');
                    });
                });
            }

            // --- Upload photo logic for admin settings ---
            const uploadBtn = document.getElementById('uploadPhotoBtn');
            const fileInput = document.getElementById('profilePhotoInput');
            const profileImg = document.getElementById('profileImage');
            const uploadStatus = document.getElementById('uploadStatus');

            if (profileImg && fileInput) {
                profileImg.style.cursor = 'pointer';
                profileImg.addEventListener('click', function(e) {
                    e.preventDefault();
                    fileInput.click();
                });
            }

            if (uploadBtn && fileInput) {
                uploadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    fileInput.click();
                });

                fileInput.addEventListener('change', function() {
                    const file = fileInput.files[0];
                    if (!file) return;

                    const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowed.includes(file.type)) {
                        if (uploadStatus) {
                            uploadStatus.style.display = 'block';
                            uploadStatus.style.color = 'red';
                            uploadStatus.textContent = 'Invalid file type';
                        }
                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        if (uploadStatus) {
                            uploadStatus.style.display = 'block';
                            uploadStatus.style.color = 'red';
                            uploadStatus.textContent = 'File too large (max 5MB)';
                        }
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        if (profileImg) profileImg.src = ev.target.result;
                    };
                    reader.readAsDataURL(file);

                    const fd = new FormData();
                    fd.append('profile_photo', file);

                    if (uploadStatus) {
                        uploadStatus.style.display = 'block';
                        uploadStatus.style.color = '#333';
                        uploadStatus.textContent = 'Uploading...';
                    }

                    fetch('../../public/actions/auth/upload_admin_profile_photo.php', {
                            method: 'POST',
                            body: fd
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                if (uploadStatus) {
                                    uploadStatus.style.color = 'green';
                                    uploadStatus.textContent = 'Uploaded successfully';
                                }
                                if (res.file_path && profileImg) profileImg.src = res.file_path;
                            } else {
                                if (uploadStatus) {
                                    uploadStatus.style.color = 'red';
                                    uploadStatus.textContent = res.message || 'Upload failed';
                                }
                            }
                        })
                        .catch(err => {
                            console.error('Upload error', err);
                            if (uploadStatus) {
                                uploadStatus.style.color = 'red';
                                uploadStatus.textContent = 'Error uploading file';
                            }
                        })
                        .finally(() => {
                            setTimeout(() => {
                                if (uploadStatus) uploadStatus.style.display = 'none';
                            }, 4000);
                        });
                });
            }
        })();
    </script>
</body>

</html>