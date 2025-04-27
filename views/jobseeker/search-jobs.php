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

// Get jobseeker ID
$query = "SELECT jobseeker_id FROM jobseeker_profiles WHERE user_id = ?";
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
        
        .company-name {
            color: #666;
            margin-bottom: 10px;
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
    </style>
</head>
<body>
    <div class="jobseeker-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>ShaSha Jobseeker</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/dashboard.php"><i>📊</i> Dashboard</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/profile.php"><i>👤</i> My Profile</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="active"><i>🔍</i> Search Jobs</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php"><i>📋</i> My Applications</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php"><i>💾</i> Saved Jobs</a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Search Jobs</h1>
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
                                    <span><i>📍</i> <?php echo htmlspecialchars($job['location']); ?></span>
                                    <span><i>💼</i> <?php echo ucfirst(htmlspecialchars($job['job_type'])); ?></span>
                                    <span><i>📅</i> Posted <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
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
</body>
</html>