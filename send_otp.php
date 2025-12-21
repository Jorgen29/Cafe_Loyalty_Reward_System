<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="public/css/styles.css">
    <title>Cups & Stories Cafe - Verify Email</title>
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

            <h1 class="login-heading">Verify Email</h1>
            <p class="login-subtitle">Enter the verification code sent to your email</p>

            <form id="verificationForm" method="post">
                <div class="form-group">
                    <label for="verificationCode" class="form-label">Verification Code</label>
                    <input 
                        type="text" 
                        id="verificationCode" 
                        name="verificationCode" 
                        class="form-input otp-input" 
                        placeholder="000000"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        required
                    >
                    <small class="form-helper">6-digit code</small>
                </div>
                <!-- Optional email field; prefills from ?email=... if present -->
                <input type="hidden" id="otpEmail" name="email" value="">

                <button type="submit" class="login-button">Confirm</button>
            </form>

            <div class="otp-actions">
                <p class="resend-text">
                    Didn't receive a code? 
                    <a href="#" class="resend-link">Resend</a>
                </p>
                <a href="index.html" class="back-link">Back to Login</a>
            </div>
        </div>
    </div>

    <script src="public/js/showpassword.js"></script>
    <script>
    (function(){
        const form = document.getElementById('verificationForm');
        const codeInput = document.getElementById('verificationCode');
        const emailInput = document.getElementById('otpEmail');
        const resendLink = document.querySelector('.resend-link');

        // Try to prefill email from query string (e.g. ?email=user@example.com)
        function qs(name){
            name = name.replace(/[\[\]]/g, '\\$&');
            const regex = new RegExp('[?&]'+name+'(=([^&#]*)|&|#|$)');
            const results = regex.exec(window.location.href);
            if (!results) return null;
            if (!results[2]) return '';
            return decodeURIComponent(results[2].replace(/\+/g, ' '));
        }

        const e = qs('email');
        if (e) { emailInput.value = e; }

        form.addEventListener('submit', function(ev){
            ev.preventDefault();
            const otp = (codeInput.value || '').trim();
            const email = (emailInput.value || '').trim();
            if (!/^[0-9]{6}$/.test(otp)) { alert('Please enter a 6-digit code'); return; }

            const body = new URLSearchParams();
            if (email) body.append('email', email);
            body.append('otp', otp);

            fetch('public/actions/auth/verify_otp.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(r => r.json()).then(j => {
                if (j && j.success) {
                    alert('Code verified. You will be redirected to set a new password.');
                    window.location.href = 'pages/auth/reset_password_otp.php';
                } else {
                    alert((j && j.message) ? j.message : 'Verification failed');
                }
            }).catch(err => {
                console.error(err);
                alert('Server error');
            });
        });

        // Resend link: call send_otp endpoint (requires email param)
        resendLink.addEventListener('click', function(ev){
            ev.preventDefault();
            const email = (emailInput.value || '').trim();
            if (!email) {
                const ask = prompt('Please enter your email to resend OTP');
                if (!ask) return; emailInput.value = ask;
            }
            const body = new URLSearchParams(); body.append('email', emailInput.value);
            fetch('public/actions/auth/send_otp.php', {
                method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString()
            }).then(r => r.json()).then(j => {
                alert((j && j.message) ? j.message : 'If the email exists, an OTP was (re)sent.');
            }).catch(err => { console.error(err); alert('Server error'); });
        });
    })();
    </script>
</body>
</html>
