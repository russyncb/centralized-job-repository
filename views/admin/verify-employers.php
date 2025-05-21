<?php
// Set page title
$page_title = 'Verify Employers';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Process verification or rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify']) && isset($_POST['employer_id'])) {
        $employer_id = $_POST['employer_id'];
        $admin_id = $_SESSION['user_id'];
        
        // Update employer verification status
        $query = "UPDATE employer_profiles 
                 SET verified = 1, 
                     verified_at = NOW(), 
                     verified_by = :admin_id 
                 WHERE employer_id = :employer_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':admin_id', $admin_id);
        $stmt->bindParam(':employer_id', $employer_id);
        
        if ($stmt->execute()) {
            // Update user status to active
            $query_user = "UPDATE users u
                          JOIN employer_profiles e ON u.user_id = e.user_id
                          SET u.status = 'active'
                          WHERE e.employer_id = :employer_id";
            
            $stmt_user = $db->prepare($query_user);
            $stmt_user->bindParam(':employer_id', $employer_id);
            
            if ($stmt_user->execute()) {
                $_SESSION['message'] = "Employer verified successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating user status.";
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Error verifying employer.";
            $_SESSION['message_type'] = "error";
        }
    } elseif (isset($_POST['reject']) && isset($_POST['employer_id'])) {
        $employer_id = $_POST['employer_id'];
        
        // Update user status to rejected
        $query_user = "UPDATE users u
                      JOIN employer_profiles e ON u.user_id = e.user_id
                      SET u.status = 'rejected'
                      WHERE e.employer_id = :employer_id";
        
        $stmt_user = $db->prepare($query_user);
        $stmt_user->bindParam(':employer_id', $employer_id);
        
        if ($stmt_user->execute()) {
            $_SESSION['message'] = "Employer application rejected.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error rejecting employer.";
            $_SESSION['message_type'] = "error";
        }
    }
    
    // If there's a next_id parameter, redirect to that employer
    if(isset($_POST['next_id']) && !empty($_POST['next_id'])) {
        header("Location: " . SITE_URL . "/views/admin/verify-employers.php?employer_id=" . $_POST['next_id']);
        exit;
    }
    
    // Redirect to refresh the page
    header("Location: " . SITE_URL . "/views/admin/verify-employers.php");
    exit;
}

// Handle search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'pending';

// Get employer_id from GET parameter for individual view if provided
$current_employer_id = isset($_GET['employer_id']) ? (int)$_GET['employer_id'] : 0;

// Build query based on whether we're viewing a specific employer or list
if($current_employer_id > 0) {
    // Get a specific employer
    $query = "SELECT e.*, u.user_id, u.first_name, u.last_name, u.email, u.phone, u.status, u.created_at
             FROM employer_profiles e
             JOIN users u ON e.user_id = u.user_id
             WHERE u.role = 'employer' AND e.employer_id = :employer_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':employer_id', $current_employer_id);
} else {
    // Get all employers matching the criteria
    $query = "SELECT e.*, u.user_id, u.first_name, u.last_name, u.email, u.phone, u.status, u.created_at
             FROM employer_profiles e
             JOIN users u ON e.user_id = u.user_id
             WHERE u.role = 'employer'";
    
    // Add status filter if not viewing all
    if($status !== 'all') {
        $query .= " AND u.status = :status";
    }
    
    // Add search condition if search is not empty
    if(!empty($search)) {
        $query .= " AND (e.company_name LIKE :search 
                   OR u.first_name LIKE :search 
                   OR u.last_name LIKE :search 
                   OR u.email LIKE :search 
                   OR e.industry LIKE :search 
                   OR e.location LIKE :search)";
    }
    
    $query .= " ORDER BY u.created_at DESC";
    
    $stmt = $db->prepare($query);
    
    // Bind status parameter if needed
    if($status !== 'all') {
        $stmt->bindParam(':status', $status);
    }
    
    // Bind search parameter if needed
    if(!empty($search)) {
        $searchParam = "%{$search}%";
        $stmt->bindParam(':search', $searchParam);
    }
}

$stmt->execute();
$employers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter to just pending employers for the main display
$pending_employers = array_filter($employers, function($employer) {
    return $employer['status'] === 'pending';
});

// If viewing a specific employer, get previous and next employer IDs
$prev_id = $next_id = 0;
if($current_employer_id > 0 && count($pending_employers) > 0) {
    // Get all employer IDs in order for navigation
    $query_all_ids = "SELECT e.employer_id
                     FROM employer_profiles e
                     JOIN users u ON e.user_id = u.user_id
                     WHERE u.status = 'pending' AND u.role = 'employer'
                     ORDER BY u.created_at DESC";
    $stmt_all_ids = $db->prepare($query_all_ids);
    $stmt_all_ids->execute();
    $all_ids = $stmt_all_ids->fetchAll(PDO::FETCH_COLUMN);
    
    // Find current position in the list
    $current_position = array_search($current_employer_id, $all_ids);
    
    // Get previous and next IDs if they exist
    if($current_position !== false) {
        $prev_id = ($current_position > 0) ? $all_ids[$current_position - 1] : 0;
        $next_id = (isset($all_ids[$current_position + 1])) ? $all_ids[$current_position + 1] : 0;
    }
}

// Get total pending count for the badge
$query_pending = "SELECT COUNT(*) as count FROM users WHERE role = 'employer' AND status = 'pending'";
$stmt_pending = $db->prepare($query_pending);
$stmt_pending->execute();
$pending_count = $stmt_pending->fetch(PDO::FETCH_ASSOC)['count'];
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
            margin: 0;
            padding: 0;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 270px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: white;
            padding: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            color: white;
            font-size: 1.3rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-logo {
            background: #fff;
            color: #1a3b5d;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            gap: 12px;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #FFC107;
        }
        
        .sidebar-menu a i {
            font-size: 1.2rem;
            width: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .badge {
            background-color: #dc3545;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .main-content {
            flex: 1;
            padding: 20px 30px;
            margin-left: 270px;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            font-weight: 500;
            color: #495057;
        }
        
        /* New Search Bar Styles */
        .filter-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        
        .filter-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.15s ease-in-out;
        }
        
        .filter-input:focus {
            border-color: #1557b0;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(21, 87, 176, 0.25);
        }
        
        .filter-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
            background-color: #fff;
            transition: border-color 0.15s ease-in-out;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px 12px;
        }
        
        .filter-select:focus {
            border-color: #1557b0;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(21, 87, 176, 0.25);
        }
        
        .filter-button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-apply {
            background-color: #1557b0;
            color: white;
            min-width: 120px;
        }
        
        .btn-apply:hover {
            background-color: #12468e;
        }
        
        .btn-reset {
            background-color: #6c757d;
            color: white;
            min-width: 120px;
        }
        
        .btn-reset:hover {
            background-color: #5a6268;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
        /* Message Styles */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Employer Card Styles - Collapsible */
        .employer-cards {
            margin-top: 20px;
        }
        
        .employer-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            margin-bottom: 15px;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .employer-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        .employer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 25px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eef2f7;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .employer-header:hover {
            background-color: #e9ecef;
        }
        
        .employer-name {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .company-icon {
            width: 40px;
            height: 40px;
            background-color: #1557b0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .company-info h3 {
            margin: 0;
            font-size: 1.3rem;
            color: #1a3b5d;
            font-weight: 600;
        }
        
        .company-info p {
            margin: 5px 0 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .toggle-icon {
            font-size: 1.5rem;
            color: #6c757d;
            transition: transform 0.3s ease;
        }
        
        .employer-content {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        
        .employer-content.open {
            padding: 25px;
            max-height: 1000px; /* Large enough to fit content */
        }
        
        .employer-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .detail-group {
            margin-bottom: 15px;
        }
        
        .detail-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #4a5568;
            font-size: 0.9rem;
        }
        
        .detail-group span {
            font-size: 1rem;
            color: #2d3748;
            word-break: break-word;
        }
        
        .detail-group span a {
            color: #1557b0;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .detail-group span a:hover {
            color: #0f4c8a;
            text-decoration: underline;
        }
        
        .employer-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-verify {
            background-color: #28a745;
            color: white;
            box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3);
        }
        
        .btn-verify:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.4);
        }
        
        .btn-reject {
            background-color: #dc3545;
            color: white;
            box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3);
        }
        
        .btn-reject:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.4);
        }
        
        .btn-document {
            background-color: #17a2b8;
            color: white;
            box-shadow: 0 2px 5px rgba(23, 162, 184, 0.3);
        }
        
        .btn-document:hover {
            background-color: #138496;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.4);
        }
        
        .btn-navigate {
            background-color: #6c757d;
            color: white;
            box-shadow: 0 2px 5px rgba(108, 117, 125, 0.3);
        }
        
        .btn-navigate:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.4);
        }
        
        .btn-navigate.disabled {
            background-color: #adb5bd;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .navigation-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .no-employers {
            text-align: center;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            padding: 60px 30px;
            margin-top: 20px;
        }
        
        .no-employers h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #1a3b5d;
            font-size: 1.8rem;
        }
        
        .no-employers p {
            margin: 0;
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        /* Quick Actions for Each Card */
        .quick-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quick-action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: white;
            border: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .quick-action-btn:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }
        
        .quick-action-view {
            color: #1557b0;
        }
        
        .quick-action-approve {
            color: #28a745;
        }
        
        .quick-action-reject {
            color: #dc3545;
        }
        
        .rotated {
            transform: rotate(180deg);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 80%;
            max-width: 900px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .modal-title {
            font-size: 1.5rem;
            color: #1a3b5d;
            margin: 0;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }
        
        .modal-body {
            padding: 20px 0;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar-header h3 span {
                display: none;
            }
            
            .sidebar-menu a span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .employer-details {
                grid-template-columns: 1fr;
            }
            
            .employer-actions {
                flex-direction: column;
            }
            
            .employer-actions form {
                width: 100%;
            }
            
            .employer-actions button {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Simplified sidebar that doesn't rely on admin-sidebar.php -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>
                    <div class="sidebar-logo">S</div>
                    <span>ShaSha Admin</span>
                </h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="<?php echo SITE_URL; ?>/views/admin/dashboard.php">
                        <i>📊</i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php" class="active">
                        <i>✓</i>
                        <span>Verify Employers</span>
                        <?php if($pending_count > 0): ?>
                            <span class="badge"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/views/admin/manage-users.php">
                        <i>👥</i>
                        <span>Users</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php">
                        <i>💼</i>
                        <span>Jobs</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/views/admin/queries.php">
                        <i>💬</i>
                        <span>Queries</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/views/admin/analytics.php">
                        <i>📈</i>
                        <span>Analytics</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/views/admin/settings.php">
                        <i>⚙️</i>
                        <span>Settings</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>/views/auth/logout.php">
                        <i>🚪</i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1><?php echo $page_title; ?></h1>
                <div class="user-info">
                    Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
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
            
            <!-- New Search & Filter Interface -->
            <div class="filter-container">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" class="filter-input" placeholder="Company name, email or contact person" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="filter-select">
                                <option value="pending" <?php if($status == 'pending') echo 'selected'; ?>>Pending</option>
                                <option value="active" <?php if($status == 'active') echo 'selected'; ?>>Active</option>
                                <option value="rejected" <?php if($status == 'rejected') echo 'selected'; ?>>Rejected</option>
                                <option value="all" <?php if($status == 'all') echo 'selected'; ?>>All Statuses</option>
                            </select>
                        </div>
                        
                        <div class="filter-buttons">
                            <button type="submit" class="filter-button btn-apply">Apply Filters</button>
                            <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php" class="filter-button btn-reset">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if(count($pending_employers) > 0): ?>
                <!-- If viewing a specific employer, show navigation controls -->
                <?php if($current_employer_id > 0): ?>
                    <div class="navigation-controls">
                        <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php" class="btn btn-navigate">
                            <i>←</i> Back to List
                        </a>
                        <div>
                            <?php if($prev_id > 0): ?>
                                <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php?employer_id=<?php echo $prev_id; ?>" class="btn btn-navigate">
                                    <i>←</i> Previous
                                </a>
                            <?php else: ?>
                                <button class="btn btn-navigate disabled">
                                    <i>←</i> Previous
                                </button>
                            <?php endif; ?>
                            
                            <?php if($next_id > 0): ?>
                                <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php?employer_id=<?php echo $next_id; ?>" class="btn btn-navigate">
                                    Next <i>→</i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-navigate disabled">
                                    Next <i>→</i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="employer-cards">
                    <?php foreach($pending_employers as $employer): ?>
                        <?php 
                            $employer_id = $employer['employer_id'];
                            // Get first letter of company name for icon
                            $company_initial = strtoupper(substr($employer['company_name'], 0, 1));
                            // Get formatted date
                            $application_date = date('M d, Y', strtotime($employer['created_at']));
                            // Determine if this card should be expanded (if it's the current view)
                            $is_expanded = ($current_employer_id > 0 && $current_employer_id == $employer_id);
                            // Business file information
                            $has_business_file = !empty($employer['business_file']);
                            $business_file_path = $has_business_file ? $employer['business_file'] : '';
                        ?>
                        <div class="employer-card" data-employer-id="<?php echo $employer_id; ?>">
                            <div class="employer-header" onclick="toggleEmployerCard(<?php echo $employer_id; ?>)">
                                <div class="employer-name">
                                    <div class="company-icon"><?php echo $company_initial; ?></div>
                                    <div class="company-info">
                                        <h3><?php echo htmlspecialchars($employer['company_name']); ?></h3>
                                        <p>Application date: <?php echo $application_date; ?></p>
                                    </div>
                                </div>
                                <div class="quick-actions">
                                    <?php if($has_business_file): ?>
                                        <button type="button" class="quick-action-btn quick-action-view" title="View Document" 
                                                onclick="viewDocument('<?php echo htmlspecialchars($business_file_path); ?>', '<?php echo htmlspecialchars($employer['company_name']); ?>'); event.stopPropagation();">
                                            📄
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="quick-action-btn" title="No Document Available" disabled>
                                            ❌📄
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="quick-action-btn quick-action-reject" title="Reject" 
                                            onclick="if(confirm('Are you sure you want to reject this employer?')) { document.getElementById('reject-form-<?php echo $employer_id; ?>').submit(); }; event.stopPropagation();">
                                        ❌
                                    </button>
                                    <button type="button" class="quick-action-btn quick-action-approve" title="Approve" 
                                            onclick="document.getElementById('verify-form-<?php echo $employer_id; ?>').submit(); event.stopPropagation();">
                                        ✓
                                    </button>
                                    <div class="toggle-icon <?php echo $is_expanded ? 'rotated' : ''; ?>">▼</div>
                                </div>
                            </div>
                            
                            <div class="employer-content <?php echo $is_expanded ? 'open' : ''; ?>">
                                <div class="employer-details">
                                    <div>
                                        <div class="detail-group">
                                            <label>Contact Person</label>
                                            <span><?php echo htmlspecialchars($employer['first_name'] . ' ' . $employer['last_name']); ?></span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Email</label>
                                            <span><?php echo htmlspecialchars($employer['email']); ?></span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Phone</label>
                                            <span><?php echo htmlspecialchars($employer['phone'] ?? 'Not provided'); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="detail-group">
                                            <label>Industry</label>
                                            <span><?php echo htmlspecialchars($employer['industry'] ?? 'Not provided'); ?></span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Location</label>
                                            <span><?php echo htmlspecialchars($employer['location'] ?? 'Not provided'); ?></span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Website</label>
                                            <span>
                                                <?php if(!empty($employer['website'])): ?>
                                                    <a href="<?php echo htmlspecialchars($employer['website']); ?>" target="_blank"><?php echo htmlspecialchars($employer['website']); ?></a>
                                                <?php else: ?>
                                                    Not provided
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Business Document</label>
                                            <span>
                                                <?php if($has_business_file): ?>
                                                    <a href="#" onclick="viewDocument('<?php echo htmlspecialchars($business_file_path); ?>', '<?php echo htmlspecialchars($employer['company_name']); ?>'); return false;">View Document</a>
                                                <?php else: ?>
                                                    Not provided
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="employer-actions">
                                    <?php if($has_business_file): ?>
                                        <button type="button" class="btn btn-document" onclick="viewDocument('<?php echo htmlspecialchars($business_file_path); ?>', '<?php echo htmlspecialchars($employer['company_name']); ?>')">
                                            <i>📄</i> View Document
                                        </button>
                                    <?php endif; ?>
                                    
                                    <form id="reject-form-<?php echo $employer_id; ?>" method="post" style="display:inline;">
                                        <input type="hidden" name="employer_id" value="<?php echo $employer_id; ?>">
                                        <?php if($next_id > 0): ?>
                                            <input type="hidden" name="next_id" value="<?php echo $next_id; ?>">
                                        <?php endif; ?>
                                        <button type="submit" name="reject" class="btn btn-reject">Reject</button>
                                    </form>
                                    
                                    <form id="verify-form-<?php echo $employer_id; ?>" method="post" style="display:inline;">
                                        <input type="hidden" name="employer_id" value="<?php echo $employer_id; ?>">
                                        <?php if($next_id > 0): ?>
                                            <input type="hidden" name="next_id" value="<?php echo $next_id; ?>">
                                        <?php endif; ?>
                                        <button type="submit" name="verify" class="btn btn-verify">Verify Employer</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-employers">
                    <?php if(!empty($search) || $status != 'pending'): ?>
                        <h3>No Matching Employers</h3>
                        <p>No employers match your search criteria. <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php">Clear filters</a></p>
                    <?php else: ?>
                        <h3>No Pending Employers</h3>
                        <p>There are currently no employers waiting for verification.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Document Viewer Modal -->
    <div id="documentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Business Document</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="documentContainer">
                <!-- Document content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-navigate" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle employer card expand/collapse
        function toggleEmployerCard(employerId) {
            const card = document.querySelector(`.employer-card[data-employer-id="${employerId}"]`);
            const content = card.querySelector('.employer-content');
            const toggleIcon = card.querySelector('.toggle-icon');
            
            content.classList.toggle('open');
            toggleIcon.classList.toggle('rotated');
        }
        
        // Modal functionality
        const modal = document.getElementById("documentModal");
        const documentContainer = document.getElementById("documentContainer");
        const closeBtn = document.getElementsByClassName("close")[0];
        
        // Show document in modal
        function viewDocument(documentPath, companyName) {
    modal.style.display = "block";
    document.querySelector('.modal-title').innerText = companyName + ' - Business Document';
    
    // Properly construct the URL path based on document path structure
    if (!documentPath.startsWith('http')) {
        // Get base URL without trailing slash
        const baseUrl = '<?php echo rtrim(SITE_URL, '/'); ?>';
        
        // Make sure we don't get double slashes by standardizing the path
        if (documentPath.startsWith('/')) {
            documentPath = baseUrl + documentPath; // Path already has leading slash
        } else {
            documentPath = baseUrl + '/' + documentPath; // Add slash between baseUrl and path
        }
        
        // Add debug output to help diagnose issues
        console.log('Constructed document path:', documentPath);
    }
    
    // Check file extension
    const fileExt = documentPath.split('.').pop().toLowerCase();
    
    if(fileExt === 'pdf') {
        // PDF file
        documentContainer.innerHTML = `<iframe src="${documentPath}" width="100%" height="600px" style="border:none;"></iframe>`;
    } else if(fileExt === 'docx' || fileExt === 'doc') {
        // Word document - offer download link
        documentContainer.innerHTML = `
            <div style="text-align:center; padding: 30px;">
                <p>Word documents cannot be previewed directly. You can download the file using the link below.</p>
                <a href="${documentPath}" download class="btn btn-document" style="margin-top:20px;">
                    <i>📥</i> Download Document
                </a>
            </div>
        `;
    } else if(fileExt === 'jpg' || fileExt === 'jpeg' || fileExt === 'png' || fileExt === 'gif') {
        // Image file
        documentContainer.innerHTML = `<img src="${documentPath}" style="max-width:100%; max-height:600px; display:block; margin:0 auto;" alt="Business Document">`;
    } else {
        // Other file types
        documentContainer.innerHTML = `
            <div style="text-align:center; padding: 30px;">
                <p>This file type cannot be previewed. You can download the file using the link below.</p>
                <a href="${documentPath}" download class="btn btn-document" style="margin-top:20px;">
                    <i>📥</i> Download Document
                </a>
            </div>
        `;
    }
    
    // Add check for file existence with error handling
    fetch(documentPath, { method: 'HEAD' })
        .then(response => {
            if (!response.ok) {
                documentContainer.innerHTML += `
                    <div style="margin-top: 15px; padding: 10px; background-color: #f8d7da; color: #721c24; border-radius: 5px;">
                        <strong>Error:</strong> The file could not be found. Status code: ${response.status}. 
                        Path tried: ${documentPath}
                    </div>
                `;
            }
        })
        .catch(error => {
            // Show fetch errors (except CORS which are expected in some cases)
            if (!error.message.includes('CORS')) {
                documentContainer.innerHTML += `
                    <div style="margin-top: 15px; padding: 10px; background-color: #f8d7da; color: #721c24; border-radius: 5px;">
                        <strong>Error:</strong> ${error.message}
                    </div>
                `;
            }
        });
}
        
        // Close modal when clicking X
        closeBtn.onclick = function() {
            closeModal();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Function to close modal
        function closeModal() {
            modal.style.display = "none";
            documentContainer.innerHTML = '';
        }
        
        // Automatically expand the card if it's the current view (when loaded)
        document.addEventListener('DOMContentLoaded', function() {
            const currentId = <?php echo $current_employer_id ?: 0; ?>;
            if (currentId > 0) {
                const card = document.querySelector(`.employer-card[data-employer-id="${currentId}"]`);
                if (card) {
                    const content = card.querySelector('.employer-content');
                    const toggleIcon = card.querySelector('.toggle-icon');
                    content.classList.add('open');
                    toggleIcon.classList.add('rotated');
                    
                    // Scroll to the card
                    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
            
            // Confirm logout
            const logoutLink = document.querySelector('a[href*="logout.php"]');
            if (logoutLink) {
                logoutLink.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to logout?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>