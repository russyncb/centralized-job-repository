<?php
// Set page title
$page_title = 'Jobseeker Dashboard';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has jobseeker role
if(!has_role('jobseeker')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get jobseeker profile
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
}

$jobseeker_id = $jobseeker['jobseeker_id'];

// Get total applications count
$query = "SELECT COUNT(*) as total FROM applications WHERE jobseeker_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $jobseeker_id);
$stmt->execute();
$total_applications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent applications
$query = "SELECT a.*, j.title as job_title, j.location as job_location, j.job_type,
         e.company_name, e.company_logo
         FROM applications a
         JOIN jobs j ON a.job_id = j.job_id
         JOIN employer_profiles e ON j.employer_id = e.employer_id
         WHERE a.jobseeker_id = ?
         ORDER BY a.applied_at DESC
         LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $jobseeker_id);
$stmt->execute();
$recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get saved jobs count
$query = "SELECT COUNT(*) as total FROM saved_jobs WHERE jobseeker_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $jobseeker_id);
$stmt->execute();
$saved_jobs_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total active jobs (excluding expired)
$total_jobs = $db->query("SELECT COUNT(*) as total FROM jobs 
    WHERE status = 'active' 
    AND (application_deadline IS NULL OR application_deadline >= CURDATE())")->fetch(PDO::FETCH_ASSOC)['total'];

// Get new jobs this week (excluding expired)
$new_jobs = $db->query("SELECT COUNT(*) as total FROM jobs 
    WHERE status = 'active' 
    AND posted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND (application_deadline IS NULL OR application_deadline >= CURDATE())")->fetch(PDO::FETCH_ASSOC)['total'];

// Get recommended jobs based on category preferences and recency
$query = "SELECT j.*, e.company_name, e.company_logo,
         CASE 
            WHEN j.category IN (
                SELECT DISTINCT j2.category 
                FROM applications a 
                JOIN jobs j2 ON a.job_id = j2.job_id 
                WHERE a.jobseeker_id = ?
            ) THEN 1 
            ELSE 0 
         END as category_match,
         CASE
            WHEN j.application_deadline IS NOT NULL AND j.application_deadline < CURDATE() THEN 'expired'
            ELSE 'active'
         END as deadline_status
         FROM jobs j
         JOIN employer_profiles e ON j.employer_id = e.employer_id
         WHERE j.status = 'active'
         AND (j.application_deadline IS NULL OR j.application_deadline >= CURDATE())
         AND j.job_id NOT IN (SELECT job_id FROM applications WHERE jobseeker_id = ?)
         ORDER BY category_match DESC, j.posted_at DESC
         LIMIT 6";

$stmt = $db->prepare($query);
$stmt->bindParam(1, $jobseeker_id);
$stmt->bindParam(2, $jobseeker_id);
$stmt->execute();
$recommended_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if profile is complete
$profile_fields = [
    'resume' => 'Resume',
    'headline' => 'Professional Headline',
    'education_level' => 'Education Level',
    'experience_years' => 'Experience Years',
    'skills' => 'Skills'
];

$missing_fields = [];
foreach($profile_fields as $field => $label) {
    if(empty($jobseeker[$field])) {
        $missing_fields[] = $label;
    }
}

$profile_complete = empty($missing_fields);
$profile_completion = 100 - (count($missing_fields) * 20);
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
        /* Sidebar Modernization */
        .sidebar {
            position: relative;
            width: 250px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: #fff;
            padding: 0;
            box-shadow: 2px 0 8px rgba(0,0,0,0.07);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: width 0.3s ease;
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
        /* Dashboard Job Opportunities */
        .dashboard-jobs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        .dashboard-job-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            color: #1a3b5d;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #eef2f7;
            text-decoration: none;
        }
        .dashboard-job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-decoration: none;
        }
        .dashboard-job-card .icon {
            font-size: 1.8rem;
            margin-bottom: 12px;
            color: rgba(255,255,255,0.9);
        }
        .dashboard-job-card .count {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: inherit;
        }
        .dashboard-job-card .label {
            font-size: 1rem;
            color: rgba(255,255,255,0.9);
            margin-bottom: 12px;
            font-weight: 500;
        }
        .dashboard-job-card .btn {
            margin-top: auto;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
        }
        .dashboard-job-card .btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: rgba(255,255,255,0.5);
        }
        .dashboard-job-card .btn::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: -100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255,255,255,0.2),
                transparent
            );
            transition: 0.5s;
        }
        .dashboard-job-card .btn:hover::after {
            left: 100%;
        }
        .dashboard-job-card.saved .btn:hover {
            background: rgba(231,76,60,0.25);
        }
        .dashboard-job-card.jobs .btn:hover {
            background: rgba(52,152,219,0.25);
        }
        .dashboard-job-card.new .btn:hover {
            background: rgba(46,204,113,0.25);
        }
        .dashboard-job-card.jobs {
            background: linear-gradient(135deg, #1a5276 0%, #154360 100%);
            color: white;
        }
        .dashboard-job-card.new {
            background: linear-gradient(135deg, #196f3d 0%, #145a32 100%);
            color: white;
        }
        .dashboard-job-card.saved {
            background: linear-gradient(135deg, #922b1c 0%, #7b241c 100%);
            color: white;
        }
        .profile-complete-badge {
            display: inline-block;
            background: #e3fcec;
            color: #1a7f37;
            font-weight: 600;
            border-radius: 20px;
            padding: 6px 18px;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        /* Jobseeker Dashboard Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        
        .jobseeker-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 30px;
            background: #f8fafc;
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

        .professional-headline {
            color: #FFD700;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
        }
        
        .company-info:hover .professional-headline {
            white-space: normal;
            word-wrap: break-word;
            position: relative;
            z-index: 10;
            background: rgba(0, 0, 0, 0.8);
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .top-bar h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #ffffff;
            font-weight: 600;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .profile-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #e3f2fd;
            color: #1976d2;
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
        }
        
        .profile-info h2 {
            margin: 0 0 5px;
            font-size: 1.5rem;
        }
        
        .profile-headline {
            color: #666;
            font-size: 1rem;
        }
        
        .profile-completion {
            display: none;
        }
        
        .progress-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress {
            height: 100%;
            background-color: #0056b3;
            border-radius: 4px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #666;
        }
        
        .profile-actions {
            margin-top: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95rem;
            text-decoration: none;
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
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            text-align: center;
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #0056b3;
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
        
        .section-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 1.2rem;
            color: #333;
        }
        
        .section-title .icon {
            margin-right: 8px;
            color: #0056b3;
        }
        
        .application-list {
            margin-bottom: 20px;
        }
        
        .application-item {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid #eef2f7;
        }
        
        .application-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .company-logo {
            width: 56px;
            height: 56px;
            background-color: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .company-logo img {
            max-width: 40px;
            max-height: 40px;
            border-radius: 8px;
        }
        
        .application-details {
            flex: 1;
        }
        
        .job-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }
        
        .company-name {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 8px;
        }
        
        .application-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .application-status {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-left: auto;
        }
        
        .status-pending {
            background-color: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }
        
        .status-accepted {
            background-color: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        
        .status-rejected {
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        .status-shortlisted {
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        
        .view-all {
            text-align: center;
            margin-top: 20px;
        }
        
        .job-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            padding: 15px;
            border: 1px solid #eef2f7;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            max-height: 250px;
            overflow: hidden;
        }

        .job-card:nth-child(3n+1) {
            background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);
            border-color: rgba(25, 118, 210, 0.1);
        }

        .job-card:nth-child(3n+2) {
            background: linear-gradient(135deg, #f3e5f5 0%, #ffffff 100%);
            border-color: rgba(156, 39, 176, 0.1);
        }

        .job-card:nth-child(3n+3) {
            background: linear-gradient(135deg, #e8f5e9 0%, #ffffff 100%);
            border-color: rgba(76, 175, 80, 0.1);
        }
        
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .job-header {
            margin-bottom: 12px;
            position: relative;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        
        .job-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .company-name {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .company-name:before {
            content: '•';
            color: #94a3b8;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 8px;
            padding: 8px 0;
        }

        .job-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(255,255,255,0.5);
            padding: 4px 8px;
            border-radius: 12px;
            backdrop-filter: blur(4px);
        }
        
        .deadline-tag {
            font-size: 0.8rem;
            padding: 4px 8px;
            margin-top: auto;
            background: rgba(52, 211, 153, 0.1);
            color: #059669;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .category-match {
            font-size: 0.8rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
            color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
            padding: 4px 8px;
            border-radius: 12px;
            width: fit-content;
        }

        .category-match .icon {
            color: #f59e0b;
        }
        
        .recent-applications {
            background: linear-gradient(135deg, #EBF3FE 0%, #F5F9FF 50%, #EBF3FE 100%);
            border-radius: 16px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.03);
            padding: 30px;
            border: 1px solid rgba(147, 197, 253, 0.15);
            position: relative;
            overflow: hidden;
        }

        .recent-applications::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 50%, #93c5fd 100%);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            position: relative;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            position: relative;
            padding-bottom: 10px;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
            border-radius: 2px;
        }

        .recommended-jobs-container {
            position: relative;
            overflow: hidden;
        }
        
        .job-grid {
            position: relative;
            margin-top: 20px;
        }
        
        .job-group {
            display: none;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(2, 1fr);
            gap: 24px;
            opacity: 0;
            transform: translateX(100%);
            transition: transform 0.5s ease, opacity 0.5s ease;
        }
        
        .job-group.active {
            display: grid;
            opacity: 1;
            transform: translateX(0);
        }
        
        .job-group.prev {
            transform: translateX(-100%);
        }
        
        .job-group.next {
            transform: translateX(100%);
        }
        
        .job-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #eef2f7;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            height: 100%;
        }
        
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
            z-index: 1000;
            border: none;
            color: #1a3b5d;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar.collapsed .sidebar-toggle {
            transform: rotate(180deg);
            right: -16px;
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
        <!-- Job Market Analytics Link -->
        <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/job-analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'job-analytics.php' ? 'active' : ''; ?>"><i>📈</i><span>Job Market Analytics</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i><span>Logout</span></a></li>
    </ul>
</div>
        <div class="main-content">
            <div class="top-bar">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?>
                    </div>
                    <?php if(!empty($jobseeker['headline'])): ?>
                        <div class="company-info">
                            <span class="professional-headline"><?php echo htmlspecialchars($jobseeker['headline']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dashboard-jobs">
                <a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php" class="dashboard-job-card saved">
                    <span class="icon">💾</span>
                    <span class="count"><?php echo $saved_jobs_count > 99 ? '100+' : $saved_jobs_count; ?></span>
                    <span class="label">Saved Jobs</span>
                    <span class="btn">View Saved</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="dashboard-job-card jobs">
                    <span class="icon">💼</span>
                    <span class="count"><?php echo $total_jobs > 99 ? '100+' : $total_jobs; ?></span>
                    <span class="label">Active Jobs</span>
                    <span class="btn">Browse Jobs</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="dashboard-job-card new">
                    <span class="icon">🆕</span>
                    <span class="count"><?php echo $new_jobs > 99 ? '100+' : $new_jobs; ?></span>
                    <span class="label">New This Week</span>
                    <span class="btn">View New Jobs</span>
                </a>
            </div>

            <div class="recommended-jobs">
                <div class="section-header">
                    <div class="section-title">Recommended Jobs</div>
                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="btn btn-outline">View All Jobs</a>
                </div>
                <div class="job-grid">
                    <?php 
                    // Split jobs into groups of 6 (2x3 grid)
                    $job_groups = array_chunk($recommended_jobs, 6);
                    ?>
                    <div class="recommended-jobs-container" id="recommendedJobsContainer">
                        <?php foreach($job_groups as $index => $group): ?>
                            <div class="job-group <?php echo $index === 0 ? 'active' : ''; ?>" data-group="<?php echo $index; ?>">
                                <?php foreach($group as $job): ?>
                                    <div class="job-card" onclick="window.location.href='<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $job['job_id']; ?>'">
                                        <div class="job-header">
                                            <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                            <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></div>
                                        </div>
                                        <div class="job-meta">
                                            <span class="job-type"><?php echo ucfirst($job['job_type']); ?></span>
                                            <span class="job-location"><?php echo htmlspecialchars($job['location']); ?></span>
                                        </div>
                                        <?php if(!empty($job['application_deadline'])): ?>
                                            <div class="deadline-tag">
                                                Deadline: <?php echo date('M d, Y', strtotime($job['application_deadline'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if($job['category_match'] > 0): ?>
                                            <div class="category-match">
                                                <span class="icon">✨</span> Category match
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="recent-applications">
                <div class="section-header">
                    <div class="section-title">Recent Applications</div>
                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php" class="btn btn-outline">View All Applications</a>
                </div>
                <div class="application-list">
                    <?php foreach($recent_applications as $application): ?>
                        <div class="application-item">
                            <div class="company-logo">
                                <?php if(!empty($application['company_logo'])): ?>
                                    <img src="<?php echo SITE_URL . '/' . $application['company_logo']; ?>" alt="Company Logo">
                                <?php else: ?>
                                    <span><?php echo strtoupper(substr($application['company_name'], 0, 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="job-title"><?php echo htmlspecialchars($application['job_title']); ?></div>
                                <div class="company-name"><?php echo htmlspecialchars($application['company_name']); ?></div>
                            </div>
                            <div class="application-status status-<?php echo $application['status']; ?>">
                                <?php echo ucfirst($application['status']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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

            // Recommended Jobs Rotation
            const jobGroups = document.querySelectorAll('.job-group');
            let currentGroupIndex = 0;
            const rotationInterval = 60000; // 1 minute in milliseconds

            function showGroup(index) {
                jobGroups.forEach((group, i) => {
                    group.classList.remove('active', 'prev', 'next');
                    if (i === index) {
                        group.classList.add('active');
                    } else if (i < index) {
                        group.classList.add('prev');
                    } else {
                        group.classList.add('next');
                    }
                });
            }

            function rotateGroups() {
                currentGroupIndex = (currentGroupIndex + 1) % jobGroups.length;
                showGroup(currentGroupIndex);
            }

            // Initialize rotation if there are groups
            if (jobGroups.length > 1) {
                let rotationTimer = setInterval(rotateGroups, rotationInterval);

                // Pause rotation on hover
                const container = document.getElementById('recommendedJobsContainer');
                container.addEventListener('mouseenter', () => clearInterval(rotationTimer));
                container.addEventListener('mouseleave', () => {
                    rotationTimer = setInterval(rotateGroups, rotationInterval);
                });
            }
        });
    </script>
    <script src="<?php echo SITE_URL; ?>/assets/js/chatbot.js"></script>
</body>
</html>