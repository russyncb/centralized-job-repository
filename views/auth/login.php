<?php
// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';
require_once BASE_PATH . '/controllers/AuthController.php';

// Initialize variables
$email = $password = "";
$error = "";
$success = "";

// Check if there's a success message from registration
if(isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Check if already logged in
if(AuthController::isLoggedIn()) {
    // Redirect based on role
    switch($_SESSION['role']) {
        case 'admin':
            header("Location: " . SITE_URL . "/views/admin/dashboard.php");
            break;
        case 'employer':
            header("Location: " . SITE_URL . "/views/employer/dashboard.php");
            break;
        case 'jobseeker':
            header("Location: " . SITE_URL . "/views/jobseeker/dashboard.php");
            break;
    }
    exit;
}

// Process login form
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    // Get form data
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validate input
    if(empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Attempt login
        $auth = new AuthController();
        $result = $auth->login($email, $password);
        
        if($result['success']) {
            $success = $result['message'];
            // Redirect based on role
            switch($_SESSION['role']) {
                case 'admin':
                    header("Location: " . SITE_URL . "/views/admin/dashboard.php");
                    break;
                case 'employer':
                    header("Location: " . SITE_URL . "/views/employer/dashboard.php");
                    break;
                case 'jobseeker':
                    header("Location: " . SITE_URL . "/views/jobseeker/dashboard.php");
                    break;
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Login page specific styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        
        .login-container {
            max-width: 450px;
            margin: 80px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            color: #0056b3;
        }
        
        .login-header h2 {
            font-size: 1.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            border-color: #0056b3;
            outline: none;
        }
        
        .btn-login {
            width: 100%;
            background-color: #0056b3;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .btn-login:hover {
            background-color: #004494;
        }
        
        .contact-admin {
            text-align: center;
            margin-top: 15px;
        }
        
        .contact-admin a {
            color: #0056b3;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .contact-admin a:hover {
            text-decoration: underline;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .form-footer a {
            color: #0056b3;
            text-decoration: none;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .back-link a {
            color: #0056b3;
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>ShaSha CJRS</h1>
            <h2>Login to Your Account</h2>
        </div>
        
        <?php if(!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if(!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo $email; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <button type="submit" name="login" class="btn-login">Login</button>
            </div>
            
            <div class="contact-admin">
                <a href="mailto:admin@shasha.com">Contact Admin</a>
            </div>
            
            <div class="form-footer">
                <p>Don't have an account? <a href="<?php echo SITE_URL; ?>/views/auth/register.php">Register</a></p>
                <p><a href="<?php echo SITE_URL; ?>/views/auth/forget-password.php">Forgot Password?</a></p>
            </div>
        </form>
        
        <div class="back-link">
            <a href="<?php echo SITE_URL; ?>">← Back to homepage</a>
        </div>
    </div>
</body>
</html>