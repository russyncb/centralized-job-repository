<?php
// Main Configuration Settings for ShaSha CJRS

// Define the base path for the application

// Site settings
define('SITE_NAME', 'ShaSha');
define('SITE_DESCRIPTION', 'Centralized Job Repository System');
define('SITE_URL', 'http://localhost/systems/claude/shasha'); // Change this for production

// File upload settings
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/shasha/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

// Security settings
define('SESSION_NAME', 'shasha_session');
define('SESSION_LIFETIME', 60 * 60 * 2); // 2 hours
define('SALT_PREFIX', 'shasha_salt_'); // Used for additional password security

// Email settings
define('MAIL_FROM', 'noreply@shasha.com');
define('MAIL_NAME', 'ShaSha CJRS');

// Pagination
define('ITEMS_PER_PAGE', 10);

// Debug mode (set to false in production)
define('DEBUG_MODE', true);

// Timezone
date_default_timezone_set('Africa/Harare');

// Error reporting
if (DEBUG_MODE === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
?>