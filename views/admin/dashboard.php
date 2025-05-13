<?php
// Set page title
$page_title = 'Admin Dashboard';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get counts for dashboard
$query_users = "SELECT COUNT(*) as total_users FROM users";
$query_employers = "SELECT COUNT(*) as total_employers FROM employer_profiles";
$query_jobseekers = "SELECT COUNT(*) as total_jobseekers FROM jobseeker_profiles";
$query_jobs = "SELECT COUNT(*) as total_jobs FROM jobs";
$query_pending_employers = "SELECT COUNT(*) as pending_employers FROM users WHERE role = 'employer' AND status = 'pending'";
$query_active_jobs = "SELECT COUNT(*) as active_jobs FROM jobs WHERE status = 'active'";

$stmt_users = $db->prepare($query_users);
$stmt_employers = $db->prepare($query_employers);
$stmt_jobseekers = $db->prepare($query_jobseekers);
$stmt_jobs = $db->prepare($query_jobs);
$stmt_pending_employers = $db->prepare($query_pending_employers);
$stmt_active_jobs = $db->prepare($query_active_jobs);

$stmt_users->execute();
$stmt_employers->execute();
$stmt_jobseekers->execute();
$stmt_jobs->execute();
$stmt_pending_employers->execute();
$stmt_active_jobs->execute();

$users_count = $stmt_users->fetch(PDO::FETCH_ASSOC)['total_users'];
$employers_count = $stmt_employers->fetch(PDO::FETCH_ASSOC)['total_employers'];
$jobseekers_count = $stmt_jobseekers->fetch(PDO::FETCH_ASSOC)['total_jobseekers'];
$jobs_count = $stmt_jobs->fetch(PDO::FETCH_ASSOC)['total_jobs'];
$pending_employers_count = $stmt_pending_employers->fetch(PDO::FETCH_ASSOC)['pending_employers'];
$active_jobs_count = $stmt_active_jobs->fetch(PDO::FETCH_ASSOC)['active_jobs'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Admin Dashboard Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            width: 100%;
            overflow-x: hidden;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
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
            padding: 20px 30px 30px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
            width: 100%;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }
        
        /* Modern Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }
        
        @media (max-width: 1200px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f5f7fa 100%);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 25px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(to bottom, #4a89dc, #5c9dff);
        }
        
        .users-card::before {
            background: linear-gradient(to bottom, #4a89dc, #5c9dff);
        }
        
        .employers-card::before {
            background: linear-gradient(to bottom, #5cb85c, #7ad57a);
        }
        
        .jobseekers-card::before {
            background: linear-gradient(to bottom, #f0ad4e, #ffbd67);
        }
        
        .jobs-card::before {
            background: linear-gradient(to bottom, #9b59b6, #b07cc6);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 28px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            background-size: 200% auto;
            color: white;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .users-icon {
            background: linear-gradient(135deg, #4a89dc 0%, #5c9dff 100%);
        }
        
        .employers-icon {
            background: linear-gradient(135deg, #5cb85c 0%, #7ad57a 100%);
        }
        
        .jobseekers-icon {
            background: linear-gradient(135deg, #f0ad4e 0%, #ffbd67 100%);
        }
        
        .jobs-icon {
            background: linear-gradient(135deg, #9b59b6 0%, #b07cc6 100%);
        }
        
        .stat-card:hover .stat-icon {
            background-position: right center;
        }
        
        .stat-info {
            flex: 1;
        }
        
        .stat-info h4 {
            margin: 0 0 5px;
            font-size: 1rem;
            color: #616161;
            font-weight: 500;
        }
        
        .stat-info h3 {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 700;
            color: #333;
        }
        
        .stat-growth {
            font-size: 0.8rem;
            color: #28a745;
            display: flex;
            align-items: center;
            margin-top: 5px;
        }
        
        /* Quick Actions Section */
        .quick-actions {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .quick-actions h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.4rem;
            color: #333;
            font-weight: 600;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        
        .action-button {
            display: flex;
            align-items: center;
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #dee2e6 100%);
            border-radius: 10px;
            text-decoration: none;
            color: #495057;
            font-weight: 500;
            transition: all 0.3s;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .action-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            color: #212529;
        }
        
        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .verify-icon {
            color: #5cb85c;
        }
        
        .job-icon {
            color: #007bff;
        }
        
        .user-icon {
            color: #fd7e14;
        }
        
        .query-icon {
            color: #6f42c1;
        }
        
        /* System Overview Styles */
        .system-overview {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .overview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .overview-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            color: #333;
        }
        
        .overview-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
            width: 100%;
        }
        
        @media (max-width: 1000px) {
            .overview-cards {
                grid-template-columns: 1fr;
            }
        }
        
        .overview-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 25px;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
        }
        
        .overview-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .overview-card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .overview-card-header h3 {
            margin: 0;
            font-size: 1.3rem;
            color: #333;
            font-weight: 600;
        }
        
        .overview-card-header i {
            font-size: 2rem;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #f8f9fa 0%, #dee2e6 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #007bff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .employers-icon-bg {
            color: #28a745;
        }
        
        .jobs-icon-bg {
            color: #9b59b6;
        }
        
        .overview-card-subheader {
            color: #666;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        
        .overview-card-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .overview-number {
            font-size: 2.8rem;
            font-weight: bold;
            color: #333;
            position: relative;
        }
        
        .overview-number-container {
            position: relative;
        }
        
        .progress-circle {
            position: absolute;
            top: -15px;
            right: -15px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            color: #28a745;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .status-green {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-blue {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        .system-alert {
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
            border-left: 5px solid #ffa000;
            border-radius: 12px;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .alert-icon {
            margin-right: 20px;
            font-size: 2rem;
            color: #f57c00;
            flex-shrink: 0;
        }
        
        .alert-text {
            color: #5d4037;
            font-size: 1.05rem;
            line-height: 1.5;
        }
        
        /* Recent Activity Section */
        .recent-activity {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            flex: 1;
            min-height: 250px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .recent-activity h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.4rem;
            color: #333;
            font-weight: 600;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            text-decoration: none;
            color: inherit;
            transition: background-color 0.2s ease;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item:hover {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding-left: 10px;
            padding-right: 10px;
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .activity-icon.user {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .activity-icon.job {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .activity-icon.query {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 500;
            margin-bottom: 3px;
            color: #333;
        }
        
        .activity-time {
            font-size: 0.85rem;
            color: #777;
        }
        
        .view-all-link {
            font-size: 0.85rem;
            color: #1a73e8;
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 15px;
            background-color: #e8f0fe;
            transition: background-color 0.2s ease;
            margin-left: 10px;
            display: inline-block;
        }
        
        .view-all-link:hover {
            background-color: #d2e3fc;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'admin-sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="top-bar">
                <h1>Admin Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></span>
                </div>
            </div>
            
            <div class="stats-cards">
                <div class="stat-card users-card">
                    <div class="stat-icon users-icon">👥</div>
                    <div class="stat-info">
                        <h4>Total Users</h4>
                        <h3><?php echo $users_count; ?></h3>
                        <div class="stat-growth">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 5px;">
                                <path d="M18 15L12 9L6 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Active System
                        </div>
                    </div>
                </div>
                
                <div class="stat-card employers-card">
                    <div class="stat-icon employers-icon">🏢</div>
                    <div class="stat-info">
                        <h4>Employers</h4>
                        <h3><?php echo $employers_count; ?></h3>
                        <div class="stat-growth">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 5px;">
                                <path d="M18 15L12 9L6 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Managing Jobs
                        </div>
                    </div>
                </div>
                
                <div class="stat-card jobseekers-card">
                    <div class="stat-icon jobseekers-icon">👤</div>
                    <div class="stat-info">
                        <h4>Job Seekers</h4>
                        <h3><?php echo $jobseekers_count; ?></h3>
                        <div class="stat-growth">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 5px;">
                                <path d="M18 15L12 9L6 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Looking for Jobs
                        </div>
                    </div>
                </div>
                
                <div class="stat-card jobs-card">
                    <div class="stat-icon jobs-icon">💼</div>
                    <div class="stat-info">
                        <h4>Active Jobs</h4>
                        <h3><?php echo $active_jobs_count; ?></h3>
                        <div class="stat-growth">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 5px;">
                                <path d="M18 15L12 9L6 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Available Positions
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions Section (New) -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php" class="action-button">
                        <div class="action-icon verify-icon">✓</div>
                        <span>Verify Employers</span>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php" class="action-button">
                        <div class="action-icon job-icon">💼</div>
                        <span>Manage Jobs</span>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/views/admin/manage-users.php" class="action-button">
                        <div class="action-icon user-icon">👥</div>
                        <span>Manage Users</span>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/views/admin/queries.php" class="action-button">
                        <div class="action-icon query-icon">💬</div>
                        <span>View Queries</span>
                    </a>
                </div>
            </div>
            
            <!-- System Overview Section (Enhanced) -->
            <div class="system-overview">
                <div class="overview-header">
                    <h2>System Overview</h2>
                </div>
                <p>Welcome to the ShaSha CJRS admin panel. From here, you can manage the entire job repository system.</p>
                
                <div class="overview-cards">
                    <div class="overview-card">
                        <div class="overview-card-header">
                            <i class="employers-icon-bg">🏢</i>
                            <h3>Employer Verification</h3>
                        </div>
                        <div class="overview-card-subheader">
                            Employers awaiting verification
                        </div>
                        <div class="overview-card-content">
                            <div class="overview-number-container">
                                <div class="overview-number"><?php echo $pending_employers_count; ?></div>
                                <?php if($pending_employers_count > 0): ?>
                                <div class="progress-circle">
                                    <?php echo $pending_employers_count; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="status-badge status-green">
                                <?php echo $pending_employers_count > 0 ? 'Action needed' : 'No action required'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overview-card">
                        <div class="overview-card-header">
                            <i class="jobs-icon-bg">💼</i>
                            <h3>Active Job Postings</h3>
                        </div>
                        <div class="overview-card-subheader">
                            Current job listings in the system
                        </div>
                        <div class="overview-card-content">
                            <div class="overview-number"><?php echo $active_jobs_count; ?></div>
                            <div class="status-badge status-blue">System ready</div>
                        </div>
                    </div>
                </div>
                
                <?php if($pending_employers_count == 0 && $active_jobs_count == 0): ?>
                <div class="system-alert">
                    <div class="alert-icon">ℹ️</div>
                    <div class="alert-text">
                        The system is currently empty. Use the navigation menu to add employers and job postings.
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Activity Section (New) -->
            <div class="recent-activity">
                <h2>Recent Activity 
                    <a href="<?php echo SITE_URL; ?>/views/admin/manage-users.php?view=recent" class="view-all-link">View All Users</a>
                    <a href="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php?view=recent" class="view-all-link">View All Jobs</a>
                </h2>
                <div class="activity-list">
                    <?php
                    // Get recent user registrations
                    $recent_users_query = "SELECT u.user_id, u.first_name, u.last_name, u.role, u.created_at 
                                        FROM users u 
                                        ORDER BY u.created_at DESC 
                                        LIMIT 3";
                    $recent_users_stmt = $db->prepare($recent_users_query);
                    $recent_users_stmt->execute();
                    $recent_users = $recent_users_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get recent job postings
                    $recent_jobs_query = "SELECT j.job_id, j.title, j.posted_at, e.company_name 
                                       FROM jobs j 
                                       JOIN employer_profiles e ON j.employer_id = e.employer_id 
                                       ORDER BY j.posted_at DESC 
                                       LIMIT 3";
                    $recent_jobs_stmt = $db->prepare($recent_jobs_query);
                    $recent_jobs_stmt->execute();
                    $recent_jobs = $recent_jobs_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Display recent users
                    foreach($recent_users as $user): 
                    ?>
                    <a href="<?php echo SITE_URL; ?>/views/admin/manage-users.php?user_id=<?php echo $user['user_id']; ?>" class="activity-item">
                        <div class="activity-icon user">👤</div>
                        <div class="activity-content">
                            <div class="activity-title">
                                New <?php echo ucfirst($user['role']); ?>: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </div>
                            <div class="activity-time"><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    
                    <!-- Display recent jobs -->
                    <?php foreach($recent_jobs as $job): ?>
                    <a href="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php?job_id=<?php echo $job['job_id']; ?>" class="activity-item">
                        <div class="activity-icon job">💼</div>
                        <div class="activity-content">
                            <div class="activity-title">
                                New Job: <?php echo htmlspecialchars($job['title']); ?> at <?php echo htmlspecialchars($job['company_name']); ?>
                            </div>
                            <div class="activity-time"><?php echo date('M d, Y H:i', strtotime($job['posted_at'])); ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    
                    <?php if(count($recent_users) == 0 && count($recent_jobs) == 0): ?>
                    <div class="activity-item">
                        <div class="activity-icon">ℹ️</div>
                        <div class="activity-content">
                            <div class="activity-title">No recent activity</div>
                            <div class="activity-time">System is waiting for new activity</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>