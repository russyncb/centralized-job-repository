<?php
// Set page title
$page_title = 'Export Applications';

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

// Get all jobs for this employer
$jobs_query = "SELECT job_id, title, 
              (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as applications_count
              FROM jobs j 
              WHERE employer_id = ? 
              ORDER BY posted_at DESC";
$jobs_stmt = $db->prepare($jobs_query);
$jobs_stmt->bindParam(1, $employer_id);
$jobs_stmt->execute();
$jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Process export request
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $export_type = $_POST['export_type'];
    $job_id = isset($_POST['job_id']) ? $_POST['job_id'] : null;
    $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : null;
    $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : null;
    
    // Create export directory if it doesn't exist
    $export_dir = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/exports/applications/' . $employer_id . '/';
    if(!is_dir($export_dir)) {
        mkdir($export_dir, 0755, true);
    }
    
    // Generate unique export folder name
    $export_folder = $export_dir . date('Y-m-d_His') . '/';
    mkdir($export_folder, 0755, true);
    
    // Start building query
    $query = "SELECT a.*, j.title as job_title, 
             u.first_name, u.last_name, u.email, u.phone,
             jp.headline, jp.education_level, jp.experience_years, jp.skills
             FROM applications a
             JOIN jobs j ON a.job_id = j.job_id
             JOIN jobseeker_profiles jp ON a.jobseeker_id = jp.jobseeker_id
             JOIN users u ON jp.user_id = u.user_id
             WHERE j.employer_id = ?";
    
    $params = [$employer_id];
    
    if($job_id) {
        $query .= " AND j.job_id = ?";
        $params[] = $job_id;
    }
    
    if($date_from) {
        $query .= " AND a.applied_at >= ?";
        $params[] = $date_from;
    }
    
    if($date_to) {
        $query .= " AND a.applied_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    $stmt = $db->prepare($query);
    foreach($params as $i => $param) {
        $stmt->bindValue($i + 1, $param);
    }
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(count($applications) > 0) {
        foreach($applications as $application) {
            // Create applicant folder
            $applicant_folder = $export_folder . sanitize_filename($application['first_name'] . '_' . $application['last_name']) . '/';
            mkdir($applicant_folder, 0755, true);
            
            // Get all documents for this application
            $docs_query = "SELECT * FROM applicant_documents 
                         WHERE application_id = ? OR (jobseeker_id = ? AND application_id IS NULL)";
            $docs_stmt = $db->prepare($docs_query);
            $docs_stmt->bindParam(1, $application['application_id']);
            $docs_stmt->bindParam(2, $application['jobseeker_id']);
            $docs_stmt->execute();
            $documents = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Copy documents to applicant folder
            foreach($documents as $doc) {
                $source = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/' . $doc['file_path'];
                $dest = $applicant_folder . $doc['document_type'] . '_' . $doc['original_filename'];
                if(file_exists($source)) {
                    copy($source, $dest);
                }
            }
            
            // Create applicant info JSON
            $applicant_info = array_merge(
                array_intersect_key($application, array_flip([
                    'first_name', 'last_name', 'email', 'phone',
                    'headline', 'education_level', 'experience_years', 'skills',
                    'status', 'applied_at'
                ])),
                ['job_title' => $application['job_title']]
            );
            
            file_put_contents(
                $applicant_folder . 'applicant_info.json',
                json_encode($applicant_info, JSON_PRETTY_PRINT)
            );
            
            // Mark application as exported
            $update_query = "UPDATE applications SET exported = 1, export_date = NOW() WHERE application_id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(1, $application['application_id']);
            $update_stmt->execute();
        }
        
        // Create zip archive
        $zip = new ZipArchive();
        $zip_name = 'applications_' . date('Y-m-d_His') . '.zip';
        $zip_path = $export_dir . $zip_name;
        
        if($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($export_folder),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach($files as $file) {
                if(!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($export_folder));
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
            
            // Log export
            $log_query = "INSERT INTO export_logs (employer_id, export_type, file_path, status) VALUES (?, ?, ?, 'completed')";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(1, $employer_id);
            $log_stmt->bindParam(2, $export_type);
            $log_stmt->bindParam(3, 'exports/applications/' . $employer_id . '/' . $zip_name);
            $log_stmt->execute();
            
            // Provide download link
            $success = "Export completed successfully. <a href='" . SITE_URL . "/exports/applications/" . $employer_id . "/" . $zip_name . "' class='download-link'>Download ZIP</a>";
            
            // Clean up export folder
            deleteDirectory($export_folder);
        } else {
            $error = "Failed to create ZIP archive.";
        }
    } else {
        $error = "No applications found matching the selected criteria.";
    }
}

// Helper function to sanitize filenames
function sanitize_filename($filename) {
    // Remove any character that isn't a letter, number, underscore or hyphen
    $filename = preg_replace("/[^a-zA-Z0-9-_]/", "_", $filename);
    // Remove multiple consecutive underscores
    $filename = preg_replace("/_+/", "_", $filename);
    // Remove leading/trailing underscores
    return trim($filename, "_");
}

// Helper function to delete directory and contents
function deleteDirectory($dir) {
    if(!file_exists($dir)) return;
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    return rmdir($dir);
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
        /* Export Applications Page Styles */
        .export-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .export-header {
            padding: 25px;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
        }
        
        .export-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #1a3b5d;
            font-weight: 600;
        }
        
        .export-content {
            padding: 25px;
        }
        
        .export-form {
            max-width: 800px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .section-title {
            font-size: 1.1rem;
            color: #1a3b5d;
            margin-bottom: 15px;
            font-weight: 600;
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
        
        .form-group select,
        .form-group input[type="date"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #1a3b5d;
            background: #f8fafc;
            transition: all 0.3s ease;
        }
        
        .form-group select:focus,
        .form-group input[type="date"]:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: white;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #1557b0 0%, #1a3b5d 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(21, 87, 176, 0.2);
        }
        
        .export-history {
            margin-top: 30px;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th,
        .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .history-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #1a3b5d;
        }
        
        .download-link {
            color: #1557b0;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .download-link:hover {
            text-decoration: underline;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="employer-container">
        <?php include 'employer-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Export Applications</h1>
                <div class="user-info">
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($employer['company_name']); ?></span>
                        <?php if($employer['verified']): ?>
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
            
            <div class="export-container">
                <div class="export-header">
                    <h3>Export Applications</h3>
                </div>
                
                <div class="export-content">
                    <form method="post" action="" class="export-form">
                        <div class="form-section">
                            <h4 class="section-title">Export Type</h4>
                            <div class="form-group">
                                <label for="export_type">Select Export Type</label>
                                <select id="export_type" name="export_type" required>
                                    <option value="all_documents">All Documents (CV, Cover Letter, Certificates)</option>
                                    <option value="cv_only">CVs Only</option>
                                    <option value="applications_summary">Applications Summary</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h4 class="section-title">Filter Options</h4>
                            <div class="form-group">
                                <label for="job_id">Select Job</label>
                                <select id="job_id" name="job_id">
                                    <option value="">All Jobs</option>
                                    <?php foreach($jobs as $job): ?>
                                        <option value="<?php echo $job['job_id']; ?>">
                                            <?php echo htmlspecialchars($job['title']); ?> 
                                            (<?php echo $job['applications_count']; ?> applications)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="date_from">From Date</label>
                                    <input type="date" id="date_from" name="date_from">
                                </div>
                                
                                <div class="form-group">
                                    <label for="date_to">To Date</label>
                                    <input type="date" id="date_to" name="date_to">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-export">
                            <span class="icon">📥</span> Export Applications
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="export-container export-history">
                <div class="export-header">
                    <h3>Export History</h3>
                </div>
                
                <div class="export-content">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $history_query = "SELECT * FROM export_logs 
                                            WHERE employer_id = ? 
                                            ORDER BY export_date DESC 
                                            LIMIT 10";
                            $history_stmt = $db->prepare($history_query);
                            $history_stmt->bindParam(1, $employer_id);
                            $history_stmt->execute();
                            $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach($history as $export):
                            ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($export['export_date'])); ?></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', $export['export_type'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $export['status']; ?>">
                                            <?php echo ucfirst($export['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($export['status'] == 'completed'): ?>
                                            <a href="<?php echo SITE_URL . '/' . $export['file_path']; ?>" class="download-link">
                                                <span class="icon">📥</span> Download
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($history)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #64748b;">
                                        No export history available
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 