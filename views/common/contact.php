<?php
// Include bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Set page title
$page_title = 'Contact Us';

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simple form validation
    if (!empty($_POST['name']) && !empty($_POST['email']) && !empty($_POST['message'])) {
        // In a real application, you would process the form data here
        // This could include sending an email, saving to database, etc.
        
        // For demo purposes, just show a success message
        $success_message = "Thank you for your message! We'll get back to you shortly.";
    } else {
        $error_message = "Please fill in all required fields.";
    }
}
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
        /* Contact page specific styling */
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
        
        .contact-section {
            padding: 80px 0;
        }
        
        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: start;
        }
        
        .contact-info h2 {
            font-size: 2rem;
            color: #1a3b5d;
            margin-bottom: 30px;
        }
        
        .info-card {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 25px;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-icon {
            width: 50px;
            height: 50px;
            background-color: #e6f0ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            color: #1a73e8;
            font-size: 1.5rem;
        }
        
        .info-content h3 {
            font-size: 1.2rem;
            color: #1a3b5d;
            margin-bottom: 5px;
        }
        
        .info-content p, .info-content a {
            font-size: 1.1rem;
            color: #4a5568;
            line-height: 1.6;
        }
        
        .info-content a {
            color: #1a73e8;
            text-decoration: none;
        }
        
        .info-content a:hover {
            text-decoration: underline;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e6f0ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a73e8;
            font-size: 1.2rem;
            transition: all 0.3s;
        }
        
        .social-link:hover {
            background-color: #1a73e8;
            color: white;
        }
        
        .office-hours {
            background-color: #f7f9fc;
            border-radius: 8px;
            padding: 30px;
        }
        
        .office-hours h3 {
            font-size: 1.3rem;
            color: #1a3b5d;
            margin-bottom: 20px;
        }
        
        .hours-list {
            list-style: none;
            padding: 0;
        }
        
        .hours-list li {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e4e7ec;
            font-size: 1.05rem;
        }
        
        .hours-list li:last-child {
            border-bottom: none;
        }
        
        .hours-list li span.day {
            font-weight: 600;
            color: #1a3b5d;
        }
        
        .hours-list li span.time {
            color: #4a5568;
        }
        
        .contact-form-container {
            background-color: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        
        .contact-form-container h2 {
            font-size: 2rem;
            color: #1a3b5d;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-size: 1rem;
            font-weight: 600;
            color: #1a3b5d;
            margin-bottom: 8px;
        }
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #d9e1ec;
            border-radius: 6px;
            font-size: 1rem;
            color: #4a5568;
            transition: all 0.3s;
        }
        
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.2);
            outline: none;
        }
        
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn-submit {
            background-color: #1a73e8;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            background-color: #1557b0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 115, 232, 0.2);
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 1rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .map-section {
            padding: 0 0 80px;
        }
        
        .map-container {
            height: 450px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        
        .map-container iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }
        
        /* Responsive styling */
        @media (max-width: 992px) {
            .contact-container {
                grid-template-columns: 1fr;
                gap: 40px;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 25px;
            }
            
            .page-header h1 {
                font-size: 2.2rem;
            }
            
            .contact-info h2, .contact-form-container h2 {
                font-size: 1.8rem;
            }
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
            .footer-links {
                grid-template-columns: 1fr;
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
                    <li><a href="<?php echo SITE_URL; ?>/views/common/about.php">About</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/contact.php" class="active">Contact</a></li>
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
            <h1>Contact Us</h1>
            <p>Have questions or need assistance? We're here to help you navigate your career journey.</p>
        </div>
    </section>

    <section class="contact-section">
        <div class="container">
            <div class="contact-container">
                <div class="contact-info">
                    <h2>Get In Touch</h2>
                    
                    <div class="info-card">
                        <div class="info-item">
                            <div class="info-icon">📍</div>
                            <div class="info-content">
                                <h3>Our Location</h3>
                                <p>123 Robert Mugabe Road<br>Eastgate Building, 3rd Floor<br>Harare, Zimbabwe</p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">📞</div>
                            <div class="info-content">
                                <h3>Call Us</h3>
                                <p>+263 242 123 456<br>+263 775 123 456</p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">✉️</div>
                            <div class="info-content">
                                <h3>Email Us</h3>
                                <p><a href="mailto:info@shasha.co.zw">info@shasha.co.zw</a><br>
                                <a href="mailto:support@shasha.co.zw">support@shasha.co.zw</a></p>
                            </div>
                        </div>
                        
                        <div class="social-links">
                            <a href="#" class="social-link">f</a>
                            <a href="#" class="social-link">in</a>
                            <a href="#" class="social-link">🐦</a>
                            <a href="#" class="social-link">📸</a>
                        </div>
                    </div>
                    
                    <div class="office-hours">
                        <h3>Office Hours</h3>
                        <ul class="hours-list">
                            <li>
                                <span class="day">Monday - Friday</span>
                                <span class="time">8:00 AM - 5:00 PM</span>
                            </li>
                            <li>
                                <span class="day">Saturday</span>
                                <span class="time">9:00 AM - 1:00 PM</span>
                            </li>
                            <li>
                                <span class="day">Sunday</span>
                                <span class="time">Closed</span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="contact-form-container">
                    <h2>Send Us a Message</h2>
                    
                    <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <?php echo $error_message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form action="" method="POST" id="contact-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Your Name *</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Your Email *</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <select id="subject" name="subject" required>
                                <option value="">Select a subject</option>
                                <option value="General Inquiry">General Inquiry</option>
                                <option value="Job Seeker Support">Job Seeker Support</option>
                                <option value="Employer Support">Employer Support</option>
                                <option value="Technical Issue">Technical Issue</option>
                                <option value="Partnerships">Partnerships</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Your Message *</label>
                            <textarea id="message" name="message" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-submit">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="map-section">
        <div class="container">
            <div class="map-container">
                <!-- Replace with actual Google Maps embed code for your location -->
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d30676.926278181763!2d31.023743899999998!3d-17.831773300000002!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1931a4e706bbc151%3A0xa4168fa94a223784!2sHarare%2C%20Zimbabwe!5e0!3m2!1sen!2sus!4v1683894010531!5m2!1sen!2sus" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
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