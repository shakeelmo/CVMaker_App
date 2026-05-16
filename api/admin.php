<?php
/**
 * Complete Admin API - FIXED Version
 * All endpoints working with database integration
 */

session_start();
header('Content-Type: application/json');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_settings.php';
require_once __DIR__ . '/../middleware/auth.php';

// Get JWT token from header
function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $matches = [];
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// Verify admin token
function verifyAdminToken($pdo) {
    $authHeader = getAuthorizationHeaderValue();
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        exit;
    }

    $payload = validateJWT($matches[1]);
    if (!$payload || empty($payload['sub'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, email, role, is_active FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$payload['sub']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || empty($admin['is_active']) || !in_array(($admin['role'] ?? 'user'), ['admin', 'super_admin'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }

    return (int)$admin['id'];
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
if ($action === '' && isset($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $qs);
    if (!empty($qs['action'])) {
        $action = $qs['action'];
    }
}

// Handle different endpoints
switch ($action) {
    // AUTHENTICATION
    case 'login':
        handleLogin($pdo);
        break;
        
    // DASHBOARD STATS
    case 'stats':
        handleStats($pdo);
        break;
        
    // USERS
    case 'users':
        verifyAdminToken($pdo);
        handleUsers($pdo, $method);
        break;
        
    // RESUMES
    case 'resumes':
        verifyAdminToken($pdo);
        handleResumes($pdo, $method);
        break;
        
    // TEMPLATES
    case 'templates':
        verifyAdminToken($pdo);
        handleTemplates($pdo, $method);
        break;
        
    // BLOGS
    case 'blogs':
        verifyAdminToken($pdo);
        handleBlogs($pdo, $method);
        break;

    case 'blog-upload':
        verifyAdminToken($pdo);
        handleBlogUpload($method);
        break;

    case 'branding-upload':
        verifyAdminToken($pdo);
        handleBrandingUpload($pdo, $method);
        break;
        
    // PAGES (CMS)
    case 'pages':
        verifyAdminToken($pdo);
        handlePages($pdo, $method);
        break;
        
    // CONTACT SUBMISSIONS
    case 'contacts':
        verifyAdminToken($pdo);
        handleContacts($pdo, $method);
        break;

    // CONTACT REPLY THREADS
    case 'contact-replies':
        verifyAdminToken($pdo);
        handleContactReplies($pdo, $method);
        break;
        
    // NEWSLETTER
    case 'newsletter':
        verifyAdminToken($pdo);
        handleNewsletter($pdo, $method);
        break;
        
    // NEWSLETTER SUBSCRIBERS
    case 'subscribers':
        verifyAdminToken($pdo);
        handleSubscribers($pdo, $method);
        break;
        
    // EMAIL TEMPLATES
    case 'email-templates':
        verifyAdminToken($pdo);
        handleEmailTemplates($pdo, $method);
        break;
        
    // EMAIL LOG
    case 'email-log':
        verifyAdminToken($pdo);
        handleEmailLog($pdo, $method);
        break;
        
    // SETTINGS
    case 'settings':
        verifyAdminToken($pdo);
        handleSettings($pdo, $method);
        break;
        
    // SEO
    case 'seo':
        verifyAdminToken($pdo);
        handleSEO($pdo, $method);
        break;
        
    // ANALYTICS
    case 'analytics':
        verifyAdminToken($pdo);
        handleAnalytics($pdo, $method);
        break;
        
    // SEND EMAIL
    // TEST EMAIL
    case 'test-email':
        verifyAdminToken($pdo);
        handleTestEmail($pdo);
        break;
    case 'send-email':
        verifyAdminToken($pdo);
        handleSendEmail($pdo);
        break;
        
    // ACTIVITY LOG
    case 'activity':
        verifyAdminToken($pdo);
        handleActivity($pdo);
        break;

    // ADMIN USERS
    case 'admin-users':
        verifyAdminToken($pdo);
        handleAdminUsers($pdo, $method);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}

// ==================== HANDLER FUNCTIONS ====================

function handleLogin($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = trim((string)($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['is_active']) || !in_array(($user['role'] ?? 'user'), ['admin', 'super_admin'], true)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        return;
    }

    $valid = false;
    if (!empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
        $valid = true;
    } elseif (($user['password_hash'] ?? null) === $password) {
        $valid = true;
    }

    if (!$valid) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        return;
    }

    $token = generateJWT($user['id']);

    try {
        $updateStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
    } catch (Throwable $e) {
    }

    logActivity($pdo, 'login', 'Admin logged in: ' . $user['email']);

    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'role' => $user['role'] ?? 'user'
        ]
    ]);
}

function handleStats($pdo) {
    try {
        // Get counts from ACTUAL database
        $userCount = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
        $resumeCount = $pdo->query("SELECT COUNT(*) as count FROM resumes")->fetch()['count'];
        $templateCount = $pdo->query("SELECT COUNT(*) as count FROM templates")->fetch()['count'];
        $blogCount = $pdo->query("SELECT COUNT(*) as count FROM blogs WHERE status='published'")->fetch()['count'];
        $contactCount = $pdo->query("SELECT COUNT(*) as count FROM contact_submissions WHERE status='new'")->fetch()['count'];
        
        // Get new users today
        $newUsersToday = $pdo->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()")->fetch()['count'];
        
        // Get weekly user growth
        $weeklyUsers = $pdo->query("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ")->fetchAll();
        
        // Get template usage
        $templateUsage = $pdo->query("
            SELECT t.name, COUNT(r.id) as count 
            FROM templates t 
            LEFT JOIN resumes r ON t.id = r.template_id 
            GROUP BY t.id 
            ORDER BY count DESC 
            LIMIT 5
        ")->fetchAll();
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'users' => $userCount,
                'resumes' => $resumeCount,
                'templates' => $templateCount,
                'published_blogs' => $blogCount,
                'new_contacts' => $contactCount,
                'new_users_today' => $newUsersToday,
                'weekly_users' => $weeklyUsers,
                'template_usage' => $templateUsage
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load stats', 'message' => $e->getMessage()]);
    }
}

function handleUsers($pdo, $method) {
    $currentAdmin = requireAdmin();

    if ($method === 'GET') {
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 20;
        $search = $_GET['search'] ?? '';
        $offset = ($page - 1) * $limit;
        
        try {
            if ($search) {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.subscription_tier, u.subscription_expires, u.ai_enabled, u.is_active, u.email_verified, u.force_password_change, u.created_at, u.updated_at, u.last_login_at, COUNT(r.id) as resume_count 
                    FROM users u 
                    LEFT JOIN resumes r ON u.id = r.user_id 
                    WHERE u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?
                    GROUP BY u.id 
                    ORDER BY u.created_at DESC 
                    LIMIT ? OFFSET ?
                ");
                $searchParam = "%$search%";
                $stmt->execute([$searchParam, $searchParam, $searchParam, $limit, $offset]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.subscription_tier, u.subscription_expires, u.ai_enabled, u.is_active, u.email_verified, u.force_password_change, u.created_at, u.updated_at, u.last_login_at, COUNT(r.id) as resume_count 
                    FROM users u 
                    LEFT JOIN resumes r ON u.id = r.user_id 
                    GROUP BY u.id 
                    ORDER BY u.created_at DESC 
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$limit, $offset]);
            }
            
            $users = $stmt->fetchAll();
            
            // Get total count
            if ($search) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email LIKE ? OR first_name LIKE ? OR last_name LIKE ?");
                $countStmt->execute([$searchParam, $searchParam, $searchParam]);
            } else {
                $countStmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            }
            $total = $countStmt->fetch()['count'];

            echo json_encode([
                'success' => true,
                'users' => $users,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit)
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load users']);
        }
    } elseif ($method === 'POST' && (($_GET['mode'] ?? '') === 'ai-access')) {
        $id = $_GET['id'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID required']);
            return;
        }

        $aiEnabled = $data['ai_enabled'] ?? null;
        if (!in_array($aiEnabled, [null, 0, 1, '0', '1'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'ai_enabled must be 1, 0, or null']);
            return;
        }
        if ($aiEnabled === '0') $aiEnabled = 0;
        if ($aiEnabled === '1') $aiEnabled = 1;

        try {
            $stmt = $pdo->prepare("UPDATE users SET ai_enabled = ? WHERE id = ? AND role != 'super_admin'");
            $stmt->bindValue(1, $aiEnabled, $aiEnabled === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(2, $id, PDO::PARAM_INT);
            $stmt->execute();
            logActivity($pdo, 'user_update', "Updated user ID: $id ai_enabled to " . var_export($aiEnabled, true));
            echo json_encode(['success' => true, 'ai_enabled' => $aiEnabled]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update AI access']);
        }
    } elseif ($method === 'PUT') {
        $id = $_GET['id'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID required']);
            return;
        }

        $subscriptionTier = strtolower(trim((string)($data['subscription_tier'] ?? '')));
        $subscriptionExpires = trim((string)($data['subscription_expires'] ?? ''));

        if (!in_array($subscriptionTier, ['free', 'pro'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'subscription_tier must be free or pro']);
            return;
        }

        if ($subscriptionExpires === '') {
            $subscriptionExpires = null;
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $subscriptionExpires)) {
            http_response_code(400);
            echo json_encode(['error' => 'subscription_expires must be YYYY-MM-DD or empty']);
            return;
        }

        if ($subscriptionTier === 'free') {
            $subscriptionExpires = null;
        }

        $newRole = trim((string)($data['role'] ?? ''));

        try {
            $targetStmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ? LIMIT 1");
            $targetStmt->execute([$id]);
            $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);
            if (!$targetUser) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                return;
            }
            if (($targetUser['role'] ?? 'user') === 'super_admin' && ($currentAdmin['role'] ?? 'user') !== 'super_admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Only super admin can modify a super admin']);
                return;
            }

            // Handle role change if provided
            if ($newRole !== '' && in_array($newRole, ['user', 'admin', 'super_admin'], true)) {
                // Only super_admin can promote to admin/super_admin or demote from admin
                if ($newRole !== ($targetUser['role'] ?? 'user') && ($currentAdmin['role'] ?? 'user') !== 'super_admin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Only super admin can change user roles']);
                    return;
                }
                // Prevent demoting last super_admin
                if (($targetUser['role'] ?? 'user') === 'super_admin' && $newRole !== 'super_admin') {
                    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'super_admin' AND is_active = 1");
                    $count = $countStmt->fetch(PDO::FETCH_ASSOC);
                    if (($count['count'] ?? 0) <= 1) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Cannot demote the last super admin']);
                        return;
                    }
                }
                $stmt = $pdo->prepare("UPDATE users SET subscription_tier = ?, subscription_expires = ?, role = ? WHERE id = ?");
                $stmt->execute([$subscriptionTier, $subscriptionExpires, $newRole, $id]);
                logActivity($pdo, 'user_update', "Updated user ID: $id subscription to $subscriptionTier, role to $newRole");
            } else {
                $stmt = $pdo->prepare("UPDATE users SET subscription_tier = ?, subscription_expires = ? WHERE id = ?");
                $stmt->execute([$subscriptionTier, $subscriptionExpires, $id]);
                logActivity($pdo, 'user_update', "Updated user ID: $id subscription to $subscriptionTier");
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update user']);
        }
    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID required']);
            return;
        }
        
        try {
            $targetStmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ? LIMIT 1");
            $targetStmt->execute([$id]);
            $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);
            if (!$targetUser) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                return;
            }
            if (($targetUser['role'] ?? 'user') === 'super_admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Super admin users cannot be deleted here']);
                return;
            }
            if ((int)$targetUser['id'] === (int)$currentAdmin['id']) {
                http_response_code(400);
                echo json_encode(['error' => 'You cannot delete your own account here']);
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($pdo, 'user_delete', "Deleted user ID: $id");
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete user']);
        }
    }
}

function handleAdminUsers($pdo, $method) {
    $currentAdmin = requireAdmin();

    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT id, email, first_name, last_name, role, is_active, force_password_change, created_at, updated_at, last_login_at FROM users WHERE role IN ('admin', 'super_admin') ORDER BY FIELD(role, 'super_admin', 'admin'), created_at DESC");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'admins' => $admins]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load admin users']);
        }
        return;
    }

    if ($method === 'POST') {
        if (($currentAdmin['role'] ?? 'user') !== 'super_admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Only super admin can create admin users']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName = trim((string)($data['last_name'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $role = trim((string)($data['role'] ?? 'admin'));

        if (!$email || !$firstName || !$lastName || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'First name, last name, email, and password are required']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid email is required']);
            return;
        }

        if (!in_array($role, ['admin', 'super_admin'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Role must be admin or super_admin']);
            return;
        }

        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 8 characters']);
            return;
        }

        try {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Email already exists']);
                return;
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, subscription_tier, is_active, email_verified, force_password_change, created_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'pro', 1, 1, 1, ?, ?, NOW(), NOW())");
            $stmt->execute([$email, $passwordHash, $firstName, $lastName, $role, $currentAdmin['id'], $currentAdmin['id']]);

            logActivity($pdo, 'admin_create', 'Created admin user: ' . $email . ' (' . $role . ')');
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create admin user']);
        }
        return;
    }

    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        $mode = trim((string)($_GET['mode'] ?? ''));
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Admin user ID required']);
            return;
        }

        try {
            $targetStmt = $pdo->prepare("SELECT id, email, role, is_active FROM users WHERE id = ? AND role IN ('admin', 'super_admin') LIMIT 1");
            $targetStmt->execute([$id]);
            $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) {
                http_response_code(404);
                echo json_encode(['error' => 'Admin user not found']);
                return;
            }

            if (($target['role'] ?? 'user') === 'super_admin' && ($currentAdmin['role'] ?? 'user') !== 'super_admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Only super admin can manage a super admin']);
                return;
            }

            if ($mode === 'reset-password') {
                if (($currentAdmin['role'] ?? 'user') !== 'super_admin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Only super admin can reset admin passwords']);
                    return;
                }
                $password = (string)($data['password'] ?? '');
                if (strlen($password) < 8) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Password must be at least 8 characters']);
                    return;
                }
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = 1, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$passwordHash, $currentAdmin['id'], $id]);
                logActivity($pdo, 'admin_password_reset', 'Reset admin password for: ' . $target['email']);
                echo json_encode(['success' => true]);
                return;
            }

            if ($mode === 'toggle-active') {
                $newActive = !empty($data['is_active']) ? 1 : 0;
                if ((int)$target['id'] === (int)$currentAdmin['id'] && $newActive === 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'You cannot deactivate your own account']);
                    return;
                }
                if (($target['role'] ?? 'user') === 'super_admin' && $newActive === 0) {
                    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'super_admin' AND is_active = 1");
                    $count = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
                    if ($count <= 1) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Cannot deactivate the last active super admin']);
                        return;
                    }
                }
                $stmt = $pdo->prepare("UPDATE users SET is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newActive, $currentAdmin['id'], $id]);
                logActivity($pdo, 'admin_status_update', ($newActive ? 'Activated' : 'Deactivated') . ' admin user: ' . $target['email']);
                echo json_encode(['success' => true]);
                return;
            }

            if ($mode === 'update-profile') {
                $firstName = trim((string)($data['first_name'] ?? ''));
                $lastName = trim((string)($data['last_name'] ?? ''));
                $email = strtolower(trim((string)($data['email'] ?? '')));
                $role = trim((string)($data['role'] ?? ($target['role'] ?? 'admin')));

                if (!$firstName || !$lastName || !$email) {
                    http_response_code(400);
                    echo json_encode(['error' => 'First name, last name, and email are required']);
                    return;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Valid email is required']);
                    return;
                }

                if (!in_array($role, ['admin', 'super_admin'], true)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Role must be admin or super_admin']);
                    return;
                }

                if ($role !== ($target['role'] ?? 'admin') && ($currentAdmin['role'] ?? 'user') !== 'super_admin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Only super admin can change admin roles']);
                    return;
                }

                if (($target['role'] ?? 'user') === 'super_admin' && $role !== 'super_admin') {
                    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'super_admin' AND is_active = 1");
                    $count = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
                    if ($count <= 1) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Cannot demote the last active super admin']);
                        return;
                    }
                    if ((int)$target['id'] === (int)$currentAdmin['id']) {
                        http_response_code(400);
                        echo json_encode(['error' => 'You cannot demote your own super admin account']);
                        return;
                    }
                }

                $emailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $emailStmt->execute([$email, $id]);
                if ($emailStmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Email already exists']);
                    return;
                }

                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $email, $role, $currentAdmin['id'], $id]);
                logActivity($pdo, 'admin_profile_update', 'Updated admin user: ' . $target['email'] . ' -> ' . $email . ' (' . $role . ')');
                echo json_encode(['success' => true]);
                return;
            }

            http_response_code(400);
            echo json_encode(['error' => 'Unsupported admin update mode']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update admin user']);
        }
        return;
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Admin user ID required']);
            return;
        }

        try {
            $targetStmt = $pdo->prepare("SELECT id, email, role, is_active FROM users WHERE id = ? AND role IN ('admin', 'super_admin') LIMIT 1");
            $targetStmt->execute([$id]);
            $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) {
                http_response_code(404);
                echo json_encode(['error' => 'Admin user not found']);
                return;
            }

            if (($target['role'] ?? 'user') === 'super_admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Super admin accounts cannot be deleted. Demote to admin first.']);
                return;
            }

            if ((int)$target['id'] === (int)$currentAdmin['id']) {
                http_response_code(400);
                echo json_encode(['error' => 'You cannot delete your own account']);
                return;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role IN ('admin', 'super_admin')");
            $stmt->execute([$id]);
            logActivity($pdo, 'admin_delete', 'Deleted admin user: ' . $target['email']);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete admin user']);
        }
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function handleResumes($pdo, $method) {
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        $userFilter = trim((string)($_GET['user'] ?? ''));

        try {
            if ($id) {
                $stmt = $pdo->prepare("
                    SELECT r.*, u.first_name, u.last_name, u.email, t.name as template_name, t.category as template_category
                    FROM resumes r
                    JOIN users u ON r.user_id = u.id
                    LEFT JOIN templates t ON r.template_id = t.id
                    WHERE r.id = ?
                    LIMIT 1
                ");
                $stmt->execute([$id]);
                $resume = $stmt->fetch();
                if (!$resume) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Resume not found']);
                    return;
                }
                echo json_encode(['success' => true, 'resume' => $resume]);
                return;
            }

            if ($userFilter !== '') {
                $stmt = $pdo->prepare("
                    SELECT r.*, u.first_name, u.last_name, u.email, t.name as template_name
                    FROM resumes r
                    JOIN users u ON r.user_id = u.id
                    LEFT JOIN templates t ON r.template_id = t.id
                    WHERE u.email LIKE ?
                    ORDER BY r.updated_at DESC
                    LIMIT 100
                ");
                $stmt->execute(['%' . $userFilter . '%']);
            } else {
                $stmt = $pdo->query("
                    SELECT r.*, u.first_name, u.last_name, u.email, t.name as template_name
                    FROM resumes r
                    JOIN users u ON r.user_id = u.id
                    LEFT JOIN templates t ON r.template_id = t.id
                    ORDER BY r.updated_at DESC
                    LIMIT 100
                ");
            }
            $resumes = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'resumes' => $resumes]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load resumes']);
        }
    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Resume ID required']);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM resumes WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($pdo, 'resume_delete', "Deleted resume ID: $id");
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete resume']);
        }
    }
}

function handleTemplates($pdo, $method) {
    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT * FROM templates ORDER BY name");
            $templates = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'templates' => $templates]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load templates']);
        }
    } elseif ($method === 'PUT') {
        $id = $_GET['id'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Template ID required']);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE templates SET is_active = ?, is_premium = ? WHERE id = ?");
            $stmt->execute([(int)$data['is_active'], (int)$data['is_premium'], $id]);
            logActivity($pdo, 'template_update', "Updated template ID: $id");
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update template']);
        }
    }
}

function handleBlogs($pdo, $method) {
    if ($method === 'GET') {
        $status = $_GET['status'] ?? '';
        $page = $_GET['page'] ?? 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        try {
            if ($status) {
                $stmt = $pdo->prepare("
                    SELECT b.*, u.first_name, u.last_name
                    FROM blogs b
                    JOIN users u ON b.author_id = u.id
                    WHERE b.status = ?
                    ORDER BY b.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$status, $limit, $offset]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT b.*, u.first_name, u.last_name
                    FROM blogs b
                    JOIN users u ON b.author_id = u.id
                    ORDER BY b.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$limit, $offset]);
            }
            
            $blogs = $stmt->fetchAll();
            echo json_encode(['success' => true, 'blogs' => $blogs]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load blogs']);
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $adminId = 1;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO blogs (title, slug, excerpt, content, featured_image, status, author_id, meta_title, meta_description, meta_keywords, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $data['title'],
                $data['slug'],
                $data['excerpt'] ?? '',
                $data['content'],
                $data['featured_image'] ?? '',
                $data['status'] ?? 'draft',
                $adminId,
                $data['meta_title'] ?? $data['title'],
                $data['meta_description'] ?? '',
                $data['meta_keywords'] ?? ''
            ]);
            
            $blogId = $pdo->lastInsertId();
            logActivity($pdo, 'blog_create', "Created blog: {$data['title']}");
            
            echo json_encode(['success' => true, 'id' => $blogId]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create blog']);
        }
    } elseif ($method === 'PUT') {
        $id = $_GET['id'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Blog ID required']);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE blogs SET
                    title = ?, slug = ?, excerpt = ?, content = ?, featured_image = ?,
                    status = ?, meta_title = ?, meta_description = ?, meta_keywords = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['title'],
                $data['slug'],
                $data['excerpt'] ?? '',
                $data['content'],
                $data['featured_image'] ?? '',
                $data['status'],
                $data['meta_title'] ?? $data['title'],
                $data['meta_description'] ?? '',
                $data['meta_keywords'] ?? '',
                $id
            ]);
            
            logActivity($pdo, 'blog_update', "Updated blog ID: $id");
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update blog']);
        }
    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Blog ID required']);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($pdo, 'blog_delete', "Deleted blog ID: $id");
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete blog']);
        }
    }
}

function handleBlogUpload($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Image file is required']);
        return;
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload failed']);
        return;
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'Image must be 5MB or smaller']);
        return;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['error' => 'Only JPG, PNG, and WEBP are allowed']);
        return;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/blog';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare upload directory']);
        return;
    }

    $fileName = 'blog-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save uploaded image']);
        return;
    }

    chmod($target, 0644);

    echo json_encode([
        'success' => true,
        'url' => '/uploads/blog/' . $fileName,
        'file' => $fileName,
        'mime' => $mime
    ]);
}

function handleBrandingUpload($pdo, $method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $type = $_POST['type'] ?? ''; // 'logo' or 'favicon'
    if (!in_array($type, ['logo', 'favicon'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type. Use "logo" or "favicon".']);
        return;
    }

    if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Image file is required']);
        return;
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload failed']);
        return;
    }

    $maxSize = $type === 'favicon' ? 1 * 1024 * 1024 : 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        http_response_code(400);
        echo json_encode(['error' => 'File too large. Logo: 5MB max. Favicon: 1MB max.']);
        return;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'image/svg+xml' => 'svg'
    ];

    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['error' => 'Only JPG, PNG, WEBP, ICO, and SVG files are allowed.']);
        return;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/branding';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare upload directory']);
        return;
    }

    // Use fixed filenames so they can be referenced consistently
    $ext = $allowed[$mime];
    $fileName = $type . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save uploaded file']);
        return;
    }

    chmod($target, 0644);

    // Save URL to settings
    $url = '/uploads/branding/' . $fileName;
    $settingKey = $type === 'logo' ? 'site_logo' : 'site_favicon';
    upsertSetting($pdo, $settingKey, $url, false);

    logActivity($pdo, 'branding_upload', ucfirst($type) . ' updated');

    echo json_encode([
        'success' => true,
        'url' => $url,
        'file' => $fileName,
        'mime' => $mime,
        'setting_key' => $settingKey
    ]);
}

function handlePages($pdo, $method) {
    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("
                SELECT p.*, u.first_name, u.last_name
                FROM pages p
                LEFT JOIN users u ON p.updated_by = u.id
                ORDER BY p.page_type
            ");
            $pages = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'pages' => $pages]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load pages']);
        }
    } elseif ($method === 'PUT') {
        $id = $_GET['id'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Page ID required']);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE pages SET
                    title = ?, content = ?, meta_title = ?, meta_description = ?, meta_keywords = ?,
                    status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['title'],
                $data['content'],
                $data['meta_title'] ?? $data['title'],
                $data['meta_description'] ?? '',
                $data['meta_keywords'] ?? '',
                $data['status'],
                $id
            ]);
            
            logActivity($pdo, 'page_update', "Updated page: {$data['title']}");
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update page']);
        }
    }
}

function handleContacts($pdo, $method) {
    if ($method === 'GET') {
        $status = $_GET['status'] ?? '';
        
        try {
            if ($status) {
                $stmt = $pdo->prepare("
                    SELECT * FROM contact_submissions
                    WHERE status = ?
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$status]);
            } else {
                $stmt = $pdo->query("
                    SELECT * FROM contact_submissions
                    ORDER BY created_at DESC
                    LIMIT 100
                ");
            }
            
            $contacts = $stmt->fetchAll();
            echo json_encode(['success' => true, 'contacts' => $contacts]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load contacts']);
        }
    } elseif ($method === 'PUT') {
        $id = $_GET['id'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Contact ID required']);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE contact_submissions SET status = ?, notes = ? WHERE id = ?
            ");
            $stmt->execute([$data['status'], $data['notes'] ?? '', $id]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update contact']);
        }
    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Contact ID required']);
            return;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM contact_submissions WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete contact']);
        }
    }
}

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
        $socket = stream_socket_client("ssl://{$smtpHost}:{$smtpPort}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new Exception("SMTP connection failed: {$errstr} ({$errno})");
        }
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
    $socket = stream_socket_client("tcp://{$smtpHost}:{$smtpPort}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new Exception("SMTP connection failed: {$errstr} ({$errno})");
    }

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

function handleContactReplies($pdo, $method) {
    if ($method === 'GET') {
        $contactId = $_GET['contact_id'] ?? null;
        if (!$contactId) {
            http_response_code(400);
            echo json_encode(['error' => 'contact_id required']);
            return;
        }

        try {
            $contactStmt = $pdo->prepare("SELECT * FROM contact_submissions WHERE id = ? LIMIT 1");
            $contactStmt->execute([$contactId]);
            $contact = $contactStmt->fetch();
            if (!$contact) {
                http_response_code(404);
                echo json_encode(['error' => 'Contact not found']);
                return;
            }

            $replyStmt = $pdo->prepare("SELECT * FROM contact_message_replies WHERE contact_submission_id = ? ORDER BY created_at ASC, id ASC");
            $replyStmt->execute([$contactId]);
            $replies = $replyStmt->fetchAll();

            echo json_encode(['success' => true, 'contact' => $contact, 'replies' => $replies]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load thread']);
        }
        return;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $contactId = $data['contact_id'] ?? null;
        $subject = trim((string)($data['subject'] ?? ''));
        $bodyHtml = trim((string)($data['body_html'] ?? ''));
        $bodyText = trim((string)($data['body_text'] ?? ''));

        if (!$contactId || $bodyHtml === '') {
            http_response_code(400);
            echo json_encode(['error' => 'contact_id and body_html are required']);
            return;
        }

        try {
            $contactStmt = $pdo->prepare("SELECT * FROM contact_submissions WHERE id = ? LIMIT 1");
            $contactStmt->execute([$contactId]);
            $contact = $contactStmt->fetch();
            if (!$contact) {
                http_response_code(404);
                echo json_encode(['error' => 'Contact not found']);
                return;
            }

            $finalSubject = $subject !== '' ? $subject : ('Re: ' . ($contact['subject'] ?: 'Your message to CV Maker'));
            $sent = sendSmtpMail($contact['email'], $contact['name'] ?? '', $finalSubject, $bodyHtml, $bodyText);
            $status = $sent ? 'sent' : 'failed';

            $stmt = $pdo->prepare("INSERT INTO contact_message_replies (contact_submission_id, direction, sender_email, sender_name, recipient_email, subject, body_html, body_text, status) VALUES (?, 'outbound', ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $contactId,
                'support@cvmaker.ink',
                'Customer Support',
                $contact['email'],
                $finalSubject,
                $bodyHtml,
                $bodyText !== '' ? $bodyText : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)),
                $status
            ]);

            $updateContact = $pdo->prepare("UPDATE contact_submissions SET status = ? WHERE id = ?");
            $updateContact->execute([$sent ? 'replied' : 'read', $contactId]);

            if (!$sent) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to send reply email, but the attempt was logged.', 'logged' => true]);
                return;
            }

            echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save reply']);
        }
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function handleNewsletter($pdo, $method) {
    if ($method === 'GET') {
        try {
            // Get newsletter stats
            $subscribers = $pdo->query("SELECT COUNT(*) as count FROM newsletter_subscribers WHERE status='active'")->fetch()['count'];
            $campaigns = $pdo->query("SELECT COUNT(*) as count FROM email_log WHERE template_id IS NOT NULL")->fetch()['count'];
            
            echo json_encode([
                'success' => true,
                'total_subscribers' => $subscribers,
                'total_campaigns' => $campaigns
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load newsletter']);
        }
    }
}

function handleSubscribers($pdo, $method) {
    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT * FROM newsletter_subscribers ORDER BY created_at DESC");
            $subscribers = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'subscribers' => $subscribers]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load subscribers']);
        }
    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Subscriber ID required']);
            return;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete subscriber']);
        }
    }
}

function handleEmailTemplates($pdo, $method) {
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        
        try {
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
                $stmt->execute([$id]);
                $template = $stmt->fetch();
                
                echo json_encode(['success' => true, 'template' => $template]);
            } else {
                $stmt = $pdo->query("SELECT * FROM email_templates ORDER BY name");
                $templates = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'templates' => $templates]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load templates']);
        }
    }
}

function handleEmailLog($pdo, $method) {
    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("
                SELECT el.*, et.name as template_name
                FROM email_log el
                LEFT JOIN email_templates et ON el.template_id = et.id
                ORDER BY el.created_at DESC
                LIMIT 100
            ");
            $logs = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'logs' => $logs]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load email log']);
        }
    }
}

function handleSettings($pdo, $method) {
    ensureSettingsTable($pdo);

    if ($method === 'GET') {
        try {
            $settingsRows = getAllSettings($pdo, true);
            $settingsResult = [];

            foreach ($settingsRows as $key => $row) {
                $settingsResult[$key] = $row['setting_value'];
            }

            if (!isset($settingsResult['paypal_environment']) && isset($settingsResult['paypal_env'])) {
                $settingsResult['paypal_environment'] = $settingsResult['paypal_env'];
            }

            echo json_encode([
                'success' => true,
                'settings' => $settingsResult,
                'meta' => $settingsRows
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load settings']);
        }
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $settings = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : (is_array($data) ? $data : []);
        if (array_key_exists('paypal_environment', $settings)) {
            $settings['paypal_env'] = $settings['paypal_environment'];
            unset($settings['paypal_environment']);
        }
        $encryptedKeys = [];

        try {
            foreach ($settings as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                upsertSetting($pdo, $key, (string)$value, in_array($key, $encryptedKeys, true));
            }

            logActivity($pdo, 'settings_update', 'Updated settings');
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update settings']);
        }
    }
}

function handleSEO($pdo, $method) {
    if ($method === 'GET') {
        try {
            $staticPages = [
                ['slug' => '/', 'label' => 'Home', 'file' => dirname(__DIR__) . '/index.html', 'type' => 'static'],
                ['slug' => '/register.html', 'label' => 'Register', 'file' => dirname(__DIR__) . '/register.html', 'type' => 'static'],
                ['slug' => '/login.html', 'label' => 'Login', 'file' => dirname(__DIR__) . '/login.html', 'type' => 'static'],
                ['slug' => '/templates.html', 'label' => 'Templates', 'file' => dirname(__DIR__) . '/templates.html', 'type' => 'static'],
                ['slug' => '/contact.html', 'label' => 'Contact Us', 'file' => dirname(__DIR__) . '/contact.html', 'type' => 'static'],
                ['slug' => '/blog.html', 'label' => 'Blog Index', 'file' => dirname(__DIR__) . '/blog.php', 'type' => 'static-dynamic']
            ];

            $items = [];
            foreach ($staticPages as $page) {
                $meta = $page['type'] === 'static-dynamic'
                    ? fetchLiveMeta('https://cvmaker.ink' . $page['slug'])
                    : extractHtmlMeta($page['file']);
                $items[] = [
                    'key' => $page['slug'],
                    'kind' => 'static',
                    'label' => $page['label'],
                    'url' => $page['slug'],
                    'meta_title' => $meta['title'],
                    'meta_description' => $meta['description']
                ];
            }

            $stmt = $pdo->query("SELECT id, page_type, title, meta_title, meta_description FROM pages WHERE status='active' ORDER BY title");
            foreach ($stmt->fetchAll() as $page) {
                $slug = mapPageTypeToUrl($page['page_type']);
                if (strpos($slug, '/page.php?slug=') === 0) {
                    continue;
                }
                $items[] = [
                    'key' => 'page:' . $page['id'],
                    'kind' => 'page',
                    'id' => (int)$page['id'],
                    'label' => $page['title'],
                    'url' => $slug,
                    'meta_title' => $page['meta_title'] ?: $page['title'],
                    'meta_description' => $page['meta_description'] ?: ''
                ];
            }

            $blogStmt = $pdo->query("SELECT id, title, slug, meta_title, meta_description FROM blogs WHERE status='published' ORDER BY created_at DESC");
            foreach ($blogStmt->fetchAll() as $blog) {
                $items[] = [
                    'key' => 'blog:' . $blog['id'],
                    'kind' => 'blog',
                    'id' => (int)$blog['id'],
                    'label' => $blog['title'],
                    'url' => '/blog/' . $blog['slug'] . '.html',
                    'meta_title' => $blog['meta_title'] ?: $blog['title'],
                    'meta_description' => $blog['meta_description'] ?: ''
                ];
            }

            $sitemapPath = dirname(__DIR__) . '/sitemap.xml';
            $sitemapContent = file_exists($sitemapPath) ? file_get_contents($sitemapPath) : '';

            echo json_encode([
                'success' => true,
                'items' => $items,
                'sitemap' => $sitemapContent
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load SEO data']);
        }
        return;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $mode = $input['mode'] ?? 'save';

        if ($mode === 'regenerate-sitemap') {
            try {
                $xml = buildSitemapXml($pdo);
                file_put_contents(dirname(__DIR__) . '/sitemap.xml', $xml);
                echo json_encode(['success' => true, 'sitemap' => $xml]);
            } catch (Throwable $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to regenerate sitemap']);
            }
            return;
        }

        $kind = $input['kind'] ?? '';
        $id = (int)($input['id'] ?? 0);
        $url = (string)($input['url'] ?? '');
        $metaTitle = trim((string)($input['meta_title'] ?? ''));
        $metaDescription = trim((string)($input['meta_description'] ?? ''));

        try {
            if ($kind === 'page' && $id > 0) {
                $stmt = $pdo->prepare("UPDATE pages SET meta_title = ?, meta_description = ? WHERE id = ?");
                $stmt->execute([$metaTitle ?: null, $metaDescription ?: null, $id]);
            } elseif ($kind === 'blog' && $id > 0) {
                $stmt = $pdo->prepare("UPDATE blogs SET meta_title = ?, meta_description = ? WHERE id = ?");
                $stmt->execute([$metaTitle ?: null, $metaDescription ?: null, $id]);
            } elseif ($kind === 'static' && $url) {
                updateStaticPageMeta(dirname(__DIR__) . mapStaticUrlToFile($url), $metaTitle, $metaDescription);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid SEO target']);
                return;
            }

            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save SEO settings']);
        }
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function mapPageTypeToUrl($pageType) {
    $map = [
        'privacy' => '/privacy.html',
        'terms' => '/terms.html',
        'about' => '/about.html',
        'help-center' => '/help-center.html',
        'faqs' => '/faqs.html'
    ];
    return $map[$pageType] ?? '/page.php?slug=' . urlencode($pageType);
}

function mapStaticUrlToFile($url) {
    $map = [
        '/' => '/index.html',
        '/register.html' => '/register.html',
        '/login.html' => '/login.html',
        '/templates.html' => '/templates.html',
        '/contact.html' => '/contact.html',
        '/blog.html' => '/blog.php'
    ];
    if (!isset($map[$url])) {
        throw new RuntimeException('Unknown static page');
    }
    return $map[$url];
}

function fetchLiveMeta($url) {
    $context = stream_context_create([
        'http' => ['timeout' => 10, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $content = @file_get_contents($url, false, $context);
    if ($content === false) {
        return ['title' => '', 'description' => ''];
    }
    preg_match('/<title>(.*?)<\/title>/is', $content, $titleMatch);
    preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $content, $descMatch);
    return [
        'title' => html_entity_decode(trim($titleMatch[1] ?? '')),
        'description' => html_entity_decode(trim($descMatch[1] ?? ''))
    ];
}

function extractHtmlMeta($filePath) {
    if (!file_exists($filePath)) {
        return ['title' => '', 'description' => ''];
    }
    $content = file_get_contents($filePath);
    preg_match('/<title>(.*?)<\/title>/is', $content, $titleMatch);
    preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $content, $descMatch);
    return [
        'title' => html_entity_decode(trim($titleMatch[1] ?? '')),
        'description' => html_entity_decode(trim($descMatch[1] ?? ''))
    ];
}

function updateStaticPageMeta($filePath, $metaTitle, $metaDescription) {
    if (!file_exists($filePath)) {
        throw new RuntimeException('Static page not found');
    }
    $content = file_get_contents($filePath);

    if (preg_match('/<title>.*?<\/title>/is', $content)) {
        $content = preg_replace('/<title>.*?<\/title>/is', '<title>' . htmlspecialchars($metaTitle ?: 'cvmaker.ink', ENT_QUOTES) . '</title>', $content, 1);
    }

    $metaTag = '<meta name="description" content="' . htmlspecialchars($metaDescription, ENT_QUOTES) . '">';
    if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'].*?["\']\s*\/?>/is', $content)) {
        $content = preg_replace('/<meta\s+name=["\']description["\']\s+content=["\'].*?["\']\s*\/?>/is', $metaTag, $content, 1);
    } else {
        $content = preg_replace('/<title>.*?<\/title>/is', '$0' . "\n    " . $metaTag, $content, 1);
    }

    file_put_contents($filePath, $content);
}

function buildSitemapXml($pdo) {
    $entries = [
        ['loc' => '/', 'priority' => '1.0'],
        ['loc' => '/templates.html', 'priority' => '0.9'],
        ['loc' => '/register.html', 'priority' => '0.8'],
        ['loc' => '/login.html', 'priority' => '0.7'],
        ['loc' => '/contact.html', 'priority' => '0.7'],
        ['loc' => '/privacy.html', 'priority' => '0.5'],
        ['loc' => '/terms.html', 'priority' => '0.5'],
        ['loc' => '/about.html', 'priority' => '0.6'],
        ['loc' => '/help-center.html', 'priority' => '0.6'],
        ['loc' => '/faqs.html', 'priority' => '0.6'],
        ['loc' => '/blog.html', 'priority' => '0.7']
    ];

    $seen = [];
    $normalized = [];
    foreach ($entries as $entry) {
        if (!isset($seen[$entry['loc']])) {
            $seen[$entry['loc']] = true;
            $normalized[] = $entry;
        }
    }

    $stmt = $pdo->query("SELECT page_type FROM pages WHERE status='active'");
    foreach ($stmt->fetchAll() as $page) {
        $loc = mapPageTypeToUrl($page['page_type']);
        if (strpos($loc, '/page.php?slug=') === 0) {
            continue;
        }
        if (!isset($seen[$loc])) {
            $seen[$loc] = true;
            $normalized[] = ['loc' => $loc, 'priority' => '0.6'];
        }
    }

    $blogStmt = $pdo->query("SELECT slug FROM blogs WHERE status='published' AND slug IS NOT NULL AND slug != ''");
    foreach ($blogStmt->fetchAll() as $blog) {
        $loc = '/blog/' . $blog['slug'] . '.html';
        if (!isset($seen[$loc])) {
            $seen[$loc] = true;
            $normalized[] = ['loc' => $loc, 'priority' => '0.6'];
        }
    }

    $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
    foreach ($normalized as $entry) {
        $xml[] = '  <url>';
        $xml[] = '    <loc>https://cvmaker.ink' . htmlspecialchars($entry['loc'], ENT_QUOTES) . '</loc>';
        $xml[] = '    <priority>' . $entry['priority'] . '</priority>';
        $xml[] = '  </url>';
    }
    $xml[] = '</urlset>';
    return implode("\n", $xml) . "\n";
}

function handleAnalytics($pdo, $method) {
    if ($method === 'GET') {
        try {
            // Get daily stats for last 30 days
            $dailyStats = $pdo->query("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(DISTINCT u.id) as new_users,
                    COUNT(DISTINCT r.id) as new_resumes
                FROM (
                    SELECT created_at, id FROM users
                    UNION ALL
                    SELECT created_at, id FROM resumes
                ) combined
                LEFT JOIN users u ON DATE(combined.created_at) = DATE(u.created_at)
                LEFT JOIN resumes r ON DATE(combined.created_at) = DATE(r.created_at)
                WHERE combined.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(combined.created_at)
                ORDER BY date DESC
            ")->fetchAll();
            
            // Get top templates
            $topTemplates = $pdo->query("
                SELECT t.name, COUNT(r.id) as usage_count
                FROM templates t
                LEFT JOIN resumes r ON t.id = r.template_id
                GROUP BY t.id
                ORDER BY usage_count DESC
                LIMIT 5
            ")->fetchAll();
            
            // Get user growth by month
            $monthlyGrowth = $pdo->query("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM users
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC
                LIMIT 12
            ")->fetchAll();
            
            echo json_encode([
                'success' => true,
                'daily_stats' => $dailyStats,
                'top_templates' => $topTemplates,
                'monthly_growth' => $monthlyGrowth
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load analytics']);
        }
    }
}

function handleActivity($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT * FROM activity_log
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $activities = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'activities' => $activities]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load activity']);
    }
}


function handleTestEmail($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $testEmail = $data['email'] ?? '';
    if (empty($testEmail)) {
        // Use the SMTP from email as test target
        $testEmail = getSetting($pdo, 'smtp_from_email', '');
    }
    if (empty($testEmail)) {
        echo json_encode(['success' => false, 'error' => 'No email address provided and no from email configured']);
        return;
    }

    $subject = '=?UTF-8?B?' . base64_encode('Test Email from CV Maker') . '?=';
    $htmlBody = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;"><div style="max-width:600px;margin:0 auto;padding:20px;border:1px solid #ddd;"><h2 style="color:#667eea;">Test Email Successful</h2><p>This is a test email from <strong>CV Maker</strong> admin dashboard.</p><p>If you received this, your SMTP configuration is working correctly.</p><p><small>Sent at: ' . date('Y-m-d H:i:s') . '</small></p></div></body></html>';
    $textBody = "Test Email from CV Maker\n\nIf you received this, your SMTP configuration is working correctly.\n\nSent at: " . date('Y-m-d H:i:s');

    try {
        $sent = sendSmtpMail($testEmail, '', $subject, $htmlBody, $textBody);
        echo json_encode(['success' => true, 'message' => "Test email sent to {$testEmail}"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed: ' . $e->getMessage()]);
    }
}

function handleSendEmail($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Log the email (actual sending requires SMTP configuration)
    logActivity($pdo, 'email_sent', "Email to: " . ($data['to'] ?? 'unknown'));
    
    echo json_encode(['success' => true, 'message' => 'Email logged for delivery']);
}

// Helper function to log activity
function logActivity($pdo, $action, $description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (action, description, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$action, $description]);
    } catch (PDOException $e) {
        // Silently fail - activity logging is not critical
    }
}
?>
