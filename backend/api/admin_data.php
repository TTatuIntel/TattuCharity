<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_admin();

$messages  = read_json(MESSAGES_FILE, []);
$donations = read_json(DONATIONS_FILE, []);
$smtp      = smtp_config();
json_response([
    'success'          => true,
    'donations'        => array_reverse($donations),
    'messages'         => array_reverse($messages),
    'subscribers'      => array_reverse(read_json(SUBSCRIBERS_FILE, [])),
    'unreadMessages'   => count_unread_messages($messages),
    'pendingDonations' => count_pending_donations($donations),
    'notifyEmail'      => $smtp['notify_email'] ?: (defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : CHARITY_EMAIL),
    'smtpConfigured'   => smtp_is_configured(),
    'smtpFrom'         => $smtp['from_email'] ?: CHARITY_EMAIL,
]);
