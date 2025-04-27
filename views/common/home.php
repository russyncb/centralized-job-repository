<?php
// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Set page title
$page_title = 'Welcome to ShaSha';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/home.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="logo">
                <a href="<?php echo SITE_URL; ?>">
                    <span class="icon">📊</span> ShaSha CJRS
                </a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/home.php" class="active">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="auth-required">Jobs</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="auth-required">Companies</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/about.php">About</a></li>
                </ul>
            </nav>
            <div class="auth-buttons">
                <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="btn btn-login">Login</a>
                <a href="<?php echo SITE_URL; ?>/views/auth/register.php" class="btn btn-register">Register</a>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Find Your Dream Job in Zimbabwe</h1>
                <p>ShaSha connects talented job seekers with top employers across Zimbabwe. Your next career opportunity is just a click away.</p>
                <div class="cta-buttons">
                    <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="btn btn-primary"><span class="icon">🔍</span> I'm Looking for a Job</a>
                    <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="btn btn-secondary"><span class="icon">🏢</span> I'm Hiring</a>
                </div>
            </div>
            <div class="hero-image">
                <!-- Placeholder for hero image -->
            </div>
        </div>
    </section>

    <section class="job-search">
        <div class="container">
            <div class="search-container">
                <h2>Search Jobs</h2>
                <form id="search-form" action="<?php echo SITE_URL; ?>/views/auth/login.php" method="GET">
                    <div class="search-fields">
                        <div class="field">
                            <input type="text" name="keyword" placeholder="Job title or keyword">
                        </div>
                        <div class="field">
                            <input type="text" name="location" placeholder="Location">
                        </div>
                        <button type="submit" class="btn btn-search">Search</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="job-categories">
        <div class="container">
            <h2>Popular Job Categories</h2>
            <p class="section-subtitle">Explore opportunities in these in-demand fields</p>
            
            <div class="category-cards">
                <div class="category-card">
                    <div class="category-icon">💻</div>
                    <h3>Information Technology</h3>
                    <p>245 open positions</p>
                    <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="browse-link">Browse Jobs <span class="arrow">→</span></a>
                </div>
                
                <div class="category-card">
                    <div class="category-icon">💰</div>
                    <h3>Finance & Banking</h3>
                    <p>186 open positions</p>
                    <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="browse-link">Browse Jobs <span class="arrow">→</span></a>
                </div>
                
                <div class="category-card">
                    <div class="category-icon">🏥</div>
                    <h3>Healthcare</h3>
                    <p>142 open positions</p>
                    <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="browse-link">Browse Jobs <span class="arrow">→</span></a>
                </div>
                
                <div class="category-card">
                    <div class="category-icon">🎓</div>
                    <h3>Education</h3>
                    <p>97 open positions</p>
                    <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="browse-link">Browse Jobs <span class="arrow">→</span></a>
                </div>
            </div>
        </div>
    </section>

    <section class="featured-jobs">
        <div class="container">
            <h2>Featured Jobs</h2>
            <p class="section-subtitle">Handpicked opportunities from top employers</p>
            
            <div class="job-cards">
                <div class="job-card">
                    <div class="job-header">
                        <div class="job-icon">📄</div>
                        <div class="job-title-company">
                            <h3>Senior Software Engineer</h3>
                            <p class="company">TechZim Solutions</p>
                        </div>
                        <span class="job-type">Full-time</span>
                    </div>
                    <div class="job-details">
                        <p class="job-location">Harare • $2,500 - $3,500/month</p>
                        <div class="job-tags">
                            <span class="tag">React</span>
                            <span class="tag">Node.js</span>
                            <span class="tag">MongoDB</span>
                        </div>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="btn btn-view">View Details</a>
                </div>
                
                <div class="job-card">
                    <div class="job-header">
                        <div class="job-icon">📄</div>
                        <div class="job-title-company">
                            <h3>Financial Analyst</h3>
                            <p class="company">ZimBank</p>
                        </div>
                        <span class="job-type">Full-time</span>
                    </div>
                    <div class="job-details">
                        <p class="job-location">Bulawayo • $2,000 - $2,800/month</p>
                        <div class="job-tags">
                            <span class="tag">Financial Modeling</span>
                            <span class="tag">Excel</span>
                            <span class="tag">Data Analysis</span>
                        </div>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="btn btn-view">View Details</a>
                </div>
                
                <div class="job-card">
                    <div class="job-header">
                        <div class="job-icon">📄</div>
                        <div class="job-title-company">
                            <h3>Marketing Manager</h3>
                            <p class="company">AfriComm</p>
                        </div>
                        <span class="job-type">Full-time</span>
                    </div>
                    <div class="job-details">
                        <p class="job-location">Harare • $1,800 - $2,500/month</p>
                        <div class="job-tags">
                            <span class="tag">Digital Marketing</span>
                            <span class="tag">Brand Strategy</span>
                            <span class="tag">Social Media</span>
                        </div>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="btn btn-view">View Details</a>
                </div>
            </div>
            
            <div class="view-all-container">
                <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="btn btn-view-all">View All Jobs <span class="arrow">→</span></a>
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
                            <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php">Browse Jobs</a></li>
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
                    // Add any parameters you want to pass to login page
                    // For example, a redirect parameter to come back after login
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
        
        // Toggle chatbot visibility
        chatbotIcon.addEventListener('click', function() {
            chatbotBox.style.display = 'flex';
            userInput.focus();
        });
        
        closeChat.addEventListener('click', function() {
            chatbotBox.style.display = 'none';
        });
        
        // Send message function
        function sendUserMessage() {
            const message = userInput.value.trim();
            if (message) {
                // Add user message to chat
                addMessage(message, 'user');
                
                // Clear input
                userInput.value = '';
                
                // Get bot response after a short delay
                setTimeout(() => {
                    const response = getBotResponse(message);
                    addMessage(response, 'bot');
                }, 600);
            }
        }
        
        // Listen for send button click
        sendMessage.addEventListener('click', sendUserMessage);
        
        // Listen for Enter key
        userInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendUserMessage();
            }
        });
        
        // Add message to chat
        function addMessage(text, sender) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message');
            messageDiv.classList.add(sender + '-message');
            
            const contentDiv = document.createElement('div');
            contentDiv.classList.add('message-content');
            contentDiv.textContent = text;
            
            messageDiv.appendChild(contentDiv);
            chatMessages.appendChild(messageDiv);
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Simple response logic
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
                return "You can contact our support team at info@shasha.co.zw or call +263 242 123 456.";
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