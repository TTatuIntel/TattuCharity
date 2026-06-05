<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!rate_limit('contact_' . client_ip(), 5, 120)) {
    json_response(['success' => false, 'message' => 'Too many submissions, please try later.'], 429);
}

$data = read_input_json();
$name    = trim((string)($data['name']    ?? ''));
$email   = trim((string)($data['email']   ?? ''));
$message = trim((string)($data['message'] ?? ''));

if ($name === '' || strlen($name) > 200) {
    json_response(['success' => false, 'message' => 'Please enter your name.'], 400);
}
if (!valid_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email.'], 400);
}
if ($message === '' || strlen($message) > 5000) {
    json_response(['success' => false, 'message' => 'Please write a message.'], 400);
}

$record = [
    'id'        => gen_id(),
    'name'      => $name,
    'email'     => $email,
    'message'   => $message,
    'createdAt' => date('c'),
    'ip'        => client_ip(),
    'read'      => false,
];

if (!append_json_record(MESSAGES_FILE, $record)) {
    json_response(['success' => false, 'message' => 'Could not save your message. Please try again.'], 500);
}

$subject = '[' . CHARITY_NAME . '] New contact message from ' . $name;
$body    = "A visitor submitted the contact form on your website.\n\n"
         . "From: {$name} <{$email}>\n\n{$message}\n\n"
         . "Submitted: " . date('Y-m-d H:i:s') . "\nIP: " . client_ip() . "\n\n"
         . "View and manage in Admin → Submissions.";
notify_info($subject, $body, $email);
// Send admin SMS first (if configured), then email notifications and auto-reply.
if (function_exists('send_admin_sms')) {
    $smsShort = "New contact from {$name}: " . substr($message, 0, 160);
    @send_admin_sms($smsShort);
}
send_contact_auto_reply($name, $email);

json_response(['success' => true, 'message' => 'Thank you! We will get back to you soon.']);
