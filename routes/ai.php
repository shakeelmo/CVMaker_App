<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_settings.php';
require_once __DIR__ . '/../middleware/auth.php';

function geminiApiRequest($apiKey, $model, $prompt) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($apiKey);
    $payload = [
        'contents' => [[
            'parts' => [[ 'text' => $prompt ]]
        ]],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 180,
        ],
    ];

    $attempts = 3;
    $lastError = 'Unknown Gemini error';
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $lastError = 'Gemini request failed: ' . $curlError;
        } else {
            $data = json_decode($response, true);
            if ($httpCode < 400) {
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if ($text) return trim($text);
                $lastError = 'Gemini returned no summary text';
            } else {
                $lastError = $data['error']['message'] ?? ('Gemini API error HTTP ' . $httpCode);
                if (stripos($lastError, 'high demand') === false && stripos($lastError, 'try again later') === false) {
                    break;
                }
            }
        }

        if ($attempt < $attempts) {
            sleep($attempt * 2);
        }
    }

    throw new RuntimeException($lastError);
}

function incrementAiUsage($pdo, $userId, $feature) {
    $stmt = $pdo->prepare("INSERT INTO ai_usage (user_id, feature, date, count) VALUES (?, ?, CURDATE(), 1) ON DUPLICATE KEY UPDATE count = count + 1");
    $stmt->execute([$userId, $feature]);
}

function userHasAiAccess($user) {
    $override = $user['ai_enabled'] ?? null;
    $tier = strtolower((string)($user['subscription_tier'] ?? 'free'));

    if ($tier === 'pro') {
        if ($override === '0' || $override === 0) return false;
        return true;
    }

    if ($override === '1' || $override === 1) return true;
    return false;
}

function getAiDailyUsage($pdo, $userId, $feature) {
    $stmt = $pdo->prepare("SELECT count FROM ai_usage WHERE user_id = ? AND feature = ? AND date = CURDATE() LIMIT 1");
    $stmt->execute([$userId, $feature]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['count'] : 0;
}

function checkAiDailyLimit($pdo, $user, $feature) {
    $tier = strtolower((string)($user['subscription_tier'] ?? 'free'));
    $settingKey = $tier === 'pro' ? 'ai_pro_uses_per_day' : 'ai_free_uses_per_day';
    $limit = trim((string)getSetting($pdo, $settingKey, '0'));
    if ($limit === '' || $limit === '0') return null;
    $usage = getAiDailyUsage($pdo, (int)$user['id'], $feature);
    if ($usage >= (int)$limit) {
        return "Daily AI limit reached for your account.";
    }
    return null;
}

function generateSummary($input) {
    $user = requireAuth();

    $pdo = getDB();
    $aiEnabled = strtolower((string)getSetting($pdo, 'ai_enabled', 'disabled'));
    if ($aiEnabled !== 'enabled') {
        http_response_code(403);
        return [
            'error' => 'AI features are currently disabled by the administrator. Please try again later.'
        ];
    }

    if (!userHasAiAccess($user)) {
        http_response_code(403);
        return [
            'error' => 'AI is not enabled for this account.',
            'upgrade_required' => true,
            'upgrade_url' => '/upgrade.html'
        ];
    }

    $limitMessage = checkAiDailyLimit($pdo, $user, 'summary_generator');
    if ($limitMessage) {
        http_response_code(403);
        return ['error' => $limitMessage];
    }

    $apiKey = trim((string)getSetting($pdo, 'gemini_api_key', ''));
    $model = trim((string)getSetting($pdo, 'gemini_model', ''));
    if ($model === '') $model = 'gemini-2.0-flash';
    if ($apiKey === '') {
        http_response_code(500);
        return ['error' => 'Gemini API key is not configured.'];
    }

    $jobTitle = trim((string)($input['job_title'] ?? 'professional'));
    $years = trim((string)($input['years'] ?? 'several'));
    $skills = $input['skills'] ?? [];
    if (is_array($skills)) {
        $skills = implode(', ', array_filter(array_map('trim', $skills)));
    }
    $skills = trim((string)$skills);
    if ($skills === '') $skills = 'relevant professional skills';

    $prompt = "Write a professional 3-sentence resume summary for a {$jobTitle} with {$years} years of experience skilled in {$skills}. Be concise, achievement-focused, and professional.";

    try {
        $summary = geminiApiRequest($apiKey, $model, $prompt);
        incrementAiUsage($pdo, (int)$user['id'], 'summary_generator');
        return [
            'success' => true,
            'summary' => $summary,
            'feature' => 'summary_generator'
        ];
    } catch (Throwable $e) {
        http_response_code(500);
        $message = $e->getMessage();
        if (stripos($message, 'high demand') !== false || stripos($message, 'try again later') !== false) {
            $message = 'AI summary is temporarily busy right now. Please try again in a moment.';
        }
        return ['error' => 'Failed to generate summary: ' . $message];
    }
}
