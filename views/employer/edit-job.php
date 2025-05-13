<?php
// Set page title
$page_title = 'Edit Job';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has employer role
if(!has_role('employer')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Check if job ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    redirect(SITE_URL . '/views/employer/manage-jobs.php', 'Job ID is required.', 'error');
}

$job_id = $_GET['id'];

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

// Get job details
$query = "SELECT * FROM jobs WHERE job_id = ? AND employer_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $job_id);
$stmt->bindParam(2, $employer_id);
$stmt->execute();

$job = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$job) {
    redirect(SITE_URL . '/views/employer/manage-jobs.php', 'Job not found or you do not have permission to edit it.', 'error');
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
        // Update job in database
        $query = "UPDATE jobs 
                 SET title = ?, description = ?, requirements = ?, responsibilities = ?, 
                 location = ?, job_type = ?, category = ?, salary_min = ?, salary_max = ?, 
                 salary_currency = ?, application_deadline = ?, status = ?, updated_at = NOW()
                 WHERE job_id = ? AND employer_id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $title);
        $stmt->bindParam(2, $description);
        $stmt->bindParam(3, $requirements);
        $stmt->bindParam(4, $responsibilities);
        $stmt->bindParam(5, $location);
        $stmt->bindParam(6, $job_type);
        $stmt->bindParam(7, $category);
        $stmt->bindParam(8, $salary_min);
        $stmt->bindParam(9, $salary_max);
        $stmt->bindParam(10, $salary_currency);
        $stmt->bindParam(11, $application_deadline);
        $stmt->bindParam(12, $status);
        $stmt->bindParam(13, $job_id);
        $stmt->bindParam(14, $employer_id);
        
        if($stmt->execute()) {
            $success = "Job updated successfully!";
            
            // Refresh job data
            $query = "SELECT * FROM jobs WHERE job_id = ? AND employer_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $job_id);
            $stmt->bindParam(2, $employer_id);
            $stmt->execute();
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Error updating job. Please try again.";
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
        /* Edit Job Styles */
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
        
        .back-link {
            color: #666;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .back-link:hover {
            color: #333;
            text-decoration: none;
        }
        
        .back-icon {
            margin-right: 5px;
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
        
        .btn-save {
            background-color: #0056b3;
            color: white;
        }
        
        .btn-save:hover {
            background-color: #004494;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
            color: white;
            text-decoration: none;
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
        <?php include 'employer-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <a href="<?php echo SITE_URL; ?>/views/employer/view-job.php?id=<?php echo $job_id; ?>" class="back-link">
                    <span class="back-icon">←</span> Back to Job
                </a>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if(isset($success)): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="job-form-container">
                <div class="job-form-header">
                    <h3>Edit Job: <?php echo htmlspecialchars($job['title']); ?></h3>
                </div>
                
                <div class="job-form-content">
                    <form method="post" action="">
                        <h4 class="section-title">Basic Information</h4>
                        
                        <div class="form-group">
                            <label for="title" class="required">Job Title</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
                            <div class="hint">Be specific with the job title (e.g., "Senior Software Developer" instead of just "Developer")</div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="location" class="required">Location</label>
                                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($job['location']); ?>" required>
                                <div class="hint">City, state, or "Remote" if applicable</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="job_type" class="required">Job Type</label>
                                <select id="job_type" name="job_type" required>
                                    <option value="full-time" <?php if($job['job_type'] == 'full-time') echo 'selected'; ?>>Full-time</option>
                                    <option value="part-time" <?php if($job['job_type'] == 'part-time') echo 'selected'; ?>>Part-time</option>
                                    <option value="contract" <?php if($job['job_type'] == 'contract') echo 'selected'; ?>>Contract</option>
                                    <option value="internship" <?php if($job['job_type'] == 'internship') echo 'selected'; ?>>Internship</option>
                                    <option value="remote" <?php if($job['job_type'] == 'remote') echo 'selected'; ?>>Remote</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="category" class="required">Job Category</label>
                            <select id="category" name="category" required>
                                <option value="">Select a category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php if($job['category'] == $cat) echo 'selected'; ?>>
                                        <?php echo $cat; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <h4 class="section-title">Job Description</h4>
                        
                        <div class="form-group">
                            <label for="description" class="required">Description</label>
                            <textarea id="description" name="description" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                            <div class="hint">Provide a detailed description of the job, including responsibilities and role overview</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="requirements">Requirements</label>
                            <textarea id="requirements" name="requirements"><?php echo htmlspecialchars($job['requirements']); ?></textarea>
                            <div class="hint">List qualifications, skills, education, and experience needed for this position</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="responsibilities">Responsibilities</label>
                            <textarea id="responsibilities" name="responsibilities"><?php echo htmlspecialchars($job['responsibilities']); ?></textarea>
                            <div class="hint">Detail the day-to-day duties and responsibilities of this role</div>
                        </div>
                        
                        <h4 class="section-title">Compensation & Deadline</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="salary_min">Minimum Salary</label>
                                <input type="number" id="salary_min" name="salary_min" value="<?php echo htmlspecialchars($job['salary_min']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="salary_max">Maximum Salary</label>
                                <input type="number" id="salary_max" name="salary_max" value="<?php echo htmlspecialchars($job['salary_max']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="salary_currency">Currency</label>
                                <select id="salary_currency" name="salary_currency">
                                    <option value="USD" <?php if($job['salary_currency'] == 'USD') echo 'selected'; ?>>USD</option>
                                    <option value="ZWL" <?php if($job['salary_currency'] == 'ZWL') echo 'selected'; ?>>ZWL</option>
                                    <option value="GBP" <?php if($job['salary_currency'] == 'GBP') echo 'selected'; ?>>GBP</option>
                                    <option value="EUR" <?php if($job['salary_currency'] == 'EUR') echo 'selected'; ?>>EUR</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="application_deadline">Application Deadline</label>
                            <input type="date" id="application_deadline" name="application_deadline" value="<?php echo htmlspecialchars($job['application_deadline']); ?>">
                            <div class="hint">Leave blank for no specific deadline</div>
                        </div>
                        
                        <h4 class="section-title">Job Status</h4>
                        
                        <div class="form-group">
                            <label for="status" class="required">Status</label>
                            <select id="status" name="status" required>
                                <option value="active" <?php if($job['status'] == 'active') echo 'selected'; ?>>Active</option>
                                <option value="closed" <?php if($job['status'] == 'closed') echo 'selected'; ?>>Closed</option>
                                <option value="draft" <?php if($job['status'] == 'draft') echo 'selected'; ?>>Draft</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <a href="<?php echo SITE_URL; ?>/views/employer/view-job.php?id=<?php echo $job_id; ?>" class="btn-submit btn-cancel">Cancel</a>
                            <button type="submit" class="btn-submit btn-save">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>