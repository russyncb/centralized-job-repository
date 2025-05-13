<?php
// Set page title
$page_title = 'Verify Employers';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

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

// Get pending employers
$query = "SELECT e.employer_id, e.company_name, e.industry, e.location, e.website, 
         u.user_id, u.first_name, u.last_name, u.email, u.phone, u.created_at
         FROM employer_profiles e
         JOIN users u ON e.user_id = u.user_id
         WHERE u.status = 'pending'
         ORDER BY u.created_at DESC";

// Initialize pending_employers as an empty array
$pending_employers = [];

try {
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pending_employers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = "Database error: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    // Ensure $pending_employers is still an array even if the query fails
    $pending_employers = [];
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
        }
        
        .employer-cards {
            margin-top: 20px;
        }
        
        .employer-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .employer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
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
        
        .no-employers {
            text-align: center;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 50px 20px;
        }
        
        .no-employers h3 {
            margin-top: 0;
            margin-bottom: 10px;
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
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>