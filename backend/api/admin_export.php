<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_admin();

$type = strtolower(trim((string)($_GET['type'] ?? '')));
$allowed = ['donations', 'messages', 'subscribers', 'summary', 'activity'];
if (!in_array($type, $allowed, true)) {
    json_response(['success' => false, 'message' => 'Invalid export type.'], 400);
}

function csv_cell($value): string {
    $s = str_replace(["\r\n", "\r", "\n"], ' ', (string)$value);
    if (strpbrk($s, ",\"\n\r") !== false) {
        return '"' . str_replace('"', '""', $s) . '"';
    }
    return $s;
}

function csv_line(array $cells): string {
    return implode(',', array_map('csv_cell', $cells)) . "\r\n";
}

function send_csv(string $filename, string $body): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF" . $body;
    exit;
}

$stamp = date('Y-m-d_His');

if ($type === 'donations') {
    $rows = read_json(DONATIONS_FILE, []);
    if (!is_array($rows)) {
        $rows = [];
    }
    $out = csv_line([
        'ID', 'Created', 'Updated', 'Donor', 'Email', 'Phone', 'Amount', 'Currency',
        'Method', 'Status', 'Reference', 'MoMo Status', 'Source', 'IP',
        'Admin Note', 'Public Note', 'Status History',
    ]);
    foreach ($rows as $d) {
        $hist = '';
        if (!empty($d['statusHistory']) && is_array($d['statusHistory'])) {
            $parts = [];
            foreach ($d['statusHistory'] as $h) {
                $parts[] = ($h['at'] ?? '') . ' ' . ($h['status'] ?? '') . ' (' . ($h['by'] ?? '') . ')';
            }
            $hist = implode(' | ', $parts);
        }
        $out .= csv_line([
            $d['id'] ?? '',
            $d['createdAt'] ?? '',
            $d['updatedAt'] ?? '',
            $d['name'] ?? '',
            $d['email'] ?? '',
            $d['phone'] ?? '',
            $d['amount'] ?? '',
            $d['currency'] ?? '',
            $d['method'] ?? '',
            $d['status'] ?? '',
            $d['reference'] ?? '',
            $d['momoStatus'] ?? '',
            $d['source'] ?? 'web',
            $d['ip'] ?? '',
            $d['adminNote'] ?? '',
            $d['message'] ?? '',
            $hist,
        ]);
    }
    log_activity('export', ['type' => 'donations', 'count' => count($rows)], 'admin');
    send_csv("tattu_donations_{$stamp}.csv", $out);
}

if ($type === 'messages') {
    $rows = read_json(MESSAGES_FILE, []);
    if (!is_array($rows)) {
        $rows = [];
    }
    $out = csv_line(['ID', 'Created', 'Name', 'Email', 'Message', 'Read', 'IP']);
    foreach ($rows as $m) {
        $out .= csv_line([
            $m['id'] ?? '',
            $m['createdAt'] ?? '',
            $m['name'] ?? '',
            $m['email'] ?? '',
            $m['message'] ?? '',
            !empty($m['read']) ? 'yes' : 'no',
            $m['ip'] ?? '',
        ]);
    }
    log_activity('export', ['type' => 'messages', 'count' => count($rows)], 'admin');
    send_csv("tattu_messages_{$stamp}.csv", $out);
}

if ($type === 'subscribers') {
    $rows = read_json(SUBSCRIBERS_FILE, []);
    if (!is_array($rows)) {
        $rows = [];
    }
    $out = csv_line(['ID', 'Subscribed', 'Email', 'IP']);
    foreach ($rows as $s) {
        $out .= csv_line([
            $s['id'] ?? '',
            $s['createdAt'] ?? '',
            $s['email'] ?? '',
            $s['ip'] ?? '',
        ]);
    }
    log_activity('export', ['type' => 'subscribers', 'count' => count($rows)], 'admin');
    send_csv("tattu_subscribers_{$stamp}.csv", $out);
}

if ($type === 'activity') {
    $rows = read_json(ACTIVITY_FILE, []);
    if (!is_array($rows)) {
        $rows = [];
    }
    $out = csv_line(['ID', 'Time', 'Type', 'By', 'Data']);
    foreach ($rows as $a) {
        $out .= csv_line([
            $a['id'] ?? '',
            $a['at'] ?? '',
            $a['type'] ?? '',
            $a['by'] ?? '',
            json_encode($a['data'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
    }
    log_activity('export', ['type' => 'activity', 'count' => count($rows)], 'admin');
    send_csv("tattu_activity_{$stamp}.csv", $out);
}

// Accountability summary
$content   = read_json(CONTENT_FILE, []);
$donations = read_json(DONATIONS_FILE, []);
$messages  = read_json(MESSAGES_FILE, []);
$subs      = read_json(SUBSCRIBERS_FILE, []);
if (!is_array($donations)) $donations = [];
if (!is_array($messages))  $messages = [];
if (!is_array($subs))      $subs = [];

$stats = build_live_stats();
$org   = $content['organization'] ?? [];
$pending = array_flip(donation_pending_statuses());
$success = array_flip(donation_success_statuses());

$byMethod = [];
$byStatus = [];
foreach ($donations as $d) {
    $m = strtolower((string)($d['method'] ?? 'unknown'));
    $st = strtolower((string)($d['status'] ?? 'unknown'));
    $byMethod[$m] = ($byMethod[$m] ?? 0) + 1;
    $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
}

$out = csv_line(['Tattu Care — Accountability Report']);
$out .= csv_line(['Generated', date('c')]);
$out .= csv_line(['Organization', $org['name'] ?? CHARITY_NAME]);
$out .= csv_line(['Registration #', $org['registrationNumber'] ?? '']);
$out .= csv_line(['Email', $org['email'] ?? CHARITY_EMAIL]);
$out .= csv_line([]);
$out .= csv_line(['Metric', 'Value']);
$out .= csv_line(['Total donations (all records)', count($donations)]);
$out .= csv_line(['Confirmed donations', $stats['confirmedDonations']]);
$out .= csv_line(['Pending donations', $stats['pendingDonations']]);
$out .= csv_line(['Confirmed raised (' . $stats['raisedCurrency'] . ')', $stats['raised']]);
$out .= csv_line(['Pending pledged (' . $stats['raisedCurrency'] . ')', $stats['pendingRaised']]);
$out .= csv_line(['Campaign goal (' . $stats['raisedCurrency'] . ')', $content['donation']['goal'] ?? '']);
$out .= csv_line(['CMS raised field (synced)', $content['donation']['raised'] ?? '']);
$out .= csv_line(['Raised last synced', $content['donation']['raisedSyncedAt'] ?? 'never']);
$out .= csv_line(['Contact messages', count($messages)]);
$out .= csv_line(['Unread messages', $stats['unreadMessages']]);
$out .= csv_line(['Newsletter subscribers', count($subs)]);
$out .= csv_line([]);
$out .= csv_line(['Donations by method']);
$out .= csv_line(['Method', 'Count']);
foreach ($byMethod as $m => $n) {
    $out .= csv_line([$m, $n]);
}
$out .= csv_line([]);
$out .= csv_line(['Donations by status']);
$out .= csv_line(['Status', 'Count']);
foreach ($byStatus as $st => $n) {
    $out .= csv_line([$st, $n]);
}
$out .= csv_line([]);
$out .= csv_line(['Recent confirmed donations (last 20)']);
$out .= csv_line(['Date', 'Donor', 'Amount', 'Currency', 'Method', 'Reference']);
$confirmed = array_values(array_filter($donations, function ($d) use ($success) {
    return isset($success[strtolower((string)($d['status'] ?? ''))]);
}));
usort($confirmed, function ($a, $b) {
    return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
});
foreach (array_slice($confirmed, 0, 20) as $d) {
    $out .= csv_line([
        $d['createdAt'] ?? '',
        $d['name'] ?? 'Anonymous',
        $d['amount'] ?? '',
        $d['currency'] ?? '',
        $d['method'] ?? '',
        $d['reference'] ?? '',
    ]);
}

log_activity('export', ['type' => 'summary'], 'admin');
send_csv("tattu_accountability_{$stamp}.csv", $out);
