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
    // If no profile exists, create one
    $query = "INSERT INTO jobseeker_profiles (user_id) VALUES (?)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $_SESSION['user_id']);
    $stmt->execute();
    
    // Fetch user data
    $query = "SELECT user_id, first_name, last_name, email, phone FROM users WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize jobseeker array with user data
    $jobseeker = array(
        'user_id' => $user['user_id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'headline' => '',
        'education_level' => '',
        'experience_years' => '',
        'skills' => '',
        'address' => '',
        'resume' => ''
    );
}

// Ensure all required keys exist
$required_keys = ['first_name', 'last_name', 'email', 'phone', 'headline', 'education_level', 'experience_years', 'skills', 'address', 'resume'];
foreach($required_keys as $key) {
    if(!isset($jobseeker[$key])) {
        $jobseeker[$key] = '';
    }
}

// Process other profile updates
if($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['resume'])) {
    // Get form data with proper checks
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $headline = isset($_POST['headline']) ? trim($_POST['headline']) : '';
    $education_level = isset($_POST['education_level']) ? trim($_POST['education_level']) : '';
    $experience_years = isset($_POST['experience_years']) ? trim($_POST['experience_years']) : '';
    $skills = isset($_POST['skills']) ? trim($_POST['skills']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    
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
                
                // Update local jobseeker array
                $jobseeker['first_name'] = $first_name;
                $jobseeker['last_name'] = $last_name;
                $jobseeker['phone'] = $phone;
                $jobseeker['headline'] = $headline;
                $jobseeker['education_level'] = $education_level;
                $jobseeker['experience_years'] = $experience_years;
                $jobseeker['skills'] = $skills;
                $jobseeker['address'] = $address;
            } else {
                $error = "Error updating jobseeker profile.";
            }
        } else {
            $error = "Error updating user information.";
        }
    }
}

// Process resume upload independently
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
        $filename = $jobseeker['user_id'] . '_' . time() . '_' . $_FILES['resume']['name'];
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
                $jobseeker['resume'] = $resume_path;
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
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/chatbot.css">
    <style>
        /* Modern Sidebar */
        .sidebar {
            position: relative;
            width: 250px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: #fff;
            padding: 0;
            box-shadow: 2px 0 8px rgba(0,0,0,0.07);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: width 0.3s ease;
            z-index: 100;
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
            position: absolute;
            top: 20px;
            right: -16px;
            width: 32px;
            height: 32px;
            background: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 101;
            border: none;
            color: #1a3b5d;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-toggle {
            transform: rotate(180deg);
            right: -16px;
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
        
        /* Modern Profile Page Styles */
        .profile-container {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 30px;
            margin-top: 20px;
            min-height: calc(100vh - 200px);  /* Account for top bar and padding */
        }
        
        .profile-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.03);
            overflow: hidden;
            margin-bottom: 25px;
            border: 1px solid rgba(147, 197, 253, 0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .profile-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 25px rgba(0,0,0,0.05);
        }
        
        .profile-header {
            padding: 25px 30px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            background: linear-gradient(135deg, #EBF3FE 0%, #F5F9FF 50%, #EBF3FE 100%);
            position: relative;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 50%, #93c5fd 100%);
        }
        
        .profile-header h3 {
            margin: 0;
            font-size: 1.3rem;
            color: #1e293b;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .profile-header h3::before {
            content: '📄';
            font-size: 1.4rem;
        }
        
        .profile-content {
            padding: 30px;
        }
        
        /* Form Styling */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: #334155;
            font-size: 0.95rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f8fafc;
            color: #334155;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.1);
            background: #ffffff;
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
            line-height: 1.5;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 6px;
        }
        
        /* Resume Section */
        .resume-file {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }
        
        .resume-file:hover {
            border-color: #60a5fa;
            box-shadow: 0 4px 12px rgba(96, 165, 250, 0.1);
        }
        
        .resume-icon {
            font-size: 2rem;
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        
        .resume-info {
            flex: 1;
        }
        
        .resume-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
            font-size: 1.05rem;
        }
        
        .resume-date {
            font-size: 0.85rem;
            color: #64748b;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #3b82f6;
            color: #3b82f6;
        }
        
        .btn-outline:hover {
            background: rgba(59, 130, 246, 0.05);
            border-color: #2563eb;
            color: #2563eb;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.85rem;
        }
        
        /* Profile Summary Card */
        .profile-summary {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 16px;
            padding: 30px;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .profile-summary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 50%, #93c5fd 100%);
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            border: 4px solid rgba(255,255,255,0.1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .profile-name {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: white;
        }
        
        .profile-email {
            color: rgba(255,255,255,0.8);
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        
        .progress-bar {
            height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 100%);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .missing-fields {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 15px;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.9);
            margin-top: 20px;
        }
        
        .missing-fields strong {
            color: #93c5fd;
            display: block;
            margin-bottom: 5px;
        }
        
        /* Profile Tips Card */
        .profile-tips {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }
        
        .profile-tips .profile-header h3::before {
            content: '💡';
        }
        
        .profile-tips ul {
            padding-left: 20px;
            margin: 0;
        }
        
        .profile-tips li {
            color: #334155;
            margin-bottom: 12px;
            font-size: 0.95rem;
            position: relative;
        }
        
        .profile-tips li::before {
            content: '•';
            color: #3b82f6;
            font-size: 1.5rem;
            position: absolute;
            left: -20px;
            top: -8px;
        }
        
        /* Messages */
        .message {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .success::before {
            content: '✓';
            background: #22c55e;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .error::before {
            content: '!';
            background: #ef4444;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* Form Row Improvements */
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }
        
        /* Required Field Indicator */
        .required::after {
            content: '*';
            color: #ef4444;
            margin-left: 4px;
        }
        
        /* File Input Styling */
        input[type="file"] {
            background: #f8fafc;
            padding: 20px;
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        input[type="file"]:hover {
            border-color: #60a5fa;
            background: #f1f5f9;
        }
        
        /* Disabled Input Styling */
        input:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
            color: #64748b;
            border-color: #e2e8f0;
        }
        
        /* Basic Layout Fixes */
        body {
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }

        .jobseeker-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            background: #f8fafc;
            overflow-y: auto;
            min-height: 100vh;
            width: calc(100% - 250px);  /* Account for sidebar width */
        }

        .sidebar.collapsed + .main-content {
            width: calc(100% - 70px);  /* Adjust when sidebar is collapsed */
        }

        /* Character Counter Styles */
        .character-counter {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 5px;
            display: flex;
            justify-content: flex-end;
        }

        .character-counter.near-limit {
            color: #f59e0b;
        }

        .character-counter.at-limit {
            color: #ef4444;
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
                                    <input type="text" id="headline" name="headline" 
                                           value="<?php echo htmlspecialchars($jobseeker['headline']); ?>" 
                                           placeholder="e.g. Senior Software Developer with 5+ years of experience"
                                           maxlength="100"
                                           oninput="updateCharacterCount(this)">
                                    <div class="character-counter" id="headlineCounter">0/100 characters</div>
                                    <div class="help-text">A brief professional summary (max 100 characters). This will appear on your profile and dashboard.</div>
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
            const mainContent = document.querySelector('.main-content');
            
            // Check localStorage for sidebar state
            if(localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                mainContent.style.width = 'calc(100% - 70px)';
            }
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                // Adjust main content width
                mainContent.style.width = sidebar.classList.contains('collapsed') ? 'calc(100% - 70px)' : 'calc(100% - 250px)';
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

            function updateCharacterCount(input) {
                const counter = document.getElementById('headlineCounter');
                const maxLength = input.maxLength;
                const currentLength = input.value.length;
                counter.textContent = `${currentLength}/${maxLength} characters`;
                
                // Update counter color based on remaining characters
                if (currentLength >= maxLength) {
                    counter.className = 'character-counter at-limit';
                } else if (currentLength >= maxLength * 0.8) {
                    counter.className = 'character-counter near-limit';
                } else {
                    counter.className = 'character-counter';
                }
            }

            // Initialize character counter on page load
            const headlineInput = document.getElementById('headline');
            if (headlineInput) {
                updateCharacterCount(headlineInput);
            }
        });
    </script>
    <script src="<?php echo SITE_URL; ?>/assets/js/chatbot.js"></script>
</body>
</html>