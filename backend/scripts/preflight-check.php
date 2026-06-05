<?php
/**
 * Pre-hosting smoke test — run: php backend/scripts/preflight-check.php
 */
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/momo.php';
require_once __DIR__ . '/../lib/fx.php';

// mailer.php is loaded via helpers.php

$ok = 0;
$fail = 0;
$warn = 0;

function check(bool $pass, string $label, string $detail = ''): void {
    global $ok, $fail;
    if ($pass) {
        $ok++;
        echo "[OK]   $label" . ($detail ? " — $detail" : '') . PHP_EOL;
    } else {
        $fail++;
        echo "[FAIL] $label" . ($detail ? " — $detail" : '') . PHP_EOL;
    }
}

function warn(string $label, string $detail = ''): void {
    global $warn;
    $warn++;
    echo "[WARN] $label" . ($detail ? " — $detail" : '') . PHP_EOL;
}

echo "=== Tattu Care preflight ===" . PHP_EOL . PHP_EOL;

$content = read_json(CONTENT_FILE, []);
check(is_array($content) && !empty($content['organization']['name']), 'content.json loads', $content['organization']['name'] ?? 'missing');
check(count($content['programs'] ?? []) >= 4, 'programs data', count($content['programs'] ?? []) . ' items');
check(count($content['impactStories'] ?? []) >= 3, 'impact stories', count($content['impactStories'] ?? []) . ' items');
check(count($content['events'] ?? []) >= 2, 'events data', count($content['events'] ?? []) . ' items');
check(count($content['team'] ?? []) >= 2, 'team data', count($content['team'] ?? []) . ' members');

$messages = read_json(MESSAGES_FILE, []);
$donations = read_json(DONATIONS_FILE, []);
$subs = read_json(SUBSCRIBERS_FILE, []);
if (count($messages) === 0) {
    warn('messages.json', 'No contact messages yet — empty live database');
} else {
    check(count($messages) >= 1, 'messages.json', count($messages) . ' messages');
}
if (count($donations) === 0) {
    warn('donations.json', 'No donations yet — empty live database');
} else {
    check(count($donations) >= 1, 'donations.json', count($donations) . ' donations');
}
if (count($subs) === 0) {
    warn('subscribers.json', 'No subscribers yet — empty live database');
} else {
    check(count($subs) >= 1, 'subscribers.json', count($subs) . ' subscribers');
}

$unread = count(array_filter($messages, fn($m) => empty($m['read'])));
echo "       Unread messages: $unread" . PHP_EOL;

$momo = new MomoClient();
check(!$momo->isConfigured(), 'MoMo credentials', 'not configured (expected until production keys added)');

try {
    $rates = fx_get_rates();
    check(!empty($rates['USD']) && !empty($rates['EUR']), 'FX rates', 'USD/EUR available');
} catch (Throwable $e) {
    check(false, 'FX rates', $e->getMessage());
}

check(is_writable(DATA_DIR), 'data/ writable', DATA_DIR);
check(file_exists(CONTENT_FILE), 'content file exists');
check(password_verify('tadmin', ADMIN_PASSWORD_HASH), 'admin password hash', 'tadmin verifies');

if (smtp_is_configured()) {
    echo "       SMTP: configured (" . smtp_config()['host'] . ')' . PHP_EOL;
} else {
    warn('SMTP email', 'Not configured — copy smtp.local.php.example → smtp.local.php');
}

if (function_exists('sms_is_configured') && sms_is_configured()) {
    echo "       SMS: configured (Twilio)" . PHP_EOL;
} else {
    warn('SMS notifications', 'Not configured — copy sms.local.php.example → sms.local.php');
}

if (MOMO_SUBSCRIPTION_KEY === 'YOUR_SUBSCRIPTION_KEY') {
    warn('MTN MoMo', 'Placeholder keys — live payments will queue as manual_pending');
}
if (empty($content['donation']['airtelMerchantCode'])) {
    warn('Airtel Money', 'airtelMerchantCode empty — Airtel pledges only');
}
if (($content['organization']['facebook'] ?? '#') === '#') {
    warn('Social links', 'Facebook still placeholder');
}

$teamNoImg = count(array_filter($content['team'] ?? [], fn($t) => empty($t['image'])));
if ($teamNoImg > 0) {
    warn('Team photos', "$teamNoImg member(s) missing images");
}

echo PHP_EOL . "=== Summary: $ok passed, $fail failed, $warn warnings ===" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
