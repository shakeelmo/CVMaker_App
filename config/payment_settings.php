<?php
require_once __DIR__ . '/database.php';

function ensureSettingsTable($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(191) NOT NULL, setting_value TEXT NULL, is_encrypted TINYINT(1) NOT NULL DEFAULT 0, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (setting_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function settingsEncryptionKey() {
    return hash('sha256', (getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: 'cvmaker-db-fallback') . '|cvmaker.ink|settings', true);
}

function encryptSettingValue($value) {
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($value, 'AES-256-CBC', settingsEncryptionKey(), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function decryptSettingValue($value) {
    if (!$value) return '';
    $decoded = base64_decode($value, true);
    if ($decoded === false || strlen($decoded) < 17) return (string)$value;
    $iv = substr($decoded, 0, 16);
    $cipher = substr($decoded, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', settingsEncryptionKey(), OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

function getAllSettings($pdo, $decrypt = false) {
    ensureSettingsTable($pdo);
    $stmt = $pdo->query("SELECT setting_key, setting_value, is_encrypted, updated_at FROM settings ORDER BY setting_key");
    $settings = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $value = $row['setting_value'];
        if ($decrypt && (int)$row['is_encrypted'] === 1) $value = decryptSettingValue($value);
        $settings[$row['setting_key']] = [
            'setting_key' => $row['setting_key'],
            'setting_value' => $value,
            'is_encrypted' => (int)$row['is_encrypted'],
            'updated_at' => $row['updated_at'],
        ];
    }
    return $settings;
}

function getSetting($pdo, $key, $default = null, $decrypt = true) {
    ensureSettingsTable($pdo);
    $stmt = $pdo->prepare("SELECT setting_value, is_encrypted FROM settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return $default;
    if ($decrypt && (int)$row['is_encrypted'] === 1) return decryptSettingValue($row['setting_value']);
    return $row['setting_value'];
}

function upsertSetting($pdo, $key, $value, $encrypted = false) {
    ensureSettingsTable($pdo);
    $storedValue = $value;
    if ($encrypted && $value !== null && $value !== '') $storedValue = encryptSettingValue($value);
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, is_encrypted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_encrypted = VALUES(is_encrypted), updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$key, $storedValue, $encrypted ? 1 : 0]);
}

function normalizeSubscriptionTier($user) {
    $tier = $user['subscription_tier'] ?? 'free';
    $expires = $user['subscription_expires'] ?? null;
    if ($tier === 'pro' && $expires && strtotime($expires) < strtotime(date('Y-m-d'))) return 'free';
    return $tier;
}

function isProUser($user) {
    return normalizeSubscriptionTier($user) === 'pro';
}

function enforceUserSubscription($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE users SET subscription_tier = 'free', subscription_expires = NULL WHERE id = ? AND subscription_tier = 'pro' AND subscription_expires IS NOT NULL AND subscription_expires < CURDATE()");
    $stmt->execute([$userId]);
}

function getPaymentSettings($pdo) {
    $paypalEnv = (string)getSetting($pdo, 'paypal_env', '');
    if ($paypalEnv === '') {
        $paypalEnv = (string)getSetting($pdo, 'paypal_environment', 'sandbox');
    }

    return [
        'paypal_client_id' => (string)getSetting($pdo, 'paypal_client_id', ''),
        'paypal_client_secret' => (string)getSetting($pdo, 'paypal_client_secret', ''),
        'paypal_env' => $paypalEnv,
        'paypal_environment' => $paypalEnv,
        'paypal_webhook_id' => (string)getSetting($pdo, 'paypal_webhook_id', ''),
        'pro_plan_price' => (string)getSetting($pdo, 'pro_plan_price', '4.99'),
        'pro_plan_currency' => (string)getSetting($pdo, 'pro_plan_currency', 'USD'),
        'pro_plan_name' => (string)getSetting($pdo, 'pro_plan_name', 'CV Maker Pro'),
    ];
}

function getFreeTemplateIds($pdo) {
    $stmt = $pdo->query("SELECT id FROM templates WHERE is_active = TRUE ORDER BY is_premium ASC, category ASC, name ASC LIMIT 3");
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function templateAllowedForUser($pdo, $user, $templateId) {
    if (isProUser($user)) return true;
    $stmt = $pdo->prepare("SELECT id, is_premium FROM templates WHERE id = ? AND is_active = TRUE LIMIT 1");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$template) return false;
    if ((int)$template['is_premium'] === 1) return false;
    return in_array((int)$template['id'], getFreeTemplateIds($pdo), true);
}
