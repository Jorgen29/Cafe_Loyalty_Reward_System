

// FIRST PASSWORD
const passwordInput = document.getElementById('password');
const passwordToggle = document.getElementById('passwordToggle');
const eyeIcon = document.getElementById('eyeIcon');

passwordToggle.addEventListener('click', () => {
    const isPassword = passwordInput.type === "password";
    passwordInput.type = isPassword ? "text" : "password";

    eyeIcon.src = isPassword 
        ? "public/icons/eye-open.png" 
        : "public/icons/eye-close.png";
});


// CONFIRM PASSWORD
const confirmInput = document.getElementById('confirm_password');
const passwordToggle2 = document.getElementById('passwordToggle2');
const eyeIcon2 = document.getElementById('eyeIcon2');

passwordToggle2.addEventListener('click', () => {
    const isPassword = confirmInput.type === "password";
    confirmInput.type = isPassword ? "text" : "password";

    eyeIcon2.src = isPassword 
        ? "public/icons/eye-open.png" 
        : "public/icons/eye-close.png";
});

