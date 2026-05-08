<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/momo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!rate_limit('donate_' . client_ip(), 10, 60)) {
    json_response(['success' => false, 'message' => 'Too many requests, please slow down.'], 429);
}

$data = read_input_json();

$amount = isset($data['amount']) ? (float) $data['amount'] : 0;
$phone  = isset($data['phone'])  ? preg_replace('/\s+/', '', (string) $data['phone']) : '';
$name   = trim((string)($data['name']  ?? ''));
$email  = trim((string)($data['email'] ?? ''));

if ($amount < 1) {
    json_response(['success' => false, 'message' => 'Please enter a valid donation amount.'], 400);
}
if (!valid_phone($phone)) {
    json_response(['success' => false, 'message' => 'Please enter a valid Mobile Money number starting with 256 (e.g. 256770000000).'], 400);
}
if ($email !== '' && !valid_email($email)) {
    json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
}

$record = [
    'id'        => gen_id(),
    'amount'    => $amount,
    'currency'  => CURRENCY,
    'phone'     => $phone,
    'name'      => $name,
    'email'     => $email,
    'createdAt' => date('c'),
    'ip'        => client_ip(),
    'status'    => 'pending',
    'reference' => null,
    'message'   => null,
];

$momo = new MoMoClient();

if ($momo->isConfigured()) {
    $res = $momo->requestToPay($phone, $amount, CURRENCY,
        "Donation to " . CHARITY_NAME . " from " . ($name ?: 'Anonymous'));

    if ($res['success']) {
        $record['status']    = 'awaiting_approval';
        $record['reference'] = $res['referenceId'];
        append_json_record(DONATIONS_FILE, $record);
        json_response([
            'success'     => true,
            'message'     => 'Payment request sent. Please check your phone and approve the Mobile Money prompt.',
            'referenceId' => $res['referenceId'],
        ]);
    } else {
        $record['status']  = 'failed';
        $record['message'] = $res['message'];
        append_json_record(DONATIONS_FILE, $record);
        json_response(['success' => false, 'message' => $res['message']], 502);
    }
} else {
    $record['status']  = 'manual_pending';
    $record['message'] = 'MTN MoMo not configured. Donor must be contacted manually.';
    append_json_record(DONATIONS_FILE, $record);
    json_response([
        'success' => true,
        'message' => 'Thank you! Your pledge has been recorded. Our team will contact you shortly to complete the donation.',
    ]);
}
