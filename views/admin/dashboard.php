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
        }
        
        .admin-container {
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
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
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
        
        .users-icon {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .employers-icon {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .jobseekers-icon {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .jobs-icon {
            background-color: #f3e5f5;
            color: #7b1fa2;
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
        
        /* System Overview Styles */
        .system-overview {
            margin-bottom: 30px;
        }
        
        .system-overview h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: #333;
        }
        
        .system-overview p {
            color: #555;
            margin-bottom: 20px;
        }
        
        .overview-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .overview-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
        }
        
        .overview-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .overview-card-header h3 {
            margin: 0;
            font-size: 1.3rem;
            color: #333;
        }
        
        .overview-card-header i {
            font-size: 1.5rem;
            color: #0056b3;
        }
        
        .overview-card-subheader {
            color: #666;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .overview-card-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .overview-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .status-green {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .status-blue {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .system-alert {
            background-color: #fff8e1;
            border-left: 4px solid #f57c00;
            border-radius: 8px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
        }
        
        .alert-icon {
            margin-right: 15px;
            font-size: 1.5rem;
            color: #f57c00;
        }
        
        .alert-text {
            color: #555;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>ShaSha Admin</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="<?php echo SITE_URL; ?>/views/admin/dashboard.php" class="active"><i>📊</i> Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php"><i>✓</i> Verify Employers <?php if($pending_employers_count > 0): ?><span class="badge"><?php echo $pending_employers_count; ?></span><?php endif; ?></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/admin/manage-users.php"><i>👥</i> Users</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php"><i>💼</i> Jobs</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/admin/settings.php"><i>⚙️</i> Settings</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Admin Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></span>
                </div>
            </div>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon users-icon">👥</div>
                    <div class="stat-info">
                        <h4>Total Users</h4>
                        <h3><?php echo $users_count; ?></h3>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon employers-icon">🏢</div>
                    <div class="stat-info">
                        <h4>Employers</h4>
                        <h3><?php echo $employers_count; ?></h3>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon jobseekers-icon">👤</div>
                    <div class="stat-info">
                        <h4>Job Seekers</h4>
                        <h3><?php echo $jobseekers_count; ?></h3>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon jobs-icon">💼</div>
                    <div class="stat-info">
                        <h4>Active Jobs</h4>
                        <h3><?php echo $active_jobs_count; ?></h3>
                    </div>
                </div>
            </div>
            
            <!-- System Overview Section (New) -->
            <div class="system-overview">
                <h1>System Overview</h1>
                <p>Welcome to the ShaSha CJRS admin panel. From here, you can manage the entire job repository system.</p>
                
                <div class="overview-cards">
                    <div class="overview-card">
                        <div class="overview-card-header">
                            <i>🏢</i>
                            <h3>Employer Verification</h3>
                        </div>
                        <div class="overview-card-subheader">
                            Employers awaiting verification
                        </div>
                        <div class="overview-card-content">
                            <div class="overview-number"><?php echo $pending_employers_count; ?></div>
                            <div class="status-badge status-green">
                                <?php echo $pending_employers_count > 0 ? 'Action needed' : 'No action required'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overview-card">
                        <div class="overview-card-header">
                            <i>💼</i>
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
        </div>
    </div>
</body>
</html>