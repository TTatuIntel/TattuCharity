<?php
/**
 * Image upload endpoint (admin only).
 *
 * Accepts multipart/form-data with field "file".
 * Validates: size <= 5MB, real image type via getimagesize,
 * extension whitelist, sanitised filename.
 *
 * Saves to: <project-root>/frontend/images/uploads/<random>.<ext>
 * Returns:  { success, url } where url is relative to the project root.
 */
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}
require_csrf();

if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
    json_response(['success' => false, 'message' => 'No file received'], 400);
}

$f = $_FILES['file'];

// PHP-level upload error?
if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Server tmp dir missing',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the file',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload',
    ];
    json_response(['success' => false, 'message' => $errors[$f['error']] ?? 'Upload failed'], 400);
}

// 5 MB max
$MAX_BYTES = 5 * 1024 * 1024;
if ($f['size'] > $MAX_BYTES) {
    json_response(['success' => false, 'message' => 'File too large (max 5MB).'], 400);
}

if (!is_uploaded_file($f['tmp_name'])) {
    json_response(['success' => false, 'message' => 'Invalid upload'], 400);
}

// Whitelist by actual image content (not just header / extension)
$info = @getimagesize($f['tmp_name']);
if (!$info || empty($info['mime'])) {
    json_response(['success' => false, 'message' => 'Not a valid image file.'], 400);
}
$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
if (!isset($mimeToExt[$info['mime']])) {
    json_response(['success' => false, 'message' => 'Only JPG, PNG, GIF, or WEBP allowed.'], 400);
}
$ext = $mimeToExt[$info['mime']];

// Destination: <project>/frontend/images/uploads/
$projectRoot = realpath(__DIR__ . '/../../');
if ($projectRoot === false) {
    json_response(['success' => false, 'message' => 'Server path error'], 500);
}
$destDir = $projectRoot . DIRECTORY_SEPARATOR
         . 'frontend' . DIRECTORY_SEPARATOR
         . 'images'   . DIRECTORY_SEPARATOR
         . 'uploads'  . DIRECTORY_SEPARATOR;

if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
    error_log('admin_upload: cannot create dir ' . $destDir);
    json_response(['success' => false, 'message' => 'Could not create upload directory'], 500);
}

// Random filename - never trust user input. Embed a slug from original name
// for human readability but always prefix with a random token.
$origName = pathinfo($f['name'] ?? '', PATHINFO_FILENAME);
$slug     = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $origName));
$slug     = trim($slug, '-');
if (strlen($slug) > 30) $slug = substr($slug, 0, 30);
if ($slug === '')       $slug = 'image';

$filename = bin2hex(random_bytes(6)) . '_' . $slug . '.' . $ext;
$destPath = $destDir . $filename;

if (!@move_uploaded_file($f['tmp_name'], $destPath)) {
    error_log('admin_upload: move_uploaded_file failed for ' . $destPath);
    json_response(['success' => false, 'message' => 'Could not save uploaded file'], 500);
}

// URL is relative to the project root (works whether the site is at /
// or under a sub-path on Apache/XAMPP).
$relUrl = 'frontend/images/uploads/' . $filename;

json_response([
    'success'  => true,
    'url'      => $relUrl,
    'filename' => $filename,
    'size'     => $f['size'],
    'width'    => $info[0] ?? null,
    'height'   => $info[1] ?? null,
]);
