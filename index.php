<?php
// Main index file - Entry point to the application
require_once __DIR__ . '/bootstrap.php';

// Redirect to homepage
header("Location: " . SITE_URL . "/views/common/home.php");
exit;
?>