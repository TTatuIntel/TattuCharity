<?php
if (!defined('TATTU_INTERNAL')) { http_response_code(403); exit('Forbidden'); }

/**
 * SMS sending support (Twilio minimal implementation).
 * Configure by creating backend/data/sms.local.php returning an array:
 * [ 'enabled'=>true, 'provider'=>'twilio', 'account_sid'=>'...', 'auth_token'=>'...', 'from'=>'+1...', 'admin_number'=>'+256...']
 */

function sms_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $cfg = [
        'enabled'      => false,
        'provider'     => 'twilio',
        'account_sid'  => '',
        'auth_token'   => '',
        'from'         => '',
        'admin_number' => '',
        'timeout'      => 10,
    ];

    $local = DATA_DIR . 'sms.local.php';
    if (is_file($local)) {
        $override = include $local;
        if (is_array($override)) {
            $cfg = array_merge($cfg, $override);
        }
    }

    $cfg['enabled'] = !empty($cfg['enabled']);
    return $cfg;
}

function sms_is_configured(): bool {
    $c = sms_config();
    if (!$c['enabled']) return false;
    if ($c['provider'] === 'twilio') {
        return $c['account_sid'] !== '' && $c['auth_token'] !== '' && $c['from'] !== '' && $c['admin_number'] !== '';
    }
    return false;
}

function send_sms(string $to, string $body): bool {
    $cfg = sms_config();
    if (!$cfg['enabled'] || $cfg['provider'] !== 'twilio') return false;
    if ($to === '' || $body === '') return false;

    $sid = $cfg['account_sid'];
    $token = $cfg['auth_token'];
    $from = $cfg['from'];

    $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
    $data = http_build_query(['From' => $from, 'To' => $to, 'Body' => $body]);

    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_TIMEOUT => (int)($cfg['timeout'] ?? 10),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || $code < 200 || $code >= 300) {
        error_log('send_sms failed: ' . ($err ?: substr($res ?: '', 0, 200)));
        return false;
    }
    return true;
}

function send_admin_sms(string $body): bool {
    $cfg = sms_config();
    if (!$cfg['enabled'] || empty($cfg['admin_number'])) return false;
    return send_sms($cfg['admin_number'], $body);
}
