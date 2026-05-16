<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_settings.php';
require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function paymentsJsonResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function getPaymentRoute(): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $path = preg_replace('#^/api/payments/?#', '', $path);
    return trim($path, '/');
}

function getPaymentInput(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    return [];
}

function loadPaymentSettingsOrFail(): array {
    try {
        return getPaymentSettings(getDB());
    } catch (Exception $e) {
        error_log('getPaymentSettings failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

function getPayPalBaseUrl(string $env): string {
    return strtolower($env) === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

function getPayPalAccessToken(array $settings): array {
    $clientId = trim((string)($settings['paypal_client_id'] ?? ''));
    $clientSecret = trim((string)($settings['paypal_client_secret'] ?? ''));
    $env = trim((string)($settings['paypal_env'] ?? 'sandbox'));

    if ($clientId === '' || $clientSecret === '') {
        paymentsJsonResponse(['error' => 'PayPal credentials are not configured'], 500);
    }

    $url = getPayPalBaseUrl($env) . '/v1/oauth2/token';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false || $curlError) {
        paymentsJsonResponse(['error' => 'Failed to connect to PayPal', 'details' => $curlError], 502);
    }

    $decoded = json_decode($body, true);
    if ($httpCode >= 400 || empty($decoded['access_token'])) {
        paymentsJsonResponse([
            'error' => 'Failed to authenticate with PayPal',
            'details' => $decoded
        ], 502);
    }

    return [
        'token' => $decoded['access_token'],
        'base_url' => getPayPalBaseUrl($env),
        'env' => $env,
        'client_id' => $clientId,
        'webhook_id' => trim((string)($settings['paypal_webhook_id'] ?? ''))
    ];
}

function paypalRequest(string $method, string $url, string $accessToken, ?array $payload = null, array $headers = []): array {
    $ch = curl_init($url);
    $httpHeaders = array_merge([
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ], $headers);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $httpHeaders,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false || $curlError) {
        paymentsJsonResponse(['error' => 'PayPal request failed', 'details' => $curlError], 502);
    }

    $decoded = json_decode($body, true);
    return [
        'status' => $httpCode,
        'body' => is_array($decoded) ? $decoded : ['raw' => $body]
    ];
}

function activateProSubscription(int $userId): void {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE users SET subscription_tier = 'pro', subscription_expires = DATE_ADD(CURDATE(), INTERVAL 30 DAY) WHERE id = ?");
    $stmt->execute([$userId]);
}

function resolveApprovedUserId(?array $paypalOrder, ?array $resource = null): ?int {
    $customId = $resource['custom_id']
        ?? $paypalOrder['purchase_units'][0]['custom_id']
        ?? null;

    if ($customId && preg_match('/^user:(\d+)$/', $customId, $matches)) {
        return (int)$matches[1];
    }

    return null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$route = getPaymentRoute();
$input = getPaymentInput();

try {
    if ($method === 'GET' && ($route === '' || $route === 'config')) {
        $settings = loadPaymentSettingsOrFail();
        paymentsJsonResponse([
            'plan' => [
                'name' => $settings['pro_plan_name'] ?: 'CV Maker Pro',
                'price' => (string)($settings['pro_plan_price'] ?: '4.99'),
                'currency' => $settings['pro_plan_currency'] ?: 'USD'
            ],
            'paypal' => [
                'client_id' => $settings['paypal_client_id'] ?: '',
                'env' => strtolower((string)($settings['paypal_env'] ?: 'sandbox'))
            ]
        ]);
    }

    if ($method === 'POST' && $route === 'create-order') {
        $user = requireAuth();
        $settings = loadPaymentSettingsOrFail();
        $auth = getPayPalAccessToken($settings);

        $planName = $settings['pro_plan_name'] ?: 'CV Maker Pro';
        $planPrice = number_format((float)($settings['pro_plan_price'] ?: 4.99), 2, '.', '');
        $currency = $settings['pro_plan_currency'] ?: 'USD';

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'pro-plan',
                'custom_id' => 'user:' . $user['id'],
                'description' => $planName,
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $planPrice
                ]
            ]],
            'application_context' => [
                'brand_name' => 'CV Maker',
                'user_action' => 'PAY_NOW',
                'return_url' => 'https://cvmaker.ink/dashboard.html?upgraded=true',
                'cancel_url' => 'https://cvmaker.ink/upgrade.html'
            ]
        ];

        $response = paypalRequest(
            'POST',
            $auth['base_url'] . '/v2/checkout/orders',
            $auth['token'],
            $payload,
            ['PayPal-Request-Id: cvmaker-' . uniqid('', true)]
        );

        if ($response['status'] >= 400) {
            paymentsJsonResponse(['error' => 'Failed to create PayPal order', 'details' => $response['body']], 502);
        }

        $approvalUrl = null;
        foreach (($response['body']['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approvalUrl = $link['href'] ?? null;
                break;
            }
        }

        paymentsJsonResponse([
            'id' => $response['body']['id'] ?? null,
            'status' => $response['body']['status'] ?? null,
            'approval_url' => $approvalUrl
        ]);
    }

    if ($method === 'POST' && $route === 'capture-order') {
        $user = requireAuth();
        $orderId = trim((string)($input['orderID'] ?? $input['order_id'] ?? ''));
        if ($orderId === '') {
            paymentsJsonResponse(['error' => 'orderID is required'], 422);
        }

        $settings = loadPaymentSettingsOrFail();
        $auth = getPayPalAccessToken($settings);
        $response = paypalRequest('POST', $auth['base_url'] . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', $auth['token']);

        if ($response['status'] >= 400) {
            paymentsJsonResponse(['error' => 'Failed to capture PayPal order', 'details' => $response['body']], 502);
        }

        $status = strtoupper((string)($response['body']['status'] ?? ''));
        if ($status === 'COMPLETED') {
            activateProSubscription((int)$user['id']);
        }

        paymentsJsonResponse([
            'success' => $status === 'COMPLETED',
            'status' => $response['body']['status'] ?? null,
            'subscription_tier' => $status === 'COMPLETED' ? 'pro' : $user['subscription_tier'],
            'subscription_expires' => $status === 'COMPLETED' ? date('Y-m-d', strtotime('+30 days')) : null,
            'details' => $response['body']
        ]);
    }

    if ($method === 'POST' && $route === 'webhook') {
        $settings = loadPaymentSettingsOrFail();
        $auth = getPayPalAccessToken($settings);
        $rawBody = file_get_contents('php://input') ?: '';
        $event = json_decode($rawBody, true);

        if (!is_array($event)) {
            paymentsJsonResponse(['error' => 'Invalid webhook payload'], 400);
        }

        $webhookId = trim((string)($settings['paypal_webhook_id'] ?? ''));
        $verificationStatus = 'skipped';

        if ($webhookId !== '') {
            $verificationPayload = [
                'auth_algo' => $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ?? '',
                'cert_id' => $_SERVER['HTTP_PAYPAL_CERT_ID'] ?? '',
                'transmission_id' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '',
                'transmission_sig' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '',
                'transmission_time' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '',
                'webhook_id' => $webhookId,
                'webhook_event' => $event
            ];

            $verification = paypalRequest('POST', $auth['base_url'] . '/v1/notifications/verify-webhook-signature', $auth['token'], $verificationPayload);
            $verificationStatus = $verification['body']['verification_status'] ?? 'FAILED';
            if ($verificationStatus !== 'SUCCESS') {
                paymentsJsonResponse(['error' => 'Webhook verification failed', 'details' => $verification['body']], 400);
            }
        }

        $eventType = strtolower((string)($event['event_type'] ?? ''));
        $updatedUserId = null;
        if ($eventType === 'payment.capture.completed') {
            $updatedUserId = resolveApprovedUserId(null, $event['resource'] ?? null);
            if ($updatedUserId) {
                activateProSubscription($updatedUserId);
            }
        }

        paymentsJsonResponse([
            'received' => true,
            'verification_status' => $verificationStatus,
            'event_type' => $event['event_type'] ?? null,
            'updated_user_id' => $updatedUserId
        ]);
    }

    paymentsJsonResponse(['error' => 'Route not found'], 404);
} catch (Throwable $e) {
    error_log('payments.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
