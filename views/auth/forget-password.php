<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/config/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/config/mail.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/classes/Mail.php';

$email = '';
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
            
            // Delete any existing reset tokens for this user
            $delete = $conn->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
            $delete->execute([':user_id' => $user['user_id']]);
            
            // Insert new token
            $insert = $conn->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)');
            $insert->execute([
                ':user_id' => $user['user_id'],
                ':token' => $token,
                ':expires_at' => $expires
            ]);
            
            // Send reset email
            $reset_link = SITE_URL . '/views/auth/reset-password.php?token=' . $token;
            $mail = new Mail();
            if ($mail->sendPasswordReset($email, $reset_link)) {
                $success = 'If an account with that email exists, a password reset link has been sent.';
                $email = '';
            } else {
                $error = 'Failed to send reset email. Please try again later.';
            }
        } else {
            // Always show this message for security
            $success = 'If an account with that email exists, a password reset link has been sent.';
            $email = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
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
            <h2>Request Password Reset</h2>
        </div>
        <?php if(!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if(!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div class="form-group">
                <button type="submit" name="request_reset" class="btn-login">Send Reset Link</button>
            </div>
            <div class="form-footer">
                <p>Remembered your password? <a href="<?php echo SITE_URL; ?>/views/auth/login.php">Login</a></p>
            </div>
        </form>
        <div class="back-link">
            <a href="<?php echo SITE_URL; ?>">← Back to homepage</a>
        </div>
    </div>
</body>
</html>
