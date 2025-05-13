<?php
// Set page title
$page_title = 'Manage Categories';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has admin role
if(!has_role('admin')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Process actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new category
    if (isset($_POST['add_category']) && !empty($_POST['name'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        
        // Check if category already exists
        $check_query = "SELECT COUNT(*) as count FROM job_categories WHERE name = :name";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':name', $name);
        $check_stmt->execute();
        
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            $_SESSION['message'] = "Category '$name' already exists.";
            $_SESSION['message_type'] = "error";
        } else {
            $query = "INSERT INTO job_categories (name, description) VALUES (:name, :description)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Category added successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding category.";
                $_SESSION['message_type'] = "error";
            }
        }
    }
    
    // Update category
    elseif (isset($_POST['edit_category']) && isset($_POST['category_id'])) {
        $category_id = $_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        
        // Check if category name is being changed and already exists
        if (!empty($name)) {
            $check_query = "SELECT COUNT(*) as count FROM job_categories WHERE name = :name AND category_id != :category_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':name', $name);
            $check_stmt->bindParam(':category_id', $category_id);
            $check_stmt->execute();
            
            if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $_SESSION['message'] = "Category '$name' already exists.";
                $_SESSION['message_type'] = "error";
            } else {
                $query = "UPDATE job_categories SET name = :name, description = :description WHERE category_id = :category_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':category_id', $category_id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Category updated successfully.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error updating category.";
                    $_SESSION['message_type'] = "error";
                }
            }
        }
    }
    
    // Delete category
    elseif (isset($_POST['delete_category']) && isset($_POST['category_id'])) {
        $category_id = $_POST['category_id'];
        
        // Check if category is being used by jobs
        $check_query = "SELECT COUNT(*) as count FROM jobs WHERE category = (SELECT name FROM job_categories WHERE category_id = :category_id)";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':category_id', $category_id);
        $check_stmt->execute();
        
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            $_SESSION['message'] = "Cannot delete category. It is being used by one or more jobs.";
            $_SESSION['message_type'] = "error";
        } else {
            $query = "DELETE FROM job_categories WHERE category_id = :category_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':category_id', $category_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Category deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting category.";
                $_SESSION['message_type'] = "error";
            }
        }
    }
    
    // Redirect to refresh the page
    header("Location: " . SITE_URL . "/views/admin/manage-categories.php");
    exit;
}

// Get all categories
$query = "SELECT * FROM job_categories ORDER BY name";

$categories = [];

try {
    $stmt = $db->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = "Database error: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    // Ensure $categories is still an array even if the query fails
    $categories = [];
}

// Count jobs using each category
$category_job_counts = [];
foreach ($categories as $category) {
    $job_query = "SELECT COUNT(*) as count FROM jobs WHERE category = :category";
    $job_stmt = $db->prepare($job_query);
    $job_stmt->bindParam(':category', $category['name']);
    $job_stmt->execute();
    $category_job_counts[$category['category_id']] = $job_stmt->fetch(PDO::FETCH_ASSOC)['count'];
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
        .categories-container {
            margin-bottom: 30px;
        }
        
        .categories-header {
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
        
        .categories-table {
            width: 100%;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .categories-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .categories-table th, 
        .categories-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .categories-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .categories-table tr:hover {
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
        
        .no-categories {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            
            <div class="categories-container">
                <div class="categories-header">
                    <div style="display: flex; align-items: center;">
                        <h2>Job Categories</h2>
                        <a href="<?php echo SITE_URL; ?>/views/admin/settings.php?active_tab=jobs" class="back-btn" style="margin-left: 15px; padding: 6px 12px; background-color: #f0f0f0; color: #333; text-decoration: none; border-radius: 4px; font-size: 0.9rem;">
                            <span style="margin-right: 5px;">←</span> Back to Job Settings
                        </a>
                    </div>
                    <button class="add-button" id="openAddModal">Add New Category</button>
                </div>
                
                <?php if(count($categories) > 0): ?>
                    <div class="categories-table">
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
                                <?php foreach($categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><?php echo htmlspecialchars($category['description'] ?? 'No description'); ?></td>
                                        <td>
                                            <span class="job-count"><?php echo $category_job_counts[$category['category_id']]; ?> jobs</span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button class="btn-edit" 
                                                        onclick="openEditModal(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>', '<?php echo htmlspecialchars(addslashes($category['description'] ?? '')); ?>')">
                                                    Edit
                                                </button>
                                                
                                                <?php if($category_job_counts[$category['category_id']] == 0): ?>
                                                    <button class="btn-delete" 
                                                            onclick="confirmDelete(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>')">
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
                <?php else: ?>
                    <div class="no-categories">
                        <h3>No Categories Found</h3>
                        <p>Click the "Add New Category" button to create your first category.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Add Category Modal -->
            <div class="modal" id="addModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Add New Category</h3>
                        <button class="close-modal" onclick="closeModal('addModal')">&times;</button>
                    </div>
                    <form method="post">
                        <div class="form-group">
                            <label for="category-name">Category Name *</label>
                            <input type="text" class="form-control" id="category-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="category-description">Description</label>
                            <textarea class="form-control" id="category-description" name="description"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                            <button type="submit" name="add_category" class="btn-submit">Add Category</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Edit Category Modal -->
            <div class="modal" id="editModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Edit Category</h3>
                        <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
                    </div>
                    <form method="post" id="editForm">
                        <input type="hidden" name="category_id" id="edit-category-id">
                        <div class="form-group">
                            <label for="edit-category-name">Category Name *</label>
                            <input type="text" class="form-control" id="edit-category-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-category-description">Description</label>
                            <textarea class="form-control" id="edit-category-description" name="description"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                            <button type="submit" name="edit_category" class="btn-submit">Update Category</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Delete Category Form (Hidden) -->
            <form id="deleteForm" method="post" style="display: none;">
                <input type="hidden" name="category_id" id="delete-category-id">
                <input type="hidden" name="delete_category" value="1">
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
        
        // Edit category
        function openEditModal(categoryId, name, description) {
            document.getElementById('edit-category-id').value = categoryId;
            document.getElementById('edit-category-name').value = name;
            document.getElementById('edit-category-description').value = description;
            openModal('editModal');
        }
        
        // Delete category
        function confirmDelete(categoryId, name) {
            if (confirm(`Are you sure you want to delete the category "${name}"?`)) {
                document.getElementById('delete-category-id').value = categoryId;
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