<?php
// JWT Authentication Middleware for PHP

require_once __DIR__ . '/../config/payment_settings.php';

// Set JWT_SECRET to a long random value in production.
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'change-this-jwt-secret-in-production');
define('JWT_EXPIRES_IN', 7 * 24 * 60 * 60); // 7 days

function generateJWT($user_id) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

    $time = time();
    $payload = json_encode([
        'iat' => $time,
        'exp' => $time + JWT_EXPIRES_IN,
        'sub' => $user_id
    ]);

    $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    $signature = hash_hmac('sha256', $base64_header . "." . $base64_payload, JWT_SECRET, true);
    $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $base64_header . "." . $base64_payload . "." . $base64_signature;
}

function validateJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    $header = $parts[0];
    $payload = $parts[1];
    $signature = $parts[2];

    $expected_signature = hash_hmac('sha256', $header . "." . $payload, JWT_SECRET, true);
    $base64_expected = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expected_signature));

    if (!hash_equals($base64_expected, $signature)) {
        return false;
    }

    $decoded_payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

    if (!$decoded_payload) {
        return false;
    }

    if (isset($decoded_payload['exp']) && $decoded_payload['exp'] < time()) {
        return false;
    }

    return $decoded_payload;
}

function getAuthorizationHeaderValue() {
    $candidates = [];

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, 'Authorization') === 0) {
                    return $value;
                }
            }
        }
    }

    $serverKeys = ['HTTP_AUTHORIZATION', 'Authorization', 'REDIRECT_HTTP_AUTHORIZATION'];
    foreach ($serverKeys as $key) {
        if (!empty($_SERVER[$key])) {
            return $_SERVER[$key];
        }
    }

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, 'Authorization') === 0) {
                    return $value;
                }
            }
        }
    }

    return '';
}

function getCurrentUser() {
    $auth_header = getAuthorizationHeaderValue();

    if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        return null;
    }

    $token = $matches[1];
    $payload = validateJWT($token);

    if (!$payload) {
        return null;
    }

    $pdo = getDB();
    enforceUserSubscription($pdo, (int)$payload['sub']);

    $stmt = $pdo->prepare("
        SELECT id, email, first_name, last_name, role, subscription_tier, subscription_expires, ai_enabled, is_active, force_password_change, last_login_at
        FROM users WHERE id = ?
    ");
    $stmt->execute([$payload['sub']]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active']) {
        return null;
    }

    $user['subscription_tier'] = normalizeSubscriptionTier($user);
    if ($user['subscription_tier'] === 'free' && !empty($user['subscription_expires']) && strtotime($user['subscription_expires']) < strtotime(date('Y-m-d'))) {
        $user['subscription_expires'] = null;
    }

    return $user;
}

function requireAuth() {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    return $user;
}

function requireAdmin() {
    $user = requireAuth();
    if (!in_array(($user['role'] ?? 'user'), ['admin', 'super_admin'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    return $user;
}

function requireSuperAdmin() {
    $user = requireAuth();
    if (($user['role'] ?? 'user') !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Super admin access required']);
        exit;
    }
    return $user;
}
