<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/momo.php';
require_once __DIR__ . '/../lib/fx.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!rate_limit('donate_' . client_ip(), 10, 60)) {
    json_response(['success' => false, 'message' => 'Too many requests, please slow down.'], 429);
}

$data = read_input_json();

if (is_honeypot_triggered($data)) {
    json_response(['success' => true, 'message' => 'Thank you! We will get back to you soon.']);
}

$method = strtolower(trim((string)($data['method'] ?? 'momo')));
if (!in_array($method, ['momo', 'airtel', 'bank', 'crypto'], true)) {
    json_response(['success' => false, 'message' => 'Unknown payment method.'], 400);
}

$amount         = isset($data['amount']) ? (float) $data['amount'] : 0;
$displayCurrency= strtoupper(trim((string)($data['currency'] ?? 'UGX')));
$phone          = isset($data['phone'])  ? preg_replace('/\s+/', '', (string) $data['phone']) : '';
$name           = trim((string)($data['name']      ?? ''));
$email          = trim((string)($data['email']     ?? ''));
$reference      = trim((string)($data['reference'] ?? ''));

if ($method === 'bank' && !valid_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email so we can confirm your transfer.'], 400);
}
if ($method === 'crypto' && !valid_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter your email so we can send a receipt after confirming your crypto transfer.'], 400);
}

$isCrypto = $method === 'crypto' || fx_is_crypto($displayCurrency);
if (!$isCrypto && $amount < 1) {
    json_response(['success' => false, 'message' => 'Please enter a valid donation amount.'], 400);
}
if ($isCrypto && $amount <= 0) {
    json_response(['success' => false, 'message' => 'Please enter a valid crypto amount.'], 400);
}

// Server-side currency conversion: donor enters X in their currency,
// we charge MoMo in CURRENCY (UGX in production, EUR in sandbox).
if (!valid_donation_currency($displayCurrency)) {
    json_response(['success' => false, 'message' => 'Currency not supported. Please pick another from the list.'], 400);
}
if ($email !== '' && !valid_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
}
if (in_array($method, ['momo', 'airtel'], true) && !valid_phone($phone)) {
    json_response(['success' => false, 'message' => 'Please enter a valid Mobile Money number starting with 256 (e.g. 256770000000).'], 400);
}

try {
    $chargeAmount = fx_convert($amount, $displayCurrency, CURRENCY);
} catch (Throwable $e) {
    error_log('donate.php FX error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Currency not supported. Please choose another.'], 400);
}
// Round to whole units for UGX/JPY/etc.; 2dp for EUR/USD
$chargeAmount = in_array(CURRENCY, ['UGX','JPY','RWF','TZS','KES','BIF','VND','XAF','XOF'], true)
    ? round($chargeAmount)
    : round($chargeAmount, 2);

if (!$isCrypto && $chargeAmount < 1) {
    json_response(['success' => false, 'message' => 'Amount too small after conversion.'], 400);
}

$record = [
    'id'              => gen_id(),
    'method'          => $method,
    'amount'          => $amount,           // donor-entered amount
    'currency'        => $displayCurrency,  // donor's currency
    'chargeAmount'    => $chargeAmount,     // amount actually charged
    'chargeCurrency'  => CURRENCY,          // MoMo charge currency
    'phone'           => $phone,
    'name'            => $name,
    'email'           => $email,
    'reference'       => $reference ?: null,
    'createdAt'       => date('c'),
    'ip'              => client_ip(),
    'status'          => 'pending',
    'message'         => null,
    'statusHistory'   => [],
];
donation_add_history($record, 'pending', 'donor', 'Donation initiated');

/* ----- Cryptocurrency: record pledge + notify team ----- */
if ($method === 'crypto') {
    $cryptoAsset = strtoupper(trim((string)($data['cryptoAsset'] ?? $displayCurrency)));
    if (!fx_is_crypto($cryptoAsset)) {
        json_response(['success' => false, 'message' => 'Please select a supported cryptocurrency.'], 400);
    }
    $record['currency'] = $cryptoAsset;
    $record['amount']   = $amount;
    $record['status']   = 'crypto_pledged';
    $record['message']  = 'Crypto donation pledged. Verify on-chain transaction hash and confirm receipt.';
    $record['adminNote'] = $reference ? ('Tx/ref: ' . $reference) : null;
    donation_add_history($record, 'crypto_pledged', 'system', $record['message']);
    append_json_record(DONATIONS_FILE, $record);
    notify_donation($record);
    log_activity('donation', ['id' => $record['id'], 'method' => 'crypto', 'amount' => $amount, 'currency' => $cryptoAsset, 'status' => 'crypto_pledged'], 'donor');
    json_response([
        'success' => true,
        'pending' => true,
        'status'  => 'crypto_pledged',
        'message' => 'Thank you! Send crypto to the wallet shown, then submit this form. We will email confirmation once verified on-chain.',
    ]);
}

/* ----- Bank transfer: log + return immediately ----- */
if ($method === 'bank') {
    $record['status']  = 'bank_pledged';
    $record['message'] = 'Donor reports a bank transfer. Verify against bank statement.';
    donation_add_history($record, 'bank_pledged', 'system', $record['message']);
    append_json_record(DONATIONS_FILE, $record);
    notify_donation($record);
    log_activity('donation', ['id' => $record['id'], 'method' => 'bank', 'amount' => $record['amount'], 'currency' => $record['currency'], 'status' => 'bank_pledged'], 'donor');
    json_response([
        'success' => true,
        'pending' => true,
        'status'  => 'bank_pledged',
        'message' => 'Thank you! We will verify your transfer and email confirmation shortly.',
    ]);
}

/* ----- Airtel Money: no integrated API yet — record as manual pledge ----- */
if ($method === 'airtel') {
    $record['status']  = 'airtel_pledged';
    $record['message'] = 'Airtel Money pledge. Awaiting manual confirmation.';
    donation_add_history($record, 'airtel_pledged', 'system', $record['message']);
    append_json_record(DONATIONS_FILE, $record);
    notify_donation($record);
    log_activity('donation', ['id' => $record['id'], 'method' => 'airtel', 'amount' => $record['amount'], 'currency' => $record['currency'], 'status' => 'airtel_pledged'], 'donor');
    json_response([
        'success' => true,
        'pending' => true,
        'status'  => 'airtel_pledged',
        'message' => 'Thank you! We will contact you shortly with Airtel payment instructions.',
    ]);
}

/* ----- MTN Mobile Money: real API flow ----- */
$momo = new MoMoClient();

if ($momo->isConfigured()) {
    $res = $momo->requestToPay($phone, $chargeAmount, CURRENCY,
        "Donation to " . CHARITY_NAME . " from " . ($name ?: 'Anonymous'));

    if ($res['success']) {
        $record['status']    = 'awaiting_approval';
        $record['reference'] = $res['referenceId'];
        donation_add_history($record, 'awaiting_approval', 'momo', 'Payment prompt sent to donor phone');
        append_json_record(DONATIONS_FILE, $record);
        notify_donation($record);
        log_activity('donation', ['id' => $record['id'], 'method' => 'momo', 'amount' => $record['amount'], 'currency' => $record['currency'], 'status' => 'awaiting_approval', 'reference' => $record['reference']], 'donor');
        json_response([
            'success'     => true,
            'pending'     => true,
            'status'      => 'awaiting_approval',
            'message'     => 'Payment request sent. Please check your phone and approve the Mobile Money prompt.',
            'referenceId' => $res['referenceId'],
        ]);
    } else {
        $record['status']  = 'failed';
        $record['message'] = $res['message'];
        donation_add_history($record, 'failed', 'momo', $res['message']);
        append_json_record(DONATIONS_FILE, $record);
        notify_donation($record);
        log_activity('donation', ['id' => $record['id'], 'method' => 'momo', 'status' => 'failed'], 'system');
        json_response(['success' => false, 'message' => $res['message']], 502);
    }
} else {
    $record['status']  = 'manual_pending';
    $record['message'] = 'MTN MoMo not configured. Donor must be contacted manually.';
    donation_add_history($record, 'manual_pending', 'system', $record['message']);
    append_json_record(DONATIONS_FILE, $record);
    notify_donation($record);
    log_activity('donation', ['id' => $record['id'], 'method' => 'momo', 'status' => 'manual_pending'], 'system');
    json_response([
        'success' => true,
        'pending' => true,
        'status'  => 'manual_pending',
        'message' => 'Thank you! Your pledge has been recorded. Our team will contact you shortly to complete the donation.',
    ]);
}
