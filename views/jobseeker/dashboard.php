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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Jobseeker Dashboard Styles */
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
            margin-top: 20px;
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
    </style>
</head>
<body>
    <div class="jobseeker-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>ShaSha Jobseeker</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/dashboard.php" class="active"><i>📊</i> Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php"><i>👤</i> My Profile</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php"><i>🔍</i> Search Jobs</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php"><i>📋</i> My Applications</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php"><i>💾</i> Saved Jobs</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Jobseeker Dashboard</h1>
            </div>
            
            <?php if(!$profile_complete): ?>
                <div class="alert">
                    <p>Your profile is incomplete. Complete your profile to increase your chances of getting hired!</p>
                </div>
            <?php endif; ?>
            
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
                        
                        <div class="profile-completion">
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo $profile_completion; ?>%;"></div>
                            </div>
                            <div class="progress-label">
                                <span>Profile Completion</span>
                                <span><?php echo $profile_completion; ?>%</span>
                            </div>
                            
                            <?php if(!$profile_complete): ?>
                                <div>
                                    Missing: <?php echo implode(', ', $missing_fields); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="profile-actions">
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php" class="btn btn-primary">Update Profile</a>
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
</body>
</html>