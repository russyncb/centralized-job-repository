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
$query = "SELECT e.employer_id, e.verified, e.company_name, u.first_name, u.last_name 
          FROM employer_profiles e
          JOIN users u ON e.user_id = u.user_id
          WHERE e.user_id = ?";
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
        
        .main-content {
            flex: 1;
            padding: 30px;
            background: #f8fafc;
            overflow-y: auto;
            width: calc(100% - 250px);
            transition: all 0.3s ease;
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
        
        .top-bar h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #ffffff;
            font-weight: 600;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 12px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .success {
            border-left: 4px solid #22c55e;
            color: #15803d;
        }
        
        .error {
            border-left: 4px solid #ef4444;
            color: #b91c1c;
        }
        
        .job-form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .job-form-header {
            padding: 25px;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
        }
        
        .job-form-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #1a3b5d;
            font-weight: 600;
        }
        
        .job-form-content {
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1a3b5d;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            color: #1a3b5d;
            background: #f8fafc;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1557b0;
            box-shadow: 0 0 0 3px rgba(21, 87, 176, 0.1);
            background: white;
        }
        
        .form-group textarea {
            height: 150px;
            resize: vertical;
            line-height: 1.6;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .required::after {
            content: '*';
            color: #ef4444;
            margin-left: 4px;
        }
        
        .hint {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 6px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn-submit {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-publish {
            background: linear-gradient(135deg, #1557b0 0%, #1a3b5d 100%);
            color: white;
        }
        
        .btn-publish:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(21, 87, 176, 0.2);
        }
        
        .btn-draft {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
            color: white;
        }
        
        .btn-draft:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.2);
        }
        
        .section-title {
            font-size: 1.1rem;
            color: #1a3b5d;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
        }
        
        .verification-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-left: 15px;
        }
        
        .verification-badge.verified {
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            color: white;
        }
        
        .verification-badge.pending {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
        }
        
        .verification-badge .icon {
            margin-right: 6px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                width: 100%;
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-submit {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="employer-container">
        <?php include 'employer-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Post a Job</h1>
                <div class="user-info">
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($employer['company_name']); ?></span>
                        <?php if($is_verified): ?>
                            <span class="verification-badge verified">
                                <span class="icon">✓</span> Verified
                            </span>
                        <?php else: ?>
                            <span class="verification-badge pending">
                                <span class="icon">⌛</span> Pending Verification
                            </span>
                        <?php endif; ?>
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