<?php
/**
 * Foreign-exchange helper.
 *
 * - Pulls live USD-base rates from open.er-api.com once an hour.
 * - Caches the response in data/fx_cache.json so subsequent requests
 *   are instant and we tolerate API downtime.
 * - Always falls back to a hard-coded rate table if everything fails.
 */
if (!defined('TATTU_INTERNAL')) { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/config.php';

const FX_CACHE_FILE   = DATA_DIR . 'fx_cache.json';
const FX_CACHE_TTL    = 3600;     // 1 hour
const FX_API_URL      = 'https://open.er-api.com/v6/latest/USD';
const FX_API_TIMEOUT  = 8;

/**
 * Returns rates relative to USD (e.g. ['UGX' => 3700, 'EUR' => 0.92, ...]).
 * Always returns at least the fallback table.
 */
function fx_get_rates(): array
{
    $cache = read_json(FX_CACHE_FILE, ['ts' => 0, 'rates' => []]);
    $age   = time() - (int)($cache['ts'] ?? 0);

    if ($age < FX_CACHE_TTL && !empty($cache['rates'])) {
        return $cache['rates'];
    }

    // Try the live API
    $rates = fx_fetch_live();
    if ($rates) {
        write_json(FX_CACHE_FILE, ['ts' => time(), 'rates' => $rates, 'source' => 'live']);
        return $rates;
    }

    // API failed — keep using last cached rates if we have them
    if (!empty($cache['rates'])) {
        return $cache['rates'];
    }

    // Last resort: hard-coded approximations
    return fx_fallback_rates();
}

/** @return array<string, float>|null */
function fx_fetch_live(): ?array
{
    $ch = curl_init(FX_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => FX_API_TIMEOUT,
        CURLOPT_USERAGENT      => CHARITY_NAME . ' (server)',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($code !== 200 || !$body) {
        error_log("fx_fetch_live: HTTP $code");
        return null;
    }
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['rates'])) {
        error_log('fx_fetch_live: bad response shape');
        return null;
    }
    return array_filter($data['rates'], fn($v) => is_numeric($v) && $v > 0);
}

function fx_fallback_rates(): array
{
    /* Approximate rates (per USD) — refresh occasionally. */
    return [
        'USD' => 1.00,    'EUR' => 0.92,    'GBP' => 0.79,    'CHF' => 0.88,
        'AUD' => 1.50,    'CAD' => 1.36,    'NZD' => 1.65,    'JPY' => 150.0,
        'CNY' => 7.20,    'INR' => 83.0,    'ZAR' => 18.5,    'NGN' => 1500,
        'GHS' => 14.5,    'EGP' => 49.0,    'AED' => 3.67,    'SAR' => 3.75,
        'UGX' => 3700,    'KES' => 130,     'TZS' => 2500,    'RWF' => 1300,
        'BIF' => 2900,    'ETB' => 110,     'XAF' => 605,     'XOF' => 605,
    ];
}

/**
 * Convert any amount/currency to the merchant's charge currency.
 *
 * @param float  $amount        Amount the donor entered
 * @param string $fromCurrency  Donor's currency (3-letter ISO code)
 * @param string $toCurrency    What MoMo will be charged in
 * @return float                Converted amount (always >= 0)
 *
 * @throws RuntimeException if either currency is unknown.
 */
function fx_convert(float $amount, string $fromCurrency, string $toCurrency): float
{
    $from = strtoupper(trim($fromCurrency));
    $to   = strtoupper(trim($toCurrency));
    if ($from === $to) return $amount;

    $rates = fx_get_rates();
    if (!isset($rates[$from]) || !isset($rates[$to])) {
        throw new RuntimeException("Unsupported currency: $from / $to");
    }
    $usd = $amount / (float) $rates[$from];
    return $usd * (float) $rates[$to];
}
