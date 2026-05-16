<?php
// Authentication routes

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/mailer_templates.php';

function registerUser($data) {
    $pdo = getDB();
    
    // Validate input
    if (empty($data['email']) || empty($data['password']) || empty($data['first_name']) || empty($data['last_name'])) {
        return ['error' => 'All fields are required'];
    }
    
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $password = $data['password'];
    $first_name = htmlspecialchars($data['first_name']);
    $last_name = htmlspecialchars($data['last_name']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Invalid email format'];
    }
    
    if (strlen($password) < 8) {
        return ['error' => 'Password must be at least 8 characters'];
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, email_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        if ($existingUser['email_verified']) {
            return ['error' => 'Email already registered'];
        }
        // User exists but not verified - delete old account so they can re-register
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND email_verified = 0");
        $stmt->execute([$existingUser['id']]);
        // Also clean up any old verification tokens
        $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?");
        $stmt->execute([$existingUser['id']]);
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_ARGON2ID);
    
    // Insert user with email_verified = 0
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, role, subscription_tier, email_verified)
        VALUES (?, ?, ?, ?, 'user', 'free', 0)
    ");
    
    try {
        $stmt->execute([$email, $password_hash, $first_name, $last_name]);
        $user_id = $pdo->lastInsertId();
        
        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Save verification token
        $stmt = $pdo->prepare("
            INSERT INTO email_verifications (user_id, token, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $token, $expires_at]);
        
        // Send verification email
        $verify_link = "https://cvmaker.ink/verify-email.html?token=" . $token;
        $email_result = sendVerificationEmail($pdo, $email, $first_name, $verify_link);
        
        // Return message asking user to verify email (no JWT token - they can't log in yet)
        return [
            'message' => 'Registration successful. Please check your email to verify your account.',
            'email_sent' => $email_result['success'],
            'user' => [
                'id' => $user_id,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'subscription_tier' => 'free',
                'email_verified' => false
            ]
        ];
    } catch (PDOException $e) {
        return ['error' => 'Registration failed'];
    }
}

function loginUser($data) {
    $pdo = getDB();
    
    if (empty($data['email']) || empty($data['password'])) {
        return ['error' => 'Email and password required'];
    }
    
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $password = $data['password'];
    
    // Get user
    $stmt = $pdo->prepare("
        SELECT id, email, password_hash, first_name, last_name, role, subscription_tier, is_active, email_verified, force_password_change
        FROM users WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['error' => 'Invalid credentials'];
    }
    
    if (!$user['is_active']) {
        return ['error' => 'Account deactivated'];
    }
    
    // Check email verification
    if (!$user['email_verified']) {
        return ['error' => 'Please verify your email first. Check your inbox or resend verification email.', 'unverified' => true, 'email' => $email];
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        return ['error' => 'Invalid credentials'];
    }
    
    $stmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Generate token
    $token = generateJWT($user['id']);
    
    return [
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role'] ?? 'user',
            'subscription_tier' => $user['subscription_tier'],
            'force_password_change' => (int)($user['force_password_change'] ?? 0)
        ]
    ];
}

function verifyEmail($data) {
    $pdo = getDB();
    
    if (empty($data['token'])) {
        return ['error' => 'Verification token is required'];
    }
    
    $token = $data['token'];
    
    // Look up token
    $stmt = $pdo->prepare("
        SELECT ev.*, u.email, u.first_name
        FROM email_verifications ev
        JOIN users u ON ev.user_id = u.id
        WHERE ev.token = ?
    ");
    $stmt->execute([$token]);
    $verification = $stmt->fetch();
    
    if (!$verification) {
        return ['error' => 'Invalid verification link. The link may have already been used or does not exist.', 'can_resend' => true];
    }
    
    // Check if already verified
    $stmt = $pdo->prepare("SELECT email_verified FROM users WHERE id = ?");
    $stmt->execute([$verification['user_id']]);
    $user = $stmt->fetch();
    
    if ($user['email_verified']) {
        return ['error' => 'Email is already verified. You can sign in now.', 'already_verified' => true];
    }
    
    // Check expiry
    if (strtotime($verification['expires_at']) < time()) {
        return ['error' => 'Verification link has expired. Please request a new one.', 'can_resend' => true, 'expired' => true];
    }
    
    // Mark email as verified
    $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
    $stmt->execute([$verification['user_id']]);
    
    // Delete used token
    $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE token = ?");
    $stmt->execute([$token]);
    
    // Send welcome email (non-blocking — failure doesn't affect verification)
    try {
        sendWelcomeEmail($pdo, $verification['email'], $verification['first_name']);
    } catch (Exception $e) {
        // Log but don't fail verification if welcome email fails
        error_log('Failed to send welcome email: ' . $e->getMessage());
    }
    
    return [
        'message' => 'Email verified successfully! You can now sign in to your account.',
        'email' => $verification['email']
    ];
}

function resendVerification($data) {
    $pdo = getDB();
    
    if (empty($data['email'])) {
        return ['error' => 'Email address is required'];
    }
    
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Invalid email format'];
    }
    
    // Find user
    $stmt = $pdo->prepare("SELECT id, first_name, email_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Don't reveal whether email exists
        return ['message' => 'If an account with that email exists and is unverified, a verification email has been sent.'];
    }
    
    if ($user['email_verified']) {
        return ['error' => 'This email is already verified. You can sign in.'];
    }
    
    // Delete any existing tokens for this user
    $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    
    // Generate new token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $pdo->prepare("
        INSERT INTO email_verifications (user_id, token, expires_at, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$user['id'], $token, $expires_at]);
    
    // Send verification email
    $verify_link = "https://cvmaker.ink/verify-email.html?token=" . $token;
    $first_name = $user['first_name'];
    
    $email_result = sendVerificationEmail($pdo, $email, $first_name, $verify_link);
    
    if (!$email_result['success']) {
        return ['error' => 'Failed to send verification email. Please try again later.'];
    }
    
    return [
        'message' => 'Verification email has been sent. Please check your inbox.'
    ];
}
function forgotPassword($data) {
    $pdo = getDB();
    
    if (empty($data['email'])) {
        return ['error' => 'Email address is required'];
    }
    
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Invalid email format'];
    }
    
    // Clean up expired tokens for this email
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE email = ? AND expires_at < NOW()');
    $stmt->execute([$email]);
    
    // Check if user exists and is verified
    $stmt = $pdo->prepare('SELECT id, first_name, email_verified FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !$user['email_verified']) {
        // Don't reveal whether email exists - always show same message
        return ['message' => 'If this email exists you will receive a reset link shortly'];
    }
    
    // Delete any existing tokens for this email
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE email = ?');
    $stmt->execute([$email]);
    
    // Generate secure token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Save token
    $stmt = $pdo->prepare('INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$email, $token, $expires_at]);
    
    // Send reset email
    $reset_link = 'https://cvmaker.ink/reset-password.html?token=' . $token;
    $first_name = $user['first_name'];
    
    $email_result = sendPasswordResetEmail($pdo, $email, $first_name, $reset_link);
    
    // Always return same message regardless of email send result
    return ['message' => 'If this email exists you will receive a reset link shortly'];
}

function resetPassword($data) {
    $pdo = getDB();
    
    if (empty($data['token']) || empty($data['password'])) {
        return ['error' => 'Token and new password are required'];
    }
    
    $token = $data['token'];
    $password = $data['password'];
    
    // Password validation
    if (strlen($password) < 8) {
        return ['error' => 'Password must be at least 8 characters'];
    }
    
    // Look up token
    $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ?');
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        return ['error' => 'Invalid or expired reset link. Please request a new password reset.', 'expired' => true];
    }
    
    // Check expiry
    if (strtotime($reset['expires_at']) < time()) {
        // Delete expired token
        $stmt = $pdo->prepare('DELETE FROM password_resets WHERE token = ?');
        $stmt->execute([$token]);
        return ['error' => 'This reset link has expired. Please request a new password reset.', 'expired' => true];
    }
    
    // Update user password
    $password_hash = password_hash($password, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
    $stmt->execute([$password_hash, $reset['email']]);
    
    // Delete used token
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE token = ?');
    $stmt->execute([$token]);
    
    // Also delete any other tokens for this email
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE email = ?');
    $stmt->execute([$reset['email']]);
    
    return ['message' => 'Password reset successful. You can now sign in with your new password.'];
}
