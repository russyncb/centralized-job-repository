<?php
// Set page title
$page_title = 'AI Chat Assistant';

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
    $message_text = trim($_POST['query_text']);
    $query_type = trim($_POST['query_type']);
    
    if(!empty($message_text) && !empty($query_type)) {
        $query = "INSERT INTO admin_queries (user_id, subject, message, status, created_at) 
                 VALUES (?, ?, ?, 'pending', NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $_SESSION['user_id']);
        $stmt->bindParam(2, $query_type);
        $stmt->bindParam(3, $message_text);
        
        if($stmt->execute()) {
            $success = "Your query has been submitted to the admin. We'll get back to you soon.";
        } else {
            $error = "Error submitting your query. Please try again.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Get previous queries
$query = "SELECT subject, message, status, admin_notes, created_at, updated_at 
          FROM admin_queries 
          WHERE user_id = ? 
          ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $_SESSION['user_id']);
$stmt->execute();
$previous_queries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employer stats for AI context
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM jobs WHERE employer_id = ?) as total_jobs,
    (SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND status = 'active') as active_jobs,
    (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id = j.job_id WHERE j.employer_id = ?) as total_applications,
    (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id = j.job_id WHERE j.employer_id = ? AND a.status = 'pending') as pending_applications";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(1, $employer_id);
$stats_stmt->bindParam(2, $employer_id);
$stats_stmt->bindParam(3, $employer_id);
$stats_stmt->bindParam(4, $employer_id);
$stats_stmt->execute();
$employer_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/chatbot.css">
    <style>
        /* Advanced Chat Assistant Styles */
        body {
            background: linear-gradient(135deg, #f6f8fc 0%, #f1f4f9 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .employer-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            width: calc(100% - 250px);
            transition: all 0.3s ease;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 25px 30px;
            background: linear-gradient(135deg, #1a3b5d 0%, #1557b0 100%);
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(26, 59, 93, 0.15);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .top-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .top-bar h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #ffffff;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .ai-badge {
            background: linear-gradient(135deg, #ff6b6b, #feca57, #48dbfb, #ff9ff3);
            background-size: 300% 300%;
            animation: gradientShift 3s ease infinite;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .company-name {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            color: #fff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .verification-badge .icon {
            margin-right: 5px;
        }

        .pending-verification {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border-color: rgba(255, 193, 7, 0.3);
        }

        .chat-assistant-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            height: calc(100vh - 200px);
            min-height: 600px;
        }

        .ai-chat-section {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .ai-chat-header {
            padding: 25px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            overflow: hidden;
        }

        .ai-chat-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            animation: headerShimmer 4s infinite;
        }

        @keyframes headerShimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .ai-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #ff6b6b, #feca57);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            animation: pulse 2s infinite;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            position: relative;
            z-index: 1;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .ai-info h3 {
            margin: 0 0 5px;
            font-size: 1.3rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .ai-status {
            font-size: 0.9rem;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            animation: blink 2s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .ai-chat-messages {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: linear-gradient(135deg, #fafbfc 0%, #f8f9fa 100%);
            position: relative;
        }

        .ai-chat-messages::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: linear-gradient(to bottom, rgba(255,255,255,0.8), transparent);
            pointer-events: none;
            z-index: 1;
        }

        .ai-message {
            margin-bottom: 25px;
            opacity: 0;
            animation: messageAppear 0.5s ease forwards;
        }

        @keyframes messageAppear {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .ai-message-content {
            background: white;
            padding: 20px 25px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            margin-left: 60px;
            transition: all 0.3s ease;
        }

        .ai-message-content:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .ai-message-content::before {
            content: '';
            position: absolute;
            left: -15px;
            top: 20px;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 10px 15px 10px 0;
            border-color: transparent white transparent transparent;
        }

        .user-message {
            text-align: right;
        }

        .user-message .ai-message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-left: 0;
            margin-right: 60px;
        }

        .user-message .ai-message-content::before {
            left: auto;
            right: -15px;
            border-width: 10px 0 10px 15px;
            border-color: transparent transparent transparent #667eea;
        }

        .ai-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .suggestion-chip {
            background: linear-gradient(135deg, #e3f2fd 0%, #f0f8ff 100%);
            color: #1976d2;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid rgba(25, 118, 210, 0.2);
        }

        .suggestion-chip:hover {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
        }

        .ai-chat-input {
            padding: 25px 30px;
            background: white;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .ai-input-wrapper {
            flex: 1;
            position: relative;
        }

        .ai-chat-input input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
            box-sizing: border-box;
        }

        .ai-chat-input input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .ai-send-btn {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .ai-send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
        }

        .ai-send-btn:active {
            transform: scale(0.95);
        }

        .admin-panel {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
            height: fit-content;
        }

        .admin-panel-header {
            padding: 25px 30px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            text-align: center;
        }

        .admin-panel-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .query-form {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #1a3b5d;
            font-size: 0.95rem;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
            box-sizing: border-box;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f5576c;
            background: white;
            box-shadow: 0 0 0 4px rgba(245, 87, 108, 0.1);
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 87, 108, 0.3);
        }

        .previous-queries {
            margin-top: 30px;
            padding: 0 30px 30px;
        }

        .queries-header {
            padding: 20px 0;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
        }

        .queries-header h4 {
            margin: 0;
            font-size: 1.1rem;
            color: #1a3b5d;
            font-weight: 600;
        }

        .query-item {
            padding: 20px;
            border-radius: 12px;
            background: linear-gradient(135deg, #fafbfc 0%, #f8f9fa 100%);
            margin-bottom: 15px;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .query-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .query-type {
            font-weight: 600;
            color: #1a3b5d;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .query-text {
            font-size: 0.9rem;
            color: #4a5568;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .query-status {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: linear-gradient(135deg, #fff3e0, #ffecb5);
            color: #ef6c00;
        }

        .status-resolved {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #388e3c;
        }

        .query-response {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            font-size: 0.9rem;
            color: #2d3748;
            border: 1px solid #e9ecef;
            line-height: 1.5;
        }

        .query-meta {
            font-size: 0.8rem;
            color: #718096;
            margin-top: 10px;
        }

        .message-alert {
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: alertSlide 0.5s ease;
        }

        @keyframes alertSlide {
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
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #388e3c;
            border: 1px solid #a5d6a7;
        }

        .alert-error {
            background: linear-gradient(135deg, #fbe9e7, #ffccbc);
            color: #d32f2f;
            border: 1px solid #ffab91;
        }

        /* Custom scrollbar */
        .ai-chat-messages::-webkit-scrollbar {
            width: 8px;
        }

        .ai-chat-messages::-webkit-scrollbar-track {
            background: #f1f4f9;
            border-radius: 4px;
        }

        .ai-chat-messages::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #cbd5e0, #a0aec0);
            border-radius: 4px;
        }

        .ai-chat-messages::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #a0aec0, #718096);
        }

        /* Responsive design */
        @media (max-width: 1200px) {
            .chat-assistant-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .admin-panel {
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .top-bar {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .ai-chat-header {
                padding: 20px;
            }

            .ai-chat-messages {
                padding: 20px;
            }

            .ai-chat-input {
                padding: 20px;
            }

            .query-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="employer-container">
        <?php include 'employer-sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div>
                    <h1>AI Chat Assistant <span class="ai-badge">🚀 LIT</span></h1>
                </div>
                <div class="user-info">
                    <div class="company-info">
                        <span class="company-name"><?php echo htmlspecialchars($employer['company_name']); ?></span>
                        <?php if($is_verified): ?>
                            <span class="verification-badge">
                                <span class="icon">✓</span> Verified
                            </span>
                        <?php else: ?>
                            <span class="verification-badge pending-verification">
                                <span class="icon">⏱</span> Pending
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="message-alert alert-success">
                    <span style="font-size: 1.2rem;">✅</span>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="message-alert alert-error">
                    <span style="font-size: 1.2rem;">❌</span>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <div class="chat-assistant-container">
                <!-- Advanced AI Chat Section -->
                <div class="ai-chat-section">
                    <div class="ai-chat-header">
                        <div class="ai-avatar">🤖</div>
                        <div class="ai-info">
                            <h3>ShaSha AI Assistant</h3>
                            <div class="ai-status">
                                <div class="status-dot"></div>
                                Online & Super Smart
                            </div>
                        </div>
                    </div>
                    
                    <div class="ai-chat-messages" id="aiChatMessages">
                        <div class="ai-message">
                            <div class="ai-message-content">
                                🎉 <strong>Welcome to the Advanced AI Assistant!</strong><br><br>
                                I'm your intelligent recruitment companion. Here's what I can help you with:
                                
                                <div class="ai-suggestions">
                                    <div class="suggestion-chip" onclick="sendPredefinedMessage('Show my recruitment analytics')">📊 Analytics Dashboard</div>
                                    <div class="suggestion-chip" onclick="sendPredefinedMessage('Help me optimize job posting')">🎯 Optimize Job Posts</div>
                                    <div class="suggestion-chip" onclick="sendPredefinedMessage('Review my applications')">👥 Application Management</div>
                                    <div class="suggestion-chip" onclick="sendPredefinedMessage('Industry hiring trends')">📈 Market Insights</div>
                                </div>
                                
                                <br>💡 <strong>Your Current Stats:</strong><br>
                                • Active Jobs: <?php echo $employer_stats['active_jobs']; ?><br>
                                • Total Applications: <?php echo $employer_stats['total_applications']; ?><br>
                                • Pending Reviews: <?php echo $employer_stats['pending_applications']; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ai-chat-input">
                        <div class="ai-input-wrapper">
                            <input type="text" id="aiMessageInput" placeholder="Ask me anything about recruitment, analytics, or ShaSha features...">
                        </div>
                        <button class="ai-send-btn" onclick="sendAIMessage()">
                            <span>🚀</span>
                        </button>
                    </div>
                </div>
                
                <!-- Admin Query Panel -->
                <div class="admin-panel">
                    <div class="admin-panel-header">
                        <h3>🎫 Submit Query to Admin</h3>
                    </div>
                    
                    <form method="post" class="query-form">
                        <div class="form-group">
                            <label for="query_type">🏷️ Query Type</label>
                            <select id="query_type" name="query_type" required>
                                <option value="">Select query type</option>
                                <option value="new_category">➕ Add New Job Category</option>
                                <option value="technical_issue">🔧 Technical Issue</option>
                                <option value="account_support">👤 Account Support</option>
                                <option value="feature_request">✨ Feature Request</option>
                                <option value="other">❓ Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="query_text">📝 Your Query</label>
                            <textarea id="query_text" name="query_text" required placeholder="Describe your query in detail..."></textarea>
                        </div>
                        
                        <button type="submit" name="admin_query" class="btn-submit">
                            🚀 Submit Query
                        </button>
                    </form>
                    
                    <?php if(count($previous_queries) > 0): ?>
                        <div class="previous-queries">
                            <div class="queries-header">
                                <h4>📋 Previous Queries</h4>
                            </div>
                            
                            <?php foreach(array_slice($previous_queries, 0, 5) as $query): ?>
                                <div class="query-item">
                                    <div class="query-type">
                                        <?php echo ucwords(str_replace('_', ' ', $query['subject'])); ?>
                                    </div>
                                    <div class="query-text">
                                        <?php echo htmlspecialchars($query['message']); ?>
                                    </div>
                                    <span class="query-status status-<?php echo $query['status']; ?>">
                                        <?php echo $query['status'] === 'pending' ? '⏳' : '✅'; ?> 
                                        <?php echo ucfirst($query['status']); ?>
                                    </span>
                                    <?php if($query['admin_notes']): ?>
                                        <div class="query-response">
                                            💬 <strong>Admin Response:</strong><br>
                                            <?php echo htmlspecialchars($query['admin_notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="query-meta">
                                        📅 <?php echo date('M d, Y', strtotime($query['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Advanced Chatbot Backend Integration -->
    <script>
        // Enhanced AI Chat System
        let messageCount = 0;
        const employerData = {
            totalJobs: <?php echo $employer_stats['total_jobs']; ?>,
            activeJobs: <?php echo $employer_stats['active_jobs']; ?>,
            totalApplications: <?php echo $employer_stats['total_applications']; ?>,
            pendingApplications: <?php echo $employer_stats['pending_applications']; ?>,
            isVerified: <?php echo $is_verified ? 'true' : 'false'; ?>,
            companyName: '<?php echo addslashes($employer['company_name']); ?>'
        };

        async function sendAIMessage() {
            const input = document.getElementById('aiMessageInput');
            const message = input.value.trim();
            
            if(message) {
                addAIMessage(message, 'user');
                input.value = '';
                
                // Show typing indicator
                showTypingIndicator();
                
                try {
                    // Use the advanced chatbot handler
                    const response = await fetch('<?php echo SITE_URL; ?>/assets/php/chatbot-handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            message: message,
                            userType: 'employer',
                            sessionId: 'employer_<?php echo $_SESSION['user_id']; ?>',
                            employerData: employerData,
                            conversationHistory: []
                        })
                    });

                    const result = await response.json();
                    
                    hideTypingIndicator();
                    
                    if(result.success) {
                        addAIMessage(result.response.text, 'assistant', result.response.options);
                    } else {
                        addAIMessage('I apologize, but I encountered an error. Please try again! 🤖💭', 'assistant');
                    }
                } catch (error) {
                    hideTypingIndicator();
                    addAIMessage(getSmartResponse(message), 'assistant');
                }
            }
        }

        function sendPredefinedMessage(message) {
            document.getElementById('aiMessageInput').value = message;
            sendAIMessage();
        }

        function addAIMessage(text, sender, options = {}) {
            const messagesDiv = document.getElementById('aiChatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `ai-message ${sender}-message`;
            
            let messageHTML = `<div class="ai-message-content">${text}`;
            
            // Add suggestions if provided
            if(options.quickReplies && options.quickReplies.length > 0) {
                messageHTML += '<div class="ai-suggestions">';
                options.quickReplies.forEach(reply => {
                    messageHTML += `<div class="suggestion-chip" onclick="sendPredefinedMessage('${reply}')">${reply}</div>`;
                });
                messageHTML += '</div>';
            }
            
            messageHTML += '</div>';
            messageDiv.innerHTML = messageHTML;
            
            // Animate message appearance
            messageDiv.style.animationDelay = `${messageCount * 0.1}s`;
            messageCount++;
            
            messagesDiv.appendChild(messageDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        function showTypingIndicator() {
            const messagesDiv = document.getElementById('aiChatMessages');
            const typingDiv = document.createElement('div');
            typingDiv.className = 'ai-message typing-indicator';
            typingDiv.id = 'typing-indicator';
            typingDiv.innerHTML = '<div class="ai-message-content">🤖 AI is thinking... <span style="animation: blink 1s infinite;">💭</span></div>';
            messagesDiv.appendChild(typingDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        function hideTypingIndicator() {
            const typingDiv = document.getElementById('typing-indicator');
            if(typingDiv) {
                typingDiv.remove();
            }
        }

        function getSmartResponse(message) {
            const lowerMessage = message.toLowerCase();
            
            // Advanced contextual responses
            if(lowerMessage.includes('analytic') || lowerMessage.includes('stats') || lowerMessage.includes('data')) {
                return `📊 <strong>Your Recruitment Analytics:</strong><br><br>
                       🎯 Active Job Postings: ${employerData.activeJobs}<br>
                       📝 Total Applications: ${employerData.totalApplications}<br>
                       ⏳ Pending Reviews: ${employerData.pendingApplications}<br>
                       📈 Performance Rate: ${employerData.totalJobs > 0 ? (employerData.totalApplications / employerData.totalJobs).toFixed(1) : 0} apps/job<br><br>
                       💡 <strong>AI Insight:</strong> Your jobs are performing ${employerData.totalApplications > 10 ? 'excellently' : 'well'}! 
                       ${employerData.pendingApplications > 5 ? 'Consider reviewing pending applications to improve response time.' : ''}`;
            }
            
            if(lowerMessage.includes('optimization') || lowerMessage.includes('optimize') || lowerMessage.includes('improve')) {
                return `🚀 <strong>Job Posting Optimization Tips:</strong><br><br>
                       ✨ Use specific, action-oriented job titles<br>
                       📋 Include 5-7 key requirements (not more!)<br>
                       💰 Always include salary range for 40% more applications<br>
                       🏢 Highlight company culture and benefits<br>
                       📱 Ensure mobile-friendly descriptions<br><br>
                       🔥 <strong>Pro Tip:</strong> Jobs with video descriptions get 300% more engagement!`;
            }
            
            if(lowerMessage.includes('trend') || lowerMessage.includes('market') || lowerMessage.includes('industry')) {
                return `📈 <strong>Current Hiring Trends:</strong><br><br>
                       🔥 Remote work options increase applications by 50%<br>
                       💼 Skills-based hiring is trending over degree requirements<br>
                       ⚡ Fast application processes (under 10 minutes) perform better<br>
                       🎯 Personalized outreach increases acceptance rates by 25%<br><br>
                       🤖 <strong>AI Recommendation:</strong> Consider adding remote/hybrid options to attract top talent!`;
            }
            
            return `🤖 I understand you're asking about "${message}". I'm continuously learning to help you better! 
                   For complex queries, you can also submit them to our admin team using the form on the right. 
                   
                   <div class="ai-suggestions">
                       <div class="suggestion-chip" onclick="sendPredefinedMessage('Show my analytics')">📊 View Analytics</div>
                       <div class="suggestion-chip" onclick="sendPredefinedMessage('Optimization tips')">🚀 Get Tips</div>
                       <div class="suggestion-chip" onclick="sendPredefinedMessage('Market trends')">📈 Market Data</div>
                   </div>`;
        }

        // Enhanced enter key support
        document.getElementById('aiMessageInput').addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                sendAIMessage();
            }
        });

        // Auto-focus on input
        document.getElementById('aiMessageInput').focus();
    </script>
</body>
</html>