<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}
require_csrf();

$data   = read_input_json();
$action = (string)($data['action'] ?? '');
$list   = read_json(MESSAGES_FILE, []);
if (!is_array($list)) $list = [];

if ($action === 'mark_all_read') {
    foreach ($list as &$m) {
        $m['read'] = true;
    }
    unset($m);
} elseif ($action === 'mark_read') {
    $ids = $data['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) {
        json_response(['success' => false, 'message' => 'No message IDs provided.'], 400);
    }
    $idSet = array_flip(array_map('strval', $ids));
    foreach ($list as &$m) {
        if (isset($idSet[(string)($m['id'] ?? '')])) {
            $m['read'] = true;
        }
    }
    unset($m);
} else {
    json_response(['success' => false, 'message' => 'Unknown action.'], 400);
}

if (!write_json(MESSAGES_FILE, $list)) {
    json_response(['success' => false, 'message' => 'Could not update messages.'], 500);
}

json_response([
    'success'        => true,
    'unreadMessages' => count_unread_messages($list),
]);
