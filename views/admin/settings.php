<?php
// Set page title
$page_title = 'Settings';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Process settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // Update site settings
    foreach ($_POST as $key => $value) {
        if ($key !== 'update_settings') {
            // Check if setting exists
            $check_query = "SELECT * FROM settings WHERE setting_name = :name";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':name', $key);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // Update existing setting
                $update_query = "UPDATE settings SET setting_value = :value WHERE setting_name = :name";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':value', $value);
                $update_stmt->bindParam(':name', $key);
                $update_stmt->execute();
            } else {
                // Insert new setting
                $insert_query = "INSERT INTO settings (setting_name, setting_value) VALUES (:name, :value)";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':name', $key);
                $insert_stmt->bindParam(':value', $value);
                $insert_stmt->execute();
            }
        }
    }
    
    $_SESSION['message'] = "Settings updated successfully.";
    $_SESSION['message_type'] = "success";
    
    // Redirect to refresh the page
    header("Location: " . SITE_URL . "/views/admin/settings.php");
    exit;
}

// Add new admin user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate input
    $errors = [];
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($first_name) || empty($last_name)) {
        $errors[] = "First name and last name are required.";
    }
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // Check if email already exists
    $check_query = "SELECT COUNT(*) as count FROM users WHERE email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    
    if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
        $errors[] = "Email address already in use.";
    }
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert user
            $user_query = "INSERT INTO users (email, password, role, first_name, last_name, status) 
                          VALUES (:email, :password, 'admin', :first_name, :last_name, 'active')";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':email', $email);
            $user_stmt->bindParam(':password', $hashed_password);
            $user_stmt->bindParam(':first_name', $first_name);
            $user_stmt->bindParam(':last_name', $last_name);
            $user_stmt->execute();
            
            $user_id = $db->lastInsertId();
            
            // Insert admin profile
            $admin_query = "INSERT INTO admin_profiles (user_id) VALUES (:user_id)";
            $admin_stmt = $db->prepare($admin_query);
            $admin_stmt->bindParam(':user_id', $user_id);
            $admin_stmt->execute();
            
            // Commit transaction
            $db->commit();
            
            $_SESSION['message'] = "Admin user added successfully.";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            // Rollback transaction
            $db->rollBack();
            
            $_SESSION['message'] = "Error adding admin user: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = "error";
    }
    
    // Redirect to refresh the page
    header("Location: " . SITE_URL . "/views/admin/settings.php");
    exit;
}

// Delete admin user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $admin_id = $_POST['admin_id'];
    
    // Prevent deleting the current logged-in admin
    if ($admin_id == $_SESSION['user_id']) {
        $_SESSION['message'] = "You cannot delete your own account.";
        $_SESSION['message_type'] = "error";
    } else {
        $delete_query = "DELETE FROM users WHERE user_id = :user_id AND role = 'admin'";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':user_id', $admin_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['message'] = "Admin user deleted successfully.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting admin user.";
            $_SESSION['message_type'] = "error";
        }
    }
    
    // Redirect to refresh the page
    header("Location: " . SITE_URL . "/views/admin/settings.php");
    exit;
}

// Get current settings
$query = "SELECT * FROM settings";
$stmt = $db->prepare($query);
$stmt->execute();
$settings_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative array
$settings = [];
foreach ($settings_rows as $row) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

// Default settings if not set
$default_settings = [
    'site_name' => 'ShaSha CJRS',
    'site_email' => 'info@shasha.co.zw',
    'site_phone' => '+263 242 123 456',
    'site_address' => 'Harare, Zimbabwe',
    'jobs_per_page' => '10',
    'enable_job_alerts' => '1',
    'enable_employer_verification' => '1',
    'maintenance_mode' => '0'
];

// Merge with defaults
$settings = array_merge($default_settings, $settings);

// Get all admin users
$admin_query = "SELECT u.user_id, u.email, u.first_name, u.last_name, u.created_at 
               FROM users u 
               WHERE u.role = 'admin' 
               ORDER BY u.created_at DESC";
$admin_stmt = $db->prepare($admin_query);
$admin_stmt->execute();
$admin_users = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Settings Page Styles */
        .settings-container {
            margin-bottom: 30px;
        }
        
        .settings-tabs {
            display: flex;
            background-color: white;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            border-bottom: 1px solid #eee;
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            font-weight: 500;
            color: #64748b;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            color: #1a3b5d;
            background-color: #f8f9fa;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: #1557b0;
        }
        
        .tab-content {
            background-color: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .settings-section {
            margin-bottom: 30px;
        }
        
        .settings-section h3 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #334155;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #64748b;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #1557b0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(21, 87, 176, 0.1);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
        }
        
        .checkbox-help {
            display: block;
            margin-top: 5px;
            color: #94a3b8;
            font-size: 0.85rem;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            box-shadow: 0 4px 12px rgba(21, 87, 176, 0.2);
            transform: translateY(-2px);
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        
        .success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .error {
            background-color: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }
        
        /* Admin Users Table Styles */
        .admin-users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .add-admin-btn {
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .add-admin-btn:hover {
            box-shadow: 0 4px 12px rgba(21, 87, 176, 0.2);
            transform: translateY(-2px);
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .admin-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #334155;
        }
        
        .admin-table tr:hover {
            background-color: #f9fafb;
        }
        
        .admin-table .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-delete {
            background-color: #fee2e2;
            color: #b91c1c;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .btn-delete:hover {
            background-color: #fecaca;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #334155;
            margin: 0;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
            line-height: 1;
        }
        
        .close-modal:hover {
            color: #334155;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .btn-cancel {
            background-color: #f1f5f9;
            color: #64748b;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background-color: #e2e8f0;
        }
        
        .no-admins {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
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
            
            <div class="settings-container">
                <div class="settings-tabs">
                    <div class="tab active" data-tab="general">General Settings</div>
                    <div class="tab" data-tab="jobs">Job Settings</div>
                    <div class="tab" data-tab="system">System Settings</div>
                    <div class="tab" data-tab="admin-users">Admin Users</div>
                </div>
                
                <div class="tab-content">
                    <!-- General Settings -->
                    <div class="tab-pane active" id="general">
                        <form method="post" action="">
                            <div class="settings-section">
                                <h3>Site Information</h3>
                                
                                <div class="form-group">
                                    <label for="site_name">Site Name</label>
                                    <input type="text" id="site_name" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="site_email">Contact Email</label>
                                        <input type="email" id="site_email" name="site_email" class="form-control" value="<?php echo htmlspecialchars($settings['site_email']); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="site_phone">Contact Phone</label>
                                        <input type="text" id="site_phone" name="site_phone" class="form-control" value="<?php echo htmlspecialchars($settings['site_phone']); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="site_address">Office Address</label>
                                    <input type="text" id="site_address" name="site_address" class="form-control" value="<?php echo htmlspecialchars($settings['site_address']); ?>">
                                </div>
                            </div>
                            
                            <button type="submit" name="update_settings" class="btn-save">Save Settings</button>
                        </form>
                    </div>
                    
                    <!-- Job Settings -->
                    <div class="tab-pane" id="jobs">
                        <form method="post" action="">
                            <div class="settings-section">
                                <h3>Job Listing Settings</h3>
                                
                                <div class="form-group">
                                    <label for="jobs_per_page">Jobs Per Page</label>
                                    <input type="number" id="jobs_per_page" name="jobs_per_page" class="form-control" min="5" max="50" value="<?php echo htmlspecialchars($settings['jobs_per_page']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="enable_job_alerts" name="enable_job_alerts" value="1" <?php echo $settings['enable_job_alerts'] ? 'checked' : ''; ?>>
                                        <label for="enable_job_alerts">Enable Job Alerts for Job Seekers</label>
                                    </div>
                                    <span class="checkbox-help">Job seekers will receive email notifications about new job postings that match their preferences.</span>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="enable_employer_verification" name="enable_employer_verification" value="1" <?php echo $settings['enable_employer_verification'] ? 'checked' : ''; ?>>
                                        <label for="enable_employer_verification">Require Employer Verification</label>
                                    </div>
                                    <span class="checkbox-help">New employer accounts will require admin verification before they can post jobs.</span>
                                </div>

                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="enable_auto_search" name="enable_auto_search" value="1" <?php echo isset($settings['enable_auto_search']) && $settings['enable_auto_search'] ? 'checked' : ''; ?>>
                                        <label for="enable_auto_search">Enable Auto-Search on Filter Selection</label>
                                    </div>
                                    <span class="checkbox-help">When enabled, search filters will apply automatically when dropdown options are selected, without needing to click the Apply button.</span>
                                </div>
                            </div>

                            <div class="settings-section">
                                <h3>Job Categories and Types</h3>
                                
                                <div class="form-group">
                                    <p>Manage the categories and types of jobs available in the system.</p>
                                    
                                    <div class="button-group" style="margin-top: 15px; display: flex; gap: 15px;">
                                        <a href="<?php echo SITE_URL; ?>/views/admin/manage-categories.php" class="btn-save" style="text-decoration: none; text-align: center;">
                                            Manage Job Categories
                                        </a>
                                        
                                        <a href="<?php echo SITE_URL; ?>/views/admin/manage-job-types.php" class="btn-save" style="text-decoration: none; text-align: center;">
                                            Manage Job Types
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_settings" class="btn-save">Save Settings</button>
                        </form>
                    </div>
                    
                    <!-- System Settings -->
                    <div class="tab-pane" id="system">
                        <form method="post" action="">
                            <div class="settings-section">
                                <h3>System Settings</h3>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                        <label for="maintenance_mode">Enable Maintenance Mode</label>
                                    </div>
                                    <span class="checkbox-help">When enabled, only administrators can access the site. Use this during updates or maintenance.</span>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_settings" class="btn-save">Save Settings</button>
                        </form>
                    </div>
                    
                    <!-- Admin Users -->
                    <div class="tab-pane" id="admin-users">
                        <div class="settings-section">
                            <div class="admin-users-header">
                                <h3>Manage Admin Users</h3>
                                <button class="add-admin-btn" id="openAddAdminModal">Add New Admin</button>
                            </div>
                            
                            <?php if(count($admin_users) > 0): ?>
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Created Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($admin_users as $admin): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                                <td>
                                                    <div class="actions">
                                                        <?php if($admin['user_id'] != $_SESSION['user_id']): ?>
                                                            <button class="btn-delete" onclick="confirmDeleteAdmin(<?php echo $admin['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($admin['first_name'] . ' ' . $admin['last_name'])); ?>')">Delete</button>
                                                        <?php else: ?>
                                                            <span style="color: #94a3b8; font-size: 0.85rem;">Current User</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="no-admins">
                                    <p>No admin users found. Click "Add New Admin" to create one.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add Admin Modal -->
            <div class="modal" id="addAdminModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Add New Admin User</h3>
                        <button class="close-modal" onclick="closeModal('addAdminModal')">&times;</button>
                    </div>
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="email">Email Address*</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name*</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Last Name*</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password*</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password*</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" onclick="closeModal('addAdminModal')">Cancel</button>
                            <button type="submit" name="add_admin" class="btn-save">Add Admin</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Delete Admin Form (Hidden) -->
            <form id="deleteAdminForm" method="post" action="" style="display: none;">
                <input type="hidden" name="admin_id" id="delete_admin_id">
                <input type="hidden" name="delete_admin" value="1">
            </form>
        </div>
    </div>
    
    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            // Check if there's an active tab in URL
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('active_tab');
            
            if (activeTab) {
                // Remove active class from all tabs and panes
                tabs.forEach(t => t.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));
                
                // Find the tab with matching data-tab attribute
                const targetTab = document.querySelector(`.tab[data-tab="${activeTab}"]`);
                if (targetTab) {
                    targetTab.classList.add('active');
                    document.getElementById(activeTab).classList.add('active');
                }
            }
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and panes
                    tabs.forEach(t => t.classList.remove('active'));
                    tabPanes.forEach(p => p.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding pane
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Open add admin modal
            document.getElementById('openAddAdminModal').addEventListener('click', function() {
                openModal('addAdminModal');
            });
        });
        
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        // Confirm delete admin
        function confirmDeleteAdmin(adminId, name) {
            if (confirm(`Are you sure you want to delete admin "${name}"?`)) {
                document.getElementById('delete_admin_id').value = adminId;
                document.getElementById('deleteAdminForm').submit();
            }
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });
    </script>
</body>
</html>