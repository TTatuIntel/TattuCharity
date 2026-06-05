<?php
/**
 * Public FX-rates endpoint.
 * Returns rates relative to USD so the frontend can preview converted amounts.
 */
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/fx.php';

$rates = fx_get_rates();
$content = read_json(CONTENT_FILE, []);
$donation = $content['donation'] ?? [];
header('Cache-Control: public, max-age=900');   // 15 min CDN/browser cache
json_response([
    'success'       => true,
    'base'          => 'USD',
    'siteCurrency'  => strtoupper((string)($donation['currency'] ?? 'UGX')),
    'charge'        => CURRENCY,
    'rates'         => $rates,
    'cryptoCodes'   => ['BTC', 'ETH', 'USDT', 'USDC', 'BNB', 'SOL'],
    'fetchedAt'     => date('c'),
]);
