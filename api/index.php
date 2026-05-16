<?php
// CV Maker API - Main Router
// File: api/index.php

ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database configuration
require_once __DIR__ . '/../config/database.php';

// Load middleware
require_once __DIR__ . '/../middleware/auth.php';

// Get request path
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/api/';
$path = str_replace($base_path, '', parse_url($request_uri, PHP_URL_PATH));
$method = $_SERVER['REQUEST_METHOD'];

// Route legacy direct admin API script when OpenLiteSpeed rewrites it through this router.
if ($path === 'admin.php') {
    require __DIR__ . '/admin.php';
    exit;
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Router
$response = ['error' => 'Route not found'];
$status_code = 404;

try {
    switch ($path) {
        case 'health':
            $response = [
                'status' => 'ok',
                'timestamp' => date('c'),
                'version' => '1.0.0',
                'php_version' => phpversion()
            ];
            $status_code = 200;
            break;
            
        case 'auth/register':
            if ($method === 'POST') {
                require_once __DIR__ . '/../routes/auth.php';
                $response = registerUser($input);
                $status_code = isset($response['error']) ? 400 : 201;
            }
            break;
            
        case 'auth/login':
            if ($method === 'POST') {
                require_once __DIR__ . '/../routes/auth.php';
                $response = loginUser($input);
                if (isset($response['error'])) {
                    $status_code = ($response['unverified'] ?? false) ? 403 : 401;
                } else {
                    $status_code = 200;
                }
            }
            break;
            
        case 'auth/verify-email':
            if ($method === 'POST') {
                require_once __DIR__ . '/../routes/auth.php';
                $response = verifyEmail($input);
                $status_code = isset($response['error']) ? 400 : 200;
            }
            break;
            
        case 'auth/resend-verification':
            if ($method === 'POST') {
                require_once __DIR__ . '/../routes/auth.php';
                $response = resendVerification($input);
                $status_code = isset($response['error']) ? 400 : 200;
            }
            break;
            
        case 'auth/forgot-password':
            if ($method === 'POST') {
                require_once __DIR__ . '/../routes/auth.php';
                $response = forgotPassword($input);
                $status_code = isset($response['error']) ? 400 : 200;
            }
            break;
            
        case 'auth/reset-password':
            if ($method === 'POST') {
                require_once __DIR__ . '/../routes/auth.php';
                $response = resetPassword($input);
                $status_code = isset($response['error']) ? 400 : 200;
            }
            break;
            
        case 'templates':
            if ($method === 'GET') {
                require_once __DIR__ . '/../routes/templates.php';
                $response = getTemplates();
                $status_code = 200;
            }
            break;

        case 'settings/public':
            if ($method === 'GET') {
                try {
                    $pdo = getDB();
                    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('max_resumes_per_free_user', 'max_resumes_per_user', 'ai_enabled', 'ai_free_uses_per_day', 'site_logo', 'site_favicon', 'site_name')");
                    $stmt->execute();
                    $rows = $stmt->fetchAll();
                    $publicSettings = [];
                    foreach ($rows as $row) {
                        $publicSettings[$row['setting_key']] = $row['setting_value'];
                    }
                    // Provide defaults if not set
                    $response = [
                        'max_resumes_per_free_user' => intval($publicSettings['max_resumes_per_free_user'] ?? 1),
                        'max_resumes_per_user' => intval($publicSettings['max_resumes_per_user'] ?? 10),
                        'ai_enabled' => ($publicSettings['ai_enabled'] ?? 'disabled') === 'enabled',
                        'ai_free_uses_per_day' => intval($publicSettings['ai_free_uses_per_day'] ?? 0),
                        'site_name' => $publicSettings['site_name'] ?? 'cvmaker.ink',
                        'site_logo' => $publicSettings['site_logo'] ?? null,
                        'site_favicon' => $publicSettings['site_favicon'] ?? null
                    ];
                    $status_code = 200;
                } catch (Exception $e) {
                    $response = ['error' => 'Failed to load public settings'];
                    $status_code = 500;
                }
            }
            break;

        case 'payments':
            require_once __DIR__ . '/payments.php';
            exit;

        case 'ai/generate-summary':
            if ($method === 'POST') {
                require_once __DIR__ . '/../routes/ai.php';
                $response = generateSummary($input);
                $status_code = isset($response['error']) ? (($response['upgrade_required'] ?? false) ? 403 : (http_response_code() ?: 400)) : 200;
            }
            break;

        case 'newsletter/subscribe':
            if ($method === 'POST') {
                require_once __DIR__ . '/../routes/newsletter.php';
                $response = subscribeNewsletter($input);
                $status_code = isset($response['error']) ? 400 : 200;
            }
            break;
            
        case 'resumes':
            require_once __DIR__ . '/../routes/resumes.php';
            $user = requireAuth();
            
            switch ($method) {
                case 'GET':
                    $response = getUserResumes();
                    $status_code = isset($response['error']) ? 404 : 200;
                    break;
                    
                case 'POST':
                    $response = createResume($input);
                    $status_code = isset($response['error']) ? 400 : 201;
                    break;
                    
                default:
                    $response = ['error' => 'Method not allowed'];
                    $status_code = 405;
                    break;
            }
            break;

        case 'resume/import':
            if ($method === 'POST') {
                require_once __DIR__ . '/../routes/resume_import.php';
                $response = importResumeFromUpload();
                $status_code = isset($response['error']) ? 400 : 200;
            } else {
                $response = ['error' => 'Method not allowed'];
                $status_code = 405;
            }
            break;

        case 'resume/upload-photo':
            if ($method === 'POST') {
                require_once __DIR__ . '/../routes/resume_photo_upload.php';
                $response = uploadResumePhoto();
                $status_code = isset($response['error']) ? 400 : 200;
            } else {
                $response = ['error' => 'Method not allowed'];
                $status_code = 405;
            }
            break;
            
        default:
            if ($path === 'payments' || strpos($path, 'payments/') === 0) {
                require_once __DIR__ . '/payments.php';
                exit;
            }

            // Check if path starts with 'resumes/' for specific resume operations
            if (strpos($path, 'resumes/') === 0) {
                $id = str_replace('resumes/', '', $path);
                require_once __DIR__ . '/../routes/resumes.php';
                
                switch ($method) {
                    case 'GET':
                        $response = getResume($id);
                        $status_code = isset($response['error']) ? 404 : 200;
                        break;
                        
                    case 'PUT':
                        $response = updateResume($id, $input);
                        $status_code = isset($response['error']) ? 400 : 200;
                        break;
                        
                    case 'DELETE':
                        $response = deleteResume($id);
                        $status_code = isset($response['error']) ? 400 : 200;
                        break;
                        
                    default:
                        $response = ['error' => 'Method not allowed'];
                        $status_code = 405;
                        break;
                }
            }
            break;
    }
} catch (Throwable $e) {
    $response = ['error' => 'Internal server error'];
    $status_code = 500;
    error_log($e->getMessage());
}

$buffer = ob_get_clean();
if (!empty($buffer)) {
    error_log('API unexpected output: ' . trim($buffer));
}

// Send response
http_response_code($status_code);
$json = json_encode($response);
if ($json === false) {
    array_walk_recursive($response, function (&$value) {
        if (is_string($value)) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252, ASCII');
        }
    });
    $json = json_encode($response);
}
if ($json === false) {
    $json = json_encode(['error' => 'Response encoding failed']);
    if ($status_code < 400) {
        http_response_code(500);
    }
}
echo $json;