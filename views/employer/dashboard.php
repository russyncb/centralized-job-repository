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

// Get employer ID and user info
$query = "SELECT e.employer_id, e.company_name, e.industry, e.location, e.verified,
          u.first_name, u.last_name 
          FROM employer_profiles e
          JOIN users u ON e.user_id = u.user_id
          WHERE e.user_id = ?";
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
            margin: 0;
            padding: 0;
        }
        
        .employer-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 30px;
            background: #f8fafc;
            overflow-y: auto;
            width: calc(100% - 250px);  /* Account for sidebar width */
            transition: all 0.3s ease;
        }
        
        .sidebar {
            position: relative;  /* Added to contain the toggle button */
            width: 250px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            transition: width 0.3s ease;
        }
        
        /* Sidebar Toggle Button */
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
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed + .main-content {
            width: calc(100% - 70px);
        }
        
        .sidebar.collapsed .sidebar-toggle {
            transform: rotate(180deg);
        }
        
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

        .verification-badge {
            display: inline-flex;
            align-items: center;
            background-color: rgba(232, 245, 233, 0.9);
            color: #388e3c;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 15px;
            backdrop-filter: blur(4px);
        }
        
        .verification-badge .icon {
            margin-right: 5px;
        }
        
        .pending-verification {
            background-color: rgba(255, 248, 225, 0.9);
            color: #f57c00;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: white;
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            aspect-ratio: 3/2;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(21, 87, 176, 0.3);
            text-decoration: none;
        }
        
        .stat-icon {
            font-size: 24px;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            margin-bottom: 10px;
        }
        
        .stat-info {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            margin: 4px 0;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 8px;
            font-weight: 500;
            text-align: center;
        }
        
        .stat-button {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            white-space: nowrap;
            backdrop-filter: blur(5px);
            margin-top: auto;
        }
        
        .stat-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }
        
        /* Card color variations */
        .stat-card.jobs {
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
        }
        
        .stat-card.applications {
            background: linear-gradient(135deg, #2c5282 0%, #2b6cb0 100%);
        }
        
        .stat-card.recent {
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
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
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .recent-jobs-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            color: white;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
        }
        
        .recent-jobs-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: white;
        }
        
        .recent-jobs-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .job-item {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            background: white;
        }
        
        .job-item:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f4f9 100%);
            transform: translateX(5px);
        }
        
        .job-item:last-child {
            border-bottom: none;
        }
        
        .job-details {
            flex: 1;
        }
        
        .job-details h4 {
            margin: 0 0 8px;
            font-size: 1.1rem;
            color: #1a3b5d;
            font-weight: 600;
        }
        
        .job-meta {
            display: flex;
            gap: 20px;
            color: #64748b;
            font-size: 0.9rem;
            align-items: center;
        }
        
        .job-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .job-meta i {
            font-size: 1.1em;
            opacity: 0.7;
        }
        
        .job-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
            min-width: 140px;
        }
        
        .job-status {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            gap: 5px;
        }
        
        .status-active {
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            color: white;
        }
        
        .status-closed {
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
            color: white;
        }
        
        .status-draft {
            background: linear-gradient(135deg, #38bdf8 0%, #0ea5e9 100%);
            color: white;
        }
        
        .status-archived {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
            color: white;
        }
        
        .job-applications {
            font-size: 0.9rem;
            color: #64748b;
            background: #f1f5f9;
            padding: 4px 12px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .job-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }
        
        .btn-blue {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
        }
        
        .btn-blue:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f4f9 100%);
        }
        
        .empty-state p {
            margin: 0;
            font-size: 1.1rem;
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
    </style>
</head>
<body>
    <div class="employer-container">
        <?php include 'employer-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($employer['company_name']); ?></span>
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
                <a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php?status=active" class="stat-card jobs">
                    <div class="stat-icon">💼</div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $active_jobs_count; ?></div>
                        <div class="stat-label">Active Job Postings</div>
                    </div>
                    <div class="stat-button">VIEW JOBS</div>
                </a>
                
                <a href="<?php echo SITE_URL; ?>/views/employer/applications.php" class="stat-card applications">
                    <div class="stat-icon">📋</div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $applications_count; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                    <div class="stat-button">VIEW APPLICATIONS</div>
                </a>
                
                <a href="<?php echo SITE_URL; ?>/views/employer/applications.php?days=7" class="stat-card recent">
                    <div class="stat-icon">🔔</div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $recent_applications_count; ?></div>
                        <div class="stat-label">Recent Applications (7 days)</div>
                    </div>
                    <div class="stat-button">VIEW RECENT</div>
                </a>
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
                                        <span><i>📍</i><?php echo htmlspecialchars($job['location']); ?></span>
                                        <span><i>💼</i><?php echo ucfirst($job['job_type']); ?></span>
                                        <span><i>📅</i>Posted: <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="job-info">
                                    <span class="job-status status-<?php echo $job['status']; ?>">
                                        <?php if($job['status'] == 'active'): ?>
                                            <i>🟢</i>
                                        <?php elseif($job['status'] == 'closed'): ?>
                                            <i>🔴</i>
                                        <?php elseif($job['status'] == 'draft'): ?>
                                            <i>🔵</i>
                                        <?php else: ?>
                                            <i>⚪</i>
                                        <?php endif; ?>
                                        <?php echo ucfirst($job['status']); ?>
                                    </span>
                                    <div class="job-applications">
                                        <i>👥</i> <?php echo $job['applications_count']; ?> application(s)
                                    </div>
                                    <div class="job-actions">
                                        <a href="<?php echo SITE_URL; ?>/views/employer/view-job.php?id=<?php echo $job['job_id']; ?>" class="btn-sm btn-blue">
                                            <i>👁️</i> View
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/views/employer/edit-job.php?id=<?php echo $job['job_id']; ?>" class="btn-sm btn-blue">
                                            <i>✏️</i> Edit
                                        </a>
                                    </div>
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

            // Add confirmation for logout - using localStorage to track confirmation
            const logoutLink = document.querySelector('a[href*="logout.php"]');
            if(logoutLink) {
                logoutLink.addEventListener('click', function(e) {
                    const hasConfirmed = localStorage.getItem('logoutConfirmed');
                    if(!hasConfirmed) {
                        if(confirm('Are you sure you want to logout?')) {
                            localStorage.setItem('logoutConfirmed', 'true');
                            // Clear the confirmation after 1 minute
                            setTimeout(() => {
                                localStorage.removeItem('logoutConfirmed');
                            }, 60000);
                        } else {
                            e.preventDefault();
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>