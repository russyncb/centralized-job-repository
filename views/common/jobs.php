<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/config/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/includes/functions.php';

$page_title = 'Browse Jobs';
$extra_css = ['home'];

// Connect to DB
$db = new Database();
$conn = $db->getConnection();

// --- Advanced Search/Filtering ---
$keyword = isset($_GET['keyword']) ? sanitize($_GET['keyword']) : '';
$location = isset($_GET['location']) ? sanitize($_GET['location']) : '';
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$salary_min = isset($_GET['salary_min']) ? (float)$_GET['salary_min'] : '';
$salary_max = isset($_GET['salary_max']) ? (float)$_GET['salary_max'] : '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$sql = "SELECT j.*, e.company_name, e.company_logo, c.name AS category_name
        FROM jobs j
        JOIN employer_profiles e ON j.employer_id = e.employer_id
        LEFT JOIN job_categories c ON j.category = c.name
        WHERE j.status = 'active'
        AND (j.application_deadline IS NULL OR j.application_deadline >= CURDATE())";
$params = [];
if ($keyword) {
    $sql .= " AND (j.title LIKE :keyword OR j.description LIKE :keyword)";
    $params[':keyword'] = "%$keyword%";
}
if ($location) {
    $sql .= " AND j.location LIKE :location";
    $params[':location'] = "%$location%";
}
if ($category) {
    $sql .= " AND j.category = :category";
    $params[':category'] = $category;
}
if ($type) {
    $sql .= " AND j.job_type = :type";
    $params[':type'] = $type;
}
if ($salary_min !== '') {
    $sql .= " AND j.salary_min >= :salary_min";
    $params[':salary_min'] = $salary_min;
}
if ($salary_max !== '') {
    $sql .= " AND j.salary_max <= :salary_max";
    $params[':salary_max'] = $salary_max;
}
$sql .= " ORDER BY j.posted_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for filter
$cat_stmt = $conn->query("SELECT name FROM job_categories ORDER BY name");
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Job types
$job_types = ['full-time' => 'Full-time', 'part-time' => 'Part-time', 'contract' => 'Contract', 'internship' => 'Internship', 'remote' => 'Remote'];

// Count total jobs for pagination
$count_sql = "SELECT COUNT(*) FROM jobs j WHERE j.status = 'active'";
$count_params = [];
if ($keyword) {
    $count_sql .= " AND (j.title LIKE :keyword OR j.description LIKE :keyword)";
    $count_params[':keyword'] = "%$keyword%";
}
if ($location) {
    $count_sql .= " AND j.location LIKE :location";
    $count_params[':location'] = "%$location%";
}
if ($category) {
    $count_sql .= " AND j.category = :category";
    $count_params[':category'] = $category;
}
if ($type) {
    $count_sql .= " AND j.job_type = :type";
    $count_params[':type'] = $type;
}
if ($salary_min !== '') {
    $count_sql .= " AND j.salary_min >= :salary_min";
    $count_params[':salary_min'] = $salary_min;
}
if ($salary_max !== '') {
    $count_sql .= " AND j.salary_max <= :salary_max";
    $count_params[':salary_max'] = $salary_max;
}
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($count_params);
$total_jobs = $count_stmt->fetchColumn();
$total_pages = ceil($total_jobs / $limit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/home.css">
    <style>
        .jobs-list-section { padding: 60px 0; }
        .jobs-list-header { text-align: center; margin-bottom: 30px; }
        .jobs-list-header h1 { font-size: 2.2rem; font-weight: 700; color: #1a3b5d; }
        .jobs-list-header p { color: #4a5568; font-size: 1.1rem; }
        .advanced-search { background: #fff; border-radius: 8px; box-shadow: 0 5px 30px rgba(0,0,0,0.08); padding: 30px; margin-bottom: 40px; }
        .advanced-search .search-fields { display: flex; flex-wrap: wrap; gap: 15px; }
        .advanced-search .field { flex: 1; min-width: 180px; }
        .advanced-search label { font-weight: 600; color: #1a3b5d; margin-bottom: 6px; display: block; }
        .advanced-search input, .advanced-search select { width: 100%; padding: 10px 12px; border: 1px solid #d9e1ec; border-radius: 6px; font-size: 1rem; }
        .advanced-search .btn-search { margin-top: 18px; }
        .job-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 30px; }
        .job-card { background: #fff; border-radius: 8px; box-shadow: 0 5px 20px rgba(0,0,0,0.07); padding: 28px 24px; display: flex; flex-direction: column; justify-content: space-between; }
        .job-card .job-header { display: flex; align-items: center; gap: 18px; margin-bottom: 12px; }
        .job-card .company-logo { width: 54px; height: 54px; border-radius: 8px; background: #f4f7fa; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .job-card .company-logo img { width: 100%; height: 100%; object-fit: contain; }
        .job-card .job-title-company { flex: 1; }
        .job-card .job-title-company h3 { font-size: 1.2rem; color: #1a3b5d; margin: 0; font-weight: 700; }
        .job-card .job-title-company .company { color: #1a73e8; font-size: 1rem; font-weight: 500; }
        .job-card .job-type { background: #e6f0ff; color: #1a73e8; border-radius: 4px; padding: 2px 10px; font-size: 0.95rem; font-weight: 600; margin-left: 10px; }
        .job-card .job-details { color: #4a5568; font-size: 1rem; margin-bottom: 10px; }
        .job-card .job-tags { margin-top: 8px; }
        .job-card .job-tags .tag { display: inline-block; background: #f1f5fa; color: #1a73e8; border-radius: 4px; padding: 2px 8px; font-size: 0.92rem; margin-right: 6px; margin-bottom: 3px; }
        .job-card .btn-view { margin-top: 18px; align-self: flex-start; background: #1a73e8; color: #fff; border: none; border-radius: 6px; padding: 10px 22px; font-weight: 600; font-size: 1rem; transition: all 0.3s; text-decoration: none; }
        .job-card .btn-view:hover { background: #1557b0; }
        .no-jobs { text-align: center; color: #888; font-size: 1.2rem; margin-top: 40px; }
        .job-meta .deadline {
            color: #e65100;
            font-weight: 500;
        }
        
        .job-meta .deadline i {
            color: #ef6c00;
        }
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

    <section class="jobs-list-section">
        <div class="container">
            <div class="jobs-list-header">
                <h1>Browse Jobs</h1>
                <p>Explore the latest job opportunities in Zimbabwe. Use the filters below to find your perfect match!</p>
            </div>
            <form class="advanced-search" method="GET" action="">
                <div class="search-fields">
                    <div class="field">
                        <label for="keyword">Keyword</label>
                        <input type="text" id="keyword" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Job title, skills, etc.">
                    </div>
                    <div class="field">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>" placeholder="City, region, etc.">
                    </div>
                    <div class="field">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php if($category === $cat) echo 'selected'; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="type">Job Type</label>
                        <select id="type" name="type">
                            <option value="">All Types</option>
                            <?php foreach($job_types as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php if($type === $key) echo 'selected'; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="salary_min">Min Salary (USD)</label>
                        <input type="number" id="salary_min" name="salary_min" value="<?php echo htmlspecialchars($salary_min); ?>" min="0" step="100">
                    </div>
                    <div class="field">
                        <label for="salary_max">Max Salary (USD)</label>
                        <input type="number" id="salary_max" name="salary_max" value="<?php echo htmlspecialchars($salary_max); ?>" min="0" step="100">
                    </div>
                    <div class="field" style="align-self: flex-end;">
                        <button type="submit" class="btn btn-search">Search</button>
                    </div>
                </div>
            </form>

            <?php if(count($jobs) === 0): ?>
                <div class="no-jobs">No jobs found. Try adjusting your filters.</div>
            <?php else: ?>
                <div class="job-cards">
                    <?php foreach($jobs as $job): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <div class="company-logo">
                                    <?php if($job['company_logo']): ?>
                                        <img src="<?php echo SITE_URL . '/uploads/' . htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?> logo">
                                    <?php else: ?>
                                        <span>🏢</span>
                                    <?php endif; ?>
                                </div>
                                <div class="job-title-company">
                                    <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                                    <div class="company"><?php echo htmlspecialchars($job['company_name']); ?></div>
                                </div>
                                <span class="job-type"><?php echo ucfirst($job['job_type']); ?></span>
                            </div>
                            <div class="job-details">
                                <div class="job-meta">
                                    <span class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                    <span class="job-type"><i class="fas fa-briefcase"></i> <?php echo ucfirst($job['job_type']); ?></span>
                                    <?php if($job['application_deadline']): ?>
                                        <?php 
                                        $deadline = new DateTime($job['application_deadline']);
                                        $today = new DateTime();
                                        $interval = $today->diff($deadline);
                                        ?>
                                        <span class="deadline">
                                            <i class="fas fa-clock"></i>
                                            <?php 
                                            echo 'Deadline: ' . date('M d, Y', strtotime($job['application_deadline']));
                                            if($interval->days <= 7) {
                                                echo ' (' . $interval->days . ' days left)';
                                            }
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="posted-date"><i class="fas fa-calendar"></i> Posted <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
                                </div>
                                <div><strong>Category:</strong> <?php echo htmlspecialchars($job['category']); ?></div>
                                <div><strong>Salary:</strong> <?php echo $job['salary_min'] ? '$' . number_format($job['salary_min']) : 'N/A'; ?> - <?php echo $job['salary_max'] ? '$' . number_format($job['salary_max']) : 'N/A'; ?> <?php echo htmlspecialchars($job['salary_currency']); ?></div>
                            </div>
                            <div class="job-tags">
                                <?php if($job['requirements']): ?>
                                    <span class="tag">Requirements</span>
                                <?php endif; ?>
                                <?php if($job['responsibilities']): ?>
                                    <span class="tag">Responsibilities</span>
                                <?php endif; ?>
                            </div>
                            <?php
                            $view_url = SITE_URL . '/views/auth/login.php?redirect=' . urlencode('/views/common/jobs.php?job_id=' . $job['job_id']);
                            $can_view = is_logged_in() && has_role('jobseeker');
                            ?>
                            <a href="<?php echo $can_view ? SITE_URL . '/views/common/job-view.php?job_id=' . $job['job_id'] : $view_url; ?>" class="btn btn-view" <?php if(!$can_view): ?>onclick="return confirm('You need to be logged in as a jobseeker to view job details. You will be redirected to login.')"<?php endif; ?>>View</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if($total_pages > 1): ?>
                <div class="pagination" style="text-align:center;margin-top:40px;">
                    <?php for($i=1;$i<=$total_pages;$i++): ?>
                        <?php
                        $query = $_GET;
                        $query['page'] = $i;
                        $url = '?' . http_build_query($query);
                        ?>
                        <a href="<?php echo $url; ?>" class="<?php if($i==$page) echo 'active'; ?>" style="display:inline-block;padding:8px 16px;margin:0 2px;border-radius:4px;background:<?php echo $i==$page?'#1a73e8':'#f1f5fa'; ?>;color:<?php echo $i==$page?'#fff':'#1a73e8'; ?>;text-decoration:none;font-weight:600;"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
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
        // This script ensures that any element with auth-required class redirects to login
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
        // Chatbot elements
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
