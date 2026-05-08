<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!rate_limit('login_' . client_ip(), 5, 300)) {
    json_response(['success' => false, 'message' => 'Too many attempts. Try again in a few minutes.'], 429);
}

$data     = read_input_json();
$password = (string)($data['password'] ?? '');

if (!hash_equals(ADMIN_PASSWORD, $password)) {
    json_response(['success' => false, 'message' => 'Incorrect password.'], 401);
}

session_regenerate_id(true);
$_SESSION['is_admin']   = true;
$_SESSION['login_time'] = time();

json_response(['success' => true, 'message' => 'Welcome.']);
