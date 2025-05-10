<?php
// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Set page title
$page_title = 'About Us';
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
        /* About page specific styling */
        .page-header {
            background: linear-gradient(135deg, #1a3b5d 0%, #2a5b8d 100%);
            color: white;
            padding: 80px 0 60px;
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 2.8rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .page-header p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
        }
        
        .about-section {
            padding: 80px 0;
        }
        
        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }
        
        .about-image {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .about-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .about-text h2 {
            font-size: 2rem;
            color: #1a3b5d;
            margin-bottom: 25px;
        }
        
        .about-text p {
            font-size: 1.1rem;
            line-height: 1.7;
            color: #4a5568;
            margin-bottom: 20px;
        }
        
        .mission-vision {
            background-color: #f7f9fc;
            padding: 80px 0;
        }
        
        .mission-vision-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        
        .mission-card, .vision-card {
            background-color: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }
        
        .mission-card h3, .vision-card h3 {
            font-size: 1.8rem;
            color: #1a3b5d;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .mission-card h3 span, .vision-card h3 span {
            font-size: 2rem;
            margin-right: 15px;
        }
        
        .mission-card p, .vision-card p {
            font-size: 1.1rem;
            line-height: 1.7;
            color: #4a5568;
        }
        
        .team-section {
            padding: 80px 0;
        }
        
        .team-section h2 {
            font-size: 2.2rem;
            color: #1a3b5d;
            text-align: center;
            margin-bottom: 50px;
        }
        
        .team-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }
        
        .team-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        
        .team-card:hover {
            transform: translateY(-10px);
        }
        
        .team-image {
            height: 220px;
            background-color: #e4e7ec;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .team-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .team-info {
            padding: 25px;
            text-align: center;
        }
        
        .team-info h3 {
            font-size: 1.3rem;
            color: #1a3b5d;
            margin-bottom: 5px;
        }
        
        .team-info p.position {
            font-size: 0.95rem;
            color: #1a73e8;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .team-info p.bio {
            font-size: 0.95rem;
            color: #4a5568;
            line-height: 1.6;
        }
        
        .achievements {
            background: linear-gradient(135deg, #1a3b5d 0%, #2a5b8d 100%);
            color: white;
            padding: 80px 0;
        }
        
        .achievements h2 {
            font-size: 2.2rem;
            text-align: center;
            margin-bottom: 60px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            text-align: center;
        }
        
        .stat-card {
            padding: 20px;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .stat-description {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        /* Site Header - Modern Style */
        .site-header {
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .site-header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #1a3b5d;
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .logo .icon {
            margin-right: 8px;
            font-size: 1.8rem;
        }
        
        .main-nav ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .main-nav ul li {
            margin: 0 15px;
        }
        
        .main-nav ul li a {
            color: #4a5568;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 0;
            transition: color 0.3s;
            position: relative;
        }
        
        .main-nav ul li a:hover,
        .main-nav ul li a.active {
            color: #1a73e8;
        }
        
        .main-nav ul li a.active:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: #1a73e8;
            border-radius: 2px 2px 0 0;
        }
        
        .auth-buttons {
            display: flex;
        }
        
        .btn {
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-login {
            color: rgba(0, 0, 0, 0.49);
            margin-right: 10px;
        }
        
        .btn-login:hover {
            background-color: rgba(26, 115, 232, 0.1);
        }
        
        .btn-register {
            background-color: #1a73e8;
            color: white;
        }
        
        .btn-register:hover {
            background-color: #1557b0;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Site Footer - Modern Style */
        .site-footer {
            background-color: #1a3b5d;
            color: #e4e7ec;
            padding: 60px 0 30px;
        }
        
        .footer-top {
            display: grid;
            grid-template-columns: 2fr 4fr;
            gap: 50px;
            margin-bottom: 40px;
        }
        
        .footer-logo {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .footer-links {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        
        .footer-links h3 {
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .footer-links ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-links ul li {
            margin-bottom: 12px;
        }
        
        .footer-links ul li a {
            color: #d9e1ec;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links ul li a:hover {
            color: #fff;
            text-decoration: underline;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 30px;
            text-align: center;
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        /* Responsive styling */
        @media (max-width: 992px) {
            .about-content, .mission-vision-container {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .team-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .footer-top {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .footer-links {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .site-header .container {
                flex-direction: column;
            }
            
            .logo {
                margin-bottom: 15px;
            }
            
            .main-nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .auth-buttons {
                margin-top: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .team-cards, .stats-container, .footer-links {
                grid-template-columns: 1fr;
            }
            
            .page-header h1 {
                font-size: 2.2rem;
            }
            
            .about-text h2, .mission-card h3, .vision-card h3 {
                font-size: 1.7rem;
            }
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
                    <li><a href="<?php echo SITE_URL; ?>/views/common/jobs.php">Jobs</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/companies.php">Companies</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/about.php" class="active">About</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/contact.php">Contact</a></li>
                </ul>
            </nav>
            <div class="auth-buttons">
                <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="btn btn-login">Login</a>
                <a href="<?php echo SITE_URL; ?>/views/auth/register.php" class="btn btn-register">Register</a>
            </div>
        </div>
    </header>

    <section class="page-header">
        <div class="container">
            <h1>About ShaSha CJRS</h1>
            <p>Zimbabwe's leading job recruitment platform connecting talented professionals with top employers starting 2025.</p>
        </div>
    </section>

    <section class="about-section">
        <div class="container">
            <div class="about-content">
                <div class="about-image">
                    <img src="/api/placeholder/600/400" alt="ShaSha Team" />
                </div>
                <div class="about-text">
                    <h2>Our Story</h2>
                    <p>ShaSha CJRS (Centralised Job Repository System) was founded in 2025 with a simple yet powerful vision - to transform how job seekers and employers connect in Zimbabwe. In a market where finding the right talent or the right opportunity was often challenging, we set out to create a transparent, efficient platform that would serve both job seekers and employers.</p>
                    <p>Starting with a small team of just 3 people, we are targetting to become Zimbabwe's most trusted job portal with over 50,000 registered job seekers and more than 2,000 verified employers. What sets us apart is our commitment to quality, verification of all job postings, and our deep understanding of the local job market.</p>
                    <p>Our name "ShaSha" comes from the Shona word meaning "to work diligently" - a principle that guides both our own team and the connections we help create between employers and talented professionals across Zimbabwe.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mission-vision">
        <div class="container">
            <div class="mission-vision-container">
                <div class="mission-card">
                    <h3><span>🎯</span> Our Mission</h3>
                    <p>To empower Zimbabwean job seekers and employers by providing a trusted, efficient platform that connects talent with opportunity, reduces unemployment, and contributes to economic growth.</p>
                    <p>We strive to make the job search and recruitment process seamless, transparent, and accessible to all Zimbabweans regardless of their location or background.</p>
                </div>
                <div class="vision-card">
                    <h3><span>🔭</span> Our Vision</h3>
                    <p>To be the definitive career platform for Zimbabwe where every qualified professional can find meaningful work and every business can access the talent they need to thrive.</p>
                    <p>We envision a future where ShaSha becomes not just a job portal but a comprehensive career development ecosystem supporting professionals throughout their entire career journey.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="team-section">
        <div class="container">
            <h2>Meet Our Leadership Team</h2>
            <div class="team-cards">
                <div class="team-card">
                    <div class="team-image">
                        <img src="/api/placeholder/300/300" alt="Team Member" />
                    </div>
                    <div class="team-info">
                        <h3>Trinity Mjunda</h3>
                        <p class="position">Founder & CEO</p>
                        <p class="bio">With over 100+ connections in the business realm, blood decided to start the system</p>
                    </div>
                </div>
                
                <div class="team-card">
                    <div class="team-image">
                        <img src="/api/placeholder/300/300" alt="Team Member" />
                    </div>
                    <div class="team-info">
                        <h3>Raisemore Manjemure</h3>
                        <p class="position">Chief Operations Officer</p>
                        <p class="bio">Raisemore oversees daily operations and ensures that both employers and job seekers receive exceptional service.</p>
                    </div>
                </div>
                
                <div class="team-card">
                    <div class="team-image">
                        <img src="/api/placeholder/300/300" alt="Team Member" />
                    </div>
                    <div class="team-info">
                        <h3>Russel Ncube</h3>
                        <p class="position">Chief Technology Officer</p>
                        <p class="bio">Russel leads our tech development, constantly enhancing the platform to meet changing market needs.</p>
                    </div>
                </div>
                
                <div class="team-card">
                    <div class="team-image">
                        <img src="/api/placeholder/300/300" alt="Team Member" />
                    </div>
                    <div class="team-info">
                        <h3>Mrs Musiyandaka</h3>
                        <p class="position">Head of Project</p>
                        <p class="bio">Mrs Musiyandaka was responsible for th foundation of the system and guiding it to the end</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="achievements">
        <div class="container">
            <h2>Our Impact</h2>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number">5K+</div>
                    <div class="stat-description">Registered Job Seekers</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">2,00+</div>
                    <div class="stat-description">Verified Employers</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">1K+</div>
                    <div class="stat-description">Successful Placements</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">77%</div>
                    <div class="stat-description">Client Satisfaction</div>
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