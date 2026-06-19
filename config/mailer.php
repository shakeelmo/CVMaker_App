<?php
/**
 * Email Configuration and Mailer for cvmaker.ink
 * 
 * This file provides SMTP email functionality using PHPMailer
 * Settings are read from the database settings table
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/payment_settings.php'; // Contains getSetting() function
// Vendor autoload - check both possible locations
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
}
require_once $vendorAutoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Get email settings from database
 * @param PDO $pdo Database connection
 * @return array Email settings
 */
function getEmailSettings(PDO $pdo): array {
    return [
        'smtp_host' => getSetting($pdo, 'smtp_host', ''),
        'smtp_port' => (int)getSetting($pdo, 'smtp_port', '587'),
        'smtp_username' => getSetting($pdo, 'smtp_username', ''),
        'smtp_password' => getSetting($pdo, 'smtp_password', ''),
        'smtp_from_email' => getSetting($pdo, 'smtp_from_email', ''),
        'smtp_from_name' => getSetting($pdo, 'smtp_from_name', 'CV Maker'),
        'smtp_encryption' => getSetting($pdo, 'smtp_encryption', 'tls'),
    ];
}

/**
 * Check if email is configured
 * @param PDO $pdo Database connection
 * @return bool True if email settings are configured
 */
function isEmailConfigured(PDO $pdo): bool {
    $settings = getEmailSettings($pdo);
    return !empty($settings['smtp_host']) 
        && !empty($settings['smtp_username']) 
        && !empty($settings['smtp_password'])
        && !empty($settings['smtp_from_email']);
}

/**
 * Send an email using PHPMailer
 * 
 * @param PDO $pdo Database connection
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body HTML body content
 * @param string $altBody Plain text alternative (optional)
 * @param array $attachments Array of file paths to attach (optional)
 * @return array Result with 'success' boolean and 'message' string
 */
function sendEmail(PDO $pdo, string $to, string $subject, string $body, string $altBody = '', array $attachments = []): array {
    // Check if email is configured
    if (!isEmailConfigured($pdo)) {
        // Keep failed configuration attempts visible in the admin email log.
        logEmail($pdo, $to, $subject, 'failed', 'Email not configured or SMTP password could not be decrypted.');
        return [
            'success' => false,
            'message' => 'Email not configured. Please configure SMTP settings in admin dashboard.'
        ];
    }

    $settings = getEmailSettings($pdo);

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->Port = $settings['smtp_port'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'];
        $mail->Password = $settings['smtp_password'];
        
        // Encryption
        switch (strtolower($settings['smtp_encryption'])) {
            case 'ssl':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                break;
            case 'tls':
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                break;
            case 'none':
            default:
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                // If port is 465, use SSL by default
                if ($settings['smtp_port'] == 465) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                }
                break;
        }

        // Recipients
        $mail->setFrom($settings['smtp_from_email'], $settings['smtp_from_name']);
        $mail->addAddress($to);
        $mail->addReplyTo($settings['smtp_from_email'], $settings['smtp_from_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        // Attachments
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $mail->addAttachment($attachment);
            }
        }

        // Send email
        $mail->send();
        
        // Log the successful email
        logEmail($pdo, $to, $subject, 'sent');
        
        return [
            'success' => true,
            'message' => 'Email sent successfully'
        ];

    } catch (Exception $e) {
        // Log the failed email
        logEmail($pdo, $to, $subject, 'failed', $mail->ErrorInfo);
        
        return [
            'success' => false,
            'message' => 'Email sending failed: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * Send a test email to verify configuration
 * 
 * @param PDO $pdo Database connection
 * @param string $testEmail Email address to send test to
 * @return array Result with 'success' boolean and 'message' string
 */
function sendTestEmail(PDO $pdo, string $testEmail): array {
    $subject = 'Test Email from CV Maker';
    $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .footer { text-align: center; color: #666; margin-top: 20px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✅ Email Test Successful!</h1>
                </div>
                <div class="content">
                    <p>Hello,</p>
                    <p>This is a test email from <strong>CV Maker</strong>. If you received this, your email configuration is working correctly!</p>
                    <p><strong>Configuration details:</strong></p>
                    <ul>
                        <li>Sent at: ' . date('Y-m-d H:i:s') . '</li>
                        <li>Recipient: ' . htmlspecialchars($testEmail) . '</li>
                    </ul>
                    <p>Your email system is now ready to send welcome emails, password resets, and notifications.</p>
                </div>
                <div class="footer">
                    <p>This is an automated message from CV Maker</p>
                </div>
            </div>
        </body>
        </html>
    ';
    
    $altBody = "Email Test Successful!\n\n" .
               "This is a test email from CV Maker. If you received this, your email configuration is working correctly!\n\n" .
               "Sent at: " . date('Y-m-d H:i:s') . "\n" .
               "Recipient: {$testEmail}\n\n" .
               "Your email system is now ready to send welcome emails, password resets, and notifications.\n\n" .
               "This is an automated message from CV Maker";
    
    return sendEmail($pdo, $testEmail, $subject, $body, $altBody);
}

/**
 * Log email activity to database
 * 
 * @param PDO $pdo Database connection
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $status sent|failed
 * @param string $error Error message if failed
 * @return void
 */
function logEmail(PDO $pdo, string $to, string $subject, string $status, string $error = ''): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_log (recipient_email, subject, status, error_message) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $to,
            $subject,
            $status,
            $error
        ]);
    } catch (PDOException $e) {
        // Silently fail - logging is not critical
        error_log('Failed to log email: ' . $e->getMessage());
    }
}

/**
 * Get recent email logs
 * 
 * @param PDO $pdo Database connection
 * @param int $limit Number of records to return
 * @return array Array of email log records
 */
function getEmailLogs(PDO $pdo, int $limit = 50): array {
    try {
        $timestampColumn = 'created_at';
        try {
            $columns = $pdo->query("DESCRIBE email_log")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('sent_at', $columns, true)) {
                $timestampColumn = 'sent_at';
            }
        } catch (PDOException $e) {
            $timestampColumn = 'created_at';
        }

        $stmt = $pdo->prepare("
            SELECT * FROM email_log 
            ORDER BY {$timestampColumn} DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
