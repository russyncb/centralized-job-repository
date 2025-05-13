<?php
// Set page title
$page_title = 'Manage Users';

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
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $action = $_POST['action'];
        $user_id = $_POST['user_id'];
        
        switch ($action) {
            case 'activate':
                $status = 'active';
                $message = "User activated successfully.";
                break;
            case 'suspend':
                $status = 'suspended';
                $message = "User suspended successfully.";
                break;
            case 'delete':
                // Delete user
                $query = "DELETE FROM users WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "User deleted successfully.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error deleting user.";
                    $_SESSION['message_type'] = "error";
                }
                
                header("Location: " . SITE_URL . "/views/admin/manage-users.php");
                exit;
                break;
            default:
                $status = 'active';
                $message = "User status updated.";
        }
        
        // Update user status
        $query = "UPDATE users SET status = :status WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating user status.";
            $_SESSION['message_type'] = "error";
        }
    }
    
    // Redirect to refresh the page
    header("Location: " . SITE_URL . "/views/admin/manage-users.php");
    exit;
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Prepare the query
$query = "SELECT u.user_id, u.email, u.role, u.first_name, u.last_name, u.phone, u.status, u.created_at 
         FROM users u
         WHERE 1=1";

// Add search condition if provided
if (!empty($search)) {
    $query .= " AND (u.email LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
}

// Add role filter if provided
if (!empty($role_filter)) {
    $query .= " AND u.role = :role";
}

// Add status filter if provided
if (!empty($status_filter)) {
    $query .= " AND u.status = :status";
}

// Order by creation date, newest first
$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);

// Bind search parameter if provided
if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $stmt->bindParam(':search', $search_param);
}

// Bind role filter if provided
if (!empty($role_filter)) {
    $stmt->bindParam(':role', $role_filter);
}

// Bind status filter if provided
if (!empty($status_filter)) {
    $stmt->bindParam(':status', $status_filter);
}

$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total users by role
$query_count = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$stmt_count = $db->prepare($query_count);
$stmt_count->execute();
$role_counts = $stmt_count->fetchAll(PDO::FETCH_ASSOC);

$role_totals = [
    'admin' => 0,
    'employer' => 0,
    'jobseeker' => 0
];

foreach ($role_counts as $count) {
    $role_totals[$count['role']] = $count['count'];
}

$total_users = array_sum($role_totals);
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
            min-height: 100vh;
            width: 100%;
            overflow-x: hidden;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
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
            padding: 20px 30px 30px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
            width: 100%;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            width: 100%;
        }
        
        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f5f7fa 100%);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 15px;
            flex: 1;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 5px 0;
            color: #333;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .filters {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            width: 100%;
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
        
        .users-table {
            width: 100%;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            box-sizing: border-box;
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
        
        .user-role {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.85rem;
            text-align: center;
        }
        
        .role-admin {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .role-employer {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .role-jobseeker {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .user-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .status-pending {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .status-suspended {
            background-color: #fbe9e7;
            color: #d32f2f;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
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
        
        .btn-suspend {
            background-color: #fbe9e7;
            color: #d32f2f;
        }
        
        .btn-delete {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .no-users {
            text-align: center;
            padding: 50px 20px;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 12px;
            width: 100%;
            box-sizing: border-box;
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
        <?php include 'admin-sidebar.php'; ?>
        
        <div class="admin-content">
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
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $role_totals['admin']; ?></div>
                    <div class="stat-label">Admins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $role_totals['employer']; ?></div>
                    <div class="stat-label">Employers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $role_totals['jobseeker']; ?></div>
                    <div class="stat-label">Job Seekers</div>
                </div>
            </div>
            
            <div class="filters">
                <form method="get" class="filter-form">
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Name or email" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <option value="">All Roles</option>
                            <option value="admin" <?php if($role_filter == 'admin') echo 'selected'; ?>>Admin</option>
                            <option value="employer" <?php if($role_filter == 'employer') echo 'selected'; ?>>Employer</option>
                            <option value="jobseeker" <?php if($role_filter == 'jobseeker') echo 'selected'; ?>>Job Seeker</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?php if($status_filter == 'active') echo 'selected'; ?>>Active</option>
                            <option value="pending" <?php if($status_filter == 'pending') echo 'selected'; ?>>Pending</option>
                            <option value="suspended" <?php if($status_filter == 'suspended') echo 'selected'; ?>>Suspended</option>
                            <option value="rejected" <?php if($status_filter == 'rejected') echo 'selected'; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-filter">Apply Filters</button>
                    <a href="<?php echo SITE_URL; ?>/views/admin/manage-users.php" class="btn-reset">Reset</a>
                </form>
            </div>
            
            <div class="users-table">
                <?php if(count($users) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="user-role role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="user-status status-<?php echo $user['status']; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <?php if($user['status'] != 'active'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn-action btn-activate">Activate</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if($user['status'] != 'suspended' && $user['role'] != 'admin'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <button type="submit" class="btn-action btn-suspend">Suspend</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if($user['user_id'] != $_SESSION['user_id'] && $user['role'] != 'admin'): ?>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn-action btn-delete">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-users">
                        <h3>No Users Found</h3>
                        <p>No users match your search criteria. Try adjusting your filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Check if auto-search is enabled in settings
            <?php
            // Check settings for auto-search
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT setting_value FROM settings WHERE setting_name = 'enable_auto_search'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $auto_search_enabled = !empty($result) && $result['setting_value'] == '1';
            ?>
            
            const autoSearchEnabled = <?php echo $auto_search_enabled ? 'true' : 'false'; ?>;
            
            if (autoSearchEnabled) {
                // Get form elements
                const filterForm = document.querySelector('.filter-form');
                const roleSelect = document.getElementById('role');
                const statusSelect = document.getElementById('status');
                const searchInput = document.getElementById('search');
                
                // Add change event listeners to select elements
                roleSelect.addEventListener('change', function() {
                    filterForm.submit();
                });
                
                statusSelect.addEventListener('change', function() {
                    filterForm.submit();
                });
                
                // For search input, submit after a short delay when typing stops
                let typingTimer;
                searchInput.addEventListener('input', function() {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(function() {
                        filterForm.submit();
                    }, 500); // 500ms delay after typing stops
                });
            }
        });
    </script>
</body>
</html>