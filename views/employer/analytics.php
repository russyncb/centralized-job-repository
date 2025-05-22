<?php
// employer-analytics.php
// ShaSha Centralized Job Repository System - Employer Analytics Dashboard

// Set page title
$page_title = 'Recruitment Analytics';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has employer role
if(!has_role('employer')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get employer ID
$query = "SELECT e.employer_id, e.verified, e.company_name, u.first_name, u.last_name 
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
$is_verified = $employer['verified'] == 1;

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
        $start_date = '2000-01-01';
        $period_label = 'All Time';
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = 'Last 30 Days';
}

// 1. Job Performance Metrics
$jobs_query = "SELECT 
    COUNT(*) as total_jobs,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_jobs,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_jobs,
    SUM(CASE WHEN posted_at BETWEEN :start_date AND :end_date THEN 1 ELSE 0 END) as new_jobs_period
    FROM jobs 
    WHERE employer_id = :employer_id";
$jobs_stmt = $db->prepare($jobs_query);
$jobs_stmt->bindParam(':employer_id', $employer_id);
$jobs_stmt->bindParam(':start_date', $start_date);
$jobs_stmt->bindParam(':end_date', $end_date);
$jobs_stmt->execute();
$job_stats = $jobs_stmt->fetch(PDO::FETCH_ASSOC);

// 2. Application Analytics
$apps_query = "SELECT 
    COUNT(*) as total_applications,
    SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
    SUM(CASE WHEN a.status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_applications,
    SUM(CASE WHEN a.status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted_applications,
    SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hired_applications,
    SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
    SUM(CASE WHEN a.applied_at BETWEEN :start_date AND :end_date THEN 1 ELSE 0 END) as new_applications_period
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    WHERE j.employer_id = :employer_id";
$apps_stmt = $db->prepare($apps_query);
$apps_stmt->bindParam(':employer_id', $employer_id);
$apps_stmt->bindParam(':start_date', $start_date);
$apps_stmt->bindParam(':end_date', $end_date);
$apps_stmt->execute();
$app_stats = $apps_stmt->fetch(PDO::FETCH_ASSOC);

// 3. Calculate key metrics
$avg_apps_per_job = $job_stats['total_jobs'] > 0 ? round($app_stats['total_applications'] / $job_stats['total_jobs'], 1) : 0;
$hire_rate = $app_stats['total_applications'] > 0 ? round(($app_stats['hired_applications'] / $app_stats['total_applications']) * 100, 1) : 0;
$response_rate = ($app_stats['pending_applications'] + $app_stats['total_applications']) > 0 ? 
    round((($app_stats['reviewed_applications'] + $app_stats['shortlisted_applications'] + $app_stats['hired_applications'] + $app_stats['rejected_applications']) / $app_stats['total_applications']) * 100, 1) : 0;

// 4. Top Performing Jobs
$top_jobs_query = "SELECT 
    j.title,
    j.posted_at,
    j.status,
    COUNT(a.application_id) as application_count,
    SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hires
    FROM jobs j
    LEFT JOIN applications a ON j.job_id = a.job_id
    WHERE j.employer_id = :employer_id
    GROUP BY j.job_id
    ORDER BY application_count DESC
    LIMIT 10";
$top_jobs_stmt = $db->prepare($top_jobs_query);
$top_jobs_stmt->bindParam(':employer_id', $employer_id);
$top_jobs_stmt->execute();
$top_jobs = $top_jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Application Status Distribution
$status_labels = ['Pending', 'Reviewed', 'Shortlisted', 'Hired', 'Rejected'];
$status_data = [
    $app_stats['pending_applications'],
    $app_stats['reviewed_applications'],
    $app_stats['shortlisted_applications'],
    $app_stats['hired_applications'],
    $app_stats['rejected_applications']
];
$status_colors = [
    'rgba(245, 158, 11, 0.8)',  // Amber for Pending
    'rgba(59, 130, 246, 0.8)',  // Blue for Reviewed
    'rgba(139, 92, 246, 0.8)',  // Purple for Shortlisted
    'rgba(16, 185, 129, 0.8)',  // Green for Hired
    'rgba(239, 68, 68, 0.8)'    // Red for Rejected
];

// 6. Applications Trend (last 30 days)
$trend_query = "SELECT 
    DATE(a.applied_at) as date,
    COUNT(*) as applications
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    WHERE j.employer_id = :employer_id
    AND a.applied_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(a.applied_at)
    ORDER BY date";
$trend_stmt = $db->prepare($trend_query);
$trend_stmt->bindParam(':employer_id', $employer_id);
$trend_stmt->execute();
$trend_data = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fill in missing dates with 0 applications
$trend_labels = [];
$trend_values = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $trend_labels[] = date('M j', strtotime($date));
    
    $found = false;
    foreach ($trend_data as $data) {
        if ($data['date'] === $date) {
            $trend_values[] = (int)$data['applications'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $trend_values[] = 0;
    }
}

// 7. Job Categories Performance
$categories_query = "SELECT 
    j.category,
    COUNT(DISTINCT j.job_id) as job_count,
    COUNT(a.application_id) as total_applications,
    AVG(CASE WHEN j.status = 'closed' AND j.posted_at IS NOT NULL THEN 
        DATEDIFF(COALESCE(j.updated_at, CURDATE()), j.posted_at) ELSE NULL END) as avg_time_to_fill
    FROM jobs j
    LEFT JOIN applications a ON j.job_id = a.job_id
    WHERE j.employer_id = :employer_id
    GROUP BY j.category
    ORDER BY total_applications DESC";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->bindParam(':employer_id', $employer_id);
$categories_stmt->execute();
$categories_performance = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Recent Hiring Activity
$recent_hires_query = "SELECT 
    u.first_name,
    u.last_name,
    j.title as job_title,
    a.applied_at,
    a.updated_at as hired_at
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    JOIN jobseeker_profiles jp ON a.jobseeker_id = jp.jobseeker_id
    JOIN users u ON jp.user_id = u.user_id
    WHERE j.employer_id = :employer_id
    AND a.status = 'hired'
    ORDER BY a.updated_at DESC
    LIMIT 10";
    
$recent_hires_stmt = $db->prepare($recent_hires_query);
$recent_hires_stmt->bindParam(':employer_id', $employer_id);
$recent_hires_stmt->execute();
$recent_hires = $recent_hires_stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Time to Hire Analytics
$time_to_hire_query = "SELECT 
    AVG(DATEDIFF(a.updated_at, a.applied_at)) as avg_time_to_hire,
    MIN(DATEDIFF(a.updated_at, a.applied_at)) as fastest_hire,
    MAX(DATEDIFF(a.updated_at, a.applied_at)) as slowest_hire
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    WHERE j.employer_id = :employer_id
    AND a.status = 'hired'
    AND a.updated_at IS NOT NULL";
$time_to_hire_stmt = $db->prepare($time_to_hire_query);
$time_to_hire_stmt->bindParam(':employer_id', $employer_id);
$time_to_hire_stmt->execute();
$time_to_hire = $time_to_hire_stmt->fetch(PDO::FETCH_ASSOC);

$avg_time_to_hire = $time_to_hire['avg_time_to_hire'] ? round($time_to_hire['avg_time_to_hire']) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/chatbot.css">
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- Include Chart.js Datalabels plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        /* Advanced Employer Analytics Styles */
        body {
            background: linear-gradient(135deg, #f6f8fc 0%, #f1f4f9 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .employer-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            width: calc(100% - 250px);
            transition: all 0.3s ease;
        }

        :root {
            --primary-color: #1a3b5d;
            --secondary-color: #1557b0;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --purple-color: #8b5cf6;
            --pink-color: #ec4899;
            --background-color: #f9fafb;
            --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 20px 35px rgba(0, 0, 0, 0.12);
            --border-radius: 16px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 25px 30px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            border-radius: var(--border-radius);
            box-shadow: 0 8px 25px rgba(26, 59, 93, 0.15);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .top-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .top-bar h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #ffffff;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .analytics-badge {
            background: linear-gradient(135deg, #ff6b6b, #feca57, #48dbfb, #ff9ff3);
            background-size: 300% 300%;
            animation: gradientShift 3s ease infinite;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .company-name {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            color: #fff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .verification-badge .icon {
            margin-right: 5px;
        }

        .pending-verification {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border-color: rgba(255, 193, 7, 0.3);
        }

        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 25px 30px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .analytics-header h2 {
            font-size: 1.5rem;
            color: #1f2937;
            margin: 0;
            font-weight: 600;
        }

        .date-range-filter {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .date-range-filter span {
            font-weight: 500;
            color: #4b5563;
        }

        .date-range-filter select {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            background-color: white;
            font-size: 0.95rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .date-range-filter select:hover {
            border-color: var(--secondary-color);
        }

        .date-range-filter select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(21, 87, 176, 0.1);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
        }

        .metric-card:nth-child(1)::before { background: var(--success-color); }
        .metric-card:nth-child(2)::before { background: var(--secondary-color); }
        .metric-card:nth-child(3)::before { background: var(--warning-color); }
        .metric-card:nth-child(4)::before { background: var(--purple-color); }
        .metric-card:nth-child(5)::before { background: var(--pink-color); }

        .metric-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .metric-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .metric-title {
            font-size: 1rem;
            color: #6b7280;
            margin: 0;
            font-weight: 500;
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .metric-subtitle {
            font-size: 0.85rem;
            color: #6b7280;
        }

        .chart-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 30px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }

        .chart-container:hover {
            box-shadow: var(--hover-shadow);
        }

        .chart-header {
            margin-bottom: 25px;
        }

        .chart-title {
            font-size: 1.4rem;
            color: #1f2937;
            margin: 0 0 8px;
            font-weight: 600;
        }

        .chart-subtitle {
            font-size: 0.95rem;
            color: #6b7280;
            margin: 0;
        }

        .chart-wrapper {
            position: relative;
            height: 350px;
            width: 100%;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .performance-table {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .table-header {
            padding: 25px 30px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid #e5e7eb;
        }

        .table-header h3 {
            margin: 0;
            font-size: 1.3rem;
            color: #1f2937;
            font-weight: 600;
        }

        .table-content {
            overflow-x: auto;
        }

        .performance-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .performance-table th,
        .performance-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }

        .performance-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }

        .performance-table td {
            color: #6b7280;
        }

        .performance-table tr:hover {
            background: #f9fafb;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-closed {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .insights-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .insight-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 25px;
            transition: all 0.3s ease;
        }

        .insight-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
        }

        .insight-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .insight-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .insight-title {
            font-size: 1.1rem;
            color: #1f2937;
            margin: 0;
            font-weight: 600;
        }

        .insight-content {
            color: #6b7280;
            line-height: 1.5;
        }

        .insight-metric {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1f2937;
            margin: 10px 0;
        }

        /* Responsive design */
        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .top-bar {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .analytics-header {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .metrics-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }

            .chart-container {
                padding: 20px;
            }

            .chart-wrapper {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="employer-container">
        <?php include 'employer-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div>
                    <h1>Recruitment Analytics <span class="analytics-badge">📊 INSIGHTS</span></h1>
                </div>
                <div class="user-info">
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($employer['company_name']); ?></span>
                        <?php if($is_verified): ?>
                            <span class="verification-badge">
                                <span class="icon">✓</span> Verified
                            </span>
                        <?php else: ?>
                            <span class="verification-badge pending-verification">
                                <span class="icon">⏱</span> Pending
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="analytics-header">
                <h2>Recruitment Performance - <?php echo $period_label; ?></h2>
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

            <!-- Key Performance Metrics -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon" style="background: var(--success-color);">📊</div>
                        <h3 class="metric-title">Total Applications</h3>
                    </div>
                    <div class="metric-value"><?php echo number_format($app_stats['total_applications']); ?></div>
                    <div class="metric-subtitle"><?php echo number_format($app_stats['new_applications_period']); ?> new in <?php echo strtolower($period_label); ?></div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon" style="background: var(--secondary-color);">💼</div>
                        <h3 class="metric-title">Active Jobs</h3>
                    </div>
                    <div class="metric-value"><?php echo number_format($job_stats['active_jobs']); ?></div>
                    <div class="metric-subtitle"><?php echo number_format($job_stats['new_jobs_period']); ?> posted in <?php echo strtolower($period_label); ?></div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon" style="background: var(--warning-color);">🎯</div>
                        <h3 class="metric-title">Hire Rate</h3>
                    </div>
                    <div class="metric-value"><?php echo $hire_rate; ?>%</div>
                    <div class="metric-subtitle"><?php echo number_format($app_stats['hired_applications']); ?> successful hires</div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon" style="background: var(--purple-color);">⚡</div>
                        <h3 class="metric-title">Avg. Applications per Job</h3>
                    </div>
                    <div class="metric-value"><?php echo $avg_apps_per_job; ?></div>
                    <div class="metric-subtitle">Job attractiveness metric</div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon" style="background: var(--pink-color);">⏱️</div>
                        <h3 class="metric-title">Avg. Time to Hire</h3>
                    </div>
                    <div class="metric-value"><?php echo $avg_time_to_hire; ?></div>
                    <div class="metric-subtitle">days from application to hire</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Application Status Distribution -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Application Status Distribution</h3>
                        <p class="chart-subtitle">Current status of all applications</p>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="applicationStatusChart"></canvas>
                    </div>
                </div>

                <!-- Applications Trend -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Applications Trend</h3>
                        <p class="chart-subtitle">Daily applications over last 30 days</p>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="applicationsTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Performing Jobs Table -->
            <div class="performance-table">
                <div class="table-header">
                    <h3>Top Performing Jobs</h3>
                </div>
                <div class="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Job Title</th>
                                <th>Posted Date</th>
                                <th>Status</th>
                                <th>Applications</th>
                                <th>Hires</th>
                                <th>Success Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($top_jobs) > 0): ?>
                                <?php foreach($top_jobs as $job): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
                                        <td><?php echo date('M j, Y', strtotime($job['posted_at'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $job['status']; ?>">
                                                <?php echo ucfirst($job['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($job['application_count']); ?></td>
                                        <td><?php echo number_format($job['hires']); ?></td>
                                        <td><?php echo $job['application_count'] > 0 ? round(($job['hires'] / $job['application_count']) * 100, 1) : 0; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: #9ca3af;">
                                        No jobs found. Start posting jobs to see analytics.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Insights Panel -->
            <div class="insights-panel">
                <div class="insight-card">
                    <div class="insight-header">
                        <div class="insight-icon" style="background: var(--success-color);">💡</div>
                        <h3 class="insight-title">Recruitment Insights</h3>
                    </div>
                    <div class="insight-content">
                        <?php if ($avg_apps_per_job > 20): ?>
                            Your jobs are highly attractive! You're getting <?php echo $avg_apps_per_job; ?> applications per job on average.
                        <?php elseif ($avg_apps_per_job > 10): ?>
                            Good job attractiveness. Consider optimizing job descriptions to increase applications.
                        <?php else: ?>
                            Consider improving job titles, descriptions, and benefits to attract more candidates.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="insight-card">
                    <div class="insight-header">
                        <div class="insight-icon" style="background: var(--warning-color);">🚀</div>
                        <h3 class="insight-title">Performance Tip</h3>
                    </div>
                    <div class="insight-content">
                        <?php if ($response_rate < 50): ?>
                            Response Rate: <span class="insight-metric"><?php echo $response_rate; ?>%</span>
                            Consider responding to applications faster to improve candidate experience.
                        <?php else: ?>
                            Great response rate of <?php echo $response_rate; ?>%! Keep maintaining quick responses to applications.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="insight-card">
                    <div class="insight-header">
                        <div class="insight-icon" style="background: var(--purple-color);">📈</div>
                        <h3 class="insight-title">Hiring Efficiency</h3>
                    </div>
                    <div class="insight-content">
                        <?php if ($avg_time_to_hire > 30): ?>
                            Time to Hire: <span class="insight-metric"><?php echo $avg_time_to_hire; ?> days</span>
                            Consider streamlining your hiring process to reduce time to hire.
                        <?php elseif ($avg_time_to_hire > 0): ?>
                            Excellent hiring speed! Average of <?php echo $avg_time_to_hire; ?> days from application to hire.
                        <?php else: ?>
                            Start hiring to see time-to-hire analytics.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Advanced Chatbot -->
    <script src="<?php echo SITE_URL; ?>/assets/js/chatbot.js"></script>

    <script>
        // Register ChartJS datalabels plugin
        Chart.register(ChartDataLabels);
        
        // Set global Chart.js defaults
        Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.font.size = 13;
        Chart.defaults.color = '#6b7280';
        Chart.defaults.plugins.datalabels.display = false;

        // Application Status Pie Chart
        const statusCtx = document.getElementById('applicationStatusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_data); ?>,
                    backgroundColor: <?php echo json_encode($status_colors); ?>,
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 10
                }]
            },
            options: {
                cutout: '60%',
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 1000
                },
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = total > 0 ? Math.round((context.raw / total) * 100) : 0;
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Applications Trend Line Chart
        const trendCtx = document.getElementById('applicationsTrendChart').getContext('2d');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_labels); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode($trend_values); ?>,
                    borderColor: '#1557b0',
                    backgroundColor: 'rgba(21, 87, 176, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#1557b0',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5
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
                            color: 'rgba(229, 231, 235, 0.4)'
                        },
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    </script>
</body>
</html>