<?php
// Set page title
$page_title = 'View Application';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has employer role
if(!has_role('employer')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get application ID
if(!isset($_GET['id'])) {
    redirect(SITE_URL . '/views/employer/applications.php', 'No application specified.', 'error');
}

$application_id = $_GET['id'];
$job_filter = isset($_GET['job_id']) ? $_GET['job_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

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

// Get application details
$query = "SELECT a.*, j.title as job_title, j.location as job_location, j.job_type,
         js.*, u.first_name, u.last_name, u.email, u.phone
         FROM applications a
         JOIN jobs j ON a.job_id = j.job_id
         JOIN jobseeker_profiles js ON a.jobseeker_id = js.jobseeker_id
         JOIN users u ON js.user_id = u.user_id
         WHERE a.application_id = ? AND j.employer_id = ?";

$stmt = $db->prepare($query);
$stmt->bindParam(1, $application_id);
$stmt->bindParam(2, $employer_id);
$stmt->execute();
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$application) {
    redirect(SITE_URL . '/views/employer/applications.php', 'Application not found or access denied.', 'error');
}

// Get applicant documents
$docs_query = "SELECT * FROM applicant_documents 
              WHERE application_id = ? 
              ORDER BY document_type";
$docs_stmt = $db->prepare($docs_query);
$docs_stmt->bindParam(1, $application_id);
$docs_stmt->execute();
$documents = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group documents by type
$grouped_documents = [];
foreach($documents as $doc) {
    $grouped_documents[$doc['document_type']][] = $doc;
}

// Get next and previous application IDs based on filters
$base_query = "SELECT a.application_id 
              FROM applications a
              JOIN jobs j ON a.job_id = j.job_id
              WHERE j.employer_id = ? ";

// Apply job filter if specified
if (!empty($job_filter)) {
    $base_query .= " AND j.job_id = ? ";
}

// Apply status filter if specified
if (!empty($status_filter)) {
    $base_query .= " AND a.status = ? ";
}

$base_query .= " ORDER BY a.applied_at DESC";

// Query for next and previous applications
$param_index = 1;
$nav_stmt = $db->prepare($base_query);
$nav_stmt->bindParam($param_index++, $employer_id);

if (!empty($job_filter)) {
    $nav_stmt->bindParam($param_index++, $job_filter);
}

if (!empty($status_filter)) {
    $nav_stmt->bindParam($param_index++, $status_filter);
}

$nav_stmt->execute();
$all_applications = $nav_stmt->fetchAll(PDO::FETCH_COLUMN);

// Find current position and get next/previous IDs
$current_position = array_search($application_id, $all_applications);
$prev_application_id = ($current_position > 0) ? $all_applications[$current_position - 1] : null;
$next_application_id = ($current_position < count($all_applications) - 1) ? $all_applications[$current_position + 1] : null;

// Build navigation URLs with filters
$navigation_params = [];
if (!empty($job_filter)) {
    $navigation_params[] = 'job_id=' . urlencode($job_filter);
}
if (!empty($status_filter)) {
    $navigation_params[] = 'status=' . urlencode($status_filter);
}
$nav_query_string = !empty($navigation_params) ? '&' . implode('&', $navigation_params) : '';

// Process application actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Handle document download action
    if ($action === 'download_all') {
        $zip_filename = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/uploads/temp/' . 
                      sanitize_filename($application['first_name'] . '_' . $application['last_name']) . '_documents.zip';
        
        // Create directory if it doesn't exist
        if (!is_dir($_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/uploads/temp/')) {
            mkdir($_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/uploads/temp/', 0755, true);
        }
        
        // Create new zip archive
        $zip = new ZipArchive();
        if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $has_files = false;
            
            foreach ($documents as $doc) {
                $file_path = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/' . $doc['file_path'];
                if (file_exists($file_path)) {
                    // Add file to zip with categorized folder structure
                    $folder = ucfirst($doc['document_type']) . 's';
                    $new_filename = sanitize_filename($doc['document_title'] ?: $doc['original_filename']);
                    $zip->addFile($file_path, $folder . '/' . $new_filename);
                    $has_files = true;
                }
            }
            
            $zip->close();
            
            if ($has_files) {
                // Set headers for download
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($zip_filename) . '"');
                header('Content-Length: ' . filesize($zip_filename));
                header('Pragma: no-cache');
                
                // Output the file
                readfile($zip_filename);
                
                // Delete temporary file
                unlink($zip_filename);
                exit;
            } else {
                $_SESSION['message'] = "No documents available to download.";
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Failed to create zip archive.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        switch($action) {
            case 'shortlist':
                $status = 'shortlisted';
                $message = "Application shortlisted successfully.";
                break;
            
            case 'reject':
                $status = 'rejected';
                $message = "Application rejected successfully.";
                break;
            
            case 'hire':
                $status = 'hired';
                $message = "Candidate marked as hired successfully.";
                break;
            
            case 'reset':
                $status = 'pending';
                $message = "Application reset to pending successfully.";
                break;
            
            default:
                $status = 'pending';
                $message = "Application status updated.";
        }
        
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Update application status
            $update_query = "UPDATE applications SET status = ?, notes = ? WHERE application_id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(1, $status);
            $update_stmt->bindParam(2, $notes);
            $update_stmt->bindParam(3, $application_id);
            $update_stmt->execute();
            
            // Log status change in application history
            $history_query = "INSERT INTO application_history 
                            (application_id, previous_status, new_status, notes, updated_by) 
                            VALUES (?, ?, ?, ?, ?)";
            
            // Try to insert if table exists, don't throw error if it doesn't
            try {
                $history_stmt = $db->prepare($history_query);
                $previous_status = $application['status'];
                $history_stmt->bindParam(1, $application_id);
                $history_stmt->bindParam(2, $previous_status);
                $history_stmt->bindParam(3, $status);
                $history_stmt->bindParam(4, $notes);
                $history_stmt->bindParam(5, $_SESSION['user_id']);
                $history_stmt->execute();
            } catch(PDOException $e) {
                // Silently handle if history table doesn't exist
            }
            
            $db->commit();
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = "success";
            
            // Refresh application data
            $stmt->execute();
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            $db->rollBack();
            $_SESSION['message'] = "Error: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
}

// Add sanitize_filename helper function before the HTML
function sanitize_filename($name) {
    // Replace spaces with underscores
    $filename = str_replace(' ', '_', $name);
    // Remove any non-alphanumeric characters except underscores and dots
    $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $filename);
    // Ensure filename isn't too long
    return substr($filename, 0, 100);
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
        /* View Application Styles */
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
        
        .application-header {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        .job-info {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .job-title {
            font-size: 1.5rem;
            color: #1a3b5d;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .job-meta {
            display: flex;
            gap: 20px;
            color: #64748b;
            font-size: 0.95rem;
        }
        
        .application-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 15px;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #38bdf8 0%, #0ea5e9 100%);
            color: white;
        }
        
        .status-shortlisted {
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            color: white;
        }
        
        .status-rejected {
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
            color: white;
        }
        
        .status-hired {
            background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
            color: white;
        }
        
        .applicant-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-group {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
        }
        
        .info-label {
            font-weight: 600;
            color: #1a3b5d;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #64748b;
        }
        
        .application-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .main-details {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        .section {
            padding: 25px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.1rem;
            color: #1a3b5d;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .experience-item, .education-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .experience-item:last-child, .education-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .skill-tag {
            background: #f1f5f9;
            color: #1a3b5d;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .side-panel {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        .actions-panel {
            padding: 25px;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-action {
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
        }
        
        .btn-shortlist {
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
        }
        
        .btn-hire {
            background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
        }
        
        .btn-reset {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .application-date {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #64748b;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .resume-section {
            padding: 25px;
            border-top: 1px solid #e5e7eb;
        }
        
        .resume-download {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            color: #1a3b5d;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .resume-download:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
        }
        
        .resume-icon {
            font-size: 1.5rem;
        }
        
        .cover-letter {
            white-space: pre-line;
            color: #64748b;
            line-height: 1.6;
        }
        
        /* Document Styles */
        .documents-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .document-category {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .document-category:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .category-title {
            font-size: 1.1rem;
            color: #1a3b5d;
            margin-bottom: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .category-icon {
            font-size: 1.4rem;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border-radius: 6px;
        }
        
        .document-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .document-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .document-link:hover {
            background: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-color: #1557b0;
            text-decoration: none;
        }
        
        .document-icon {
            font-size: 1.8rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            color: #1a3b5d;
            transition: all 0.3s ease;
        }
        
        .document-link:hover .document-icon {
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(0,0,0,0.08);
            color: #1557b0;
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
            font-size: 0.85rem;
            color: #64748b;
        }
        
        .no-documents-message {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            color: #64748b;
            font-style: italic;
            border: 1px dashed #e5e7eb;
        }
        
        /* Notes Field Styles */
        .action-form {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1a3b5d;
        }
        
        .notes-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        .notes-textarea:focus {
            outline: none;
            border-color: #1557b0;
            box-shadow: 0 0 0 3px rgba(21, 87, 176, 0.1);
        }
        
        /* Display application notes if they exist */
        .application-notes {
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #1557b0;
        }
        
        .notes-label {
            font-weight: 500;
            color: #1a3b5d;
            margin-bottom: 10px;
        }
        
        .notes-content {
            color: #64748b;
            white-space: pre-line;
            line-height: 1.6;
        }
        
        /* Navigation styles */
        .navigation-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-radius: 12px;
            padding: 18px 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            position: relative;
        }
        
        .nav-position {
            color: #64748b;
            font-weight: 500;
            background: #f8fafc;
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        .nav-buttons {
            display: flex;
            gap: 15px;
        }
        
        .nav-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 22px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .nav-btn::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: -100%;
            background: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,0.2), rgba(255,255,255,0));
            transition: 0.5s;
        }
        
        .nav-btn:hover::after {
            left: 100%;
        }
        
        .nav-back {
            color: #64748b;
            background: #f1f5f9;
            font-weight: 600;
        }
        
        .nav-back:hover {
            background: #e2e8f0;
            color: #1a3b5d;
            text-decoration: none;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .nav-prev, .nav-next {
            color: white;
            background: linear-gradient(135deg, #1557b0 0%, #1a3b5d 100%);
            font-weight: 600;
        }
        
        .nav-prev:hover, .nav-next:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 18px rgba(21, 87, 176, 0.25);
            text-decoration: none;
            color: white;
        }
        
        .nav-disabled {
            color: #94a3b8;
            background: #f1f5f9;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .shortcut-info {
            font-size: 0.9rem;
            color: #64748b;
            padding: 8px 15px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            position: absolute;
            right: 25px;
            bottom: -18px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            font-weight: 500;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            width: 100%;
            margin-bottom: 15px;
        }
        
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(29, 78, 216, 0.25);
        }
        
        @media (max-width: 768px) {
            .navigation-bar {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-buttons {
                width: 100%;
            }
            
            .nav-btn {
                flex: 1;
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
                <h1>View Application</h1>
                <div class="user-info">
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($employer['company_name']); ?></span>
                        <?php if($employer['verified']): ?>
                            <span class="verification-badge">
                                <span class="icon">✓</span> Verified
                            </span>
                        <?php else: ?>
                            <span class="verification-badge pending-verification">
                                <span class="icon">⌛</span> Pending Verification
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Application Navigation -->
            <div class="navigation-bar">
                <a href="<?php echo SITE_URL; ?>/views/employer/applications.php<?php echo !empty($job_filter) ? '?job_id=' . $job_filter : ''; ?><?php echo !empty($status_filter) ? (!empty($job_filter) ? '&' : '?') . 'status=' . $status_filter : ''; ?>" class="nav-btn nav-back">
                    <span class="icon">←</span> Back to List
                </a>
                
                <div class="nav-position">
                    Application <?php echo $current_position + 1; ?> of <?php echo count($all_applications); ?>
                </div>
                
                <div class="nav-buttons">
                    <?php if($prev_application_id): ?>
                        <a href="<?php echo SITE_URL; ?>/views/employer/view-application.php?id=<?php echo $prev_application_id . $nav_query_string; ?>" class="nav-btn nav-prev">
                            <span class="icon">←</span> Previous
                        </a>
                    <?php else: ?>
                        <span class="nav-btn nav-disabled">
                            <span class="icon">←</span> Previous
                        </span>
                    <?php endif; ?>
                    
                    <?php if($next_application_id): ?>
                        <a href="<?php echo SITE_URL; ?>/views/employer/view-application.php?id=<?php echo $next_application_id . $nav_query_string; ?>" class="nav-btn nav-next">
                            Next <span class="icon">→</span>
                        </a>
                    <?php else: ?>
                        <span class="nav-btn nav-disabled">
                            Next <span class="icon">→</span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if(isset($_SESSION['message'])): ?>
                <div class="message <?php echo $_SESSION['message_type']; ?>">
                    <?php 
                        echo $_SESSION['message']; 
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="application-header">
                <div class="job-info">
                    <div class="job-title"><?php echo htmlspecialchars($application['job_title']); ?></div>
                    <div class="job-meta">
                        <span><?php echo htmlspecialchars($application['job_location']); ?></span>
                        <span><?php echo ucfirst($application['job_type']); ?></span>
                    </div>
                </div>
                
                <div class="applicant-info">
                    <div class="info-group">
                        <div class="info-label">Applicant Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Contact Information</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($application['email']); ?><br>
                            <?php echo htmlspecialchars($application['phone']); ?>
                        </div>
                    </div>
                </div>
                
                <span class="application-status status-<?php echo $application['status']; ?>">
                    <?php echo ucfirst($application['status']); ?>
                </span>
                
                <?php if(!empty($application['notes'])): ?>
                <div class="application-notes">
                    <div class="notes-label">Decision Notes:</div>
                    <div class="notes-content"><?php echo nl2br(htmlspecialchars($application['notes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="application-content">
                <div class="main-details">
                    <?php if(!empty($application['experience_years'])): ?>
                        <div class="section">
                            <h3 class="section-title">Work Experience</h3>
                            <div class="experience-item">
                                <?php echo htmlspecialchars($application['experience_years']); ?> years of experience
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($application['education_level'])): ?>
                        <div class="section">
                            <h3 class="section-title">Education</h3>
                            <div class="education-item">
                                <?php echo htmlspecialchars($application['education_level']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($application['skills'])): ?>
                        <div class="section">
                            <h3 class="section-title">Skills</h3>
                            <div class="skills-list">
                                <?php foreach(explode(', ', $application['skills']) as $skill): ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Application Documents Section -->
                    <div class="section">
                        <h3 class="section-title">Application Documents</h3>
                        
                        <?php if(empty($grouped_documents)): ?>
                            <div class="no-documents-message">No documents attached to this application.</div>
                        <?php else: ?>
                            <div class="documents-container">
                                <!-- CV Documents -->
                                <?php if(!empty($grouped_documents['cv'])): ?>
                                    <div class="document-category">
                                        <h4 class="category-title"><span class="category-icon">📄</span> CV/Resume</h4>
                                        <div class="document-list">
                                            <?php foreach($grouped_documents['cv'] as $doc): ?>
                                                <a href="<?php echo SITE_URL . '/' . $doc['file_path']; ?>" class="document-link" target="_blank" download>
                                                    <div class="document-icon">📄</div>
                                                    <div class="document-info">
                                                        <div class="document-title"><?php echo htmlspecialchars($doc['document_title'] ?: $doc['original_filename']); ?></div>
                                                        <div class="document-meta">
                                                            <?php echo strtoupper(pathinfo($doc['original_filename'], PATHINFO_EXTENSION)); ?> 
                                                            • <?php echo round($doc['file_size']/1024); ?> KB
                                                            • Uploaded <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                                        </div>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Cover Letter Documents -->
                                <?php if(!empty($grouped_documents['cover_letter'])): ?>
                                    <div class="document-category">
                                        <h4 class="category-title"><span class="category-icon">✉️</span> Cover Letter</h4>
                                        <div class="document-list">
                                            <?php foreach($grouped_documents['cover_letter'] as $doc): ?>
                                                <a href="<?php echo SITE_URL . '/' . $doc['file_path']; ?>" class="document-link" target="_blank" download>
                                                    <div class="document-icon">✉️</div>
                                                    <div class="document-info">
                                                        <div class="document-title"><?php echo htmlspecialchars($doc['document_title'] ?: $doc['original_filename']); ?></div>
                                                        <div class="document-meta">
                                                            <?php echo strtoupper(pathinfo($doc['original_filename'], PATHINFO_EXTENSION)); ?> 
                                                            • <?php echo round($doc['file_size']/1024); ?> KB
                                                            • Uploaded <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                                        </div>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Certificate Documents -->
                                <?php if(!empty($grouped_documents['certificate'])): ?>
                                    <div class="document-category">
                                        <h4 class="category-title"><span class="category-icon">🎓</span> Certificates</h4>
                                        <div class="document-list">
                                            <?php foreach($grouped_documents['certificate'] as $doc): ?>
                                                <a href="<?php echo SITE_URL . '/' . $doc['file_path']; ?>" class="document-link" target="_blank" download>
                                                    <div class="document-icon">🎓</div>
                                                    <div class="document-info">
                                                        <div class="document-title"><?php echo htmlspecialchars($doc['document_title'] ?: $doc['original_filename']); ?></div>
                                                        <div class="document-meta">
                                                            <?php echo strtoupper(pathinfo($doc['original_filename'], PATHINFO_EXTENSION)); ?> 
                                                            • <?php echo round($doc['file_size']/1024); ?> KB
                                                            • Uploaded <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                                        </div>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Other Documents -->
                                <?php if(!empty($grouped_documents['other'])): ?>
                                    <div class="document-category">
                                        <h4 class="category-title"><span class="category-icon">📎</span> Other Documents</h4>
                                        <div class="document-list">
                                            <?php foreach($grouped_documents['other'] as $doc): ?>
                                                <a href="<?php echo SITE_URL . '/' . $doc['file_path']; ?>" class="document-link" target="_blank" download>
                                                    <div class="document-icon">📎</div>
                                                    <div class="document-info">
                                                        <div class="document-title"><?php echo htmlspecialchars($doc['document_title'] ?: $doc['original_filename']); ?></div>
                                                        <div class="document-meta">
                                                            <?php echo strtoupper(pathinfo($doc['original_filename'], PATHINFO_EXTENSION)); ?> 
                                                            • <?php echo round($doc['file_size']/1024); ?> KB
                                                            • Uploaded <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                                        </div>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="side-panel">
                    <div class="actions-panel">
                        <h3 class="section-title">Actions</h3>
                        
                        <?php if (!empty($documents)): ?>
                        <form method="post" style="margin-bottom: 20px;">
                            <input type="hidden" name="action" value="download_all">
                            <button type="submit" class="btn-action btn-download">
                                <span class="icon">📦</span> Download All Documents
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <div class="action-form">
                            <div class="form-group">
                                <label for="action-notes">Decision Notes</label>
                                <textarea id="action-notes" name="notes" placeholder="Add notes about your decision (optional)..." rows="4" class="notes-textarea"></textarea>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <?php if($application['status'] != 'shortlisted' && $application['status'] != 'hired'): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="shortlist">
                                    <input type="hidden" name="notes" class="notes-hidden">
                                    <button type="submit" class="btn-action btn-shortlist action-btn">
                                        <span class="icon">👍</span> Shortlist Candidate
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if($application['status'] != 'hired'): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="hire">
                                    <input type="hidden" name="notes" class="notes-hidden">
                                    <button type="submit" class="btn-action btn-hire action-btn">
                                        <span class="icon">🎉</span> Hire Candidate
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if($application['status'] != 'rejected'): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="notes" class="notes-hidden">
                                    <button type="submit" class="btn-action btn-reject action-btn">
                                        <span class="icon">❌</span> Reject Application
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if($application['status'] != 'pending'): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="reset">
                                    <input type="hidden" name="notes" class="notes-hidden">
                                    <button type="submit" class="btn-action btn-reset action-btn">
                                        <span class="icon">🔄</span> Reset Status
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <div class="application-date">
                            Applied on <?php echo date('F d, Y', strtotime($application['applied_at'])); ?>
                        </div>
                    </div>
                    
                    <?php if(!empty($application['resume_path'])): ?>
                        <div class="resume-section">
                            <h3 class="section-title">Resume</h3>
                            <a href="<?php echo SITE_URL . '/' . $application['resume_path']; ?>" class="resume-download" target="_blank">
                                <span class="resume-icon">📄</span>
                                <span>Download Resume</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sync notes across all action buttons
        const notesTextarea = document.querySelector('.notes-textarea');
        const hiddenNotesFields = document.querySelectorAll('.notes-hidden');
        const actionButtons = document.querySelectorAll('.action-btn');
        
        if(notesTextarea) {
            notesTextarea.addEventListener('input', function() {
                const notes = this.value;
                hiddenNotesFields.forEach(field => {
                    field.value = notes;
                });
            });
            
            // Add confirmation for actions with required notes
            actionButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const form = this.closest('form');
                    const action = form.querySelector('input[name="action"]').value;
                    
                    if(action === 'reject' && !notesTextarea.value.trim()) {
                        if(!confirm('Are you sure you want to reject this application without adding any notes?')) {
                            e.preventDefault();
                        }
                    } else if((action === 'hire' || action === 'shortlist') && !confirm('Are you sure you want to ' + action + ' this candidate?')) {
                        e.preventDefault();
                    }
                });
            });
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            // Don't trigger when typing in form fields
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }
            
            // Navigate with arrow keys
            if (e.key === 'ArrowRight') {
                const nextBtn = document.querySelector('.nav-next:not(.nav-disabled)');
                if (nextBtn) nextBtn.click();
            } else if (e.key === 'ArrowLeft') {
                const prevBtn = document.querySelector('.nav-prev:not(.nav-disabled)');
                if (prevBtn) prevBtn.click();
            } 
            // Download all documents with 'D' key
            else if (e.key === 'd' || e.key === 'D') {
                const downloadBtn = document.querySelector('.btn-download');
                if (downloadBtn) {
                    downloadBtn.click();
                }
            }
        });
        
        // Add keyboard shortcut info tooltip
        const navBar = document.querySelector('.navigation-bar');
        if (navBar) {
            const shortcutInfo = document.createElement('div');
            shortcutInfo.className = 'shortcut-info';
            shortcutInfo.innerHTML = 'Keyboard shortcuts: ← prev | → next | D download all';
            navBar.appendChild(shortcutInfo);
        }
    });
</script>
</html> 