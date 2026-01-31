<?php
/**
 * Signup Page - signup.php
 * Handles user registration with session check
 */

session_start();

// If user already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: pages/admin/admin.php');
    } elseif ($_SESSION['role'] === 'staff') {
        header('Location: pages/cashier/cashier.php');
    } else {
        header('Location: pages/user/home.php');
    }
    exit;
}

// Get any error messages from URL parameters
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="public/css/styles.css">
    <title>Cups & Stories Cafe - Create Account</title>
</head>
<body class="login-page">
    <div class="auth-wrapper">
        <div class="signup-container">
             <div class="login-header">
                <div class="logo">
                    <img src="public\assets\css\images\logo images\BrownLogoBackground.png" alt="Cups & Stories Cafe Logo" class="logo-image">
                </div>

                 <h1 class="login-heading">Join Our Community</h1>
                <p class="login-subtitle">Create your account and start earning rewards</p>
             </div>
            
           

            <form id="signupForm">
                <?php if ($error): ?>
                    <div id="errorMessage" class="alert alert-error" style="display: block;">
                        <?php echo $error; ?>
                    </div>
                <?php else: ?>
                    <div id="errorMessage" class="alert alert-error" style="display: none;"></div>
                <?php endif; ?>
                
                <div id="successMessage" class="alert alert-success" style="display: none;"></div>

                <div class="name-row">
                    <div class="form-group">
                        <label for="firstName" class="form-label">First Name</label>
                        <input 
                            type="text" 
                            id="firstName" 
                            name="firstName" 
                            class="form-input" 
                            placeholder="John"
                            required
                        >
                        <small class="error-text" id="firstName-error"></small>
                    </div>

                    <div class="form-group">
                        <label for="lastName" class="form-label">Last Name</label>
                        <input 
                            type="text" 
                            id="lastName" 
                            name="lastName" 
                            class="form-input" 
                            placeholder="Doe"
                            required
                        >
                        <small class="error-text" id="lastName-error"></small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="you@example.com"
                        required
                    >
                    <small class="error-text" id="email-error"></small>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-container">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Create a strong password"
                            required
                        >
                        <button 
                            type="button" 
                            class="password-toggle" 
                            id="passwordToggle"
                            aria-label="Toggle password visibility"
                        >
                            <img id="eyeIcon" src="public/icons/eye-close.png" width="20" alt="Show/Hide">
                        </button>
                    </div>
                    <small class="form-helper">8+ chars, uppercase, number, special char (!@#$%^&*)</small>
                    <small class="error-text" id="password-error"></small>
                </div>

                <div class="form-group">
                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                    <div class="password-container">
                        <input 
                            type="password" 
                            id="confirmPassword" 
                            name="confirmPassword" 
                            class="form-input" 
                            placeholder="Confirm your password"
                            required
                        >
                        <button 
                            type="button" 
                            class="password-toggle" 
                            id="passwordToggle2"
                            aria-label="Toggle password visibility"
                        >
                            <img id="eyeIcon2" src="public/icons/eye-close.png" width="20" alt="Show/Hide">
                        </button>
                    </div>
                    <small class="error-text" id="confirmPassword-error"></small>
                </div>

               

                <button type="submit" class="login-button" id="signupBtn">Create Account</button>
            </form>

            <div class="divider">
                <span>Already a member?</span>
            </div>

            <div class="register-link">
                <a href="index.php" class="login-link">Sign in to your account</a>
            </div>
        </div>
    </div>

    <script src="public/js/showpassword.js"></script>
    <script src="public/js/signup-handler.js"></script>

</body>
</html>