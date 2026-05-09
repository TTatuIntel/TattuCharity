<?php
if (!defined('TATTU_INTERNAL')) { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/config.php';

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json(string $file, $default = []) {
    if (!file_exists($file)) return $default;
    $contents = @file_get_contents($file);
    if ($contents === false || $contents === '') return $default;
    $decoded = json_decode($contents, true);
    return $decoded ?? $default;
}

function write_json(string $file, $data): bool {
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        error_log("write_json: cannot create dir $dir");
        return false;
    }
    $tmp = $file . '.tmp';
    $bytes = @file_put_contents(
        $tmp,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
    if ($bytes === false) {
        error_log("write_json: failed to write $tmp");
        return false;
    }
    return @rename($tmp, $file);
}

function append_json_record(string $file, array $record): bool {
    $list = read_json($file, []);
    if (!is_array($list)) $list = [];
    $list[] = $record;
    return write_json($file, $list);
}

function require_admin(): void {
    if (empty($_SESSION['is_admin'])) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    // 2-hour absolute session timeout
    $age = time() - (int)($_SESSION['login_time'] ?? 0);
    if ($age > 7200) {
        $_SESSION = [];
        session_destroy();
        json_response(['success' => false, 'message' => 'Session expired. Please log in again.'], 401);
    }
    // Bind session to the originating IP to defeat trivial hijacking
    if (!empty($_SESSION['login_ip']) && $_SESSION['login_ip'] !== client_ip()) {
        $_SESSION = [];
        session_destroy();
        error_log('require_admin: session IP mismatch, destroyed');
        json_response(['success' => false, 'message' => 'Session invalid.'], 401);
    }
}

function read_input_json(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function valid_email(string $e): bool {
    return (bool) filter_var($e, FILTER_VALIDATE_EMAIL);
}

function valid_phone(string $p): bool {
    $p = preg_replace('/\s+/', '', $p);
    return (bool) preg_match('/^256[0-9]{9}$/', $p);
}

function rate_limit(string $key, int $max = 10, int $windowSec = 60): bool {
    $file = DATA_DIR . 'rate_' . preg_replace('/[^a-z0-9]/i', '', $key) . '.json';
    $now  = time();
    $rec  = read_json($file, ['count' => 0, 'reset' => $now + $windowSec]);
    if ($now > ($rec['reset'] ?? 0)) {
        $rec = ['count' => 0, 'reset' => $now + $windowSec];
    }
    $rec['count'] = (int)($rec['count'] ?? 0) + 1;
    write_json($file, $rec);
    return $rec['count'] <= $max;
}

function client_ip(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function gen_id(): string {
    return bin2hex(random_bytes(8));
}
