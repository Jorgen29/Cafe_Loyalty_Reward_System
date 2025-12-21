/**
 * Signup Form Handler
 * Handles form submission, validation, and API communication
 */

document.addEventListener('DOMContentLoaded', function() {
    const signupForm = document.getElementById('signupForm');
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    const signupBtn = document.getElementById('signupBtn');

    if (!signupForm) return;

    signupForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Clear previous messages
        successMessage.style.display = 'none';
        errorMessage.style.display = 'none';
        clearErrors();

        // Get form data
        const formData = new FormData(signupForm);

        // Disable submit button
        signupBtn.disabled = true;
        signupBtn.textContent = 'Creating Account...';

        try {
            const response = await fetch('public/actions/auth/register.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!response.ok) {
                // Handle validation errors
                if (data.errors) {
                    displayFieldErrors(data.errors);
                    errorMessage.textContent = 'Please fix the errors below.';
                } else {
                    errorMessage.textContent = data.message || 'An error occurred. Please try again.';
                }
                errorMessage.style.display = 'block';
            } else {
                // Success
                successMessage.textContent = data.message;
                successMessage.style.display = 'block';
                signupForm.reset();

                // Redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 2000);
            }
        } catch (error) {
            console.error('Error:', error);
            errorMessage.textContent = 'Network error. Please try again.';
            errorMessage.style.display = 'block';
        } finally {
            signupBtn.disabled = false;
            signupBtn.textContent = 'Create Account';
        }
    });

    // Display field-specific errors
    function displayFieldErrors(errors) {
        Object.keys(errors).forEach(field => {
            const errorElement = document.getElementById(`${field}-error`);
            const inputElement = document.getElementById(field);

            if (errorElement) {
                errorElement.textContent = errors[field];
            }

            if (inputElement) {
                inputElement.classList.add('error');
            }
        });
    }

    // Clear error messages
    function clearErrors() {
        document.querySelectorAll('.error-text').forEach(el => {
            el.textContent = '';
        });

        document.querySelectorAll('.form-input.error').forEach(el => {
            el.classList.remove('error');
        });
    }

    // Clear errors on input
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('focus', function() {
            this.classList.remove('error');
            const errorElement = document.getElementById(`${this.id}-error`);
            if (errorElement) {
                errorElement.textContent = '';
            }
        });
    });
});
