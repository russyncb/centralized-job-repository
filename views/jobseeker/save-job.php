<?php
// Set page title
$page_title = 'Save Job';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has jobseeker role
if(!has_role('jobseeker')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Check if job ID and action are provided
if(!isset($_GET['id']) || !isset($_GET['action']) || empty($_GET['id']) || empty($_GET['action'])) {
    redirect(SITE_URL . '/views/jobseeker/search-jobs.php', 'Invalid request.', 'error');
}

$job_id = $_GET['id'];
$action = $_GET['action'];
$redirect_page = isset($_GET['redirect']) ? $_GET['redirect'] : 'search';

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

// Check if job exists and is active
$query = "SELECT job_id FROM jobs WHERE job_id = ? AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $job_id);
$stmt->execute();

if($stmt->rowCount() == 0) {
    redirect(SITE_URL . '/views/jobseeker/search-jobs.php', 'Job not found or no longer active.', 'error');
}

if($action === 'save') {
    // Check if already saved
    $query = "SELECT job_id FROM saved_jobs WHERE job_id = ? AND jobseeker_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $job_id);
    $stmt->bindParam(2, $jobseeker_id);
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        // Save the job
        $query = "INSERT INTO saved_jobs (job_id, jobseeker_id, saved_at) VALUES (?, ?, NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $job_id);
        $stmt->bindParam(2, $jobseeker_id);
        
        if($stmt->execute()) {
            $_SESSION['message'] = "Job saved successfully.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Failed to save job.";
            $_SESSION['message_type'] = "error";
        }
    }
} elseif($action === 'remove') {
    // Remove the saved job
    $query = "DELETE FROM saved_jobs WHERE job_id = ? AND jobseeker_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $job_id);
    $stmt->bindParam(2, $jobseeker_id);
    
    if($stmt->execute()) {
        $_SESSION['message'] = "Job removed from saved jobs.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Failed to remove job.";
        $_SESSION['message_type'] = "error";
    }
}

// Redirect based on the source page
switch($redirect_page) {
    case 'view':
        redirect(SITE_URL . '/views/jobseeker/view-job.php?id=' . $job_id);
        break;
    case 'saved':
        redirect(SITE_URL . '/views/jobseeker/saved-jobs.php');
        break;
    default:
        redirect(SITE_URL . '/views/jobseeker/search-jobs.php');
        break;
}
?>

<script>
    // Add confirmation for logout
    document.addEventListener('DOMContentLoaded', function() {
        const logoutLink = document.querySelector('a[href*="logout.php"]');
        if(logoutLink) {
            logoutLink.addEventListener('click', function(e) {
                if(!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });
        }
    });
</script> 