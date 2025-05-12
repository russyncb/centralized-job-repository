<?php
// Set page title
$page_title = 'Apply for Job';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has jobseeker role
if(!has_role('jobseeker')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Check if job ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    redirect(SITE_URL . '/views/jobseeker/search-jobs.php', 'Job ID is required.', 'error');
}

$job_id = $_GET['id'];

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

// Check if already applied
$query = "SELECT * FROM applications WHERE job_id = ? AND jobseeker_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $job_id);
$stmt->bindParam(2, $jobseeker_id);
$stmt->execute();

if($stmt->rowCount() > 0) {
    redirect(SITE_URL . '/views/jobseeker/view-job.php?id=' . $job_id, 'You have already applied for this job.', 'error');
}

// Get job details
$query = "SELECT j.*, e.company_name FROM jobs j
         JOIN employer_profiles e ON j.employer_id = e.employer_id
         WHERE j.job_id = ? AND j.status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $job_id);
$stmt->execute();
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$job) {
    redirect(SITE_URL . '/views/jobseeker/search-jobs.php', 'Job not found or no longer active.', 'error');
}

// Check if application deadline has passed
$deadline_passed = false;
if(!empty($job['application_deadline'])) {
    $deadline = new DateTime($job['application_deadline']);
    $today = new DateTime();
    $deadline_passed = $today > $deadline;
    
    if($deadline_passed) {
        redirect(SITE_URL . '/views/jobseeker/view-job.php?id=' . $job_id, 'The application deadline for this job has passed.', 'error');
    }
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $cover_letter = trim($_POST['cover_letter']);
    
    // Handle resume upload
    $resume_path = $jobseeker['resume']; // Default to profile resume
    
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
                // Set resume path
                $resume_path = 'uploads/resumes/' . $filename;
            } else {
                $error = "Error uploading resume.";
            }
        }
    }
    
    // Insert application
    if(!isset($error)) {
        $query = "INSERT INTO applications (job_id, jobseeker_id, cover_letter, resume, status)
                 VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $job_id);
        $stmt->bindParam(2, $jobseeker_id);
        $stmt->bindParam(3, $cover_letter);
        $stmt->bindParam(4, $resume_path);
        
        if($stmt->execute()) {
            redirect(SITE_URL . '/views/jobseeker/my-applications.php', 'Your application has been submitted successfully.', 'success');
        } else {
            $error = "Error submitting application. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Apply Job Styles */
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
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: #fff;
            padding: 0;
            box-shadow: 2px 0 8px rgba(0,0,0,0.07);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: all 0.3s ease;
            position: relative;
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
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            font-size: 0.95rem;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border-left-color: #fff;
        }
        
        .sidebar-menu a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .sidebar-toggle {
            position: fixed;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            background: #1557b0;
            color: white;
            border: none;
            width: 24px;
            height: 40px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            padding: 0;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            background: #1a3b5d;
        }
        
        .sidebar.collapsed {
            margin-left: -250px;
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 0;
        }
        
        .sidebar.collapsed ~ .sidebar-toggle {
            left: 0;
            transform: translateY(-50%) rotate(180deg);
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
        
        .back-link {
            color: #666;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .back-link:hover {
            color: #333;
            text-decoration: none;
        }
        
        .back-icon {
            margin-right: 5px;
        }
        
        .application-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .job-summary {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .job-title {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .company-name {
            color: #666;
            margin-bottom: 15px;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .application-form {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
        }
        
        .form-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .form-header h2 {
            margin: 0;
            font-size: 1.2rem;
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
        
        .form-group textarea,
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        
        .form-group textarea {
            height: 200px;
            resize: vertical;
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
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #6c757d;
            color: #6c757d;
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
            text-decoration: none;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .resume-section {
            margin-bottom: 20px;
        }
        
        .current-resume {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
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
        
        .resume-option {
            margin-bottom: 10px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
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
                <h3>ShaSha Jobseeker</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/dashboard.php"><i>📊</i><span>Dashboard</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php"><i>👤</i><span>My Profile</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php"><i>🔍</i><span>Search Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php"><i>💾</i><span>Saved Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php" class="active"><i>📝</i><span>My Applications</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i><span>Logout</span></a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $job_id; ?>" class="back-link">
                    <span class="back-icon">←</span> Back to Job
                </a>
            </div>
            
            <div class="application-container">
                <?php if(isset($error)): ?>
                    <div class="message error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="job-summary">
                    <h1 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h1>
                    <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></div>
                    <div class="job-meta">
                        <span><i>📍</i> <?php echo htmlspecialchars($job['location']); ?></span>
                        <span><i>💼</i> <?php echo ucfirst($job['job_type']); ?></span>
                        <?php if(!empty($job['application_deadline'])): ?>
                            <span><i>⏱️</i> Deadline: <?php echo date('M d, Y', strtotime($job['application_deadline'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="application-form">
                    <div class="form-header">
                        <h2>Submit Your Application</h2>
                    </div>
                    
                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="resume-section">
                            <?php if(!empty($jobseeker['resume'])): ?>
                                <div class="current-resume">
                                    <div class="resume-icon">📄</div>
                                    <div class="resume-info">
                                        <div class="resume-name">
                                            <?php
                                            $filename = basename($jobseeker['resume']);
                                            $parts = explode('_', $filename, 3);
                                            echo htmlspecialchars($parts[2] ?? $filename);
                                            ?>
                                        </div>
                                        <div>Your current resume will be used by default</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="resume">Upload Resume (Optional)</label>
                                <input type="file" id="resume" name="resume">
                                <div class="help-text">
                                    <?php if(!empty($jobseeker['resume'])): ?>
                                        Upload a new resume or leave this empty to use your profile resume.
                                    <?php else: ?>
                                        Upload your resume. Accepted formats: PDF, DOC, DOCX. Max file size: 5MB.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cover_letter">Cover Letter</label>
                            <textarea id="cover_letter" name="cover_letter" placeholder="Explain why you're a good fit for this position..."><?php echo isset($_POST['cover_letter']) ? htmlspecialchars($_POST['cover_letter']) : ''; ?></textarea>
                            <div class="help-text">Introduce yourself and explain why you're a good fit for this position.</div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $job_id; ?>" class="btn btn-outline">Cancel</a>
                            <button type="submit" class="btn btn-primary">Submit Application</button>
                        </div>
                    </form>
                </div>
            </div>
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
        });
    </script>
</body>
</html>