<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mail {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        
        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host = MAIL_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = MAIL_USERNAME;
        $this->mailer->Password = MAIL_PASSWORD;
        $this->mailer->SMTPSecure = MAIL_ENCRYPTION;
        $this->mailer->Port = MAIL_PORT;
        
        // Default sender
        $this->mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    }
    
    public function sendPasswordReset($to, $resetLink) {
        try {
            $this->mailer->addAddress($to);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Password Reset Request - ShaSha CJRS';
            
            // Email body
            $body = "
                <h2>Password Reset Request</h2>
                <p>Hello,</p>
                <p>We received a request to reset your password. Click the link below to reset it:</p>
                <p><a href='{$resetLink}' style='background-color: #0056b3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                <p>If you did not request this, please ignore this email.</p>
                <p>This link will expire in 1 hour.</p>
                <br>
                <p>Best regards,<br>ShaSha CJRS Team</p>
            ";
            
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Mail Error: " . $e->getMessage());
            return false;
        }
    }
} 