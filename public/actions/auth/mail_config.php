<?php
// PHPMailer SMTP configuration placeholders.
// Edit these values with your SMTP provider credentials.

// Example (Gmail with App Password):
// define('SMTP_HOST', 'smtp.gmail.com');
// define('SMTP_PORT', 587);
// define('SMTP_USER', 'your-email@gmail.com');
// define('SMTP_PASS', 'your-app-password');
// define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
// define('FROM_EMAIL', 'no-reply@yourdomain.com');
// define('FROM_NAME', 'Cups & Stories Cafe');

// Default placeholders (change before use)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587); // TLS port
if (!defined('SMTP_USER')) define('SMTP_USER', 'cupsandstoriescafe15@gmail.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'qcgs qeov mttq kwll');
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'tls');
if (!defined('FROM_EMAIL')) define('FROM_EMAIL', 'cupsandstoriescafe15@gmail.com');
if (!defined('FROM_NAME')) define('FROM_NAME', 'Cups & Stories Cafe');


// Security note: For production, do NOT commit real credentials into source control.
// Either add this file to .gitignore or set values via environment variables and load them here.
