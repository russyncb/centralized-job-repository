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

// Get jobseeker profile
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
        /* Jobseeker Profile Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        
        .jobseeker-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            background-color: #343a40;
            color: white;
            padding: 20px 0;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #495057;
            margin-bottom: 20px;
        }
        
        .sidebar-header h3 {
            color: white;
            font-size: 1.3rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: #ced4da;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #495057;
            color: white;
            border-left-color: #0056b3;
        }
        
        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
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
        <?php include __DIR__ . '/jobseeker-sidebar.php'; ?>
        <div class="main-content">
            <div class="top-bar">
                <h1>My Profile</h1>
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
        <div class="chatbot-box" id="chatbot-box">
            <div class="chatbot-header">
                <h3>ShaSha Assistant</h3>
                <button id="close-chat">×</button>
            </div>
            <div class="chatbot-messages" id="chatbot-messages">
                <div class="message bot-message">
                    <div class="message-content">
                        Hi there! I'm ShaSha's assistant. How can I help you today?
                    </div>
                </div>
            </div>
            <div class="chatbot-input">
                <input type="text" id="user-input" placeholder="Type your message here...">
                <button id="send-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    <script>
        // Chatbot logic (same as home page)
        document.addEventListener('DOMContentLoaded', function() {
            const chatbotIcon = document.getElementById('chatbot-icon');
            const chatbotBox = document.getElementById('chatbot-box');
            const closeChat = document.getElementById('close-chat');
            const userInput = document.getElementById('user-input');
            const sendMessage = document.getElementById('send-message');
            const chatMessages = document.getElementById('chatbot-messages');
            chatbotIcon.addEventListener('click', function() {
                chatbotBox.style.display = 'flex';
                userInput.focus();
            });
            closeChat.addEventListener('click', function() {
                chatbotBox.style.display = 'none';
            });
            function sendUserMessage() {
                const message = userInput.value.trim();
                if (message) {
                    addMessage(message, 'user');
                    userInput.value = '';
                    setTimeout(() => {
                        const response = getBotResponse(message);
                        addMessage(response, 'bot');
                    }, 600);
                }
            }
            sendMessage.addEventListener('click', sendUserMessage);
            userInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendUserMessage();
                }
            });
            function addMessage(text, sender) {
                const messageDiv = document.createElement('div');
                messageDiv.classList.add('message');
                messageDiv.classList.add(sender + '-message');
                const contentDiv = document.createElement('div');
                contentDiv.classList.add('message-content');
                contentDiv.textContent = text;
                messageDiv.appendChild(contentDiv);
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            function getBotResponse(message) {
                message = message.toLowerCase();
                if (message.includes('hello') || message.includes('hi') || message.includes('hey')) {
                    return "Hello! How can I help you with ShaSha today?";
                } else if (message.includes('profile') || message.includes('update')) {
                    return "To update your profile, click 'My Profile' in the sidebar.";
                } else if (message.includes('job') && (message.includes('find') || message.includes('search') || message.includes('look'))) {
                    return "To search for jobs, click 'Search Jobs' in the sidebar. You can filter by category, location, and more.";
                } else if (message.includes('application') || message.includes('applied')) {
                    return "To view your job applications, click 'My Applications' in the sidebar.";
                } else if (message.includes('logout')) {
                    return "To logout, click the 'Logout' button in the sidebar. You'll be asked to confirm before logging out.";
                } else if (message.includes('thank')) {
                    return "You're welcome! Is there anything else I can help you with?";
                } else {
                    return "I'm here to help! For specific questions, try using the sidebar or contact support if you need more assistance.";
                }
            }
        });
    </script>
</body>
</html>