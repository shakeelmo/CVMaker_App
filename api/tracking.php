<?php
/**
 * Tracking Codes API
 * Returns Google Analytics, Search Console, and other verification codes
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Get all settings from database
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'ga_%' OR setting_key LIKE 'gsc_%' OR setting_key LIKE 'bing_%' OR setting_key LIKE 'yandex_%'");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Settings table might not exist, return empty
}

// Build response
$response = [
    'success' => true,
    'tracking' => [
        'google_analytics' => [
            'enabled' => ($settings['ga_enabled'] ?? '0') === '1',
            'measurement_id' => $settings['ga_measurement_id'] ?? null
        ],
        'google_search_console' => [
            'verification_code' => $settings['gsc_verification_code'] ?? null,
            'verification_id' => $settings['gsc_verification_id'] ?? null
        ],
        'bing_webmaster' => [
            'verification_code' => $settings['bing_verification_code'] ?? null
        ],
        'yandex_webmaster' => [
            'verification_code' => $settings['yandex_verification_code'] ?? null
        ]
    ]
];

echo json_encode($response);
