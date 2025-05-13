<?php
// Set page title
$page_title = 'Company Profile';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has employer role
if(!has_role('employer')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get employer data
$query = "SELECT e.employer_id, e.company_name, e.industry, e.company_size, e.location, 
          e.website, e.description, e.verified, e.company_logo,
          u.first_name, u.last_name, u.email, u.phone
          FROM employer_profiles e
          JOIN users u ON e.user_id = u.user_id
          WHERE e.user_id = ?";

$stmt = $db->prepare($query);
$stmt->bindParam(1, $_SESSION['user_id']);
$stmt->execute();
$employer = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$employer) {
    redirect(SITE_URL . '/views/auth/logout.php', 'Employer profile not found.', 'error');
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update employer profile
    $company_name = trim($_POST['company_name']);
    $industry = trim($_POST['industry']);
    $company_size = trim($_POST['company_size']);
    $location = trim($_POST['location']);
    $website = trim($_POST['website']);
    $description = trim($_POST['description']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    
    // Basic validation
    if(empty($company_name) || empty($industry) || empty($location)) {
        $error = "Please fill in all required fields.";
    } else {
        // Update employer profile
        $query = "UPDATE employer_profiles 
                 SET company_name = ?, industry = ?, company_size = ?, location = ?, website = ?, description = ?
                 WHERE user_id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $company_name);
        $stmt->bindParam(2, $industry);
        $stmt->bindParam(3, $company_size);
        $stmt->bindParam(4, $location);
        $stmt->bindParam(5, $website);
        $stmt->bindParam(6, $description);
        $stmt->bindParam(7, $_SESSION['user_id']);
        
        if($stmt->execute()) {
            // Update user profile
            $query = "UPDATE users 
                     SET first_name = ?, last_name = ?, phone = ?
                     WHERE user_id = ?";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $first_name);
            $stmt->bindParam(2, $last_name);
            $stmt->bindParam(3, $phone);
            $stmt->bindParam(4, $_SESSION['user_id']);
            
            if($stmt->execute()) {
                $success = "Profile updated successfully.";
                
                // Refresh employer data
                $query = "SELECT e.*, u.first_name, u.last_name, u.email, u.phone
                          FROM employer_profiles e
                          JOIN users u ON e.user_id = u.user_id
                          WHERE e.user_id = ?";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $_SESSION['user_id']);
                $stmt->execute();
                $employer = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Error updating user information.";
            }
        } else {
            $error = "Error updating company profile.";
        }
    }
}

// Handle logo upload
if(isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if(!in_array($_FILES['company_logo']['type'], $allowed_types)) {
        $error = "Only JPG, PNG, and GIF files are allowed.";
    } elseif($_FILES['company_logo']['size'] > $max_size) {
        $error = "File size must be less than 2MB.";
    } else {
        // Create uploads directory if it doesn't exist
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/uploads/company_logos/';
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $filename = $employer['employer_id'] . '_' . time() . '_' . $_FILES['company_logo']['name'];
        $target_file = $upload_dir . $filename;
        
        if(move_uploaded_file($_FILES['company_logo']['tmp_name'], $target_file)) {
            // Update database with new logo
            $logo_path = 'uploads/company_logos/' . $filename;
            
            $query = "UPDATE employer_profiles SET company_logo = ? WHERE user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $logo_path);
            $stmt->bindParam(2, $_SESSION['user_id']);
            
            if($stmt->execute()) {
                $success = "Company logo uploaded successfully.";
                
                // Refresh employer data
                $query = "SELECT e.*, u.first_name, u.last_name, u.email, u.phone
                          FROM employer_profiles e
                          JOIN users u ON e.user_id = u.user_id
                          WHERE e.user_id = ?";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $_SESSION['user_id']);
                $stmt->execute();
                $employer = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Error updating logo in database.";
            }
        } else {
            $error = "Error uploading file.";
        }
    }
}

// Get list of industries
$industries = [
    'Information Technology',
    'Finance & Banking',
    'Healthcare',
    'Education',
    'Manufacturing',
    'Retail',
    'Hospitality',
    'Construction',
    'Agriculture',
    'Government',
    'Non-Profit',
    'Media & Communications',
    'Transportation & Logistics',
    'Energy & Utilities',
    'Legal',
    'Real Estate',
    'Other'
];

// Get company sizes
$company_sizes = [
    '1-10 employees',
    '11-50 employees',
    '51-200 employees',
    '201-500 employees',
    '501-1000 employees',
    '1000+ employees'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Employer Profile Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        
        .employer-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: #fff;
            padding: 0;
            box-shadow: 2px 0 8px rgba(0,0,0,0.07);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 32px 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.03);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-logo {
            background: #fff;
            color: #1a3b5d;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            font-weight: bold;
        }
        
        .sidebar-header h3 {
            color: #fff;
            font-size: 1.25rem;
            margin: 0;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex: 1;
        }
        
        .sidebar-menu li {
            margin-bottom: 2px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 28px;
            color: #e4e7ec;
            text-decoration: none;
            font-size: 1.05rem;
            border-left: 4px solid transparent;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-menu a:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 0;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transition: width 0.3s ease;
        }
        
        .sidebar-menu a:hover:before {
            width: 100%;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.08);
            color: #fff;
            border-left: 4px solid #ffd600;
        }
        
        .sidebar-menu a i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .sidebar-menu a span {
            position: relative;
            z-index: 1;
        }
        
        .sidebar-footer {
            padding: 18px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 0.95rem;
            color: #bfc9d9;
            background: rgba(255,255,255,0.03);
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            transition: margin-left 0.3s ease;
            margin-left: 0;
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 70px;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-name {
            font-size: 1.1rem;
            color: #333;
            font-weight: 500;
        }
        
        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .company-name {
            font-size: 1rem;
            color: #666;
        }
        
        .verification-badge {
            display: inline-flex;
            align-items: center;
            background-color: #e8f5e9;
            color: #388e3c;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-left: 15px;
        }
        
        .verification-badge .icon {
            margin-right: 5px;
        }
        
        .pending-verification {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .profile-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .profile-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .profile-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .profile-content {
            padding: 20px;
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
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .required::after {
            content: '*';
            color: #dc3545;
            margin-left: 4px;
        }
        
        .btn-save {
            background-color: #0056b3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-save:hover {
            background-color: #004494;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .company-logo {
            margin-bottom: 20px;
        }
        
        .logo-preview {
            width: 150px;
            height: 150px;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            overflow: hidden;
        }
        
        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .upload-hint {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .section-title {
            font-size: 1.1rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="employer-container">
        <?php include 'employer-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Company Profile</h1>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($employer['first_name'] . ' ' . $employer['last_name']); ?>
                    </div>
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($employer['company_name']); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if(isset($success)): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="profile-container">
                <div class="profile-header">
                    <h3>Company Information</h3>
                </div>
                
                <div class="profile-content">
                    <!-- Company Logo Upload Form -->
                    <form method="post" action="" enctype="multipart/form-data" class="company-logo">
                        <h4 class="section-title">Company Logo</h4>
                        
                        <div class="logo-preview">
                            <?php if(!empty($employer['company_logo'])): ?>
                                <img src="<?php echo SITE_URL . '/' . $employer['company_logo']; ?>" alt="Company Logo">
                            <?php else: ?>
                                <span>No logo uploaded</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="company_logo">Upload Logo</label>
                            <input type="file" id="company_logo" name="company_logo">
                            <div class="upload-hint">Recommended size: 400x400px. Max file size: 2MB. Formats: JPG, PNG, GIF</div>
                        </div>
                        
                        <button type="submit" class="btn-save">Upload Logo</button>
                    </form>
                    
                    <!-- Company Profile Form -->
                    <form method="post" action="">
                        <h4 class="section-title">Company Details</h4>
                        
                        <div class="form-group">
                            <label for="company_name" class="required">Company Name</label>
                            <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($employer['company_name']); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="industry" class="required">Industry</label>
                                <select id="industry" name="industry" required>
                                    <option value="">Select industry</option>
                                    <?php foreach($industries as $industry): ?>
                                        <option value="<?php echo $industry; ?>" <?php if($employer['industry'] == $industry) echo 'selected'; ?>>
                                            <?php echo $industry; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="company_size">Company Size</label>
                                <select id="company_size" name="company_size">
                                    <option value="">Select company size</option>
                                    <?php foreach($company_sizes as $size): ?>
                                        <option value="<?php echo $size; ?>" <?php if($employer['company_size'] == $size) echo 'selected'; ?>>
                                            <?php echo $size; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="location" class="required">Location</label>
                                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($employer['location']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="website">Website</label>
                                <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($employer['website']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Company Description</label>
                            <textarea id="description" name="description"><?php echo htmlspecialchars($employer['description']); ?></textarea>
                        </div>
                        
                        <h4 class="section-title">Contact Information</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name" class="required">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($employer['first_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name" class="required">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($employer['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($employer['email']); ?>" disabled>
                                <div class="upload-hint">Email cannot be changed. Please contact support if you need to update your email address.</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($employer['phone']); ?>">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-save">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>       