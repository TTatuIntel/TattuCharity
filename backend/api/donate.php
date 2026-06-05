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

$method = strtolower(trim((string)($data['method'] ?? 'momo')));
if (!in_array($method, ['momo', 'airtel', 'bank'], true)) {
    json_response(['success' => false, 'message' => 'Unknown payment method.'], 400);
}

$amount         = isset($data['amount']) ? (float) $data['amount'] : 0;
$displayCurrency= strtoupper(trim((string)($data['currency'] ?? 'USD')));
$phone          = isset($data['phone'])  ? preg_replace('/\s+/', '', (string) $data['phone']) : '';
$name           = trim((string)($data['name']      ?? ''));
$email          = trim((string)($data['email']     ?? ''));
$reference      = trim((string)($data['reference'] ?? ''));

if ($amount < 1) {
    json_response(['success' => false, 'message' => 'Please enter a valid donation amount.'], 400);
}
if (!preg_match('/^[A-Z]{3}$/', $displayCurrency)) {
    json_response(['success' => false, 'message' => 'Invalid currency code.'], 400);
}
if ($email !== '' && !valid_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
}
if (in_array($method, ['momo', 'airtel'], true) && !valid_phone($phone)) {
    json_response(['success' => false, 'message' => 'Please enter a valid Mobile Money number starting with 256 (e.g. 256770000000).'], 400);
}
if ($method === 'bank' && !valid_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email so we can confirm your transfer.'], 400);
}

// Server-side currency conversion: donor enters X in their currency,
// we charge MoMo in CURRENCY (UGX in production, EUR in sandbox).
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

if ($chargeAmount < 1) {
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
];

/* ----- Bank transfer: log + return immediately ----- */
if ($method === 'bank') {
    $record['status']  = 'bank_pledged';
    $record['message'] = 'Donor reports a bank transfer. Verify against bank statement.';
    append_json_record(DONATIONS_FILE, $record);
    notify_donation($record);
    json_response([
        'success' => true,
        'message' => 'Thank you! We will verify your transfer and email confirmation shortly.',
    ]);
}

/* ----- Airtel Money: no integrated API yet — record as manual pledge ----- */
if ($method === 'airtel') {
    $record['status']  = 'airtel_pledged';
    $record['message'] = 'Airtel Money pledge. Awaiting manual confirmation.';
    append_json_record(DONATIONS_FILE, $record);
    notify_donation($record);
    json_response([
        'success' => true,
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
        append_json_record(DONATIONS_FILE, $record);
        notify_donation($record);
        json_response([
            'success'     => true,
            'message'     => 'Payment request sent. Please check your phone and approve the Mobile Money prompt.',
            'referenceId' => $res['referenceId'],
        ]);
    } else {
        $record['status']  = 'failed';
        $record['message'] = $res['message'];
        append_json_record(DONATIONS_FILE, $record);
        notify_donation($record);
        json_response(['success' => false, 'message' => $res['message']], 502);
    }
} else {
    $record['status']  = 'manual_pending';
    $record['message'] = 'MTN MoMo not configured. Donor must be contacted manually.';
    append_json_record(DONATIONS_FILE, $record);
    notify_donation($record);
    json_response([
        'success' => true,
        'message' => 'Thank you! Your pledge has been recorded. Our team will contact you shortly to complete the donation.',
    ]);
}
