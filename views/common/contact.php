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
    </style>
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
                    <li><a href="<?php echo SITE_URL; ?>/views/common/home.php">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="auth-required">Jobs</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="auth-required">Companies</a></li>
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

    <script>
        // Redirect auth-required links to login page
        document.addEventListener('DOMContentLoaded', function() {
            // This script ensures that any element with auth-required class redirects to login
            const authLinks = document.querySelectorAll('.auth-required');
            
            authLinks.forEach(link => {
                link.addEventListener('click', function(event) {
                    // Add any parameters you want to pass to login page
                    // For example, a redirect parameter to come back after login
                    const currentPath = window.location.pathname;
                    link.href = `<?php echo SITE_URL; ?>/views/auth/login.php?redirect=${encodeURIComponent(currentPath)}`;
                });
            });
            
            // Form validation
            const contactForm = document.getElementById('contact-form');
            if (contactForm) {
                contactForm.addEventListener('submit', function(event) {
                    let valid = true;
                    const requiredFields = contactForm.querySelectorAll('[required]');
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = '#dc3545';
                        } else {
                            field.style.borderColor = '#d9e1ec';
                        }
                    });
                    
                    if (!valid) {
                        event.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            }
        });
    </script>
</body>
</html>