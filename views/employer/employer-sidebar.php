<?php
$current_page = basename($_SERVER['PHP_SELF']);
function is_active($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>
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
        transition: width 0.3s ease;
        position: relative;
        z-index: 100;
    }
    
    .sidebar.collapsed {
        width: 70px;
    }
    
    .sidebar.collapsed .sidebar-header h3,
    .sidebar.collapsed .sidebar-menu a span {
        display: none;
    }
    
    .sidebar.collapsed .sidebar-menu a {
        padding: 14px;
        justify-content: center;
    }
    
    .sidebar.collapsed .sidebar-menu a i {
        margin: 0;
    }
    
    .sidebar-toggle {
        position: absolute;
        top: 20px;
        right: -16px;
        width: 32px;
        height: 32px;
        background: #ffffff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        z-index: 101;
        border: none;
        color: #1a3b5d;
        font-size: 1.2rem;
        transition: all 0.3s ease;
    }
    
    .sidebar.collapsed .sidebar-toggle {
        transform: rotate(180deg);
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
        font-size: 1.2rem;
        font-weight: 600;
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
        transition: all 0.3s ease;
    }
    
    .sidebar-menu a:hover, 
    .sidebar-menu a.active {
        background: rgba(255,255,255,0.1);
        color: #fff;
        border-left-color: #ffd600;
    }
    
    .sidebar-menu a i {
        font-size: 1.2rem;
        width: 24px;
        text-align: center;
    }
</style>

<div class="sidebar">
    <button class="sidebar-toggle">❮</button>
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <?php 
            $initials = 'SE'; // Default initials for "ShaSha Employer"
            if (isset($employer) && isset($employer['first_name']) && isset($employer['last_name'])) {
                $initials = strtoupper(substr($employer['first_name'], 0, 1) . substr($employer['last_name'], 0, 1));
            }
            echo $initials;
            ?>
        </div>
        <h3>ShaSha</h3>
    </div>
    
    <ul class="sidebar-menu">
        <li><a href="<?php echo SITE_URL; ?>/views/employer/dashboard.php" class="<?php echo is_active('dashboard.php'); ?>"><i>📊</i><span>Dashboard</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/employer/profile.php" class="<?php echo is_active('profile.php'); ?>"><i>👤</i><span>Company Profile</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/employer/post-job.php" class="<?php echo is_active('post-job.php'); ?>"><i>📝</i><span>Post a Job</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php" class="<?php echo is_active('manage-jobs.php'); ?>"><i>💼</i><span>Manage Jobs</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/employer/applications.php" class="<?php echo is_active('applications.php'); ?>"><i>📋</i><span>Applications</span></a></li>
        <li><a href="#" onclick="openChatAssistant(event)" class="<?php echo is_active('chat-assistant.php'); ?>"><i>💬</i><span>Chat Assistant</span></a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i><span>Logout</span></a></li>
    </ul>
</div>

<!-- Include the chat assistant modal -->
<?php include 'chat-assistant-modal.php'; ?>

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

    // Add confirmation for logout
    const logoutLink = document.querySelector('a[href*="logout.php"]');
    if(logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            if(!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    }
});

// Chat Assistant Modal Functions
function openChatAssistant(event) {
    event.preventDefault();
    document.getElementById('chatAssistantModal').style.display = 'block';
    loadChatHistory(); // Load chat history when modal opens
}

// Close modal when clicking the close button
document.querySelector('.close-modal').addEventListener('click', function() {
    document.getElementById('chatAssistantModal').style.display = 'none';
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('chatAssistantModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});
</script> 