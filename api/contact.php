<?php
/**
 * Contact Form API
 * Handles contact form submissions and sends SMTP email notifications
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

const SMTP_CONNECT_TIMEOUT_SECONDS = 5;

function smtpExpect($socket, $codes) {
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (preg_match('/^\d{3} /', $line)) break;
    }
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, (array)$codes, true)) {
        throw new Exception('SMTP error: ' . trim($response));
    }
    return $response;
}

function smtpCommand($socket, $command, $codes) {
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $codes);
}

function sendSmtpMail($toEmail, $toName, $subject, $htmlBody, $textBody = '') {
    // Read SMTP settings from database
    global $pdo;
    $smtpHost = getSetting($pdo, 'smtp_host', 'smtp.gmail.com');
    $smtpPort = (int)getSetting($pdo, 'smtp_port', '587');
    $smtpUser = getSetting($pdo, 'smtp_username', '');
    $smtpPass = getSetting($pdo, 'smtp_password', '');
    $fromEmail = getSetting($pdo, 'smtp_from_email', 'support@cvmaker.ink');
    $fromName = getSetting($pdo, 'smtp_from_name', 'Customer Support');
    $smtpEncryption = getSetting($pdo, 'smtp_encryption', 'tls');

    if (empty($smtpUser) || empty($smtpPass)) {
        throw new Exception('SMTP not configured. Set email settings in admin dashboard.');
    }

    $boundary = 'b_' . bin2hex(random_bytes(12));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $toHeader = $toName ? sprintf('"%s" <%s>', addslashes($toName), $toEmail) : $toEmail;
    $headers = [
        "Date: " . date(DATE_RFC2822),
        "From: {$fromName} <{$fromEmail}>",
        "To: {$toHeader}",
        "Reply-To: {$fromEmail}",
        "Subject: {$encodedSubject}",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\""
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= ($textBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody))) . "\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlBody . "\r\n";
    $message .= "--{$boundary}--\r\n";

    // SSL (port 465) - direct TLS connection
    if (strtolower($smtpEncryption) === 'ssl' || $smtpPort == 465) {
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $socket = stream_socket_client("ssl://{$smtpHost}:{$smtpPort}", $errno, $errstr, SMTP_CONNECT_TIMEOUT_SECONDS, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new Exception("SMTP connection failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($socket, SMTP_CONNECT_TIMEOUT_SECONDS);
        try {
            smtpExpect($socket, [220]);
            smtpCommand($socket, 'EHLO cvmaker.ink', [250]);
            smtpCommand($socket, 'AUTH LOGIN', [334]);
            smtpCommand($socket, base64_encode($smtpUser), [334]);
            smtpCommand($socket, base64_encode($smtpPass), [235]);
            smtpCommand($socket, "MAIL FROM:<{$fromEmail}>", [250]);
            smtpCommand($socket, "RCPT TO:<{$toEmail}>", [250, 251]);
            smtpCommand($socket, 'DATA', [354]);
            fwrite($socket, $message . "\r\n.\r\n");
            smtpExpect($socket, [250]);
            smtpCommand($socket, 'QUIT', [221]);
            fclose($socket);
            return true;
        } catch (Exception $e) {
            fclose($socket);
            throw $e;
        }
    }

    // TLS (port 587) - STARTTLS
    $socket = stream_socket_client("tcp://{$smtpHost}:{$smtpPort}", $errno, $errstr, SMTP_CONNECT_TIMEOUT_SECONDS, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new Exception("SMTP connection failed: {$errstr} ({$errno})");
    }
    stream_set_timeout($socket, SMTP_CONNECT_TIMEOUT_SECONDS);

    try {
        smtpExpect($socket, [220]);
        smtpCommand($socket, 'EHLO cvmaker.ink', [250]);
        smtpCommand($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception('Failed to enable TLS encryption');
        }
        smtpCommand($socket, 'EHLO cvmaker.ink', [250]);
        smtpCommand($socket, 'AUTH LOGIN', [334]);
        smtpCommand($socket, base64_encode($smtpUser), [334]);
        smtpCommand($socket, base64_encode($smtpPass), [235]);
        smtpCommand($socket, "MAIL FROM:<{$fromEmail}>", [250]);
        smtpCommand($socket, "RCPT TO:<{$toEmail}>", [250, 251]);
        smtpCommand($socket, 'DATA', [354]);
        fwrite($socket, $message . "\r\n.\r\n");
        smtpExpect($socket, [250]);
        smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    } catch (Exception $e) {
        fclose($socket);
        throw $e;
    }
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$subject = trim($data['subject'] ?? '');
$message = trim($data['message'] ?? '');

if ($name === '' || $email === '' || $subject === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM contact_submissions WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stmt->execute([$ipAddress]);
$recentSubmissions = $stmt->fetch()['count'];
if ($recentSubmissions >= 5) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many submissions. Please try again later.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO contact_submissions (name, email, subject, message, status, ip_address, user_agent) VALUES (?, ?, ?, ?, 'new', ?, ?)");
    $stmt->execute([$name, $email, $subject, $message, $ipAddress, $userAgent]);
    $submissionId = $pdo->lastInsertId();

    $htmlBody = "<!DOCTYPE html>
<html>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
        <h2 style='color: #667eea;'>New Contact Form Submission</h2>
        <p><strong>Submission ID:</strong> #{$submissionId}</p>
        <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
        <hr style='border: none; border-top: 1px solid #eee;' />
        <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
        <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
        <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
        <p><strong>Message:</strong></p>
        <div style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>" . nl2br(htmlspecialchars($message)) . "</div>
    </div>
</body>
</html>";

    $textBody = "New Contact Form Submission\n\nSubmission ID: {$submissionId}\nName: {$name}\nEmail: {$email}\nSubject: {$subject}\n\nMessage:\n{$message}";

    $mailSent = false;
    try {
        $recipientEmail = getSetting($pdo, 'contact_email', getSetting($pdo, 'smtp_from_email', 'support@cvmaker.ink'));
        $mailSent = sendSmtpMail($recipientEmail, 'Customer Support', "[cvmaker.ink Contact Form] {$subject}", $htmlBody, $textBody);
    } catch (Throwable $mailError) {
        error_log('Contact notification email failed for submission #' . $submissionId . ': ' . $mailError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your message. We will get back to you soon.',
        'id' => $submissionId,
        'email_sent' => $mailSent
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to process contact submission.',
        'details' => $e->getMessage()
    ]);
}
?>
