<?php
// Set page title
$page_title = 'Admin Queries';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Handle query response
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond'])) {
    $query_id = $_POST['query_id'];
    $response = trim($_POST['response']);
    $status = $_POST['status'];
    
    if(!empty($response)) {
        $query = "UPDATE admin_queries 
                 SET response = ?, status = ?, responded_at = NOW() 
                 WHERE query_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $response);
        $stmt->bindParam(2, $status);
        $stmt->bindParam(3, $query_id);
        
        if($stmt->execute()) {
            $success = "Response submitted successfully.";
        } else {
            $error = "Error submitting response.";
        }
    } else {
        $error = "Please provide a response.";
    }
}

// Get all queries with user and company info
$query = "SELECT q.*, u.first_name, u.last_name, e.company_name 
          FROM admin_queries q
          JOIN users u ON q.user_id = u.user_id
          LEFT JOIN employer_profiles e ON u.user_id = e.user_id
          ORDER BY q.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$queries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Modern Query Management Styles */
        .queries-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 1px solid rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .queries-header {
            padding: 25px;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #f6f8fc 0%, #f1f4f9 100%);
        }
        
        .queries-header h3 {
            margin: 0;
            font-size: 1.3rem;
            color: #1a3b5d;
            font-weight: 600;
        }
        
        .query-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .query-item {
            padding: 25px;
            border-bottom: 1px solid #eee;
            transition: all 0.2s;
        }
        
        .query-item:hover {
            background: #f8fafc;
        }
        
        .query-item:last-child {
            border-bottom: none;
        }
        
        .query-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .query-info h4 {
            margin: 0 0 5px;
            font-size: 1.1rem;
            color: #1a3b5d;
            font-weight: 600;
        }
        
        .query-meta {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .query-status {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .status-in_progress {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-resolved {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-rejected {
            background: #fbe9e7;
            color: #d32f2f;
        }
        
        .query-content {
            margin-bottom: 20px;
            color: #2d3748;
            line-height: 1.6;
        }
        
        .query-response {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .response-form {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
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
        
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            resize: vertical;
            min-height: 100px;
            font-size: 0.95rem;
        }
        
        .form-group select {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        .btn-submit {
            padding: 10px 20px;
            background: linear-gradient(135deg, #0056b3 0%, #007bff 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,86,179,0.2);
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .error {
            background: #fbe9e7;
            color: #d32f2f;
            border: 1px solid #ffccbc;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 1.1rem;
            color: #1a3b5d;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'admin-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Employer Queries</h1>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="queries-container">
                <div class="queries-header">
                    <h3>All Queries</h3>
                </div>
                
                <?php if(count($queries) > 0): ?>
                    <ul class="query-list">
                        <?php foreach($queries as $query): ?>
                            <li class="query-item">
                                <div class="query-header">
                                    <div class="query-info">
                                        <h4><?php echo ucwords(str_replace('_', ' ', $query['query_type'])); ?></h4>
                                        <div class="query-meta">
                                            <span>From: <?php echo htmlspecialchars($query['company_name']); ?></span>
                                            <span>Submitted: <?php echo date('M d, Y H:i', strtotime($query['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <span class="query-status status-<?php echo $query['status']; ?>">
                                        <?php echo ucfirst($query['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="query-content">
                                    <?php echo nl2br(htmlspecialchars($query['query_text'])); ?>
                                </div>
                                
                                <?php if($query['response']): ?>
                                    <div class="query-response">
                                        <strong>Response:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($query['response'])); ?>
                                        <div class="query-meta">
                                            Responded: <?php echo date('M d, Y H:i', strtotime($query['responded_at'])); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <form method="post" class="response-form">
                                        <input type="hidden" name="query_id" value="<?php echo $query['query_id']; ?>">
                                        
                                        <div class="form-group">
                                            <label for="response_<?php echo $query['query_id']; ?>">Your Response</label>
                                            <textarea id="response_<?php echo $query['query_id']; ?>" name="response" required></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="status_<?php echo $query['query_id']; ?>">Status</label>
                                            <select id="status_<?php echo $query['query_id']; ?>" name="status" required>
                                                <option value="resolved">Resolved</option>
                                                <option value="in_progress">In Progress</option>
                                                <option value="rejected">Rejected</option>
                                            </select>
                                        </div>
                                        
                                        <button type="submit" name="respond" class="btn-submit">Submit Response</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No queries found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 