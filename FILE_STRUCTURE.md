# Complete Cafe Loyalty Reward System - File Structure

```
Cafe_Loyalty_Reward_System/
│
├── index.html                          # Login Page (HTML)
├── index.php                           # Login Page (PHP - session check)
├── signup.html                         # Signup Page (HTML)
├── signup.php                          # Signup Page (PHP - form handler)
├── send_otp.php                        # OTP Email Verification
├── AUTH_SYSTEM_README.md               # Complete auth documentation
├── QUICK_SETUP.md                      # Quick setup guide
├── FILE_STRUCTURE.md                   # This file
│
├── assets/
│   └── page_image/
│       └── home_page/                  # Home page images
│
├── public/
│   ├── css/
│   │   └── styles.css                  # Main frontend styles
│   │
│   ├── js/
│   │   ├── showpassword.js             # Password visibility toggle
│   │   ├── login-handler.js            # Login form AJAX handler
│   │   └── signup-handler.js           # Signup form AJAX handler
│   │
│   ├── icons/
│   │   ├── logo.png
│   │   ├── eye-close.png
│   │   ├── eye-open.png
│   │   ├── logout.jpg
│   │   └── [other icons]
│   │
│   ├── assets/
│   │   ├── css/
│   │   │   ├── admin-styles.css        # Admin & Cashier dashboard styling
│   │   │   ├── user-styles.css         # User pages styling
│   │   │   ├── cashier-styles.css      # Cashier POS styling
│   │   │   └── images/
│   │   │       └── logo images/        # Cafe logos
│   │   │
│   │   ├── db/
│   │   │   ├── cf-rw-db.sql            # Main database schema
│   │   │   ├── product.sql             # Product table
│   │   │   ├── home_page_assets.sql    # Home page data
│   │   │   ├── ingredienttransaction.sql
│   │   │   └── [other sql files]
│   │   │
│   │   ├── images/
│   │   │   ├── profiles/               # User profile photos
│   │   │   ├── page_image/             # Page preview images
│   │   │   └── [product images]
│   │   │
│   │   ├── coffee-1.jpg                # Default menu item image
│   │   └── [background images]
│   │
│   └── actions/
│       ├── auth/                       # Authentication & Profile Management
│       │   ├── db_config.php           # Database connection config
│       │   ├── register.php            # User registration handler
│       │   ├── login.php               # User authentication (loads profile images)
│       │   ├── logout.php              # Session destruction
│       │   ├── session_check.php       # Session validation utilities
│       │   ├── send_otp.php            # OTP email sender
│       │   ├── verify_otp.php          # OTP verification
│       │   ├── forgot_password.php     # Forgot password flow
│       │   ├── reset_password_otp.php  # Reset password with OTP
│       │   ├── reset_password.php      # Password update handler
│       │   ├── mail_config.php         # Email configuration
│       │   ├── update_profile.php      # Admin profile updater
│       │   ├── upload_profile_photo.php # Customer profile photo upload
│       │   ├── upload_admin_profile_photo.php # Admin profile photo upload
│       │   ├── update_cashier_profile.php # Cashier profile updater (NEW)
│       │   └── upload_cashier_profile_photo.php # Cashier photo upload (NEW)
│       │
│       ├── cashier/
│       │   ├── delete_cashier.php
│       │   └── save_cashier.php
│       │
│       ├── customer/
│       │   ├── claim_coffee_reward.php
│       │   ├── claim_reward.php
│       │   └── get_rewards.php
│       │
│       ├── orders/
│       │   └── save_order.php
│       │
│       ├── products/
│       │   ├── delete_ingredient.php
│       │   ├── delete_product.php
│       │   ├── get_ingredient.php
│       │   ├── save_ingredient.php
│       │   └── save_product.php
│       │
│       ├── rewards/
│       │   ├── delete_reward.php
│       │   └── save_reward.php
│       │
│       ├── fix_free_refill.php
│       ├── get_home_page.php
│       ├── get_menu_page.php
│       ├── get_reward_page.php
│       ├── save_home_page.php
│       ├── save_menu_page.php
│       ├── save_reward_page.php
│       └── upload_page_image.php
│
├── pages/
│   ├── admin/                          # Admin Dashboard Pages
│   │   ├── admin.php                   # Main admin dashboard
│   │   ├── menu.php                    # Menu management
│   │   ├── transactions.php            # Transaction history
│   │   ├── inventory.php               # Ingredient inventory
│   │   ├── inventory_reports.php       # Inventory transaction reports
│   │   ├── members_list.php            # Customer members list
│   │   ├── cashiers_list.php           # Cashier staff management
│   │   ├── reports.php                 # Sales reports
│   │   ├── rewards.php                 # Rewards management
│   │   ├── settings.php                # Admin account settings
│   │   ├── profile_info.php            # Admin profile info
│   │   ├── page_view.php               # Page content editor
│   │   └── page_view_images/           # Page preview images
│   │
│   ├── cashier/                        # Cashier Pages
│   │   ├── cashier.php                 # POS System with QR scanner
│   │   ├── transactions.php            # Cashier transaction history
│   │   ├── inventory.php               # Ingredient inventory access
│   │   ├── reports.php                 # Cashier reports
│   │   └── settings.php                # Cashier account settings (NEW)
│   │
│   ├── user/                           # Customer User Pages
│   │   ├── home.php                    # User home page
│   │   ├── menu.php                    # Menu browsing
│   │   ├── rewards.php                 # Rewards/loyalty points page
│   │   ├── profile.php                 # Customer profile
│   │   ├── profile_info.php            # Profile editing
│   │   └── faqs.php                    # FAQs page
│   │
│   └── auth/                           # Auth related pages
│       ├── reset_password.php          # Password reset form
│       └── reset_password_otp.php      # OTP verification for password reset
│
├── logs/                               # Application logs
│
├── database/                           # Database files
│
├── vendor/                             # Composer packages
│   └── phpmailer/                      # Email sending library
│
└── composer.json                       # PHP dependencies
```

---

## Recent Updates & New Features

### Profile Image Management (NEW)

- ✓ Customer profile images stored in `customer.image_path` column
- ✓ Admin/Staff profile images stored in `user.image_path` column
- ✓ Separate upload handlers for each role:
  - `upload_profile_photo.php` - Customer uploads
  - `upload_admin_profile_photo.php` - Admin uploads
  - `upload_cashier_profile_photo.php` - Cashier uploads (NEW)
- ✓ Images loaded into session during login
- ✓ Profile images display in headers & modals

### Cashier Settings Page (NEW)

- ✓ Dedicated settings page (`pages/cashier/settings.php`)
- ✓ Edit first name, last name, password
- ✓ Profile photo upload functionality
- ✓ `update_cashier_profile.php` handler for profile updates
- ✓ `upload_cashier_profile_photo.php` handler for photo uploads

### Menu Item Images (NEW)

- ✓ Product images display from `product.image_path` column
- ✓ Cashier POS shows actual product images instead of placeholders
- ✓ Fallback to default image if product has no image

### Hamburger Menu Standardization (FIXED)

- ✓ All pages use consistent hamburger menu IDs:
  - `hamburger-menu-btn` (open button)
  - `sidebar-close-btn` (close button)
- ✓ Fixed pages: admin.php, menu.php, transactions.php, inventory.php, inventory_reports.php, members_list.php, cashiers_list.php, reports.php, rewards.php, settings.php, page_view.php

### Member Modal Images (UPDATED)

- ✓ Member detail modal displays actual customer profile images
- ✓ Shows placeholder emoji when no image available

---

## Database Tables in Use

| Table                 | Purpose                                  | Key Columns                                                                      |
| --------------------- | ---------------------------------------- | -------------------------------------------------------------------------------- |
| user                  | User accounts (admin, staff, user roles) | user_id, email, password, role, image_path                                       |
| customer              | Customer profiles                        | customer_id, user_id, first_name, last_name, image_path, tier_level, date_joined |
| cashier               | Cashier staff info                       | cashier_id, user_id, first_name, last_name, store_id                             |
| product               | Menu items                               | product_id, product_name, product_price, image_path, product_category            |
| order                 | Customer orders                          | order_id, customer_id, order_date, order_time, payment_method                    |
| orderdetails          | Order line items                         | order_detail_id, order_id, product_id, qty, price                                |
| reward                | Loyalty rewards                          | reward_id, reward_name, points, discount_percent, expiration_date                |
| ingredienttransaction | Ingredient usage tracking                | ingredient_id, transaction_type, qty_change                                      |

---

## Key Features Implemented

### Authentication & Security

✓ User Registration with validation & OTP verification
✓ User Login with role-based authentication
✓ Password hashing (bcrypt)
✓ Session management with role checking
✓ Forgot password with OTP email verification
✓ Password reset flow
✓ Role-based access control (admin, staff, user)

### User Management

✓ Admin profile management with photo upload
✓ Cashier profile management with dedicated settings page
✓ Customer profile management
✓ Profile photo uploads (different storage by role)
✓ Photo display in headers, modals, and member lists

### POS System (Cashier)

✓ Cashier menu display with actual product images
✓ QR code scanning for customer loyalty
✓ Free refill voucher handling
✓ Multiple payment methods support
✓ Transaction recording with history
✓ Inventory access for ingredient tracking

### Dashboard & Reporting

✓ Admin dashboard with statistics
✓ Transaction history with filtering & sorting
✓ Inventory management & reports
✓ Sales reports & analytics
✓ Member management with profile images
✓ Rewards management & voucher system

### Mobile Responsive

✓ Hamburger menu for mobile navigation
✓ Responsive layouts on all pages
✓ Touch-friendly buttons and modals
✓ Mobile-optimized cashier POS

### Email & Communication

✓ OTP generation and email delivery
✓ Password reset via email
✓ Configurable SMTP settings

---

## Installation & Setup

See `QUICK_SETUP.md` for detailed installation instructions.

For technical details on authentication system, see `AUTH_SYSTEM_README.md`.
