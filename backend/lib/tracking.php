<?php
/**
 * Activity logging, donation totals, and anti-spam helpers.
 */
if (!defined('TATTU_INTERNAL')) { http_response_code(403); exit('Forbidden'); }

function is_honeypot_triggered(array $data): bool {
    return trim((string)($data['website'] ?? $data['_hp'] ?? '')) !== '';
}

function log_activity(string $type, array $data = [], string $by = 'system'): void {
    if (!defined('ACTIVITY_FILE')) {
        return;
    }
    $list = read_json(ACTIVITY_FILE, []);
    if (!is_array($list)) {
        $list = [];
    }
    array_unshift($list, [
        'id'   => gen_id(),
        'type' => $type,
        'at'   => date('c'),
        'by'   => $by,
        'data' => $data,
    ]);
    if (count($list) > 500) {
        $list = array_slice($list, 0, 500);
    }
    write_json(ACTIVITY_FILE, $list);
}

function donation_add_history(array &$record, string $status, string $by, ?string $note = null): void {
    if (!isset($record['statusHistory']) || !is_array($record['statusHistory'])) {
        $record['statusHistory'] = [];
    }
    $record['statusHistory'][] = [
        'status' => $status,
        'at'     => date('c'),
        'by'     => $by,
        'note'   => $note,
    ];
}

function donation_success_statuses(): array {
    return ['success', 'completed'];
}

function compute_raised_from_donations(?string $baseCurrency = null): array {
    $donations = read_json(DONATIONS_FILE, []);
    if (!is_array($donations)) {
        $donations = [];
    }
    $content = read_json(CONTENT_FILE, []);
    $base = strtoupper($baseCurrency ?: (string)($content['donation']['currency'] ?? 'UGX'));
    $skip = ['failed', 'cancelled', 'rejected'];
    $success = array_flip(donation_success_statuses());
    $total = 0.0;
    $count = 0;

    foreach ($donations as $d) {
        $st = strtolower((string)($d['status'] ?? ''));
        if (isset($skip[$st]) || !isset($success[$st])) {
            continue;
        }
        $cur = strtoupper((string)($d['currency'] ?? $base));
        if ($cur !== $base) {
            continue;
        }
        $total += (float)($d['amount'] ?? 0);
        $count++;
    }

    return [
        'raised'           => round($total, 2),
        'currency'         => $base,
        'confirmedCount'   => $count,
    ];
}

function sync_content_raised(string $by = 'admin'): ?float {
    $stats = compute_raised_from_donations();
    $content = read_json(CONTENT_FILE, []);
    if (!isset($content['donation']) || !is_array($content['donation'])) {
        $content['donation'] = [];
    }
    $content['donation']['raised'] = $stats['raised'];
    $content['donation']['raisedSyncedAt'] = date('c');
    if (!write_json(CONTENT_FILE, $content)) {
        return null;
    }
    log_activity('raised_sync', [
        'raised'   => $stats['raised'],
        'currency' => $stats['currency'],
        'count'    => $stats['confirmedCount'],
    ], $by);
    return $stats['raised'];
}

function build_live_stats(): array {
    $donations   = read_json(DONATIONS_FILE, []);
    $messages    = read_json(MESSAGES_FILE, []);
    $subscribers = read_json(SUBSCRIBERS_FILE, []);
    if (!is_array($donations))   $donations = [];
    if (!is_array($messages))    $messages = [];
    if (!is_array($subscribers)) $subscribers = [];

    $raised = compute_raised_from_donations();
    $pendingTotal = 0.0;
    $pending = array_flip(donation_pending_statuses());
    $base = $raised['currency'];
    foreach ($donations as $d) {
        $st = strtolower((string)($d['status'] ?? ''));
        if (!isset($pending[$st])) {
            continue;
        }
        if (strtoupper((string)($d['currency'] ?? $base)) === $base) {
            $pendingTotal += (float)($d['amount'] ?? 0);
        }
    }

    return [
        'donationsTotal'     => count($donations),
        'messagesTotal'      => count($messages),
        'subscribersTotal'   => count(subscribers),
        'unreadMessages'     => count_unread_messages($messages),
        'pendingDonations'   => count_pending_donations($donations),
        'raised'             => $raised['raised'],
        'raisedCurrency'     => $raised['currency'],
        'confirmedDonations' => $raised['confirmedCount'],
        'pendingRaised'      => round($pendingTotal, 2),
        'generatedAt'        => date('c'),
    ];
}

function map_momo_api_status(string $apiStatus): string {
    $s = strtoupper(trim($apiStatus));
    if ($s === 'SUCCESSFUL') {
        return 'success';
    }
    if (in_array($s, ['FAILED', 'REJECTED', 'TIMEOUT', 'EXPIRED'], true)) {
        return 'failed';
    }
    return 'awaiting_approval';
}

function poll_donation_momo_status(array &$record): array {
    require_once __DIR__ . '/momo.php';
    $ref = trim((string)($record['reference'] ?? ''));
    if ($ref === '' || strtolower((string)($record['method'] ?? '')) !== 'momo') {
        return ['success' => false, 'message' => 'Not an MTN MoMo donation with a reference ID.'];
    }
    $momo = new MoMoClient();
    if (!$momo->isConfigured()) {
        return ['success' => false, 'message' => 'MoMo API is not configured.'];
    }
    $res = $momo->getTransactionStatus($ref);
    if (!$res['success']) {
        return $res;
    }
    $apiStatus = (string)($res['status'] ?? '');
    $newStatus = map_momo_api_status($apiStatus);
    $prev = (string)($record['status'] ?? '');
    if ($newStatus !== $prev) {
        donation_add_history($record, $newStatus, 'momo_poll', 'MoMo API: ' . $apiStatus);
        $record['status'] = $newStatus;
        $record['momoStatus'] = $apiStatus;
        $record['updatedAt'] = date('c');
        $record['updatedBy'] = 'momo_poll';
        if ($newStatus === 'success') {
            $record['adminNote'] = trim((string)($record['adminNote'] ?? ''))
                ?: 'Confirmed automatically via MoMo status poll.';
        }
    } else {
        $record['momoStatus'] = $apiStatus;
    }
    return [
        'success'    => true,
        'momoStatus' => $apiStatus,
        'status'     => $record['status'],
        'changed'    => $newStatus !== $prev,
    ];
}
