<?php
// Set page title
$page_title = 'Verify Employers';

// Include bootstrap - Fixed path to match register.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Process verification or rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify']) && isset($_POST['employer_id'])) {
        $employer_id = $_POST['employer_id'];
        $admin_id = $_SESSION['user_id'];
        
        // Update employer verification status
        $query = "UPDATE employer_profiles 
                 SET verified = 1, 
                     verified_at = NOW(), 
                     verified_by = :admin_id 
                 WHERE employer_id = :employer_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':admin_id', $admin_id);
        $stmt->bindParam(':employer_id', $employer_id);
        
        if ($stmt->execute()) {
            // Update user status to active
            $query_user = "UPDATE users u
                          JOIN employer_profiles e ON u.user_id = e.user_id
                          SET u.status = 'active'
                          WHERE e.employer_id = :employer_id";
            
            $stmt_user = $db->prepare($query_user);
            $stmt_user->bindParam(':employer_id', $employer_id);
            
            if ($stmt_user->execute()) {
                $_SESSION['message'] = "Employer verified successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating user status.";
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Error verifying employer.";
            $_SESSION['message_type'] = "error";
        }
    } elseif (isset($_POST['reject']) && isset($_POST['employer_id'])) {
        $employer_id = $_POST['employer_id'];
        
        // Update user status to rejected
        $query_user = "UPDATE users u
                      JOIN employer_profiles e ON u.user_id = e.user_id
                      SET u.status = 'rejected'
                      WHERE e.employer_id = :employer_id";
        
        $stmt_user = $db->prepare($query_user);
        $stmt_user->bindParam(':employer_id', $employer_id);
        
        if ($stmt_user->execute()) {
            $_SESSION['message'] = "Employer application rejected.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error rejecting employer.";
            $_SESSION['message_type'] = "error";
        }
    }
    
    // Redirect to refresh the page
    header("Location: " . SITE_URL . "/views/admin/verify-employers.php");
    exit;
}

// Initialize pending employers as an empty array to prevent count errors
$pending_employers = [];

// Pagination setup
$records_per_page = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// DIAGNOSTIC: Check sidebar function
function get_pending_employers_count_test() {
    global $db;
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'employer' AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

// Get total number of pending employers - Match sidebar query
$count_query = "SELECT COUNT(*) AS total FROM users WHERE role = 'employer' AND status = 'pending'";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $records_per_page);

// DIAGNOSTIC: Alternative count query to check if pending status might be stored differently
$alt_count_query = "SELECT COUNT(*) AS total FROM users WHERE role = 'employer'";
$alt_count_stmt = $db->prepare($alt_count_query);
$alt_count_stmt->execute();
$alt_total_records = $alt_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// DIAGNOSTIC: Check users with employer role
$diagnostic_query = "SELECT user_id, email, status, role FROM users WHERE role = 'employer' LIMIT 10";
$diagnostic_stmt = $db->prepare($diagnostic_query);
$diagnostic_stmt->execute();
$diagnostic_results = $diagnostic_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending employers with pagination
$query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.status, u.created_at,
         e.employer_id, e.company_name, e.industry, e.location, e.website, e.business_file
         FROM users u
         LEFT JOIN employer_profiles e ON u.user_id = e.user_id
         WHERE u.role = 'employer' AND u.status = 'pending'
         ORDER BY u.created_at DESC
         LIMIT :offset, :records_per_page";

try {
    $stmt = $db->prepare($query);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $pending_employers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log error
    echo "Error fetching pending employers: " . $e->getMessage();
    // Already initialized $pending_employers as empty array above
}

// DIAGNOSTIC: Alternative query that might catch more employers
$alt_query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.status, u.created_at,
             e.employer_id, e.company_name, e.industry, e.location, e.website, e.business_file
             FROM users u
             LEFT JOIN employer_profiles e ON u.user_id = e.user_id
             WHERE u.role = 'employer'
             ORDER BY u.created_at DESC
             LIMIT 10";

try {
    $alt_stmt = $db->prepare($alt_query);
    $alt_stmt->execute();
    $alt_employers = $alt_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error fetching alternative employers: " . $e->getMessage();
    $alt_employers = [];
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
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .diagnostic-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 20px;
            padding: 20px;
        }
        
        .diagnostic-section {
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        
        .diagnostic-section h2 {
            margin-top: 0;
            color: #1557b0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        table, th, td {
            border: 1px solid #ddd;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
        }
        
        .diagnostic-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .label {
            font-weight: bold;
            margin-right: 10px;
        }
        
        .warning {
            color: orange;
            font-weight: bold;
        }
        
        .error {
            color: red;
            font-weight: bold;
        }
        
        .success {
            color: green;
            font-weight: bold;
        }
        
        .debug-log {
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            white-space: pre-wrap;
            margin: 10px 0;
            max-height: 300px;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="diagnostic-container">
        <h1>Verify Employers - Diagnostic Mode</h1>
        
        <div class="diagnostic-section">
            <h2>Database Connection</h2>
            <div class="diagnostic-info">
                <p><span class="label">Connection Status:</span> 
                <?php 
                    try {
                        $db->query("SELECT 1");
                        echo '<span class="success">Connected</span>';
                    } catch (PDOException $e) {
                        echo '<span class="error">Failed: ' . $e->getMessage() . '</span>';
                    }
                ?>
                </p>
            </div>
            <p>This checks if the database connection is working properly.</p>
        </div>
        
        <div class="diagnostic-section">
            <h2>Employer Count Comparison</h2>
            <div class="diagnostic-info">
                <p><span class="label">Admin sidebar count function result:</span> 
                <?php 
                    if (function_exists('get_pending_employers_count')) {
                        echo get_pending_employers_count(); 
                    } else {
                        echo get_pending_employers_count_test();
                    }
                ?>
                </p>
                <p><span class="label">Current page count query result:</span> <?php echo $total_records; ?></p>
                <p><span class="label">Alternative count (all employers):</span> <?php echo $alt_total_records; ?></p>
            </div>
            <p>This compares how employers are counted in different parts of the system.</p>
        </div>
        
        <div class="diagnostic-section">
            <h2>Users with 'employer' Role</h2>
            <p>First 10 users with 'employer' role in the database:</p>
            <?php if(count($diagnostic_results) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($diagnostic_results as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td><?php echo $user['status']; ?></td>
                                <td><?php echo $user['role']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="warning">No users with 'employer' role found!</p>
            <?php endif; ?>
        </div>
        
        <div class="diagnostic-section">
            <h2>Main Query Results (Pending Employers)</h2>
            <div class="diagnostic-info">
                <p><span class="label">SQL Query:</span> <?php echo str_replace(':offset', $offset, str_replace(':records_per_page', $records_per_page, $query)); ?></p>
                <p><span class="label">Total Results:</span> <?php echo count($pending_employers); ?></p>
            </div>
            
            <?php if(count($pending_employers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Employer ID</th>
                            <th>Company</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pending_employers as $employer): ?>
                            <tr>
                                <td><?php echo $employer['user_id']; ?></td>
                                <td><?php echo $employer['first_name'] . ' ' . $employer['last_name']; ?></td>
                                <td><?php echo $employer['email']; ?></td>
                                <td><?php echo $employer['employer_id'] ?? 'N/A'; ?></td>
                                <td><?php echo $employer['company_name'] ?? 'N/A'; ?></td>
                                <td><?php echo $employer['status']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="warning">No pending employers found with the main query!</p>
            <?php endif; ?>
        </div>
        
        <div class="diagnostic-section">
            <h2>Alternative Query Results (All Employers)</h2>
            <div class="diagnostic-info">
                <p><span class="label">SQL Query:</span> <?php echo $alt_query; ?></p>
                <p><span class="label">Total Results:</span> <?php echo count($alt_employers); ?></p>
            </div>
            
            <?php if(count($alt_employers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Employer ID</th>
                            <th>Company</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($alt_employers as $employer): ?>
                            <tr>
                                <td><?php echo $employer['user_id']; ?></td>
                                <td><?php echo $employer['first_name'] . ' ' . $employer['last_name']; ?></td>
                                <td><?php echo $employer['email']; ?></td>
                                <td><?php echo $employer['status']; ?></td>
                                <td><?php echo $employer['employer_id'] ?? 'N/A'; ?></td>
                                <td><?php echo $employer['company_name'] ?? 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="warning">No employers found with the alternative query!</p>
            <?php endif; ?>
        </div>
        
        <div class="diagnostic-section">
            <h2>System Information</h2>
            <div class="diagnostic-info">
                <p><span class="label">PHP Version:</span> <?php echo phpversion(); ?></p>
                <p><span class="label">Document Root:</span> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></p>
                <p><span class="label">Script Path:</span> <?php echo $_SERVER['SCRIPT_FILENAME']; ?></p>
                <p><span class="label">Site URL:</span> <?php echo SITE_URL; ?></p>
            </div>
        </div>
        
        <div class="diagnostic-section">
            <h2>Session Information</h2>
            <div class="diagnostic-info">
                <p><span class="label">Session ID:</span> <?php echo session_id(); ?></p>
                <p><span class="label">User logged in:</span> <?php echo isset($_SESSION['user_id']) ? 'Yes (ID: '.$_SESSION['user_id'].')' : 'No'; ?></p>
                <p><span class="label">User role:</span> <?php echo $_SESSION['role'] ?? 'Not set'; ?></p>
            </div>
        </div>
        
        <div>
            <a href="<?php echo SITE_URL; ?>/views/admin/verify-employers.php" class="btn btn-primary">Return to Normal View</a>
        </div>
    </div>
</body>
</html>