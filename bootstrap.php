<?php
// Define the base path for the application
define('BASE_PATH', __DIR__);

// Include essential files
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/functions.php';

// PHPMailer Autoloader
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';