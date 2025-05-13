<?php
function is_active($page) {
    return strpos($_SERVER['PHP_SELF'], $page) !== false ? 'active' : '';
}
?>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">S</div>
        <h3>ShaSha</h3>
    </div>
    
    <ul class="sidebar-menu">
        <li><a href="<?php echo SITE_URL; ?>/views/admin/dashboard.php" class="<?php echo is_active('dashboard.php'); ?>"><i>📊</i><span>Dashboard</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/admin/employers.php" class="<?php echo is_active('employers.php'); ?>"><i>🏢</i><span>Employers</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/admin/jobseekers.php" class="<?php echo is_active('jobseekers.php'); ?>"><i>👥</i><span>Jobseekers</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/admin/jobs.php" class="<?php echo is_active('jobs.php'); ?>"><i>💼</i><span>Jobs</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/admin/categories.php" class="<?php echo is_active('categories.php'); ?>"><i>📑</i><span>Categories</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/admin/queries.php" class="<?php echo is_active('queries.php'); ?>"><i>💬</i><span>Queries</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php" onclick="return confirm('Are you sure you want to logout?') || event.preventDefault();"><i>🚪</i><span>Logout</span></a></li>
    </ul>
</div>

<style>
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    
    // Check localStorage for sidebar state
    if(localStorage.getItem('sidebarCollapsed') === 'true') {
        sidebar.classList.add('collapsed');
    }
    
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        // Save state to localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    });
});
</script> 