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
    
    $business_file_path = null; // Initialize
    
    // If employer, get company name and business file
    if($role == 'employer') {
        $company_name = trim($_POST['company_name']);
        
        // Handle business file upload
        if(isset($_FILES['business_file']) && $_FILES['business_file']['error'] == UPLOAD_ERR_OK) {
            $business_file = $_FILES['business_file'];
            
            // Define upload directory - FIXED PATH
            $upload_base_dir = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/uploads/business_files/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_base_dir)) {
                if (!mkdir($upload_base_dir, 0755, true)) {
                    $error = "Failed to create upload directory. Please contact administrator.";
                }
            }
            
            if(empty($error)) {
                // Validate file type
                $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $file_type = $business_file['type'];
                $file_extension = strtolower(pathinfo($business_file['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'doc', 'docx'];
                
                if(!in_array($file_extension, $allowed_extensions)) {
                    $error = "Only PDF, DOC, and DOCX files are allowed for business documents.";
                } elseif($business_file['size'] > 5 * 1024 * 1024) { // 5MB limit
                    $error = "File size must be less than 5MB.";
                } else {
                    // Generate unique filename to avoid conflicts
                    $file_extension = pathinfo($business_file['name'], PATHINFO_EXTENSION);
                    $unique_filename = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($business_file['name'], PATHINFO_FILENAME)) . '.' . $file_extension;
                    $target_file = $upload_base_dir . $unique_filename;
                    
                    // Attempt to move uploaded file
                    if(move_uploaded_file($business_file['tmp_name'], $target_file)) {
                        // Store relative path for database
                        $business_file_path = '/uploads/business_files/' . $unique_filename;
                        
                        // Log successful upload
                        error_log("File uploaded successfully: " . $target_file);
                        error_log("Database path: " . $business_file_path);
                    } else {
                        $error = "Failed to upload business file. Error details: " . error_get_last()['message'];
                        error_log("File upload failed. Temp file: " . $business_file['tmp_name'] . ", Target: " . $target_file);
                        error_log("Upload directory writable: " . (is_writable($upload_base_dir) ? 'Yes' : 'No'));
                        error_log("Upload directory exists: " . (is_dir($upload_base_dir) ? 'Yes' : 'No'));
                    }
                }
            }
        } else {
            // Handle file upload errors
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive.',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
            ];
            
            $upload_error_code = isset($_FILES['business_file']) ? $_FILES['business_file']['error'] : UPLOAD_ERR_NO_FILE;
            $error = isset($upload_errors[$upload_error_code]) ? $upload_errors[$upload_error_code] : 'Unknown upload error.';
            
            if($upload_error_code == UPLOAD_ERR_NO_FILE) {
                $error = "Please upload a business document.";
            }
        }
    }
    
    // Validate input
    if(empty($error)) {
        if(empty($email) || empty($password) || empty($confirm_password) || empty($first_name) || empty($last_name)) {
            $error = "Please fill in all required fields.";
        } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif($password != $confirm_password) {
            $error = "Passwords do not match.";
        } elseif(strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif($role == 'employer' && (empty($company_name) || empty($business_file_path))) {
            $error = "Please enter company name and upload a business document.";
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
                if($userData['role'] == 'employer') {
                    // For employers, redirect to login with verification message
                    $message = "Registration successful! Your account needs to be verified by an admin before you can access the system. Please wait for verification.";
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
                
                // If registration failed and file was uploaded, clean up
                if(!empty($business_file_path)) {
                    $full_file_path = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha' . $business_file_path;
                    if(file_exists($full_file_path)) {
                        unlink($full_file_path);
                    }
                }
            }
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
        
        /* File upload styling */
        .file-upload-info {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 4px;
            padding: 10px;
            margin-top: 5px;
            font-size: 0.85rem;
            color: #0066cc;
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
        
        // File upload validation
        function validateFile() {
            const fileInput = document.getElementById('business_file');
            const file = fileInput.files[0];
            
            if (file) {
                const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                const allowedExtensions = ['pdf', 'doc', 'docx'];
                const fileExtension = file.name.split('.').pop().toLowerCase();
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!allowedExtensions.includes(fileExtension)) {
                    alert('Please upload only PDF, DOC, or DOCX files.');
                    fileInput.value = '';
                    return false;
                }
                
                if (file.size > maxSize) {
                    alert('File size must be less than 5MB.');
                    fileInput.value = '';
                    return false;
                }
            }
            
            return true;
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
                        <span class="tooltiptext">Upload a business registration document, license, or certificate to verify your company's legitimacy.</span>
                    </span>
                </label>
                <input type="file" id="business_file" name="business_file" 
                       accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                       onchange="validateFile()">
                <div class="file-upload-info">
                    <strong>Accepted formats:</strong> PDF, DOC, DOCX<br>
                    <strong>Maximum size:</strong> 5MB<br>
                    <strong>Required for verification:</strong> Business registration, license, or certificate
                </div>
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