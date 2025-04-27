<?php
// Set page title
$page_title = 'View Application';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has employer role
if(!has_role('employer')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Check if application ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    redirect(SITE_URL . '/views/employer/applications.php', 'Application ID is required.', 'error');
}

$application_id = $_GET['id'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get employer ID
$query = "SELECT employer_id FROM employer_profiles WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $_SESSION['user_id']);
$stmt->execute();
$employer = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$employer) {
    redirect(SITE_URL . '/views/auth/logout.php', 'Employer profile not found.', 'error');
}

$employer_id = $employer['employer_id'];

// Get application details
$query = "SELECT a.*, j.job_id, j.title as job_title, j.location as job_location, j.job_type, j.employer_id,
         js.jobseeker_id, js.resume as profile_resume, js.headline, js.education_level, js.experience_years, js.skills,
         u.first_name, u.last_name, u.email, u.phone, u.created_at as user_created_at
         FROM applications a
         JOIN jobs j ON a.job_id = j.job_id
         JOIN jobseeker_profiles js ON a.jobseeker_id = js.jobseeker_id
         JOIN users u ON js.user_id = u.user_id
         WHERE a.application_id = ? AND j.employer_id = ?";
         
$stmt = $db->prepare($query);
$stmt->bindParam(1, $application_id);
$stmt->bindParam(2, $employer_id);
$stmt->execute();

$application = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$application) {
    redirect(SITE_URL . '/views/employer/applications.php', 'Application not found or you do not have permission to view it.', 'error');
}

// Process application status update
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $status = $_POST['status'];
    
    // Update application status
    $query = "UPDATE applications SET status = ? WHERE application_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $status);
    $stmt->bindParam(2, $application_id);
    
    if($stmt->execute()) {
        $success = "Application status updated to " . ucfirst($status) . ".";
        
        // Refresh application data
        $query = "SELECT a.*, j.job_id, j.title as job_title, j.location as job_location, j.job_type, j.employer_id,
                 js.jobseeker_id, js.resume as profile_resume, js.headline, js.education_level, js.experience_years, js.skills,
                 u.first_name, u.last_name, u.email, u.phone, u.created_at as user_created_at
                 FROM applications a
                 JOIN jobs j ON a.job_id = j.job_id
                 JOIN jobseeker_profiles js ON a.jobseeker_id = js.jobseeker_id
                 JOIN users u ON js.user_id = u.user_id
                 WHERE a.application_id = ? AND j.employer_id = ?";
                 
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $application_id);
        $stmt->bindParam(2, $employer_id);
        $stmt->execute();
        
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = "Error updating application status.";
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
        /* View Application Styles */
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
            display: flex;
            gap: 20px;
        }
        
        .application-main {
            flex: 2;
        }
        
        .application-sidebar {
            flex: 1;
        }
        
        .application-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .card-content {
            padding: 20px;
        }
        
        .applicant-name {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .applicant-headline {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        
        .applicant-details {
            margin-bottom: 20px;
        }
        
        .detail-item {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-weight: 500;
            color: #555;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #333;
        }
        
        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        
        .skill-tag {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .resume-section {
            margin-bottom: 20px;
        }
        
        .cover-letter {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #0056b3;
            margin-bottom: 20px;
        }
        
        .job-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .job-title {
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .job-meta {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 15px;
        }
        
        .status-form {
            margin-bottom: 20px;
        }
        
        .status-form select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
            margin-bottom: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .status-pending {
            background-color: #e0f7fa;
            color: #0097a7;
        }
        
        .status-shortlisted {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .status-rejected {
            background-color: #fbe9e7;
            color: #d32f2f;
        }
        
        .status-hired {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
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
        }
        
        .btn-download {
            background-color: #e3f2fd;
            color: #1976d2;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-download:hover {
            background-color: #bbdefb;
            color: #1976d2;
            text-decoration: none;
        }
        
        .btn-icon {
            margin-right: 8px;
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
        
        .application-date {
            text-align: right;
            font-size: 0.9rem;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="employer-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>ShaSha Employer</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="<?php echo SITE_URL; ?>/views/employer/dashboard.php"><i>📊</i> Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/profile.php"><i>👤</i> Company Profile</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/post-job.php"><i>📝</i> Post a Job</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php"><i>💼</i> Manage Jobs</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/applications.php" class="active"><i>📋</i> Applications</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <a href="<?php echo SITE_URL; ?>/views/employer/applications.php" class="back-link">
                    <span class="back-icon">←</span> Back to Applications
                </a>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if(isset($success)): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="application-container">
                <div class="application-main">
                    <div class="application-card">
                        <div class="card-header">
                            <h3>Applicant Information</h3>
                        </div>
                        <div class="card-content">
                            <h2 class="applicant-name"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></h2>
                            
                            <?php if(!empty($application['headline'])): ?>
                                <div class="applicant-headline"><?php echo htmlspecialchars($application['headline']); ?></div>
                            <?php endif; ?>
                            
                            <div class="applicant-details">
                                <div class="detail-item">
                                    <div class="detail-label">Email</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($application['email']); ?></div>
                                </div>
                                
                                <?php if(!empty($application['phone'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Phone</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($application['phone']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($application['education_level'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Education Level</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($application['education_level']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($application['experience_years'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Experience</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($application['experience_years']); ?> years</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($application['skills'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Skills</div>
                                        <div class="skills-list">
                                            <?php
                                            $skills = explode(',', $application['skills']);
                                            foreach($skills as $skill):
                                                if(!empty(trim($skill))):
                                            ?>
                                                <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                            <?php
                                                endif;
                                            endforeach;
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if(!empty($application['profile_resume']) || !empty($application['resume'])): ?>
                                <div class="resume-section">
                                    <div class="detail-label">Resume</div>
                                    <?php if(!empty($application['resume'])): ?>
                                        <a href="<?php echo SITE_URL . '/' . $application['resume']; ?>" class="btn btn-download" target="_blank">
                                            <span class="btn-icon">📄</span> Download Submitted Resume
                                        </a>
                                    <?php elseif(!empty($application['profile_resume'])): ?>
                                        <a href="<?php echo SITE_URL . '/' . $application['profile_resume']; ?>" class="btn btn-download" target="_blank">
                                            <span class="btn-icon">📄</span> Download Profile Resume
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($application['cover_letter'])): ?>
                                <div class="detail-label">Cover Letter</div>
                                <div class="cover-letter">
                                    <?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="application-date">
                                Applied on <?php echo date('F d, Y \a\t h:i A', strtotime($application['applied_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="application-sidebar">
                    <div class="application-card">
                        <div class="card-header">
                            <h3>Application Status</h3>
                        </div>
                        <div class="card-content">
                            <span class="status-badge status-<?php echo $application['status']; ?>">
                                <?php echo ucfirst($application['status']); ?>
                            </span>
                            
                            <form method="post" action="" class="status-form">
                                <label for="status">Update Status</label>
                                <select id="status" name="status">
                                    <option value="pending" <?php if($application['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="shortlisted" <?php if($application['status'] == 'shortlisted') echo 'selected'; ?>>Shortlisted</option>
                                    <option value="rejected" <?php if($application['status'] == 'rejected') echo 'selected'; ?>>Rejected</option>
                                    <option value="hired" <?php if($application['status'] == 'hired') echo 'selected'; ?>>Hired</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Update Status</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="application-card">
                        <div class="card-header">
                            <h3>Job Information</h3>
                        </div>
                        <div class="card-content">
                            <div class="job-info">
                                <div class="job-title"><?php echo htmlspecialchars($application['job_title']); ?></div>
                                <div class="job-meta">
                                    <?php echo htmlspecialchars($application['job_location']); ?> • <?php echo ucfirst($application['job_type']); ?>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/views/employer/view-job.php?id=<?php echo $application['job_id']; ?>" class="btn btn-primary">View Job</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>