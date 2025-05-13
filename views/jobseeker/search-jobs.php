<?php
// Set page title
$page_title = 'Search Jobs';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has jobseeker role
if(!has_role('jobseeker')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get jobseeker profile
$query = "SELECT jp.*, u.first_name, u.last_name, u.email, u.phone
          FROM jobseeker_profiles jp
          JOIN users u ON jp.user_id = u.user_id
          WHERE jp.user_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $_SESSION['user_id']);
$stmt->execute();
$jobseeker = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$jobseeker) {
    redirect(SITE_URL . '/views/auth/logout.php', 'Jobseeker profile not found.', 'error');
}

$jobseeker_id = $jobseeker['jobseeker_id'];

// Set up pagination
$jobs_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $jobs_per_page;

// Get search parameters
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$job_type = isset($_GET['job_type']) ? $_GET['job_type'] : '';

// Build search condition
$conditions = ["j.status = 'active'"];
$params = [];

// Add condition to exclude expired jobs
$conditions[] = "(j.application_deadline IS NULL OR j.application_deadline >= CURDATE())";

if(!empty($keyword)) {
    $conditions[] = "(j.title LIKE ? OR j.description LIKE ? OR e.company_name LIKE ?)";
    $keyword_param = '%' . $keyword . '%';
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $params[] = $keyword_param;
}

if(!empty($location)) {
    $conditions[] = "j.location LIKE ?";
    $params[] = '%' . $location . '%';
}

if(!empty($category)) {
    $conditions[] = "j.category = ?";
    $params[] = $category;
}

if(!empty($job_type)) {
    $conditions[] = "j.job_type = ?";
    $params[] = $job_type;
}

// Create WHERE clause
$where_clause = implode(' AND ', $conditions);

// Count total matching jobs for pagination
$count_query = "SELECT COUNT(*) as total 
                FROM jobs j
                JOIN employer_profiles e ON j.employer_id = e.employer_id
                WHERE $where_clause";

$count_stmt = $db->prepare($count_query);
for($i = 0; $i < count($params); $i++) {
    $count_stmt->bindValue($i + 1, $params[$i]);
}
$count_stmt->execute();
$total_jobs = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate total pages
$total_pages = ceil($total_jobs / $jobs_per_page);

// Get jobs with application and saved status
$query = "SELECT j.*, e.company_name, e.company_logo,
          (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id AND a.jobseeker_id = ?) as has_applied,
          (SELECT COUNT(*) FROM saved_jobs s WHERE s.job_id = j.job_id AND s.jobseeker_id = ?) as is_saved
          FROM jobs j
          JOIN employer_profiles e ON j.employer_id = e.employer_id
          WHERE $where_clause
          ORDER BY j.posted_at DESC 
          LIMIT $offset, $jobs_per_page";

$stmt = $db->prepare($query);

// Bind jobseeker_id as first two parameters
$stmt->bindValue(1, $jobseeker_id);
$stmt->bindValue(2, $jobseeker_id);

// Bind the rest of the search parameters
for($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 3, $params[$i]);
}

$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$category_query = "SELECT DISTINCT category FROM jobs WHERE status = 'active' ORDER BY category";
$category_stmt = $db->prepare($category_query);
$category_stmt->execute();
$categories = $category_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get job types for filter
$job_types = ['full-time', 'part-time', 'contract', 'internship', 'remote'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Search Jobs Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        
        .jobseeker-container {
            display: flex;
            min-height: 100vh;
        }
        
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
            position: fixed;
            top: 20px;
            left: 260px;
            width: 32px;
            height: 32px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
            border: none;
            color: #1a3b5d;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-toggle {
            left: 80px;
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
            padding: 25px 30px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            color: white;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-name {
            font-size: 1.1rem;
            color: #ffffff;
            font-weight: 500;
        }
        
        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .company-name {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.85);
        }

        .top-bar h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #ffffff;
            font-weight: 600;
        }
        
        .search-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        
        .search-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: #0056b3;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #004494;
            color: white;
            text-decoration: none;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #6c757d;
            color: #6c757d;
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
            text-decoration: none;
        }
        
        .search-results {
            margin-bottom: 30px;
        }
        
        .search-info {
            margin-bottom: 20px;
            color: #666;
        }
        
        .job-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .company-logo {
            width: 70px;
            height: 70px;
            background-color: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-right: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .company-logo img {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
        }
        
        .company-logo span {
            font-size: 1.8rem;
            font-weight: bold;
            color: #0056b3;
        }
        
        .job-details {
            flex: 1;
        }
        
        .job-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .job-title a {
            color: #0056b3;
            text-decoration: none;
        }
        
        .job-title a:hover {
            text-decoration: underline;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .job-meta span {
            display: flex;
            align-items: center;
        }
        
        .job-meta span i {
            margin-right: 5px;
        }
        
        .job-description {
            color: #555;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .job-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .job-tag {
            background-color: #f0f5ff;
            color: #0056b3;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .job-salary {
            color: #388e3c;
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .job-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .btn-applied {
            background-color: #e8f5e9;
            color: #388e3c;
            cursor: default;
        }
        
        .btn-saved {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 4px;
            color: #0056b3;
            text-decoration: none;
        }
        
        .pagination a {
            background-color: white;
            border: 1px solid #dee2e6;
        }
        
        .pagination a:hover {
            background-color: #f0f5ff;
        }
        
        .pagination span {
            background-color: #0056b3;
            color: white;
            border: 1px solid #0056b3;
        }
        
        .no-results {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .no-results h3 {
            margin-bottom: 10px;
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            text-align: center;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .deadline-info {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .deadline-today {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe3e3 100%);
            color: #e53e3e;
            border: 1px solid #fed7d7;
        }

        .deadline-tomorrow {
            background: linear-gradient(135deg, #fff8f1 0%, #ffedd5 100%);
            color: #dd6b20;
            border: 1px solid #fbd38d;
        }

        .deadline-week {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .deadline-normal {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .deadline-icon {
            display: inline-block;
            margin-right: 4px;
            font-size: 1rem;
        }
        
        @media (max-width: 768px) {
            .jobseeker-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .job-card {
                flex-direction: column;
            }
            
            .company-logo {
                margin-bottom: 15px;
            }
            
            .search-form {
                grid-template-columns: 1fr;
            }
        }

        /* Floating Chatbot */
        .floating-chat {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .floating-chat-button {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #1a5276 0%, #154360 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }

        .floating-chat-button:hover {
            transform: scale(1.1);
        }

        .floating-chat-button svg {
            width: 28px;
            height: 28px;
            color: white;
        }
    </style>
</head>
<body>
    <div class="jobseeker-container">
        <div class="sidebar">
            <button class="sidebar-toggle">❮</button>
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <?php echo strtoupper(substr($jobseeker['first_name'], 0, 1) . substr($jobseeker['last_name'], 0, 1)); ?>
                </div>
                <h3>ShaSha</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/dashboard.php"><i>📊</i><span>Dashboard</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php"><i>👤</i><span>My Profile</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="active"><i>🔍</i><span>Search Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php"><i>💾</i><span>Saved Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php"><i>📝</i><span>My Applications</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i><span>Logout</span></a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="top-bar">
                <h1>Search Jobs</h1>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?>
                    </div>
                </div>
            </div>
            
            <div class="search-container">
                <form method="get" action="" class="search-form">
                    <div class="form-group">
                        <label for="keyword">Keywords</label>
                        <input type="text" id="keyword" name="keyword" placeholder="Job title or skills" value="<?php echo htmlspecialchars($keyword); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" placeholder="City or Remote" value="<?php echo htmlspecialchars($location); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php if($category == $cat) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="job_type">Job Type</label>
                        <select id="job_type" name="job_type">
                            <option value="">All Types</option>
                            <?php foreach($job_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php if($job_type == $type) echo 'selected'; ?>>
                                    <?php echo ucfirst(htmlspecialchars($type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="search-buttons">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="btn btn-outline">Reset</a>
                    </div>
                </form>
            </div>
            
            <div class="search-results">
                <?php if(!empty($keyword) || !empty($location) || !empty($category) || !empty($job_type)): ?>
                    <div class="search-info">
                        Found <?php echo $total_jobs; ?> job<?php echo $total_jobs != 1 ? 's' : ''; ?> matching your search criteria
                    </div>
                <?php endif; ?>
                
                <?php if(count($jobs) > 0): ?>
                    <?php foreach($jobs as $job): ?>
                        <div class="job-card">
                            <div class="company-logo">
                                <?php if(!empty($job['company_logo'])): ?>
                                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?> Logo">
                                <?php else: ?>
                                    <span><?php echo htmlspecialchars(strtoupper(substr($job['company_name'], 0, 1))); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="job-details">
                                <div class="job-title">
                                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $job['job_id']; ?>">
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </a>
                                </div>
                                <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></div>
                                <div class="job-meta">
                                    <?php echo htmlspecialchars($job['location']); ?> • <?php echo ucfirst($job['job_type']); ?> • <?php echo htmlspecialchars($job['category']); ?>
                                    <?php if($job['application_deadline']): ?>
                                        <?php 
                                        $deadline = new DateTime($job['application_deadline']);
                                        $today = new DateTime();
                                        $tomorrow = new DateTime('tomorrow');
                                        $interval = $today->diff($deadline);
                                        
                                        // Determine deadline class and icon
                                        $deadlineClass = 'deadline-normal';
                                        $deadlineIcon = '📅';
                                        
                                        if ($today->format('Y-m-d') === $deadline->format('Y-m-d')) {
                                            $deadlineClass = 'deadline-today';
                                            $deadlineIcon = '⚡';
                                        } elseif ($tomorrow->format('Y-m-d') === $deadline->format('Y-m-d')) {
                                            $deadlineClass = 'deadline-tomorrow';
                                            $deadlineIcon = '⏰';
                                        } elseif ($interval->days <= 7) {
                                            $deadlineClass = 'deadline-week';
                                            $deadlineIcon = '📆';
                                        }
                                        ?>
                                        <span class="deadline-info <?php echo $deadlineClass; ?>">
                                            <span class="deadline-icon"><?php echo $deadlineIcon; ?></span>
                                            Deadline: 
                                            <?php
                                            if ($today->format('Y-m-d') === $deadline->format('Y-m-d')) {
                                                echo 'Today, 11:59 PM';
                                            } elseif ($tomorrow->format('Y-m-d') === $deadline->format('Y-m-d')) {
                                                echo 'Tomorrow, 11:59 PM';
                                            } else {
                                                echo date('M d, Y', strtotime($job['application_deadline']));
                                                if($interval->days <= 7) {
                                                    echo ' (' . $interval->days . ' days left)';
                                                }
                                            }
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="job-description">
                                    <?php 
                                    $description = strip_tags($job['description']);
                                    echo htmlspecialchars(substr($description, 0, 200)) . (strlen($description) > 200 ? '...' : ''); 
                                    ?>
                                </div>
                                
                                <?php if(!empty($job['skills_required'])): ?>
                                    <div class="job-tags">
                                        <?php 
                                            $skills = explode(',', $job['skills_required']);
                                            foreach($skills as $skill): 
                                                $skill = trim($skill);
                                                if(!empty($skill)):
                                        ?>
                                            <span class="job-tag"><?php echo htmlspecialchars($skill); ?></span>
                                        <?php 
                                                endif;
                                            endforeach; 
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($job['salary_range'])): ?>
                                    <div class="job-salary">
                                        <i>💰</i> <?php echo htmlspecialchars($job['salary_range']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="job-actions">
                                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                    
                                    <?php if($job['has_applied'] > 0): ?>
                                        <span class="btn btn-applied btn-sm">Applied</span>
                                    <?php else: ?>
                                        <a href="<?php echo SITE_URL; ?>/views/jobseeker/apply-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-outline btn-sm">Apply Now</a>
                                    <?php endif; ?>
                                    
                                    <?php if($job['is_saved'] > 0): ?>
                                        <a href="<?php echo SITE_URL; ?>/views/jobseeker/save-job.php?id=<?php echo $job['job_id']; ?>&action=remove&redirect=search" class="btn btn-saved btn-sm">Saved</a>
                                    <?php else: ?>
                                        <a href="<?php echo SITE_URL; ?>/views/jobseeker/save-job.php?id=<?php echo $job['job_id']; ?>&action=save&redirect=search" class="btn btn-outline btn-sm">Save Job</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if($total_pages > 1): ?>
                        <div class="pagination">
                            <?php 
                                // Build the query string for pagination links
                                $query_params = [];
                                if(!empty($keyword)) $query_params[] = "keyword=" . urlencode($keyword);
                                if(!empty($location)) $query_params[] = "location=" . urlencode($location);
                                if(!empty($category)) $query_params[] = "category=" . urlencode($category);
                                if(!empty($job_type)) $query_params[] = "job_type=" . urlencode($job_type);
                                $query_string = !empty($query_params) ? "&" . implode("&", $query_params) : "";
                            ?>
                            
                            <?php if($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1 . $query_string; ?>">Previous</a>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if($i == $current_page): ?>
                                    <span><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i . $query_string; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1 . $query_string; ?>">Next</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-results">
                        <h3>No jobs found</h3>
                        <p>Try adjusting your search criteria or check back later for new job postings.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="floating-chat">
        <div class="floating-chat-button" id="floating-chat-button">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
        </div>
    </div>
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

            // New code for immediate search on category and job type changes
            const categorySelect = document.getElementById('category');
            const jobTypeSelect = document.getElementById('job_type');
            const searchForm = document.querySelector('.search-form');
            const keywordInput = document.getElementById('keyword');
            const locationInput = document.getElementById('location');

            // Function to check if only category or job type has changed
            function isOnlyFilterChange(changedElement) {
                const keywordEmpty = !keywordInput.value.trim();
                const locationEmpty = !locationInput.value.trim();
                return keywordEmpty && locationEmpty && 
                       (changedElement === categorySelect || changedElement === jobTypeSelect);
            }

            // Handle category change
            categorySelect.addEventListener('change', function(e) {
                if (isOnlyFilterChange(categorySelect)) {
                    searchForm.submit();
                }
            });

            // Handle job type change
            jobTypeSelect.addEventListener('change', function(e) {
                if (isOnlyFilterChange(jobTypeSelect)) {
                    searchForm.submit();
                }
            });

            // Prevent form submission on enter key for keyword and location
            keywordInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });

            locationInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });

            // Floating chat button click handler
            const floatingChatButton = document.getElementById('floating-chat-button');
            if(floatingChatButton) {
                floatingChatButton.addEventListener('click', function() {
                    // Add your chat functionality here
                    console.log('Chat button clicked');
                });
            }
        });
    </script>
</body>
</html>