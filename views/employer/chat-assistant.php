<?php
// Set page title
$page_title = 'Chat Assistant';

// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user has employer role
if(!has_role('employer')) {
    redirect(SITE_URL . '/views/auth/login.php', 'You do not have permission to access this page.', 'error');
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get employer ID
$query = "SELECT e.employer_id, e.verified, e.company_name, u.first_name, u.last_name 
          FROM employer_profiles e
          JOIN users u ON e.user_id = u.user_id
          WHERE e.user_id = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $_SESSION['user_id']);
$stmt->execute();
$employer = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$employer) {
    redirect(SITE_URL . '/views/auth/logout.php', 'Employer profile not found.', 'error');
}

$employer_id = $employer['employer_id'];
$is_verified = $employer['verified'] == 1;

// Process admin query submission
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_query'])) {
    $query_text = trim($_POST['query_text']);
    $query_type = trim($_POST['query_type']);
    
    if(!empty($query_text) && !empty($query_type)) {
        $query = "INSERT INTO admin_queries (user_id, query_type, query_text, status, created_at) 
                 VALUES (?, ?, ?, 'pending', NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $_SESSION['user_id']);
        $stmt->bindParam(2, $query_type);
        $stmt->bindParam(3, $query_text);
        
        if($stmt->execute()) {
            $success = "Your query has been submitted to the admin. We'll get back to you soon.";
        } else {
            $error = "Error submitting your query. Please try again.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Handle chat message submission
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_message'])) {
    $message = trim($_POST['message']);
    if(!empty($message)) {
        // Store the message
        $query = "INSERT INTO chat_messages (user_id, message, is_assistant, created_at) 
                 VALUES (?, ?, 0, NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $_SESSION['user_id']);
        $stmt->bindParam(2, $message);
        $stmt->execute();
        
        // Generate assistant response based on keywords
        $response = generateAssistantResponse($message);
        
        // Store assistant response
        $query = "INSERT INTO chat_messages (user_id, message, is_assistant, created_at) 
                 VALUES (?, ?, 1, NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $_SESSION['user_id']);
        $stmt->bindParam(2, $response);
        $stmt->execute();
        
        // Return JSON response for AJAX
        if(isset($_POST['ajax'])) {
            echo json_encode(['success' => true, 'response' => $response]);
            exit;
        }
    }
}

// Get chat history
$query = "SELECT message, is_assistant, created_at 
          FROM chat_messages 
          WHERE user_id = ? 
          ORDER BY created_at ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $_SESSION['user_id']);
$stmt->execute();
$chat_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to generate basic responses based on keywords
function generateAssistantResponse($message) {
    $message = strtolower($message);
    
    if(strpos($message, 'post job') !== false || strpos($message, 'create job') !== false) {
        return "To post a new job, click on 'Post a Job' in the sidebar menu. You'll need to fill in details like job title, description, requirements, and other relevant information.";
    }
    
    if(strpos($message, 'application') !== false || strpos($message, 'applicant') !== false) {
        return "You can view and manage all job applications in the 'Applications' section. There you can review candidates, update application status, and contact applicants.";
    }
    
    if(strpos($message, 'category') !== false) {
        return "To request a new job category, please use the 'Submit Query to Admin' form on the right and select 'Add New Job Category' as the query type.";
    }
    
    if(strpos($message, 'profile') !== false || strpos($message, 'company') !== false) {
        return "You can update your company profile by clicking on 'Company Profile' in the sidebar. Make sure to keep your information up to date!";
    }
    
    if(strpos($message, 'verify') !== false || strpos($message, 'verification') !== false) {
        return "Company verification is handled by our admin team. If your company is not verified yet, please submit a verification request using the 'Account Support' query type.";
    }
    
    return "I understand your message. For specific assistance, you can submit a query to our admin team using the form on the right. They'll be happy to help!";
}

// Get previous queries
$query = "SELECT query_type, query_text, status, response, created_at, responded_at 
          FROM admin_queries 
          WHERE user_id = ? 
          ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $_SESSION['user_id']);
$stmt->execute();
$previous_queries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        /* Chat Assistant Styles */
        .chat-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .chat-main {
            flex: 1;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .chat-sidebar {
            width: 300px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .chat-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        
        .chat-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #333;
        }
        
        .chat-messages {
            padding: 20px;
            height: 400px;
            overflow-y: auto;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }
        
        .message-content {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.95rem;
        }
        
        .user-message {
            justify-content: flex-end;
        }
        
        .user-message .message-content {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .assistant-message .message-content {
            background: #f5f5f5;
            color: #333;
        }
        
        .chat-input {
            padding: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }
        
        .chat-input input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        
        .chat-input button {
            padding: 12px 20px;
            background: #0056b3;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .chat-input button:hover {
            background: #004494;
        }
        
        .query-form {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #0056b3;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            transition: background 0.2s;
        }
        
        .btn-submit:hover {
            background: #004494;
        }
        
        .previous-queries {
            margin-top: 20px;
        }
        
        .query-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .query-item:last-child {
            border-bottom: none;
        }
        
        .query-type {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }
        
        .query-text {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }
        
        .query-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .status-resolved {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .query-response {
            margin-top: 10px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #333;
        }
        
        .query-meta {
            font-size: 0.85rem;
            color: #888;
            margin-top: 5px;
        }
        
        .message-alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .alert-error {
            background: #fbe9e7;
            color: #d32f2f;
        }
    </style>
</head>
<body>
    <div class="employer-container">
        <?php include 'employer-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>Chat Assistant</h1>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($employer['first_name'] . ' ' . $employer['last_name']); ?>
                    </div>
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($employer['company_name']); ?></span>
                        <?php if($is_verified): ?>
                            <span class="verification-badge">
                                <span class="icon">✓</span> Verified
                            </span>
                        <?php else: ?>
                            <span class="verification-badge pending-verification">
                                <span class="icon">⌛</span> Pending Verification
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="message-alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="message-alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="chat-container">
                <div class="chat-main">
                    <div class="chat-header">
                        <h3>Chat with Assistant</h3>
                    </div>
                    
                    <div class="chat-messages" id="chatMessages">
                        <?php if(empty($chat_history)): ?>
                            <div class="message assistant-message">
                                <div class="message-content">
                                    Hello! I'm your ShaSha assistant. How can I help you today? You can ask me questions about:
                                    <ul>
                                        <li>Posting and managing jobs</li>
                                        <li>Handling applications</li>
                                        <li>Company verification</li>
                                        <li>Profile management</li>
                                        <li>Or submit queries to admin</li>
                                    </ul>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach($chat_history as $chat): ?>
                                <div class="message <?php echo $chat['is_assistant'] ? 'assistant' : 'user'; ?>-message">
                                    <div class="message-content">
                                        <?php echo htmlspecialchars($chat['message']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="chat-input">
                        <input type="text" id="messageInput" placeholder="Type your message here...">
                        <button onclick="sendMessage()">Send</button>
                    </div>
                </div>
                
                <div class="chat-sidebar">
                    <div class="chat-header">
                        <h3>Submit Query to Admin</h3>
                    </div>
                    
                    <form method="post" class="query-form">
                        <div class="form-group">
                            <label for="query_type">Query Type</label>
                            <select id="query_type" name="query_type" required>
                                <option value="">Select query type</option>
                                <option value="new_category">Add New Job Category</option>
                                <option value="technical_issue">Technical Issue</option>
                                <option value="account_support">Account Support</option>
                                <option value="feature_request">Feature Request</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="query_text">Your Query</label>
                            <textarea id="query_text" name="query_text" required placeholder="Describe your query in detail..."></textarea>
                        </div>
                        
                        <button type="submit" name="admin_query" class="btn-submit">Submit Query</button>
                    </form>
                    
                    <?php if(count($previous_queries) > 0): ?>
                        <div class="previous-queries">
                            <div class="chat-header">
                                <h3>Previous Queries</h3>
                            </div>
                            
                            <?php foreach($previous_queries as $query): ?>
                                <div class="query-item">
                                    <div class="query-type"><?php echo ucwords(str_replace('_', ' ', $query['query_type'])); ?></div>
                                    <div class="query-text"><?php echo htmlspecialchars($query['query_text']); ?></div>
                                    <span class="query-status status-<?php echo $query['status']; ?>">
                                        <?php echo ucfirst($query['status']); ?>
                                    </span>
                                    <?php if($query['response']): ?>
                                        <div class="query-response">
                                            <?php echo htmlspecialchars($query['response']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="query-meta">
                                        Submitted: <?php echo date('M d, Y', strtotime($query['created_at'])); ?>
                                        <?php if($query['responded_at']): ?>
                                            <br>Responded: <?php echo date('M d, Y', strtotime($query['responded_at'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if(message) {
                // Add user message to UI
                addMessage(message, 'user');
                
                // Clear input
                input.value = '';
                
                // Send message to server
                fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'chat_message=1&message=' + encodeURIComponent(message) + '&ajax=1'
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        addMessage(data.response, 'assistant');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    addMessage('Sorry, there was an error processing your message. Please try again.', 'assistant');
                });
            }
        }
        
        function addMessage(text, sender) {
            const messagesDiv = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}-message`;
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.textContent = text;
            
            messageDiv.appendChild(contentDiv);
            messagesDiv.appendChild(messageDiv);
            
            // Scroll to bottom
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
        
        // Allow sending message with Enter key
        document.getElementById('messageInput').addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                sendMessage();
            }
        });
    </script>
</body>
</html> 