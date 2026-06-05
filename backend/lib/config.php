<?php
/* =====================================================================
 *  TATTU CHARITY - CONFIGURATION
 *  Edit the values below to configure your installation.
 * =====================================================================
 *
 *  SETUP CHECKLIST
 *  ----------------
 *   1. Change ADMIN_PASSWORD_HASH in config (see comment for how to generate).
 *   2. Get MTN Mobile Money API credentials from
 *      https://momodeveloper.mtn.com and fill in the MOMO_* values.
 *   3. Update CHARITY_EMAIL to your real address.
 *   4. Copy backend/data/smtp.local.php.example → smtp.local.php and add SMTP credentials.
 *   5. Test mail: php backend/scripts/test-smtp.php
 *   6. Visit /backend/admin.php to log in and manage content.
 * ===================================================================== */

// Block direct browser access. API endpoints define TATTU_INTERNAL.
if (!defined('TATTU_INTERNAL')) {
    http_response_code(403);
    exit('Forbidden');
}

// 1) ADMIN PANEL PASSWORD ---------------------------------------------
// Store a bcrypt hash — never plain text. Current password: tadmin
// Generate a new hash: php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"
define('ADMIN_PASSWORD_HASH', '$2y$10$oXgHyXuEVfmsPV6XqqccPehHqu/kgJd./LZBFmqiP0/Ld7z41/xOW');

// 2) MTN MOBILE MONEY CREDENTIALS -------------------------------------
define('MOMO_ENV',              'sandbox');     // 'sandbox' or 'mtnuganda'
define('MOMO_SUBSCRIPTION_KEY', 'YOUR_SUBSCRIPTION_KEY');
define('MOMO_API_USER',         'YOUR_API_USER_UUID');
define('MOMO_API_KEY',          'YOUR_API_KEY');
define('MOMO_CALLBACK_HOST',    'tattucare.org');

// 3) CHARITY DETAILS (used for outgoing emails) -----------------------
define('CHARITY_EMAIL', 'info@tattucare.org');
define('CHARITY_NAME',  'Tattu Care');
// All website submissions (contact, donations, newsletter) notify this address.
define('NOTIFY_EMAIL', CHARITY_EMAIL);

// 4) SMTP (outgoing email) --------------------------------------------
// Defaults below; override credentials in backend/data/smtp.local.php (gitignored).
define('SMTP_ENABLED',     true);
define('SMTP_HOST',        'smtp.gmail.com');
define('SMTP_PORT',        587);
define('SMTP_ENCRYPTION',  'tls');   // tls | ssl | none
define('SMTP_USERNAME',    '');
define('SMTP_PASSWORD',    '');
define('SMTP_FROM_EMAIL',  CHARITY_EMAIL);
define('SMTP_FROM_NAME',   CHARITY_NAME);
define('SMTP_AUTO_REPLY',  true);    // send confirmation emails to visitors/donors

// 5) CURRENCY ---------------------------------------------------------
define('CURRENCY', MOMO_ENV === 'sandbox' ? 'EUR' : 'UGX');

// 6) PATHS (do not change unless you moved folders) -------------------
//    File layout:
//      backend/lib/config.php     <-- this file
//      backend/data/              <-- private data (json files)
//      backend/error.log          <-- private error log
define('DATA_DIR',         __DIR__ . '/../data/');
define('CONTENT_FILE',     DATA_DIR . 'content.json');
define('DONATIONS_FILE',   DATA_DIR . 'donations.json');
define('MESSAGES_FILE',    DATA_DIR . 'messages.json');
define('SUBSCRIBERS_FILE', DATA_DIR . 'subscribers.json');
define('ACTIVITY_FILE',    DATA_DIR . 'activity.json');

// 7) SESSION + ERROR LOGGING ------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    // Harden the session cookie BEFORE starting the session.
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('TATTU_SID');
    session_start();
}
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../error.log');

// 8) BASIC SECURITY HEADERS -------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
