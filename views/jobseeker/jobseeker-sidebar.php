<?php
$current_page = basename($_SERVER['PHP_SELF']);
function is_active($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">SS</div>
        <h3>ShaSha</h3>
    </div>
    <ul class="sidebar-menu">
        <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/dashboard.php" class="<?php echo is_active('dashboard.php'); ?>"><i>📊</i> Dashboard</a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php" class="<?php echo is_active('profile.php'); ?>"><i>👤</i> My Profile</a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="<?php echo is_active('search-jobs.php'); ?>"><i>🔍</i> Search Jobs</a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php" class="<?php echo is_active('my-applications.php'); ?>"><i>📋</i> My Applications</a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php" class="<?php echo is_active('saved-jobs.php'); ?>"><i>💾</i> Saved Jobs</a></li>
        <!-- New Job Market Analytics Link -->
        <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/job-analytics.php" class="<?php echo is_active('job-analytics.php'); ?>"><i>📈</i> Job Market Analytics</a></li>
        <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php" id="logout-link"><i>🚪</i> Logout</a></li>
    </ul>
    <div class="sidebar-footer">
        <span>Logged in as <b><?php
            if (isset($jobseeker['first_name'])) {
                echo htmlspecialchars($jobseeker['first_name']);
            } elseif (isset($_SESSION['first_name'])) {
                echo htmlspecialchars($_SESSION['first_name']);
            } else {
                echo 'Jobseeker';
            }
        ?></b></span>
    </div>
</div>
<script>
// Logout confirmation
const logoutLink = document.getElementById('logout-link');
if (logoutLink) {
    logoutLink.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
}
</script>
<style>
/* Floating Chatbot Styles */
.chatbot-container {
    position: fixed;
    bottom: 32px;
    right: 32px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}
.chatbot-icon {
    background: #1a73e8;
    color: #fff;
    border-radius: 50%;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(26, 115, 232, 0.15);
    cursor: pointer;
    margin-bottom: 10px;
    transition: background 0.2s;
}
.chatbot-icon:hover {
    background: #1557b0;
}
.chatbot-box {
    display: none;
    flex-direction: column;
    width: 340px;
    max-width: 95vw;
    height: 420px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(26, 115, 232, 0.18);
    overflow: hidden;
    animation: fadeIn 0.2s;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
.chatbot-header {
    background: #1a73e8;
    color: #fff;
    padding: 16px;
    font-size: 1.1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
#close-chat {
    background: none;
    border: none;
    color: #fff;
    font-size: 1.5rem;
    cursor: pointer;
}
.chatbot-messages {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    background: #f7f9fa;
}
.message {
    margin-bottom: 12px;
    display: flex;
}
.bot-message .message-content {
    background: #e3f2fd;
    color: #1a3b5d;
    border-radius: 12px 12px 12px 0;
    padding: 10px 16px;
    font-size: 0.98rem;
    max-width: 80%;
}
.user-message .message-content {
    background: #1a73e8;
    color: #fff;
    border-radius: 12px 12px 0 12px;
    padding: 10px 16px;
    font-size: 0.98rem;
    margin-left: auto;
    max-width: 80%;
}
.chatbot-input {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-top: 1px solid #e4e7ec;
    background: #fff;
}
#user-input {
    flex: 1;
    border: none;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 1rem;
    background: #f7f9fa;
    margin-right: 8px;
    outline: none;
}
#send-message {
    background: #1a73e8;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s;
}
#send-message:hover {
    background: #1557b0;
}
</style>