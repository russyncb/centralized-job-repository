<?php
// Set page title
$page_title = 'Verify Employers';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Enable error reporting for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Get pending employer count for badge
$count_query = "SELECT COUNT(*) as count FROM users u 
                JOIN employer_profiles e ON u.user_id = e.user_id 
                WHERE u.status = 'pending'";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$employer_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// DIAGNOSTIC: Get users with 'pending' status
$users_query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.status
               FROM users u
               WHERE u.status = 'pending'";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$pending_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// DIAGNOSTIC: Get employer_profiles without verified flag set
$profiles_query = "SELECT e.employer_id, e.user_id, e.company_name, e.verified
                  FROM employer_profiles e
                  WHERE e.verified = 0 OR e.verified IS NULL";
$profiles_stmt = $db->prepare($profiles_query);
$profiles_stmt->execute();
$unverified_profiles = $profiles_stmt->fetchAll(PDO::FETCH_ASSOC);

// TRY DIFFERENT QUERY APPROACHES

// Approach 1: Original JOIN query with simpler conditions
$query1 = "SELECT e.employer_id, e.company_name, e.industry, e.location, e.website, 
          u.user_id, u.first_name, u.last_name, u.email, u.phone, u.created_at, e.business_file
          FROM users u
          JOIN employer_profiles e ON u.user_id = e.user_id
          WHERE u.status = 'pending'
          ORDER BY u.created_at DESC";

$stmt1 = $db->prepare($query1);
$stmt1->execute();
$pending_employers1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

// Approach 2: Get all employer profiles first, left join with users
$query2 = "SELECT e.employer_id, e.company_name, e.industry, e.location, e.website, 
          u.user_id, u.first_name, u.last_name, u.email, u.phone, u.created_at, e.business_file
          FROM employer_profiles e
          LEFT JOIN users u ON e.user_id = u.user_id
          WHERE (e.verified = 0 OR e.verified IS NULL)
          ORDER BY u.created_at DESC";

$stmt2 = $db->prepare($query2);
$stmt2->execute();
$pending_employers2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Use the most successful query results
if (count($pending_employers1) > 0) {
    $pending_employers = $pending_employers1;
    $query_used = "Approach 1: JOIN with u.status = 'pending'";
} elseif (count($pending_employers2) > 0) {
    $pending_employers = $pending_employers2;
    $query_used = "Approach 2: LEFT JOIN with e.verified = 0 OR NULL";
} else {
    $pending_employers = [];
    $query_used = "No successful query approach";
}

// Demo data - Uncomment this block to show sample data if you need to test the UI
/*
$pending_employers = [
    [
        'employer_id' => 1,
        'company_name' => 'Acme Corporation',
        'industry' => 'Technology',
        'location' => 'New York, NY',
        'website' => 'https://acme.example.com',
        'user_id' => 101,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@acme.example.com',
        'phone' => '555-123-4567',
        'created_at' => '2023-05-15 10:30:00',
        'business_file' => '/uploads/business/acme_business_license.pdf'
    ],
    [
        'employer_id' => 2,
        'company_name' => 'Global Industries',
        'industry' => 'Manufacturing',
        'location' => 'Chicago, IL',
        'website' => 'https://global.example.com',
        'user_id' => 102,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane.smith@global.example.com',
        'phone' => '555-987-6543',
        'created_at' => '2023-05-16 09:15:00',
        'business_file' => '/uploads/business/global_certificate.pdf'
    ]
];
*/

// Function to create diagnostic output
function getDiagnosticInfo($db, $pending_users, $unverified_profiles, $query_used, $employer_count) {
    $output = "<div class='debug-panel'>";
    $output .= "<h4>System Diagnostics</h4>";
    $output .= "<p>Query approach used: " . $query_used . "</p>";
    $output .= "<p>Badge count: " . $employer_count . "</p>";
    
    // Database info
    $output .= "<h5>Database Connection</h5>";
    $output .= "<p>PDO Attributes:</p><ul>";
    $attributes = [
        PDO::ATTR_DRIVER_NAME => "Driver Name",
        PDO::ATTR_SERVER_VERSION => "Server Version",
        PDO::ATTR_CLIENT_VERSION => "Client Version",
        PDO::ATTR_CONNECTION_STATUS => "Connection Status"
    ];
    
    foreach ($attributes as $attr => $name) {
        try {
            $value = $db->getAttribute($attr);
            $output .= "<li>" . $name . ": " . $value . "</li>";
        } catch (Exception $e) {
            $output .= "<li>" . $name . ": Not available</li>";
        }
    }
    $output .= "</ul>";
    
    // Users with 'pending' status
    $output .= "<h5>Users with 'pending' status: " . count($pending_users) . "</h5>";
    if (count($pending_users) > 0) {
        $output .= "<table class='debug-table'>";
        $output .= "<tr><th>User ID</th><th>Name</th><th>Email</th><th>Status</th></tr>";
        foreach ($pending_users as $user) {
            $output .= "<tr>";
            $output .= "<td>" . $user['user_id'] . "</td>";
            $output .= "<td>" . $user['first_name'] . " " . $user['last_name'] . "</td>";
            $output .= "<td>" . $user['email'] . "</td>";
            $output .= "<td>" . $user['status'] . "</td>";
            $output .= "</tr>";
        }
        $output .= "</table>";
    } else {
        $output .= "<p>No users with 'pending' status found.</p>";
    }
    
    // Unverified employer profiles
    $output .= "<h5>Unverified employer profiles: " . count($unverified_profiles) . "</h5>";
    if (count($unverified_profiles) > 0) {
        $output .= "<table class='debug-table'>";
        $output .= "<tr><th>Employer ID</th><th>User ID</th><th>Company Name</th><th>Verified</th></tr>";
        foreach ($unverified_profiles as $profile) {
            $output .= "<tr>";
            $output .= "<td>" . $profile['employer_id'] . "</td>";
            $output .= "<td>" . $profile['user_id'] . "</td>";
            $output .= "<td>" . $profile['company_name'] . "</td>";
            $output .= "<td>" . ($profile['verified'] ? "Yes" : "No") . "</td>";
            $output .= "</tr>";
        }
        $output .= "</table>";
    } else {
        $output .= "<p>No unverified employer profiles found.</p>";
    }
    
    $output .= "</div>";
    return $output;
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
        /* Admin Dashboard Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .admin-content {
            flex: 1;
            padding: 105px 30px 30px;
            transition: all 0.3s ease;
            min-height: 100vh;
            background-color: #f8f9fa;
            width: calc(100% - 270px);
            max-width: 100%;
            box-sizing: border-box;
            overflow-y: auto;
        }
        
        .sidebar.collapsed ~ .admin-content {
            width: calc(100% - 80px);
        }
        
        @media (max-width: 768px) {
            .admin-content {
                width: calc(100% - 80px);
            }
        }
        
        .employer-cards {
            margin-top: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .employer-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .employer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            width: 100%;
            box-sizing: border-box;
        }
        
        .employer-name h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .employer-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .detail-group {
            margin-bottom: 10px;
        }
        
        .detail-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .detail-group span {
            font-size: 1rem;
        }
        
        .employer-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .btn-verify {
            background-color: #28a745;
            color: white;
        }
        
        .btn-reject {
            background-color: #dc3545;
            color: white;
        }
        
        .badge {
            display: inline-block;
            background-color: #dc3545;
            color: white;
            font-size: 0.8rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 12px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .no-employers {
            text-align: center;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 50px 20px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .no-employers h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        /* Debug Panel Styles */
        .debug-panel {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
            overflow-x: auto;
        }
        
        .debug-panel h4, .debug-panel h5 {
            margin-top: 0;
            margin-bottom: 0.5rem;
        }
        
        .debug-panel h5 {
            margin-top: 1rem;
        }
        
        .debug-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .debug-table th, .debug-table td {
            padding: 0.5rem;
            border: 1px solid #dee2e6;
            text-align: left;
        }
        
        .debug-table th {
            background-color: #f8f9fa;
        }
        
        .toggle-debug {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 10px;
        }
        
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'admin-sidebar.php'; ?>
        
        <div class="admin-content">
            <?php if(isset($_SESSION['message'])): ?>
                <div class="message <?php echo $_SESSION['message_type']; ?>">
                    <?php 
                        echo $_SESSION['message']; 
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>
            
            <h1>Verify Employers <?php if($employer_count > 0): ?><span class="badge"><?php echo $employer_count; ?></span><?php endif; ?></h1>
            
            <!-- Debug Toggle Button -->
            <button class="toggle-debug" onclick="toggleDebug()">Show System Diagnostics</button>
            
            <!-- Debug Information (hidden by default) -->
            <div id="debugInfo" class="hidden">
                <?php echo getDiagnosticInfo($db, $pending_users, $unverified_profiles, $query_used, $employer_count); ?>
            </div>
            
            <?php if(is_array($pending_employers) && count($pending_employers) > 0): ?>
                <div class="employer-cards">
                    <?php foreach($pending_employers as $employer): ?>
                        <div class="employer-card">
                            <div class="employer-header">
                                <div class="employer-name">
                                    <h3><?php echo htmlspecialchars($employer['company_name']); ?></h3>
                                    <p>Application date: <?php echo date('M d, Y', strtotime($employer['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="employer-details">
                                <div>
                                    <div class="detail-group">
                                        <label>Contact Person</label>
                                        <span><?php echo htmlspecialchars($employer['first_name'] . ' ' . $employer['last_name']); ?></span>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <label>Email</label>
                                        <span><?php echo htmlspecialchars($employer['email']); ?></span>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <label>Phone</label>
                                        <span><?php echo htmlspecialchars($employer['phone'] ?? 'Not provided'); ?></span>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="detail-group">
                                        <label>Industry</label>
                                        <span><?php echo htmlspecialchars($employer['industry'] ?? 'Not provided'); ?></span>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <label>Location</label>
                                        <span><?php echo htmlspecialchars($employer['location'] ?? 'Not provided'); ?></span>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <label>Website</label>
                                        <span>
                                            <?php if(!empty($employer['website'])): ?>
                                                <a href="<?php echo htmlspecialchars($employer['website']); ?>" target="_blank"><?php echo htmlspecialchars($employer['website']); ?></a>
                                            <?php else: ?>
                                                Not provided
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <label>Business Document</label>
                                        <span>
                                            <?php if(!empty($employer['business_file'])): ?>
                                                <a href="<?php echo SITE_URL . $employer['business_file']; ?>" target="_blank">View Document</a>
                                            <?php else: ?>
                                                Not provided
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="employer-actions">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="employer_id" value="<?php echo $employer['employer_id']; ?>">
                                    <button type="submit" name="reject" class="btn btn-reject" onclick="return confirm('Are you sure you want to reject this employer?')">Reject</button>
                                </form>
                                
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="employer_id" value="<?php echo $employer['employer_id']; ?>">
                                    <button type="submit" name="verify" class="btn btn-verify">Verify Employer</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-employers">
                    <h3>No Pending Employers</h3>
                    <p>There are currently no employers waiting for verification.</p>
                    
                    <?php if($employer_count > 0): ?>
                    <div class="message error">
                        <p><strong>System Notice:</strong> Badge shows <?php echo $employer_count; ?> pending employer(s), but none were found in database query. Please check the diagnostics for more information.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($employer_count > 0): ?>
                    <!-- Quick Fix Section -->
                    <div style="text-align: left; margin-top: 20px; padding: 15px; background-color: #fff3cd; border-radius: 5px;">
                        <h4>Quick Fix Options:</h4>
                        <p>There appears to be a data inconsistency. Here are some potential solutions:</p>
                        <ol>
                            <li>
                                <strong>Option 1:</strong> Uncomment the test data in the code to temporarily display sample employers while you fix the database issue.
                            </li>
                            <li>
                                <strong>Option 2:</strong> Check if users have 'pending' status but might be missing employer profile entries.
                            </li>
                            <li>
                                <strong>Option 3:</strong> Check database permissions and connection settings.
                            </li>
                        </ol>
                        <p>Use the "Show System Diagnostics" button above to investigate further.</p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleDebug() {
            var debugInfo = document.getElementById('debugInfo');
            var toggleBtn = document.querySelector('.toggle-debug');
            
            if (debugInfo.classList.contains('hidden')) {
                debugInfo.classList.remove('hidden');
                toggleBtn.textContent = 'Hide System Diagnostics';
            } else {
                debugInfo.classList.add('hidden');
                toggleBtn.textContent = 'Show System Diagnostics';
            }
        }
    </script>
</body>
</html>