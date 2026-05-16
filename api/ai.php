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
        throw new RuntimeException('Gemini request failed: ' . $curlError);
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $msg = $data['error']['message'] ?? ('Gemini API error HTTP ' . $httpCode);
        throw new RuntimeException($msg);
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$text) {
        throw new RuntimeException('Gemini returned no summary text');
    }
    return trim($text);
}

function incrementAiUsage($pdo, $userId, $feature) {
    $stmt = $pdo->prepare("INSERT INTO ai_usage (user_id, feature, date, count) VALUES (?, ?, CURDATE(), 1) ON DUPLICATE KEY UPDATE count = count + 1");
    $stmt->execute([$userId, $feature]);
}

function generateSummary($input) {
    $user = requireAuth();
    if (!isProUser($user)) {
        http_response_code(403);
        return [
            'error' => 'AI is available for Pro users only.',
            'upgrade_required' => true,
            'upgrade_url' => '/upgrade.html'
        ];
    }

    $pdo = getDB();
    $aiEnabled = strtolower((string)getSetting($pdo, 'ai_enabled', 'disabled'));
    if ($aiEnabled !== 'enabled') {
        http_response_code(403);
        return [
            'error' => 'AI features are currently disabled by the administrator. Please try again later.'
        ];
    }

    $apiKey = trim((string)getSetting($pdo, 'gemini_api_key', ''));
    $model = trim((string)getSetting($pdo, 'gemini_model', 'gemini-1.5-flash'));
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
        return ['error' => 'Failed to generate summary: ' . $e->getMessage()];
    }
}
