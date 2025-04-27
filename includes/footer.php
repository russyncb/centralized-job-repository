</div>
    </div>
    
    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>About ShaSha CJRS</h3>
                    <p>ShaSha is a Centralized Job Repository System designed to connect job seekers and employers in Zimbabwe.</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="<?php echo SITE_URL; ?>/views/common/about.php">About Us</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/views/common/contact.php">Contact Us</a></li>
                        <?php if(!is_logged_in()): ?>
                            <li><a href="<?php echo SITE_URL; ?>/views/auth/login.php">Login</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/views/auth/register.php">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact</h3>
                    <p>Email: info@shasha.com</p>
                    <p>Phone: +263 123 456 789</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ShaSha CJRS. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    <?php if(isset($extra_js)): ?>
        <?php foreach($extra_js as $js): ?>
            <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $js; ?>.js"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>