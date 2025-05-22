<?php
// Set page title
$page_title = 'Saved Jobs';

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

// Handle save/unsave job action
if(isset($_GET['action']) && isset($_GET['job_id']) && is_numeric($_GET['job_id'])) {
    $job_id = (int)$_GET['job_id'];
    $action = $_GET['action'];
    
    if($action === 'remove') {
        // Delete the saved job
        $delete_query = "DELETE FROM saved_jobs WHERE jobseeker_id = ? AND job_id = ?";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(1, $jobseeker_id);
        $delete_stmt->bindParam(2, $job_id);
        
        if($delete_stmt->execute()) {
            $_SESSION['message'] = "Job removed from saved jobs.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Failed to remove job.";
            $_SESSION['message_type'] = "error";
        }
    }
    
    // Redirect to remove the parameter from URL
    redirect(SITE_URL . '/views/jobseeker/saved-jobs.php', null, null);
}

// Set up pagination
$jobs_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $jobs_per_page;

// Count total saved jobs
$count_query = "SELECT COUNT(*) as total FROM saved_jobs WHERE jobseeker_id = ?";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(1, $jobseeker_id);
$count_stmt->execute();
$total_saved_jobs = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate total pages
$total_pages = ceil($total_saved_jobs / $jobs_per_page);

// Get saved jobs with job and employer details
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT j.*, e.company_name, e.company_logo, sj.saved_at
          FROM saved_jobs sj
          JOIN jobs j ON sj.job_id = j.job_id
          JOIN employer_profiles e ON j.employer_id = e.employer_id
          WHERE sj.jobseeker_id = ?";

$params = [$jobseeker_id];

if (!empty($search_query)) {
    $query .= " AND (j.title LIKE ? OR j.location LIKE ? OR e.company_name LIKE ? OR j.description LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$query .= " ORDER BY sj.saved_at DESC LIMIT $offset, $jobs_per_page";
$stmt = $db->prepare($query);

foreach($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}

$stmt->execute();
$saved_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if already applied to jobs
$applied_jobs = [];
if(!empty($saved_jobs)) {
    $job_ids = array_column($saved_jobs, 'job_id');
    $placeholders = implode(',', array_fill(0, count($job_ids), '?'));
    
    $application_query = "SELECT job_id FROM applications WHERE jobseeker_id = ? AND job_id IN ($placeholders)";
    $application_stmt = $db->prepare($application_query);
    
    // Bind jobseeker_id as the first parameter
    $application_stmt->bindParam(1, $jobseeker_id);
    
    // Bind job_ids starting from parameter 2
    foreach($job_ids as $key => $job_id) {
        $application_stmt->bindParam($key + 2, $job_ids[$key]);
    }
    
    $application_stmt->execute();
    $applied_jobs = $application_stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/chatbot.css">
    <style>
        /* Saved Jobs Styles */
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
        
        .saved-count {
            background-color: #e3f2fd;
            color: #0056b3;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .job-list {
            margin-bottom: 30px;
        }
        
        .job-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .company-logo {
            width: 70px;
            height: 70px;
            background-color: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-right: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .company-logo img {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
        }
        
        .company-logo span {
            font-size: 1.8rem;
            font-weight: bold;
            color: #0056b3;
        }
        
        .job-details {
            flex: 1;
        }
        
        .job-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .job-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .job-title a {
            color: #0056b3;
            text-decoration: none;
        }
        
        .job-title a:hover {
            text-decoration: underline;
        }
        
        .job-status {
            display: flex;
            align-items: center;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .status-closed {
            background-color: #f5f5f5;
            color: #757575;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .job-meta span {
            display: flex;
            align-items: center;
        }
        
        .job-meta span i {
            margin-right: 5px;
            font-size: 1.1rem;
        }
        
        .job-description {
            margin-bottom: 15px;
            font-size: 0.95rem;
            color: #333;
            line-height: 1.5;
        }
        
        .job-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .job-tag {
            background-color: #f0f5ff;
            color: #0056b3;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .job-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s;
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
        
        .btn-danger {
            background-color: transparent;
            border: 1px solid #dc3545;
            color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #dc3545;
            color: white;
            text-decoration: none;
        }
        
        .btn-disabled {
            background-color: #6c757d;
            color: white;
            cursor: not-allowed;
        }
        
        .applied-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
        }
        
        .applied-badge i {
            margin-right: 5px;
        }
        
        .saved-date {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 10px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 4px;
            color: #0056b3;
            text-decoration: none;
        }
        
        .pagination a {
            background-color: white;
            border: 1px solid #dee2e6;
        }
        
        .pagination a:hover {
            background-color: #f0f5ff;
        }
        
        .pagination span {
            background-color: #0056b3;
            color: white;
            border: 1px solid #0056b3;
        }
        
        .no-jobs {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .no-jobs h3 {
            margin-bottom: 10px;
        }
        
        .no-jobs p {
            color: #666;
            margin-bottom: 20px;
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
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            text-align: center;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .search-container {
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: #1557b0;
            box-shadow: 0 0 0 3px rgba(21, 87, 176, 0.1);
            outline: none;
        }
        
        .search-button {
            padding: 12px 25px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(21, 87, 176, 0.2);
        }
        
        .search-stats {
            margin-top: 10px;
            color: #666;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .jobseeker-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .job-card {
                flex-direction: column;
            }
            
            .company-logo {
                margin-bottom: 15px;
            }
            
            .job-header {
                flex-direction: column;
            }
            
            .job-status {
                margin-top: 10px;
            }
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
        
        /* Sidebar Toggle Button */
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
            <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>"><i>📊</i><span>Dashboard</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>"><i>👤</i><span>My Profile</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'search-jobs.php' ? 'active' : ''; ?>"><i>🔍</i><span>Search Jobs</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'saved-jobs.php' ? 'active' : ''; ?>"><i>💾</i><span>Saved Jobs</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'my-applications.php' ? 'active' : ''; ?>"><i>📝</i><span>My Applications</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/job-analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'job-analytics.php' ? 'active' : ''; ?>"><i>📈</i><span>Job Market Analytics</span></a></li>
            <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i><span>Logout</span></a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <h1>Saved Jobs</h1>
            <div class="user-info">
                <div class="user-name">
                    <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?>
                </div>
                <?php if(!empty($jobseeker['headline'])): ?>
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($jobseeker['headline']); ?></span>
                    </div>
                <?php endif; ?>
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
        
        <div class="search-container">
            <form class="search-form" method="GET" action="">
                <input 
                    type="text" 
                    name="search" 
                    class="search-input" 
                    placeholder="Search saved jobs by title, company, or location..."
                    value="<?php echo htmlspecialchars($search_query); ?>"
                >
                <button type="submit" class="search-button">
                    <span>🔍</span> Search
                </button>
            </form>
            <?php if(!empty($search_query)): ?>
                <div class="search-stats">
                    Showing results for "<?php echo htmlspecialchars($search_query); ?>"
                    <a href="?" class="clear-search">(Clear search)</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="job-list">
            <?php if(count($saved_jobs) > 0): ?>
                <?php foreach($saved_jobs as $job): ?>
                    <div class="job-card">
                        <div class="company-logo">
                            <?php if(!empty($job['company_logo'])): ?>
                                <img src="<?php echo SITE_URL . '/' . $job['company_logo']; ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?> Logo">
                            <?php else: ?>
                                <span><?php echo strtoupper(substr($job['company_name'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="job-details">
                            <div class="job-header">
                                <div>
                                    <div class="job-title">
                                        <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $job['job_id']; ?>">
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </a>
                                    </div>
                                    <div class="company-name">
                                        <?php echo htmlspecialchars($job['company_name']); ?>
                                    </div>
                                </div>
                                <div class="job-status">
                                    <span class="status-badge status-<?php echo $job['status']; ?>">
                                        <?php echo ucfirst($job['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="job-meta">
                                <span><i>📍</i> <?php echo htmlspecialchars($job['location']); ?></span>
                                <span><i>💼</i> <?php echo ucfirst($job['job_type']); ?></span>
                                <?php if(!empty($job['salary_range'])): ?>
                                    <span><i>💰</i> <?php echo htmlspecialchars($job['salary_range']); ?></span>
                                <?php endif; ?>
                                <span><i>📅</i> Posted: <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
                            </div>
                            
                            <div class="job-description">
                                <?php 
                                    $description = strip_tags($job['description']);
                                    echo strlen($description) > 200 ? substr($description, 0, 200) . '...' : $description;
                                ?>
                            </div>
                            
                            <?php if(!empty($job['skills_required'])): ?>
                                <div class="job-tags">
                                    <?php 
                                        $skills = explode(',', $job['skills_required']);
                                        foreach($skills as $skill): 
                                            $skill = trim($skill);
                                            if(!empty($skill)):
                                    ?>
                                        <span class="job-tag"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php 
                                            endif;
                                        endforeach; 
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="saved-date">
                                Saved on <?php echo date('M d, Y', strtotime($job['saved_at'])); ?>
                            </div>
                            
                            <div class="job-actions">
                                <?php if(in_array($job['job_id'], $applied_jobs)): ?>
                                    <span class="applied-badge"><i>✓</i> Already Applied</span>
                                <?php endif; ?>
                                
                                <?php if($job['status'] == 'active'): ?>
                                    <?php if(!in_array($job['job_id'], $applied_jobs)): ?>
                                        <a href="<?php echo SITE_URL; ?>/views/jobseeker/apply-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-primary">Apply Now</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn btn-disabled" disabled>Job Closed</button>
                                <?php endif; ?>
                                
                                <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-outline">View Details</a>
                                <a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php?action=remove&job_id=<?php echo $job['job_id']; ?>" class="btn btn-danger remove-job">Remove</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if($current_page > 1): ?>
                            <a href="?page=<?php echo $current_page - 1; ?>">&laquo; Previous</a>
                        <?php endif; ?>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if($i == $current_page): ?>
                                <span><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📌</div>
                    <h3>No Saved Jobs Yet</h3>
                    <p>Save jobs you're interested in to revisit them later.</p>
                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="btn btn-primary">Explore Jobs</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Include the Advanced Chatbot JS -->
<script src="<?php echo SITE_URL; ?>/assets/js/chatbot.js"></script>

<!-- Sidebar Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
    }
    
    // Remove job confirmation
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-job')) {
            if (!confirm('Are you sure you want to remove this job from your saved jobs?')) {
                e.preventDefault();
            }
        }
    });
});
</script>
</body>
</html>