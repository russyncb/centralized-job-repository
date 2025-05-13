<?php
// Set page title
$page_title = 'View Job';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has employer role
if(!has_role('employer')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Check if job ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    redirect(SITE_URL . '/views/employer/manage-jobs.php', 'Job ID is required.', 'error');
}

$job_id = $_GET['id'];

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

// Get job details
$query = "SELECT * FROM jobs WHERE job_id = ? AND employer_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $job_id);
$stmt->bindParam(2, $employer_id);
$stmt->execute();

$job = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$job) {
    redirect(SITE_URL . '/views/employer/manage-jobs.php', 'Job not found or you do not have permission to view it.', 'error');
}

// Get application count for this job
$query = "SELECT COUNT(*) as count, 
         SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
         SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
         SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
         SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired
         FROM applications 
         WHERE job_id = ?";
         
$stmt = $db->prepare($query);
$stmt->bindParam(1, $job_id);
$stmt->execute();
$applications = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* View Job Styles */
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
        
        .job-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .job-header {
            padding: 25px;
            border-bottom: 1px solid #eee;
            position: relative;
        }
        
        .job-title {
            font-size: 1.8rem;
            margin: 0 0 10px;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
            color: #666;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
        }
        
        .meta-icon {
            margin-right: 5px;
            color: #0056b3;
        }
        
        .job-status {
            position: absolute;
            top: 25px;
            right: 25px;
            padding: 6px 12px;
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
            color: #333;
        }
        
        .job-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            background-color: #0056b3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.95rem;
            cursor: pointer;
        }
        
        .btn-action:hover {
            background-color: #004494;
            text-decoration: none;
            color: white;
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
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .application-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 15px;
            text-align: center;
            flex: 1;
            min-width: 120px;
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
        
        .stat-total {
            border-bottom: 3px solid #0056b3;
        }
        
        .stat-pending {
            border-bottom: 3px solid #0097a7;
        }
        
        .stat-shortlisted {
            border-bottom: 3px solid #388e3c;
        }
        
        .stat-rejected {
            border-bottom: 3px solid #d32f2f;
        }
        
        .stat-hired {
            border-bottom: 3px solid #1976d2;
        }
    </style>
</head>
<body>
    <div class="employer-container">
        <?php include 'employer-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php" class="back-link">
                    <span class="back-icon">←</span> Back to Jobs
                </a>
            </div>
            
            <div class="job-container">
                <div class="job-header">
                    <h1 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h1>
                    
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
                            <span class="meta-icon">📅</span>
                            <span>Posted on <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
                        </div>
                        <?php if(!empty($job['application_deadline'])): ?>
                            <div class="meta-item">
                                <span class="meta-icon">⏰</span>
                                <span>Deadline: <?php echo date('M d, Y', strtotime($job['application_deadline'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <span class="job-status status-<?php echo $job['status']; ?>">
                        <?php echo ucfirst($job['status']); ?>
                    </span>
                </div>
                
                <div class="job-content">
                    <div class="application-stats">
                        <div class="stat-card stat-total">
                            <div class="stat-number"><?php echo $applications['count']; ?></div>
                            <div class="stat-label">Total Applications</div>
                        </div>
                        <div class="stat-card stat-pending">
                            <div class="stat-number"><?php echo $applications['pending']; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-card stat-shortlisted">
                            <div class="stat-number"><?php echo $applications['shortlisted']; ?></div>
                            <div class="stat-label">Shortlisted</div>
                        </div>
                        <div class="stat-card stat-rejected">
                            <div class="stat-number"><?php echo $applications['rejected']; ?></div>
                            <div class="stat-label">Rejected</div>
                        </div>
                        <div class="stat-card stat-hired">
                            <div class="stat-number"><?php echo $applications['hired']; ?></div>
                            <div class="stat-label">Hired</div>
                        </div>
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
                    
                    <?php if(!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
                        <div class="job-section">
                            <h3 class="section-title">Compensation</h3>
                            <div class="section-content">
                                <?php
                                $salary = '';
                                if(!empty($job['salary_min']) && !empty($job['salary_max'])) {
                                    $salary = number_format($job['salary_min']) . ' - ' . number_format($job['salary_max']);
                                } elseif(!empty($job['salary_min'])) {
                                    $salary = 'From ' . number_format($job['salary_min']);
                                } elseif(!empty($job['salary_max'])) {
                                    $salary = 'Up to ' . number_format($job['salary_max']);
                                }
                                
                                if(!empty($salary)) {
                                    echo $job['salary_currency'] . ' ' . $salary . ' per month';
                                }
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="job-actions">
                        <a href="<?php echo SITE_URL; ?>/views/employer/edit-job.php?id=<?php echo $job_id; ?>" class="btn-action">
                            <span class="icon">✏️</span> Edit Job
                        </a>
                        <a href="<?php echo SITE_URL; ?>/views/employer/applications.php?job_id=<?php echo $job_id; ?>" class="btn-action">
                            <span class="icon">📋</span> View Applications (<?php echo $applications['count']; ?>)
                        </a>
                        <?php if($job['status'] == 'active'): ?>
                            <form method="post" action="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php" style="display:inline;">
                                <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                                <input type="hidden" name="action" value="close">
                                <button type="submit" class="btn-action btn-secondary">
                                    <span class="icon">🛑</span> Close Job
                                </button>
                            </form>
                        <?php elseif($job['status'] != 'active'): ?>
                            <form method="post" action="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php" style="display:inline;">
                                <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="btn-action">
                                    <span class="icon">▶️</span> Activate Job
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this job? This action cannot be undone.');">
                            <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn-action btn-danger">
                                <span class="icon">🗑️</span> Delete Job
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>