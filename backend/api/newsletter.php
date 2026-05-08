<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!rate_limit('news_' . client_ip(), 10, 120)) {
    json_response(['success' => false, 'message' => 'Too many requests.'], 429);
}

$data  = read_input_json();
$email = trim((string)($data['email'] ?? ''));

if (!valid_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email.'], 400);
}

$list = read_json(SUBSCRIBERS_FILE, []);
foreach ($list as $row) {
    if (strcasecmp($row['email'] ?? '', $email) === 0) {
        json_response(['success' => true, 'message' => "You're already subscribed - thank you!"]);
    }
}

$list[] = [
    'id'        => gen_id(),
    'email'     => $email,
    'createdAt' => date('c'),
    'ip'        => client_ip(),
];

if (!write_json(SUBSCRIBERS_FILE, $list)) {
    json_response(['success' => false, 'message' => 'Could not save subscription. Please try again.'], 500);
}

json_response(['success' => true, 'message' => 'Thank you for subscribing!']);
