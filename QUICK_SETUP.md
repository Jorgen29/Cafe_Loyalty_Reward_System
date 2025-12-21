# Authentication System - Quick Setup Guide

## What Was Created

### Backend (PHP) - `public/actions/auth/`
1. **db_config.php** - Database connection (MySQL on localhost, cf-rw-db)
2. **register.php** - Signup validation & user creation
3. **login.php** - Login authentication with session management
4. **logout.php** - Session destroy & redirect
5. **session_check.php** - Session utilities for protected pages

### Frontend (JavaScript) - `public/js/`
1. **signup-handler.js** - Signup form AJAX submission
2. **login-handler.js** - Login form AJAX submission

### Frontend (HTML) - Root Directory
- **signup.html** (Updated) - Now connected to register.php
- **index.html** (Updated) - Now connected to login.php

### Documentation
- **AUTH_SYSTEM_README.md** - Complete technical documentation

---

## How to Use

### 1. Create an Account
- Visit `signup.html`
- Fill in: First Name, Last Name, Email
- Password must have: 8+ chars, uppercase, number, special char (!@#$%^&*)
- Click "Create Account"
- Redirects to login after success

### 2. Login
- Visit `index.html`
- Enter email and password
- Click "Sign In"
- Redirects to appropriate dashboard:
  - Admin → `pages/admin/admin.php`
  - Staff → `pages/cashier/cashier.php`
  - User → `pages/user/home.php`

### 3. Logout
- In user pages, click logout in profile section
- Directs to `public/actions/auth/logout.php`
- Session destroyed, redirected to login

---

## Password Requirements
✓ 8+ characters  
✓ At least 1 uppercase letter (A-Z)  
✓ At least 1 number (0-9)  
✓ At least 1 special character (!@#$%^&*)  

**Example**: `SecurePass123!`

---

## Database Tables Used
- **user** - Stores login credentials (email, password, role)
- **customer** - Stores user profile (first_name, last_name, tier_level, date_joined)

---

## Key Features
✓ Email validation (unique, valid format)  
✓ Password hashing (bcrypt, cost 12)  
✓ Real-time field error display  
✓ Session-based authentication  
✓ Role-based redirect (admin/staff/user)  
✓ AJAX form submission (no page reload)  
✓ Responsive design (mobile/tablet/desktop)  
✓ Security: Input validation + password verification  

---

## File Paths Reference
```
/index.html                    - Login page
/signup.html                   - Signup page
/public/css/styles.css         - Auth styling + alerts
/public/js/login-handler.js    - Login form script
/public/js/signup-handler.js   - Signup form script
/public/actions/auth/          - All auth backend files
  ├── db_config.php
  ├── register.php
  ├── login.php
  ├── logout.php
  └── session_check.php
```

---

## Testing the System

### Test User Registration:
1. Go to `/signup.html`
2. Fill form with:
   - First Name: John
   - Last Name: Doe
   - Email: john@example.com
   - Password: TestPass123!
3. Click "Create Account"
4. Should see success message → redirects to login

### Test User Login:
1. Go to `/index.html`
2. Enter credentials from signup
3. Click "Sign In"
4. Should redirect to user home page

---

## Error Handling Examples

**Invalid Email**: "Please enter a valid email address"  
**Weak Password**: "Password must contain at least one special character"  
**Password Mismatch**: "Passwords do not match"  
**Existing Email**: "Email address already registered"  
**Wrong Credentials**: "Invalid email or password"  

---

## Next Steps (Optional)
- Add email verification on signup
- Add password reset functionality
- Add user profile editing
- Add login history
- Add 2FA (Two-Factor Authentication)
- Create admin user management page

---

## Support
For detailed technical documentation, see `AUTH_SYSTEM_README.md`
