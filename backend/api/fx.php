<?php
/**
 * Public FX-rates endpoint.
 * Returns rates relative to USD so the frontend can preview converted amounts.
 */
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/fx.php';

$rates = fx_get_rates();
header('Cache-Control: public, max-age=900');   // 15 min CDN/browser cache
json_response([
    'success'   => true,
    'base'      => 'USD',
    'charge'    => CURRENCY,                    // currency MoMo will be charged in
    'rates'     => $rates,
    'fetchedAt' => date('c'),
]);
