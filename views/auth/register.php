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
    
    // If employer, get company name and business file
    if($role == 'employer') {
        $company_name = trim($_POST['company_name']);
        $business_file = $_FILES['business_file'];

        // Validate business file
        if($business_file['error'] == UPLOAD_ERR_OK) {
            $target_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/business_files/';
            
            // Check if the directory exists, if not, create it
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $target_file = $target_dir . basename($business_file['name']);
            move_uploaded_file($business_file['tmp_name'], $target_file);
            $business_file_path = '/uploads/business_files/' . basename($business_file['name']);
        } else {
            $error = "Error uploading business file.";
        }
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
    } elseif($role == 'employer' && (empty($company_name) || empty($business_file_path))) {
        $error = "Please enter company name and upload a business file.";
    } else {
        // Generate a unique verification token
        $verification_token = bin2hex(random_bytes(16));

        // Prepare user data
        $userData = [
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'verification_token' => $verification_token
        ];
        
        // Add company name and business file if employer
        if($role == 'employer') {
            $userData['company_name'] = $company_name;
            $userData['business_file'] = $business_file_path;
        }
        
        // Attempt registration
        $auth = new AuthController();
        $result = $auth->register($userData);
        
        if($result['success']) {
            // Send verification email (commented out to avoid the error message)
            /*
            $mail = new PHPMailer\PHPMailer\PHPMailer();
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com'; // Set the SMTP server to send through
            $mail->SMTPAuth = true;
            $mail->Username = 'your_email@example.com'; // SMTP username
            $mail->Password = 'your_password'; // SMTP password
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('no-reply@example.com', 'ShaSha CJRS');
            $mail->addAddress($email, $first_name . ' ' . $last_name);

            $mail->isHTML(true);
            $mail->Subject = 'Email Verification';
            $mail->Body    = 'Please click the link to verify your email: <a href="' . SITE_URL . '/verify-email.php?token=' . $verification_token . '">Verify Email</a>';

            if(!$mail->send()) {
                $error = 'Verification email could not be sent. Please try again later.';
            }
            */
            
            if($userData['role'] == 'employer') {
                // For employers, redirect to login with verification message
                $message = "Registration successful! Your account needs to be verified by an admin before you can access the system. Please wait 15 minutes.";
                header("Location: " . SITE_URL . "/views/auth/login.php?success=" . urlencode($message) . "&role=employer");
                exit;
            } else {
                // For jobseekers, redirect to login with success message
                $message = "Registration successful! Please login to continue.";
                header("Location: " . SITE_URL . "/views/auth/login.php?success=" . urlencode($message));
                exit;
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
        
        .success-message a {
            color: #155724;
            font-weight: bold;
            text-decoration: underline;
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
        
        /* Tooltip container */
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }

        /* Tooltip text */
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 0;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        /* Tooltip arrow */
        .tooltip .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #555 transparent transparent transparent;
        }

        /* Show the tooltip text when you hover over the tooltip container */
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
    </style>
    <script>
        // Function to toggle company name and business file fields based on role selection
        function toggleCompanyField() {
            const roleSelect = document.getElementById('role');
            const companyField = document.getElementById('company-field');
            const businessFileField = document.getElementById('business-file-field');
            
            if(roleSelect.value === 'employer') {
                companyField.style.display = 'block';
                businessFileField.style.display = 'block';
            } else {
                companyField.style.display = 'none';
                businessFileField.style.display = 'none';
            }
        }

        // Initialize the display based on the current role selection
        document.addEventListener('DOMContentLoaded', toggleCompanyField);
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
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
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
            
            <div class="form-group" id="company-field" style="display: none;">
                <label for="company_name" class="required-field">Company Name</label>
                <input type="text" id="company_name" name="company_name" value="<?php echo $company_name; ?>">
            </div>
            
            <div class="form-group" id="business-file-field" style="display: none;">
                <label for="business_file" class="required-field">Business Document
                    <span class="tooltip">?
                        <span class="tooltiptext">A business document is required to verify your company's legitimacy. Accepted formats: PDF, DOCX.</span>
                    </span>
                </label>
                <input type="file" id="business_file" name="business_file" accept="application/pdf, application/vnd.openxmlformats-officedocument.wordprocessingml.document, .docx, .pdf">
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