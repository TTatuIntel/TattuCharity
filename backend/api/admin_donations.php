<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}
require_csrf();

$data   = read_input_json();
$action = (string)($data['action'] ?? '');
$list   = read_json(DONATIONS_FILE, []);
if (!is_array($list)) {
    $list = [];
}

if ($action === 'create') {
    $fields = sanitize_donation_fields($data, true);
    $record = [
        'id'         => gen_id(),
        'method'     => $fields['method'] ?? 'manual',
        'amount'     => $fields['amount'],
        'currency'   => $fields['currency'],
        'phone'      => $fields['phone'] ?? '',
        'name'       => $fields['name'] ?? '',
        'email'      => $fields['email'] ?? '',
        'reference'  => $fields['reference'] ?? null,
        'createdAt'  => date('c'),
        'ip'         => 'admin',
        'status'     => $fields['status'] ?? 'success',
        'message'    => $fields['message'] ?? 'Recorded manually by admin.',
        'adminNote'  => $fields['adminNote'] ?? null,
        'source'     => 'manual',
        'updatedAt'  => date('c'),
        'updatedBy'  => 'admin',
    ];
    $list[] = $record;
    if (!write_json(DONATIONS_FILE, $list)) {
        json_response(['success' => false, 'message' => 'Could not save donation.'], 500);
    }
    if (!empty($data['notifyDonor']) && !empty($record['email'])) {
        send_donation_auto_reply($record);
    }
    json_response([
        'success'          => true,
        'donation'         => $record,
        'pendingDonations' => count_pending_donations($list),
    ]);
}

if ($action === 'update' || $action === 'confirm') {
    $id = trim((string)($data['id'] ?? ''));
    if ($id === '') {
        json_response(['success' => false, 'message' => 'Donation ID required.'], 400);
    }
    $idx = find_donation_index($list, $id);
    if ($idx < 0) {
        json_response(['success' => false, 'message' => 'Donation not found.'], 404);
    }

    $prevStatus = (string)($list[$idx]['status'] ?? '');
    if ($action === 'confirm') {
        $fields = ['status' => 'success'];
        if (!empty($data['adminNote'])) {
            $fields['adminNote'] = trim((string)$data['adminNote']);
        } elseif (trim((string)($list[$idx]['adminNote'] ?? '')) === '') {
            $fields['adminNote'] = 'Confirmed manually by admin.';
        }
    } else {
        $fields = sanitize_donation_fields($data, false);
        if (empty($fields)) {
            json_response(['success' => false, 'message' => 'No fields to update.'], 400);
        }
    }

    foreach ($fields as $k => $v) {
        $list[$idx][$k] = $v;
    }
    $list[$idx]['updatedAt'] = date('c');
    $list[$idx]['updatedBy'] = 'admin';

    if (!write_json(DONATIONS_FILE, $list)) {
        json_response(['success' => false, 'message' => 'Could not update donation.'], 500);
    }

    $updated = $list[$idx];
    $newStatus = (string)($updated['status'] ?? '');
    $shouldNotify = !empty($data['notifyDonor'])
        && !empty($updated['email'])
        && $newStatus === 'success'
        && $prevStatus !== 'success'
        && $prevStatus !== 'completed';
    if ($shouldNotify) {
        send_donation_auto_reply($updated);
    }

    json_response([
        'success'          => true,
        'donation'         => $updated,
        'pendingDonations' => count_pending_donations($list),
    ]);
}

if ($action === 'delete') {
    $id = trim((string)($data['id'] ?? ''));
    if ($id === '') {
        json_response(['success' => false, 'message' => 'Donation ID required.'], 400);
    }
    $idx = find_donation_index($list, $id);
    if ($idx < 0) {
        json_response(['success' => false, 'message' => 'Donation not found.'], 404);
    }
    array_splice($list, $idx, 1);
    if (!write_json(DONATIONS_FILE, $list)) {
        json_response(['success' => false, 'message' => 'Could not delete donation.'], 500);
    }
    json_response([
        'success'          => true,
        'pendingDonations' => count_pending_donations($list),
    ]);
}

json_response(['success' => false, 'message' => 'Unknown action.'], 400);
