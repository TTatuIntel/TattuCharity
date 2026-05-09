<?php
/* =====================================================================
 *  TATTU CHARITY - CONFIGURATION
 *  Edit the values below to configure your installation.
 * =====================================================================
 *
 *  SETUP CHECKLIST
 *  ----------------
 *   1. Change ADMIN_PASSWORD below to a strong password.
 *   2. Get MTN Mobile Money API credentials from
 *      https://momodeveloper.mtn.com and fill in the MOMO_* values.
 *   3. Update CHARITY_EMAIL to your real address.
 *   4. Visit /backend/admin.php to log in and manage content.
 * ===================================================================== */

// Block direct browser access. API endpoints define TATTU_INTERNAL.
if (!defined('TATTU_INTERNAL')) {
    http_response_code(403);
    exit('Forbidden');
}

// 1) ADMIN PANEL PASSWORD ---------------------------------------------
define('ADMIN_PASSWORD', 'change-me-now');

// 2) MTN MOBILE MONEY CREDENTIALS -------------------------------------
define('MOMO_ENV',              'sandbox');     // 'sandbox' or 'mtnuganda'
define('MOMO_SUBSCRIPTION_KEY', 'YOUR_SUBSCRIPTION_KEY');
define('MOMO_API_USER',         'YOUR_API_USER_UUID');
define('MOMO_API_KEY',          'YOUR_API_KEY');
define('MOMO_CALLBACK_HOST',    'tattucare.org');

// 3) CHARITY DETAILS (used for outgoing emails) -----------------------
define('CHARITY_EMAIL', 'info@tattucare.org');
define('CHARITY_NAME',  'Tattu Care');

// 4) CURRENCY ---------------------------------------------------------
define('CURRENCY', MOMO_ENV === 'sandbox' ? 'EUR' : 'UGX');

// 5) PATHS (do not change unless you moved folders) -------------------
//    File layout:
//      backend/lib/config.php     <-- this file
//      backend/data/              <-- private data (json files)
//      backend/error.log          <-- private error log
define('DATA_DIR',         __DIR__ . '/../data/');
define('CONTENT_FILE',     DATA_DIR . 'content.json');
define('DONATIONS_FILE',   DATA_DIR . 'donations.json');
define('MESSAGES_FILE',    DATA_DIR . 'messages.json');
define('SUBSCRIBERS_FILE', DATA_DIR . 'subscribers.json');

// 6) SESSION + ERROR LOGGING ------------------------------------------
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

// 7) BASIC SECURITY HEADERS -------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
