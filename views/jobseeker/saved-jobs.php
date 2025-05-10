<?php
// Set page title
$page_title = 'Saved Jobs';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has jobseeker role
if(!has_role('jobseeker')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

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

// Handle save/unsave job action
if(isset($_GET['action']) && isset($_GET['job_id']) && is_numeric($_GET['job_id'])) {
    $job_id = (int)$_GET['job_id'];
    $action = $_GET['action'];
    
    if($action === 'remove') {
        // Delete the saved job
        $delete_query = "DELETE FROM saved_jobs WHERE jobseeker_id = ? AND job_id = ?";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(1, $jobseeker_id);
        $delete_stmt->bindParam(2, $job_id);
        
        if($delete_stmt->execute()) {
            $_SESSION['message'] = "Job removed from saved jobs.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Failed to remove job.";
            $_SESSION['message_type'] = "error";
        }
    }
    
    // Redirect to remove the parameter from URL
    redirect(SITE_URL . '/views/jobseeker/saved-jobs.php', null, null);
}

// Set up pagination
$jobs_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $jobs_per_page;

// Count total saved jobs
$count_query = "SELECT COUNT(*) as total FROM saved_jobs WHERE jobseeker_id = ?";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(1, $jobseeker_id);
$stmt->execute();
$total_saved_jobs = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate total pages
$total_pages = ceil($total_saved_jobs / $jobs_per_page);

// Get saved jobs with job and employer details
$query = "SELECT j.*, e.company_name, e.company_logo, sj.saved_at
          FROM saved_jobs sj
          JOIN jobs j ON sj.job_id = j.job_id
          JOIN employer_profiles e ON j.employer_id = e.employer_id
          WHERE sj.jobseeker_id = ?
          ORDER BY sj.saved_at DESC
          LIMIT $offset, $jobs_per_page";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $jobseeker_id);
$stmt->execute();
$saved_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if already applied to jobs
$applied_jobs = [];
if(!empty($saved_jobs)) {
    $job_ids = array_column($saved_jobs, 'job_id');
    $placeholders = implode(',', array_fill(0, count($job_ids), '?'));
    
    $application_query = "SELECT job_id FROM applications WHERE jobseeker_id = ? AND job_id IN ($placeholders)";
    $application_stmt = $db->prepare($application_query);
    
    // Bind jobseeker_id as the first parameter
    $application_stmt->bindParam(1, $jobseeker_id);
    
    // Bind job_ids starting from parameter 2
    foreach($job_ids as $key => $job_id) {
        $application_stmt->bindParam($key + 2, $job_ids[$key]);
    }
    
    $application_stmt->execute();
    $applied_jobs = $application_stmt->fetchAll(PDO::FETCH_COLUMN);
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
        /* Saved Jobs Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        
        .jobseeker-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            background-color: #343a40;
            color: white;
            padding: 20px 0;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #495057;
            margin-bottom: 20px;
        }
        
        .sidebar-header h3 {
            color: white;
            font-size: 1.3rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: #ced4da;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #495057;
            color: white;
            border-left-color: #0056b3;
        }
        
        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .saved-count {
            background-color: #e3f2fd;
            color: #0056b3;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .job-list {
            margin-bottom: 30px;
        }
        
        .job-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .company-logo {
            width: 70px;
            height: 70px;
            background-color: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-right: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .company-logo img {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
        }
        
        .company-logo span {
            font-size: 1.8rem;
            font-weight: bold;
            color: #0056b3;
        }
        
        .job-details {
            flex: 1;
        }
        
        .job-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .job-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .job-title a {
            color: #0056b3;
            text-decoration: none;
        }
        
        .job-title a:hover {
            text-decoration: underline;
        }
        
        .job-status {
            display: flex;
            align-items: center;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .status-closed {
            background-color: #f5f5f5;
            color: #757575;
        }
        
        .company-name {
            color: #666;
            margin-bottom: 10px;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .job-meta span {
            display: flex;
            align-items: center;
        }
        
        .job-meta span i {
            margin-right: 5px;
            font-size: 1.1rem;
        }
        
        .job-description {
            margin-bottom: 15px;
            font-size: 0.95rem;
            color: #333;
            line-height: 1.5;
        }
        
        .job-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .job-tag {
            background-color: #f0f5ff;
            color: #0056b3;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .job-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: #0056b3;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #004494;
            color: white;
            text-decoration: none;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #0056b3;
            color: #0056b3;
        }
        
        .btn-outline:hover {
            background-color: #f0f5ff;
            text-decoration: none;
        }
        
        .btn-danger {
            background-color: transparent;
            border: 1px solid #dc3545;
            color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #dc3545;
            color: white;
            text-decoration: none;
        }
        
        .btn-disabled {
            background-color: #6c757d;
            color: white;
            cursor: not-allowed;
        }
        
        .applied-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
        }
        
        .applied-badge i {
            margin-right: 5px;
        }
        
        .saved-date {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 10px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 4px;
            color: #0056b3;
            text-decoration: none;
        }
        
        .pagination a {
            background-color: white;
            border: 1px solid #dee2e6;
        }
        
        .pagination a:hover {
            background-color: #f0f5ff;
        }
        
        .pagination span {
            background-color: #0056b3;
            color: white;
            border: 1px solid #0056b3;
        }
        
        .no-jobs {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .no-jobs h3 {
            margin-bottom: 10px;
        }
        
        .no-jobs p {
            color: #666;
            margin-bottom: 20px;
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
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            text-align: center;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .jobseeker-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .job-card {
                flex-direction: column;
            }
            
            .company-logo {
                margin-bottom: 15px;
            }
            
            .job-header {
                flex-direction: column;
            }
            
            .job-status {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="jobseeker-container">
        <?php include __DIR__ . '/jobseeker-sidebar.php'; ?>
        <div class="main-content">
            <div class="top-bar">
                <h1>Saved Jobs</h1>
                <div>
                    <span class="saved-count"><?php echo $total_saved_jobs; ?> Jobs Saved</span>
                </div>
            </div>
            
            <?php if(isset($_SESSION['message'])): ?>
                <div class="message <?php echo $_SESSION['message_type']; ?>">
                    <?php 
                        echo $_SESSION['message']; 
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="job-list">
                <?php if(count($saved_jobs) > 0): ?>
                    <?php foreach($saved_jobs as $job): ?>
                        <div class="job-card">
                            <div class="company-logo">
                                <?php if(!empty($job['company_logo'])): ?>
                                    <img src="<?php echo SITE_URL . '/' . $job['company_logo']; ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?> Logo">
                                <?php else: ?>
                                    <span><?php echo strtoupper(substr($job['company_name'], 0, 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="job-details">
                                <div class="job-header">
                                    <div>
                                        <div class="job-title">
                                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $job['job_id']; ?>">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </a>
                                        </div>
                                        <div class="company-name">
                                            <?php echo htmlspecialchars($job['company_name']); ?>
                                        </div>
                                    </div>
                                    <div class="job-status">
                                        <span class="status-badge status-<?php echo $job['status']; ?>">
                                            <?php echo ucfirst($job['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="job-meta">
                                    <span><i>📍</i> <?php echo htmlspecialchars($job['location']); ?></span>
                                    <span><i>💼</i> <?php echo ucfirst($job['job_type']); ?></span>
                                    <?php if(!empty($job['salary_range'])): ?>
                                        <span><i>💰</i> <?php echo htmlspecialchars($job['salary_range']); ?></span>
                                    <?php endif; ?>
                                    <span><i>📅</i> Posted: <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
                                </div>
                                
                                <div class="job-description">
                                    <?php 
                                        $description = strip_tags($job['description']);
                                        echo strlen($description) > 200 ? substr($description, 0, 200) . '...' : $description;
                                    ?>
                                </div>
                                
                                <?php if(!empty($job['skills_required'])): ?>
                                    <div class="job-tags">
                                        <?php 
                                            $skills = explode(',', $job['skills_required']);
                                            foreach($skills as $skill): 
                                                $skill = trim($skill);
                                                if(!empty($skill)):
                                        ?>
                                            <span class="job-tag"><?php echo htmlspecialchars($skill); ?></span>
                                        <?php 
                                                endif;
                                            endforeach; 
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="saved-date">
                                    Saved on <?php echo date('M d, Y', strtotime($job['saved_at'])); ?>
                                </div>
                                
                                <div class="job-actions">
                                    <?php if(in_array($job['job_id'], $applied_jobs)): ?>
                                        <span class="applied-badge"><i>✓</i> Already Applied</span>
                                    <?php endif; ?>
                                    
                                    <?php if($job['status'] == 'active'): ?>
                                        <?php if(!in_array($job['job_id'], $applied_jobs)): ?>
                                            <a href="<?php echo SITE_URL; ?>/views/jobseeker/apply-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-primary">Apply Now</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-disabled" disabled>Job Closed</button>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/view-job.php?id=<?php echo $job['job_id']; ?>" class="btn btn-outline">View Details</a>
                                    <a href="<?php echo SITE_URL; ?>/views/jobseeker/saved-jobs.php?action=remove&job_id=<?php echo $job['job_id']; ?>" class="btn btn-danger remove-job">Remove</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?>">&laquo; Previous</a>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if($i == $current_page): ?>
                                    <span><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📌</div>
                        <h3>No Saved Jobs Yet</h3>
                        <p>Save jobs you're interested in to revisit them later.</p>
                        <a href="<?php echo SITE_URL; ?>/views/jobseeker/search-jobs.php" class="btn btn-primary">Explore Jobs</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Chatbot Container -->
    <div class="chatbot-container">
        <div class="chatbot-icon" id="chatbot-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
        </div>
        <div class="chatbot-box" id="chatbot-box">
            <div class="chatbot-header">
                <h3>ShaSha Assistant</h3>
                <button id="close-chat">×</button>
            </div>
            <div class="chatbot-messages" id="chatbot-messages">
                <div class="message bot-message">
                    <div class="message-content">
                        Hi there! I'm ShaSha's assistant. How can I help you today?
                    </div>
                </div>
            </div>
            <div class="chatbot-input">
                <input type="text" id="user-input" placeholder="Type your message here...">
                <button id="send-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add confirmation for remove action
            const removeButtons = document.querySelectorAll('.remove-job');
            if(removeButtons) {
                removeButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        if(!confirm('Are you sure you want to remove this job from your saved jobs?')) {
                            e.preventDefault();
                        }
                    });
                });
            }
        });
        // Chatbot logic (same as home page)
        document.addEventListener('DOMContentLoaded', function() {
            const chatbotIcon = document.getElementById('chatbot-icon');
            const chatbotBox = document.getElementById('chatbot-box');
            const closeChat = document.getElementById('close-chat');
            const userInput = document.getElementById('user-input');
            const sendMessage = document.getElementById('send-message');
            const chatMessages = document.getElementById('chatbot-messages');
            chatbotIcon.addEventListener('click', function() {
                chatbotBox.style.display = 'flex';
                userInput.focus();
            });
            closeChat.addEventListener('click', function() {
                chatbotBox.style.display = 'none';
            });
            function sendUserMessage() {
                const message = userInput.value.trim();
                if (message) {
                    addMessage(message, 'user');
                    userInput.value = '';
                    setTimeout(() => {
                        const response = getBotResponse(message);
                        addMessage(response, 'bot');
                    }, 600);
                }
            }
            sendMessage.addEventListener('click', sendUserMessage);
            userInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendUserMessage();
                }
            });
            function addMessage(text, sender) {
                const messageDiv = document.createElement('div');
                messageDiv.classList.add('message');
                messageDiv.classList.add(sender + '-message');
                const contentDiv = document.createElement('div');
                contentDiv.classList.add('message-content');
                contentDiv.textContent = text;
                messageDiv.appendChild(contentDiv);
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            function getBotResponse(message) {
                message = message.toLowerCase();
                if (message.includes('hello') || message.includes('hi') || message.includes('hey')) {
                    return "Hello! How can I help you with ShaSha today?";
                } else if (message.includes('profile') || message.includes('update')) {
                    return "To update your profile, click 'My Profile' in the sidebar.";
                } else if (message.includes('job') && (message.includes('find') || message.includes('search') || message.includes('look'))) {
                    return "To search for jobs, click 'Search Jobs' in the sidebar. You can filter by category, location, and more.";
                } else if (message.includes('application') || message.includes('applied')) {
                    return "To view your job applications, click 'My Applications' in the sidebar.";
                } else if (message.includes('logout')) {
                    return "To logout, click the 'Logout' button in the sidebar. You'll be asked to confirm before logging out.";
                } else if (message.includes('thank')) {
                    return "You're welcome! Is there anything else I can help you with?";
                } else {
                    return "I'm here to help! For specific questions, try using the sidebar or contact support if you need more assistance.";
                }
            }
        });
    </script>
</body>
</html>