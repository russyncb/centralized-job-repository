<?php
// Main index file - Entry point to the application
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Redirect to homepage
header("Location: " . SITE_URL . "/views/common/home.php");
exit;
?>