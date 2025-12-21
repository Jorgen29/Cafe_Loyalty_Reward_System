<?php
session_start();
if (empty($_SESSION['otp_verified_user_id'])) {
    header('Location: /send_otp.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../public/css/styles.css">
    <title>Cups & Stories Cafe - Reset Password</title>
</head>
<body class="login-page">
    <div class="auth-wrapper">
        <div class="login-container">
            <div class="logo">
                <div class="logo-title">CUPS</div>
                <div class="logo-subtitle">& STORIES</div>
                <div class="logo-divider"></div>
                <div class="logo-cafe">C A F E</div>
            </div>

            <h1 class="login-heading">Set a new password</h1>
            <p class="login-subtitle">Enter your new password below</p>

            <form id="resetForm" method="post">
                <div class="form-group">
                    <label for="newPassword" class="form-label">New password</label>
                    <input type="password" id="newPassword" name="newPassword" class="form-input" placeholder="New password" required>
                    <small class="form-helper">Minimum 6 characters</small>
                </div>
                <div class="form-group">
                    <label for="confirmPassword" class="form-label">Confirm password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" placeholder="Confirm password" required>
                </div>
                <button type="submit" class="login-button">Save Password</button>
                <div id="msg" style="display:none;margin-top:10px;"></div>
            </form>

            <div style="margin-top:12px; text-align:center;"><a href="../../index.php">Back to Login</a></div>
        </div>
    </div>

    <script>
    (function(){
        const form = document.getElementById('resetForm');
        const np = document.getElementById('newPassword');
        const cp = document.getElementById('confirmPassword');
        const msg = document.getElementById('msg');

        form.addEventListener('submit', function(ev){
            ev.preventDefault();
            const newP = (np.value || '').trim();
            const conf = (cp.value || '').trim();
            if (!newP || !conf) { msg.style.display='block'; msg.style.color='red'; msg.textContent='Please fill both fields'; return; }
            if (newP !== conf) { msg.style.display='block'; msg.style.color='red'; msg.textContent='Passwords do not match'; return; }
            if (newP.length < 6) { msg.style.display='block'; msg.style.color='red'; msg.textContent='Password must be at least 6 characters'; return; }

            const body = new URLSearchParams();
            body.append('newPassword', newP);
            body.append('confirmPassword', conf);

            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true; btn.textContent = 'Saving...';

            fetch('../../public/actions/auth/reset_password_otp.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(r => r.json()).then(j => {
                if (j && j.success) {
                    msg.style.display='block'; msg.style.color='green'; msg.textContent = j.message || 'Password updated';
                    setTimeout(()=> { window.location.href = '../../index.php'; }, 1500);
                } else {
                    msg.style.display='block'; msg.style.color='red'; msg.textContent = (j && j.message) ? j.message : 'Failed to update password';
                }
            }).catch(err => {
                console.error(err);
                msg.style.display='block'; msg.style.color='red'; msg.textContent = 'Server error';
            }).finally(()=>{ btn.disabled = false; btn.textContent = 'Save Password'; });
        });
    })();
    </script>
</body>
</html>
