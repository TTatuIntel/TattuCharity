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

if ($action === 'sync_raised') {
    $raised = sync_content_raised('admin');
    if ($raised === null) {
        json_response(['success' => false, 'message' => 'Could not sync raised total.'], 500);
    }
    $stats = compute_raised_from_donations();
    json_response([
        'success' => true,
        'raised'  => $raised,
        'stats'   => $stats,
        'message' => 'Raised total synced from confirmed donations.',
    ]);
}

if ($action === 'poll_momo') {
    $id = trim((string)($data['id'] ?? ''));
    if ($id === '') {
        json_response(['success' => false, 'message' => 'Donation ID required.'], 400);
    }
    $idx = find_donation_index($list, $id);
    if ($idx < 0) {
        json_response(['success' => false, 'message' => 'Donation not found.'], 404);
    }
    $poll = poll_donation_momo_status($list[$idx]);
    if (!$poll['success']) {
        json_response($poll, 502);
    }
    if (!write_json(DONATIONS_FILE, $list)) {
        json_response(['success' => false, 'message' => 'Could not save MoMo poll result.'], 500);
    }
    $syncedRaised = null;
    if (!empty($poll['changed']) && in_array($list[$idx]['status'], donation_success_statuses(), true)) {
        $syncedRaised = sync_content_raised('momo_poll');
    }
    log_activity('momo_poll', [
        'id'         => $id,
        'status'     => $list[$idx]['status'],
        'momoStatus' => $poll['momoStatus'] ?? '',
        'changed'    => !empty($poll['changed']),
    ], 'admin');
    json_response([
        'success'          => true,
        'donation'         => $list[$idx],
        'poll'             => $poll,
        'syncedRaised'     => $syncedRaised,
        'pendingDonations' => count_pending_donations($list),
    ]);
}

if ($action === 'poll_all_momo') {
    $polled = 0;
    $changed = 0;
    $confirmed = 0;
    foreach ($list as $i => $rec) {
        $st = strtolower((string)($rec['status'] ?? ''));
        if ($st !== 'awaiting_approval' || strtolower((string)($rec['method'] ?? '')) !== 'momo') {
            continue;
        }
        $poll = poll_donation_momo_status($list[$i]);
        if (!$poll['success']) {
            continue;
        }
        $polled++;
        if (!empty($poll['changed'])) {
            $changed++;
            if (in_array($list[$i]['status'], donation_success_statuses(), true)) {
                $confirmed++;
            }
        }
    }
    if ($polled > 0 && !write_json(DONATIONS_FILE, $list)) {
        json_response(['success' => false, 'message' => 'Could not save poll results.'], 500);
    }
    $syncedRaised = $confirmed > 0 ? sync_content_raised('momo_poll') : null;
    log_activity('momo_poll_all', ['polled' => $polled, 'changed' => $changed, 'confirmed' => $confirmed], 'admin');
    json_response([
        'success'          => true,
        'polled'           => $polled,
        'changed'          => $changed,
        'confirmed'        => $confirmed,
        'syncedRaised'     => $syncedRaised,
        'pendingDonations' => count_pending_donations($list),
        'message'          => $polled === 0
            ? 'No MoMo donations awaiting approval.'
            : "Polled {$polled} donation(s); {$changed} status update(s).",
    ]);
}

if ($action === 'create') {
    $fields = sanitize_donation_fields($data, true);
    $status = $fields['status'] ?? 'success';
    $record = [
        'id'            => gen_id(),
        'method'        => $fields['method'] ?? 'manual',
        'amount'        => $fields['amount'],
        'currency'      => $fields['currency'],
        'phone'         => $fields['phone'] ?? '',
        'name'          => $fields['name'] ?? '',
        'email'         => $fields['email'] ?? '',
        'reference'     => $fields['reference'] ?? null,
        'createdAt'     => date('c'),
        'ip'            => 'admin',
        'status'        => $status,
        'message'       => $fields['message'] ?? 'Recorded manually by admin.',
        'adminNote'     => $fields['adminNote'] ?? null,
        'source'        => 'manual',
        'statusHistory' => [],
        'updatedAt'     => date('c'),
        'updatedBy'     => 'admin',
    ];
    donation_add_history($record, $status, 'admin', 'Manual donation recorded');
    $list[] = $record;
    if (!write_json(DONATIONS_FILE, $list)) {
        json_response(['success' => false, 'message' => 'Could not save donation.'], 500);
    }
    $syncedRaised = in_array($status, donation_success_statuses(), true) ? sync_content_raised('admin') : null;
    log_activity('donation_admin', ['id' => $record['id'], 'action' => 'create', 'status' => $status], 'admin');
    if (!empty($data['notifyDonor']) && !empty($record['email'])) {
        send_donation_auto_reply($record);
    }
    json_response([
        'success'          => true,
        'donation'         => $record,
        'syncedRaised'     => $syncedRaised,
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

    $newStatus = strtolower((string)($list[$idx]['status'] ?? ''));
    $prevLower = strtolower($prevStatus);
    if ($newStatus !== $prevLower) {
        $note = trim((string)($list[$idx]['adminNote'] ?? ''));
        donation_add_history($list[$idx], $newStatus, 'admin', $note ?: 'Status updated by admin');
    }

    if (!write_json(DONATIONS_FILE, $list)) {
        json_response(['success' => false, 'message' => 'Could not update donation.'], 500);
    }

    $updated = $list[$idx];
    $syncedRaised = null;
    $wasSuccess = in_array($prevLower, donation_success_statuses(), true);
    $isSuccess  = in_array($newStatus, donation_success_statuses(), true);
    if ($isSuccess !== $wasSuccess || ($isSuccess && $newStatus !== $prevLower)) {
        $syncedRaised = sync_content_raised('admin');
    }
    log_activity('donation_admin', ['id' => $id, 'action' => $action, 'from' => $prevStatus, 'to' => $newStatus], 'admin');

    $shouldNotify = !empty($data['notifyDonor'])
        && !empty($updated['email'])
        && $isSuccess
        && !$wasSuccess;
    if ($shouldNotify) {
        send_donation_auto_reply($updated);
    }

    json_response([
        'success'          => true,
        'donation'         => $updated,
        'syncedRaised'     => $syncedRaised,
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
