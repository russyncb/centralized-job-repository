<?php
// Set page title
$page_title = 'Employer Dashboard';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has employer role
if(!has_role('employer')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get employer ID
$query = "SELECT employer_id, company_name, industry, location, verified 
          FROM employer_profiles 
          WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $_SESSION['user_id']);
$stmt->execute();
$employer = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$employer) {
    redirect(SITE_URL . '/views/auth/logout.php', 'Employer profile not found.', 'error');
}

$employer_id = $employer['employer_id'];

// Check if employer is verified
$is_verified = $employer['verified'] == 1;

// Get counts for dashboard
$query_active_jobs = "SELECT COUNT(*) as count FROM jobs WHERE employer_id = ? AND status = 'active'";
$query_all_jobs = "SELECT COUNT(*) as count FROM jobs WHERE employer_id = ?";
$query_applications = "SELECT COUNT(*) as count FROM applications a 
                      JOIN jobs j ON a.job_id = j.job_id 
                      WHERE j.employer_id = ?";
$query_recent_applications = "SELECT COUNT(*) as count FROM applications a 
                             JOIN jobs j ON a.job_id = j.job_id 
                             WHERE j.employer_id = ? AND a.applied_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

$stmt_active_jobs = $db->prepare($query_active_jobs);
$stmt_all_jobs = $db->prepare($query_all_jobs);
$stmt_applications = $db->prepare($query_applications);
$stmt_recent_applications = $db->prepare($query_recent_applications);

$stmt_active_jobs->bindParam(1, $employer_id);
$stmt_all_jobs->bindParam(1, $employer_id);
$stmt_applications->bindParam(1, $employer_id);
$stmt_recent_applications->bindParam(1, $employer_id);

$stmt_active_jobs->execute();
$stmt_all_jobs->execute();
$stmt_applications->execute();
$stmt_recent_applications->execute();

$active_jobs_count = $stmt_active_jobs->fetch(PDO::FETCH_ASSOC)['count'];
$all_jobs_count = $stmt_all_jobs->fetch(PDO::FETCH_ASSOC)['count'];
$applications_count = $stmt_applications->fetch(PDO::FETCH_ASSOC)['count'];
$recent_applications_count = $stmt_recent_applications->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent jobs
$query_recent_jobs = "SELECT job_id, title, location, job_type, category, status, posted_at, 
                     (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as applications_count
                     FROM jobs j 
                     WHERE employer_id = ? 
                     ORDER BY posted_at DESC 
                     LIMIT 5";

$stmt_recent_jobs = $db->prepare($query_recent_jobs);
$stmt_recent_jobs->bindParam(1, $employer_id);
$stmt_recent_jobs->execute();

$recent_jobs = $stmt_recent_jobs->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Employer Dashboard Styles */
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
        
        .user-info {
            display: flex;
            align-items: center;
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
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
        }
        
        .jobs-icon {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .applications-icon {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .recent-icon {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .stat-info h4 {
            margin: 0 0 5px;
            font-size: 1rem;
            color: #666;
        }
        
        .stat-info h3 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            padding: 12px 20px;
            background-color: #0056b3;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-action:hover {
            background-color: #004494;
        }
        
        .btn-action .icon {
            margin-right: 8px;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .recent-jobs {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .recent-jobs-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .recent-jobs-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .recent-jobs-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .job-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .job-item:last-child {
            border-bottom: none;
        }
        
        .job-details h4 {
            margin: 0 0 5px;
            font-size: 1.1rem;
        }
        
        .job-meta {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .job-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            text-align: center;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .status-closed {
            background-color: #fbe9e7;
            color: #d32f2f;
        }
        
        .status-draft {
            background-color: #e0f7fa;
            color: #0097a7;
        }
        
        .status-archived {
            background-color: #f5f5f5;
            color: #757575;
        }
        
        .job-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.85rem;
            border-radius: 4px;
            text-decoration: none;
        }
        
        .btn-blue {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-blue:hover {
            background-color: #bbdefb;
        }
        
        .notification-card {
            background-color: #fff8e1;
            border-left: 4px solid #f57c00;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
        }
        
        .notification-icon {
            font-size: 1.5rem;
            color: #f57c00;
            margin-right: 15px;
        }
        
        .notification-message {
            flex: 1;
        }
        
        .notification-message p {
            margin: 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #666;
        }
        
        .empty-state p {
            margin-bottom: 0;
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
                <li><a href="<?php echo SITE_URL; ?>/views/employer/dashboard.php" class="active"><i>📊</i> Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/profile.php"><i>👤</i> Company Profile</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/post-job.php"><i>📝</i> Post a Job</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php"><i>💼</i> Manage Jobs</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/applications.php"><i>📋</i> Applications</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Employer Dashboard</h1>
                <div class="user-info">
                    <span><?php echo $employer['company_name']; ?></span>
                    <?php if($is_verified): ?>
                        <span class="verification-badge">
                            <span class="icon">✓</span> Verified
                        </span>
                    <?php else: ?>
                        <span class="verification-badge pending-verification">
                            <span class="icon">⏱</span> Pending Verification
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if(!$is_verified): ?>
                <div class="notification-card">
                    <div class="notification-icon">ℹ️</div>
                    <div class="notification-message">
                        <p>Your employer account is pending verification by our admin team. Some features may be limited until your account is verified.</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon jobs-icon">💼</div>
                    <div class="stat-info">
                        <h4>Active Job Postings</h4>
                        <h3><?php echo $active_jobs_count; ?></h3>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon applications-icon">📋</div>
                    <div class="stat-info">
                        <h4>Total Applications</h4>
                        <h3><?php echo $applications_count; ?></h3>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon recent-icon">🔔</div>
                    <div class="stat-info">
                        <h4>Recent Applications (7 days)</h4>
                        <h3><?php echo $recent_applications_count; ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="<?php echo SITE_URL; ?>/views/employer/post-job.php" class="btn-action">
                    <span class="icon">📝</span> Post a New Job
                </a>
                <a href="<?php echo SITE_URL; ?>/views/employer/profile.php" class="btn-action btn-secondary">
                    <span class="icon">👤</span> Update Company Profile
                </a>
            </div>
            
            <div class="recent-jobs">
                <div class="recent-jobs-header">
                    <h3>Recent Job Postings</h3>
                </div>
                
                <?php if(count($recent_jobs) > 0): ?>
                    <ul class="recent-jobs-list">
                        <?php foreach($recent_jobs as $job): ?>
                            <li class="job-item">
                                <div class="job-details">
                                    <h4><?php echo htmlspecialchars($job['title']); ?></h4>
                                    <div class="job-meta">
                                        <span><?php echo htmlspecialchars($job['location']); ?></span>
                                        <span><?php echo ucfirst($job['job_type']); ?></span>
                                        <span>Posted: <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="job-info">
                                    <span class="job-status status-<?php echo $job['status']; ?>">
                                        <?php echo ucfirst($job['status']); ?>
                                    </span>
                                    <div class="job-applications">
                                        <?php echo $job['applications_count']; ?> application(s)
                                    </div>
                                </div>
                                <div class="job-actions">
                                    <a href="<?php echo SITE_URL; ?>/views/employer/view-job.php?id=<?php echo $job['job_id']; ?>" class="btn-sm btn-blue">View</a>
                                    <a href="<?php echo SITE_URL; ?>/views/employer/edit-job.php?id=<?php echo $job['job_id']; ?>" class="btn-sm btn-blue">Edit</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <p>You haven't posted any jobs yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>