/**
 * Login Form Handler
 * Handles form submission, validation, and API communication
 */

document.addEventListener("DOMContentLoaded", function () {
  const loginForm = document.getElementById("loginForm");
  const successMessage = document.getElementById("successMessage");
  const errorMessage = document.getElementById("errorMessage");
  const loginBtn = document.getElementById("loginBtn");

  if (!loginForm) return;

  loginForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    // Clear previous messages
    successMessage.style.display = "none";
    errorMessage.style.display = "none";
    clearErrors();

    // Get form data
    const formData = new FormData(loginForm);

    // Disable submit button
    loginBtn.disabled = true;
    loginBtn.textContent = "Signing In...";

    try {
      const response = await fetch("public/actions/auth/login.php", {
        method: "POST",
        body: formData,
      });

      // Check if response is valid JSON
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        throw new Error("Invalid response format from server");
      }

      const data = await response.json();

      if (!response.ok) {
        // Handle validation errors
        if (data.errors) {
          displayFieldErrors(data.errors);
          errorMessage.textContent = "Please check your credentials.";
        } else {
          errorMessage.textContent =
            data.message || "An error occurred. Please try again.";
        }
        errorMessage.style.display = "block";
        } else {
        // Success
        successMessage.textContent = data.message;
        successMessage.style.display = "block";
        errorMessage.style.display = "none";
        loginForm.reset();

        // Log user info
        console.log("User logged in:", data.user);
        console.log("Redirecting to:", data.redirect);

        // Show login alert
        //const userName = data.user?.name ? data.user.name : "User";
        //alert(`Welcome, ${userName}! You have been successfully logged in.`);

        // Redirect after 1.5 seconds to ensure user sees success message
        setTimeout(() => {
          window.location.href = data.redirect;
        }, 1500);
      }
    } catch (error) {
      console.error("Error:", error);
      errorMessage.textContent = "Network error. Please try again.";
      errorMessage.style.display = "block";
    } finally {
      loginBtn.disabled = false;
      loginBtn.textContent = "Sign In";
    }
  });

  // Display field-specific errors
  function displayFieldErrors(errors) {
    Object.keys(errors).forEach((field) => {
      const errorElement = document.getElementById(`${field}-error`);
      const inputElement = document.getElementById(field);

      if (errorElement) {
        errorElement.textContent = errors[field];
      }

      if (inputElement) {
        inputElement.classList.add("error");
      }
    });
  }

  // Clear error messages
  function clearErrors() {
    document.querySelectorAll(".error-text").forEach((el) => {
      el.textContent = "";
    });

    document.querySelectorAll(".form-input.error").forEach((el) => {
      el.classList.remove("error");
    });
  }

  // Clear errors on input
  document.querySelectorAll(".form-input").forEach((input) => {
    input.addEventListener("focus", function () {
      this.classList.remove("error");
      const errorElement = document.getElementById(`${this.id}-error`);
      if (errorElement) {
        errorElement.textContent = "";
      }
    });
  });
});
