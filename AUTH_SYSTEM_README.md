# Authentication System Documentation

## Overview
Complete user authentication system for Cafe Loyalty Reward System with signup, login, and session management.

## Folder Structure
```
public/
├── actions/
│   └── auth/
│       ├── db_config.php          # Database connection configuration
│       ├── register.php           # Signup/Registration handler
│       ├── login.php              # Login authentication handler
│       ├── logout.php             # Logout and session destroy
│       └── session_check.php      # Session validation utilities
├── js/
│   ├── login-handler.js           # Login form AJAX handler
│   ├── signup-handler.js          # Signup form AJAX handler
│   └── showpassword.js            # Password visibility toggle
└── css/
    └── styles.css                 # Contains auth styling + alerts
```

## Files Created/Modified

### Backend Files (PHP)

#### 1. `public/actions/auth/db_config.php`
- **Purpose**: Database connection configuration
- **Features**:
  - Connects to MySQL database using MySQLi
  - Error handling with try-catch
  - UTF-8 charset support
- **Database**: `cf-rw-db`
- **Credentials**: Uses `root` user with no password (default XAMPP setup)

#### 2. `public/actions/auth/register.php`
- **Purpose**: Handles user registration/signup
- **Request Method**: POST
- **Validation**:
  - First/Last name: 2+ chars, letters/spaces/hyphens only
  - Email: Valid email format, must not already exist
  - Password: 8+ chars, uppercase, number, special character required
  - Confirm password: Must match password field
- **Database Operations**:
  - Inserts user into `user` table with hashed password (bcrypt, cost 12)
  - Creates customer profile in `customer` table
  - Sets default tier level as "Normal"
- **Response**: JSON with success/error messages
- **HTTP Status**:
  - 201: Account created successfully
  - 400: Validation errors
  - 409: Email already registered
  - 500: Server error

#### 3. `public/actions/auth/login.php`
- **Purpose**: Handles user authentication
- **Request Method**: POST
- **Validation**:
  - Email: Required and valid format
  - Password: Required
- **Authentication**:
  - Queries user by email
  - Verifies password using `password_verify()`
  - Creates session variables upon success
- **Session Variables Set**:
  - `user_id`: User ID
  - `email`: User email
  - `role`: User role (admin, staff, user)
  - `customer_id`: Customer ID (if exists)
  - `first_name`: First name
  - `last_name`: Last name
- **Redirect Logic**:
  - Admin role → `pages/admin/admin.php`
  - Staff role → `pages/cashier/cashier.php`
  - User role → `pages/user/home.php`
- **Response**: JSON with user info and redirect URL
- **HTTP Status**:
  - 200: Login successful
  - 400: Validation errors
  - 401: Invalid credentials
  - 500: Server error

#### 4. `public/actions/auth/logout.php`
- **Purpose**: Destroys user session
- **Action**: Calls `session_destroy()` and redirects to login

#### 5. `public/actions/auth/session_check.php`
- **Purpose**: Session validation utilities
- **Functions**:
  - `isLoggedIn()`: Check if user is authenticated
  - `hasRole($role)`: Check user role
  - `getUserInfo()`: Get user session data
  - `requireLogin()`: Redirect if not logged in
  - `requireRole($role)`: Redirect if wrong role

### Frontend Files (HTML)

#### 1. `signup.html` (Updated)
- **Changes**:
  - Removed action attribute from form (now handled by JavaScript)
  - Added success/error message containers
  - Added error text display below each field
  - Included `signup-handler.js` script

#### 2. `index.html` (Updated)
- **Changes**:
  - Removed action attribute from form
  - Added success/error message containers
  - Added error text display below fields
  - Included `login-handler.js` script

### Frontend Files (JavaScript)

#### 1. `public/js/signup-handler.js`
- **Purpose**: Handle signup form submission via AJAX
- **Features**:
  - Form validation
  - Error display and clearing
  - API communication with `register.php`
  - Success redirect to login page
  - User-friendly error messages
  - Field-level error highlighting

#### 2. `public/js/login-handler.js`
- **Purpose**: Handle login form submission via AJAX
- **Features**:
  - Form validation
  - Error display and clearing
  - API communication with `login.php`
  - Role-based redirection
  - Session creation
  - Field-level error highlighting

### CSS Updates

#### `public/css/styles.css`
- **Added Styles**:
  - `.alert`: Base alert styling with animation
  - `.alert-success`: Green success message
  - `.alert-error`: Red error message
  - `.error-text`: Field-level error text styling
  - `.form-input.error`: Red border for invalid fields
  - Animation for smooth message appearance

## Database Schema

### User Table
```sql
CREATE TABLE user (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'staff', 'user') NOT NULL
);
```

### Customer Table
```sql
CREATE TABLE customer (
  customer_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  email VARCHAR(150),
  tier_level VARCHAR(50),
  date_joined DATE,
  FOREIGN KEY (user_id) REFERENCES user(user_id)
);
```

## Password Requirements
- Minimum 8 characters
- At least 1 uppercase letter (A-Z)
- At least 1 number (0-9)
- At least 1 special character (!@#$%^&*)

## Security Features
- Passwords hashed with bcrypt (cost 12)
- Input validation on both frontend and backend
- Email uniqueness validation
- Password verification using `password_verify()`
- Session-based authentication
- Error messages don't expose database details

## API Endpoints

### POST `/public/actions/auth/register.php`
Create new user account

**Request Parameters**:
- `firstName`: string (required)
- `lastName`: string (required)
- `email`: string (required, valid email)
- `password`: string (required)
- `confirmPassword`: string (required)

**Response**:
```json
{
  "success": true,
  "message": "Account created successfully! Redirecting to login...",
  "redirect": "index.html"
}
```

### POST `/public/actions/auth/login.php`
Authenticate user

**Request Parameters**:
- `email`: string (required)
- `password`: string (required)

**Response**:
```json
{
  "success": true,
  "message": "Login successful!",
  "redirect": "pages/user/home.php",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "role": "user",
    "name": "John Doe"
  }
}
```

### GET `/public/actions/auth/logout.php`
Logout current user (redirects to login)

## Usage Instructions

### For Users
1. **Sign Up**:
   - Go to `signup.html`
   - Fill in First Name, Last Name, Email
   - Create password (must meet requirements)
   - Confirm password
   - Check "I agree to Terms & Conditions"
   - Click "Create Account"
   - Success message appears, then redirects to login

2. **Login**:
   - Go to `index.html`
   - Enter email and password
   - Check "Remember me" if desired
   - Click "Sign In"
   - Redirected to appropriate dashboard based on role

3. **Logout**:
   - Click logout button in user profile
   - Redirected to login page
   - Session destroyed

### For Developers
1. **Require Authentication in Protected Pages**:
   ```php
   <?php
   require_once 'public/actions/auth/session_check.php';
   requireLogin(); // Redirect to login if not authenticated
   ?>
   ```

2. **Check User Role**:
   ```php
   <?php
   require_once 'public/actions/auth/session_check.php';
   requireRole('admin'); // Redirect if not admin
   ?>
   ```

3. **Get User Information**:
   ```php
   <?php
   require_once 'public/actions/auth/session_check.php';
   $user = getUserInfo();
   echo $user['first_name']; // Access user data
   ?>
   ```

## Testing Credentials
After signup, you can test with:
- **Email**: user@example.com
- **Password**: SecurePass123! (meets all requirements)

## Error Handling
- Frontend: AJAX errors caught and displayed to user
- Backend: JSON responses with appropriate HTTP status codes
- Database: Error messages logged but not exposed to user
- Validation: Both frontend and backend validation

## Future Enhancements
- Email verification on signup
- Password reset functionality
- Two-factor authentication (2FA)
- Social login integration
- Login history tracking
- Account deactivation option
- API token-based authentication for mobile apps
