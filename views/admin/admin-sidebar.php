<?php
function is_active($page) {
    return strpos($_SERVER['PHP_SELF'], $page) !== false ? 'active' : '';
}

// Get pending employer count
function get_pending_employers_count() {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'employer' AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'];
}

// Get pending queries count
function get_pending_queries_count() {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) as count FROM admin_queries WHERE status = 'pending'";
    $stmt = $db->prepare($query);
    
    try {
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    } catch (PDOException $e) {
        // Table might not exist yet
        return 0;
    }
}

$pending_employers = get_pending_employers_count();
$pending_queries = get_pending_queries_count();
?>

<div class="sidebar" id="admin-sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="sidebar-logo">S</div>
            <h3 id="logo-text">ShaSha Admin</h3>
        </div>
        <button class="sidebar-toggle" id="sidebar-toggle">
            <span class="toggle-icon">≡</span>
        </button>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo SITE_URL; ?>/views/admin/dashboard.php" class="<?php echo is_active('dashboard.php'); ?>">
                <i class="menu-icon">📊</i>
                <span class="menu-text">Dashboard</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php" class="<?php echo is_active('verify-employers.php'); ?>">
                <i class="menu-icon">✓</i>
                <span class="menu-text">Verify Employers</span>
                <?php if($pending_employers > 0): ?>
                    <span class="badge"><?php echo $pending_employers; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/views/admin/manage-users.php" class="<?php echo is_active('manage-users.php'); ?>">
                <i class="menu-icon">👥</i>
                <span class="menu-text">Users</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/views/admin/manage-jobs.php" class="<?php echo is_active('manage-jobs.php'); ?>">
                <i class="menu-icon">💼</i>
                <span class="menu-text">Jobs</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/views/admin/queries.php" class="<?php echo is_active('queries.php'); ?>">
                <i class="menu-icon">💬</i>
                <span class="menu-text">Queries</span>
                <?php if($pending_queries > 0): ?>
                    <span class="badge"><?php echo $pending_queries; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/views/admin/analytics.php" class="<?php echo is_active('analytics.php'); ?>">
                <i class="menu-icon">📈</i>
                <span class="menu-text">Analytics</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/views/admin/settings.php" class="<?php echo is_active('settings.php'); ?>">
                <i class="menu-icon">⚙️</i>
                <span class="menu-text">Settings</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo SITE_URL; ?>/views/auth/logout.php" onclick="return confirm('Are you sure you want to logout?') || event.preventDefault();">
                <i class="menu-icon">🚪</i>
                <span class="menu-text">Logout</span>
            </a>
        </li>
    </ul>
</div>

<div class="header-bar">
    <div class="header-title">
        <h1><?php echo isset($page_title) ? $page_title : 'Admin Panel'; ?></h1>
    </div>
    <div class="user-info">
        Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
    </div>
</div>

<style>
    /* Admin Sidebar Styles */
    .sidebar {
        width: 270px;
        background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
        color: #fff;
        box-shadow: 2px 0 10px rgba(0,0,0,0.15);
        display: flex;
        flex-direction: column;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
        transition: all 0.3s ease;
    }
    
    .sidebar.collapsed {
        width: 80px;
    }
    
    .sidebar-header {
        padding: 25px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        height: 85px;
    }
    
    .logo-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .sidebar-logo {
        background: #fff;
        color: #1a3b5d;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: bold;
        flex-shrink: 0;
    }
    
    .sidebar-header h3 {
        color: #fff;
        font-size: 1.2rem;
        margin: 0;
        white-space: nowrap;
        transition: opacity 0.3s ease;
    }
    
    .sidebar.collapsed .sidebar-header h3 {
        opacity: 0;
        width: 0;
        display: none;
    }
    
    .sidebar-toggle {
        background: rgba(255,255,255,0.1);
        border: none;
        color: white;
        width: 35px;
        height: 35px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.5rem;
        transition: all 0.3s ease;
    }
    
    .sidebar-toggle:hover {
        background: rgba(255,255,255,0.2);
    }
    
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
        overflow-y: auto;
        flex: 1;
    }
    
    .sidebar-menu li {
        margin-bottom: 5px;
    }
    
    .sidebar-menu a {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 5px solid transparent;
        position: relative;
        overflow: hidden;
        gap: 12px;
    }
    
    .sidebar-menu a:before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 0;
        height: 100%;
        background: rgba(255,255,255,0.1);
        transition: all 0.3s ease;
        z-index: 0;
    }
    
    .sidebar-menu a:hover:before {
        width: 100%;
    }
    
    .sidebar-menu a.active {
        background: rgba(255,255,255,0.1);
        color: #fff;
        border-left-color: #FFC107;
    }
    
    .menu-icon {
        font-size: 1.4rem;
        min-width: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
    }
    
    .menu-text {
        font-size: 1rem;
        white-space: nowrap;
        opacity: 1;
        transition: opacity 0.2s ease;
        z-index: 1;
    }
    
    .badge {
        background: #f44336;
        color: white;
        font-size: 0.7rem;
        border-radius: 50%;
        padding: 0.25rem 0.5rem;
        margin-left: auto;
        z-index: 1;
    }
    
    .sidebar.collapsed .menu-text, 
    .sidebar.collapsed .badge {
        opacity: 0;
        width: 0;
        display: none;
    }
    
    /* Header Bar Styles */
    .header-bar {
        background: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        padding: 0 30px;
        height: 85px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: fixed;
        top: 0;
        left: 270px;
        right: 0;
        z-index: 900;
        transition: all 0.3s ease;
    }
    
    .sidebar.collapsed ~ .header-bar {
        left: 80px;
    }
    
    .header-title h1 {
        margin: 0;
        font-size: 1.5rem;
        color: #1a3b5d;
    }
    
    .user-info {
        color: #64748b;
        font-weight: 500;
    }
    
    /* Main Content Adjustment */
    .admin-content {
        margin-left: 270px;
        padding: 105px 30px 30px;
        transition: all 0.3s ease;
        min-height: 100vh;
        background-color: #f8f9fa;
    }
    
    .sidebar.collapsed ~ .admin-content {
        margin-left: 80px;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .sidebar {
            width: 80px;
        }
        
        .sidebar .menu-text,
        .sidebar .badge,
        .sidebar .sidebar-header h3 {
            opacity: 0;
            width: 0;
            display: none;
        }
        
        .admin-content {
            margin-left: 80px;
        }
        
        .header-bar {
            left: 80px;
        }
        
        .sidebar.expanded {
            width: 270px;
        }
        
        .sidebar.expanded .menu-text,
        .sidebar.expanded .badge,
        .sidebar.expanded .sidebar-header h3 {
            opacity: 1;
            width: auto;
            display: block;
        }
        
        .sidebar.expanded ~ .header-bar,
        .sidebar.expanded ~ .admin-content {
            left: 270px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle
    const sidebar = document.getElementById('admin-sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    
    // Check localStorage for sidebar state
    if(localStorage.getItem('adminSidebarCollapsed') === 'true') {
        sidebar.classList.add('collapsed');
    }
    
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        // Save state to localStorage
        localStorage.setItem('adminSidebarCollapsed', sidebar.classList.contains('collapsed'));
    });
    
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