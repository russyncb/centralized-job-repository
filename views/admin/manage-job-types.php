<?php
// Set page title
$page_title = 'Manage Job Types';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Create job_types table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS job_types (
    job_type_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$db->exec($create_table_query);

// Check if the job_types table is empty and populate with default values
$check_query = "SELECT COUNT(*) as count FROM job_types";
$check_stmt = $db->prepare($check_query);
$check_stmt->execute();

if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
    // Insert default job types
    $default_types = [
        ['full-time', 'Standard full-time employment position'],
        ['part-time', 'Part-time employment position with reduced hours'],
        ['contract', 'Fixed-term contract position'],
        ['internship', 'Training position for students or recent graduates'],
        ['remote', 'Position that can be performed remotely']
    ];
    
    $insert_query = "INSERT INTO job_types (name, description) VALUES (:name, :description)";
    $insert_stmt = $db->prepare($insert_query);
    
    foreach ($default_types as $type) {
        $insert_stmt->bindParam(':name', $type[0]);
        $insert_stmt->bindParam(':description', $type[1]);
        $insert_stmt->execute();
    }
}

// Process actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new job type
    if (isset($_POST['add_job_type']) && !empty($_POST['name'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        
        // Check if job type already exists
        $check_query = "SELECT COUNT(*) as count FROM job_types WHERE name = :name";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':name', $name);
        $check_stmt->execute();
        
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            $_SESSION['message'] = "Job type '$name' already exists.";
            $_SESSION['message_type'] = "error";
        } else {
            $query = "INSERT INTO job_types (name, description) VALUES (:name, :description)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Job type added successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding job type.";
                $_SESSION['message_type'] = "error";
            }
        }
    }
    
    // Update job type
    elseif (isset($_POST['edit_job_type']) && isset($_POST['job_type_id'])) {
        $job_type_id = $_POST['job_type_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        
        // Check if job type name is being changed and already exists
        if (!empty($name)) {
            $check_query = "SELECT COUNT(*) as count FROM job_types WHERE name = :name AND job_type_id != :job_type_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':name', $name);
            $check_stmt->bindParam(':job_type_id', $job_type_id);
            $check_stmt->execute();
            
            if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $_SESSION['message'] = "Job type '$name' already exists.";
                $_SESSION['message_type'] = "error";
            } else {
                $query = "UPDATE job_types SET name = :name, description = :description WHERE job_type_id = :job_type_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':job_type_id', $job_type_id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Job type updated successfully.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error updating job type.";
                    $_SESSION['message_type'] = "error";
                }
            }
        }
    }
    
    // Delete job type
    elseif (isset($_POST['delete_job_type']) && isset($_POST['job_type_id'])) {
        $job_type_id = $_POST['job_type_id'];
        
        // Get the job type name to check in jobs table
        $name_query = "SELECT name FROM job_types WHERE job_type_id = :job_type_id";
        $name_stmt = $db->prepare($name_query);
        $name_stmt->bindParam(':job_type_id', $job_type_id);
        $name_stmt->execute();
        $job_type_name = $name_stmt->fetch(PDO::FETCH_ASSOC)['name'];
        
        // Check if job type is being used by jobs
        $check_query = "SELECT COUNT(*) as count FROM jobs WHERE job_type = :job_type";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':job_type', $job_type_name);
        $check_stmt->execute();
        
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            $_SESSION['message'] = "Cannot delete job type. It is being used by one or more jobs.";
            $_SESSION['message_type'] = "error";
        } else {
            $query = "DELETE FROM job_types WHERE job_type_id = :job_type_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':job_type_id', $job_type_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Job type deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting job type.";
                $_SESSION['message_type'] = "error";
            }
        }
    }
    
    // Redirect to refresh the page
    header("Location: " . SITE_URL . "/views/admin/manage-job-types.php");
    exit;
}

// Get all job types
$query = "SELECT * FROM job_types ORDER BY name";

$job_types = [];

try {
    $stmt = $db->prepare($query);
    $stmt->execute();
    $job_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = "Database error: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    // Ensure $job_types is still an array even if the query fails
    $job_types = [];
}

// Count jobs using each job type
$job_type_counts = [];
foreach ($job_types as $type) {
    $job_query = "SELECT COUNT(*) as count FROM jobs WHERE job_type = :job_type";
    $job_stmt = $db->prepare($job_query);
    $job_stmt->bindParam(':job_type', $type['name']);
    $job_stmt->execute();
    $job_type_counts[$type['job_type_id']] = $job_stmt->fetch(PDO::FETCH_ASSOC)['count'];
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
        .job-types-container {
            margin-bottom: 30px;
        }
        
        .job-types-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .add-button {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        
        .add-button:hover {
            background-color: #45a049;
        }
        
        .job-types-table {
            width: 100%;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .job-types-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .job-types-table th, 
        .job-types-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .job-types-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .job-types-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-edit, 
        .btn-delete {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        
        .btn-edit {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-delete {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .btn-edit:hover {
            background-color: #bbdefb;
        }
        
        .btn-delete:hover {
            background-color: #f5c6cb;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            padding-top: 15px;
            gap: 10px;
        }
        
        .btn-submit {
            background-color: #1976d2;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95rem;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95rem;
        }
        
        .btn-submit:hover {
            background-color: #1565c0;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        
        .job-count {
            display: inline-block;
            padding: 3px 8px;
            background-color: #f0f0f0;
            border-radius: 10px;
            font-size: 0.8rem;
            color: #666;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .job-type-tag {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .job-type-full-time {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .job-type-part-time {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .job-type-contract {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .job-type-internship {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .job-type-remote {
            background-color: #e0f7fa;
            color: #0097a7;
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
            
            <div class="job-types-container">
                <div class="job-types-header">
                    <div style="display: flex; align-items: center;">
                        <h2>Job Types</h2>
                        <a href="<?php echo SITE_URL; ?>/views/admin/settings.php?active_tab=jobs" class="back-btn" style="margin-left: 15px; padding: 6px 12px; background-color: #f0f0f0; color: #333; text-decoration: none; border-radius: 4px; font-size: 0.9rem;">
                            <span style="margin-right: 5px;">←</span> Back to Job Settings
                        </a>
                    </div>
                    <button class="add-button" id="openAddModal">Add New Job Type</button>
                </div>
                
                <div class="job-types-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Jobs</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($job_types as $type): ?>
                                <tr>
                                    <td>
                                        <span class="job-type-tag job-type-<?php echo htmlspecialchars($type['name']); ?>">
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($type['description'] ?? 'No description'); ?></td>
                                    <td>
                                        <span class="job-count"><?php echo $job_type_counts[$type['job_type_id']]; ?> jobs</span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-edit" 
                                                    onclick="openEditModal(<?php echo $type['job_type_id']; ?>, '<?php echo htmlspecialchars(addslashes($type['name'])); ?>', '<?php echo htmlspecialchars(addslashes($type['description'] ?? '')); ?>')">
                                                Edit
                                            </button>
                                            
                                            <?php if($job_type_counts[$type['job_type_id']] == 0): ?>
                                                <button class="btn-delete" 
                                                        onclick="confirmDelete(<?php echo $type['job_type_id']; ?>, '<?php echo htmlspecialchars(addslashes($type['name'])); ?>')">
                                                    Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Add Job Type Modal -->
            <div class="modal" id="addModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Add New Job Type</h3>
                        <button class="close-modal" onclick="closeModal('addModal')">&times;</button>
                    </div>
                    <form method="post">
                        <div class="form-group">
                            <label for="job-type-name">Job Type Name *</label>
                            <input type="text" class="form-control" id="job-type-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="job-type-description">Description</label>
                            <textarea class="form-control" id="job-type-description" name="description"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                            <button type="submit" name="add_job_type" class="btn-submit">Add Job Type</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Edit Job Type Modal -->
            <div class="modal" id="editModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Edit Job Type</h3>
                        <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
                    </div>
                    <form method="post" id="editForm">
                        <input type="hidden" name="job_type_id" id="edit-job-type-id">
                        <div class="form-group">
                            <label for="edit-job-type-name">Job Type Name *</label>
                            <input type="text" class="form-control" id="edit-job-type-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-job-type-description">Description</label>
                            <textarea class="form-control" id="edit-job-type-description" name="description"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                            <button type="submit" name="edit_job_type" class="btn-submit">Update Job Type</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Delete Job Type Form (Hidden) -->
            <form id="deleteForm" method="post" style="display: none;">
                <input type="hidden" name="job_type_id" id="delete-job-type-id">
                <input type="hidden" name="delete_job_type" value="1">
            </form>
        </div>
    </div>
    
    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        // Open add modal
        document.getElementById('openAddModal').addEventListener('click', function() {
            openModal('addModal');
        });
        
        // Edit job type
        function openEditModal(jobTypeId, name, description) {
            document.getElementById('edit-job-type-id').value = jobTypeId;
            document.getElementById('edit-job-type-name').value = name;
            document.getElementById('edit-job-type-description').value = description;
            openModal('editModal');
        }
        
        // Delete job type
        function confirmDelete(jobTypeId, name) {
            if (confirm(`Are you sure you want to delete the job type "${name}"?`)) {
                document.getElementById('delete-job-type-id').value = jobTypeId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });
    </script>
</body>
</html> 