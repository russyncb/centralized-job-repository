<?php
// Set page title
$page_title = 'Post a Job';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has employer role
if(!has_role('employer')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get employer ID
$query = "SELECT employer_id, verified FROM employer_profiles WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $_SESSION['user_id']);
$stmt->execute();
$employer = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$employer) {
    redirect(SITE_URL . '/views/auth/logout.php', 'Employer profile not found.', 'error');
}

$employer_id = $employer['employer_id'];
$is_verified = $employer['verified'] == 1;

// Redirect if employer is not verified
if(!$is_verified) {
    redirect(SITE_URL . '/views/employer/dashboard.php', 'Your account needs to be verified before you can post jobs.', 'error');
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title']);
    $location = trim($_POST['location']);
    $job_type = $_POST['job_type'];
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    $responsibilities = trim($_POST['responsibilities']);
    $salary_min = !empty($_POST['salary_min']) ? $_POST['salary_min'] : null;
    $salary_max = !empty($_POST['salary_max']) ? $_POST['salary_max'] : null;
    $salary_currency = $_POST['salary_currency'];
    $application_deadline = !empty($_POST['application_deadline']) ? $_POST['application_deadline'] : null;
    $status = $_POST['status'];
    
    // Basic validation
    if(empty($title) || empty($location) || empty($job_type) || empty($category) || empty($description)) {
        $error = "Please fill in all required fields.";
    } else {
        // Insert job into database
        $query = "INSERT INTO jobs 
                 (employer_id, title, description, requirements, responsibilities, location, job_type, category, 
                 salary_min, salary_max, salary_currency, application_deadline, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $employer_id);
        $stmt->bindParam(2, $title);
        $stmt->bindParam(3, $description);
        $stmt->bindParam(4, $requirements);
        $stmt->bindParam(5, $responsibilities);
        $stmt->bindParam(6, $location);
        $stmt->bindParam(7, $job_type);
        $stmt->bindParam(8, $category);
        $stmt->bindParam(9, $salary_min);
        $stmt->bindParam(10, $salary_max);
        $stmt->bindParam(11, $salary_currency);
        $stmt->bindParam(12, $application_deadline);
        $stmt->bindParam(13, $status);
        
        if($stmt->execute()) {
            $job_id = $db->lastInsertId();
            if($status == 'active') {
                $success = "Job posted successfully! It is now live and visible to job seekers.";
            } else {
                $success = "Job saved as a draft. You can activate it later from the Manage Jobs section.";
            }
            
            // Clear form data
            $title = $location = $description = $requirements = $responsibilities = '';
            $job_type = 'full-time';
            $salary_min = $salary_max = null;
            $salary_currency = 'USD';
            $application_deadline = null;
            $status = 'active';
        } else {
            $error = "Error posting job. Please try again.";
        }
    }
}

// Get job categories
$query = "SELECT name FROM job_categories ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Post Job Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        
        .employer-container {
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
        
        .sidebar-footer {
            padding: 18px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 0.95rem;
            color: #bfc9d9;
            background: rgba(255,255,255,0.03);
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
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-name {
            font-size: 1.1rem;
            color: #333;
            font-weight: 500;
        }
        
        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .company-name {
            font-size: 1rem;
            color: #666;
        }
        
        .job-form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .job-form-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .job-form-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .job-form-content {
            padding: 20px;
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
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .required::after {
            content: '*';
            color: #dc3545;
            margin-left: 4px;
        }
        
        .hint {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn-submit {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-publish {
            background-color: #0056b3;
            color: white;
        }
        
        .btn-publish:hover {
            background-color: #004494;
        }
        
        .btn-draft {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-draft:hover {
            background-color: #5a6268;
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
        
        .section-title {
            font-size: 1.1rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="employer-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">SS</div>
                <h3>ShaSha Employer</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="<?php echo SITE_URL; ?>/views/employer/dashboard.php"><i>📊</i><span>Dashboard</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/profile.php"><i>👤</i><span>Company Profile</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/post-job.php" class="active"><i>📝</i><span>Post a Job</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/manage-jobs.php"><i>💼</i><span>Manage Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/employer/applications.php"><i>📋</i><span>Applications</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i><span>Logout</span></a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Post a Job</h1>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($employer['first_name'] . ' ' . $employer['last_name']); ?>
                    </div>
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($employer['company_name']); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if(isset($success)): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="job-form-container">
                <div class="job-form-header">
                    <h3>Job Details</h3>
                </div>
                
                <div class="job-form-content">
                    <form method="post" action="">
                        <h4 class="section-title">Basic Information</h4>
                        
                        <div class="form-group">
                            <label for="title" class="required">Job Title</label>
                            <input type="text" id="title" name="title" value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
                            <div class="hint">Be specific with the job title (e.g., "Senior Software Developer" instead of just "Developer")</div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="location" class="required">Location</label>
                                <input type="text" id="location" name="location" value="<?php echo isset($location) ? htmlspecialchars($location) : ''; ?>" required>
                                <div class="hint">City, state, or "Remote" if applicable</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="job_type" class="required">Job Type</label>
                                <select id="job_type" name="job_type" required>
                                    <option value="full-time" <?php if(isset($job_type) && $job_type == 'full-time') echo 'selected'; ?>>Full-time</option>
                                    <option value="part-time" <?php if(isset($job_type) && $job_type == 'part-time') echo 'selected'; ?>>Part-time</option>
                                    <option value="contract" <?php if(isset($job_type) && $job_type == 'contract') echo 'selected'; ?>>Contract</option>
                                    <option value="internship" <?php if(isset($job_type) && $job_type == 'internship') echo 'selected'; ?>>Internship</option>
                                    <option value="remote" <?php if(isset($job_type) && $job_type == 'remote') echo 'selected'; ?>>Remote</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="category" class="required">Job Category</label>
                            <select id="category" name="category" required>
                                <option value="">Select a category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php if(isset($category) && $category == $cat) echo 'selected'; ?>>
                                        <?php echo $cat; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <h4 class="section-title">Job Description</h4>
                        
                        <div class="form-group">
                            <label for="description" class="required">Description</label>
                            <textarea id="description" name="description" required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                            <div class="hint">Provide a detailed description of the job, including responsibilities and role overview</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="requirements">Requirements</label>
                            <textarea id="requirements" name="requirements"><?php echo isset($requirements) ? htmlspecialchars($requirements) : ''; ?></textarea>
                            <div class="hint">List qualifications, skills, education, and experience needed for this position</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="responsibilities">Responsibilities</label>
                            <textarea id="responsibilities" name="responsibilities"><?php echo isset($responsibilities) ? htmlspecialchars($responsibilities) : ''; ?></textarea>
                            <div class="hint">Detail the day-to-day duties and responsibilities of this role</div>
                        </div>
                        
                        <h4 class="section-title">Compensation & Deadline</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="salary_min">Minimum Salary</label>
                                <input type="number" id="salary_min" name="salary_min" value="<?php echo isset($salary_min) ? htmlspecialchars($salary_min) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="salary_max">Maximum Salary</label>
                                <input type="number" id="salary_max" name="salary_max" value="<?php echo isset($salary_max) ? htmlspecialchars($salary_max) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="salary_currency">Currency</label>
                                <select id="salary_currency" name="salary_currency">
                                    <option value="USD" <?php if(isset($salary_currency) && $salary_currency == 'USD') echo 'selected'; ?>>USD</option>
                                    <option value="ZWL" <?php if(isset($salary_currency) && $salary_currency == 'ZWL') echo 'selected'; ?>>ZWL</option>
                                    <option value="GBP" <?php if(isset($salary_currency) && $salary_currency == 'GBP') echo 'selected'; ?>>GBP</option>
                                    <option value="EUR" <?php if(isset($salary_currency) && $salary_currency == 'EUR') echo 'selected'; ?>>EUR</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="application_deadline">Application Deadline</label>
                            <input type="date" id="application_deadline" name="application_deadline" value="<?php echo isset($application_deadline) ? htmlspecialchars($application_deadline) : ''; ?>">
                            <div class="hint">Leave blank for no specific deadline</div>
                        </div>
                        
                        <h4 class="section-title">Job Status</h4>
                        
                        <div class="form-group">
                            <label for="status" class="required">Status</label>
                            <select id="status" name="status" required>
                                <option value="active" <?php if(isset($status) && $status == 'active') echo 'selected'; ?>>Active (Publish Now)</option>
                                <option value="draft" <?php if(isset($status) && $status == 'draft') echo 'selected'; ?>>Draft (Save for Later)</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="save_as_draft" class="btn-submit btn-draft" onclick="document.getElementById('status').value = 'draft';">Save as Draft</button>
                            <button type="submit" name="publish" class="btn-submit btn-publish" onclick="document.getElementById('status').value = 'active';">Publish Job</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>