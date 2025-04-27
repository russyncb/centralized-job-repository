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
        
        .settings-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .settings-tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
        }
        
        .tab {
            padding: 15px 20px;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            position: relative;
        }
        
        .tab.active {
            color: #0056b3;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #0056b3;
        }
        
        .tab-content {
            padding: 25px;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        
        .form-group input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .settings-section {
            margin-bottom: 30px;
        }
        
        .settings-section h3 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .btn-save {
            background-color: #0056b3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-save:hover {
            background-color: #004494;
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
                <li><a href="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php"><i>💼</i> Jobs</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/admin/settings.php" class="active"><i>⚙️</i> Settings</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>System Settings</h1>
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
            
            <div class="settings-container">
                <div class="settings-tabs">
                    <div class="tab active" data-tab="general">General Settings</div>
                    <div class="tab" data-tab="jobs">Job Settings</div>
                    <div class="tab" data-tab="system">System Settings</div>
                </div>
                
                <form method="post" action="">
                    <div class="tab-content">
                        <!-- General Settings -->
                        <div class="tab-pane active" id="general">
                            <div class="settings-section">
                                <h3>Site Information</h3>
                                
                                <div class="form-group">
                                    <label for="site_name">Site Name</label>
                                    <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="site_email">Contact Email</label>
                                        <input type="email" id="site_email" name="site_email" value="<?php echo htmlspecialchars($settings['site_email']); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="site_phone">Contact Phone</label>
                                        <input type="text" id="site_phone" name="site_phone" value="<?php echo htmlspecialchars($settings['site_phone']); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="site_address">Office Address</label>
                                    <input type="text" id="site_address" name="site_address" value="<?php echo htmlspecialchars($settings['site_address']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Job Settings -->
                        <div class="tab-pane" id="jobs">
                            <div class="settings-section">
                                <h3>Job Listing Settings</h3>
                                
                                <div class="form-group">
                                    <label for="jobs_per_page">Jobs Per Page</label>
                                    <input type="number" id="jobs_per_page" name="jobs_per_page" min="5" max="50" value="<?php echo htmlspecialchars($settings['jobs_per_page']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="enable_job_alerts" value="1" <?php echo $settings['enable_job_alerts'] ? 'checked' : ''; ?>>
                                        Enable Job Alerts for Job Seekers
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="enable_employer_verification" value="1" <?php echo $settings['enable_employer_verification'] ? 'checked' : ''; ?>>
                                        Require Employer Verification
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Settings -->
                        <div class="tab-pane" id="system">
                            <div class="settings-section">
                                <h3>System Settings</h3>
                                
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="maintenance_mode" value="1" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                        Enable Maintenance Mode
                                    </label>
                                    <small style="display: block; margin-top: 5px; color: #666;">When enabled, only administrators can access the site.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="padding: 0 25px 25px;">
                        <button type="submit" name="update_settings" class="btn-save">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
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
        });
    </script>
</body>
</html>