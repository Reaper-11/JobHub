<?php

// Support reply email settings.
// If you install PHPMailer manually in XAMPP, place it in one of these folders:
// 1. C:\xampp\htdocs\JobHub\vendor\phpmailer\phpmailer\src\
// 2. C:\xampp\htdocs\JobHub\PHPMailer\src\

if (!defined('JOBHUB_SUPPORT_SMTP_ENABLED')) {
    define('JOBHUB_SUPPORT_SMTP_ENABLED', false);
}

if (!defined('JOBHUB_SUPPORT_SMTP_HOST')) {
    define('JOBHUB_SUPPORT_SMTP_HOST', 'smtp.example.com');
}

if (!defined('JOBHUB_SUPPORT_SMTP_PORT')) {
    define('JOBHUB_SUPPORT_SMTP_PORT', 587);
}

if (!defined('JOBHUB_SUPPORT_SMTP_SECURE')) {
    define('JOBHUB_SUPPORT_SMTP_SECURE', 'tls');
}

if (!defined('JOBHUB_SUPPORT_SMTP_USERNAME')) {
    define('JOBHUB_SUPPORT_SMTP_USERNAME', 'support@jobhub.com');
}

if (!defined('JOBHUB_SUPPORT_SMTP_PASSWORD')) {
    define('JOBHUB_SUPPORT_SMTP_PASSWORD', 'your-smtp-password');
}

if (!defined('JOBHUB_SUPPORT_FROM_EMAIL')) {
    define('JOBHUB_SUPPORT_FROM_EMAIL', 'support@jobhub.com');
}

if (!defined('JOBHUB_SUPPORT_FROM_NAME')) {
    define('JOBHUB_SUPPORT_FROM_NAME', 'JobHub Support');
}
