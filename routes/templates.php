<?php
// Templates routes

require_once __DIR__ . '/../config/payment_settings.php';

function getTemplates() {
    $pdo = getDB();

    $user = getCurrentUser();
    if ($user) {
        enforceUserSubscription($pdo, (int)$user['id']);
        $user['subscription_tier'] = normalizeSubscriptionTier($user);
    }

    $is_premium = $user && isProUser($user);

    if ($is_premium) {
        $stmt = $pdo->query("
            SELECT id, name, slug, description, thumbnail_url, category, is_premium
            FROM templates
            WHERE is_active = TRUE
            ORDER BY category, name
        ");
    } else {
        $stmt = $pdo->query("
            SELECT id, name, slug, description, thumbnail_url, category, is_premium
            FROM templates
            WHERE is_active = TRUE AND is_premium = FALSE
            ORDER BY category, name
        ");
    }

    $templates = $stmt->fetchAll();

    return ['templates' => $templates];
}
