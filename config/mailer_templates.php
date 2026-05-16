<?php
/**
 * Email Template Functions for cvmaker.ink
 * 
 * Loads HTML email templates and replaces placeholders with dynamic values.
 * Templates are stored in /public_html/templates/emails/
 */

/**
 * Load an email template and replace placeholders
 * 
 * @param string $templateName Template filename (e.g. 'verification.html')
 * @param array $variables Key-value pairs to replace in template (e.g. ['FIRST_NAME' => 'John'])
 * @return string|false Rendered HTML or false on failure
 */
function loadEmailTemplate(string $templateName, array $variables = []): string|false {
    $templatePath = __DIR__ . '/../templates/emails/' . $templateName;
    
    if (!file_exists($templatePath)) {
        error_log("Email template not found: {$templatePath}");
        return false;
    }
    
    $html = file_get_contents($templatePath);
    if ($html === false) {
        error_log("Failed to read email template: {$templatePath}");
        return false;
    }
    
    // Always inject the current year
    $variables['YEAR'] = $variables['YEAR'] ?? date('Y');
    
    // Replace all {{KEY}} placeholders with values
    foreach ($variables as $key => $value) {
        $html = str_replace('{{' . strtoupper($key) . '}}', htmlspecialchars((string)$value), $html);
    }
    
    // Remove any unreplaced placeholders
    $html = preg_replace('/\{\{[A-Z_]+\}\}/', '', $html);
    
    return $html;
}

/**
 * Generate plain-text version from HTML email
 * 
 * @param string $html HTML content
 * @return string Plain text
 */
function htmlToPlainText(string $html): string {
    // Remove style and head sections
    $text = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
    $text = preg_replace('/<head[^>]*>.*?<\/head>/si', '', $text);
    
    // Convert links: <a href="url">text</a> → text (url)
    $text = preg_replace('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si', '$2 ($1)', $text);
    
    // Convert breaks and paragraphs to newlines
    $text = preg_replace('/<br\s*\/?>/si', "\n", $text);
    $text = preg_replace('/<\/p>/si', "\n\n", $text);
    $text = preg_replace('/<\/div>/si', "\n", $text);
    
    // Strip remaining tags
    $text = strip_tags($text);
    
    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Collapse multiple newlines
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    
    return trim($text);
}

/**
 * Send verification email using template
 * 
 * @param PDO $pdo Database connection
 * @param string $toEmail Recipient email
 * @param string $firstName Recipient first name
 * @param string $verifyUrl Verification URL
 * @return array Result with 'success' and 'message'
 */
function sendVerificationEmail(PDO $pdo, string $toEmail, string $firstName, string $verifyUrl): array {
    $subject = 'Verify your CV Maker account';
    
    $html = loadEmailTemplate('verification.html', [
        'FIRST_NAME' => $firstName,
        'VERIFY_URL' => $verifyUrl,
    ]);
    
    if ($html === false) {
        // Fallback to inline template if file is missing
        $html = buildFallbackVerificationEmail($firstName, $verifyUrl);
    }
    
    $altBody = "Hello {$firstName},\n\n" .
               "Thank you for creating your account on CV Maker! Please verify your email address by visiting this link:\n\n" .
               $verifyUrl . "\n\n" .
               "This link expires in 24 hours.\n\n" .
               "If you did not create an account on CV Maker, you can safely ignore this email.\n\n" .
               "CV Maker";
    
    return sendEmail($pdo, $toEmail, $subject, $html, $altBody);
}

/**
 * Send password reset email using template
 * 
 * @param PDO $pdo Database connection
 * @param string $toEmail Recipient email
 * @param string $firstName Recipient first name
 * @param string $resetUrl Password reset URL
 * @return array Result with 'success' and 'message'
 */
function sendPasswordResetEmail(PDO $pdo, string $toEmail, string $firstName, string $resetUrl): array {
    $subject = 'Reset your CV Maker password';
    
    $html = loadEmailTemplate('password-reset.html', [
        'FIRST_NAME' => $firstName,
        'RESET_URL' => $resetUrl,
    ]);
    
    if ($html === false) {
        // Fallback to inline template if file is missing
        $html = buildFallbackPasswordResetEmail($firstName, $resetUrl);
    }
    
    $altBody = "Hello {$firstName},\n\n" .
               "We received a request to reset your password for your CV Maker account.\n\n" .
               "Click the link below to choose a new password:\n\n" .
               $resetUrl . "\n\n" .
               "This link expires in 1 hour.\n\n" .
               "If you did not request a password reset, you can safely ignore this email.\n\n" .
               "CV Maker";
    
    return sendEmail($pdo, $toEmail, $subject, $html, $altBody);
}

/**
 * Send welcome email using template
 * Called after successful email verification
 * 
 * @param PDO $pdo Database connection
 * @param string $toEmail Recipient email
 * @param string $firstName Recipient first name
 * @return array Result with 'success' and 'message'
 */
function sendWelcomeEmail(PDO $pdo, string $toEmail, string $firstName): array {
    $subject = 'Welcome to CV Maker';
    
    $html = loadEmailTemplate('welcome.html', [
        'FIRST_NAME' => $firstName,
    ]);
    
    if ($html === false) {
        // Fallback to inline template if file is missing
        $html = buildFallbackWelcomeEmail($firstName);
    }
    
    $altBody = "Hello {$firstName},\n\n" .
               "Welcome to CV Maker! Your account is now active.\n\n" .
               "Start building your professional resume now:\n" .
               "https://cvmaker.ink/builder.html\n\n" .
               "Here's what you can do:\n" .
               "- Choose from professional resume templates\n" .
               "- Build section by section with guided prompts\n" .
               "- Customize colors, fonts, and layouts\n" .
               "- Download as PDF\n" .
               "- Get AI-powered writing suggestions\n\n" .
               "We're excited to have you!\n\n" .
               "CV Maker";
    
    return sendEmail($pdo, $toEmail, $subject, $html, $altBody);
}

// ─── Fallback inline templates (used if template files are missing) ───

function buildFallbackVerificationEmail(string $firstName, string $verifyUrl): string {
    return '<!DOCTYPE html><html><head><style>' .
        'body{font-family:Arial,sans-serif;line-height:1.6;color:#333;background:#f4f5f7;padding:20px;}' .
        '.container{max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);}' .
        '.header{background:linear-gradient(135deg,#667eea,#764ba2);padding:36px;text-align:center;color:#fff;}' .
        '.header h1{margin:0;font-size:24px;}' .
        '.content{padding:36px;}' .
        '.button{display:inline-block;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff!important;text-decoration:none;padding:15px 36px;border-radius:8px;font-size:16px;font-weight:700;}' .
        '.footer{background:#f8f9fb;padding:24px;text-align:center;font-size:12px;color:#a0aec0;border-top:1px solid #e8ecf1;}' .
        '</style></head><body><div class="container">' .
        '<div class="header"><h1>Verify Your Email Address</h1></div>' .
        '<div class="content">' .
        '<p>Hello ' . htmlspecialchars($firstName) . ',</p>' .
        '<p>Thank you for creating your account on <strong>CV Maker</strong>! Please verify your email address.</p>' .
        '<p style="text-align:center;"><a href="' . $verifyUrl . '" class="button">Verify My Email</a></p>' .
        '<p style="word-break:break-all;color:#667eea;font-size:13px;">' . $verifyUrl . '</p>' .
        '<p><small>⏱ Expires in 24 hours</small></p>' .
        '<p style="font-size:13px;color:#a0aec0;">If you did not create an account, you can safely ignore this email.</p>' .
        '</div>' .
        '<div class="footer">&copy; ' . date('Y') . ' CV Maker</div>' .
        '</div></body></html>';
}

function buildFallbackPasswordResetEmail(string $firstName, string $resetUrl): string {
    return '<!DOCTYPE html><html><head><style>' .
        'body{font-family:Arial,sans-serif;line-height:1.6;color:#333;background:#f4f5f7;padding:20px;}' .
        '.container{max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);}' .
        '.header{background:linear-gradient(135deg,#e53e3e,#c53030);padding:36px;text-align:center;color:#fff;}' .
        '.header h1{margin:0;font-size:24px;}' .
        '.content{padding:36px;}' .
        '.button{display:inline-block;background:linear-gradient(135deg,#e53e3e,#c53030);color:#fff!important;text-decoration:none;padding:15px 36px;border-radius:8px;font-size:16px;font-weight:700;}' .
        '.footer{background:#f8f9fb;padding:24px;text-align:center;font-size:12px;color:#a0aec0;border-top:1px solid #e8ecf1;}' .
        '</style></head><body><div class="container">' .
        '<div class="header"><h1>Reset Your Password</h1></div>' .
        '<div class="content">' .
        '<p>Hello ' . htmlspecialchars($firstName) . ',</p>' .
        '<p>We received a request to reset your password for your <strong>CV Maker</strong> account.</p>' .
        '<p style="text-align:center;"><a href="' . $resetUrl . '" class="button">Reset My Password</a></p>' .
        '<p style="word-break:break-all;color:#e53e3e;font-size:13px;">' . $resetUrl . '</p>' .
        '<p><small>⏱ Expires in 1 hour</small></p>' .
        '<p style="font-size:13px;color:#a0aec0;">If you did not request this, you can safely ignore this email.</p>' .
        '</div>' .
        '<div class="footer">&copy; ' . date('Y') . ' CV Maker</div>' .
        '</div></body></html>';
}

function buildFallbackWelcomeEmail(string $firstName): string {
    return '<!DOCTYPE html><html><head><style>' .
        'body{font-family:Arial,sans-serif;line-height:1.6;color:#333;background:#f4f5f7;padding:20px;}' .
        '.container{max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);}' .
        '.header{background:linear-gradient(135deg,#48bb78,#38a169);padding:36px;text-align:center;color:#fff;}' .
        '.header h1{margin:0;font-size:24px;}' .
        '.content{padding:36px;}' .
        '.button{display:inline-block;background:linear-gradient(135deg,#48bb78,#38a169);color:#fff!important;text-decoration:none;padding:15px 36px;border-radius:8px;font-size:16px;font-weight:700;}' .
        '.footer{background:#f8f9fb;padding:24px;text-align:center;font-size:12px;color:#a0aec0;border-top:1px solid #e8ecf1;}' .
        '</style></head><body><div class="container">' .
        '<div class="header"><h1>Welcome Aboard! 🎉</h1></div>' .
        '<div class="content">' .
        '<p>Hello ' . htmlspecialchars($firstName) . ',</p>' .
        '<p>Your <strong>CV Maker</strong> account is now active. Start building your professional resume!</p>' .
        '<p style="text-align:center;"><a href="https://cvmaker.ink/builder.html" class="button">Start Building My Resume</a></p>' .
        '</div>' .
        '<div class="footer">&copy; ' . date('Y') . ' CV Maker</div>' .
        '</div></body></html>';
}
