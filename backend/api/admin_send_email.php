<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}
require_csrf();

if (!smtp_is_configured()) {
    json_response([
        'success' => false,
        'message' => 'SMTP is not configured. Add credentials in backend/data/smtp.local.php and run php backend/scripts/test-smtp.php',
    ], 503);
}

$data   = read_input_json();
$action = (string)($data['action'] ?? '');

if ($action === 'test') {
    $cfg = smtp_config();
    $to  = $cfg['notify_email'] ?: CHARITY_EMAIL;
    $subject = '[' . CHARITY_NAME . '] Admin SMTP test — ' . date('Y-m-d H:i:s');
    $body    = "SMTP test from the Tattu Care admin panel.\n\n"
             . "If you received this at {$to}, notifications and auto-replies are routed correctly.\n\n"
             . "Time: " . date('c');
    $ok = send_mail($to, $subject, $body, ['reply_to' => $cfg['from_email'] ?: CHARITY_EMAIL]);
    if (!$ok) {
        json_response([
            'success' => false,
            'message' => 'Test email failed. Regenerate a Gmail App Password (2-Step Verification required) and update smtp.local.php. Check backend/error.log.',
        ], 502);
    }
    json_response(['success' => true, 'message' => "Test email sent to {$to}"]);
}

if ($action === 'reply') {
    $to      = trim((string)($data['to'] ?? ''));
    $subject = trim((string)($data['subject'] ?? ''));
    $body    = trim((string)($data['body'] ?? ''));

    if (!valid_email($to)) {
        json_response(['success' => false, 'message' => 'Valid recipient email required.'], 400);
    }
    if ($subject === '') {
        json_response(['success' => false, 'message' => 'Subject is required.'], 400);
    }
    if ($body === '') {
        json_response(['success' => false, 'message' => 'Message body is required.'], 400);
    }

    $cfg = smtp_config();
    $ok  = send_mail($to, $subject, $body, ['reply_to' => $cfg['from_email'] ?: CHARITY_EMAIL]);
    if (!$ok) {
        json_response(['success' => false, 'message' => 'Could not send email. Check SMTP credentials in smtp.local.php.'], 502);
    }

    $messageId = trim((string)($data['messageId'] ?? ''));
    if ($messageId !== '') {
        $list = read_json(MESSAGES_FILE, []);
        if (is_array($list)) {
            foreach ($list as &$m) {
                if ((string)($m['id'] ?? '') === $messageId) {
                    $m['read'] = true;
                    $m['repliedAt'] = date('c');
                    break;
                }
            }
            unset($m);
            write_json(MESSAGES_FILE, $list);
        }
    }

    json_response(['success' => true, 'message' => 'Email sent to ' . $to]);
}

json_response(['success' => false, 'message' => 'Unknown action.'], 400);
