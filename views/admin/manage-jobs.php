<?php
// Set page title
$page_title = 'Manage Jobs';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['job_id'])) {
        $action = $_POST['action'];
        $job_id = $_POST['job_id'];
        
        switch ($action) {
            case 'activate':
                $status = 'active';
                $message = "Job activated successfully.";
                break;
            case 'close':
                $status = 'closed';
                $message = "Job closed successfully.";
                break;
            case 'delete':
                // Delete job
                $query = "DELETE FROM jobs WHERE job_id = :job_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':job_id', $job_id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Job deleted successfully.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error deleting job.";
                    $_SESSION['message_type'] = "error";
                }
                
                header("Location: " . SITE_URL . "/views/admin/manage-jobs.php");
                exit;
                break;
            default:
                $status = 'active';
                $message = "Job status updated.";
        }
        
        // Update job status
        $query = "UPDATE jobs SET status = :status WHERE job_id = :job_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':job_id', $job_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating job status.";
            $_SESSION['message_type'] = "error";
        }
    }
    
    // Redirect to refresh the page
    header("Location: " . SITE_URL . "/views/admin/manage-jobs.php");
    exit;
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Prepare the query
$query = "SELECT j.job_id, j.title, j.location, j.job_type, j.category, j.status, j.posted_at, 
         e.company_name, u.first_name, u.last_name, u.email 
         FROM jobs j
         JOIN employer_profiles e ON j.employer_id = e.employer_id
         JOIN users u ON e.user_id = u.user_id
         WHERE 1=1";

// Add search condition if provided
if (!empty($search)) {
    $query .= " AND (j.title LIKE :search OR j.location LIKE :search OR e.company_name LIKE :search)";
}

// Add category filter if provided
if (!empty($category_filter)) {
    $query .= " AND j.category = :category";
}

// Add status filter if provided
if (!empty($status_filter)) {
    $query .= " AND j.status = :status";
}

// Order by posted date, newest first
$query .= " ORDER BY j.posted_at DESC";

$stmt = $db->prepare($query);

// Bind search parameter if provided
if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $stmt->bindParam(':search', $search_param);
}

// Bind category filter if provided
if (!empty($category_filter)) {
    $stmt->bindParam(':category', $category_filter);
}

// Bind status filter if provided
if (!empty($status_filter)) {
    $stmt->bindParam(':status', $status_filter);
}

$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get job categories
$query_categories = "SELECT name FROM job_categories ORDER BY name";
$stmt_categories = $db->prepare($query_categories);
$stmt_categories->execute();
$categories = $stmt_categories->fetchAll(PDO::FETCH_COLUMN);

// Count job statuses
$query_statuses = "SELECT status, COUNT(*) as count FROM jobs GROUP BY status";
$stmt_statuses = $db->prepare($query_statuses);
$stmt_statuses->execute();
$status_counts = $stmt_statuses->fetchAll(PDO::FETCH_ASSOC);

$status_totals = [
    'active' => 0,
    'closed' => 0,
    'draft' => 0,
    'archived' => 0
];

foreach ($status_counts as $count) {
    $status_totals[$count['status']] = $count['count'];
}

$total_jobs = array_sum($status_totals);
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
        
        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 15px;
            flex: 1;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 5px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .filters {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #666;
        }
        
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn-filter {
            background-color: #0056b3;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            align-self: flex-end;
        }
        
        .btn-reset {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            align-self: flex-end;
        }
        
        .jobs-table {
            width: 100%;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .job-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.85rem;
            text-align: center;
        }
        
        .type-full-time {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .type-part-time {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .type-contract {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
       .freelance {
        background-color: #e3f2fd;
        color: #1976d2;
       }
       
       .type-internship {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .type-remote {
            background-color: #e0f7fa;
            color: #0097a7;
        }
        
        .job-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.85rem;
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
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-action {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        
        .btn-view {
            background-color: #e3f2fd;
            color: #1976d2;
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
        
        .no-jobs {
            text-align: center;
            padding: 50px 20px;
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
                <h1>Manage Jobs</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></span>
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
            
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_jobs; ?></div>
                    <div class="stat-label">Total Jobs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $status_totals['active']; ?></div>
                    <div class="stat-label">Active Jobs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $status_totals['closed']; ?></div>
                    <div class="stat-label">Closed Jobs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $status_totals['draft'] + $status_totals['archived']; ?></div>
                    <div class="stat-label">Other</div>
                </div>
            </div>
            
            <div class="filters">
                <form method="get" class="filter-form">
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Job title, location or company" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category; ?>" <?php if($category_filter == $category) echo 'selected'; ?>>
                                    <?php echo $category; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?php if($status_filter == 'active') echo 'selected'; ?>>Active</option>
                            <option value="closed" <?php if($status_filter == 'closed') echo 'selected'; ?>>Closed</option>
                            <option value="draft" <?php if($status_filter == 'draft') echo 'selected'; ?>>Draft</option>
                            <option value="archived" <?php if($status_filter == 'archived') echo 'selected'; ?>>Archived</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-filter">Apply Filters</button>
                    <a href="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php" class="btn-reset">Reset</a>
                </form>
            </div>
            
            <div class="jobs-table">
                <?php if(count($jobs) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Company</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Posted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($jobs as $job): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($job['title']); ?></td>
                                    <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($job['location']); ?></td>
                                    <td>
                                        <span class="job-type type-<?php echo str_replace(' ', '-', strtolower($job['job_type'])); ?>">
                                            <?php echo ucfirst($job['job_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($job['category']); ?></td>
                                    <td>
                                        <span class="job-status status-<?php echo $job['status']; ?>">
                                            <?php echo ucfirst($job['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($job['posted_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="<?php echo SITE_URL; ?>/views/admin/view-job.php?id=<?php echo $job['job_id']; ?>" class="btn-action btn-view">View</a>
                                            
                                            <?php if($job['status'] != 'active'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn-action btn-activate">Activate</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if($job['status'] != 'closed'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                    <input type="hidden" name="action" value="close">
                                                    <button type="submit" class="btn-action btn-close">Close</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this job? This action cannot be undone.');">
                                                <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn-action btn-delete">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-jobs">
                        <h3>No Jobs Found</h3>
                        <p>No jobs match your search criteria. Try adjusting your filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>      