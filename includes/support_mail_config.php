<?php

// JobHub Gmail SMTP configuration
// Gmail requires 2-Step Verification and an App Password.
// Do not use your normal Gmail password here.

defined('JOBHUB_SUPPORT_SMTP_ENABLED') || define('JOBHUB_SUPPORT_SMTP_ENABLED', true);
defined('JOBHUB_SUPPORT_SMTP_HOST') || define('JOBHUB_SUPPORT_SMTP_HOST', 'smtp.gmail.com');
defined('JOBHUB_SUPPORT_SMTP_PORT') || define('JOBHUB_SUPPORT_SMTP_PORT', 587);
defined('JOBHUB_SUPPORT_SMTP_SECURE') || define('JOBHUB_SUPPORT_SMTP_SECURE', 'tls');
defined('JOBHUB_SUPPORT_SMTP_USERNAME') || define('JOBHUB_SUPPORT_SMTP_USERNAME', 'ddipenmhz123@gmail.com');
// Paste your real 16-character Gmail App Password here
// Do not use your normal Gmail password.
defined('JOBHUB_SUPPORT_SMTP_PASSWORD') || define('JOBHUB_SUPPORT_SMTP_PASSWORD', 'axak tafy zfjv crlt');
// For Gmail, keep this the same as JOBHUB_SUPPORT_SMTP_USERNAME.
defined('JOBHUB_SUPPORT_FROM_EMAIL') || define('JOBHUB_SUPPORT_FROM_EMAIL', 'ddipenmhz123@gmail.com');
defined('JOBHUB_SUPPORT_FROM_NAME') || define('JOBHUB_SUPPORT_FROM_NAME', 'JobHub Support');
