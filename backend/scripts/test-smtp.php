<?php
/**
 * SMTP test — run from project root:
 *   php backend/scripts/test-smtp.php
 *   php backend/scripts/test-smtp.php --to=you@example.com
 */
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';

$to = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--to=') === 0) {
        $to = substr($arg, 5);
    }
}

$cfg = smtp_config();
$to  = $to ?: ($cfg['notify_email'] ?: CHARITY_EMAIL);

echo "=== Tattu Care SMTP test ===" . PHP_EOL . PHP_EOL;
echo "SMTP enabled:  " . ($cfg['enabled'] ? 'yes' : 'no') . PHP_EOL;
echo "SMTP host:     " . ($cfg['host'] ?: '(not set)') . PHP_EOL;
echo "SMTP port:     " . $cfg['port'] . PHP_EOL;
echo "Encryption:    " . $cfg['encryption'] . PHP_EOL;
echo "From:          " . $cfg['from_name'] . ' <' . $cfg['from_email'] . '>' . PHP_EOL;
echo "Notify inbox:  " . ($cfg['notify_email'] ?: CHARITY_EMAIL) . PHP_EOL;
echo "Auto-replies:  " . ($cfg['auto_reply'] ? 'on' : 'off') . PHP_EOL;
echo "Local config:  " . (is_file(DATA_DIR . 'smtp.local.php') ? 'smtp.local.php found' : 'missing — copy smtp.local.php.example') . PHP_EOL;
echo PHP_EOL;

if (!smtp_is_configured()) {
    echo "[FAIL] SMTP is not fully configured." . PHP_EOL;
    echo "       Copy backend/data/smtp.local.php.example to smtp.local.php and add your credentials." . PHP_EOL;
    exit(1);
}

$subject = '[' . CHARITY_NAME . '] SMTP test — ' . date('Y-m-d H:i:s');
$body    = "This is a test email from the Tattu Care website.\n\n"
         . "If you received this, SMTP is working correctly.\n\n"
         . "Server: " . php_uname('n') . "\n"
         . "Time: " . date('c') . "\n";

echo "Sending test email to: {$to} ..." . PHP_EOL;

$ok = send_mail($to, $subject, $body, ['reply_to' => $cfg['from_email']]);

if ($ok) {
    echo "[OK]   Email sent successfully. Check the inbox (and spam folder)." . PHP_EOL;
    exit(0);
}

echo "[FAIL] Could not send email." . PHP_EOL;

// Try to show recent relevant error log lines to help debugging.
$errFile = __DIR__ . '/../error.log';
if (is_file($errFile) && is_readable($errFile)) {
    echo PHP_EOL . "=== Recent error.log ===" . PHP_EOL;
    $lines = @file($errFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $last = array_slice($lines, -20);
    foreach ($last as $ln) {
        echo $ln . PHP_EOL;
    }
    echo "=== End error.log ===" . PHP_EOL;
} else {
    echo "No readable backend/error.log found at {$errFile}" . PHP_EOL;
}

echo PHP_EOL . "Hint: Gmail requires a valid App Password (with 2-Step Verification) for SMTP." . PHP_EOL;
echo "See: https://support.google.com/accounts/answer/185833" . PHP_EOL;
exit(1);
