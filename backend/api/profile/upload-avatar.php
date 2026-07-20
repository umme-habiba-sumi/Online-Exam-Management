<?php
/**
 * POST /backend/api/profile/upload-avatar.php
 * multipart/form-data: avatar (image file)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

sendCorsHeaders();
requireMethod('POST');
requireCsrf();
$user = requireLogin();
$userId = (int) $user['id'];

ensureAvatarColumn(db());

if (empty($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
    jsonError('Choose an image to upload.');
}

$file = $_FILES['avatar'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    jsonError('Upload failed. Try again.');
}

if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
    jsonError('Image must be 2 MB or smaller.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']) ?: '';
$map = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
if (!isset($map[$mime])) {
    jsonError('Use a JPG, PNG, or WebP image.');
}

$ext = $map[$mime];
$dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    jsonError('Could not create upload folder.', 500);
}

$filename = 'u' . $userId . '_' . time() . '.' . $ext;
$dest = $dir . DIRECTORY_SEPARATOR . $filename;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    jsonError('Could not save image.', 500);
}

$relative = 'uploads/avatars/' . $filename;

// Remove previous avatar file if it was under uploads/avatars/
$old = $user['avatar'] ?? null;
if (is_string($old) && str_starts_with($old, 'uploads/avatars/')) {
    $oldPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $old);
    if (is_file($oldPath)) {
        @unlink($oldPath);
    }
}

try {
    db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$relative, $userId]);
} catch (Throwable $e) {
    @unlink($dest);
    jsonError('Could not update profile photo.', 500);
}

$fresh = db()->prepare(
    'SELECT id, name, email, role, roll_or_id, department, designation, avatar, created_at
     FROM users WHERE id = ? LIMIT 1'
);
$fresh->execute([$userId]);
$row = $fresh->fetch();

jsonResponse([
    'ok'         => true,
    'user'       => publicUser($row ?: array_merge($user, ['avatar' => $relative])),
    'csrf_token' => csrfToken(),
]);
