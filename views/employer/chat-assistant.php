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
        /* Modern Chat Assistant Styles */
        .chat-container {
            display: flex;
            gap: 24px;
            margin: 20px 0;
            height: calc(100vh - 180px);
            min-height: 500px;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .chat-sidebar {
            width: 320px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            height: fit-content;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .chat-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #f6f8fc 0%, #f1f4f9 100%);
        }
        
        .chat-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #1a3b5d;
            font-weight: 600;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            background: #fff;
            min-height: 300px;
        }
        
        .message {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            opacity: 0;
            transform: translateY(20px);
            animation: messageAppear 0.3s ease forwards;
        }

        @keyframes messageAppear {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-content {
            max-width: 75%;
            padding: 16px 20px;
            border-radius: 16px;
            font-size: 0.95rem;
            line-height: 1.5;
            position: relative;
            transition: transform 0.2s;
        }

        .message-content:hover {
            transform: translateY(-2px);
        }
        
        .user-message {
            justify-content: flex-end;
        }
        
        .user-message .message-content {
            background: linear-gradient(135deg, #0056b3 0%, #007bff 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .assistant-message .message-content {
            background: linear-gradient(135deg, #f6f8fc 0%, #f1f4f9 100%);
            color: #1a3b5d;
            border-bottom-left-radius: 4px;
        }

        .assistant-message .message-content ul {
            margin: 10px 0 5px;
            padding-left: 20px;
        }

        .assistant-message .message-content li {
            margin: 5px 0;
            color: #2c5282;
        }
        
        .chat-input {
            padding: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 12px;
            background: #fff;
            position: relative;
        }
        
        .chat-input input {
            flex: 1;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .chat-input input:focus {
            outline: none;
            border-color: #0056b3;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(0,86,179,0.1);
        }
        
        .chat-input button {
            padding: 14px 24px;
            background: linear-gradient(135deg, #0056b3 0%, #007bff 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
            min-width: 100px;
        }
        
        .chat-input button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,86,179,0.2);
        }

        .chat-input button:active {
            transform: translateY(0);
        }
        
        .query-form {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1a3b5d;
            font-size: 0.9rem;
        }
        
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0056b3;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(0,86,179,0.1);
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #0056b3 0%, #007bff 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,86,179,0.2);
        }

        .btn-submit:active {
            transform: translateY(0);
        }
        
        .previous-queries {
            margin-top: 20px;
            padding: 0 24px 24px;
        }
        
        .query-item {
            padding: 16px;
            border-radius: 12px;
            background: #f8fafc;
            margin-bottom: 12px;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }

        .query-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .query-item:last-child {
            margin-bottom: 0;
        }
        
        .query-type {
            font-weight: 600;
            color: #1a3b5d;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .query-text {
            font-size: 0.9rem;
            color: #4a5568;
            margin-bottom: 12px;
            line-height: 1.5;
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
        
        .status-resolved {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .query-response {
            margin-top: 12px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #2d3748;
            border: 1px solid #e2e8f0;
            line-height: 1.5;
        }
        
        .query-meta {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 8px;
        }

        /* Custom scrollbar */
        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #f1f4f9;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }

        /* Message alert styles */
        .message-alert {
            padding: 16px;
            margin-bottom: 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: alertAppear 0.3s ease;
        }

        @keyframes alertAppear {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #388e3c;
            border: 1px solid #c8e6c9;
        }
        
        .alert-error {
            background: #fbe9e7;
            color: #d32f2f;
            border: 1px solid #ffccbc;
        }

        /* Main content layout fixes */
        .employer-container {
            display: flex;
            min-height: 100vh;
            background: #f8fafc;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            background: #f8fafc;
            overflow-y: auto;
            width: calc(100% - 250px);
            transition: all 0.3s ease;
        }

        /* Top bar styles to match dashboard */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 25px 30px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            color: white;
        }

        .top-bar h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #ffffff;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            font-size: 1.1rem;
            color: #ffffff;
            font-weight: 500;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .company-name {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.85);
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            background-color: rgba(232, 245, 233, 0.9);
            color: #388e3c;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 15px;
            backdrop-filter: blur(4px);
        }

        .verification-badge .icon {
            margin-right: 5px;
        }

        .pending-verification {
            background-color: rgba(255, 248, 225, 0.9);
            color: #f57c00;
        }

        /* Chat container adjustment */
        .chat-container {
            margin-top: 0; /* Remove top margin since header has margin-bottom */
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
                                <span class="icon">⏱</span> Pending Verification
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