<?php
// analytics.php
// ShaSha Centralized Job Repository System

// Set page title
$page_title = 'Analytics';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

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

// Total counts for the period
// 1. New users by role
$users_query = "SELECT role, COUNT(*) as count 
               FROM users 
               WHERE created_at BETWEEN :start_date AND :end_date
               GROUP BY role";
$users_stmt = $db->prepare($users_query);
$users_stmt->bindParam(':start_date', $start_date);
$users_stmt->bindParam(':end_date', $end_date);
$users_stmt->execute();
$new_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$users_by_role = [
    'jobseeker' => 0,
    'employer' => 0,
    'admin' => 0
];

foreach ($new_users as $user) {
    $users_by_role[$user['role']] = $user['count'];
}

$total_new_users = array_sum($users_by_role);

// 2. New jobs
$jobs_query = "SELECT COUNT(*) as count 
              FROM jobs 
              WHERE posted_at BETWEEN :start_date AND :end_date";
$jobs_stmt = $db->prepare($jobs_query);
$jobs_stmt->bindParam(':start_date', $start_date);
$jobs_stmt->bindParam(':end_date', $end_date);
$jobs_stmt->execute();
$new_jobs = $jobs_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// 3. New applications
$applications_query = "SELECT COUNT(*) as count 
                      FROM applications 
                      WHERE applied_at BETWEEN :start_date AND :end_date";
$applications_stmt = $db->prepare($applications_query);
$applications_stmt->bindParam(':start_date', $start_date);
$applications_stmt->bindParam(':end_date', $end_date);
$applications_stmt->execute();
$new_applications = $applications_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// 4. Jobs by category
$categories_query = "SELECT category, COUNT(*) as count 
                    FROM jobs 
                    WHERE posted_at BETWEEN :start_date AND :end_date
                    GROUP BY category
                    ORDER BY count DESC
                    LIMIT 8";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->bindParam(':start_date', $start_date);
$categories_stmt->bindParam(':end_date', $end_date);
$categories_stmt->execute();
$jobs_by_category = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Jobs by status
$status_query = "SELECT status, COUNT(*) as count 
                FROM jobs 
                WHERE posted_at BETWEEN :start_date AND :end_date
                GROUP BY status";
$status_stmt = $db->prepare($status_query);
$status_stmt->bindParam(':start_date', $start_date);
$status_stmt->bindParam(':end_date', $end_date);
$status_stmt->execute();
$jobs_by_status = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

$status_counts = [
    'active' => 0,
    'closed' => 0,
    'draft' => 0,
    'archived' => 0
];

foreach ($jobs_by_status as $status) {
    $status_counts[$status['status']] = $status['count'];
}

// 6. Top employers by job count
$employers_query = "SELECT e.company_name, COUNT(j.job_id) as job_count
                   FROM jobs j
                   JOIN employer_profiles e ON j.employer_id = e.employer_id
                   WHERE j.posted_at BETWEEN :start_date AND :end_date
                   GROUP BY j.employer_id
                   ORDER BY job_count DESC
                   LIMIT 5";
$employers_stmt = $db->prepare($employers_query);
$employers_stmt->bindParam(':start_date', $start_date);
$employers_stmt->bindParam(':end_date', $end_date);
$employers_stmt->execute();
$top_employers = $employers_stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Application success rate
$hire_query = "SELECT 
                (SELECT COUNT(*) FROM applications 
                 WHERE status = 'hired' AND applied_at BETWEEN :start_date AND :end_date) as hired,
                (SELECT COUNT(*) FROM applications 
                 WHERE applied_at BETWEEN :start_date AND :end_date) as total";
$hire_stmt = $db->prepare($hire_query);
$hire_stmt->bindParam(':start_date', $start_date);
$hire_stmt->bindParam(':end_date', $end_date);
$hire_stmt->execute();
$hire_stats = $hire_stmt->fetch(PDO::FETCH_ASSOC);

$hire_rate = $hire_stats['total'] > 0 ? round(($hire_stats['hired'] / $hire_stats['total']) * 100, 1) : 0;

// 8. Registration trends (last 12 days)
$trend_days = 12;
$registration_trend = [];

// Initialize the array with zeros for the past X days
for ($i = $trend_days - 1; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $registration_trend[$day] = 0;
}

// Get actual counts
$trend_query = "SELECT DATE(created_at) as day, COUNT(*) as count
               FROM users
               WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL $trend_days DAY)
               GROUP BY DATE(created_at)";
$trend_stmt = $db->prepare($trend_query);
$trend_stmt->execute();
$trend_results = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fill in actual values
foreach ($trend_results as $result) {
    $registration_trend[$result['day']] = $result['count'];
}

// Format for display
$trend_labels = array_map(function($day) {
    return date('M d', strtotime($day));
}, array_keys($registration_trend));

$trend_values = array_values($registration_trend);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .date-range-filter {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .date-range-filter select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background-color: white;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(0, 123, 255, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-title {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 5px;
        }
        
        .stat-change {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .stat-change.positive {
            color: #10b981;
        }
        
        .stat-change.negative {
            color: #ef4444;
        }
        
        .stats-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .stats-header {
            margin-bottom: 20px;
        }
        
        .stats-title {
            font-size: 1.2rem;
            color: #334155;
            margin: 0 0 5px;
        }
        
        .stats-subtitle {
            font-size: 0.85rem;
            color: #64748b;
            margin: 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .stats-item {
            padding: 15px;
            border-radius: 8px;
            background-color: #f8fafc;
        }
        
        .stats-item-header {
            font-weight: 600;
            margin-bottom: 10px;
            color: #334155;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .stats-item-content {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-label {
            color: #64748b;
        }
        
        .stat-number {
            font-weight: 600;
            color: #334155;
            padding: 2px 8px;
            background-color: #e2e8f0;
            border-radius: 4px;
        }
        
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .top-list {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .top-list-title {
            font-size: 1.2rem;
            color: #334155;
            margin: 0 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .top-list-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .top-list-item:last-child {
            border-bottom: none;
        }
        
        .top-list-name {
            font-weight: 500;
        }
        
        .top-list-count {
            background-color: #f1f5f9;
            color: #334155;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .progress-bar {
            height: 8px;
            background-color: #e5e7eb;
            border-radius: 4px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background-color: #3b82f6;
            border-radius: 4px;
        }
        
        @media (max-width: 992px) {
            .charts-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include '../admin/admin-sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="analytics-header">
                <h2>System Analytics - <?php echo $period_label; ?></h2>
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
            
            <!-- Key Metrics Cards -->
            <div class="cards-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background-color: rgba(59, 130, 246, 0.1); color: #3b82f6;">👥</div>
                        <h3 class="stat-title">New Users</h3>
                    </div>
                    <div class="stat-value"><?php echo $total_new_users; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">💼</div>
                        <h3 class="stat-title">New Jobs</h3>
                    </div>
                    <div class="stat-value"><?php echo $new_jobs; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background-color: rgba(249, 115, 22, 0.1); color: #f97316;">📝</div>
                        <h3 class="stat-title">New Applications</h3>
                    </div>
                    <div class="stat-value"><?php echo $new_applications; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background-color: rgba(139, 92, 246, 0.1); color: #8b5cf6;">👔</div>
                        <h3 class="stat-title">Hire Rate</h3>
                    </div>
                    <div class="stat-value"><?php echo $hire_rate; ?>%</div>
                </div>
            </div>
            
            <!-- User Stats (replaced chart) -->
            <div class="stats-container">
                <div class="stats-header">
                    <h3 class="stats-title">User Statistics</h3>
                    <p class="stats-subtitle">Overview of user distribution and activity</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-item">
                        <div class="stats-item-header">Users by Role</div>
                        <div class="stats-item-content">
                            <div class="stat-row">
                                <div class="stat-label">Job Seekers</div>
                                <div class="stat-number"><?php echo $users_by_role['jobseeker']; ?></div>
                            </div>
                            <div class="stat-row">
                                <div class="stat-label">Employers</div>
                                <div class="stat-number"><?php echo $users_by_role['employer']; ?></div>
                            </div>
                            <div class="stat-row">
                                <div class="stat-label">Admins</div>
                                <div class="stat-number"><?php echo $users_by_role['admin']; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-item">
                        <div class="stats-item-header">Jobs by Status</div>
                        <div class="stats-item-content">
                            <div class="stat-row">
                                <div class="stat-label">Active</div>
                                <div class="stat-number"><?php echo $status_counts['active']; ?></div>
                            </div>
                            <div class="stat-row">
                                <div class="stat-label">Closed</div>
                                <div class="stat-number"><?php echo $status_counts['closed']; ?></div>
                            </div>
                            <div class="stat-row">
                                <div class="stat-label">Draft</div>
                                <div class="stat-number"><?php echo $status_counts['draft']; ?></div>
                            </div>
                            <div class="stat-row">
                                <div class="stat-label">Archived</div>
                                <div class="stat-number"><?php echo $status_counts['archived']; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-item">
                        <div class="stats-item-header">Recent Activity</div>
                        <div class="stats-item-content">
                            <div class="stat-row">
                                <div class="stat-label">New Users Today</div>
                                <div class="stat-number"><?php echo isset($registration_trend[date('Y-m-d')]) ? $registration_trend[date('Y-m-d')] : 0; ?></div>
                            </div>
                            <div class="stat-row">
                                <div class="stat-label">Applications This Week</div>
                                <div class="stat-number"><?php echo $new_applications; ?></div>
                            </div>
                            <div class="stat-row">
                                <div class="stat-label">Success Rate</div>
                                <div class="stat-number"><?php echo $hire_rate; ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Lists -->
            <div class="charts-row">
                <!-- Top Job Categories -->
                <div class="top-list">
                    <h3 class="top-list-title">Top Job Categories</h3>
                    <?php if(count($jobs_by_category) > 0): ?>
                        <?php 
                        // Calculate the maximum for percentage
                        $max_category_count = $jobs_by_category[0]['count'];
                        foreach($jobs_by_category as $category): 
                            $percentage = ($category['count'] / $max_category_count) * 100;
                        ?>
                            <div class="top-list-item">
                                <div style="flex: 1;">
                                    <div class="top-list-name"><?php echo htmlspecialchars($category['category']); ?></div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                                <div class="top-list-count"><?php echo $category['count']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No job categories data available for this period.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Top Employers -->
                <div class="top-list">
                    <h3 class="top-list-title">Top Employers</h3>
                    <?php if(count($top_employers) > 0): ?>
                        <?php 
                        // Calculate the maximum for percentage
                        $max_employer_count = $top_employers[0]['job_count'];
                        foreach($top_employers as $employer): 
                            $percentage = ($employer['job_count'] / $max_employer_count) * 100;
                        ?>
                            <div class="top-list-item">
                                <div style="flex: 1;">
                                    <div class="top-list-name"><?php echo htmlspecialchars($employer['company_name']); ?></div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                                <div class="top-list-count"><?php echo $employer['job_count']; ?> jobs</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No employer data available for this period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>