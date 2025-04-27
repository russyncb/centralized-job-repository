<?php
// Include bootstrap if not already included
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Redirect if not logged in (except for public pages)
$public_pages = [
    '/systems/claude/shasha/views/auth/login.php',
    '/systems/claude/shasha/views/auth/register.php',
    '/systems/claude/shasha/views/common/about.php',
    '/systems/claude/shasha/views/common/contact.php'
];

if(!is_logged_in() && !in_array($_SERVER['PHP_SELF'], $public_pages)) {
    redirect(SITE_URL . '/views/auth/login.php', 'Please login to access this page.', 'error');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <?php if(isset($extra_css)): ?>
        <?php foreach($extra_css as $css): ?>
            <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/<?php echo $css; ?>.css">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="logo">
                <a href="<?php echo SITE_URL; ?>">
                    <h1>ShaSha CJRS</h1>
                </a>
            </div>
            <nav class="main-nav">
                <ul>
                    <?php if(is_logged_in()): ?>
                        <?php if(has_role('admin')): ?>
                            <li><a href="<?php echo SITE_URL; ?>/views/admin/dashboard.php">Dashboard</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php">Verify Employers</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/admin/manage-users.php">Manage Users</a></li>
                        <?php elseif(has_role('employer')): ?>
                            <li><a href="<?php echo SITE_URL; ?>/views/employer/dashboard.php">Dashboard</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/employer/post-job.php">Post Job</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php">Manage Jobs</a></li>
                        <?php elseif(has_role('jobseeker')): ?>
                            <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/dashboard.php">Dashboard</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php">Find Jobs</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php">My Applications</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php">Login</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/views/auth/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="main-content">
        <div class="container">
            <?php display_message(); ?>