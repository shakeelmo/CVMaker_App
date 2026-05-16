<?php
// Resume routes

require_once __DIR__ . '/../middleware/auth.php';

function getUserResumes() {
    $user = requireAuth();
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT r.*, t.name as template_name, t.thumbnail_url
        FROM resumes r
        LEFT JOIN templates t ON r.template_id = t.id
        WHERE r.user_id = ? AND r.is_active = TRUE
        ORDER BY r.updated_at DESC
    ");
    $stmt->execute([$user['id']]);
    
    return ['resumes' => $stmt->fetchAll()];
}

function getResume($id) {
    $user = requireAuth();
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT r.*, t.name as template_name, t.thumbnail_url
        FROM resumes r
        LEFT JOIN templates t ON r.template_id = t.id
        WHERE r.id = ? AND r.user_id = ? AND r.is_active = TRUE
    ");
    $stmt->execute([$id, $user['id']]);
    $resume = $stmt->fetch();
    
    if (!$resume) {
        return ['error' => 'Resume not found'];
    }

    $contentData = [
        'title' => $resume['title'] ?? '',
        'basics' => ['name' => '', 'headline' => '', 'email' => '', 'phone' => '', 'location' => '', 'linkedIn' => '', 'url' => ''],
        'target' => ['jobTitle' => '', 'industry' => '', 'jobDescription' => ''],
        'summary' => '',
        'skills' => [],
        'experience' => [],
        'education' => [],
        'certifications' => [],
        'projects' => [],
        'languages' => [],
        'template' => 'modern',
        'settings' => ['font' => 'Inter', 'format' => 'a4', 'color' => '#2563eb', 'sectionOrder' => 'skills-first']
    ];

    if (!empty($resume['content'])) {
        $decoded = is_string($resume['content']) ? json_decode($resume['content'], true) : $resume['content'];
        if (is_array($decoded)) {
            $contentData = array_replace_recursive($contentData, $decoded);
        }
    }
    
    $resume['content'] = $contentData;
    
    try {
        $pdo->prepare("UPDATE resumes SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);
    } catch (Exception $e) {
    }
    
    return ['resume' => $resume];
}

function createResume($data) {
    $user = requireAuth();
    $pdo = getDB();
    
    if ($user['subscription_tier'] === 'free') {
        // Get free-tier resume limit from settings (default 1)
        $limitStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'max_resumes_per_free_user'");
        $limitStmt->execute();
        $limitRow = $limitStmt->fetch();
        $freeLimit = $limitRow ? intval($limitRow['setting_value']) : 1;
        if ($freeLimit < 0) $freeLimit = 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM resumes WHERE user_id = ? AND is_active = TRUE");
        $stmt->execute([$user['id']]);
        $count = $stmt->fetchColumn();
        
        if ($count >= $freeLimit) {
            return ['error' => 'Free tier limited to ' . $freeLimit . ' resume' . ($freeLimit !== 1 ? 's' : ''), 'upgrade_required' => true, 'free_limit' => $freeLimit];
        }
    }
    
    $title = htmlspecialchars($data['title'] ?? 'My Resume');
    $template_id = intval($data['template_id'] ?? 1);
    $slug = 'resume-' . uniqid();
    $content = $data['content'] ?? null;
    if (is_array($content)) {
        $content = json_encode($content);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO resumes (user_id, title, slug, template_id, content)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    try {
        $stmt->execute([$user['id'], $title, $slug, $template_id, $content]);
        $resume_id = $pdo->lastInsertId();
        
        return [
            'message' => 'Resume created successfully',
            'id' => (int)$resume_id,
            'resume_id' => (int)$resume_id,
            'slug' => $slug
        ];
    } catch (PDOException $e) {
        error_log('createResume failed: ' . $e->getMessage());
        return ['error' => 'Failed to create resume'];
    }
}

function updateResume($id, $data) {
    $user = requireAuth();
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT id FROM resumes WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    if (!$stmt->fetch()) {
        return ['error' => 'Resume not found'];
    }
    
    $title = isset($data['title']) ? htmlspecialchars($data['title']) : null;
    $template_id = isset($data['template_id']) ? intval($data['template_id']) : null;
    
    $updates = [];
    $params = [];
    
    if ($title) {
        $updates[] = 'title = ?';
        $params[] = $title;
    }
    if ($template_id) {
        $updates[] = 'template_id = ?';
        $params[] = $template_id;
    }
    
    $updates[] = 'updated_at = NOW()';
    
    if (!empty($updates)) {
        $params[] = $id;
        $sql = "UPDATE resumes SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    if (array_key_exists('content', $data)) {
        $content = is_string($data['content']) ? $data['content'] : json_encode($data['content']);
        $stmt = $pdo->prepare("UPDATE resumes SET content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$content, $id]);
    }
    
    return ['message' => 'Resume updated successfully'];
}

function deleteResume($id) {
    $user = requireAuth();
    $pdo = getDB();
    
    $stmt = $pdo->prepare("DELETE FROM resumes WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    
    if ($stmt->rowCount() === 0) {
        return ['error' => 'Resume not found'];
    }
    
    return ['message' => 'Resume deleted permanently'];
}
