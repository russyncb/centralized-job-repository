<?php
// Set page title
$page_title = 'Manage Queries';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Create admin_queries table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS admin_queries (
    query_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'in_progress', 'resolved') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";
$db->exec($create_table_query);

// Handle query status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['query_id'])) {
    $query_id = $_POST['query_id'];
    $action = $_POST['action'];
    $admin_notes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';
    
    switch ($action) {
        case 'mark_in_progress':
            $status = 'in_progress';
            $message = "Query marked as in progress.";
            break;
            
        case 'mark_resolved':
            $status = 'resolved';
            $message = "Query marked as resolved.";
            break;
            
        default:
            $status = 'pending';
            $message = "Query status updated.";
    }
    
    $update_query = "UPDATE admin_queries 
                    SET status = :status, admin_notes = :admin_notes 
                    WHERE query_id = :query_id";
    $stmt = $db->prepare($update_query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':admin_notes', $admin_notes);
    $stmt->bindParam(':query_id', $query_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating query status.";
        $_SESSION['message_type'] = "error";
    }
    
    // Redirect to refresh the page
    header("Location: " . SITE_URL . "/views/admin/queries.php");
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch queries - CHANGED: Renamed from $query to $sql_query to avoid conflict
$sql_query = "SELECT q.*, u.first_name, u.last_name, u.email, u.role
          FROM admin_queries q
          JOIN users u ON q.user_id = u.user_id
          WHERE 1=1";

// Add status filter if provided
if (!empty($status_filter)) {
    $sql_query .= " AND q.status = :status";
}

// Add search condition if provided
if (!empty($search)) {
    $sql_query .= " AND (u.email LIKE :search OR u.first_name LIKE :search OR 
                u.last_name LIKE :search OR q.subject LIKE :search)";
}

$sql_query .= " ORDER BY CASE 
                WHEN q.status = 'pending' THEN 0
                WHEN q.status = 'in_progress' THEN 1
                ELSE 2
             END, q.created_at DESC";

$stmt = $db->prepare($sql_query);

// Bind status parameter if provided
if (!empty($status_filter)) {
    $stmt->bindParam(':status', $status_filter);
}

// Bind search parameter if provided
if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $stmt->bindParam(':search', $search_param);
}

$stmt->execute();
$queries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get query status counts
$count_query = "SELECT status, COUNT(*) as count 
               FROM admin_queries 
               GROUP BY status";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$status_counts = $count_stmt->fetchAll(PDO::FETCH_ASSOC);

$status_totals = [
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0
];

foreach ($status_counts as $count) {
    $status_totals[$count['status']] = $count['count'];
}

$total_queries = array_sum($status_totals);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        .query-cards {
            margin-top: 20px;
        }
        
        .query-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #ddd;
        }
        
        .query-card.pending {
            border-left-color: #f44336;
        }
        
        .query-card.in-progress {
            border-left-color: #FFC107;
        }
        
        .query-card.resolved {
            border-left-color: #4CAF50;
        }
        
        .query-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .query-title h3 {
            margin: 0 0 5px;
            font-size: 1.2rem;
        }
        
        .query-meta {
            font-size: 0.9rem;
            color: #666;
            display: flex;
            gap: 10px;
        }
        
        .user-role {
            background-color: #e3f2fd;
            color: #1976d2;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.8rem;
        }
        
        .user-role.employer {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .user-role.jobseeker {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .query-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-left: auto;
        }
        
        .status-pending {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .status-in-progress {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .status-resolved {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .query-message {
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .query-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-progress {
            background-color: #FFC107;
            color: #000;
        }
        
        .btn-resolve {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-reopen {
            background-color: #f44336;
            color: white;
        }
        
        .admin-notes {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .admin-notes h4 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1rem;
            color: #555;
        }
        
        .admin-notes-content {
            font-style: italic;
            color: #666;
        }
        
        .admin-notes-form {
            margin-top: 20px;
        }
        
        .admin-notes-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            height: 100px;
            font-family: inherit;
            resize: vertical;
        }
        
        .filter-tools {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0;
            font-size: 2rem;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .stat-pending h3 {
            color: #f44336;
        }
        
        .stat-in-progress h3 {
            color: #FFC107;
        }
        
        .stat-resolved h3 {
            color: #4CAF50;
        }
        
        .no-queries {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .no-queries h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include '../admin/admin-sidebar.php'; ?>
        
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
            
            <div class="stats-cards">
                <div class="stat-card">
                    <h3><?php echo $total_queries; ?></h3>
                    <p>Total Queries</p>
                </div>
                <div class="stat-card stat-pending">
                    <h3><?php echo $status_totals['pending']; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card stat-in-progress">
                    <h3><?php echo $status_totals['in_progress']; ?></h3>
                    <p>In Progress</p>
                </div>
                <div class="stat-card stat-resolved">
                    <h3><?php echo $status_totals['resolved']; ?></h3>
                    <p>Resolved</p>
                </div>
            </div>
            
            <div class="filter-tools">
                <form method="get" class="filter-form">
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="User name, email or subject" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php if($status_filter == 'pending') echo 'selected'; ?>>Pending</option>
                            <option value="in_progress" <?php if($status_filter == 'in_progress') echo 'selected'; ?>>In Progress</option>
                            <option value="resolved" <?php if($status_filter == 'resolved') echo 'selected'; ?>>Resolved</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="<?php echo SITE_URL; ?>/views/admin/queries.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>
            
            <?php if(count($queries) > 0): ?>
                <div class="query-cards">
                    <?php foreach($queries as $query): ?>
                        <div class="query-card <?php echo $query['status']; ?>">
                            <div class="query-header">
                                <div class="query-title">
                                    <h3><?php echo htmlspecialchars($query['subject'] ?? 'No Subject'); ?></h3>
                                    <div class="query-meta">
                                        <span><?php echo htmlspecialchars($query['first_name'] . ' ' . $query['last_name']); ?></span>
                                        <span><?php echo htmlspecialchars($query['email']); ?></span>
                                        <span class="user-role <?php echo $query['role']; ?>"><?php echo ucfirst($query['role']); ?></span>
                                        <span>Submitted: <?php echo date('M d, Y', strtotime($query['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="query-status status-<?php echo $query['status']; ?>">
                                    <?php 
                                        $status_text = $query['status'] == 'in_progress' ? 'In Progress' : ucfirst($query['status']);
                                        echo $status_text;
                                    ?>
                                </div>
                            </div>
                            
                            <div class="query-message">
                                <?php echo nl2br(htmlspecialchars($query['message'] ?? 'No message content')); ?>
                            </div>
                            
                            <?php if(!empty($query['admin_notes'])): ?>
                                <div class="admin-notes">
                                    <h4>Admin Notes:</h4>
                                    <div class="admin-notes-content">
                                        <?php echo nl2br(htmlspecialchars($query['admin_notes'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post" class="admin-notes-form">
                                <input type="hidden" name="query_id" value="<?php echo $query['query_id']; ?>">
                                
                                <div style="margin-bottom: 15px;">
                                    <label for="admin_notes_<?php echo $query['query_id']; ?>" style="display: block; margin-bottom: 5px; font-weight: 500;">Admin Notes</label>
                                    <textarea name="admin_notes" id="admin_notes_<?php echo $query['query_id']; ?>" placeholder="Add your notes here"><?php echo htmlspecialchars($query['admin_notes'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="query-actions">
                                    <?php if($query['status'] == 'pending'): ?>
                                        <button type="submit" name="action" value="mark_in_progress" class="btn btn-progress">Mark In Progress</button>
                                        <button type="submit" name="action" value="mark_resolved" class="btn btn-resolve">Mark Resolved</button>
                                    <?php elseif($query['status'] == 'in_progress'): ?>
                                        <button type="submit" name="action" value="mark_resolved" class="btn btn-resolve">Mark Resolved</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="mark_in_progress" class="btn btn-reopen">Reopen Query</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-queries">
                    <h3>No Queries Found</h3>
                    <p>There are no queries matching your current filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Check if auto-search is enabled in settings
            <?php
            // Check settings for auto-search
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT setting_value FROM settings WHERE setting_name = 'enable_auto_search'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $auto_search_enabled = !empty($result) && $result['setting_value'] == '1';
            ?>
            
            const autoSearchEnabled = <?php echo $auto_search_enabled ? 'true' : 'false'; ?>;
            
            if (autoSearchEnabled) {
                // Get form elements
                const filterForm = document.querySelector('.filter-form');
                const statusSelect = document.getElementById('status');
                const searchInput = document.getElementById('search');
                
                // Add change event listeners to select elements
                statusSelect.addEventListener('change', function() {
                    filterForm.submit();
                });
                
                // For search input, submit after a short delay when typing stops
                let typingTimer;
                searchInput.addEventListener('input', function() {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(function() {
                        filterForm.submit();
                    }, 500); // 500ms delay after typing stops
                });
            }
            
            // Make sure the buttons work by enforcing form submission
            document.querySelectorAll('.query-actions button').forEach(button => {
                button.addEventListener('click', function() {
                    // Ensure the form gets submitted when a button is clicked
                    this.closest('form').submit();
                });
            });
        });
    </script>
</body>
</html>