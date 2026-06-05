<?php
if (!defined('TATTU_INTERNAL')) { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/tracking.php';

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
    // Use REMOTE_ADDR only — X-Forwarded-For can be spoofed and breaks session binding.
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void {
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_response(['success' => false, 'message' => 'Security token invalid. Refresh the page and try again.'], 403);
    }
}

function verify_admin_password(string $password): bool {
    if (defined('ADMIN_PASSWORD_HASH') && ADMIN_PASSWORD_HASH !== '') {
        return password_verify($password, ADMIN_PASSWORD_HASH);
    }
    return defined('ADMIN_PASSWORD') && hash_equals((string)ADMIN_PASSWORD, $password);
}

/**
 * Send a plain-text notification to the site inbox (NOTIFY_EMAIL).
 * Used for contact form, donations, newsletter — admin gets email + in-panel copy.
 */
function notify_info(string $subject, string $body, ?string $replyTo = null): void {
    $cfg = smtp_config();
    $to  = $cfg['notify_email'] ?: CHARITY_EMAIL;
    $defaultReply = $cfg['from_email'] ?: CHARITY_EMAIL;
    send_mail($to, $subject, $body, ['reply_to' => $replyTo ?: $defaultReply]);
}

/**
 * Send a confirmation or informational email to a site visitor.
 */
function notify_user(string $to, string $subject, string $body): void {
    if (!valid_email($to)) {
        return;
    }
    $cfg = smtp_config();
    if (!$cfg['auto_reply']) {
        return;
    }
    send_mail($to, $subject, $body, [
        'reply_to' => $cfg['from_email'] ?: CHARITY_EMAIL,
    ]);
}

function send_contact_auto_reply(string $name, string $email): void {
    $body = "Dear {$name},\n\n"
          . "Thank you for contacting " . CHARITY_NAME . ". We have received your message "
          . "and a member of our team will respond as soon as possible.\n\n"
          . "If your enquiry is urgent, you can also reach us at " . CHARITY_EMAIL . ".\n\n"
          . "With gratitude,\n"
          . "The " . CHARITY_NAME . " Team";
    notify_user(
        $email,
        'We received your message — ' . CHARITY_NAME,
        $body
    );
}

function send_newsletter_welcome(string $email): void {
    $body = "Thank you for subscribing to " . CHARITY_NAME . " updates.\n\n"
          . "You will receive news about our programs, collection drives, and impact stories "
          . "in communities across Uganda.\n\n"
          . "To unsubscribe, reply to this email or contact us at " . CHARITY_EMAIL . ".\n\n"
          . "With gratitude,\n"
          . "The " . CHARITY_NAME . " Team";
    notify_user(
        $email,
        'Welcome to ' . CHARITY_NAME . ' updates',
        $body
    );
}

function send_donation_auto_reply(array $record): void {
    $email = trim((string) ($record['email'] ?? ''));
    if ($email === '') {
        return;
    }

    $name   = trim((string) ($record['name'] ?? '')) ?: 'Friend';
    $amt    = ($record['currency'] ?? '') . ' ' . ($record['amount'] ?? '');
    $method = strtoupper((string) ($record['method'] ?? 'donation'));
    $status = (string) ($record['status'] ?? '');

    switch ($status) {
        case 'awaiting_approval':
            $nextStep = 'Please approve the Mobile Money prompt on your phone to complete the payment.';
            break;
        case 'crypto_pledged':
            $nextStep = 'We will verify your cryptocurrency transfer on-chain and email you once it is confirmed.';
            break;
        case 'bank_pledged':
            $nextStep = 'We will verify your bank transfer and email you once it is confirmed.';
            break;
        case 'airtel_pledged':
            $nextStep = 'Our team will contact you shortly with Airtel Money payment instructions.';
            break;
        case 'manual_pending':
            $nextStep = 'Our team will contact you shortly to help complete your donation.';
            break;
        case 'success':
        case 'completed':
            $nextStep = 'Your donation has been recorded. Thank you for your generous support!';
            break;
        default:
            $nextStep = 'Our team will follow up if any further action is needed.';
    }

    $body = "Dear {$name},\n\n"
          . "Thank you for your generous support of " . CHARITY_NAME . ".\n\n"
          . "Donation summary\n"
          . "----------------\n"
          . "Amount: {$amt}\n"
          . "Method: {$method}\n\n"
          . "{$nextStep}\n\n"
          . "Your gift helps provide clean water, education, and healthcare to communities in Uganda.\n\n"
          . "Questions? Reply to this email or contact us at " . CHARITY_EMAIL . ".\n\n"
          . "With gratitude,\n"
          . "The " . CHARITY_NAME . " Team";

    notify_user(
        $email,
        'Thank you for supporting ' . CHARITY_NAME,
        $body
    );
}

function count_unread_messages(array $messages): int {
    $n = 0;
    foreach ($messages as $m) {
        if (empty($m['read'])) $n++;
    }
    return $n;
}

function gen_id(): string {
    return bin2hex(random_bytes(8));
}

function donation_allowed_statuses(): array {
    return [
        'success', 'completed', 'bank_pledged', 'airtel_pledged', 'crypto_pledged',
        'awaiting_approval', 'manual_pending', 'pending',
        'failed', 'cancelled', 'rejected',
    ];
}

function donation_allowed_methods(): array {
    return ['momo', 'airtel', 'bank', 'crypto', 'manual', 'cash'];
}

function donation_pending_statuses(): array {
    return ['bank_pledged', 'airtel_pledged', 'crypto_pledged', 'awaiting_approval', 'manual_pending', 'pending'];
}

function count_pending_donations(array $donations): int {
    $pending = array_flip(donation_pending_statuses());
    $n = 0;
    foreach ($donations as $d) {
        $st = strtolower((string)($d['status'] ?? ''));
        if (isset($pending[$st])) {
            $n++;
        }
    }
    return $n;
}

function find_donation_index(array $list, string $id): int {
    foreach ($list as $i => $d) {
        if ((string)($d['id'] ?? '') === $id) {
            return $i;
        }
    }
    return -1;
}

function sanitize_donation_fields(array $data, bool $isCreate = false): array {
    $method = strtolower(trim((string)($data['method'] ?? ($isCreate ? 'manual' : ''))));
    if ($method !== '' && !in_array($method, donation_allowed_methods(), true)) {
        json_response(['success' => false, 'message' => 'Invalid payment method.'], 400);
    }

    $status = strtolower(trim((string)($data['status'] ?? ($isCreate ? 'success' : ''))));
    if ($status !== '' && !in_array($status, donation_allowed_statuses(), true)) {
        json_response(['success' => false, 'message' => 'Invalid donation status.'], 400);
    }

    $amount = isset($data['amount']) ? (float)$data['amount'] : ($isCreate ? 0 : null);
    if ($isCreate && $amount < 1) {
        json_response(['success' => false, 'message' => 'Amount must be at least 1.'], 400);
    }
    if (!$isCreate && $amount !== null && $amount < 0) {
        json_response(['success' => false, 'message' => 'Amount cannot be negative.'], 400);
    }

    $currency = strtoupper(trim((string)($data['currency'] ?? '')));
    if ($currency !== '' && !preg_match('/^[A-Z]{3}$/', $currency)) {
        json_response(['success' => false, 'message' => 'Invalid currency code.'], 400);
    }
    if ($isCreate && $currency === '') {
        json_response(['success' => false, 'message' => 'Currency is required.'], 400);
    }

    $email = trim((string)($data['email'] ?? ''));
    if ($email !== '' && !valid_email($email)) {
        json_response(['success' => false, 'message' => 'Invalid email address.'], 400);
    }

    $phone = preg_replace('/\s+/', '', (string)($data['phone'] ?? ''));
    if ($phone !== '' && !valid_phone($phone)) {
        json_response(['success' => false, 'message' => 'Phone must be 256XXXXXXXXX.'], 400);
    }

    $out = [];
    if ($method !== '') $out['method'] = $method;
    if ($status !== '') $out['status'] = $status;
    if ($amount !== null) $out['amount'] = $amount;
    if ($currency !== '') $out['currency'] = $currency;
    if (array_key_exists('name', $data)) $out['name'] = trim((string)$data['name']);
    if (array_key_exists('email', $data)) $out['email'] = $email;
    if (array_key_exists('phone', $data)) $out['phone'] = $phone;
    if (array_key_exists('reference', $data)) {
        $ref = trim((string)$data['reference']);
        $out['reference'] = $ref !== '' ? $ref : null;
    }
    if (array_key_exists('message', $data)) {
        $msg = trim((string)$data['message']);
        $out['message'] = $msg !== '' ? $msg : null;
    }
    if (array_key_exists('adminNote', $data)) {
        $note = trim((string)$data['adminNote']);
        $out['adminNote'] = $note !== '' ? $note : null;
    }
    return $out;
}

function notify_donation(array $record): void {
    $name   = $record['name'] ?: 'Anonymous';
    $email  = $record['email'] ?: '';
    $amt    = ($record['currency'] ?? '') . ' ' . ($record['amount'] ?? '');
    $method = strtoupper($record['method'] ?? 'momo');
    $status = $record['status'] ?? 'pending';
    $body   = "A new donation was submitted on your website.\n\n"
            . "Donor: {$name}" . ($email ? " <{$email}>" : '') . "\n"
            . "Amount: {$amt}\nMethod: {$method}\nStatus: {$status}\n"
            . "Phone: " . ($record['phone'] ?? '—') . "\n"
            . "Reference: " . ($record['reference'] ?? '—') . "\n\n"
            . "Submitted: " . ($record['createdAt'] ?? date('c')) . "\n"
            . "View in Admin → Submissions.";
    notify_info('[' . CHARITY_NAME . "] Donation ({$status}): {$amt} from {$name}", $body, $email ?: null);
    send_donation_auto_reply($record);
}
