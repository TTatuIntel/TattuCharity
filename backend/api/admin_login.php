<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$ip       = client_ip();
$lockFile = DATA_DIR . 'lock_login_' . preg_replace('/[^a-z0-9]/i', '', $ip) . '.json';

// Lockout: after 5 failed attempts within 15min, block this IP for 15min more.
$lock = read_json($lockFile, ['fails' => 0, 'firstFailAt' => 0, 'lockedUntil' => 0]);
$now  = time();
if (!empty($lock['lockedUntil']) && $now < $lock['lockedUntil']) {
    $minsLeft = (int) ceil(($lock['lockedUntil'] - $now) / 60);
    json_response([
        'success' => false,
        'message' => "Too many failed attempts. Try again in {$minsLeft} minute(s)."
    ], 429);
}
// Reset window if older than 15min and not currently locked.
if ($now - (int)($lock['firstFailAt'] ?? 0) > 900 && empty($lock['lockedUntil'])) {
    $lock = ['fails' => 0, 'firstFailAt' => 0, 'lockedUntil' => 0];
}

$data     = read_input_json();
require_csrf();
$password = (string)($data['password'] ?? '');

if (!verify_admin_password($password)) {
    $lock['fails']        = (int)($lock['fails'] ?? 0) + 1;
    $lock['firstFailAt']  = $lock['firstFailAt'] ?: $now;
    if ($lock['fails'] >= 5) {
        $lock['lockedUntil'] = $now + 900; // 15 minutes
        $lock['fails']       = 0;
        $lock['firstFailAt'] = 0;
        write_json($lockFile, $lock);
        error_log("admin_login: IP {$ip} locked out");
        json_response(['success' => false, 'message' => 'Too many failed attempts. Locked for 15 minutes.'], 429);
    }
    write_json($lockFile, $lock);
    json_response(['success' => false, 'message' => 'Incorrect password.'], 401);
}

// Success: clear lockout state and rotate session.
@unlink($lockFile);
session_regenerate_id(true);
$_SESSION['is_admin']   = true;
$_SESSION['login_time'] = $now;
$_SESSION['login_ip']   = $ip;
csrf_token(); // issue token for subsequent admin API calls

json_response(['success' => true, 'message' => 'Welcome.', 'csrf' => csrf_token()]);
