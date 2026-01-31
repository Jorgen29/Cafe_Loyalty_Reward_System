<?php
/**
 * User Profile Edit Page
 * Protected page - requires authentication
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=Please+log+in+first');
    exit;
}

// Check if user has the correct role
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'user' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../index.php?error=Unauthorized+access');
    exit;
}

// Get user info from session
$userName = htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
$userEmail = htmlspecialchars($_SESSION['email']);
$date_joined = htmlspecialchars($_SESSION['date_joined'] ?? ''); // date_joined stored in session (no leading space)
$address = htmlspecialchars($_SESSION['address'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Cafe Loyalty Reward</title>
    <link rel="stylesheet" href="../../public/assets/css/user-styles.css">    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">    <style>
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
            display: grid;
            grid-template-columns: 150px 1fr;
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.getElementById('hamburger-btn');
            const navLinks = document.getElementById('nav-links');
            
            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', function() {
                    navLinks.classList.toggle('show');
                });
            }

            window.addEventListener('resize', function() {
                if(window.innerWidth > 768) {
                    navLinks.classList.remove('show');
                }
            });

            const navLinks_items = document.querySelectorAll('#nav-links a');
            navLinks_items.forEach(link => {
                link.addEventListener('click', function() {
                    if(window.innerWidth <= 768) {
                        navLinks.classList.remove('show');
                    }
                });
            });

            // Password visibility toggle
            const passwordBtns = document.querySelectorAll('.show-password-btn');
            passwordBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const input = this.previousElementSibling;
                    if (input.type === 'password') {
                        input.type = 'text';
                        this.textContent = '🙈';
                    } else {
                        input.type = 'password';
                        this.textContent = '👁️';
                    }
                });
            });

            // Form submission with AJAX
            const profileForm = document.querySelector('form');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Disable submit button
                    const submitBtn = profileForm.querySelector('.save-btn');
                    const originalText = submitBtn.textContent;
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Saving...';

                    // Collect form data
                    const formData = new FormData(profileForm);

                    // Send AJAX request
                    fetch('../../public/actions/auth/update_profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        const successMsg = document.getElementById('successMessage');
                        const errorMsg = document.getElementById('errorMessage');

                        if (data.success) {
                            successMsg.innerHTML = data.message;
                            successMsg.style.display = 'block';
                            errorMsg.style.display = 'none';
                            
                            // Update session name in header if needed
                            document.querySelector('.profile-user-info h2').textContent = data.user.firstName + ' ' + data.user.lastName;

                            // Scroll to top
                            window.scrollTo(0, 0);

                            // If password was changed, refresh the page so session/UX updates
                            if (data.password_changed) {
                                setTimeout(() => { window.location.reload(); }, 1000);
                            }

                            // Hide success message after 3 seconds
                            setTimeout(() => {
                                successMsg.style.display = 'none';
                            }, 3000);
                        } else {
                            // Handle validation errors
                            if (data.errors) {
                                let errorHtml = '<strong>Please fix the following errors:</strong><ul>';
                                for (const field in data.errors) {
                                    errorHtml += `<li>${data.errors[field]}</li>`;
                                    // Add error class to input field
                                    const inputField = document.querySelector(`[name="${field}"]`);
                                    if (inputField) {
                                        inputField.classList.add('error');
                                        inputField.addEventListener('focus', function() {
                                            this.classList.remove('error');
                                        });
                                    }
                                }
                                errorHtml += '</ul>';
                                errorMsg.innerHTML = errorHtml;
                            } else {
                                errorMsg.innerHTML = data.message;
                            }
                            errorMsg.style.display = 'block';
                            successMsg.style.display = 'none';
                            window.scrollTo(0, 0);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        errorMsg.innerHTML = 'An error occurred while updating your profile.';
                        errorMsg.style.display = 'block';
                        successMsg.style.display = 'none';
                        window.scrollTo(0, 0);
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    });
                });
            }

            // Logout button
            const logoutSection = document.querySelector('.logout-section');
            if (logoutSection) {
                logoutSection.addEventListener('click', function() {
                    if(confirm('Are you sure you want to logout?')) {
                        window.location.href = '../../public/actions/auth/logout.php';
                    }
                });
            }
        });
    </script>
</head>
<body>
    <header class="header">
         <a href="home.php">
        <div class="header-left">
           
            <img src="../../public/assets/css/images/logo images/cups and stories logo.png" alt="Cafe Logo" class="logo">
            
        </div>
        </a>
        <div class="header-center">
            <input type="text" class="search" placeholder="Search...">
        </div>
        <div class="header-right">
             <nav id="nav-links">
                <a href="home.php">Home</a>
                <a href="menu.php">Menu</a>
                <a href="rewards.php">Rewards</a>
                
                <a href="faqs.php">FAQs</a>
            </nav>
            <div class="profile">
                 <a href="profile.php">
                <img src="<?php echo !empty($_SESSION['profile_image'])
    ? htmlspecialchars($_SESSION['profile_image'])
    : '../../public/icons/logo.png'; ?>" 
alt="User">
                </a>
               
            </div>
            <button class="hamburger" id="hamburger-btn" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>

    <div class="banner-section">
        <img src="../../public/assets/background.jpg" alt="Profile Banner" class="banner">
        <div class="banner-text">Edit Profile</div>
    </div>

    <div class="profile-container">
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                     <a href="profile.php">
                <img src="<?php echo !empty($_SESSION['profile_image'])
    ? htmlspecialchars($_SESSION['profile_image'])
    : '../../public/icons/logo.png'; ?>" 
alt="User">
                </a>
                    <button class="profile-edit-btn">Upload Photo</button>
                </div>
                <div class="profile-user-info">
                    <h2><?php echo $userName; ?></h2>
                    <div class="profile-level">Member Since <?php echo $date_joined; ?></div>
                </div>
            </div>
        </div>

        <!-- Edit Form Section -->
        <div class="form-section">
            <h3 class="form-title">Edit Account</h3>
            <div id="successMessage" class="alert alert-success" style="display: none;"></div>
            <div id="errorMessage" class="alert alert-error" style="display: none;"></div>
            <form>
                <div class="form-row">
                    <label class="form-label">Email:</label>
                    <input class="form-input" type="email" value="<?php echo $userEmail; ?>" readonly>
                </div>

                <div class="form-row">
                    <label class="form-label">First Name:</label>
                    <input class="form-input" type="text" name="firstName" value="<?php echo htmlspecialchars($_SESSION['first_name'] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <label class="form-label">Last Name:</label>
                    <input class="form-input" type="text" name="lastName" value="<?php echo htmlspecialchars($_SESSION['last_name'] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <label class="form-label">Birthday:</label>
                    <input class="form-input" type="date" name="birthday" value="<?php echo htmlspecialchars($_SESSION['birthday'] ?? ''); ?>" <?php echo (!empty($_SESSION['birthday'])) ? 'disabled' : ''; ?>>
                </div>

                <div class="form-row">
                    <label class="form-label">Address:</label>
                    <input class="form-input" type="text" name="address" value="<?php echo htmlspecialchars($_SESSION['address'] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <label class="form-label">Sex:</label>
                    <select class="form-select" name="sex">
                        <option value="">Select...</option>
                        <option value="Female" <?php echo (isset($_SESSION['sex']) && $_SESSION['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Male" <?php echo (isset($_SESSION['sex']) && $_SESSION['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Prefer not to say" <?php echo (isset($_SESSION['sex']) && $_SESSION['sex'] === 'Prefer not to say') ? 'selected' : ''; ?>>Prefer not to say</option>
                    </select>
                </div>

                <div class="form-row">
                    <label class="form-label">Occupation:</label>
                    <input class="form-input" type="text" name="occupation" value="<?php echo htmlspecialchars($_SESSION['occupation'] ?? ''); ?>">
                </div>

                <div class="form-row">
                    <label class="form-label">Change Password (optional):</label>
                    <div class="form-password-container">
                        <input class="form-input form-input-password" type="password" name="newPassword" placeholder="New password">
                        <button type="button" class="show-password-btn">👁️</button>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Confirm Password:</label>
                    <input class="form-input" type="password" name="confirmPassword" placeholder="Confirm password">
                </div>

                <button class="save-btn" type="submit">Save Changes</button>
            </form>
        </div>

        <!-- Logout Section -->
        <div class="logout-section">
            <img src="../../public/icons/logout.jpg" alt="Logout">
            <span class="logout-text">Log out</span>
        </div>
    </div>

    <footer class="footer">
        <div style="text-align:center; color:#fff; font-size:20px; margin-bottom:10px;">Contact us:</div>
        <div class="footer-contact-row">
            <div class="footer-contact-item">
                <img src="../../public/icons/call.png" alt="Phone">
                <span>0906 377 9569</span>
            </div>
            <div class="footer-contact-item">
                <img src="../../public/icons/placeholder.png" alt="Location">
                <span>Barangay Pinagtipunan, General Trias, Cavite</span>
            </div>
            <div class="footer-contact-item">
                <a href="https://web.facebook.com/profile.php?id=100095680143645" style="text-decoration: none;color:white;text-align:center;display: flex;align-items: center; gap: 12px;">
                <img src="../../public/icons/communication.png" alt="Facebook">
                <span >CUPS & Stories CAFE</span>
                </a>
            </div>
        </div>
    </footer>
</body>
</html>
