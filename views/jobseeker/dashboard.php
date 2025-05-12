<?php
// Set page title
$page_title = 'Jobseeker Dashboard';

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

// Get total applications count
$query = "SELECT COUNT(*) as total FROM applications WHERE jobseeker_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $jobseeker_id);
$stmt->execute();
$total_applications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent applications
$query = "SELECT a.*, j.title as job_title, j.location as job_location, j.job_type,
         e.company_name, e.company_logo
         FROM applications a
         JOIN jobs j ON a.job_id = j.job_id
         JOIN employer_profiles e ON j.employer_id = e.employer_id
         WHERE a.jobseeker_id = ?
         ORDER BY a.applied_at DESC
         LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $jobseeker_id);
$stmt->execute();
$recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get saved jobs count
$query = "SELECT COUNT(*) as total FROM saved_jobs WHERE jobseeker_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $jobseeker_id);
$stmt->execute();
$saved_jobs_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recommended jobs
$query = "SELECT j.*, e.company_name, e.company_logo
         FROM jobs j
         JOIN employer_profiles e ON j.employer_id = e.employer_id
         WHERE j.status = 'active'
         ORDER BY j.posted_at DESC
         LIMIT 3";
$stmt = $db->prepare($query);
$stmt->execute();
$recommended_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if profile is complete
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

$profile_complete = empty($missing_fields);
$profile_completion = 100 - (count($missing_fields) * 20);

// Get total active jobs
$total_jobs = $db->query("SELECT COUNT(*) as total FROM jobs WHERE status = 'active'")->fetch(PDO::FETCH_ASSOC)['total'];
// Get new jobs this week
$new_jobs = $db->query("SELECT COUNT(*) as total FROM jobs WHERE status = 'active' AND posted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Sidebar Modernization */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: #fff;
            padding: 0;
            box-shadow: 2px 0 8px rgba(0,0,0,0.07);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: width 0.3s ease;
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
        
        .sidebar-footer {
            padding: 18px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 0.95rem;
            color: #bfc9d9;
            background: rgba(255,255,255,0.03);
        }
        /* Dashboard Job Opportunities */
        .dashboard-jobs {
            display: flex;
            gap: 24px;
            margin-bottom: 32px;
        }
        .dashboard-job-card {
            background: #fff;
            color: #1a3b5d;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            padding: 24px 32px;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-width: 180px;
        }
        .dashboard-job-card .icon {
            font-size: 2.1rem;
            margin-bottom: 10px;
        }
        .dashboard-job-card .count {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .dashboard-job-card .label {
            font-size: 1.05rem;
            color: #4a5568;
            margin-bottom: 10px;
        }
        .dashboard-job-card .btn {
            margin-top: auto;
        }
        .profile-complete-badge {
            display: inline-block;
            background: #e3fcec;
            color: #1a7f37;
            font-weight: 600;
            border-radius: 20px;
            padding: 6px 18px;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        /* Jobseeker Dashboard Styles */
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
        
        .grid-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .profile-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
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
            margin-right: 20px;
        }
        
        .profile-info h2 {
            margin: 0 0 5px;
            font-size: 1.5rem;
        }
        
        .profile-headline {
            color: #666;
            font-size: 1rem;
        }
        
        .profile-completion {
            display: none;
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
        
        .profile-actions {
            margin-top: 20px;
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
            text-decoration: none;
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
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            text-align: center;
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #0056b3;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .section-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 1.2rem;
            color: #333;
        }
        
        .section-title .icon {
            margin-right: 8px;
            color: #0056b3;
        }
        
        .application-list {
            margin-bottom: 20px;
        }
        
        .application-item {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .company-logo {
            width: 50px;
            height: 50px;
            background-color: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .company-logo img {
            max-width: 40px;
            max-height: 40px;
        }
        
        .application-details {
            flex: 1;
        }
        
        .job-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .company-name {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .application-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #666;
            font-size: 0.85rem;
        }
        
        .application-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-left: auto;
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
        
        .view-all {
            text-align: center;
            margin-top: 20px;
        }
        
        .job-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 15px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .job-header {
            display: flex;
            margin-bottom: 15px;
        }
        
        .job-info {
            flex: 1;
        }
        
        .job-title a {
            color: #0056b3;
            text-decoration: none;
        }
        
        .job-title a:hover {
            text-decoration: underline;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
            color: #666;
            font-size: 0.85rem;
        }
        
        .job-actions {
            margin-top: 15px;
        }
        
        .job-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .tag {
            background-color: #f0f5ff;
            color: #0056b3;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .no-items {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert {
            background-color: #fff8e1;
            border-left: 4px solid #f57c00;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert p {
            margin: 0;
        }
        
        /* Collapsible Sidebar */
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
        
        /* Sidebar Toggle Button - New Position */
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
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/dashboard.php" class="active"><i>📊</i><span>Dashboard</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php"><i>👤</i><span>My Profile</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php"><i>🔍</i><span>Search Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php"><i>💾</i><span>Saved Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php"><i>📝</i><span>My Applications</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i><span>Logout</span></a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="top-bar">
                <div class="welcome-section">
                    <h1>Jobseeker Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?></p>
                    <?php if(!empty($jobseeker['headline'])): ?>
                        <div class="headline"><?php echo htmlspecialchars($jobseeker['headline']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="action-buttons">
                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php" class="btn btn-outline">Update Resume</a>
                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="btn btn-primary">Browse Jobs</a>
                </div>
            </div>
            <div class="dashboard-jobs">
                <div class="dashboard-job-card">
                    <span class="icon">💼</span>
                    <span class="count"><?php echo $total_jobs; ?></span>
                    <span class="label">Active Jobs</span>
                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="btn btn-primary">Browse Jobs</a>
                </div>
                <div class="dashboard-job-card">
                    <span class="icon">🆕</span>
                    <span class="count"><?php echo $new_jobs; ?></span>
                    <span class="label">New This Week</span>
                </div>
            </div>
            <div class="grid-container">
                <div class="main-column">
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <?php echo strtoupper(substr($jobseeker['first_name'], 0, 1) . substr($jobseeker['last_name'], 0, 1)); ?>
                            </div>
                            <div class="profile-info">
                                <h2><?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?></h2>
                                <?php if(!empty($jobseeker['headline'])): ?>
                                    <div class="profile-headline"><?php echo htmlspecialchars($jobseeker['headline']); ?></div>
                                <?php else: ?>
                                    <div class="profile-headline">No headline set</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if($profile_complete): ?>
                            <div class="profile-complete-badge">Profile Complete ✔</div>
                        <?php else: ?>
                        <div class="profile-completion">
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo $profile_completion; ?>%;"></div>
                            </div>
                            <div class="progress-label">
                                <span>Profile Completion</span>
                                <span><?php echo $profile_completion; ?>%</span>
                            </div>
                            <div>
                                Missing: <?php echo implode(', ', $missing_fields); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="profile-actions">
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php" class="btn btn-outline">Update Profile</a>
                        </div>
                    </div>
                    
                    <h3 class="section-title"><span class="icon">📝</span> Recent Applications</h3>
                    <div class="application-list">
                        <?php if(count($recent_applications) > 0): ?>
                            <?php foreach($recent_applications as $application): ?>
                                <div class="application-item">
                                    <div class="company-logo">
                                        <?php if(!empty($application['company_logo'])): ?>
                                            <img src="<?php echo SITE_URL . '/' . $application['company_logo']; ?>" alt="Company Logo">
                                        <?php else: ?>
                                            <span><?php echo strtoupper(substr($application['company_name'], 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="application-details">
                                        <div class="job-title"><?php echo htmlspecialchars($application['job_title']); ?></div>
                                        <div class="company-name"><?php echo htmlspecialchars($application['company_name']); ?></div>
                                        <div class="application-meta">
                                            <span><?php echo htmlspecialchars($application['job_location']); ?></span>
                                            <span><?php echo ucfirst($application['job_type']); ?></span>
                                            <span>Applied: <?php echo date('M d, Y', strtotime($application['applied_at'])); ?></span>
                                        </div>
                                    </div>
                                    <span class="application-status status-<?php echo $application['status']; ?>">
                                        <?php echo ucfirst($application['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="view-all">
                                <a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php" class="btn btn-outline">View All Applications</a>
                            </div>
                        <?php else: ?>
                            <div class="no-items">
                                <p>You haven't applied to any jobs yet.</p>
                                <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="btn btn-primary">Search Jobs</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h3 class="section-title"><span class="icon">✨</span> Recommended Jobs</h3>
                    <div class="recommended-jobs">
                        <?php if(count($recommended_jobs) > 0): ?>
                            <?php foreach($recommended_jobs as $job): ?>
                                <div class="job-card">
                                    <div class="job-header">
                                        <div class="company-logo">
                                            <?php if(!empty($job['company_logo'])): ?>
                                                <img src="<?php echo SITE_URL . '/' . $job['company_logo']; ?>" alt="Company Logo">
                                            <?php else: ?>
                                                <span><?php echo strtoupper(substr($job['company_name'], 0, 1)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="job-info">
                                            <div class="job-title">
                                                <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $job['job_id']; ?>">
                                                    <?php echo htmlspecialchars($job['title']); ?>
                                                </a>
                                            </div>
                                            <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></div>
                                            <div class="job-meta">
                                                <span><?php echo htmlspecialchars($job['location']); ?></span>
                                                <span><?php echo ucfirst($job['job_type']); ?></span>
                                                <span>Posted: <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="job-actions">
                                        <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-primary">View Job</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="view-all">
                                <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="btn btn-outline">View All Jobs</a>
                            </div>
                        <?php else: ?>
                            <div class="no-items">
                                <p>No recommended jobs available at the moment.</p>
                                <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="btn btn-primary">Search Jobs</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="side-column">
                    <div class="stats-cards">
                        <div class="stat-card">
                            <div class="stat-icon">📝</div>
                            <div class="stat-number"><?php echo $total_applications; ?></div>
                            <div class="stat-label">Applications</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">💾</div>
                            <div class="stat-number"><?php echo $saved_jobs_count; ?></div>
                            <div class="stat-label">Saved Jobs</div>
                        </div>
                    </div>
                    
                    <div class="profile-card">
                        <h3 class="section-title"><span class="icon">🔍</span> Quick Actions</h3>
                        <div class="quick-actions">
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">Find Jobs</a>
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php" class="btn btn-outline" style="width: 100%; margin-bottom: 10px;">Update Resume</a>
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php" class="btn btn-outline" style="width: 100%;">View Saved Jobs</a>
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