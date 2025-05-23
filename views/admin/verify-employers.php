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
        
        try {
            // Start transaction
            $db->beginTransaction();
            
            // First, get the business file path and user_id to clean up
            $query_get_info = "SELECT e.business_file, e.user_id 
                              FROM employer_profiles e 
                              WHERE e.employer_id = :employer_id";
            $stmt_get_info = $db->prepare($query_get_info);
            $stmt_get_info->bindParam(':employer_id', $employer_id);
            $stmt_get_info->execute();
            $employer_info = $stmt_get_info->fetch(PDO::FETCH_ASSOC);
            
            if($employer_info) {
                $user_id = $employer_info['user_id'];
                $business_file = $employer_info['business_file'];
                
                // Delete the employer profile first (this will cascade delete related records)
                $query_delete_profile = "DELETE FROM employer_profiles WHERE employer_id = :employer_id";
                $stmt_delete_profile = $db->prepare($query_delete_profile);
                $stmt_delete_profile->bindParam(':employer_id', $employer_id);
                $stmt_delete_profile->execute();
                
                // Delete the user record (this ensures complete cleanup)
                $query_delete_user = "DELETE FROM users WHERE user_id = :user_id";
                $stmt_delete_user = $db->prepare($query_delete_user);
                $stmt_delete_user->bindParam(':user_id', $user_id);
                $stmt_delete_user->execute();
                
                // Delete the business file from filesystem if it exists
                if(!empty($business_file)) {
                    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha' . $business_file;
                    if(file_exists($file_path)) {
                        unlink($file_path);
                        error_log("Deleted business file: " . $file_path);
                    }
                }
                
                // Commit the transaction
                $db->commit();
                
                $_SESSION['message'] = "Employer application rejected and removed completely. They can register again if needed.";
                $_SESSION['message_type'] = "success";
            } else {
                $db->rollback();
                $_SESSION['message'] = "Employer not found.";
                $_SESSION['message_type'] = "error";
            }
            
        } catch (Exception $e) {
            // Rollback on error
            $db->rollback();
            $_SESSION['message'] = "Error rejecting employer: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            error_log("Error rejecting employer: " . $e->getMessage());
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

// Handle search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'pending';
$industry = isset($_GET['industry']) ? trim($_GET['industry']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'created_at';
$sort_order = isset($_GET['sort_order']) ? trim($_GET['sort_order']) : 'DESC';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get employer_id from GET parameter for individual view if provided
$current_employer_id = isset($_GET['employer_id']) ? (int)$_GET['employer_id'] : 0;

// Build the base query - ONLY include pending and active employers (rejected are deleted)
$base_query = "FROM employer_profiles e
               JOIN users u ON e.user_id = u.user_id
               WHERE u.role = 'employer'";

$conditions = [];
$params = [];

// Add status filter - since rejected employers are deleted, we only have pending and active
if($status === 'pending') {
    $conditions[] = "u.status = 'pending'";
} elseif($status === 'active') {
    $conditions[] = "u.status = 'active'";
}
// If status is 'all', we show both pending and active (no rejected since they're deleted)

// Add search condition if search is not empty
if(!empty($search)) {
    $conditions[] = "(e.company_name LIKE :search 
                   OR u.first_name LIKE :search 
                   OR u.last_name LIKE :search 
                   OR u.email LIKE :search 
                   OR e.industry LIKE :search 
                   OR e.location LIKE :search)";
    $params[':search'] = "%{$search}%";
}

// Add industry filter
if(!empty($industry)) {
    $conditions[] = "e.industry LIKE :industry";
    $params[':industry'] = "%{$industry}%";
}

// Add date range filter
if(!empty($date_from)) {
    $conditions[] = "DATE(u.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if(!empty($date_to)) {
    $conditions[] = "DATE(u.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

// Combine conditions
$where_clause = "";
if(!empty($conditions)) {
    $where_clause = " AND " . implode(" AND ", $conditions);
}

// Handle individual employer view
if($current_employer_id > 0) {
    $query = "SELECT e.*, u.user_id, u.first_name, u.last_name, u.email, u.phone, u.status, u.created_at
             FROM employer_profiles e
             JOIN users u ON e.user_id = u.user_id
             WHERE u.role = 'employer' AND e.employer_id = :employer_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':employer_id', $current_employer_id);
    $stmt->execute();
    $employers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total " . $base_query . $where_clause;
    $count_stmt = $db->prepare($count_query);
    foreach($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $per_page);

    // Get employers with pagination
    $valid_sort_columns = ['created_at', 'company_name', 'first_name', 'last_name', 'email', 'industry', 'location'];
    $sort_column = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'created_at';
    $sort_direction = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
    
    // Map sort column to actual table column
    $sort_mapping = [
        'created_at' => 'u.created_at',
        'company_name' => 'e.company_name',
        'first_name' => 'u.first_name',
        'last_name' => 'u.last_name',
        'email' => 'u.email',
        'industry' => 'e.industry',
        'location' => 'e.location'
    ];
    
    $order_by = " ORDER BY " . $sort_mapping[$sort_column] . " " . $sort_direction;
    
    $query = "SELECT e.*, u.user_id, u.first_name, u.last_name, u.email, u.phone, u.status, u.created_at " 
             . $base_query . $where_clause . $order_by . " LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    foreach($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $employers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get unique industries for filter dropdown
$industry_query = "SELECT DISTINCT industry FROM employer_profiles WHERE industry IS NOT NULL AND industry != '' ORDER BY industry";
$industry_stmt = $db->prepare($industry_query);
$industry_stmt->execute();
$industries = $industry_stmt->fetchAll(PDO::FETCH_COLUMN);

// Filter to just pending employers for navigation
$pending_employers = array_filter($employers, function($employer) {
    return $employer['status'] === 'pending';
});

// If viewing a specific employer, get previous and next employer IDs
$prev_id = $next_id = 0;
if($current_employer_id > 0 && count($pending_employers) > 0) {
    $query_all_ids = "SELECT e.employer_id
                     FROM employer_profiles e
                     JOIN users u ON e.user_id = u.user_id
                     WHERE u.status = 'pending' AND u.role = 'employer'
                     ORDER BY u.created_at DESC";
    $stmt_all_ids = $db->prepare($query_all_ids);
    $stmt_all_ids->execute();
    $all_ids = $stmt_all_ids->fetchAll(PDO::FETCH_COLUMN);
    
    $current_position = array_search($current_employer_id, $all_ids);
    
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

// Get total active count
$query_active = "SELECT COUNT(*) as count FROM users WHERE role = 'employer' AND status = 'active'";
$stmt_active = $db->prepare($query_active);
$stmt_active->execute();
$active_count = $stmt_active->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Enhanced Admin Dashboard Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid #1557b0;
        }
        
        .stat-card.pending {
            border-left-color: #ffc107;
        }
        
        .stat-card.active {
            border-left-color: #28a745;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #1557b0;
            margin-bottom: 5px;
        }
        
        .stat-number.pending {
            color: #ffc107;
        }
        
        .stat-number.active {
            color: #28a745;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Enhanced Filter Container */
        .filter-container {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .filter-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1a3b5d;
            margin: 0;
        }
        
        .filter-toggle {
            background: #1557b0;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .filter-toggle:hover {
            background: #0f4c8a;
        }
        
        .filter-content {
            transition: all 0.3s ease;
        }
        
        .filter-content.collapsed {
            display: none;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
            font-size: 0.95rem;
        }
        
        .filter-input, .filter-select {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .filter-input:focus, .filter-select:focus {
            border-color: #1557b0;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(21, 87, 176, 0.25);
        }
        
        .filter-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px 12px;
            padding-right: 40px;
        }
        
        .filter-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            align-items: center;
        }
        
        .filter-button {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-apply {
            background: linear-gradient(135deg, #1557b0 0%, #0f4c8a 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(21, 87, 176, 0.3);
        }
        
        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(21, 87, 176, 0.4);
        }
        
        .btn-reset {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
        
        /* Enhanced Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 25px 0;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .pagination-info {
            color: #6c757d;
            font-size: 0.95rem;
        }
        
        .pagination {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .pagination a, .pagination span {
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: #495057;
            border: 1px solid #dee2e6;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: #1557b0;
            color: white;
            border-color: #1557b0;
        }
        
        .pagination .current {
            background: #1557b0;
            color: white;
            border-color: #1557b0;
        }
        
        .pagination .disabled {
            color: #adb5bd;
            cursor: not-allowed;
        }
        
        /* Message Styles */
        .message {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            font-weight: 500;
        }
        
        .success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .error {
            background: linear-gradient(135deg, #f8d7da 0%, #f1b0b7 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        /* Enhanced Employer Cards */
        .employer-cards {
            margin-top: 20px;
        }
        
        .employer-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.4s ease;
            overflow: hidden;
        }
        
        .employer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 35px rgba(0,0,0,0.12);
        }
        
        .employer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #eef2f7;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .employer-header:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        }
        
        .employer-name {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .company-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #1557b0 0%, #0f4c8a 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.3rem;
            box-shadow: 0 3px 10px rgba(21, 87, 176, 0.3);
        }
        
        .company-info h3 {
            margin: 0;
            font-size: 1.4rem;
            color: #1a3b5d;
            font-weight: 600;
        }
        
        .company-info p {
            margin: 5px 0 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
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
            transition: all 0.4s ease;
        }
        
        .employer-content.open {
            padding: 25px;
            max-height: 1000px;
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
            font-weight: 600;
            margin-bottom: 8px;
            color: #4a5568;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-group span {
            font-size: 1rem;
            color: #2d3748;
            word-break: break-word;
            line-height: 1.5;
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
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-verify {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }
        
        .btn-document {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
        }
        
        .btn-document:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
        }
        
        .btn-zip {
            background: linear-gradient(135deg, #fd7e14 0%, #e8630c 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(253, 126, 20, 0.3);
        }
        
        .btn-zip:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(253, 126, 20, 0.4);
        }
        
        .btn-navigate {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-navigate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
        
        .btn-navigate.disabled {
            background: #adb5bd;
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
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
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
        
        /* Enhanced Quick Actions */
        .quick-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quick-action-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 2px solid #dee2e6;
            color: #6c757d;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .quick-action-view {
            color: #1557b0;
            border-color: #1557b0;
        }
        
        .quick-action-view:hover {
            background: #1557b0;
            color: white;
        }
        
        .quick-action-approve {
            color: #28a745;
            border-color: #28a745;
        }
        
        .quick-action-approve:hover {
            background: #28a745;
            color: white;
        }
        
        .quick-action-reject {
            color: #dc3545;
            border-color: #dc3545;
        }
        
        .quick-action-reject:hover {
            background: #dc3545;
            color: white;
        }
        
        .rotated {
            transform: rotate(180deg);
        }
        
        /* Enhanced Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 0;
            border: none;
            border-radius: 15px;
            width: 90%;
            max-width: 1000px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: white;
        }
        
        .modal-title {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 600;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.3s;
            line-height: 1;
        }
        
        .close:hover {
            opacity: 0.7;
        }
        
        .modal-body {
            padding: 25px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            background: #f8f9fa;
        }
        
        /* Document Viewer Styles */
        .document-viewer {
            text-align: center;
            padding: 20px;
        }
        
        .document-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .document-iframe {
            width: 100%;
            height: 600px;
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .document-image {
            max-width: 100%;
            max-height: 600px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .download-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .filter-row {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
            
            .employer-details {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h3 span {
                display: none;
            }
            
            .sidebar-menu a span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 15px;
            }
            
            .filter-row {
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
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .quick-actions {
                flex-direction: column;
                gap: 5px;
            }
        }
        
        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #1557b0;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="admin-container">
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
            
            <!-- Stats Cards -->
            <?php if($current_employer_id == 0): ?>
                <div class="stats-container">
                    <div class="stat-card pending">
                        <div class="stat-number pending"><?php echo $pending_count; ?></div>
                        <div class="stat-label">Pending Verification</div>
                    </div>
                    <div class="stat-card active">
                        <div class="stat-number active"><?php echo $active_count; ?></div>
                        <div class="stat-label">Verified Employers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo isset($total_records) ? $total_records : count($employers); ?></div>
                        <div class="stat-label">Total Results</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo isset($total_pages) ? $total_pages : 1; ?></div>
                        <div class="stat-label">Pages</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Enhanced Filter Container -->
            <div class="filter-container">
                <div class="filter-header">
                    <h3 class="filter-title">🔍 Advanced Filters</h3>
                    <button class="filter-toggle" onclick="toggleFilters()">Toggle Filters</button>
                </div>
                <div class="filter-content" id="filterContent">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" id="filterForm">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="search">🔎 Search</label>
                                <input type="text" id="search" name="search" class="filter-input" 
                                       placeholder="Company, person, email..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="status">📊 Status</label>
                                <select id="status" name="status" class="filter-select">
                                    <option value="pending" <?php if($status == 'pending') echo 'selected'; ?>>Pending Verification</option>
                                    <option value="active" <?php if($status == 'active') echo 'selected'; ?>>Verified/Active</option>
                                    <option value="all" <?php if($status == 'all') echo 'selected'; ?>>All Statuses</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="industry">🏭 Industry</label>
                                <select id="industry" name="industry" class="filter-select">
                                    <option value="">All Industries</option>
                                    <?php foreach($industries as $ind): ?>
                                        <option value="<?php echo htmlspecialchars($ind); ?>" 
                                                <?php if($industry == $ind) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($ind); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="date_from">📅 From Date</label>
                                <input type="date" id="date_from" name="date_from" class="filter-input" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_to">📅 To Date</label>
                                <input type="date" id="date_to" name="date_to" class="filter-input" 
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="sort_by">🔄 Sort By</label>
                                <select id="sort_by" name="sort_by" class="filter-select">
                                    <option value="created_at" <?php if($sort_by == 'created_at') echo 'selected'; ?>>Application Date</option>
                                    <option value="company_name" <?php if($sort_by == 'company_name') echo 'selected'; ?>>Company Name</option>
                                    <option value="first_name" <?php if($sort_by == 'first_name') echo 'selected'; ?>>First Name</option>
                                    <option value="last_name" <?php if($sort_by == 'last_name') echo 'selected'; ?>>Last Name</option>
                                    <option value="email" <?php if($sort_by == 'email') echo 'selected'; ?>>Email</option>
                                    <option value="industry" <?php if($sort_by == 'industry') echo 'selected'; ?>>Industry</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="sort_order">⬇️ Order</label>
                                <select id="sort_order" name="sort_order" class="filter-select">
                                    <option value="DESC" <?php if($sort_order == 'DESC') echo 'selected'; ?>>Newest First</option>
                                    <option value="ASC" <?php if($sort_order == 'ASC') echo 'selected'; ?>>Oldest First</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="filter-button btn-apply">
                                🔍 Apply Filters
                            </button>
                            <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php" class="filter-button btn-reset">
                                🔄 Reset All
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if(count($employers) > 0): ?>
                <!-- Navigation Controls for Individual View -->
                <?php if($current_employer_id > 0): ?>
                    <div class="navigation-controls">
                        <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php" class="btn btn-navigate">
                            ← Back to List
                        </a>
                        <div>
                            <?php if($prev_id > 0): ?>
                                <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php?employer_id=<?php echo $prev_id; ?>" class="btn btn-navigate">
                                    ← Previous
                                </a>
                            <?php else: ?>
                                <button class="btn btn-navigate disabled">
                                    ← Previous
                                </button>
                            <?php endif; ?>
                            
                            <?php if($next_id > 0): ?>
                                <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php?employer_id=<?php echo $next_id; ?>" class="btn btn-navigate">
                                    Next →
                                </a>
                            <?php else: ?>
                                <button class="btn btn-navigate disabled">
                                    Next →
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination (only show if not viewing individual employer) -->
                <?php if($current_employer_id == 0 && isset($total_pages) && $total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> employers
                        </div>
                        <div class="pagination">
                            <?php if($page > 1): ?>
                                <a href="?page=1&<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">First</a>
                                <a href="?page=<?php echo ($page-1); ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">Previous</a>
                            <?php else: ?>
                                <span class="disabled">First</span>
                                <span class="disabled">Previous</span>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <?php if($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page+1); ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">Next</a>
                                <a href="?page=<?php echo $total_pages; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">Last</a>
                            <?php else: ?>
                                <span class="disabled">Next</span>
                                <span class="disabled">Last</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="employer-cards">
                    <?php foreach($employers as $employer): ?>
                        <?php 
                            $employer_id = $employer['employer_id'];
                            $company_initial = strtoupper(substr($employer['company_name'], 0, 1));
                            $application_date = date('M d, Y', strtotime($employer['created_at']));
                            $is_expanded = ($current_employer_id > 0 && $current_employer_id == $employer_id);
                            $has_business_file = !empty($employer['business_file']);
                            $business_file_path = $has_business_file ? $employer['business_file'] : '';
                            
                            // Check file existence
                            $full_file_path = '';
                            $file_exists = false;
                            if($has_business_file) {
                                $full_file_path = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha' . $business_file_path;
                                $file_exists = file_exists($full_file_path);
                            }
                        ?>
                        <div class="employer-card" data-employer-id="<?php echo $employer_id; ?>">
                            <div class="employer-header" onclick="toggleEmployerCard(<?php echo $employer_id; ?>)">
                                <div class="employer-name">
                                    <div class="company-icon"><?php echo $company_initial; ?></div>
                                    <div class="company-info">
                                        <h3><?php echo htmlspecialchars($employer['company_name']); ?>
                                            <span class="status-badge status-<?php echo $employer['status']; ?>">
                                                <?php echo ucfirst($employer['status']); ?>
                                            </span>
                                        </h3>
                                        <p>Applied: <?php echo $application_date; ?> | 
                                           Contact: <?php echo htmlspecialchars($employer['first_name'] . ' ' . $employer['last_name']); ?></p>
                                    </div>
                                </div>
                                <div class="quick-actions">
                                    <?php if($has_business_file && $file_exists): ?>
                                        <button type="button" class="quick-action-btn quick-action-view" title="View Document" 
                                                onclick="viewDocument('<?php echo htmlspecialchars($business_file_path); ?>', '<?php echo htmlspecialchars($employer['company_name']); ?>', <?php echo $employer_id; ?>); event.stopPropagation();">
                                            📄
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="quick-action-btn" title="No Document Available" disabled>
                                            ❌
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if($employer['status'] == 'pending'): ?>
                                        <button type="button" class="quick-action-btn quick-action-reject" title="Reject & Delete" 
                                                onclick="if(confirm('Are you sure you want to PERMANENTLY DELETE this employer? They can register again later.')) { document.getElementById('reject-form-<?php echo $employer_id; ?>').submit(); }; event.stopPropagation();">
                                            🗑️
                                        </button>
                                        <button type="button" class="quick-action-btn quick-action-approve" title="Approve" 
                                                onclick="if(confirm('Are you sure you want to verify this employer?')) { document.getElementById('verify-form-<?php echo $employer_id; ?>').submit(); }; event.stopPropagation();">
                                            ✅
                                        </button>
                                    <?php endif; ?>
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
                                            <label>Email Address</label>
                                            <span><a href="mailto:<?php echo htmlspecialchars($employer['email']); ?>"><?php echo htmlspecialchars($employer['email']); ?></a></span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Phone Number</label>
                                            <span><?php echo htmlspecialchars($employer['phone'] ?? 'Not provided'); ?></span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Application Status</label>
                                            <span class="status-badge status-<?php echo $employer['status']; ?>">
                                                <?php echo ucfirst($employer['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="detail-group">
                                            <label>Industry</label>
                                            <span><?php echo htmlspecialchars($employer['industry'] ?? 'Not specified'); ?></span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Company Size</label>
                                            <span><?php echo htmlspecialchars($employer['company_size'] ?? 'Not specified'); ?></span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Location</label>
                                            <span><?php echo htmlspecialchars($employer['location'] ?? 'Not specified'); ?></span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Website</label>
                                            <span>
                                                <?php if(!empty($employer['website'])): ?>
                                                    <a href="<?php echo htmlspecialchars($employer['website']); ?>" target="_blank">
                                                        <?php echo htmlspecialchars($employer['website']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    Not provided
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="detail-group">
                                            <label>Business Document</label>
                                            <span>
                                                <?php if($has_business_file): ?>
                                                    📄 <?php echo htmlspecialchars(basename($business_file_path)); ?>
                                                    <br><small>Status: <?php echo $file_exists ? '✅ File exists' : '❌ File missing'; ?></small>
                                                    <?php if($file_exists): ?>
                                                        <br><small>Size: <?php echo number_format(filesize($full_file_path)); ?> bytes</small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    ❌ Not provided
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Company Description</label>
                                            <span><?php echo htmlspecialchars($employer['description'] ?? 'No description provided'); ?></span>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <label>Application Date</label>
                                            <span><?php echo date('F j, Y \a\t g:i A', strtotime($employer['created_at'])); ?></span>
                                        </div>
                                        
                                        <?php if($employer['verified'] && $employer['verified_at']): ?>
                                            <div class="detail-group">
                                                <label>Verified Date</label>
                                                <span><?php echo date('F j, Y \a\t g:i A', strtotime($employer['verified_at'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="employer-actions">
                                    <?php if($has_business_file && $file_exists): ?>
                                        <button type="button" class="btn btn-document" onclick="viewDocument('<?php echo htmlspecialchars($business_file_path); ?>', '<?php echo htmlspecialchars($employer['company_name']); ?>', <?php echo $employer_id; ?>)">
                                            👁️ View Document
                                        </button>
                                        <a href="<?php echo SITE_URL; ?>/views/admin/serve-document.php?file=<?php echo urlencode($business_file_path); ?>&action=download&employer_id=<?php echo $employer_id; ?>" class="btn btn-document">
                                            💾 Download
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/views/admin/serve-document.php?file=<?php echo urlencode($business_file_path); ?>&action=zip&employer_id=<?php echo $employer_id; ?>" class="btn btn-zip">
                                            📦 Download ZIP
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if($employer['status'] == 'pending'): ?>
                                        <form id="reject-form-<?php echo $employer_id; ?>" method="post" style="display:inline;">
                                            <input type="hidden" name="employer_id" value="<?php echo $employer_id; ?>">
                                            <?php if($next_id > 0): ?>
                                                <input type="hidden" name="next_id" value="<?php echo $next_id; ?>">
                                            <?php endif; ?>
                                            <button type="submit" name="reject" class="btn btn-reject" onclick="return confirm('⚠️ WARNING: This will PERMANENTLY DELETE this employer application and all associated data.\n\nThe employer will be able to register again with the same email if needed.\n\nAre you sure you want to continue?')">
                                                🗑️ Reject & Delete
                                            </button>
                                        </form>
                                        
                                        <form id="verify-form-<?php echo $employer_id; ?>" method="post" style="display:inline;">
                                            <input type="hidden" name="employer_id" value="<?php echo $employer_id; ?>">
                                            <?php if($next_id > 0): ?>
                                                <input type="hidden" name="next_id" value="<?php echo $next_id; ?>">
                                            <?php endif; ?>
                                            <button type="submit" name="verify" class="btn btn-verify" onclick="return confirm('Are you sure you want to verify this employer?')">
                                                ✅ Verify Employer
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Bottom Pagination -->
                <?php if($current_employer_id == 0 && isset($total_pages) && $total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </div>
                        <div class="pagination">
                            <?php if($page > 1): ?>
                                <a href="?page=1&<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">First</a>
                                <a href="?page=<?php echo ($page-1); ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">Previous</a>
                            <?php endif; ?>
                            
                            <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <?php if($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page+1); ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">Next</a>
                                <a href="?page=<?php echo $total_pages; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">Last</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-employers">
                    <?php if(!empty($search) || $status != 'pending' || !empty($industry) || !empty($date_from) || !empty($date_to)): ?>
                        <h3>🔍 No Matching Employers</h3>
                        <p>No employers match your search criteria. <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php">Clear all filters</a> to see all employers.</p>
                    <?php else: ?>
                        <h3>✅ All Caught Up!</h3>
                        <p>There are currently no employers waiting for verification. Great job!</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Enhanced Document Viewer Modal -->
    <div id="documentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Business Document</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="documentContainer">
                <div class="document-viewer">
                    <div class="loading"></div>
                    <p>Loading document...</p>
                </div>
            </div>
            <div class="modal-footer">
                <div>
                    <button class="btn btn-navigate" onclick="closeModal()">Close</button>
                </div>
                <div>
                    <a id="downloadLink" href="#" class="btn btn-document" style="display:none;">
                        💾 Download
                    </a>
                    <a id="zipLink" href="#" class="btn btn-zip" style="display:none;">
                        📦 Download ZIP
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle filter visibility
        function toggleFilters() {
            const content = document.getElementById('filterContent');
            content.classList.toggle('collapsed');
        }
        
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
        const downloadLink = document.getElementById("downloadLink");
        const zipLink = document.getElementById("zipLink");
        
        // Show document in modal with proper file serving
        function viewDocument(documentPath, companyName, employerId) {
            modal.style.display = "block";
            document.querySelector('.modal-title').innerText = companyName + ' - Business Document';
            
            // Show loading state
            documentContainer.innerHTML = '<div class="document-viewer"><div class="loading"></div><p>Loading document...</p></div>';
            
            // Use the secure file serving script
            const baseUrl = '<?php echo rtrim(SITE_URL, '/'); ?>';
            const viewUrl = baseUrl + '/views/admin/serve-document.php?file=' + encodeURIComponent(documentPath) + '&action=view&employer_id=' + employerId;
            const downloadUrl = baseUrl + '/views/admin/serve-document.php?file=' + encodeURIComponent(documentPath) + '&action=download&employer_id=' + employerId;
            const zipUrl = baseUrl + '/views/admin/serve-document.php?file=' + encodeURIComponent(documentPath) + '&action=zip&employer_id=' + employerId;
            
            console.log('Document path:', documentPath);
            console.log('View URL:', viewUrl);
            console.log('Download URL:', downloadUrl);
            console.log('ZIP URL:', zipUrl);
            
            // Set download links
            downloadLink.href = downloadUrl;
            downloadLink.style.display = 'inline-flex';
            zipLink.href = zipUrl;
            zipLink.style.display = 'inline-flex';
            
            // Check file extension
            const fileExt = documentPath.split('.').pop().toLowerCase();
            
            // Test if file exists first
            fetch(viewUrl, { method: 'HEAD' })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`File not found (${response.status}): ${response.statusText}`);
                    }
                    
                    // File exists, now display based on type
                    if(fileExt === 'pdf') {
                        documentContainer.innerHTML = `
                            <iframe src="${viewUrl}" class="document-iframe"></iframe>
                        `;
                    } else if(['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(fileExt)) {
                        documentContainer.innerHTML = `
                            <img src="${viewUrl}" class="document-image" alt="Business Document">
                        `;
                    } else if(['doc', 'docx'].includes(fileExt)) {
                        documentContainer.innerHTML = `
                            <div class="download-section">
                                <h4>📄 Microsoft Word Document</h4>
                                <p>Word documents cannot be previewed directly in the browser.</p>
                                <p><strong>File:</strong> ${documentPath.split('/').pop()}</p>
                                <p><strong>Company:</strong> ${companyName}</p>
                                <div style="margin-top: 20px;">
                                    <a href="${downloadUrl}" class="btn btn-document">
                                        💾 Download Document
                                    </a>
                                    <a href="${zipUrl}" class="btn btn-zip" style="margin-left: 10px;">
                                        📦 Download as ZIP
                                    </a>
                                </div>
                            </div>
                        `;
                    } else {
                        documentContainer.innerHTML = `
                            <div class="download-section">
                                <h4>📎 Document File</h4>
                                <p>This file type cannot be previewed directly in the browser.</p>
                                <p><strong>File:</strong> ${documentPath.split('/').pop()}</p>
                                <p><strong>Company:</strong> ${companyName}</p>
                                <div style="margin-top: 20px;">
                                    <a href="${downloadUrl}" class="btn btn-document">
                                        💾 Download Document
                                    </a>
                                    <a href="${zipUrl}" class="btn btn-zip" style="margin-left: 10px;">
                                        📦 Download as ZIP
                                    </a>
                                </div>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading document:', error);
                    documentContainer.innerHTML = `
                        <div class="document-error">
                            <h4>❌ Error Loading Document</h4>
                            <p><strong>Error:</strong> ${error.message}</p>
                            <p><strong>Original Path:</strong> ${documentPath}</p>
                            <p><strong>Attempted URL:</strong> ${viewUrl}</p>
                            <p>The document file may have been moved or deleted. Please contact the system administrator.</p>
                        </div>
                    `;
                    downloadLink.style.display = 'none';
                    zipLink.style.display = 'none';
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
            downloadLink.style.display = 'none';
            zipLink.style.display = 'none';
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-expand current employer if viewing individual
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
            
            // Add logout confirmation
            const logoutLink = document.querySelector('a[href*="logout.php"]');
            if (logoutLink) {
                logoutLink.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to logout?')) {
                        e.preventDefault();
                    }
                });
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modal
            if (e.key === 'Escape' && modal.style.display === 'block') {
                closeModal();
            }
            
            // Ctrl+F to focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search').focus();
            }
        });
    </script>
</body>
</html>