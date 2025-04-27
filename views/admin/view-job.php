<?php
// Set page title
$page_title = 'View Job';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Check if job ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    redirect(SITE_URL . '/views/admin/manage-jobs.php', 'Job ID is required.', 'error');
}

$job_id = $_GET['id'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get job details
$query = "SELECT j.*, e.company_name, e.industry, e.location as company_location, 
         e.website, u.first_name, u.last_name, u.email, u.phone
         FROM jobs j
         JOIN employer_profiles e ON j.employer_id = e.employer_id
         JOIN users u ON e.user_id = u.user_id
         WHERE j.job_id = :job_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':job_id', $job_id);
$stmt->execute();

$job = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$job) {
    redirect(SITE_URL . '/views/admin/manage-jobs.php', 'Job not found.', 'error');
}

// Count applications for this job
$query_apps = "SELECT COUNT(*) as total_applications FROM applications WHERE job_id = :job_id";
$stmt_apps = $db->prepare($query_apps);
$stmt_apps->bindParam(':job_id', $job_id);
$stmt_apps->execute();
$applications_count = $stmt_apps->fetch(PDO::FETCH_ASSOC)['total_applications'];
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
        
        .job-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .job-header {
            padding: 25px;
            border-bottom: 1px solid #eee;
            position: relative;
        }
        
        .job-title {
            font-size: 1.5rem;
            margin: 0 0 5px;
        }
        
        .job-company {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 15px;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            color: #555;
        }
        
        .meta-icon {
            margin-right: 5px;
            color: #0056b3;
        }
        
        .job-status {
            position: absolute;
            top: 25px;
            right: 25px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
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
        
        .job-content {
            padding: 25px;
        }
        
        .job-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .section-content {
            line-height: 1.6;
        }
        
        .job-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #555;
        }
        
        .detail-value {
            font-size: 0.95rem;
        }
        
        .employer-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .employer-section {
            margin-bottom: 20px;
        }
        
        .back-button {
            display: inline-block;
            margin-right: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            color: #333;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .btn-action {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-activate {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .btn-close {
            background-color: #fbe9e7;
            color: #d32f2f;
        }
        
        .btn-delete {
            background-color: #f8d7da;
            color: #721c24;
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
                <li><a href="<?php echo SITE_URL; ?>/views/admin/dashboard.php"><i>📊</i> Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php"><i>✓</i> Verify Employers</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/admin/manage-users.php"><i>👥</i> Users</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php" class="active"><i>💼</i> Jobs</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/admin/settings.php"><i>⚙️</i> Settings</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>View Job</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></span>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php" class="back-button">← Back to Jobs</a>
                
                <?php if($job['status'] != 'active'): ?>
                    <form method="post" action="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php" style="display:inline;">
                        <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="btn-action btn-activate">Activate Job</button>
                    </form>
                <?php endif; ?>
                
                <?php if($job['status'] != 'closed'): ?>
                    <form method="post" action="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php" style="display:inline;">
                        <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                        <input type="hidden" name="action" value="close">
                        <button type="submit" class="btn-action btn-close">Close Job</button>
                    </form>
                <?php endif; ?>
                
                <form method="post" action="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this job? This action cannot be undone.');">
                    <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn-action btn-delete">Delete Job</button>
                </form>
            </div>
            
            <div class="job-container">
                <div class="job-header">
                    <h2 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h2>
                    <div class="job-company"><?php echo htmlspecialchars($job['company_name']); ?></div>
                    
                    <div class="job-meta">
                        <div class="meta-item">
                            <span class="meta-icon">📍</span>
                            <span><?php echo htmlspecialchars($job['location']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">💼</span>
                            <span><?php echo ucfirst($job['job_type']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">📂</span>
                            <span><?php echo htmlspecialchars($job['category']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">📝</span>
                            <span>Posted on <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">👥</span>
                            <span><?php echo $applications_count; ?> application(s)</span>
                        </div>
                    </div>
                    
                    <span class="job-status status-<?php echo $job['status']; ?>">
                        <?php echo ucfirst($job['status']); ?>
                    </span>
                </div>
                
                <div class="job-content">
                    <div class="job-details">
                        <?php if(!empty($job['salary_min']) && !empty($job['salary_max'])): ?>
                            <div class="detail-item">
                                <div class="detail-label">Salary Range</div>
                                <div class="detail-value">
                                    <?php echo $job['salary_currency'] . ' ' . number_format($job['salary_min']) . ' - ' . number_format($job['salary_max']) . '/month'; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-item">
                            <div class="detail-label">Location</div>
                            <div class="detail-value"><?php echo htmlspecialchars($job['location']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Job Type</div>
                            <div class="detail-value"><?php echo ucfirst($job['job_type']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Category</div>
                            <div class="detail-value"><?php echo htmlspecialchars($job['category']); ?></div>
                        </div>
                        
                        <?php if(!empty($job['application_deadline'])): ?>
                            <div class="detail-item">
                                <div class="detail-label">Application Deadline</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($job['application_deadline'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="job-section">
                        <h3 class="section-title">Job Description</h3>
                        <div class="section-content">
                            <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                        </div>
                    </div>
                    
                    <?php if(!empty($job['requirements'])): ?>
                        <div class="job-section">
                            <h3 class="section-title">Requirements</h3>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($job['responsibilities'])): ?>
                        <div class="job-section">
                            <h3 class="section-title">Responsibilities</h3>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($job['responsibilities'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="job-section">
                        <h3 class="section-title">Employer Information</h3>
                        <div class="employer-info">
                            <div class="employer-section">
                                <div class="detail-label">Company</div>
                                <div class="detail-value"><?php echo htmlspecialchars($job['company_name']); ?></div>
                            </div>
                            
                            <div class="employer-section">
                                <div class="detail-label">Industry</div>
                                <div class="detail-value"><?php echo htmlspecialchars($job['industry'] ?? 'Not specified'); ?></div>
                            </div>
                            
                            <div class="employer-section">
                                <div class="detail-label">Contact Person</div>
                                <div class="detail-value"><?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></div>
                            </div>
                            
                            <div class="employer-section">
                                <div class="detail-label">Contact Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($job['email']); ?></div>
                            </div>
                            
                            <?php if(!empty($job['phone'])): ?>
                                <div class="employer-section">
                                    <div class="detail-label">Contact Phone</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($job['phone']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($job['website'])): ?>
                                <div class="employer-section">
                                    <div class="detail-label">Website</div>
                                    <div class="detail-value">
                                        <a href="<?php echo htmlspecialchars($job['website']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($job['website']); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>