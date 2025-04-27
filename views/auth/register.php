<?php
// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';
require_once BASE_PATH . '/controllers/AuthController.php';

// Initialize variables
$email = $password = $confirm_password = $first_name = $last_name = $phone = $company_name = "";
$role = "jobseeker"; // Default role
$error = "";
$success = "";

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

// Process registration form
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Get form data
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $role = $_POST['role'];
    
    // If employer, get company name
    if($role == 'employer') {
        $company_name = trim($_POST['company_name']);
    }
    
    // Validate input
    if(empty($email) || empty($password) || empty($confirm_password) || empty($first_name) || empty($last_name)) {
        $error = "Please fill in all required fields.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif($password != $confirm_password) {
        $error = "Passwords do not match.";
    } elseif(strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif($role == 'employer' && empty($company_name)) {
        $error = "Please enter company name.";
    } else {
        // Prepare user data
        $userData = [
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone
        ];
        
        // Add company name if employer
        if($role == 'employer') {
            $userData['company_name'] = $company_name;
        }
        
        // Attempt registration
        $auth = new AuthController();
        $result = $auth->register($userData);
        
        if($result['success']) {
            $success = $result['message'];
            
            // Clear form data
            $email = $password = $confirm_password = $first_name = $last_name = $phone = $company_name = "";
            $role = "jobseeker";
            
            // If jobseeker, automatically login
            if($userData['role'] == 'jobseeker') {
                $login_result = $auth->login($email, $password);
                if($login_result['success']) {
                    header("Location: " . SITE_URL . "/views/jobseeker/dashboard.php");
                    exit;
                }
            } else {
                // For employers, show success message with verification info
                $success .= " Your account needs to be verified by an admin before you can access the system.";
            }
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
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Registration page specific styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        
        .register-container {
            max-width: 550px;
            margin: 50px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            color: #0056b3;
        }
        
        .register-header h2 {
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
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus {
            border-color: #0056b3;
            outline: none;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.8rem;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .required-field::after {
            content: "*";
            color: #dc3545;
            margin-left: 4px;
        }
        
        .btn-register {
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
        
        .btn-register:hover {
            background-color: #004494;
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
    <script>
        // Function to toggle company name field based on role selection
        function toggleCompanyField() {
            const roleSelect = document.getElementById('role');
            const companyField = document.getElementById('company-field');
            
            if(roleSelect.value === 'employer') {
                companyField.style.display = 'block';
            } else {
                companyField.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>ShaSha CJRS</h1>
            <h2>Create an Account</h2>
        </div>
        
        <?php if(!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if(!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name" class="required-field">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo $first_name; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name" class="required-field">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo $last_name; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email" class="required-field">Email</label>
                <input type="email" id="email" name="email" value="<?php echo $email; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" value="<?php echo $phone; ?>">
            </div>
            
            <div class="form-group">
                <label for="role" class="required-field">Account Type</label>
                <select id="role" name="role" onchange="toggleCompanyField()" required>
                    <option value="jobseeker" <?php if($role == "jobseeker") echo "selected"; ?>>Job Seeker</option>
                    <option value="employer" <?php if($role == "employer") echo "selected"; ?>>Employer</option>
                </select>
            </div>
            
            <div class="form-group" id="company-field" style="display: <?php echo ($role == 'employer' ? 'block' : 'none'); ?>">
                <label for="company_name" class="required-field">Company Name</label>
                <input type="text" id="company_name" name="company_name" value="<?php echo $company_name; ?>">
            </div>
            
            <div class="form-group">
                <label for="password" class="required-field">Password</label>
                <input type="password" id="password" name="password" required>
                <small>Password must be at least 6 characters long.</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="required-field">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="form-group">
                <button type="submit" name="register" class="btn-register">Register</button>
            </div>
            
            <div class="form-footer">
                <p>Already have an account? <a href="<?php echo SITE_URL; ?>/views/auth/login.php">Login</a></p>
            </div>
        </form>
        
        <div class="back-link">
            <a href="<?php echo SITE_URL; ?>">← Back to homepage</a>
        </div>
    </div>
</body>
</html>