<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/config/db.php';

$error = $success = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$valid_token = false;
$user_id = null;

// Validate token
if (!empty($token)) {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if token exists and is valid
    $stmt = $conn->prepare('
        SELECT user_id 
        FROM password_resets 
        WHERE token = :token 
        AND expires_at > NOW() 
        AND used = 0
    ');
    $stmt->execute([':token' => $token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $valid_token = true;
        $user_id = $result['user_id'];
    } else {
        $error = 'Invalid or expired password reset link. Please request a new one.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && $valid_token) {
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate passwords
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please enter both password fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Update password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE users SET password = :password WHERE user_id = :user_id');
        $update->execute([':password' => $hashed_password, ':user_id' => $user_id]);
        
        // Mark token as used
        $mark_used = $conn->prepare('UPDATE password_resets SET used = 1 WHERE token = :token');
        $mark_used->execute([':token' => $token]);
        
        $success = 'Your password has been reset successfully. You can now login with your new password.';
        $valid_token = false; // Hide the form after successful reset
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Arial', sans-serif; }
        .login-container { max-width: 450px; margin: 80px auto; background-color: white; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); padding: 40px; }
        .login-header { text-align: center; margin-bottom: 30px; }
        .login-header h1 { font-size: 1.8rem; margin-bottom: 5px; color: #0056b3; }
        .login-header h2 { font-size: 1.5rem; color: #333; font-weight: 500; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #444; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 1rem; transition: border-color 0.3s ease; }
        .form-group input:focus { border-color: #0056b3; outline: none; }
        .btn-login { width: 100%; background-color: #0056b3; color: white; padding: 12px; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s ease; }
        .btn-login:hover { background-color: #004494; }
        .form-footer { text-align: center; margin-top: 25px; font-size: 0.9rem; color: #666; }
        .form-footer a { color: #0056b3; text-decoration: none; }
        .form-footer a:hover { text-decoration: underline; }
        .error-message { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
        .success-message { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #666; font-size: 0.9rem; }
        .back-link a { color: #0056b3; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>ShaSha CJRS</h1>
            <h2>Reset Your Password</h2>
        </div>
        
        <?php if(!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if(!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($valid_token): ?>
            <form method="post" action="">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <button type="submit" name="reset_password" class="btn-login">Reset Password</button>
                </div>
            </form>
        <?php endif; ?>
        
        <div class="form-footer">
            <p>Remembered your password? <a href="<?php echo SITE_URL; ?>/views/auth/login.php">Login</a></p>
            <?php if(!$valid_token && empty($success)): ?>
                <p>Need a new reset link? <a href="<?php echo SITE_URL; ?>/views/auth/forget-password.php">Request Password Reset</a></p>
            <?php endif; ?>
        </div>
        
        <div class="back-link">
            <a href="<?php echo SITE_URL; ?>">← Back to homepage</a>
        </div>
    </div>
</body>
</html> 