<?php
// Set page title
$page_title = 'My Applications';

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
    redirect(SITE_URL . '/views/auth/logout.php', 'Jobseeker profile not found.', 'error');
    exit;
}

$jobseeker_id = $jobseeker['jobseeker_id'];

// Set up pagination
$applications_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $applications_per_page;

// Get status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Process application withdrawal if requested
if(isset($_GET['withdraw']) && is_numeric($_GET['withdraw'])) {
    $application_id = (int)$_GET['withdraw'];
    
    // Verify application belongs to this jobseeker
    $verify_query = "SELECT * FROM applications WHERE application_id = ? AND jobseeker_id = ?";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->bindParam(1, $application_id);
    $verify_stmt->bindParam(2, $jobseeker_id);
    $verify_stmt->execute();
    
    if($verify_stmt->rowCount() > 0) {
        // Update application status to withdrawn
        $update_query = "UPDATE applications SET status = 'withdrawn', updated_at = NOW() WHERE application_id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(1, $application_id);
        
        if($update_stmt->execute()) {
            $_SESSION['message'] = "Application withdrawn successfully.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Failed to withdraw application.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Application not found or you don't have permission to withdraw it.";
        $_SESSION['message_type'] = "error";
    }
    
    // Redirect to remove the withdraw parameter from URL
    redirect(SITE_URL . '/views/jobseeker/my-applications.php' . (!empty($status_filter) ? '?status=' . $status_filter : ''), null, null);
}

// Base query for applications
$query = "SELECT a.*, j.title as job_title, j.location as job_location, j.job_type, j.status as job_status,
         e.company_name, e.company_logo
         FROM applications a
         JOIN jobs j ON a.job_id = j.job_id
         JOIN employer_profiles e ON j.employer_id = e.employer_id
         WHERE a.jobseeker_id = ?";

// Add status filter if provided
if(!empty($status_filter)) {
    $query .= " AND a.status = ?";
}

// Count total applications for pagination
$count_query = str_replace("a.*, j.title as job_title, j.location as job_location, j.job_type, j.status as job_status,
         e.company_name, e.company_logo", "COUNT(*) as total", $query);

$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(1, $jobseeker_id);

if(!empty($status_filter)) {
    $count_stmt->bindParam(2, $status_filter);
}

$count_stmt->execute();
$total_applications = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate total pages
$total_pages = ceil($total_applications / $applications_per_page);

// Add order and pagination
$query .= " ORDER BY a.applied_at DESC LIMIT $offset, $applications_per_page";

// Get applications
$stmt = $db->prepare($query);
$stmt->bindParam(1, $jobseeker_id);

if(!empty($status_filter)) {
    $stmt->bindParam(2, $status_filter);
}

$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get application counts by status
$status_query = "SELECT status, COUNT(*) as count FROM applications WHERE jobseeker_id = ? GROUP BY status";
$status_stmt = $db->prepare($status_query);
$status_stmt->bindParam(1, $jobseeker_id);
$status_stmt->execute();
$status_counts = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

$application_counts = [
    'all' => $total_applications,
    'pending' => 0,
    'shortlisted' => 0,
    'rejected' => 0,
    'hired' => 0,
    'withdrawn' => 0
];

foreach($status_counts as $count) {
    $application_counts[$count['status']] = $count['count'];
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
        /* My Applications Styles */
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
            position: fixed;
            top: 20px;
            left: 260px;
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
            left: 80px;
            transform: rotate(180deg);
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
            padding: 25px 30px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            color: white;
        }
        
        .top-bar h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #ffffff;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .user-name {
            font-size: 1.1rem;
            color: #ffffff;
            font-weight: 500;
        }
        
        .professional-headline {
            color: #FFD700;
            font-weight: 600;
            font-size: 0.95rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #eef2f7;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1a5276;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .status-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            overflow-x: auto;
            padding-bottom: 5px;
        }
        
        .status-tab {
            padding: 10px 20px;
            border-radius: 8px;
            color: #475569;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            background: white;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        
        .status-tab:hover {
            background: #f8fafc;
            color: #1e293b;
            text-decoration: none;
        }
        
        .status-tab.active {
            background: #1a5276;
            color: white;
            border-color: #1a5276;
        }
        
        .application-list {
            margin-bottom: 30px;
        }
        
        .application-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 25px;
            border: 1px solid #eef2f7;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .application-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .company-logo {
            width: 70px;
            height: 70px;
            background: #f8f9fa;
            border: 1px solid #eef2f7;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .company-logo img {
            max-width: 50px;
            max-height: 50px;
            border-radius: 8px;
        }
        
        .application-details {
            flex: 1;
        }
        
        .application-header {
            margin-bottom: 12px;
        }
        
        .job-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .job-title a {
            color: #1a5276;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .job-title a:hover {
            color: #2980b9;
        }
        
        .company-name {
            color: #64748b;
            font-size: 0.95rem;
        }
        
        .application-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 12px;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .application-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .application-feedback {
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 8px;
            margin-top: 12px;
            color: #475569;
            font-size: 0.95rem;
            border: 1px solid #e2e8f0;
        }
        
        .application-status {
            margin-left: auto;
            padding-left: 20px;
            border-left: 1px solid #eef2f7;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-pending {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }
        
        .status-shortlisted {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        
        .status-hired {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        
        .status-rejected {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        .status-withdrawn {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        
        .application-actions {
            margin-top: 15px;
        }
        
        .withdraw-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .withdraw-btn:hover {
            background-color: #fee2e2;
            border-color: #fecaca;
        }
        
        .no-applications {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #eef2f7;
        }
        
        .no-applications h3 {
            color: #1e293b;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .no-applications p {
            color: #64748b;
            margin-bottom: 20px;
        }
        
        .no-applications .btn-primary {
            background: linear-gradient(135deg, #1a5276 0%, #154360 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .no-applications .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 82, 118, 0.2);
        }
        
        .message {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #166534;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #bbf7d0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border-color: #fecaca;
        }
        
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .application-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .application-status {
                margin-left: 0;
                margin-top: 15px;
                padding-left: 0;
                border-left: none;
                border-top: 1px solid #eef2f7;
                padding-top: 15px;
                width: 100%;
            }
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
                <h3>ShaSha</h3>
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
                <h1>My Applications</h1>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?>
                    </div>
                </div>
            </div>
            
            <?php if(isset($_SESSION['message'])): ?>
                <div class="message <?php echo $_SESSION['message_type']; ?>">
                    <?php 
                        echo $_SESSION['message']; 
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $application_counts['all']; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $application_counts['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $application_counts['shortlisted']; ?></div>
                    <div class="stat-label">Shortlisted</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $application_counts['hired']; ?></div>
                    <div class="stat-label">Hired</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $application_counts['rejected']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
            
            <div class="status-tabs">
                <a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php" class="status-tab <?php if(empty($status_filter)) echo 'active'; ?>">
                    All Applications
                </a>
                <a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php?status=pending" class="status-tab <?php if($status_filter == 'pending') echo 'active'; ?>">
                    Pending
                </a>
                <a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php?status=shortlisted" class="status-tab <?php if($status_filter == 'shortlisted') echo 'active'; ?>">
                    Shortlisted
                </a>
                <a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php?status=hired" class="status-tab <?php if($status_filter == 'hired') echo 'active'; ?>">
                    Hired
                </a>
                <a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php?status=rejected" class="status-tab <?php if($status_filter == 'rejected') echo 'active'; ?>">
                    Rejected
                </a>
                <a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php?status=withdrawn" class="status-tab <?php if($status_filter == 'withdrawn') echo 'active'; ?>">
                    Withdrawn
                </a>
            </div>
            
            <div class="application-list">
                <?php if(count($applications) > 0): ?>
                    <?php foreach($applications as $application): ?>
                        <div class="application-item">
                            <div class="company-logo">
                                <?php if(!empty($application['company_logo'])): ?>
                                    <img src="<?php echo SITE_URL . '/' . $application['company_logo']; ?>" alt="<?php echo htmlspecialchars($application['company_name']); ?> Logo">
                                <?php else: ?>
                                    <span><?php echo strtoupper(substr($application['company_name'], 0, 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="application-details">
                                <div class="application-header">
                                    <div class="job-title">
                                        <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $application['job_id']; ?>">
                                            <?php echo htmlspecialchars($application['job_title']); ?>
                                            <?php if($application['job_status'] == 'closed'): ?>
                                                <span class="job-closed">Job Closed</span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="company-name">
                                        <?php echo htmlspecialchars($application['company_name']); ?>
                                    </div>
                                </div>
                                
                                <div class="application-meta">
                                    <span><i>📍</i> <?php echo htmlspecialchars($application['job_location']); ?></span>
                                    <span><i>💼</i> <?php echo ucfirst($application['job_type']); ?></span>
                                    <span><i>📅</i> Applied: <?php echo date('M d, Y', strtotime($application['applied_at'])); ?></span>
                                </div>
                                
                                <?php if(!empty($application['feedback'])): ?>
                                    <div class="application-feedback">
                                        <strong>Feedback:</strong> <?php echo htmlspecialchars($application['feedback']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if($application['status'] == 'pending'): ?>
                                    <div class="application-actions">
                                        <a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php?withdraw=<?php echo $application['application_id']; ?>" class="btn btn-danger withdraw-btn">
                                            Withdraw Application
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="application-status">
                                <span class="status-badge status-<?php echo $application['status']; ?>">
                                    <?php echo ucfirst($application['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?><?php if(!empty($status_filter)) echo '&status=' . $status_filter; ?>">&laquo; Previous</a>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if($i == $current_page): ?>
                                    <span><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?><?php if(!empty($status_filter)) echo '&status=' . $status_filter; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?><?php if(!empty($status_filter)) echo '&status=' . $status_filter; ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-applications">
                        <h3>No applications found</h3>
                        <?php if(!empty($status_filter)): ?>
                            <p>You don't have any <?php echo $status_filter; ?> applications.</p>
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php" class="btn btn-primary">View All Applications</a>
                        <?php else: ?>
                            <p>You haven't applied to any jobs yet.</p>
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="btn btn-primary">Search Jobs</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
            // Sidebar Toggle with localStorage persistence
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            
            // Check if there's a saved state
            const sidebarState = localStorage.getItem('sidebarState');
            if (sidebarState === 'collapsed') {
                sidebar.classList.add('collapsed');
            }
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                // Save the state
                localStorage.setItem('sidebarState', 
                    sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded'
                );
            });

            // Logout confirmation
            const logoutLink = document.querySelector('a[href*="logout.php"]');
            if (logoutLink) {
                logoutLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (confirm('Are you sure you want to logout?')) {
                        window.location.href = this.href;
                    }
                });
            }

            // Chatbot icon click handler
            const chatbotIcon = document.getElementById('chatbot-icon');
            chatbotIcon.addEventListener('click', function() {
                // You can implement your chatbot logic here
                alert('Chat functionality coming soon!');
            });

            // Add confirmation for application withdrawal
            const withdrawButtons = document.querySelectorAll('.withdraw-btn');
            if(withdrawButtons) {
                withdrawButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        if(!confirm('Are you sure you want to withdraw this application? This action cannot be undone.')) {
                            e.preventDefault();
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>