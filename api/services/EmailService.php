<?php
// api/services/EmailService.php
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(true);

        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['SMTP_USERNAME'] ?? '202211866@gordoncollege.edu.ph';
        $this->mailer->Password = $_ENV['SMTP_PASSWORD'] ?? 'sysftsbjrdnutkgo';
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = $_ENV['SMTP_PORT'] ?? 587;

        // Additional Gmail-specific settings for better reliability
        $this->mailer->SMTPKeepAlive = true;
        $this->mailer->Timeout = 30;
        $this->mailer->SMTPDebug = 0; // Set to 2 for debugging if needed

        // Default sender
        $this->mailer->setFrom(
            $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@smartscribe.com',
            $_ENV['SMTP_FROM_NAME'] ?? 'SmartScribe'
        );
    }

    public function sendPasswordResetEmail($toEmail, $resetLink, $userName = null) {
        try {
            // Recipients
            $this->mailer->addAddress($toEmail, $userName);

            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Reset Your SmartScribe Password';

            $htmlBody = $this->getPasswordResetEmailTemplate($resetLink, $userName);
            $textBody = $this->getPasswordResetEmailText($resetLink, $userName);

            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = $textBody;

            $this->mailer->send();
            return ['success' => true, 'message' => 'Password reset email sent successfully'];

        } catch (Exception $e) {
            // Enhanced error logging
            $errorMessage = $e->getMessage();
            $smtpError = $this->mailer->ErrorInfo;

            error_log("=== EMAIL SEND FAILURE ===");
            error_log("Time: " . date('Y-m-d H:i:s'));
            error_log("To: " . $toEmail);
            error_log("Exception: " . $errorMessage);
            error_log("SMTP Error: " . $smtpError);
            error_log("SMTP Host: " . $this->mailer->Host);
            error_log("SMTP Port: " . $this->mailer->Port);
            error_log("SMTP Username: " . $this->mailer->Username);
            error_log("From Email: " . $this->mailer->From);
            error_log("==========================");

            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $errorMessage,
                'smtp_error' => $smtpError,
                'debug_info' => [
                    'host' => $this->mailer->Host,
                    'port' => $this->mailer->Port,
                    'from' => $this->mailer->From
                ]
            ];
        }
    }

    private function getPasswordResetEmailTemplate($resetLink, $userName = null) {
        $greeting = $userName ? "Hello $userName," : "Hello,";

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Reset Your Password</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 SmartScribe</h1>
                    <h2>Password Reset Request</h2>
                </div>
                <div class='content'>
                    <p>$greeting</p>

                    <p>You have requested to reset your password for your SmartScribe account. Click the button below to create a new password:</p>

                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$resetLink' class='button'>Reset My Password</a>
                    </div>

                    <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background: #f0f0f0; padding: 10px; border-radius: 5px; font-family: monospace;'>$resetLink</p>

                    <div class='warning'>
                        <strong>⚠️ Security Notice:</strong><br>
                        This link will expire in 1 hour for your security.<br>
                        If you didn't request this password reset, please ignore this email.
                    </div>

                    <p>If you have any questions, please contact our support team.</p>

                    <p>Best regards,<br>The SmartScribe Team</p>
                </div>
                <div class='footer'>
                    <p>This email was sent to you because a password reset was requested for your SmartScribe account.</p>
                    <p>© 2025 SmartScribe. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function getPasswordResetEmailText($resetLink, $userName = null) {
        $greeting = $userName ? "Hello $userName," : "Hello,";

        return "$greeting

You have requested to reset your password for your SmartScribe account.

To reset your password, please visit: $resetLink

This link will expire in 1 hour for your security.

If you didn't request this password reset, please ignore this email.

If you have any questions, please contact our support team.

Best regards,
The SmartScribe Team

---
This email was sent to you because a password reset was requested for your SmartScribe account.
© 2025 SmartScribe. All rights reserved.";
    }
}
?>