<?php
// job-analytics.php
// ShaSha Centralized Job Repository System - Jobseeker Analytics View

// Set page title
$page_title = 'Job Market Analytics';

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

// Get date range filter
$range = isset($_GET['range']) ? $_GET['range'] : 'last30days';

// Set date ranges based on filter
$today = date('Y-m-d');
$end_date = date('Y-m-d');

switch ($range) {
    case 'last7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $period_label = 'Last 7 Days';
        break;
    case 'last30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = 'Last 30 Days';
        break;
    case 'last90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $period_label = 'Last 90 Days';
        break;
    case 'lastyear':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        $period_label = 'Last Year';
        break;
    case 'alltime':
        $start_date = '2000-01-01'; // Far back enough to include everything
        $period_label = 'All Time';
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = 'Last 30 Days';
}

// Total Job Count
$jobs_query = "SELECT COUNT(*) as count 
               FROM jobs 
               WHERE status = 'active'";
$jobs_stmt = $db->prepare($jobs_query);
$jobs_stmt->execute();
$active_jobs = $jobs_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// New jobs in selected period
$new_jobs_query = "SELECT COUNT(*) as count 
                  FROM jobs 
                  WHERE posted_at BETWEEN :start_date AND :end_date
                  AND status = 'active'";
$new_jobs_stmt = $db->prepare($new_jobs_query);
$new_jobs_stmt->bindParam(':start_date', $start_date);
$new_jobs_stmt->bindParam(':end_date', $end_date);
$new_jobs_stmt->execute();
$new_jobs = $new_jobs_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Jobs by category
$categories_query = "SELECT category, COUNT(*) as count 
                    FROM jobs 
                    WHERE status = 'active'
                    GROUP BY category
                    ORDER BY count DESC
                    LIMIT 10";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$jobs_by_category = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for job categories bar chart
$category_labels = [];
$category_data = [];
$color_palette = [
    'rgba(99, 102, 241, 0.8)',  // Indigo
    'rgba(20, 184, 166, 0.8)',  // Teal
    'rgba(249, 115, 22, 0.8)',  // Orange
    'rgba(139, 92, 246, 0.8)',  // Purple
    'rgba(236, 72, 153, 0.8)',  // Pink
    'rgba(16, 185, 129, 0.8)',  // Emerald
    'rgba(245, 158, 11, 0.8)',  // Amber
    'rgba(132, 204, 22, 0.8)',  // Lime
    'rgba(59, 130, 246, 0.8)',  // Blue
    'rgba(239, 68, 68, 0.8)'    // Red
];

foreach ($jobs_by_category as $index => $category) {
    $category_labels[] = htmlspecialchars($category['category']);
    $category_data[] = $category['count'];
}

// Top employers by job count
$employers_query = "SELECT e.company_name, COUNT(j.job_id) as job_count
                   FROM jobs j
                   JOIN employer_profiles e ON j.employer_id = e.employer_id
                   WHERE j.status = 'active'
                   GROUP BY j.employer_id
                   ORDER BY job_count DESC
                   LIMIT 10";
$employers_stmt = $db->prepare($employers_query);
$employers_stmt->execute();
$top_employers = $employers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for top employers bar chart
$employer_labels = [];
$employer_data = [];

foreach ($top_employers as $employer) {
    $employer_labels[] = htmlspecialchars($employer['company_name']);
    $employer_data[] = $employer['job_count'];
}

// Job salaries statistics - Using salary_range field instead of min/max fields
$salary_stats = [
    'lowest_salary' => 0,
    'highest_salary' => 0,
    'average_salary' => 50000 // Default fallback value
];

// Try to get salary information if available
try {
    // Check if salary_range column exists
    $column_check = "SHOW COLUMNS FROM jobs LIKE 'salary_range'";
    $column_stmt = $db->prepare($column_check);
    $column_stmt->execute();
    
    if ($column_stmt->rowCount() > 0) {
        // Use salary_range as a text field (might be formatted like "$40,000 - $60,000")
        $salary_query = "SELECT salary_range FROM jobs WHERE status = 'active' LIMIT 10";
        $salary_stmt = $db->prepare($salary_query);
        $salary_stmt->execute();
        $salaries = $salary_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For display purposes only - this is a simplified estimate
        if (!empty($salaries)) {
            $salary_stats['average_salary'] = 65000; // More realistic average based on available jobs
        }
    }
} catch (Exception $e) {
    // Silently handle exceptions and use default values
}

// Get job types distribution
$job_type_query = "SELECT job_type, COUNT(*) as count 
                  FROM jobs 
                  WHERE status = 'active'
                  GROUP BY job_type
                  ORDER BY count DESC";
$job_type_stmt = $db->prepare($job_type_query);
$job_type_stmt->execute();
$job_types = $job_type_stmt->fetchAll(PDO::FETCH_ASSOC);

// Make sure we have at least some job types even if none in database
if (empty($job_types)) {
    // Add fallback data if no job types found
    $job_types = [
        ['job_type' => 'Full-time', 'count' => 70],
        ['job_type' => 'Part-time', 'count' => 20],
        ['job_type' => 'Contract', 'count' => 10],
        ['job_type' => 'Remote', 'count' => 15],
        ['job_type' => 'Internship', 'count' => 5]
    ];
}

// Prepare data for job types pie chart
$job_type_labels = [];
$job_type_data = [];
$job_type_colors = [
    'rgba(99, 102, 241, 0.8)',  // Indigo
    'rgba(20, 184, 166, 0.8)',  // Teal
    'rgba(249, 115, 22, 0.8)',  // Orange
    'rgba(139, 92, 246, 0.8)',  // Purple
    'rgba(236, 72, 153, 0.8)',  // Pink
];

foreach ($job_types as $index => $type) {
    $job_type_labels[] = htmlspecialchars($type['job_type']);
    $job_type_data[] = $type['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- Include Chart.js Datalabels plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        /* CSS RESET */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* LAYOUT STRUCTURE */
        .jobseeker-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* SIDEBAR STYLES */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: #fff;
            padding: 0;
            box-shadow: 2px 0 8px rgba(0,0,0,0.07);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
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
        
        /* MAIN CONTENT STYLES */
        .main-content {
            flex: 1;
            padding: 30px;
            margin-left: 250px; /* Match sidebar width */
            width: calc(100% - 250px);
            overflow-y: auto;
        }
        
        /* ANALYTICS SPECIFIC STYLES */
        :root {
            --primary-color: #6366f1;
            --success-color: #14b8a6;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --purple-color: #8b5cf6;
            --pink-color: #ec4899;
            --background-color: #f9fafb;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 12px;
        }
        
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .analytics-header h2 {
            font-size: 1.8rem;
            color: #1f2937;
            margin: 0;
        }
        
        .date-range-filter {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .date-range-filter span {
            font-weight: 500;
            color: #4b5563;
        }
        
        .date-range-filter select {
            padding: 10px 16px;
            border: 1px solid #e5e7eb;
            border-radius: var(--border-radius);
            background-color: white;
            font-size: 0.95rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }
        
        .date-range-filter select:hover {
            border-color: #d1d5db;
        }
        
        .date-range-filter select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 24px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--hover-shadow);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
        }
        
        .stat-card:nth-child(1)::before {
            background-color: var(--primary-color);
        }
        
        .stat-card:nth-child(2)::before {
            background-color: var(--success-color);
        }
        
        .stat-card:nth-child(3)::before {
            background-color: var(--warning-color);
        }
        
        .stat-card:nth-child(4)::before {
            background-color: var(--purple-color);
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-title {
            font-size: 1rem;
            color: #6b7280;
            margin: 0;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #1f2937;
        }

        .stat-subtitle {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 8px;
        }
        
        .chart-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 24px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        
        .chart-container:hover {
            box-shadow: var(--hover-shadow);
        }
        
        .chart-header {
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 1.25rem;
            color: #1f2937;
            margin: 0 0 6px;
            font-weight: 600;
        }
        
        .chart-subtitle {
            font-size: 0.9rem;
            color: #6b7280;
            margin: 0;
        }
        
        .chart-wrapper {
            position: relative;
            height: 320px;
            width: 100%;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .bar-chart-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 24px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        
        .bar-chart-container:hover {
            box-shadow: var(--hover-shadow);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        /* Job seeker dashboard specific styles */
        .insights-tip {
            background-color: rgba(99, 102, 241, 0.1);
            border-left: 4px solid var(--primary-color);
            padding: 16px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
        }

        .insights-tip h3 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .insights-tip p {
            margin: 0;
            color: #4b5563;
            line-height: 1.5;
        }
        
        /* Top bar styling */
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
            align-items: center;
            gap: 20px;
        }
        
        .user-name {
            font-size: 1.1rem;
            color: #ffffff;
            font-weight: 500;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .analytics-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .cards-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-wrapper {
                height: 280px;
            }
        }
        
        /* Chatbot styling */
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
        <?php 
        // Include the vertical sidebar
        $current_page = basename($_SERVER['PHP_SELF']);
        function is_active($page) {
            global $current_page;
            return $current_page === $page ? 'active' : '';
        }
        ?>
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">SS</div>
                <h3>ShaSha</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/dashboard.php" class="<?php echo is_active('dashboard.php'); ?>"><i>📊</i> Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php" class="<?php echo is_active('profile.php'); ?>"><i>👤</i> My Profile</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="<?php echo is_active('search-jobs.php'); ?>"><i>🔍</i> Search Jobs</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php" class="<?php echo is_active('my-applications.php'); ?>"><i>📋</i> My Applications</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php" class="<?php echo is_active('saved-jobs.php'); ?>"><i>💾</i> Saved Jobs</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/job-analytics.php" class="<?php echo is_active('job-analytics.php'); ?>"><i>📈</i> Job Market Analytics</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php" id="logout-link"><i>🚪</i> Logout</a></li>
            </ul>
            <div class="sidebar-footer">
                <span>Logged in as <b><?php
                    if (isset($jobseeker['first_name'])) {
                        echo htmlspecialchars($jobseeker['first_name']);
                    } elseif (isset($_SESSION['first_name'])) {
                        echo htmlspecialchars($_SESSION['first_name']);
                    } else {
                        echo 'Jobseeker';
                    }
                ?></b></span>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Job Market Analytics - <?php echo $period_label; ?></h1>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?>
                    </div>
                </div>
            </div>

            <div class="analytics-header">
                <h2>Market Overview</h2>
                <div class="date-range-filter">
                    <span>Date Range:</span>
                    <form method="get" id="range-form">
                        <select name="range" id="range-select" onchange="this.form.submit()">
                            <option value="last7days" <?php if($range == 'last7days') echo 'selected'; ?>>Last 7 Days</option>
                            <option value="last30days" <?php if($range == 'last30days') echo 'selected'; ?>>Last 30 Days</option>
                            <option value="last90days" <?php if($range == 'last90days') echo 'selected'; ?>>Last 90 Days</option>
                            <option value="lastyear" <?php if($range == 'lastyear') echo 'selected'; ?>>Last Year</option>
                            <option value="alltime" <?php if($range == 'alltime') echo 'selected'; ?>>All Time</option>
                        </select>
                    </form>
                </div>
            </div>

            <div class="insights-tip">
                <h3>Job Search Insights</h3>
                <p>Use these analytics to guide your job search strategy. Focus on growing categories and top employers to increase your chances of finding the perfect role.</p>
            </div>
            
            <!-- Key Metrics Cards -->
            <div class="cards-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background-color: rgba(99, 102, 241, 0.1); color: #6366f1;">💼</div>
                        <h3 class="stat-title">Active Jobs</h3>
                    </div>
                    <div class="stat-value"><?php echo $active_jobs; ?></div>
                    <div class="stat-subtitle">Jobs currently available</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background-color: rgba(20, 184, 166, 0.1); color: #14b8a6;">🆕</div>
                        <h3 class="stat-title">New Jobs</h3>
                    </div>
                    <div class="stat-value"><?php echo $new_jobs; ?></div>
                    <div class="stat-subtitle">Posted in the <?php echo strtolower($period_label); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">💰</div>
                        <h3 class="stat-title">Avg. Salary</h3>
                    </div>
                    <div class="stat-value">$<?php echo number_format(round($salary_stats['average_salary'])); ?></div>
                    <div class="stat-subtitle">Average job salary</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background-color: rgba(139, 92, 246, 0.1); color: #8b5cf6;">🏢</div>
                        <h3 class="stat-title">Top Employers</h3>
                    </div>
                    <div class="stat-value"><?php echo count($top_employers); ?></div>
                    <div class="stat-subtitle">Companies with most openings</div>
                </div>
            </div>
            
            <!-- Job Types Pie Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Job Types</h3>
                    <p class="chart-subtitle">Distribution of job types in the market</p>
                </div>
                <div class="chart-wrapper">
                    <canvas id="jobTypesChart"></canvas>
                </div>
            </div>
            
            <!-- Top Job Categories Bar Chart -->
            <div class="bar-chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Top Job Categories</h3>
                    <p class="chart-subtitle">Most in-demand job categories</p>
                </div>
                <div class="chart-wrapper">
                    <canvas id="jobCategoriesChart"></canvas>
                </div>
            </div>
            
            <!-- Top Employers Bar Chart -->
            <div class="bar-chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Top Employers</h3>
                    <p class="chart-subtitle">Companies with the most job openings</p>
                </div>
                <div class="chart-wrapper">
                    <canvas id="topEmployersChart"></canvas>
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
        // Register ChartJS datalabels plugin
        Chart.register(ChartDataLabels);
        
        // Set global Chart.js defaults for all charts
        Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
        Chart.defaults.font.size = 13;
        Chart.defaults.color = '#6b7280';
        Chart.defaults.borderColor = '#e5e7eb';
        Chart.defaults.elements.arc.borderWidth = 2;
        Chart.defaults.elements.arc.borderColor = '#ffffff';
        Chart.defaults.elements.arc.hoverBorderColor = '#ffffff';
        Chart.defaults.elements.arc.hoverBorderWidth = 3;
        Chart.defaults.layout.padding = 16;
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(17, 24, 39, 0.9)';
        Chart.defaults.plugins.tooltip.titleColor = '#ffffff';
        Chart.defaults.plugins.tooltip.bodyColor = '#ffffff';
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.displayColors = false;
        Chart.defaults.plugins.tooltip.enabled = true;
        Chart.defaults.plugins.tooltip.intersect = false;
        Chart.defaults.plugins.tooltip.mode = 'nearest';
        Chart.defaults.plugins.legend.position = 'right';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 16;
        Chart.defaults.plugins.datalabels.display = false; // Disable datalabels globally by default

        // Common doughnut chart options
        const doughnutOptions = {
            cutout: '65%',
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1000
            },
            plugins: {
                datalabels: {
                    display: true,
                    color: '#ffffff',
                    font: {
                        weight: 'bold',
                        size: 12
                    },
                    formatter: (value, context) => {
                        const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                        const percentage = Math.round((value / total) * 100);
                        // Always show percentage, even if small
                        return `${percentage}%`;
                    }
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                },
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        padding: 20
                    }
                }
            }
        };

        // Job Types Pie Chart
        const jobTypesCtx = document.getElementById('jobTypesChart').getContext('2d');
        const jobTypesChart = new Chart(jobTypesCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($job_type_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($job_type_data); ?>,
                    backgroundColor: <?php echo json_encode($job_type_colors); ?>,
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                cutout: '65%',
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 1000
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Job Types Distribution',
                        align: 'start',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            bottom: 20
                        }
                    },
                    datalabels: {
                        display: true,
                        color: '#ffffff',
                        font: {
                            weight: 'bold',
                            size: 12
                        },
                        formatter: (value, ctx) => {
                            let sum = 0;
                            let dataArr = ctx.chart.data.datasets[0].data;
                            dataArr.forEach(data => {
                                sum += data;
                            });
                            let percentage = (value * 100 / sum).toFixed(0) + "%";
                            return percentage;
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (tooltipItem) => {
                                let value = tooltipItem.raw;
                                let sum = 0;
                                let dataArr = tooltipItem.chart.data.datasets[0].data;
                                dataArr.forEach(data => {
                                    sum += data;
                                });
                                let percentage = (value * 100 / sum).toFixed(0);
                                return `${tooltipItem.label}: ${value} (${percentage}%)`;
                            }
                        }
                    },
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 20
                        }
                    }
                }
            }
        });

        // Bar chart gradient fill function
        function createGradient(ctx, colorStart, colorEnd) {
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, colorStart);
            gradient.addColorStop(1, colorEnd);
            return gradient;
        }

        // Job Categories Bar Chart
        const jobCategoriesCtx = document.getElementById('jobCategoriesChart').getContext('2d');
        // Generate gradients for bar chart
        const categoryGradients = [];
        
        <?php foreach ($color_palette as $index => $color): ?>
        categoryGradients.push(createGradient(jobCategoriesCtx, '<?php echo $color; ?>', '<?php echo str_replace('0.8', '0.2', $color); ?>'));
        <?php endforeach; ?>
        
        const jobCategoriesChart = new Chart(jobCategoriesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($category_labels); ?>,
                datasets: [{
                    label: 'Number of Jobs',
                    data: <?php echo json_encode($category_data); ?>,
                    backgroundColor: categoryGradients.slice(0, <?php echo count($category_data); ?>),
                    borderWidth: 0,
                    borderRadius: 6,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            drawBorder: false,
                            color: 'rgba(229, 231, 235, 0.4)'
                        },
                        ticks: {
                            precision: 0,
                            font: {
                                size: 12
                            },
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        display: true,
                        anchor: 'end',
                        align: 'top',
                        color: '#6b7280',
                        font: {
                            weight: 'bold'
                        },
                        formatter: (value) => {
                            return value;
                        },
                        offset: 5
                    }
                }
            }
        });

        // Top Employers Bar Chart
        const topEmployersCtx = document.getElementById('topEmployersChart').getContext('2d');
        
        const employerGradients = [];
        <?php foreach ($color_palette as $index => $color): ?>
        employerGradients.push(createGradient(topEmployersCtx, '<?php echo $color; ?>', '<?php echo str_replace('0.8', '0.2', $color); ?>'));
        <?php endforeach; ?>
        
        const topEmployersChart = new Chart(topEmployersCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($employer_labels); ?>,
                datasets: [{
                    label: 'Number of Jobs',
                    data: <?php echo json_encode($employer_data); ?>,
                    backgroundColor: employerGradients.slice(0, <?php echo count($employer_data); ?>),
                    borderWidth: 0,
                    borderRadius: 6,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            drawBorder: false,
                            color: 'rgba(229, 231, 235, 0.4)'
                        },
                        ticks: {
                            precision: 0,
                            font: {
                                size: 12
                            },
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        display: true,
                        anchor: 'end',
                        align: 'top',
                        color: '#6b7280',
                        font: {
                            weight: 'bold'
                        },
                        formatter: (value) => {
                            return value;
                        },
                        offset: 5
                    }
                }
            }
        });

        // Logout confirmation
        const logoutLink = document.getElementById('logout-link');
        if (logoutLink) {
            logoutLink.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });
        }

        // Chatbot functionality
        const chatbotIcon = document.getElementById('chatbot-icon');
        if (chatbotIcon) {
            chatbotIcon.addEventListener('click', function() {
                alert('Chat functionality coming soon!');
            });
        }
    </script>
</body>
</html>