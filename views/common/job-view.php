<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/config/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/includes/functions.php';

$page_title = 'Job Details';
$extra_css = ['home'];

$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if (!$job_id) {
    redirect(SITE_URL . '/views/common/jobs.php', 'Invalid job selected.', 'error');
}

// If not logged in, redirect to login
if (!is_logged_in()) {
    $redirect_url = SITE_URL . '/views/auth/login.php?redirect=' . urlencode('/views/common/job-view.php?job_id=' . $job_id);
    redirect($redirect_url, 'Please login to view job details.', 'error');
}
// If not a jobseeker, show message
if (!has_role('jobseeker')) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Job Details - ' . SITE_NAME . '</title><link rel="stylesheet" href="' . SITE_URL . '/assets/css/style.css"><link rel="stylesheet" href="' . SITE_URL . '/assets/css/home.css"></head><body>';
    echo '<div style="max-width:600px;margin:80px auto;padding:40px;background:#fff;border-radius:8px;box-shadow:0 5px 30px rgba(0,0,0,0.08);text-align:center;">';
    echo '<h2>Access Restricted</h2>';
    echo '<p>Only jobseekers can view job details. Please <a href="' . SITE_URL . '/views/auth/login.php">login as a jobseeker</a>.</p>';
    echo '<a href="' . SITE_URL . '/views/common/jobs.php" style="display:inline-block;margin-top:20px;padding:10px 24px;background:#1a73e8;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">Back to Jobs</a>';
    echo '</div></body></html>';
    exit;
}

// Fetch job details
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->prepare("SELECT j.*, e.company_name, e.company_logo, c.name AS category_name
    FROM jobs j
    JOIN employer_profiles e ON j.employer_id = e.employer_id
    LEFT JOIN job_categories c ON j.category = c.name
    WHERE j.job_id = :job_id AND j.status = 'active'");
$stmt->execute([':job_id' => $job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$job) {
    redirect(SITE_URL . '/views/common/jobs.php', 'Job not found or is no longer available.', 'error');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']) . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/home.css">
    <style>
        .job-details-section { padding: 60px 0; }
        .job-details-card { background: #fff; border-radius: 10px; box-shadow: 0 5px 30px rgba(0,0,0,0.08); max-width: 800px; margin: 0 auto; padding: 40px 32px; }
        .job-details-header { display: flex; align-items: center; gap: 24px; margin-bottom: 24px; }
        .job-details-header .company-logo { width: 70px; height: 70px; border-radius: 10px; background: #f4f7fa; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .job-details-header .company-logo img { width: 100%; height: 100%; object-fit: contain; }
        .job-details-header .job-title-company { flex: 1; }
        .job-details-header h2 { font-size: 2rem; color: #1a3b5d; margin: 0; font-weight: 700; }
        .job-details-header .company { color: #1a73e8; font-size: 1.1rem; font-weight: 500; }
        .job-details-header .job-type { background: #e6f0ff; color: #1a73e8; border-radius: 4px; padding: 4px 14px; font-size: 1rem; font-weight: 600; margin-left: 10px; }
        .job-details-body { color: #4a5568; font-size: 1.08rem; }
        .job-details-body strong { color: #1a3b5d; }
        .job-details-body ul { margin: 0 0 18px 18px; }
        .job-details-footer { margin-top: 32px; text-align: right; }
        .job-details-footer .btn-back { background: #f1f5fa; color: #1a73e8; border: none; border-radius: 6px; padding: 10px 22px; font-weight: 600; font-size: 1rem; text-decoration: none; margin-right: 10px; }
        .job-details-footer .btn-back:hover { background: #e6f0ff; }
        .job-details-footer .btn-apply { background: #1a73e8; color: #fff; border: none; border-radius: 6px; padding: 10px 22px; font-weight: 600; font-size: 1rem; text-decoration: none; }
        .job-details-footer .btn-apply:hover { background: #1557b0; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="logo">
                <a href="<?php echo SITE_URL; ?>">
                    <span class="icon">💼</span> ShaSha CJRS
                </a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/home.php">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/jobs.php" class="active">Jobs</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/companies.php">Companies</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/about.php">About</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/contact.php">Contact</a></li>
                </ul>
            </nav>
            <div class="auth-buttons">
                <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="btn btn-login">Login</a>
                <a href="<?php echo SITE_URL; ?>/views/auth/register.php" class="btn btn-register">Register</a>
            </div>
        </div>
    </header>

    <section class="job-details-section">
        <div class="container">
            <div class="job-details-card">
                <div class="job-details-header">
                    <div class="company-logo">
                        <?php if($job['company_logo']): ?>
                            <img src="<?php echo SITE_URL . '/uploads/' . htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?> logo">
                        <?php else: ?>
                            <span>🏢</span>
                        <?php endif; ?>
                    </div>
                    <div class="job-title-company">
                        <h2><?php echo htmlspecialchars($job['title']); ?></h2>
                        <div class="company"><?php echo htmlspecialchars($job['company_name']); ?></div>
                    </div>
                    <span class="job-type"><?php echo ucfirst($job['job_type']); ?></span>
                </div>
                <div class="job-details-body">
                    <div><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></div>
                    <div><strong>Category:</strong> <?php echo htmlspecialchars($job['category']); ?></div>
                    <div><strong>Salary:</strong> <?php echo $job['salary_min'] ? '$' . number_format($job['salary_min']) : 'N/A'; ?> - <?php echo $job['salary_max'] ? '$' . number_format($job['salary_max']) : 'N/A'; ?> <?php echo htmlspecialchars($job['salary_currency']); ?></div>
                    <div><strong>Deadline:</strong> <?php echo $job['application_deadline'] ? format_date($job['application_deadline']) : 'N/A'; ?></div>
                    <div style="margin-top:18px;"><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($job['description'])); ?></div>
                    <?php if($job['requirements']): ?>
                        <div style="margin-top:18px;"><strong>Requirements:</strong><br><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></div>
                    <?php endif; ?>
                    <?php if($job['responsibilities']): ?>
                        <div style="margin-top:18px;"><strong>Responsibilities:</strong><br><?php echo nl2br(htmlspecialchars($job['responsibilities'])); ?></div>
                    <?php endif; ?>
                </div>
                <div class="job-details-footer">
                    <a href="<?php echo SITE_URL; ?>/views/common/jobs.php" class="btn-back">Back to Jobs</a>
                    <a href="#" class="btn-apply" onclick="alert('Job application coming soon!')">Apply</a>
                </div>
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-top">
                <div class="footer-logo">
                    <span class="icon">📊</span> ShaSha CJRS
                    <p>Connecting talent with opportunity across Zimbabwe since 2015.</p>
                </div>
                <div class="footer-links">
                    <div class="link-group">
                        <h3>FOR JOB SEEKERS</h3>
                        <ul>
                            <li><a href="<?php echo SITE_URL; ?>/views/common/jobs.php">Browse Jobs</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php">Career Resources</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php">Resume Builder</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php">Job Alerts</a></li>
                        </ul>
                    </div>
                    <div class="link-group">
                        <h3>FOR EMPLOYERS</h3>
                        <ul>
                            <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php">Post a Job</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php">Browse Candidates</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php">Recruitment Solutions</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php">Pricing</a></li>
                        </ul>
                    </div>
                    <div class="link-group">
                        <h3>CONTACT</h3>
                        <ul class="contact-info">
                            <li>Harare, Zimbabwe</li>
                            <li><a href="mailto:info@shasha.co.zw">info@shasha.co.zw</a></li>
                            <li>+263 242 123 456</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ShaSha CJRS. All rights reserved.</p>
            </div>
        </div>
    </footer>

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
    // Redirect auth-required links to login page
    document.addEventListener('DOMContentLoaded', function() {
        const authLinks = document.querySelectorAll('.auth-required, .browse-link, .btn-view, .btn-view-all');
        authLinks.forEach(link => {
            link.addEventListener('click', function(event) {
                const currentPath = window.location.pathname;
                link.href = `<?php echo SITE_URL; ?>/views/auth/login.php?redirect=${encodeURIComponent(currentPath)}`;
            });
        });
    });
</script>
<script>
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
            }
            else if (message.includes('register') || message.includes('sign up') || message.includes('create account')) {
                return "To register, click the 'Register' button in the top right corner. You can sign up as a job seeker or an employer.";
            }
            else if (message.includes('job') && (message.includes('find') || message.includes('search') || message.includes('look'))) {
                return "To search for jobs, you'll need to create an account first. Once logged in, you can browse all available positions or use our search filters.";
            }
            else if (message.includes('employer') || message.includes('company') || message.includes('post job')) {
                return "Employers can register and post job opportunities after verification. We verify all employers to ensure job postings are legitimate.";
            }
            else if (message.includes('contact') || message.includes('support')) {
                return "You can contact our support team at info@shasha.co.zw or call +263 242 123 456. You can also visit our Contact page for more options.";
            }
            else if (message.includes('thank')) {
                return "You're welcome! Is there anything else I can help you with?";
            }
            else {
                return "I'm sorry, I don't have information on that topic yet. For specific questions, please contact our support team or try registering to access more features.";
            }
        }
    });
</script>
</body>
</html> 