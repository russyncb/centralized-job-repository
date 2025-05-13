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

// Get similar jobs with better prioritization
$query = "SELECT j.job_id, j.title, j.location, j.job_type, j.posted_at, e.company_name, e.company_logo,
         CASE 
            WHEN j.category = ? THEN 2  -- Exact category match gets highest priority
            WHEN j.category IN (
                SELECT DISTINCT j2.category 
                FROM applications a 
                JOIN jobs j2 ON a.job_id = j2.job_id 
                WHERE a.jobseeker_id = ?
            ) THEN 1  -- Categories user has applied to before get second priority
            ELSE 0    -- Other categories get lowest priority
         END as match_score
         FROM jobs j
         JOIN employer_profiles e ON j.employer_id = e.employer_id
         WHERE j.status = 'active' 
         AND j.job_id != ?
         AND (j.application_deadline IS NULL OR j.application_deadline >= CURDATE())
         ORDER BY match_score DESC, j.posted_at DESC
         LIMIT 6";

$stmt = $db->prepare($query);
$stmt->bindParam(1, $job['category']);  // Current job's category
$stmt->bindParam(2, $jobseeker_id);     // Jobseeker ID for history matching
$stmt->bindParam(3, $job_id);           // Exclude current job
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
            padding: 25px 30px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            color: white;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .back-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ffffff;
            text-decoration: none;
            font-size: 1.1rem;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-3px);
            color: white;
            text-decoration: none;
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
            background: linear-gradient(135deg, #1a5276 0%, #154360 100%);
            padding: 30px;
            border-radius: 12px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .job-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: white;
        }

        .company-headline {
            color: #FFD700;
            font-size: 1rem;
            margin: 8px 0;
            font-weight: 500;
        }
        
        .company-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
        }
        
        .company-logo img {
            max-width: 60px;
            max-height: 60px;
            border-radius: 8px;
        }
        
        .company-details h2 {
            font-size: 1.2rem;
            color: white;
            margin-bottom: 5px;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .meta-item i {
            font-size: 1.2rem;
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
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn-apply {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            border: none;
            flex: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-apply:hover {
            background: linear-gradient(135deg, #27ae60 0%, #219a52 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.2);
        }

        .btn-saved {
            background: linear-gradient(135deg, #27ae60 0%, #219a52 100%);
            color: white;
            border: none;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-save {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-save:hover {
            background: linear-gradient(135deg, #2980b9 0%, #2471a3 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
        }
        
        .job-info {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #eef2f7;
        }
        
        .info-section {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eef2f7;
        }
        
        .info-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a5276;
            font-size: 0.95rem;
        }
        
        .info-value {
            color: #2c3e50;
            font-size: 1.05rem;
        }
        
        .company-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .company-section-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #1a5276;
            color: #1a5276;
            font-weight: 600;
        }
        
        .company-info {
            margin-bottom: 15px;
        }
        
        .similar-jobs {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 25px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .similar-jobs::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 50%, #93c5fd 100%);
        }
        
        .similar-jobs-title {
            font-size: 1.4rem;
            color: #1e293b;
            margin-bottom: 25px;
            font-weight: 600;
            position: relative;
        }
        
        .recommended-jobs-container {
            position: relative;
            min-height: 200px;
        }
        
        .similar-job-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            transition: opacity 0.3s ease, transform 0.3s ease;
            display: none;
            opacity: 0;
            transform: translateX(20px);
        }
        
        .similar-job-item.active {
            display: block;
            opacity: 1;
            transform: translateX(0);
        }
        
        .similar-job-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            border-color: #cbd5e1;
        }
        
        .similar-job-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .company-logo-small {
            width: 48px;
            height: 48px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .company-logo-small img {
            max-width: 36px;
            max-height: 36px;
            object-fit: contain;
        }
        
        .company-logo-small span {
            font-size: 1.2rem;
            font-weight: 600;
            color: #64748b;
        }
        
        .similar-job-info {
            flex: 1;
        }
        
        .similar-job-title a {
            color: #1e293b;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            transition: color 0.2s ease;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .similar-job-title a:hover {
            color: #3b82f6;
        }
        
        .similar-job-company {
            color: #64748b;
            font-size: 0.95rem;
            margin-top: 4px;
        }
        
        .similar-job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 12px 0;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .meta-item i {
            font-size: 1rem;
            color: #94a3b8;
        }
        
        .similar-job-actions {
            margin-top: 15px;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            color: white;
            text-decoration: none;
        }
        
        .job-navigation {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .nav-dots {
            display: flex;
            gap: 10px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .nav-dot {
            width: 10px;
            height: 10px;
            background: #e2e8f0;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .nav-dot:hover {
            background: #94a3b8;
            transform: scale(1.2);
        }
        
        .nav-dot.active {
            background: #3b82f6;
            transform: scale(1.2);
        }
        
        .match-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-top: 8px;
        }
        
        .perfect-match {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        
        .similar-match {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #3b82f6;
            border: 1px solid #bfdbfe;
        }
        
        .match-icon {
            font-size: 1rem;
        }
        
        @media (max-width: 768px) {
            .job-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
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
                <div class="top-bar-left">
                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="back-link">
                        <span>←</span> Back to Jobs
                    </a>
                </div>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?>
                    </div>
                    <?php if(!empty($jobseeker['headline'])): ?>
                        <div class="professional-headline"><?php echo htmlspecialchars($jobseeker['headline']); ?></div>
                    <?php endif; ?>
                </div>
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
                                <?php if(!empty($job['headline'])): ?>
                                    <div class="company-headline"><?php echo htmlspecialchars($job['headline']); ?></div>
                                <?php endif; ?>
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
                            <span class="btn btn-applied">✓ Already Applied</span>
                        <?php elseif($deadline_passed): ?>
                            <span class="btn btn-disabled">Application Closed</span>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/apply-job.php?id=<?php echo $job_id; ?>" class="btn btn-apply">
                                <span>📝</span> Apply Now
                            </a>
                        <?php endif; ?>
                        
                        <?php if($job['is_saved'] > 0): ?>
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/save-job.php?id=<?php echo $job_id; ?>&action=remove&redirect=view" class="btn btn-saved">
                                <span>✓</span> Saved
                            </a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/save-job.php?id=<?php echo $job_id; ?>&action=save&redirect=view" class="btn btn-save">
                                <span>💾</span> Save Job
                            </a>
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
                            <h3 class="similar-jobs-title">Recommended Jobs</h3>
                            <div class="recommended-jobs-container" id="recommendedJobsContainer">
                                <?php foreach($similar_jobs as $index => $similar_job): ?>
                                    <div class="similar-job-item <?php echo $index === 0 ? 'active' : ''; ?>" data-job-index="<?php echo $index; ?>" data-match-score="<?php echo $similar_job['match_score']; ?>">
                                        <div class="similar-job-header">
                                            <div class="company-logo-small">
                                                <?php if(!empty($similar_job['company_logo'])): ?>
                                                    <img src="<?php echo SITE_URL . '/' . $similar_job['company_logo']; ?>" alt="Company Logo">
                                                <?php else: ?>
                                                    <span><?php echo strtoupper(substr($similar_job['company_name'], 0, 1)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="similar-job-info">
                                                <div class="similar-job-title">
                                                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $similar_job['job_id']; ?>">
                                                        <?php echo htmlspecialchars($similar_job['title']); ?>
                                                    </a>
                                                </div>
                                                <div class="similar-job-company"><?php echo htmlspecialchars($similar_job['company_name']); ?></div>
                                            </div>
                                        </div>
                                        <div class="similar-job-meta">
                                            <span class="meta-item"><i>📍</i> <?php echo htmlspecialchars($similar_job['location']); ?></span>
                                            <span class="meta-item"><i>💼</i> <?php echo ucfirst($similar_job['job_type']); ?></span>
                                            <span class="meta-item"><i>📅</i> <?php echo date('M d', strtotime($similar_job['posted_at'])); ?></span>
                                        </div>
                                        <div class="similar-job-actions">
                                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $similar_job['job_id']; ?>" class="btn btn-view">View Job</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="job-navigation">
                                <div class="nav-dots">
                                    <?php for($i = 0; $i < count($similar_jobs); $i++): ?>
                                        <span class="nav-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></span>
                                    <?php endfor; ?>
                                </div>
                            </div>
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

            // Recommended Jobs Rotation with longer interval
            const jobItems = document.querySelectorAll('.similar-job-item');
            const navDots = document.querySelectorAll('.nav-dot');
            let currentIndex = 0;
            const rotationInterval = 60000; // 1 minute in milliseconds

            function showJob(index) {
                // Add fade out effect to current job
                if (jobItems[currentIndex]) {
                    jobItems[currentIndex].style.opacity = '0';
                    jobItems[currentIndex].style.transform = 'translateX(20px)';
                }

                setTimeout(() => {
                    jobItems.forEach(item => {
                        item.classList.remove('active');
                    });
                    navDots.forEach(dot => dot.classList.remove('active'));

                    jobItems[index].classList.add('active');
                    navDots[index].classList.add('active');

                    // Trigger reflow to ensure animation plays
                    jobItems[index].offsetHeight;

                    // Add fade in effect to new job
                    jobItems[index].style.opacity = '1';
                    jobItems[index].style.transform = 'translateX(0)';
                }, 300); // Wait for fade out to complete
            }

            function rotateJobs() {
                currentIndex = (currentIndex + 1) % jobItems.length;
                showJob(currentIndex);
            }

            // Initialize first job with fade in
            if (jobItems.length > 0) {
                jobItems[0].style.opacity = '1';
                jobItems[0].style.transform = 'translateX(0)';
            }

            // Initialize rotation
            let rotationTimer = setInterval(rotateJobs, rotationInterval);

            // Add click handlers for navigation dots
            navDots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    clearInterval(rotationTimer);
                    currentIndex = index;
                    showJob(currentIndex);
                    rotationTimer = setInterval(rotateJobs, rotationInterval);
                });
            });

            // Pause rotation on hover
            const container = document.getElementById('recommendedJobsContainer');
            container.addEventListener('mouseenter', () => clearInterval(rotationTimer));
            container.addEventListener('mouseleave', () => {
                rotationTimer = setInterval(rotateJobs, rotationInterval);
            });

            // Add visual indicator for match score
            const jobCards = document.querySelectorAll('.similar-job-item');
            jobCards.forEach(card => {
                const matchScore = parseInt(card.dataset.matchScore);
                let matchLabel = '';
                let matchClass = '';
                
                if (matchScore === 2) {
                    matchLabel = 'Perfect Category Match';
                    matchClass = 'perfect-match';
                } else if (matchScore === 1) {
                    matchLabel = 'Similar Category';
                    matchClass = 'similar-match';
                }
                
                if (matchLabel) {
                    const matchDiv = document.createElement('div');
                    matchDiv.className = `match-indicator ${matchClass}`;
                    matchDiv.innerHTML = `<span class="match-icon">✨</span> ${matchLabel}`;
                    card.querySelector('.similar-job-header').appendChild(matchDiv);
                }
            });
        });
    </script>
</body>
</html>