<?php
// Set page title
$page_title = 'Applications';

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

// Process application actions (shortlist, reject, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['application_id'])) {
    $action = $_POST['action'];
    $application_id = $_POST['application_id'];
    
    // Verify that this application belongs to a job posted by this employer
    $check_query = "SELECT a.application_id 
                  FROM applications a 
                  JOIN jobs j ON a.job_id = j.job_id 
                  WHERE a.application_id = ? AND j.employer_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(1, $application_id);
    $check_stmt->bindParam(2, $employer_id);
    $check_stmt->execute();
    
    if($check_stmt->rowCount() > 0) {
        switch($action) {
            case 'shortlist':
                $status = 'shortlisted';
                $message = "Application shortlisted successfully.";
                break;
                
            case 'reject':
                $status = 'rejected';
                $message = "Application rejected successfully.";
                break;
                
            case 'hire':
                $status = 'hired';
                $message = "Candidate marked as hired successfully.";
                break;
                
            case 'reset':
                $status = 'pending';
                $message = "Application reset to pending successfully.";
                break;
                
            default:
                $status = 'pending';
                $message = "Application status updated.";
        }
        
        // Update application status
        $update_query = "UPDATE applications SET status = ? WHERE application_id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(1, $status);
        $update_stmt->bindParam(2, $application_id);
        
        if($update_stmt->execute()) {
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating application status.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "You do not have permission to modify this application.";
        $_SESSION['message_type'] = "error";
    }
    
    // Redirect back to the same page
    $redirect_url = SITE_URL . "/views/employer/applications.php";
    if(isset($_GET['job_id'])) {
        $redirect_url .= "?job_id=" . $_GET['job_id'];
    }
    if(isset($_GET['status'])) {
        $redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . "status=" . $_GET['status'];
    }
    
    header("Location: " . $redirect_url);
    exit;
}

// Get filter parameters
$job_id_filter = isset($_GET['job_id']) ? $_GET['job_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base query
$query = "SELECT a.*, j.title as job_title, j.location as job_location, j.job_type,
         js.jobseeker_id, u.first_name, u.last_name, u.email, u.phone
         FROM applications a
         JOIN jobs j ON a.job_id = j.job_id
         JOIN jobseeker_profiles js ON a.jobseeker_id = js.jobseeker_id
         JOIN users u ON js.user_id = u.user_id
         WHERE j.employer_id = ?";

// Add job filter if provided
if(!empty($job_id_filter)) {
    $query .= " AND a.job_id = ?";
}

// Add status filter if provided
if(!empty($status_filter)) {
    $query .= " AND a.status = ?";
}

// Add search filter if provided
if(!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR j.title LIKE ?)";
}

// Order by application date, newest first
$query .= " ORDER BY a.applied_at DESC";

$stmt = $db->prepare($query);

// Bind employer ID
$stmt->bindParam(1, $employer_id);

// Bind job filter if provided
$param_index = 2;
if(!empty($job_id_filter)) {
    $stmt->bindParam($param_index, $job_id_filter);
    $param_index++;
}

// Bind status filter if provided
if(!empty($status_filter)) {
    $stmt->bindParam($param_index, $status_filter);
    $param_index++;
}

// Bind search parameters if provided
if(!empty($search)) {
    $search_param = '%' . $search . '%';
    for($i = 0; $i < 4; $i++) {
        $stmt->bindParam($param_index, $search_param);
        $param_index++;
    }
}

$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all jobs for this employer (for filter dropdown)
$jobs_query = "SELECT job_id, title FROM jobs WHERE employer_id = ? ORDER BY title";
$jobs_stmt = $db->prepare($jobs_query);
$jobs_stmt->bindParam(1, $employer_id);
$jobs_stmt->execute();
$employer_jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get application counts by status
$count_query = "SELECT a.status, COUNT(*) as count 
               FROM applications a
               JOIN jobs j ON a.job_id = j.job_id
               WHERE j.employer_id = ?";
               
if(!empty($job_id_filter)) {
    $count_query .= " AND a.job_id = ?";
}

$count_query .= " GROUP BY a.status";

$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(1, $employer_id);

if(!empty($job_id_filter)) {
    $count_stmt->bindParam(2, $job_id_filter);
}

$count_stmt->execute();
$status_counts = $count_stmt->fetchAll(PDO::FETCH_ASSOC);

$application_counts = [
    'all' => 0,
    'pending' => 0,
    'shortlisted' => 0,
    'rejected' => 0,
    'hired' => 0
];

foreach($status_counts as $count) {
    $application_counts[$count['status']] = $count['count'];
    $application_counts['all'] += $count['count'];
}

// Get current job title if job filter is applied
$current_job_title = '';
if(!empty($job_id_filter)) {
    foreach($employer_jobs as $job) {
        if($job['job_id'] == $job_id_filter) {
            $current_job_title = $job['title'];
            break;
        }
    }
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
        /* Applications Styles */
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
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-name {
            font-size: 1.1rem;
            color: #333;
            font-weight: 500;
        }
        
        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .company-name {
            font-size: 1rem;
            color: #666;
        }
        
        .job-filter {
            font-size: 0.9rem;
            color: #666;
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
            text-decoration: none;
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
        
        .status-tab:hover {
            text-decoration: none;
            color: #0056b3;
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
        
        .applications-table {
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
        
        .applicant-name {
            font-weight: 500;
        }
        
        .applicant-email {
            font-size: 0.85rem;
            color: #666;
        }
        
        .job-title {
            font-weight: 500;
        }
        
        .job-meta {
            font-size: 0.85rem;
            color: #666;
        }
        
        .application-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .status-pending {
            background-color: #e0f7fa;
            color: #0097a7;
        }
        
        .status-shortlisted {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .status-rejected {
            background-color: #fbe9e7;
            color: #d32f2f;
        }
        
        .status-hired {
            background-color: #e3f2fd;
            color: #1976d2;
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
        
        .btn-shortlist {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .btn-hire {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-reject {
            background-color: #fbe9e7;
            color: #d32f2f;
        }
        
        .btn-reset {
            background-color: #f5f5f5;
            color: #757575;
        }
        
        .no-applications {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .date-applied {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="employer-container">
        <?php include 'employer-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Applications</h1>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($employer['first_name'] . ' ' . $employer['last_name']); ?>
                    </div>
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($employer['company_name']); ?></span>
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
                        <label for="job_id">Filter by Job</label>
                        <select id="job_id" name="job_id">
                            <option value="">All Jobs</option>
                            <?php foreach($employer_jobs as $job): ?>
                                <option value="<?php echo $job['job_id']; ?>" <?php if($job_id_filter == $job['job_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="search">Search Applicants</label>
                        <input type="text" id="search" name="search" placeholder="Name or email" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <button type="submit" class="btn-filter">Apply Filters</button>
                    <a href="<?php echo SITE_URL; ?>/views/employer/applications.php" class="btn-reset">Reset</a>
                </form>
            </div>
            
            <div class="status-tabs">
                <?php
                // Prepare URL base for tabs
                $tab_url = SITE_URL . "/views/employer/applications.php";
                if(!empty($job_id_filter)) {
                    $tab_url .= "?job_id=" . $job_id_filter;
                }
                $tab_url_with_params = $tab_url . (strpos($tab_url, '?') !== false ? '&' : '?') . "status=";
                ?>
                
                <a href="<?php echo $tab_url; ?>" class="status-tab <?php if(empty($status_filter)) echo 'active'; ?>">
                    All <span class="status-count"><?php echo $application_counts['all']; ?></span>
                </a>
                <a href="<?php echo $tab_url_with_params; ?>pending" class="status-tab <?php if($status_filter == 'pending') echo 'active'; ?>">
                    Pending <span class="status-count"><?php echo $application_counts['pending']; ?></span>
                </a>
                <a href="<?php echo $tab_url_with_params; ?>shortlisted" class="status-tab <?php if($status_filter == 'shortlisted') echo 'active'; ?>">
                    Shortlisted <span class="status-count"><?php echo $application_counts['shortlisted']; ?></span>
                </a>
                <a href="<?php echo $tab_url_with_params; ?>hired" class="status-tab <?php if($status_filter == 'hired') echo 'active'; ?>">
                    Hired <span class="status-count"><?php echo $application_counts['hired']; ?></span>
                </a>
                <a href="<?php echo $tab_url_with_params; ?>rejected" class="status-tab <?php if($status_filter == 'rejected') echo 'active'; ?>">
                    Rejected <span class="status-count"><?php echo $application_counts['rejected']; ?></span>
                </a>
            </div>
            
            <?php if(count($applications) > 0): ?>
                <div class="applications-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th>Job</th>
                                <th>Status</th>
                                <th>Applied On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($applications as $application): ?>
                                <tr>
                                    <td>
                                        <div class="applicant-name">
                                            <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                                        </div>
                                        <div class="applicant-email">
                                            <?php echo htmlspecialchars($application['email']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="job-title">
                                            <?php echo htmlspecialchars($application['job_title']); ?>
                                        </div>
                                        <div class="job-meta">
                                            <?php echo htmlspecialchars($application['job_location']); ?> • <?php echo ucfirst($application['job_type']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="application-status status-<?php echo $application['status']; ?>">
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </td>
                                    <td class="date-applied">
                                        <?php echo date('M d, Y', strtotime($application['applied_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="<?php echo SITE_URL; ?>/views/employer/view-application.php?id=<?php echo $application['application_id']; ?>" class="btn-action btn-view">View</a>
                                            
                                            <?php if($application['status'] != 'shortlisted' && $application['status'] != 'hired'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                    <input type="hidden" name="action" value="shortlist">
                                                    <button type="submit" class="btn-action btn-shortlist">Shortlist</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if($application['status'] != 'hired'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                    <input type="hidden" name="action" value="hire">
                                                    <button type="submit" class="btn-action btn-hire">Hire</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if($application['status'] != 'rejected'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn-action btn-reject">Reject</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if($application['status'] != 'pending'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                    <input type="hidden" name="action" value="reset">
                                                    <button type="submit" class="btn-action btn-reset">Reset</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-applications">
                    <h3>No applications found</h3>
                    <p>There are no applications that match your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>