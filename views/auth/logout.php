<?php
// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Destroy the session
session_destroy();

// Redirect to login page
redirect(SITE_URL . '/views/auth/login.php', 'You have been logged out successfully.', 'success');
?>