<?php
/**
 * Login Page - index.php
 * Handles user login with session management
 */

session_start();

// If user already logged in, redirect to appropriate dashboard
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
    <title>Cups & Stories Cafe - Login</title>
</head>
<body class="login-page">
    <div class="auth-wrapper">
        <div class="login-container">
            <div class="login-header">
                <div class="logo">
                    <img src="public\assets\css\images\logo images\BrownLogoBackground.png" alt="Cups & Stories Cafe Logo" class="logo-image">
                </div>

                <h1 class="login-heading">Welcome</h1>
                <p class="login-subtitle">Login to your Cafe Loyalty account</p>
            </div>
           

            <form id="loginForm" autocomplete="off">
                <?php if ($error): ?>
                    <div id="errorMessage" class="alert alert-error" style="display: block;">
                        <?php echo $error; ?>
                    </div>
                <?php else: ?>
                    <div id="errorMessage" class="alert alert-error" style="display: none;"></div>
                <?php endif; ?>
                
                <div id="successMessage" class="alert alert-success" style="display: none;"></div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="you@example.com"
                        autocomplete="off"
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
                            placeholder="Enter your password"
                            autocomplete="off"
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
                    <small class="error-text" id="password-error"></small>
                </div>

                <div class="form-options">
                    <!-- <div class="remember-me">
                        <input type="checkbox" id="rememberMe" name="rememberMe">
                        <label for="rememberMe">Remember me</label>
                    </div> -->
                        <a href="#" class="forgot-password" id="forgotPasswordLink">Forgot password?</a>
                </div>

                <button type="submit" class="login-button" id="loginBtn">Sign In</button>
            </form>

            <div class="divider">
                <span>New to Cups & Stories?</span>
            </div>

            <div class="register-link">
                <a href="signup.php" class="signup-link">Create an account</a>
            </div>
        </div>
    </div>

        <!-- Forgot password modal -->
        <div id="forgotModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); align-items:center; justify-content:center; z-index:10000;">
            <div style="background:#fff; padding:20px; max-width:420px; width:100%; margin:0 20px; border-radius:8px; max-height:90vh; overflow:auto; box-shadow:0 8px 30px rgba(0,0,0,0.2);">
                <h3>Reset your password</h3>
                <p>Enter your email and we'll send a reset link.</p>
                <div id="forgotMsg" style="display:none; margin-bottom:8px;"></div>
                <input type="email" id="forgotEmail" placeholder="you@example.com" style="width:100%; padding:10px; margin-bottom:10px;">
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button id="forgotCancel" type="button" style="padding:8px 12px;">Cancel</button>
                    <button id="forgotSend" type="button" style="padding:8px 12px; background:#6b4423; color:#fff; border:none;">Send</button>
                </div>
            </div>
        </div>

        <script>
            // Forgot password modal behavior + autocomplete toggle
            document.addEventListener('DOMContentLoaded', function(){
                const forgotLink = document.getElementById('forgotPasswordLink');
                const modal = document.getElementById('forgotModal');
                const cancel = document.getElementById('forgotCancel');
                const sendBtn = document.getElementById('forgotSend');
                const emailInput = document.getElementById('forgotEmail');
                const msg = document.getElementById('forgotMsg');

                // Autocomplete behavior: disable by default; enable only when "Remember me" checked
                const loginEmail = document.getElementById('email');
                const loginPassword = document.getElementById('password');
                const rememberCheckbox = document.getElementById('rememberMe');

                function updateLoginAutocomplete() {
                    if (!loginEmail || !loginPassword || !rememberCheckbox) return;
                    if (rememberCheckbox.checked) {
                        loginEmail.setAttribute('autocomplete', 'email');
                        loginPassword.setAttribute('autocomplete', 'current-password');
                    } else {
                        // set to off to discourage browser autofill
                        loginEmail.setAttribute('autocomplete', 'off');
                        loginPassword.setAttribute('autocomplete', 'off');
                    }
                }

                // Initialize autocomplete state
                updateLoginAutocomplete();
                if (rememberCheckbox) rememberCheckbox.addEventListener('change', updateLoginAutocomplete);

                function showModal(){ modal.style.display = 'flex'; emailInput.value = ''; msg.style.display = 'none'; emailInput.focus(); }
                function hideModal(){ modal.style.display = 'none'; }

                if (forgotLink) forgotLink.addEventListener('click', function(e){ e.preventDefault(); showModal(); });
                if (cancel) cancel.addEventListener('click', function(e){ e.preventDefault(); hideModal(); });

                if (sendBtn) sendBtn.addEventListener('click', function(){
                    const email = (emailInput.value || '').trim();
                    if (!email) { msg.style.display = 'block'; msg.style.color = 'red'; msg.textContent = 'Please enter your email'; return; }

                    sendBtn.disabled = true; sendBtn.textContent = 'Sending...';

                    fetch('public/actions/auth/forgot_password.php', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: new URLSearchParams({ email })
                    }).then(r => r.json()).then(j => {
                        if (j && j.success) {
                            // Inform the user, then redirect to OTP entry page where they can paste the code
                            msg.style.display = 'block'; msg.style.color = 'green'; msg.textContent = j.message || 'If this email exists, an OTP has been sent.';
                            // Close the modal after a short delay and redirect to send_otp.php with email prefilled
                            setTimeout(()=>{
                                hideModal();
                                const target = 'send_otp.php?email=' + encodeURIComponent(email);
                                window.location.href = target;
                            }, 1200);
                        } else {
                            msg.style.display = 'block'; msg.style.color = 'red'; msg.textContent = (j && j.message) ? j.message : 'Failed to send OTP';
                        }
                    }).catch(err => {
                        console.error('Forgot password error', err);
                        msg.style.display = 'block'; msg.style.color = 'red'; msg.textContent = 'Server error';
                    }).finally(()=>{ sendBtn.disabled = false; sendBtn.textContent = 'Send'; });
                });
            });
        </script>

        <script src="public/js/showpassword.js"></script>
        <script src="public/js/login-handler.js"></script>
</body>
</html>
