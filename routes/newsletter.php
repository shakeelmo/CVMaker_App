<?php

function subscribeNewsletter($input) {
    global $pdo;

    $email = trim((string)($input['email'] ?? ''));
    $firstName = trim((string)($input['first_name'] ?? ''));

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Valid email is required'];
    }

    try {
        $stmt = $pdo->prepare("SELECT id, status FROM newsletter_subscribers WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE newsletter_subscribers SET first_name = ?, status = 'active' WHERE id = ?");
            $stmt->execute([$firstName ?: null, $existing['id']]);
            return ['success' => true, 'message' => 'You are subscribed to the newsletter'];
        }

        $stmt = $pdo->prepare("INSERT INTO newsletter_subscribers (email, first_name, status) VALUES (?, ?, 'active')");
        $stmt->execute([$email, $firstName ?: null]);

        return ['success' => true, 'message' => 'Successfully subscribed to the newsletter'];
    } catch (PDOException $e) {
        error_log('Newsletter subscribe failed: ' . $e->getMessage());
        return ['error' => 'Failed to subscribe right now'];
    }
}
