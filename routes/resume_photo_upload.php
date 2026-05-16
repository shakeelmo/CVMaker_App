<?php

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';

function uploadResumePhoto() {
    $user = requireAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['error' => 'Method not allowed'];
    }

    if (!isset($_FILES['photo'])) {
        return ['error' => 'Please choose a JPG, PNG, or WEBP image.'];
    }

    $file = $_FILES['photo'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['error' => friendlyPhotoUploadError($file['error'] ?? UPLOAD_ERR_NO_FILE)];
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['error' => 'Photo is too large. Maximum size is 2MB.'];
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if (!$tmpPath || !is_uploaded_file($tmpPath)) {
        return ['error' => 'Upload could not be verified. Please try again.'];
    }

    $imageInfo = @getimagesize($tmpPath);
    $mimeType = $imageInfo['mime'] ?? ($file['type'] ?? '');
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mimeType])) {
        return ['error' => 'Invalid image type. Please upload JPG, PNG, or WEBP.'];
    }

    $ext = $allowed[$mimeType];
    $uploadDir = dirname(__DIR__) . '/uploads/photos/' . (int)$user['id'];
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return ['error' => 'Could not prepare photo upload folder.'];
    }

    $filename = 'profile_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        return ['error' => 'Could not save the uploaded photo. Please try again.'];
    }

    @chmod($destination, 0644);

    return [
        'message' => 'Photo uploaded successfully',
        'url' => 'https://cvmaker.ink/uploads/photos/' . (int)$user['id'] . '/' . $filename
    ];
}

function friendlyPhotoUploadError($code) {
    switch ((int)$code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Photo is too large. Maximum size is 2MB.';
        case UPLOAD_ERR_PARTIAL:
            return 'The photo upload was interrupted. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'Please choose a JPG, PNG, or WEBP image.';
        default:
            return 'The photo upload failed. Please try again.';
    }
}
