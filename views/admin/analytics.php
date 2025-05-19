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

// Prepare data for user roles pie chart
$user_role_labels = ['Job Seekers', 'Employers', 'Admins'];
$user_role_data = [
    $users_by_role['jobseeker'],
    $users_by_role['employer'],
    $users_by_role['admin']
];
$user_role_colors = ['#6366f1', '#14b8a6', '#8b5cf6'];

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
    'rgba(132, 204, 22, 0.8)'   // Lime
];

foreach ($jobs_by_category as $index => $category) {
    $category_labels[] = htmlspecialchars($category['category']);
    $category_data[] = $category['count'];
}

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

// Prepare data for jobs by status pie chart
$status_labels = ['Active', 'Closed', 'Draft', 'Archived'];
$status_data = [
    $status_counts['active'],
    $status_counts['closed'],
    $status_counts['draft'],
    $status_counts['archived']
];
$status_colors = [
    'rgba(16, 185, 129, 0.8)',  // Green for Active
    'rgba(99, 102, 241, 0.8)',  // Indigo for Closed
    'rgba(245, 158, 11, 0.8)',  // Amber for Draft
    'rgba(100, 116, 139, 0.8)'  // Slate for Archived
];

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

// Prepare data for top employers bar chart
$employer_labels = [];
$employer_data = [];

foreach ($top_employers as $employer) {
    $employer_labels[] = htmlspecialchars($employer['company_name']);
    $employer_data[] = $employer['job_count'];
}

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

// 8. Recent activity data
$today_users_query = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()";
$today_users_stmt = $db->prepare($today_users_query);
$today_users_stmt->execute();
$new_users_today = $today_users_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$week_applications_query = "SELECT COUNT(*) as count FROM applications 
                          WHERE applied_at BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()";
$week_applications_stmt = $db->prepare($week_applications_query);
$week_applications_stmt->execute();
$applications_this_week = $week_applications_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Prepare data for recent activity pie chart
$activity_labels = ['New Users Today', 'Applications This Week', 'Success Rate'];
$activity_data = [
    $new_users_today,
    $applications_this_week,
    $hire_stats['hired']
];
$activity_colors = [
    'rgba(99, 102, 241, 0.8)',  // Indigo
    'rgba(236, 72, 153, 0.8)',  // Pink
    'rgba(16, 185, 129, 0.8)'   // Green
];
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
        
        body {
            background-color: var(--background-color);
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
                        <div class="stat-icon" style="background-color: rgba(99, 102, 241, 0.1); color: #6366f1;">👥</div>
                        <h3 class="stat-title">New Users</h3>
                    </div>
                    <div class="stat-value"><?php echo $total_new_users; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background-color: rgba(20, 184, 166, 0.1); color: #14b8a6;">💼</div>
                        <h3 class="stat-title">New Jobs</h3>
                    </div>
                    <div class="stat-value"><?php echo $new_jobs; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">📝</div>
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
            
            <!-- User Statistics Section with 3 Pie Charts -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">User Statistics</h3>
                    <p class="chart-subtitle">Overview of user distribution and activity</p>
                </div>
                <div class="charts-grid">
                    <!-- Users by Role Pie Chart -->
                    <div class="chart-wrapper">
                        <canvas id="usersByRoleChart"></canvas>
                    </div>
                    
                    <!-- Jobs by Status Pie Chart -->
                    <div class="chart-wrapper">
                        <canvas id="jobsByStatusChart"></canvas>
                    </div>
                    
                    <!-- Recent Activity Pie Chart -->
                    <div class="chart-wrapper">
                        <canvas id="recentActivityChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Top Job Categories Bar Chart -->
            <div class="bar-chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Top Job Categories</h3>
                    <p class="chart-subtitle">Most popular job categories (Top 8)</p>
                </div>
                <div class="chart-wrapper">
                    <canvas id="jobCategoriesChart"></canvas>
                </div>
            </div>
            
            <!-- Top Employers Bar Chart -->
            <div class="bar-chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Top Employers</h3>
                    <p class="chart-subtitle">Employers with most job postings</p>
                </div>
                <div class="chart-wrapper">
                    <canvas id="topEmployersChart"></canvas>
                </div>
            </div>
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
                        return percentage > 5 ? `${percentage}%` : '';
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

        // Users by Role Pie Chart
        const usersByRoleCtx = document.getElementById('usersByRoleChart').getContext('2d');
        const usersByRoleChart = new Chart(usersByRoleCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($user_role_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($user_role_data); ?>,
                    backgroundColor: <?php echo json_encode($user_role_colors); ?>,
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                ...doughnutOptions,
                plugins: {
                    ...doughnutOptions.plugins,
                    title: {
                        display: true,
                        text: 'Users by Role',
                        align: 'start',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            bottom: 20
                        }
                    }
                }
            }
        });

        // Jobs by Status Pie Chart
        const jobsByStatusCtx = document.getElementById('jobsByStatusChart').getContext('2d');
        const jobsByStatusChart = new Chart(jobsByStatusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_data); ?>,
                    backgroundColor: <?php echo json_encode($status_colors); ?>,
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                ...doughnutOptions,
                plugins: {
                    ...doughnutOptions.plugins,
                    title: {
                        display: true,
                        text: 'Jobs by Status',
                        align: 'start',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            bottom: 20
                        }
                    }
                }
            }
        });

        // Recent Activity Pie Chart
        const recentActivityCtx = document.getElementById('recentActivityChart').getContext('2d');
        const recentActivityChart = new Chart(recentActivityCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($activity_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($activity_data); ?>,
                    backgroundColor: <?php echo json_encode($activity_colors); ?>,
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                ...doughnutOptions,
                plugins: {
                    ...doughnutOptions.plugins,
                    title: {
                        display: true,
                        text: 'Recent Activity',
                        align: 'start',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            bottom: 20
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
                    },
                    title: {
                        display: true,
                        text: 'Top Job Categories',
                        position: 'top',
                        align: 'start',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            bottom: 20
                        }
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
                    },
                    title: {
                        display: true,
                        text: 'Top Employers',
                        position: 'top',
                        align: 'start',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            bottom: 20
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>