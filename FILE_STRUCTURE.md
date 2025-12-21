# Complete Cafe Loyalty Reward System - File Structure

```
Cafe_Loyalty_Reward_System/
│
├── index.html                          # Login Page (UPDATED - now with AJAX)
├── signup.html                         # Signup Page (UPDATED - now with AJAX)
├── send_otp.html                       # Email Verification Page
├── AUTH_SYSTEM_README.md               # Complete auth documentation
├── QUICK_SETUP.md                      # Quick setup guide
│
├── public/
│   ├── css/
│   │   └── styles.css                  # Main styles (UPDATED - added alerts/errors)
│   │
│   ├── js/
│   │   ├── showpassword.js             # Password visibility toggle
│   │   ├── login-handler.js            # Login form AJAX handler (NEW)
│   │   └── signup-handler.js           # Signup form AJAX handler (NEW)
│   │
│   ├── icons/
│   │   ├── logo.png
│   │   ├── eye-close.png
│   │   ├── user.png
│   │   ├── phone.png
│   │   ├── location.png
│   │   └── facebook.png
│   │
│   ├── assets/
│   │   ├── css/
│   │   │   ├── admin-styles.css        # Admin pages styling
│   │   │   ├── user-styles.css         # User pages styling
│   │   │   └── cashier-styles.css      # Cashier POS styling
│   │   │
│   │   ├── db/
│   │   │   └── cf-rw-db.sql            # Database schema
│   │   │
│   │   ├── background.jpg
│   │   ├── Home-page-bg1.jpg
│   │   └── [other images]
│   │
│   └── actions/
│       └── auth/                       # Authentication System (NEW FOLDER)
│           ├── db_config.php           # Database connection config (NEW)
│           ├── register.php            # Signup handler (NEW)
│           ├── login.php               # Login handler (NEW)
│           ├── logout.php              # Logout handler (NEW)
│           └── session_check.php       # Session utilities (NEW)
│
├── pages/
│   ├── admin/
│   │   ├── admin.php                   # Admin dashboard (protected)
│   │   ├── inventory.html              # Inventory management
│   │   ├── members_list.php           # Members management
│   │   ├── menu.php                   # Menu management
│   │   ├── page_view.php              # Page editor
│   │   ├── reports.php                # Reports
│   │   └── transactions.php           # Transactions
│   │
│   ├── cashier/
│   │   └── cashier.php                # POS System with QR scanner
│   │
│   ├── user/
│   │   ├── home.php                   # User home page
│   │   ├── menu.php                   # Menu page
│   │   ├── rewards.php                # Rewards page
│   │   ├── profile.php                # Profile page
│   │   ├── profile_info.html           # Profile edit page
│   │   └── faqs.php                   # FAQs page (UPDATED)
│   │
│   └── auth/
│       └── [empty]
│
└── database/
    └── [empty]
```

---

## New Files Created (Authentication System)

### Backend (5 PHP files in `public/actions/auth/`)
1. **db_config.php** - Database connection
2. **register.php** - User registration handler
3. **login.php** - User authentication handler
4. **logout.php** - Session destruction
5. **session_check.php** - Session validation utilities

### Frontend (2 JavaScript files in `public/js/`)
1. **login-handler.js** - Login form submission
2. **signup-handler.js** - Signup form submission

### Documentation (2 Markdown files in root)
1. **AUTH_SYSTEM_README.md** - Complete technical documentation
2. **QUICK_SETUP.md** - Quick setup & usage guide

---

## Updated Files

### HTML
- `index.html` - Updated with AJAX form handling
- `signup.html` - Updated with AJAX form handling

### CSS
- `public/css/styles.css` - Added alert/error styling

### Earlier Updates (FAQs)
- `pages/user/faqs.php` - Redesigned with modern responsive layout

---

## Database Tables in Use

### user
- user_id (PK, auto-increment)
- email (UNIQUE)
- password (bcrypt hashed)
- role (enum: admin, staff, user)

### customer
- customer_id (PK, auto-increment)
- user_id (FK to user)
- first_name
- last_name
- email
- tier_level
- date_joined
- [other fields]

---

## Key Features Implemented

✓ User Registration with validation
✓ User Login with authentication
✓ Password hashing (bcrypt)
✓ Session management
✓ Role-based redirection
✓ Real-time form validation
✓ Error/success messaging
✓ AJAX form submission
✓ Mobile responsive design
✓ Field-level error display

---

## Test the System

1. **Sign Up**: Go to `/signup.html`
2. **Login**: Go to `/index.html`
3. **Logout**: Click profile → logout

---

For detailed information, see:
- `AUTH_SYSTEM_README.md` - Technical details
- `QUICK_SETUP.md` - Quick reference
