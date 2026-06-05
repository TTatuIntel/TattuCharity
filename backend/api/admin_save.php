<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}
require_csrf();
if (!rate_limit('admin_save_' . client_ip(), 30, 60)) {
    json_response(['success' => false, 'message' => 'Too many save requests. Please wait a moment.'], 429);
}

$content = read_input_json();
if (empty($content) || !is_array($content)) {
    json_response(['success' => false, 'message' => 'Invalid content payload'], 400);
}

// Snapshot existing content before overwriting
$existing = read_json(CONTENT_FILE, null);
if ($existing !== null) {
    $backupDir = DATA_DIR . 'backups/';
    if (!is_dir($backupDir)) @mkdir($backupDir, 0775, true);
    @file_put_contents(
        $backupDir . 'content_' . date('Ymd_His') . '.json',
        json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    $files = glob($backupDir . 'content_*.json') ?: [];
    if (count($files) > 20) {
        usort($files, fn($a,$b) => filemtime($a) <=> filemtime($b));
        foreach (array_slice($files, 0, count($files) - 20) as $old) @unlink($old);
    }
}

if (!write_json(CONTENT_FILE, $content)) {
    json_response(['success' => false, 'message' => 'Could not save content.'], 500);
}

json_response(['success' => true, 'message' => 'Content saved.']);
