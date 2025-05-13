<?php
// Set page title
$page_title = 'My Profile';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has jobseeker role
if(!has_role('jobseeker')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get jobseeker profile with user information
$query = "SELECT jp.*, u.first_name, u.last_name, u.email, u.phone
          FROM jobseeker_profiles jp
          JOIN users u ON jp.user_id = u.user_id
          WHERE jp.user_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $_SESSION['user_id']);
$stmt->execute();
$jobseeker = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$jobseeker) {
    redirect(SITE_URL . '/views/auth/logout.php', 'Jobseeker profile not found.', 'error');
    exit;
}

$jobseeker_id = $jobseeker['jobseeker_id'];

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $headline = trim($_POST['headline']);
    $education_level = trim($_POST['education_level']);
    $experience_years = trim($_POST['experience_years']);
    $skills = trim($_POST['skills']);
    $address = trim($_POST['address']);
    
    // Basic validation
    if(empty($first_name) || empty($last_name)) {
        $error = "Please fill in all required fields.";
    } else {
        // Update user data
        $query = "UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $first_name);
        $stmt->bindParam(2, $last_name);
        $stmt->bindParam(3, $phone);
        $stmt->bindParam(4, $_SESSION['user_id']);
        
        if($stmt->execute()) {
            // Update jobseeker profile
            $query = "UPDATE jobseeker_profiles SET headline = ?, education_level = ?, experience_years = ?, skills = ?, address = ? WHERE user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $headline);
            $stmt->bindParam(2, $education_level);
            $stmt->bindParam(3, $experience_years);
            $stmt->bindParam(4, $skills);
            $stmt->bindParam(5, $address);
            $stmt->bindParam(6, $_SESSION['user_id']);
            
            if($stmt->execute()) {
                $success = "Profile updated successfully.";
                
                // Refresh jobseeker data
                $query = "SELECT jp.*, u.first_name, u.last_name, u.email, u.phone
                          FROM jobseeker_profiles jp
                          JOIN users u ON jp.user_id = u.user_id
                          WHERE jp.user_id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $_SESSION['user_id']);
                $stmt->execute();
                $jobseeker = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Error updating jobseeker profile.";
            }
        } else {
            $error = "Error updating user information.";
        }
    }
}

// Process resume upload
if(isset($_FILES['resume']) && $_FILES['resume']['error'] === 0) {
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if(!in_array($_FILES['resume']['type'], $allowed_types)) {
        $error = "Only PDF and Word documents are allowed.";
    } elseif($_FILES['resume']['size'] > $max_size) {
        $error = "File size must be less than 5MB.";
    } else {
        // Create uploads directory if it doesn't exist
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/uploads/resumes/';
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $filename = $jobseeker_id . '_' . time() . '_' . $_FILES['resume']['name'];
        $target_file = $upload_dir . $filename;
        
        if(move_uploaded_file($_FILES['resume']['tmp_name'], $target_file)) {
            // Update database with new resume
            $resume_path = 'uploads/resumes/' . $filename;
            
            $query = "UPDATE jobseeker_profiles SET resume = ? WHERE user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $resume_path);
            $stmt->bindParam(2, $_SESSION['user_id']);
            
            if($stmt->execute()) {
                $success = "Resume uploaded successfully.";
                
                // Refresh jobseeker data
                // Refresh jobseeker data
                $query = "SELECT jp.*, u.first_name, u.last_name, u.email, u.phone
                          FROM jobseeker_profiles jp
                          JOIN users u ON jp.user_id = u.user_id
                          WHERE jp.user_id = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $_SESSION['user_id']);
                $stmt->execute();
                $jobseeker = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Error updating resume in database.";
            }
        } else {
            $error = "Error uploading file.";
        }
    }
}

// List of education levels
$education_levels = [
    'High School',
    'Certificate',
    'Diploma',
    'Associate Degree',
    'Bachelor\'s Degree',
    'Master\'s Degree',
    'PhD/Doctorate',
    'Professional Certification',
    'Other'
];

// Calculate profile completion
$profile_fields = [
    'resume' => 'Resume',
    'headline' => 'Professional Headline',
    'education_level' => 'Education Level',
    'experience_years' => 'Experience Years',
    'skills' => 'Skills'
];

$missing_fields = [];
foreach($profile_fields as $field => $label) {
    if(empty($jobseeker[$field])) {
        $missing_fields[] = $label;
    }
}

$profile_completion = 100 - (count($missing_fields) * 20);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Modern Sidebar */
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
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed .sidebar-header h3,
        .sidebar.collapsed .sidebar-menu a span {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-menu a {
            padding: 14px;
            justify-content: center;
        }
        
        .sidebar.collapsed .sidebar-menu a i {
            margin: 0;
        }
        
        .sidebar-toggle {
            position: fixed;
            top: 20px;
            left: 260px; /* Position it just outside the expanded sidebar */
            width: 32px;
            height: 32px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
            border: none;
            color: #1a3b5d;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-toggle {
            left: 80px; /* Adjust position when sidebar is collapsed */
            transform: rotate(180deg);
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
            font-size: 1.2rem;
            font-weight: 600;
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
        
        /* Modern Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 25px 30px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            color: white;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-name {
            font-size: 1.1rem;
            color: #ffffff;
            font-weight: 500;
        }
        
        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .company-name {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.85);
        }

        .top-bar h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #ffffff;
            font-weight: 600;
        }

        .specialist-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.95rem;
            margin-left: 12px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .specialist-badge .icon {
            margin-right: 8px;
            font-size: 1.1rem;
        }

        .specialist-badge .years {
            color: #ffd700;
            font-weight: 600;
            margin: 0 4px;
        }
        
        /* Floating Chatbot */
        .chatbot-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }
        
        .chatbot-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }
        
        .chatbot-icon:hover {
            transform: scale(1.1);
        }
        
        .chatbot-icon svg {
            width: 28px;
            height: 28px;
            color: white;
        }
        
        .chatbot-box {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            display: none;
            flex-direction: column;
        }
        
        .chatbot-header {
            padding: 15px 20px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chatbot-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        #close-chat {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        
        .chatbot-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .chatbot-input {
            padding: 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }
        
        .chatbot-input input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 20px;
            outline: none;
        }
        
        .chatbot-input button {
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .chatbot-input button:hover {
            opacity: 0.9;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        
        .user-message {
            align-items: flex-end;
        }
        
        .bot-message {
            align-items: flex-start;
        }
        
        .message-content {
            padding: 10px 15px;
            border-radius: 15px;
            max-width: 80%;
        }
        
        .user-message .message-content {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .bot-message .message-content {
            background: #f5f5f5;
            color: #333;
        }
        
        /* Jobseeker Profile Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        
        .jobseeker-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .profile-container {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 20px;
        }
        
        .profile-main {
            margin-bottom: 20px;
        }
        
        .profile-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 20px;
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
        
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: #0056b3;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #004494;
            color: white;
            text-decoration: none;
        }
        
        .resume-preview {
            margin-bottom: 20px;
        }
        
        .resume-file {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .resume-icon {
            font-size: 1.5rem;
            margin-right: 15px;
            color: #0056b3;
        }
        
        .resume-info {
            flex: 1;
        }
        
        .resume-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .resume-date {
            font-size: 0.85rem;
            color: #666;
        }
        
        .resume-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #0056b3;
            color: #0056b3;
        }
        
        .btn-outline:hover {
            background-color: #f0f5ff;
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.85rem;
        }
        
        .profile-summary {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #e3f2fd;
            color: #1976d2;
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .profile-name {
            font-size: 1.2rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .profile-email {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
        
        .progress-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress {
            height: 100%;
            background-color: #0056b3;
            border-radius: 4px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #666;
        }
        
        .missing-fields {
            margin-top: 15px;
            font-size: 0.85rem;
            color: #666;
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
    </style>
</head>
<body>
    <div class="jobseeker-container">
        <div class="sidebar">
            <button class="sidebar-toggle">❮</button>
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <?php echo strtoupper(substr($jobseeker['first_name'], 0, 1) . substr($jobseeker['last_name'], 0, 1)); ?>
                </div>
                <h3>ShaSha</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/dashboard.php"><i>📊</i><span>Dashboard</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php" class="active"><i>👤</i><span>My Profile</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php"><i>🔍</i><span>Search Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php"><i>💾</i><span>Saved Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php"><i>📝</i><span>My Applications</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i><span>Logout</span></a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="top-bar">
                <h1>My Profile</h1>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?>
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
                <div class="profile-main">
                    <div class="profile-card">
                        <div class="profile-header">
                            <h3>Resume</h3>
                        </div>
                        <div class="profile-content">
                            <?php if(!empty($jobseeker['resume'])): ?>
                                <div class="resume-preview">
                                    <div class="resume-file">
                                        <div class="resume-icon">📄</div>
                                        <div class="resume-info">
                                            <div class="resume-name">
                                                <?php
                                                $filename = basename($jobseeker['resume']);
                                                $parts = explode('_', $filename, 3);
                                                echo htmlspecialchars($parts[2] ?? $filename);
                                                ?>
                                            </div>
                                            <div class="resume-date">
                                                Uploaded: <?php echo date('F d, Y', filectime($_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/' . $jobseeker['resume'])); ?>
                                            </div>
                                        </div>
                                        <div class="resume-actions">
                                            <a href="<?php echo SITE_URL . '/' . $jobseeker['resume']; ?>" class="btn btn-outline btn-sm" target="_blank">View</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post" action="" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="resume">Upload Resume</label>
                                    <input type="file" id="resume" name="resume">
                                    <div class="help-text">Accepted formats: PDF, DOC, DOCX. Max file size: 5MB.</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Upload Resume</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="profile-card">
                        <div class="profile-header">
                            <h3>Personal Information</h3>
                        </div>
                        <div class="profile-content">
                            <form method="post" action="">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="first_name" class="required">First Name</label>
                                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($jobseeker['first_name']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="last_name" class="required">Last Name</label>
                                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($jobseeker['last_name']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" value="<?php echo htmlspecialchars($jobseeker['email']); ?>" disabled>
                                        <div class="help-text">Email cannot be changed. Contact support if you need to update your email.</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="phone">Phone</label>
                                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($jobseeker['phone']); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="headline">Professional Headline</label>
                                    <input type="text" id="headline" name="headline" value="<?php echo htmlspecialchars($jobseeker['headline']); ?>" placeholder="e.g. Senior Software Developer with 5+ years of experience">
                                    <div class="help-text">A brief professional summary that appears at the top of your profile.</div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="education_level">Education Level</label>
                                        <select id="education_level" name="education_level">
                                            <option value="">Select education level</option>
                                            <?php foreach($education_levels as $level): ?>
                                                <option value="<?php echo $level; ?>" <?php if($jobseeker['education_level'] == $level) echo 'selected'; ?>>
                                                    <?php echo $level; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="experience_years">Years of Experience</label>
                                        <input type="number" id="experience_years" name="experience_years" value="<?php echo htmlspecialchars($jobseeker['experience_years']); ?>" min="0" max="50">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="skills">Skills</label>
                                    <textarea id="skills" name="skills" placeholder="e.g. JavaScript, PHP, Project Management, Communication"><?php echo htmlspecialchars($jobseeker['skills']); ?></textarea>
                                    <div class="help-text">Enter your skills separated by commas.</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="address">Address</label>
                                    <textarea id="address" name="address" placeholder="Your address"><?php echo htmlspecialchars($jobseeker['address']); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="profile-sidebar">
                    <div class="profile-summary">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($jobseeker['first_name'], 0, 1) . substr($jobseeker['last_name'], 0, 1)); ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?></div>
                        <div class="profile-email"><?php echo htmlspecialchars($jobseeker['email']); ?></div>
                        
                        <div class="progress-bar">
                            <div class="progress" style="width: <?php echo $profile_completion; ?>%;"></div>
                        </div>
                        <div class="progress-label">
                            <span>Profile Completion</span>
                            <span><?php echo $profile_completion; ?>%</span>
                        </div>
                        
                        <?php if(!empty($missing_fields)): ?>
                            <div class="missing-fields">
                                <strong>Missing:</strong> <?php echo implode(', ', $missing_fields); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-tips profile-card">
                        <div class="profile-header">
                            <h3>Profile Tips</h3>
                        </div>
                        <div class="profile-content">
                            <ul style="padding-left: 20px;">
                                <li>Add a clear professional headline</li>
                                <li>List your skills to help employers find you</li>
                                <li>Upload your latest resume</li>
                                <li>Keep your contact information up to date</li>
                                <li>Be specific about your education and experience</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Chatbot Container -->
    <div class="chatbot-container">
        <div class="chatbot-icon" id="chatbot-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar Toggle
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            
            // Check localStorage for sidebar state
            if(localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
            }
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                // Save state to localStorage
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });

            // Add confirmation for logout
            const logoutLink = document.querySelector('a[href*="logout.php"]');
            if(logoutLink) {
                logoutLink.addEventListener('click', function(e) {
                    if(!confirm('Are you sure you want to logout?')) {
                        e.preventDefault();
                    }
                });
            }

            // Chatbot icon click handler
            const chatbotIcon = document.getElementById('chatbot-icon');
            chatbotIcon.addEventListener('click', function() {
                // You can implement your chatbot logic here
                alert('Chat functionality coming soon!');
            });
        });
    </script>
</body>
</html>