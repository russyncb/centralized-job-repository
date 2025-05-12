<?php
// Set page title
$page_title = 'View Job';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has jobseeker role
if(!has_role('jobseeker')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Check if job ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    redirect(SITE_URL . '/views/jobseeker/search-jobs.php', 'Job ID is required.', 'error');
}

$job_id = $_GET['id'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get jobseeker ID
$query = "SELECT jp.*, u.first_name, u.last_name, u.email 
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

// Get job details
$query = "SELECT j.*, e.company_name, e.company_logo, e.industry, e.website, e.description as company_description, 
         (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id AND a.jobseeker_id = ?) as has_applied,
         (SELECT COUNT(*) FROM saved_jobs s WHERE s.job_id = j.job_id AND s.jobseeker_id = ?) as is_saved
         FROM jobs j
         JOIN employer_profiles e ON j.employer_id = e.employer_id
         WHERE j.job_id = ? AND j.status = 'active'";

$stmt = $db->prepare($query);
$stmt->bindParam(1, $jobseeker_id);
$stmt->bindParam(2, $jobseeker_id);
$stmt->bindParam(3, $job_id);
$stmt->execute();

$job = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$job) {
    redirect(SITE_URL . '/views/jobseeker/search-jobs.php', 'Job not found or no longer active.', 'error');
}

// Get similar jobs
$query = "SELECT j.job_id, j.title, j.location, j.job_type, j.posted_at, e.company_name, e.company_logo
         FROM jobs j
         JOIN employer_profiles e ON j.employer_id = e.employer_id
         WHERE j.status = 'active' AND j.job_id != ? AND j.category = ?
         ORDER BY j.posted_at DESC
         LIMIT 3";

$stmt = $db->prepare($query);
$stmt->bindParam(1, $job_id);
$stmt->bindParam(2, $job['category']);
$stmt->execute();

$similar_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if application deadline has passed
$deadline_passed = false;
if(!empty($job['application_deadline'])) {
    $deadline = new DateTime($job['application_deadline']);
    $today = new DateTime();
    $deadline_passed = $today > $deadline;
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
        /* View Job Styles */
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
            position: relative;
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
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            font-size: 0.95rem;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border-left-color: #fff;
        }
        
        .sidebar-menu a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .sidebar-toggle {
            position: fixed;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            background: #1557b0;
            color: white;
            border: none;
            width: 24px;
            height: 40px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            padding: 0;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            background: #1a3b5d;
        }
        
        .sidebar.collapsed {
            margin-left: -250px;
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 0;
        }
        
        .sidebar.collapsed ~ .sidebar-toggle {
            left: 0;
            transform: translateY(-50%) rotate(180deg);
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
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 20px;
        }
        
        .job-details {
            margin-bottom: 20px;
        }
        
        .job-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .job-header {
            padding: 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: flex-start;
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            background-color: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-right: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .company-logo img {
            max-width: 60px;
            max-height: 60px;
        }
        
        .job-header-info {
            flex: 1;
        }
        
        .job-title {
            font-size: 1.8rem;
            margin: 0 0 5px;
        }
        
        .company-name {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 15px;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            color: #666;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
        }
        
        .meta-icon {
            margin-right: 5px;
        }
        
        .job-body {
            padding: 25px;
        }
        
        .job-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #333;
        }
        
        .section-content {
            line-height: 1.6;
            color: #444;
        }
        
        .job-actions {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95rem;
            text-decoration: none;
            width: 100%;
            text-align: center;
            margin-bottom: 10px;
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
        
        .btn-applied {
            background-color: #e8f5e9;
            color: #388e3c;
            cursor: default;
        }
        
        .btn-saved {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-disabled {
            background-color: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }
        
        .job-info {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-section {
            margin-bottom: 20px;
        }
        
        .info-section:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #555;
        }
        
        .info-value {
            color: #333;
        }
        
        .company-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .company-section-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        
        .company-info {
            margin-bottom: 15px;
        }
        
        .similar-jobs {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
        }
        
        .similar-jobs-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        
        .similar-job-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .similar-job-item:last-child {
            border-bottom: none;
        }
        
        .similar-job-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .similar-job-title a {
            color: #0056b3;
            text-decoration: none;
        }
        
        .similar-job-title a:hover {
            text-decoration: underline;
        }
        
        .similar-job-company {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .similar-job-meta {
            color: #666;
            font-size: 0.85rem;
        }
        
        .deadline-alert {
            background-color: #fbe9e7;
            color: #d32f2f;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
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
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/dashboard.php"><i>📊</i><span>Dashboard</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php"><i>👤</i><span>My Profile</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="active"><i>🔍</i><span>Search Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php"><i>💾</i><span>Saved Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php"><i>📝</i><span>My Applications</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i><span>Logout</span></a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="back-link">
                    <span class="back-icon">←</span> Back to Jobs
                </a>
            </div>
            
            <div class="job-container">
                <div class="job-details">
                    <div class="job-card">
                        <div class="job-header">
                            <div class="company-logo">
                                <?php if(!empty($job['company_logo'])): ?>
                                    <img src="<?php echo SITE_URL . '/' . $job['company_logo']; ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?> Logo">
                                <?php else: ?>
                                    <span><?php echo strtoupper(substr($job['company_name'], 0, 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="job-header-info">
                                <h1 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h1>
                                <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></div>
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
                                        <span class="meta-icon">📅</span>
                                        <span>Posted <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
                                    </div>
                                    <?php if(!empty($job['application_deadline'])): ?>
                                        <div class="meta-item">
                                            <span class="meta-icon">⏱️</span>
                                            <span>Deadline: <?php echo date('M d, Y', strtotime($job['application_deadline'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="job-body">
                            <?php if($deadline_passed): ?>
                                <div class="deadline-alert">
                                    The application deadline for this job has passed.
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
                                <div class="job-section">
                                    <h3 class="section-title">Salary</h3>
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
                            
                            <div class="job-section">
                                <h3 class="section-title">Job Description</h3>
                                <div class="section-content">
                                    <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                                </div>
                            </div>
                            
                            <?php if(!empty($job['responsibilities'])): ?>
                                <div class="job-section">
                                    <h3 class="section-title">Responsibilities</h3>
                                    <div class="section-content">
                                        <?php echo nl2br(htmlspecialchars($job['responsibilities'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($job['requirements'])): ?>
                                <div class="job-section">
                                    <h3 class="section-title">Requirements</h3>
                                    <div class="section-content">
                                        <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="job-sidebar">
                    <div class="job-actions">
                        <?php if($job['has_applied'] > 0): ?>
                            <span class="btn btn-applied">Applied</span>
                        <?php elseif($deadline_passed): ?>
                            <span class="btn btn-disabled">Application Closed</span>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/apply-job.php?id=<?php echo $job_id; ?>" class="btn btn-primary">Apply Now</a>
                        <?php endif; ?>
                        
                        <?php if($job['is_saved'] > 0): ?>
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/save-job.php?id=<?php echo $job_id; ?>&action=remove&redirect=view" class="btn btn-saved">Saved</a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/save-job.php?id=<?php echo $job_id; ?>&action=save&redirect=view" class="btn btn-outline">Save Job</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="job-info">
                        <div class="info-section">
                            <div class="info-label">Category</div>
                            <div class="info-value"><?php echo htmlspecialchars($job['category']); ?></div>
                        </div>
                        
                        <div class="info-section">
                            <div class="info-label">Job Type</div>
                            <div class="info-value"><?php echo ucfirst($job['job_type']); ?></div>
                        </div>
                        
                        <div class="info-section">
                            <div class="info-label">Location</div>
                            <div class="info-value"><?php echo htmlspecialchars($job['location']); ?></div>
                        </div>
                        
                        <div class="info-section">
                            <div class="info-label">Posted Date</div>
                            <div class="info-value"><?php echo date('F d, Y', strtotime($job['posted_at'])); ?></div>
                        </div>
                        
                        <?php if(!empty($job['application_deadline'])): ?>
                            <div class="info-section">
                                <div class="info-label">Application Deadline</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($job['application_deadline'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="company-section">
                        <h3 class="company-section-title">About the Company</h3>
                        <div class="company-info">
                            <div class="info-section">
                                <div class="info-label">Company</div>
                                <div class="info-value"><?php echo htmlspecialchars($job['company_name']); ?></div>
                            </div>
                            
                            <?php if(!empty($job['industry'])): ?>
                                <div class="info-section">
                                    <div class="info-label">Industry</div>
                                    <div class="info-value"><?php echo htmlspecialchars($job['industry']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($job['website'])): ?>
                                <div class="info-section">
                                    <div class="info-label">Website</div>
                                    <div class="info-value">
                                        <a href="<?php echo htmlspecialchars($job['website']); ?>" target="_blank"><?php echo htmlspecialchars($job['website']); ?></a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if(!empty($job['company_description'])): ?>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($job['company_description'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(count($similar_jobs) > 0): ?>
                        <div class="similar-jobs">
                            <h3 class="similar-jobs-title">Similar Jobs</h3>
                            <?php foreach($similar_jobs as $similar_job): ?>
                                <div class="similar-job-item">
                                    <div class="similar-job-title">
                                        <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $similar_job['job_id']; ?>">
                                            <?php echo htmlspecialchars($similar_job['title']); ?>
                                        </a>
                                    </div>
                                    <div class="similar-job-company"><?php echo htmlspecialchars($similar_job['company_name']); ?></div>
                                    <div class="similar-job-meta">
                                        <?php echo htmlspecialchars($similar_job['location']); ?> • <?php echo ucfirst($similar_job['job_type']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
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

            // Add confirmation for logout
            const logoutLink = document.querySelector('a[href*="logout.php"]');
            if(logoutLink) {
                logoutLink.addEventListener('click', function(e) {
                    if(!confirm('Are you sure you want to logout?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>