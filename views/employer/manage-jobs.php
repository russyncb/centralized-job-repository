<?php
// Set page title
$page_title = 'Manage Jobs';

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

// Process job actions (activate, close, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['job_id'])) {
    $action = $_POST['action'];
    $job_id = $_POST['job_id'];
    
    // Verify that this job belongs to the employer
    $check_query = "SELECT job_id FROM jobs WHERE job_id = ? AND employer_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(1, $job_id);
    $check_stmt->bindParam(2, $employer_id);
    $check_stmt->execute();
    
    if($check_stmt->rowCount() > 0) {
        switch($action) {
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
                $delete_query = "DELETE FROM jobs WHERE job_id = ?";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->bindParam(1, $job_id);
                
                if($delete_stmt->execute()) {
                    $_SESSION['message'] = "Job deleted successfully.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error deleting job.";
                    $_SESSION['message_type'] = "error";
                }
                
                // Redirect
                header("Location: " . SITE_URL . "/views/employer/manage-jobs.php");
                exit;
                
            default:
                $status = 'active';
                $message = "Job status updated.";
        }
        
        // Update job status
        $update_query = "UPDATE jobs SET status = ? WHERE job_id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(1, $status);
        $update_stmt->bindParam(2, $job_id);
        
        if($update_stmt->execute()) {
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating job status.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "You do not have permission to modify this job.";
        $_SESSION['message_type'] = "error";
    }
    
    // Redirect
    header("Location: " . SITE_URL . "/views/employer/manage-jobs.php");
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'posted_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Base query
$query = "SELECT j.*, 
         (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as applications_count
         FROM jobs j 
         WHERE j.employer_id = ?";

// Add status filter if provided
if(!empty($status_filter)) {
    $query .= " AND j.status = ?";
}

// Add search filter if provided
if(!empty($search)) {
    $query .= " AND (j.title LIKE ? OR j.location LIKE ? OR j.category LIKE ?)";
}

// Add sorting
$valid_sort_columns = [
    'title' => 'j.title',
    'status' => 'j.status',
    'applications' => 'applications_count',
    'posted_at' => 'j.posted_at',
    'deadline' => 'j.application_deadline'
];

$sort_column = isset($valid_sort_columns[$sort_by]) ? $valid_sort_columns[$sort_by] : 'j.posted_at';
$sort_direction = $sort_order === 'ASC' ? 'ASC' : 'DESC';
$query .= " ORDER BY " . $sort_column . " " . $sort_direction;

$stmt = $db->prepare($query);

// Bind employer ID
$stmt->bindParam(1, $employer_id);

// Bind status filter if provided
$param_index = 2;
if(!empty($status_filter)) {
    $stmt->bindParam($param_index, $status_filter);
    $param_index++;
}

// Bind search parameters if provided
if(!empty($search)) {
    $search_param = '%' . $search . '%';
    $stmt->bindParam($param_index, $search_param);
    $param_index++;
    $stmt->bindParam($param_index, $search_param);
    $param_index++;
    $stmt->bindParam($param_index, $search_param);
}

$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total job counts by status
$count_query = "SELECT status, COUNT(*) as count FROM jobs WHERE employer_id = ? GROUP BY status";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(1, $employer_id);
$count_stmt->execute();
$status_counts = $count_stmt->fetchAll(PDO::FETCH_ASSOC);

$job_counts = [
    'all' => 0,
    'active' => 0,
    'closed' => 0,
    'draft' => 0,
    'archived' => 0
];

foreach($status_counts as $count) {
    $job_counts[$count['status']] = $count['count'];
    $job_counts['all'] += $count['count'];
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
        /* Manage Jobs Styles */
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
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: #fff;
            padding: 0;
            box-shadow: 2px 0 8px rgba(0,0,0,0.07);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: all 0.3s ease;
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
            font-size: 1.7rem;
            font-weight: bold;
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
        
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            transition: margin-left 0.3s ease;
            margin-left: 0;
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 70px;
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
        
        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .company-name {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.85);
        }
        
        .verification-badge {
            display: inline-flex;
            align-items: center;
            background-color: rgba(232, 245, 233, 0.9);
            color: #388e3c;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 15px;
            backdrop-filter: blur(4px);
        }
        
        .verification-badge .icon {
            margin-right: 5px;
        }
        
        .pending-verification {
            background-color: rgba(255, 248, 225, 0.9);
            color: #f57c00;
        }
        
        .action-button {
            display: inline-flex;
            align-items: center;
            background-color: #0056b3;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.95rem;
        }
        
        .action-button:hover {
            background-color: #004494;
            text-decoration: none;
            color: white;
        }
        
        .action-button .icon {
            margin-right: 8px;
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
        
        .filters-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        
        .btn-filter {
            background-color: #0056b3;
            color: white;
            border: none;
            padding: 9px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-reset {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            padding: 9px 15px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .btn-reset:hover {
            background-color: #5a6268;
            color: white;
            text-decoration: none;
        }
        
        .status-tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        
        .status-tab {
            padding: 10px 20px;
            cursor: pointer;
            color: #555;
            font-weight: 500;
            position: relative;
        }
        
        .status-tab.active {
            color: #0056b3;
        }
        
        .status-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #0056b3;
        }
        
        .status-count {
            display: inline-block;
            background-color: #f0f0f0;
            color: #555;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.85rem;
            margin-left: 5px;
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
        
        .job-title {
            font-weight: 500;
        }
        
        .job-title a {
            color: #0056b3;
            text-decoration: none;
        }
        
        .job-title a:hover {
            text-decoration: underline;
        }
        
        .job-meta {
            font-size: 0.85rem;
            color: #666;
        }
        
        .job-status {
            display: inline-block;
            padding: 4px 10px;
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
        
        .applications {
            text-align: center;
            font-weight: 500;
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
            background-color: #f0f0f0;
            color: #333;
        }
        
        .btn-view {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-edit {
            background-color: #e8f5e9;
            color: #388e3c;
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
            background-color: #fbe9e7;
            color: #d32f2f;
        }
        
        .no-jobs {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination-link {
            display: inline-block;
            padding: 5px 10px;
            margin: 0 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
        }
        
        .pagination-link.active {
            background-color: #0056b3;
            color: white;
            border-color: #0056b3;
        }
        
        .pagination-link:hover:not(.active) {
            background-color: #f5f5f5;
        }
        
        .deadline {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .deadline.expired {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .deadline.ending-soon {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .no-deadline {
            color: #757575;
            font-style: italic;
            font-size: 0.85rem;
        }
        
        .jobs-table th a {
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .jobs-table th a:hover {
            color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="employer-container">
        <?php include 'employer-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Manage Jobs</h1>
                <div class="user-info">
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($employer['company_name']); ?></span>
                        <?php if($is_verified): ?>
                            <span class="verification-badge">
                                <span class="icon">✓</span> Verified
                            </span>
                        <?php else: ?>
                            <span class="verification-badge pending-verification">
                                <span class="icon">⌛</span> Pending Verification
                            </span>
                        <?php endif; ?>
                    </div>
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
            
            <div class="filters-container">
                <form method="get" action="" class="filter-form">
                    <div class="form-group">
                        <label for="search">Search Jobs</label>
                        <input type="text" id="search" name="search" placeholder="Title, location, or category" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?php if($status_filter == 'active') echo 'selected'; ?>>Active</option>
                            <option value="closed" <?php if($status_filter == 'closed') echo 'selected'; ?>>Closed</option>
                            <option value="draft" <?php if($status_filter == 'draft') echo 'selected'; ?>>Draft</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-filter">Apply Filters</button>
                    <a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php" class="btn-reset">Reset</a>
                </form>
            </div>
            
            <div class="status-tabs">
                <a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php" class="status-tab <?php if(empty($status_filter)) echo 'active'; ?>">
                    All <span class="status-count"><?php echo $job_counts['all']; ?></span>
                </a>
                <a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php?status=active" class="status-tab <?php if($status_filter == 'active') echo 'active'; ?>">
                    Active <span class="status-count"><?php echo $job_counts['active']; ?></span>
                </a>
                <a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php?status=closed" class="status-tab <?php if($status_filter == 'closed') echo 'active'; ?>">
                    Closed <span class="status-count"><?php echo $job_counts['closed']; ?></span>
                </a>
                <a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php?status=draft" class="status-tab <?php if($status_filter == 'draft') echo 'active'; ?>">
                    Draft <span class="status-count"><?php echo $job_counts['draft']; ?></span>
                </a>
            </div>
            
            <?php if(count($jobs) > 0): ?>
                <div class="jobs-table">
                    <table>
                        <thead>
                            <tr>
                                <th>
                                    <a href="?sort=title&order=<?php echo ($sort_by === 'title' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        Job <?php echo $sort_by === 'title' ? ($sort_order === 'ASC' ? '↑' : '↓') : ''; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=status&order=<?php echo ($sort_by === 'status' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        Status <?php echo $sort_by === 'status' ? ($sort_order === 'ASC' ? '↑' : '↓') : ''; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=applications&order=<?php echo ($sort_by === 'applications' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        Applications <?php echo $sort_by === 'applications' ? ($sort_order === 'ASC' ? '↑' : '↓') : ''; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=posted_at&order=<?php echo ($sort_by === 'posted_at' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        Posted <?php echo $sort_by === 'posted_at' ? ($sort_order === 'ASC' ? '↑' : '↓') : ''; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=deadline&order=<?php echo ($sort_by === 'deadline' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        Deadline <?php echo $sort_by === 'deadline' ? ($sort_order === 'ASC' ? '↑' : '↓') : ''; ?>
                                    </a>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($jobs as $job): ?>
                                <tr>
                                    <td>
                                        <div class="job-title">
                                            <a href="<?php echo SITE_URL; ?>/views/employer/view-job.php?id=<?php echo $job['job_id']; ?>">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </a>
                                        </div>
                                        <div class="job-meta">
                                            <?php echo htmlspecialchars($job['location']); ?> • <?php echo ucfirst($job['job_type']); ?> • <?php echo htmlspecialchars($job['category']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="job-status status-<?php echo $job['status']; ?>">
                                            <?php echo ucfirst($job['status']); ?>
                                        </span>
                                    </td>
                                    <td class="applications">
                                        <a href="<?php echo SITE_URL; ?>/views/employer/applications.php?job_id=<?php echo $job['job_id']; ?>">
                                            <?php echo $job['applications_count']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($job['posted_at'])); ?></td>
                                    <td>
                                        <?php if($job['application_deadline']): ?>
                                            <?php 
                                            $deadline = new DateTime($job['application_deadline']);
                                            $today = new DateTime();
                                            $interval = $today->diff($deadline);
                                            $deadline_passed = $today > $deadline;
                                            ?>
                                            <span class="deadline <?php echo $deadline_passed ? 'expired' : ($interval->days <= 7 ? 'ending-soon' : ''); ?>">
                                                <?php 
                                                if($deadline_passed) {
                                                    echo 'Expired';
                                                } else {
                                                    echo date('M d, Y', strtotime($job['application_deadline']));
                                                    if($interval->days <= 7) {
                                                        echo ' (' . $interval->days . ' days left)';
                                                    }
                                                }
                                                ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="no-deadline">No deadline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="<?php echo SITE_URL; ?>/views/employer/view-job.php?id=<?php echo $job['job_id']; ?>" class="btn-action btn-view">View</a>
                                            <a href="<?php echo SITE_URL; ?>/views/employer/edit-job.php?id=<?php echo $job['job_id']; ?>" class="btn-action btn-edit">Edit</a>
                                            
                                            <?php if($job['status'] != 'active'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn-action btn-activate">Activate</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if($job['status'] == 'active'): ?>
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
                </div>
            <?php else: ?>
                <div class="no-jobs">
                    <h3>No jobs found</h3>
                    <p>You haven't posted any jobs yet, or no jobs match your search criteria.</p>
                    <p><a href="<?php echo SITE_URL; ?>/views/employer/post-job.php">Post your first job</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>