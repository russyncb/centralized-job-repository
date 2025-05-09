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
        }
        
        @media (max-width: 576px) {
            .team-cards, .stats-container {
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
                    <span class="icon">📊</span> ShaSha CJRS
                </a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="<?php echo SITE_URL; ?>/views/common/home.php">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="auth-required">Jobs</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="auth-required">Companies</a></li>
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
            <p>Zimbabwe's leading job recruitment platform connecting talented professionals with top employers since 2015.</p>
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
                    <p>ShaSha CJRS (Comprehensive Job Recruitment System) was founded in 2015 with a simple yet powerful vision - to transform how job seekers and employers connect in Zimbabwe. In a market where finding the right talent or the right opportunity was often challenging, we set out to create a transparent, efficient platform that would serve both job seekers and employers.</p>
                    <p>Starting with a small team of just 5 people, we've grown to become Zimbabwe's most trusted job portal with over 50,000 registered job seekers and more than 2,000 verified employers. What sets us apart is our commitment to quality, verification of all job postings, and our deep understanding of the local job market.</p>
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
                        <h3>Tendai Moyo</h3>
                        <p class="position">Founder & CEO</p>
                        <p class="bio">With over 15 years in HR and recruitment, Tendai founded ShaSha with a vision to transform Zimbabwe's job market.</p>
                    </div>
                </div>
                
                <div class="team-card">
                    <div class="team-image">
                        <img src="/api/placeholder/300/300" alt="Team Member" />
                    </div>
                    <div class="team-info">
                        <h3>Chiedza Nyamapfeni</h3>
                        <p class="position">Chief Operations Officer</p>
                        <p class="bio">Chiedza oversees daily operations and ensures that both employers and job seekers receive exceptional service.</p>
                    </div>
                </div>
                
                <div class="team-card">
                    <div class="team-image">
                        <img src="/api/placeholder/300/300" alt="Team Member" />
                    </div>
                    <div class="team-info">
                        <h3>Tatenda Chikwanha</h3>
                        <p class="position">Chief Technology Officer</p>
                        <p class="bio">Tatenda leads our tech development, constantly enhancing the platform to meet changing market needs.</p>
                    </div>
                </div>
                
                <div class="team-card">
                    <div class="team-image">
                        <img src="/api/placeholder/300/300" alt="Team Member" />
                    </div>
                    <div class="team-info">
                        <h3>Rutendo Madziwa</h3>
                        <p class="position">Head of Partnerships</p>
                        <p class="bio">Rutendo builds relationships with key employers and organizations across Zimbabwe to expand opportunities.</p>
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
                    <div class="stat-number">50K+</div>
                    <div class="stat-description">Registered Job Seekers</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">2,000+</div>
                    <div class="stat-description">Verified Employers</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">15K+</div>
                    <div class="stat-description">Successful Placements</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">97%</div>
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
        });
    </script>
</body>
</html>