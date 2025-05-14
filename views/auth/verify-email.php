<?php
// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Check if token is provided
if (!isset($_GET['token'])) {
    redirect(SITE_URL . '/views/auth/login.php', 'Invalid verification link.', 'error');
    exit;
}

$token = $_GET['token'];

// Verify the token
$query = "SELECT user_id FROM users WHERE verification_token = :token AND is_verified = FALSE";
$stmt = $db->prepare($query);
$stmt->bindParam(':token', $token);
$stmt->execute();

if ($stmt->rowCount() === 1) {
    // Token is valid, update the user's verification status
    $updateQuery = "UPDATE users SET is_verified = TRUE, verification_token = NULL WHERE verification_token = :token";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':token', $token);
    $updateStmt->execute();

    redirect(SITE_URL . '/views/auth/login.php', 'Your email has been verified. Please log in.', 'success');
} else {
    redirect(SITE_URL . '/views/auth/login.php', 'Invalid or expired verification link.', 'error');
}

exit; 