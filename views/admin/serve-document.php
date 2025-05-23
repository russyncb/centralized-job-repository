<?php
// Create this file as: /views/admin/serve-document.php
// Complete document serving script with ZIP download functionality

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has admin role
if(!has_role('admin')) {
    http_response_code(403);
    exit('Access denied');
}

// Get parameters
$file_path = isset($_GET['file']) ? $_GET['file'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : 'view'; // 'view', 'download', or 'zip'
$employer_id = isset($_GET['employer_id']) ? (int)$_GET['employer_id'] : 0;

if(empty($file_path)) {
    http_response_code(400);
    exit('File parameter missing');
}

// Security: Only allow files from uploads/business_files directory
if(!str_contains($file_path, '/uploads/business_files/')) {
    http_response_code(403);
    exit('Invalid file path');
}

// Construct full file path
$full_path = $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha' . $file_path;

// Debug logging
error_log("Serve Document - Requested file path: " . $file_path);
error_log("Serve Document - Full file path: " . $full_path);
error_log("Serve Document - File exists: " . (file_exists($full_path) ? 'Yes' : 'No'));
error_log("Serve Document - Action: " . $action);

// Check if file exists
if(!file_exists($full_path)) {
    http_response_code(404);
    exit('File not found: ' . htmlspecialchars($file_path));
}

// Check if it's actually a file
if(!is_file($full_path)) {
    http_response_code(400);
    exit('Invalid file');
}

// Get file info
$file_size = filesize($full_path);
$file_extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
$original_filename = basename($full_path);

// Get company information if employer_id is provided
$company_name = '';
$contact_name = '';
if($employer_id > 0) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT e.company_name, u.first_name, u.last_name 
              FROM employer_profiles e 
              JOIN users u ON e.user_id = u.user_id 
              WHERE e.employer_id = :employer_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':employer_id', $employer_id);
    $stmt->execute();
    $employer_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($employer_info) {
        $company_name = $employer_info['company_name'];
        $contact_name = $employer_info['first_name'] . ' ' . $employer_info['last_name'];
    }
}

// Clean company name for filename
$safe_company_name = !empty($company_name) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $company_name) : 'Unknown_Company';

// Set appropriate content type
$content_types = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'bmp' => 'image/bmp',
    'txt' => 'text/plain'
];

$content_type = isset($content_types[$file_extension]) ? $content_types[$file_extension] : 'application/octet-stream';

// Handle different actions
switch($action) {
    case 'zip':
        // Create ZIP file with company name
        $zip_filename = $safe_company_name . '_Business_Documents.zip';
        $temp_zip_path = sys_get_temp_dir() . '/' . uniqid('business_docs_') . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($temp_zip_path, ZipArchive::CREATE) !== TRUE) {
            http_response_code(500);
            exit('Cannot create ZIP file');
        }
        
        // Add the business document to ZIP
        $document_name_in_zip = $safe_company_name . '_Business_Document.' . $file_extension;
        $zip->addFile($full_path, $document_name_in_zip);
        
        // Create a simple info file
        $info_content = "Company: " . $company_name . "\n";
        $info_content .= "Contact: " . $contact_name . "\n";
        $info_content .= "Document: " . $original_filename . "\n";
        $info_content .= "Downloaded: " . date('Y-m-d H:i:s') . "\n";
        $info_content .= "Downloaded by: " . $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] . "\n";
        
        $zip->addFromString($safe_company_name . '_Info.txt', $info_content);
        
        $zip->close();
        
        // Send ZIP file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
        header('Content-Length: ' . filesize($temp_zip_path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Expires: 0');
        
        // Clear any output buffering
        while(ob_get_level()) {
            ob_end_clean();
        }
        
        readfile($temp_zip_path);
        
        // Clean up temporary file
        unlink($temp_zip_path);
        exit;
        
    case 'download':
        // Direct download with company name prefix
        $download_filename = $safe_company_name . '_' . $original_filename;
        
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . $download_filename . '"');
        header('Content-Length: ' . $file_size);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Expires: 0');
        
        // Clear any output buffering
        while(ob_get_level()) {
            ob_end_clean();
        }
        
        readfile($full_path);
        exit;
        
    case 'view':
    default:
        // For viewing - set appropriate headers
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . $file_size);
        
        // For PDFs, try to display inline
        if($file_extension === 'pdf') {
            header('Content-Disposition: inline; filename="' . $original_filename . '"');
        } else {
            // For other files, force download
            $download_filename = $safe_company_name . '_' . $original_filename;
            header('Content-Disposition: attachment; filename="' . $download_filename . '"');
        }
        
        header('Cache-Control: public, max-age=3600');
        
        // Clear any output buffering
        while(ob_get_level()) {
            ob_end_clean();
        }
        
        readfile($full_path);
        exit;
}
?>