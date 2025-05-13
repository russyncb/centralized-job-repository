<?php
// Set page title
$page_title = 'Apply for Job';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has jobseeker role
if(!has_role('jobseeker')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You must be logged in as a job seeker to apply for jobs.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get job ID from URL
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if(!$job_id) {
    redirect(SITE_URL . '/views/jobseeker/search-jobs.php', 'Invalid job ID.', 'error');
}

// Get job details
$query = "SELECT j.*, e.company_name, e.company_logo, e.employer_id 
          FROM jobs j
          JOIN employer_profiles e ON j.employer_id = e.employer_id
          WHERE j.job_id = ? AND j.status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $job_id);
$stmt->execute();
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$job) {
    redirect(SITE_URL . '/views/jobseeker/search-jobs.php', 'Job not found or no longer active.', 'error');
}

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
    redirect(SITE_URL . '/views/jobseeker/profile.php', 'Please complete your profile before applying for jobs.', 'error');
}

// Check if already applied
$query = "SELECT * FROM applications WHERE job_id = ? AND jobseeker_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $job_id);
$stmt->bindParam(2, $jobseeker['jobseeker_id']);
$stmt->execute();
if($stmt->fetch()) {
    redirect(SITE_URL . '/views/jobseeker/my-applications.php', 'You have already applied for this job.', 'info');
}

// Get existing documents
$docs_query = "SELECT * FROM applicant_documents 
               WHERE jobseeker_id = ? AND application_id IS NULL
               ORDER BY document_type, upload_date DESC";
$docs_stmt = $db->prepare($docs_query);
$docs_stmt->bindParam(1, $jobseeker['jobseeker_id']);
$docs_stmt->execute();
$documents = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group documents by type
$grouped_documents = [
    'cv' => [],
    'cover_letter' => [],
    'certificate' => [],
    'other' => []
];

foreach($documents as $doc) {
    $grouped_documents[$doc['document_type']][] = $doc;
}

// Define acceptable document types and size limits
$allowed_mime_types = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
$max_file_size = 5 * 1024 * 1024; // 5MB

// Process application submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize error array
    $errors = [];
    
    // Check CV selection or upload
    $selected_cv = isset($_POST['cv_document']) ? $_POST['cv_document'] : null;
    $has_new_cv_upload = isset($_FILES['new_cv']) && $_FILES['new_cv']['error'] === 0;
    $has_new_cv_title = !empty($_POST['new_cv_title']);
    
    if(!$selected_cv && !$has_new_cv_upload) {
        $errors[] = "Please select an existing CV or upload a new one.";
    }
    
    if($has_new_cv_upload) {
        if(!$has_new_cv_title) {
            $errors[] = "Please provide a title for your uploaded CV.";
        }
        
        // Validate CV file
        $cv_file = $_FILES['new_cv'];
        if(!in_array($cv_file['type'], $allowed_mime_types)) {
            $errors[] = "CV must be in PDF or Word format.";
        }
        if($cv_file['size'] > $max_file_size) {
            $errors[] = "CV size must be less than 5MB.";
        }
    }
    
    // Process other document uploads
    $doc_types = ['cover_letter', 'certificate', 'other'];
    foreach($doc_types as $type) {
        if(isset($_FILES["new_{$type}"]) && $_FILES["new_{$type}"]['error'] === 0) {
            $file = $_FILES["new_{$type}"];
            
            // Validate file
            if(!in_array($file['type'], $allowed_mime_types)) {
                $errors[] = ucfirst($type) . " must be in PDF or Word format.";
            }
            if($file['size'] > $max_file_size) {
                $errors[] = ucfirst($type) . " size must be less than 5MB.";
            }
            if(empty($_POST["new_{$type}_title"])) {
                $errors[] = "Please provide a title for your uploaded " . ucfirst($type) . ".";
            }
        }
    }
    
    // If no errors, proceed with application
    if(empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Create application record
            $app_query = "INSERT INTO applications (job_id, jobseeker_id, status, applied_at) 
                          VALUES (?, ?, 'pending', NOW())";
            $app_stmt = $db->prepare($app_query);
            $app_stmt->bindParam(1, $job_id);
            $app_stmt->bindParam(2, $jobseeker['jobseeker_id']);
            $app_stmt->execute();
            
            $application_id = $db->lastInsertId();
            
            // Process selected existing documents
            $document_types = ['cv', 'cover_letter', 'certificate', 'other'];
            $selected_docs = [];
            
            // Add selected CV
            if($selected_cv) {
                $selected_docs[] = $selected_cv;
            }
            
            // Add selected cover letter if any
            if(isset($_POST['cover_letter_document'])) {
                $selected_docs[] = $_POST['cover_letter_document'];
            }
            
            // Add selected certificates if any
            if(isset($_POST['certificate_documents']) && is_array($_POST['certificate_documents'])) {
                $selected_docs = array_merge($selected_docs, $_POST['certificate_documents']);
            }
            
            // Add selected other documents if any
            if(isset($_POST['other_documents']) && is_array($_POST['other_documents'])) {
                $selected_docs = array_merge($selected_docs, $_POST['other_documents']);
            }
            
            // Update selected documents with the application ID
            if(!empty($selected_docs)) {
                $placeholders = implode(',', array_fill(0, count($selected_docs), '?'));
                $update_docs_query = "UPDATE applicant_documents 
                                     SET application_id = ? 
                                     WHERE document_id IN ($placeholders)";
                $update_docs_stmt = $db->prepare($update_docs_query);
                $update_docs_stmt->bindParam(1, $application_id);
                
                foreach($selected_docs as $key => $doc_id) {
                    $update_docs_stmt->bindValue($key + 2, $doc_id);
                }
                
                $update_docs_stmt->execute();
            }
            
            // Process new document uploads
            foreach($document_types as $type) {
                if(isset($_FILES["new_{$type}"]) && $_FILES["new_{$type}"]['error'] === 0) {
                    $file = $_FILES["new_{$type}"];
                    $title = $_POST["new_{$type}_title"];
                    
                    // Create upload directory
                    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/uploads/documents/' . $jobseeker['jobseeker_id'] . '/';
                    if(!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $filename = $type . '_' . time() . '_' . preg_replace('/[^A-Za-z0-9\.\-]/', '_', $file['name']);
                    $destination = $upload_dir . $filename;
                    
                    if(move_uploaded_file($file['tmp_name'], $destination)) {
                        // Insert document record
                        $insert_doc_query = "INSERT INTO applicant_documents 
                                           (jobseeker_id, application_id, document_type, 
                                            file_path, original_filename, file_size, 
                                            mime_type, document_title, upload_date) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $insert_doc_stmt = $db->prepare($insert_doc_query);
                        $insert_doc_stmt->bindParam(1, $jobseeker['jobseeker_id']);
                        $insert_doc_stmt->bindParam(2, $application_id);
                        $insert_doc_stmt->bindParam(3, $type);
                        
                        $file_path = 'uploads/documents/' . $jobseeker['jobseeker_id'] . '/' . $filename;
                        $insert_doc_stmt->bindParam(4, $file_path);
                        $insert_doc_stmt->bindParam(5, $file['name']);
                        $insert_doc_stmt->bindParam(6, $file['size']);
                        $insert_doc_stmt->bindParam(7, $file['type']);
                        $insert_doc_stmt->bindParam(8, $title);
                        
                        $insert_doc_stmt->execute();
                    } else {
                        throw new Exception("Failed to upload file: " . $file['name']);
                    }
                }
            }
            
            // Create notification for the employer
            $notification_query = "INSERT INTO notifications (user_id, title, message, is_read, created_at) 
                                  SELECT user_id, 
                                         'New Job Application', 
                                         CONCAT('New application for ', ?, ' from ', ?, ' ', ?), 
                                         0, 
                                         NOW() 
                                  FROM employer_profiles 
                                  WHERE employer_id = ?";
            $notification_stmt = $db->prepare($notification_query);
            $notification_stmt->bindParam(1, $job['title']);
            $notification_stmt->bindParam(2, $jobseeker['first_name']);
            $notification_stmt->bindParam(3, $jobseeker['last_name']);
            $notification_stmt->bindParam(4, $job['employer_id']);
            $notification_stmt->execute();
            
            // Commit transaction
            $db->commit();
            
            // Redirect to success page
            redirect(SITE_URL . '/views/jobseeker/my-applications.php', 'Your application has been submitted successfully!', 'success');
            
        } catch(Exception $e) {
            // Roll back transaction on error
            $db->rollBack();
            $error_message = "An error occurred: " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Application Page Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .jobseeker-container {
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
        
        .job-header {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            background: #f8fafc;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: #1a3b5d;
        }
        
        .job-info h2 {
            margin: 0 0 5px;
            color: #1a3b5d;
            font-size: 1.5rem;
        }
        
        .company-name {
            color: #64748b;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        .job-meta {
            display: flex;
            gap: 20px;
            color: #64748b;
            font-size: 0.95rem;
        }
        
        .application-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .form-header {
            padding: 20px 25px;
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-header h3 {
            margin: 0;
            color: #1a3b5d;
            font-size: 1.2rem;
        }
        
        .form-content {
            padding: 25px;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 12px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .error {
            border-left: 4px solid #ef4444;
            color: #b91c1c;
        }
        
        .required-note {
            margin-bottom: 20px;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .required-note span {
            color: #ef4444;
        }

        /* Sidebar Modernization */
        .sidebar {
            position: relative;
            width: 250px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            color: #fff;
            padding: 0;
            box-shadow: 2px 0 8px rgba(0,0,0,0.07);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: width 0.3s ease;
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

        .sidebar-footer {
            padding: 18px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 0.95rem;
            color: #bfc9d9;
            background: rgba(255,255,255,0.03);
        }

        /* Sidebar Toggle Button */
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
            z-index: 1000;
            border: none;
            color: #1a3b5d;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar.collapsed .sidebar-toggle {
            transform: rotate(180deg);
        }

        /* Modern Submit Button */
        .btn-submit {
            background: linear-gradient(135deg, #1557b0 0%, #1a3b5d 100%);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(21, 87, 176, 0.3);
        }

        .btn-submit::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: -100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255,255,255,0.2),
                transparent
            );
            transition: 0.5s;
        }

        .btn-submit:hover::after {
            left: 100%;
        }

        /* Document Section Styles */
        .document-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
            color: #1a3b5d;
            margin-bottom: 15px;
        }

        .section-title .required {
            color: #ef4444;
        }

        .document-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }

        .document-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .document-item:hover {
            border-color: #1557b0;
            background: #f8fafc;
        }

        .document-info {
            flex: 1;
        }

        .document-title {
            font-weight: 500;
            color: #1a3b5d;
            margin-bottom: 4px;
        }

        .document-meta {
            font-size: 0.9rem;
            color: #64748b;
        }

        .upload-new {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #e5e7eb;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1a3b5d;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .form-group input[type="text"]:focus {
            border-color: #1557b0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(21, 87, 176, 0.1);
        }

        .form-group input[type="file"] {
            display: block;
            width: 100%;
            padding: 8px;
            margin-top: 8px;
            background: #f8fafc;
            border: 1px dashed #e5e7eb;
            border-radius: 6px;
            cursor: pointer;
        }

        .help-text {
            margin-top: 6px;
            font-size: 0.85rem;
            color: #64748b;
        }

        /* Radio and Checkbox Styles */
        input[type="radio"],
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
        }

        .btn-submit {
            margin-top: 20px;
            width: 100%;
            justify-content: center;
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
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php"><i>🔍</i><span>Search Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php"><i>💾</i><span>Saved Jobs</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/jobseeker/my-applications.php" class="active"><i>📝</i><span>My Applications</span></a></li>
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php"><i>🚪</i><span>Logout</span></a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Apply for Job</h1>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?></span>
                </div>
            </div>
            
            <div class="job-header">
                <?php if($job['company_logo']): ?>
                    <img src="<?php echo SITE_URL . '/' . $job['company_logo']; ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>" class="company-logo">
                <?php else: ?>
                    <div class="company-logo">
                        <?php echo strtoupper(substr($job['company_name'], 0, 2)); ?>
                    </div>
                <?php endif; ?>
                
                <div class="job-info">
                    <h2><?php echo htmlspecialchars($job['title']); ?></h2>
                    <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></div>
                    <div class="job-meta">
                        <span>📍 <?php echo htmlspecialchars($job['location']); ?></span>
                        <span>💼 <?php echo ucfirst($job['job_type']); ?></span>
                        <?php if(!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
                            <span>💰 
                                <?php 
                                    if(!empty($job['salary_min']) && !empty($job['salary_max'])) {
                                        echo $job['salary_currency'] . ' ' . number_format($job['salary_min']) . ' - ' . number_format($job['salary_max']);
                                    } elseif(!empty($job['salary_min'])) {
                                        echo $job['salary_currency'] . ' ' . number_format($job['salary_min']) . '+';
                                    } elseif(!empty($job['salary_max'])) {
                                        echo 'Up to ' . $job['salary_currency'] . ' ' . number_format($job['salary_max']);
                                    }
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if(isset($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="application-form">
                <div class="form-header">
                    <h3>Submit Application</h3>
                </div>
                
                <div class="form-content">
                    <div class="required-note">
                        Fields marked with <span>*</span> are required
                    </div>
                    
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $job_id; ?>" enctype="multipart/form-data">
                        <!-- CV Section -->
                        <div class="document-section">
                            <h4 class="section-title">
                                <span class="icon">📄</span> CV/Resume <span class="required">*</span>
                            </h4>
                            
                            <?php if(isset($grouped_documents['cv'])): ?>
                                <div class="document-list">
                                    <?php foreach($grouped_documents['cv'] as $doc): ?>
                                        <label class="document-item">
                                            <input type="radio" name="cv_document" value="<?php echo $doc['document_id']; ?>" required>
                                            <div class="document-info">
                                                <div class="document-title"><?php echo htmlspecialchars($doc['document_title']); ?></div>
                                                <div class="document-meta">
                                                    Uploaded <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="upload-new">
                                <div class="form-group">
                                    <label for="new_cv_title">Upload New CV</label>
                                    <input type="text" id="new_cv_title" name="new_cv_title" placeholder="Document Title">
                                </div>
                                <div class="form-group">
                                    <input type="file" id="new_cv" name="new_cv">
                                    <div class="help-text">Accepted formats: PDF, DOC, DOCX. Max size: 5MB</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cover Letter Section -->
                        <div class="document-section">
                            <h4 class="section-title">
                                <span class="icon">✉️</span> Cover Letter
                            </h4>
                            
                            <?php if(isset($grouped_documents['cover_letter'])): ?>
                                <div class="document-list">
                                    <?php foreach($grouped_documents['cover_letter'] as $doc): ?>
                                        <label class="document-item">
                                            <input type="radio" name="cover_letter_document" value="<?php echo $doc['document_id']; ?>">
                                            <div class="document-info">
                                                <div class="document-title"><?php echo htmlspecialchars($doc['document_title']); ?></div>
                                                <div class="document-meta">
                                                    Uploaded <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="upload-new">
                                <div class="form-group">
                                    <label for="new_cover_letter_title">Upload New Cover Letter</label>
                                    <input type="text" id="new_cover_letter_title" name="new_cover_letter_title" placeholder="Document Title">
                                </div>
                                <div class="form-group">
                                    <input type="file" id="new_cover_letter" name="new_cover_letter">
                                    <div class="help-text">Accepted formats: PDF, DOC, DOCX. Max size: 5MB</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Certificates Section -->
                        <div class="document-section">
                            <h4 class="section-title">
                                <span class="icon">🎓</span> Certificates
                            </h4>
                            
                            <?php if(isset($grouped_documents['certificate'])): ?>
                                <div class="document-list">
                                    <?php foreach($grouped_documents['certificate'] as $doc): ?>
                                        <label class="document-item">
                                            <input type="checkbox" name="certificate_documents[]" value="<?php echo $doc['document_id']; ?>">
                                            <div class="document-info">
                                                <div class="document-title"><?php echo htmlspecialchars($doc['document_title']); ?></div>
                                                <div class="document-meta">
                                                    Uploaded <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="upload-new">
                                <div class="form-group">
                                    <label for="new_certificate_title">Upload New Certificate</label>
                                    <input type="text" id="new_certificate_title" name="new_certificate_title" placeholder="Document Title">
                                </div>
                                <div class="form-group">
                                    <input type="file" id="new_certificate" name="new_certificate">
                                    <div class="help-text">Accepted formats: PDF, DOC, DOCX. Max size: 5MB</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Other Documents Section -->
                        <div class="document-section">
                            <h4 class="section-title">
                                <span class="icon">📎</span> Other Documents
                            </h4>
                            
                            <?php if(isset($grouped_documents['other'])): ?>
                                <div class="document-list">
                                    <?php foreach($grouped_documents['other'] as $doc): ?>
                                        <label class="document-item">
                                            <input type="checkbox" name="other_documents[]" value="<?php echo $doc['document_id']; ?>">
                                            <div class="document-info">
                                                <div class="document-title"><?php echo htmlspecialchars($doc['document_title']); ?></div>
                                                <div class="document-meta">
                                                    Uploaded <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="upload-new">
                                <div class="form-group">
                                    <label for="new_other_title">Upload New Document</label>
                                    <input type="text" id="new_other_title" name="new_other_title" placeholder="Document Title">
                                </div>
                                <div class="form-group">
                                    <input type="file" id="new_other" name="new_other">
                                    <div class="help-text">Accepted formats: PDF, DOC, DOCX. Max size: 5MB</div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <span class="icon">📤</span> Submit Application
                        </button>
                    </form>
                </div>
            </div>
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
            
            // CV validation and form helper
            const applicationForm = document.querySelector('form');
            const newCvTitle = document.getElementById('new_cv_title');
            const newCvFile = document.getElementById('new_cv');
            const existingCvRadios = document.querySelectorAll('input[name="cv_document"]');
            
            // Highlight the CV section as required
            const cvSection = document.querySelector('.document-section:first-of-type');
            if (cvSection) {
                cvSection.style.borderColor = '#ef4444';
                cvSection.style.borderWidth = '2px';
            }
            
            // FIXED FUNCTION: Improved validation logic
            function validateCVSelection() {
                // Check if any existing CV is selected
                let hasExistingCV = false;
                existingCvRadios.forEach(radio => {
                    if (radio.checked) {
                        hasExistingCV = true;
                    }
                });
                
                // Check if new CV is being uploaded - either both fields or neither should be filled
                let hasTitle = newCvTitle.value.trim() !== '';
                let hasFile = newCvFile.files.length > 0;
                
                // Valid if either:
                // 1. An existing CV is selected, OR
                // 2. Both new CV title and file are provided
                // 3. Neither new CV title nor file are provided (user is selecting an existing CV)
                let validNewCV = (hasTitle && hasFile) || (!hasTitle && !hasFile);
                
                // Return true if existing CV selected OR valid new CV combination
                return hasExistingCV || (validNewCV && hasExistingCV) || (hasTitle && hasFile);
            }
            
            // Add form validation
            applicationForm.addEventListener('submit', function(e) {
                // Prevent default form submission
                e.preventDefault();
                
                // Check if CV is selected/uploaded properly
                if (!validateCVSelection()) {
                    alert('Please either select an existing CV or complete both title and file fields when uploading a new CV.');
                    // Highlight the CV section and scroll to it
                    cvSection.style.borderColor = '#ef4444';
                    cvSection.scrollIntoView({ behavior: 'smooth' });
                } else {
                    // If validation passes, submit the form
                    this.submit();
                }
            });
            
            // Real-time validation feedback
            newCvTitle.addEventListener('input', updateCVValidation);
            newCvFile.addEventListener('change', updateCVValidation);
            existingCvRadios.forEach(radio => {
                radio.addEventListener('change', updateCVValidation);
            });
            
            function updateCVValidation() {
                if (validateCVSelection()) {
                    cvSection.style.borderColor = '#22c55e';
                } else {
                    cvSection.style.borderColor = '#ef4444';
                }
            }
            
            // Update validation initially
            updateCVValidation();
        });
    </script>
</body>
</html>