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
    <title>Edit Profile - Cups & Stories Cafe</title>
    <link rel="stylesheet" href="../../public/assets/css/user-styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=logout" />
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

        /* QR modal default styling (profile page did not include cashier modal CSS) */
        .qr-modal {
            position: fixed;
            inset: 0;
            display: none;
            /* toggled via inline style/show */
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.45);
            z-index: 9999;
            padding: 20px;
        }

        .qr-modal .qr-modal-content {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.2);
            max-width: 480px;
            width: 100%;
        }

        /* Responsive QR modal layout */
        .qr-modal .qr-modal-body {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .qr-modal #profile-qr-code {
            width: 220px;
            height: 220px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid #eee;
            box-sizing: border-box;
            flex: 0 0 auto;
        }

        .qr-modal #profile-qr-info {
            font-size: 13px;
            color: #666;
        }

        .qr-modal #profile-qr-logo img {
            max-width: 140px;
            max-height: 80px;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid #eee;
            background: #fff;
            padding: 6px;
        }

        @media (max-width: 480px) {
            .qr-modal .qr-modal-content {
                padding: 14px;
            }

            .qr-modal .qr-modal-body {
                flex-direction: column;
                align-items: center;
            }

            .qr-modal #profile-qr-code {
                width: 160px;
                height: 160px;
            }

            .qr-modal #profile-qr-logo img {
                max-width: 120px;
                max-height: 60px;
            }
        }
    </style>

    <!-- QR generation library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
                if (window.innerWidth > 768) {
                    navLinks.classList.remove('show');
                }
            });

            // QR modal handling for showing user's QR code to cashier
            const qrBtn = document.getElementById('qr-show-btn');
            const qrModal = document.getElementById('profile-qr-modal');
            const qrClose = document.getElementById('profile-qr-close');
            const qrContainer = document.getElementById('profile-qr-code');
            const qrInfo = document.getElementById('profile-qr-info');

            if (qrBtn && qrModal && qrClose && qrContainer) {
                qrBtn.addEventListener('click', function() {
                    // Clear any previous QR content
                    qrContainer.innerHTML = '';
                    if (qrInfo) qrInfo.innerHTML = ''; // remove displaying of available discounts

                    // Build payload: customer_id|first_name|last_name
                    const customerId = '<?php echo $customerId ? (int)$customerId : 0; ?>';
                    const customerName = '<?php echo addslashes($customerName); ?>';
                    const payload = customerId + '|' + customerName;

                    // Create a temporary hidden container so qrcode lib produces an <img>
                    const tmpDiv = document.createElement('div');
                    tmpDiv.style.position = 'absolute';
                    tmpDiv.style.left = '-9999px';
                    document.body.appendChild(tmpDiv);

                    try {
                        new QRCode(tmpDiv, {
                            text: payload,
                            width: 220,
                            height: 220
                        });
                    } catch (e) {
                        // If QR generation fails, fall back to plain text display
                        qrContainer.textContent = payload;
                        document.body.removeChild(tmpDiv);
                        qrModal.style.display = 'flex';
                        return;
                    }

                    // Allow a short timeout for the library to insert the image
                    setTimeout(function() {
                        // Attempt to find generated image
                        const tmpImg = tmpDiv.querySelector('img');
                        // Create canvas for final QR; size adapts to container width
                        const containerRect = qrContainer.getBoundingClientRect();
                        // subtract some padding so canvas fits comfortably
                        let computedSize = Math.floor(Math.min(320, containerRect.width - 20));
                        if (computedSize < 140) computedSize = 140;
                        const canvas = document.createElement('canvas');
                        const size = computedSize;
                        canvas.width = size;
                        canvas.height = size;
                        const ctx = canvas.getContext('2d');

                        if (tmpImg && tmpImg.src) {
                            const img = new Image();
                            img.crossOrigin = 'anonymous';
                            img.onload = function() {
                                // Draw QR onto canvas sized to modal container
                                try {
                                    ctx.drawImage(img, 0, 0, size, size);
                                } catch (e) {
                                    ctx.fillStyle = '#fff';
                                    ctx.fillRect(0, 0, size, size);
                                }

                                // Draw a white rounded background for the logo (smaller so it doesn't obscure finder patterns)
                                const logoSize = Math.floor(size * 0.18); // ~18% of QR size
                                const logoX = Math.floor((size - logoSize) / 2);
                                const logoY = Math.floor((size - logoSize) / 2);

                                // White rounded box
                                const radius = 8;
                                ctx.fillStyle = '#ffffff';
                                ctx.beginPath();
                                ctx.moveTo(logoX + radius, logoY);
                                ctx.arcTo(logoX + logoSize, logoY, logoX + logoSize, logoY + logoSize, radius);
                                ctx.arcTo(logoX + logoSize, logoY + logoSize, logoX, logoY + logoSize, radius);
                                ctx.arcTo(logoX, logoY + logoSize, logoX, logoY, radius);
                                ctx.arcTo(logoX, logoY, logoX + logoSize, logoY, radius);
                                ctx.closePath();
                                ctx.fill();

                                // Draw logo image centered
                                const logo = new Image();
                                logo.crossOrigin = 'anonymous';
                                logo.src = '../../public/assets/css/images/logo images/logoName.png';
                                logo.onload = function() {
                                    // Fit logo into the white box with small padding
                                    const padding = Math.floor(logoSize * 0.12);
                                    const lw = logoSize - padding * 2;
                                    const lh = logoSize - padding * 2;
                                    ctx.drawImage(logo, logoX + padding, logoY + padding, lw, lh);

                                    // Style canvas for display
                                    canvas.style.display = 'block';
                                    canvas.style.background = '#ffffff';
                                    canvas.style.borderRadius = '8px';
                                    canvas.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';

                                    // Place canvas and download button into a centered wrapper to avoid overlap
                                    const wrapper = document.createElement('div');
                                    wrapper.style.display = 'flex';
                                    wrapper.style.flexDirection = 'column';
                                    wrapper.style.alignItems = 'center';
                                    wrapper.style.gap = '8px';
                                    wrapper.appendChild(canvas);

                                    const dl = document.createElement('a');
                                    dl.textContent = 'Download QR';
                                    dl.href = canvas.toDataURL('image/png');
                                    dl.download = 'cafe_qr_<?php echo ($customerId ? (int)$customerId : 0); ?>.png';
                                    dl.style.display = 'inline-block';
                                    dl.style.marginTop = '4px';
                                    dl.style.padding = '8px 12px';
                                    dl.style.background = '#6b4423';
                                    dl.style.color = '#fff';
                                    dl.style.borderRadius = '6px';
                                    dl.style.textDecoration = 'none';
                                    dl.style.fontFamily = "'Georgia', serif";
                                    dl.style.fontWeight = '600';
                                    dl.style.zIndex = '10';
                                    wrapper.appendChild(dl);
                                    qrContainer.appendChild(wrapper);

                                    // Cleanup
                                    if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                                };

                                logo.onerror = function() {
                                    // If logo fails, still show canvas and download inside wrapper
                                    canvas.style.display = 'block';
                                    canvas.style.background = '#ffffff';
                                    canvas.style.borderRadius = '8px';
                                    canvas.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';
                                    const wrapper2 = document.createElement('div');
                                    wrapper2.style.display = 'flex';
                                    wrapper2.style.flexDirection = 'column';
                                    wrapper2.style.alignItems = 'center';
                                    wrapper2.style.gap = '8px';
                                    wrapper2.appendChild(canvas);
                                    const dl2 = document.createElement('a');
                                    dl2.textContent = 'Download QR';
                                    dl2.href = canvas.toDataURL('image/png');
                                    dl2.download = 'cafe_qr_<?php echo ($customerId ? (int)$customerId : 0); ?>.png';
                                    dl2.style.display = 'inline-block';
                                    dl2.style.marginTop = '4px';
                                    dl2.style.padding = '8px 12px';
                                    dl2.style.background = '#6b4423';
                                    dl2.style.color = '#fff';
                                    dl2.style.borderRadius = '6px';
                                    dl2.style.textDecoration = 'none';
                                    dl2.style.fontFamily = "'Georgia', serif";
                                    dl2.style.fontWeight = '600';
                                    dl2.style.zIndex = '10';
                                    wrapper2.appendChild(dl2);
                                    qrContainer.appendChild(wrapper2);
                                    if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                                };
                            };
                            img.onerror = function() {
                                // Fallback: if generated image cannot be loaded, fallback to simple text
                                qrContainer.textContent = payload;
                                if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                            };
                            img.src = tmpImg.src;
                        } else {
                            // If no image produced (some environments produce a table), fallback to direct QR rendering into container
                            qrContainer.innerHTML = '';
                            try {
                                // Render directly into container and wrap the image if possible
                                new QRCode(qrContainer, {
                                    text: payload,
                                    width: 220,
                                    height: 220
                                });
                                // If qrcodejs placed an img directly, wrap it
                                const directImg = qrContainer.querySelector('img');
                                if (directImg) {
                                    const canvas2 = document.createElement('canvas');
                                    const size2 = 220;
                                    canvas2.width = size2;
                                    canvas2.height = size2;
                                    const ctx2 = canvas2.getContext('2d');
                                    const tmpDirect = new Image();
                                    tmpDirect.crossOrigin = 'anonymous';
                                    tmpDirect.onload = function() {
                                        ctx2.drawImage(tmpDirect, 0, 0, size2, size2);
                                        canvas2.style.display = 'block';
                                        canvas2.style.background = '#ffffff';
                                        canvas2.style.borderRadius = '8px';
                                        canvas2.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';
                                        const wrapper3 = document.createElement('div');
                                        wrapper3.style.display = 'flex';
                                        wrapper3.style.flexDirection = 'column';
                                        wrapper3.style.alignItems = 'center';
                                        wrapper3.style.gap = '8px';
                                        wrapper3.appendChild(canvas2);
                                        const dl3 = document.createElement('a');
                                        dl3.textContent = 'Download QR';
                                        dl3.href = canvas2.toDataURL('image/png');
                                        dl3.download = 'cafe_qr_<?php echo ($customerId ? (int)$customerId : 0); ?>.png';
                                        dl3.style.display = 'inline-block';
                                        dl3.style.marginTop = '4px';
                                        dl3.style.padding = '8px 12px';
                                        dl3.style.background = '#6b4423';
                                        dl3.style.color = '#fff';
                                        dl3.style.borderRadius = '6px';
                                        dl3.style.textDecoration = 'none';
                                        dl3.style.fontFamily = "'Georgia', serif";
                                        dl3.style.fontWeight = '600';
                                        dl3.style.zIndex = '10';
                                        wrapper3.appendChild(dl3);
                                        qrContainer.innerHTML = '';
                                        qrContainer.appendChild(wrapper3);
                                    };
                                    tmpDirect.onerror = function() {
                                        // If we cannot load the direct image, leave the rendered table or image as-is
                                    };
                                    tmpDirect.src = directImg.src;
                                }
                            } catch (e) {
                                qrContainer.textContent = payload;
                            }
                            if (tmpDiv && tmpDiv.parentNode) tmpDiv.parentNode.removeChild(tmpDiv);
                        }

                        // Show modal after we've started generation
                        qrModal.style.display = 'flex';
                    }, 60);
                });

                qrClose.addEventListener('click', function() {
                    qrModal.style.display = 'none';
                });
                qrModal.addEventListener('click', function(e) {
                    if (e.target === qrModal) qrModal.style.display = 'none';
                });
            }

            const navLinks_items = document.querySelectorAll('#nav-links a');
            navLinks_items.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        navLinks.classList.remove('show');
                    }
                });
            });

            // // Password visibility toggle
            // const passwordBtns = document.querySelectorAll('.show-password-btn');
            // passwordBtns.forEach(btn => {
            //     btn.addEventListener('click', function(e) {
            //         e.preventDefault();
            //         const input = this.previousElementSibling;
            //         if (input.type === 'password') {
            //             input.type = 'text';
            //             this.src = '../../public/icons/eye-close.png';
            //         } else {
            //             input.type = 'password';
            //             this.src = '../../public/icons/eye-open.png';
            //         }
            //     });
            // });

            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            const eyeIcon = document.getElementById('eyeIcon');

            passwordToggle.addEventListener('click', () => {
                const isPassword = passwordInput.type === "password";
                passwordInput.type = isPassword ? "text" : "password";

                eyeIcon.src = isPassword ?
                    "../../public/icons/eye-open.png" :
                    "../../public/icons/eye-close.png";
            });

            const confirmInput = document.getElementById('confirmPassword');
            const passwordToggle2 = document.getElementById('passwordToggle2');
            const eyeIcon2 = document.getElementById('eyeIcon2');

            passwordToggle2.addEventListener('click', () => {
                const isPassword = confirmInput.type === "password";
                confirmInput.type = isPassword ? "text" : "password";

                eyeIcon2.src = isPassword ?
                    "../../public/icons/eye-open.png" :
                    "../../public/icons/eye-close.png";
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
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 1000);
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
            //const logoutSection = document.querySelector('.logout-section');
            //if (logoutSection) {
            //logoutSection.addEventListener('click', function() {
            //if (confirm('Are you sure you want to logout?')) {
            //window.location.href = '../../public/actions/auth/logout.php';
            //}
            //});
            //}

            // Upload photo logic
            const uploadBtn = document.getElementById('uploadPhotoBtn');
            const fileInput = document.getElementById('profilePhotoInput');
            const profileImg = document.getElementById('profileImage');
            const uploadStatus = document.getElementById('uploadStatus');

            if (uploadBtn && fileInput) {
                uploadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    fileInput.click();
                });

                fileInput.addEventListener('change', function() {
                    const file = fileInput.files[0];
                    if (!file) return;

                    // Basic size/type check on client
                    const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowed.includes(file.type)) {
                        uploadStatus.style.display = 'block';
                        uploadStatus.style.color = 'red';
                        uploadStatus.textContent = 'Invalid file type';
                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        uploadStatus.style.display = 'block';
                        uploadStatus.style.color = 'red';
                        uploadStatus.textContent = 'File too large (max 5MB)';
                        return;
                    }

                    // Preview image
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        profileImg.src = ev.target.result;
                    };
                    reader.readAsDataURL(file);

                    // Upload via AJAX
                    const fd = new FormData();
                    fd.append('profile_photo', file);

                    uploadStatus.style.display = 'block';
                    uploadStatus.style.color = '#333';
                    uploadStatus.textContent = 'Uploading...';

                    fetch('../../public/actions/auth/upload_profile_photo.php', {
                            method: 'POST',
                            body: fd
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                uploadStatus.style.color = 'green';
                                uploadStatus.textContent = 'Uploaded successfully';
                                // Ensure the img uses the returned path (relative)
                                if (res.file_path) profileImg.src = res.file_path;
                            } else {
                                uploadStatus.style.color = 'red';
                                uploadStatus.textContent = res.message || 'Upload failed';
                            }
                        })
                        .catch(err => {
                            console.error('Upload error', err);
                            uploadStatus.style.color = 'red';
                            uploadStatus.textContent = 'Error uploading file';
                        })
                        .finally(() => {
                            setTimeout(() => {
                                uploadStatus.style.display = 'none';
                            }, 4000);
                        });
                });
            }

        });
    </script>
</head>

<body>
    <header class="serif header">
        <a href="home.php">
            <div class="header-left">

                <img src="../../public/assets/css/images/logo images/cups and stories logo.png" alt="Cafe Logo" class="logo">

            </div>
        </a>
        <!-- <div class="header-center">
            <input type="text" class="search" placeholder="Search...">
        </div> -->
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
            <nav id="nav-links">
                <a href="../../public/actions/auth/logout.php" class="logout">
                    <span class="material-symbols-outlined" alt="logout">logout</span>
                </a>
                <button id="qr-show-btn" class="profile-actions" title="Show QR" style="background:none;border:none;cursor:pointer;padding:0;margin-right:8px;">
                    <!-- simple QR SVG icon -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="3" width="6" height="6" stroke="#ffffff" fill="none" stroke-width="1.5" />
                        <rect x="15" y="3" width="6" height="6" stroke="#ffffff" fill="none" stroke-width="1.5" />
                        <rect x="3" y="15" width="6" height="6" stroke="#ffffff" fill="none" stroke-width="1.5" />
                        <rect x="11" y="11" width="2" height="2" fill="#ffffff" />
                        <rect x="14" y="11" width="2" height="2" fill="#ffffff" />
                        <rect x="11" y="14" width="2" height="2" fill="#ffffff" />
                    </svg>
                </button>
                <!-- Profile QR Modal -->
                <div class="qr-modal" id="profile-qr-modal" style="display:none;">
                    <div class="qr-modal-content" style="max-width:480px;padding:20px;">
                        <div style="display:flex;justify-content: flex-end;align-items:center;margin-bottom:12px;">

                            <button id="profile-qr-close" style="background:none;border:none;font-size:20px;cursor:pointer;">&times;</button>
                        </div>
                        <div class="qr-modal-body">
                            <div id="profile-qr-code"></div>
                            <div style="flex:1;min-width:180px;">


                                <div id="profile-qr-logo" style="margin-top:12px;display:flex;align-items:center;justify-content: center;">
                                    <img id="profile-qr-logo-img" src="../../public/assets/css/images/logo images/logoName.png" alt="Cafe Logo" style="max-width:140px;max-height:100%;object-fit:contain;border-radius:6px;border:1px solid #eee;background:#fff;padding:6px;" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </nav>
            <button class="hamburger" id="hamburger-btn" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>

    <div class="banner-section">
        <img src="../../public/assets/profile-page.jpg" alt="Profile Banner" class="banner">
        <div class="banner-text">Edit Profile</div>
    </div>

    <div class="profile-container">
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <a href="profile.php">
                        <img id="profileImage" src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? '../../public/icons/logo.png'); ?>" alt="User">
                    </a>
                    <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/*" style="display:none">
                    <button class="profile-edit-btn" id="uploadPhotoBtn">Upload Photo</button>
                    <div id="uploadStatus" style="margin-top:8px; font-size:13px; display:none"></div>
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
                        <input class="form-input form-input-password" type="password" id="password" name="newPassword" placeholder="New password">
                        <button type="button" id="passwordToggle" class="show-password-btn"><img id="eyeIcon" src="../../public/icons/eye-close.png" width="20" alt="Show/Hide"></button>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Confirm Password:</label>
                    <div class="form-password-container">
                        <input class="form-input form-input-password" type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm password">
                        <button type="button" id="passwordToggle2" class="show-password-btn"><img id="eyeIcon2" src="../../public/icons/eye-close.png" width="20" alt="Show/Hide"></button>

                    </div>

                </div>

                <button class="save-btn" type="submit">Save Changes</button>
            </form>
        </div>

        <!-- Logout Section -->
        <!--<div class="logout-section">
            <img src="../../public/icons/logout.jpg" alt="Logout">
            <span class="logout-text">Log out</span>
        </div>-->
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
                    <span>CUPS & Stories CAFE</span>
                </a>
            </div>
        </div>
    </footer>
</body>

</html>