<?php
if (!defined('TATTU_INTERNAL')) { http_response_code(403); exit('Forbidden'); }

/**
 * SMTP mail for Tattu Care — no Composer dependency.
 * Configure via config.php + backend/data/smtp.local.php (credentials).
 */

function smtp_config(): array {
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $cfg = [
        'enabled'      => defined('SMTP_ENABLED') ? (bool) SMTP_ENABLED : false,
        'host'         => defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com',
        'port'         => defined('SMTP_PORT') ? (int) SMTP_PORT : 587,
        'encryption'   => defined('SMTP_ENCRYPTION') ? strtolower((string) SMTP_ENCRYPTION) : 'tls',
        'username'     => defined('SMTP_USERNAME') ? (string) SMTP_USERNAME : '',
        'password'     => defined('SMTP_PASSWORD') ? (string) SMTP_PASSWORD : '',
        'from_email'   => defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : CHARITY_EMAIL,
        'from_name'    => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : CHARITY_NAME,
        'notify_email' => defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : CHARITY_EMAIL,
        'auto_reply'   => !defined('SMTP_AUTO_REPLY') || SMTP_AUTO_REPLY,
        'timeout'      => 30,
    ];

    $local = DATA_DIR . 'smtp.local.php';
    if (is_file($local)) {
        $override = include $local;
        if (is_array($override)) {
            $cfg = array_merge($cfg, $override);
        }
    }

    $cfg['enabled']    = !empty($cfg['enabled']);
    $cfg['port']       = (int) ($cfg['port'] ?? 587);
    $cfg['encryption'] = strtolower((string) ($cfg['encryption'] ?? 'tls'));
    $cfg['auto_reply'] = !empty($cfg['auto_reply']);

    return $cfg;
}

function smtp_is_configured(): bool {
    $c = smtp_config();
    if (!$c['enabled']) {
        return false;
    }
    if ($c['host'] === '' || $c['from_email'] === '') {
        return false;
    }
    if ($c['username'] !== '' && $c['password'] === '') {
        return false;
    }
    return true;
}

function encode_mail_header(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/[^\x20-\x7E]/', $value)) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
    return $value;
}

function format_mail_address(string $email, string $name = ''): string {
    $email = trim($email);
    $name  = trim($name);
    if ($name === '') {
        return "<{$email}>";
    }
    return encode_mail_header($name) . " <{$email}>";
}

final class SmtpMailer {
    private array $config;
    /** @var resource|null */
    private $socket = null;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function send(string $to, string $subject, string $body, array $opts = []): bool {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('SmtpMailer: invalid recipient');
            return false;
        }

        try {
            $this->connect();
            $this->expect('220');
            $this->ehlo();

            if ($this->config['encryption'] === 'tls') {
                $this->cmd('STARTTLS', '220');
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS failed');
                }
                $this->ehlo();
            }

            if (!empty($this->config['username'])) {
                $this->authLogin($this->config['username'], $this->config['password']);
            }

            // Gmail requires envelope sender to match the authenticated account (or a verified alias).
            $envelopeFrom = $this->config['username'] ?: $this->config['from_email'];
            $this->cmd("MAIL FROM:<{$envelopeFrom}>", '250');
            $this->cmd("RCPT TO:<{$to}>", '250');

            $this->cmd('DATA', '354');
            $this->writeMessage($to, $subject, $body, $opts);
            $this->expect('250');
            $this->cmd('QUIT', '221');
            $this->close();
            return true;
        } catch (Throwable $e) {
            error_log('SmtpMailer: ' . $e->getMessage());
            $this->close();
            return false;
        }
    }

    private function connect(): void {
        $host = $this->config['host'];
        $port = (int) $this->config['port'];
        $enc  = $this->config['encryption'];
        $timeout = (int) ($this->config['timeout'] ?? 30);

        $target = ($enc === 'ssl')
            ? "ssl://{$host}:{$port}"
            : "tcp://{$host}:{$port}";

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$this->socket) {
            throw new RuntimeException("Connect failed ({$errno}): {$errstr}");
        }
        stream_set_timeout($this->socket, $timeout);
    }

    private function ehlo(): void {
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $host = preg_replace('/[^a-zA-Z0-9.\-]/', '', $host) ?: 'localhost';
        $this->cmd("EHLO {$host}", '250');
    }

    private function authLogin(string $user, string $pass): void {
        $this->cmd('AUTH LOGIN', '334');
        $this->cmd(base64_encode($user), '334');
        $this->cmd(base64_encode($pass), '235');
    }

    private function writeMessage(string $to, string $subject, string $body, array $opts): void {
        $fromEmail = $this->config['from_email'];
        $fromName  = $this->config['from_name'];
        $replyTo   = trim((string) ($opts['reply_to'] ?? ''));

        $headers = [
            'Date: ' . date('r'),
            'From: ' . format_mail_address($fromEmail, $fromName),
            'To: ' . format_mail_address($to),
            'Subject: ' . encode_mail_header($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: TattuCare-SMTP',
        ];

        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . format_mail_address($replyTo);
        }

        $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $body);
        $message = str_replace("\n", "\r\n", $message);
        $message = preg_replace('/^\./m', '..', $message);

        fwrite($this->socket, $message . "\r\n.\r\n");
    }

    private function cmd(string $command, string $expectCode): void {
        fwrite($this->socket, $command . "\r\n");
        $this->expect($expectCode);
    }

    private function expect(string $code): void {
        $response = $this->readResponse();
        if (strpos($response, $code) !== 0) {
            throw new RuntimeException("Expected {$code}, got: {$response}");
        }
    }

    private function readResponse(): string {
        if (!$this->socket) {
            throw new RuntimeException('No socket');
        }
        $lines = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $lines .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($lines);
    }

    private function close(): void {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
    }
}

/**
 * Send an email via SMTP (if configured) or PHP mail() fallback.
 */
function send_mail(string $to, string $subject, string $body, array $opts = []): bool {
    $cfg = smtp_config();

    if (smtp_is_configured()) {
        $mailer = new SmtpMailer($cfg);
        return $mailer->send($to, $subject, $body, $opts);
    }

    $fromEmail = $cfg['from_email'];
    $fromName  = $cfg['from_name'];
    $replyTo   = trim((string) ($opts['reply_to'] ?? ''));

    $headers = 'From: ' . $fromName . ' <' . $fromEmail . ">\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= 'Reply-To: ' . $replyTo . "\r\n";
    }

    $ok = @mail($to, $subject, $body, $headers);
    if (!$ok) {
        error_log("send_mail fallback failed for {$to}");
    }
    return $ok;
}
