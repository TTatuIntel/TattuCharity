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

function fx_fetch_crypto(): ?array
{
    $url = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,tether,usd-coin,binancecoin,solana&vs_currencies=usd';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => FX_API_TIMEOUT,
        CURLOPT_USERAGENT      => CHARITY_NAME . ' (server)',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($code !== 200 || !$body) {
        return null;
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        return null;
    }

    $map = [
        'BTC'  => 'bitcoin',
        'ETH'  => 'ethereum',
        'USDT' => 'tether',
        'USDC' => 'usd-coin',
        'BNB'  => 'binancecoin',
        'SOL'  => 'solana',
    ];
    $out = [];
    foreach ($map as $code => $id) {
        $usdPerCoin = (float)($data[$id]['usd'] ?? 0);
        if ($usdPerCoin > 0) {
            $out[$code] = 1 / $usdPerCoin;
        }
    }
    return $out ?: null;
}

function fx_crypto_fallback(): array
{
    return [
        'BTC'  => 1 / 95000,
        'ETH'  => 1 / 3500,
        'USDT' => 1.0,
        'USDC' => 1.0,
        'BNB'  => 1 / 600,
        'SOL'  => 1 / 150,
    ];
}

function fx_get_crypto_rates(): array
{
    $cache = read_json(FX_CACHE_FILE, ['ts' => 0, 'crypto' => []]);
    $age   = time() - (int)($cache['cryptoTs'] ?? 0);
    if ($age < FX_CACHE_TTL && !empty($cache['crypto'])) {
        return $cache['crypto'];
    }
    $live = fx_fetch_crypto();
    if ($live) {
        $cache['crypto']   = $live;
        $cache['cryptoTs'] = time();
        write_json(FX_CACHE_FILE, $cache);
        return $live;
    }
    if (!empty($cache['crypto'])) {
        return $cache['crypto'];
    }
    return fx_crypto_fallback();
}

/**
 * Returns rates relative to USD (e.g. ['UGX' => 3700, 'EUR' => 0.92, ...]).
 * Always returns at least the fallback table.
 */
function fx_get_rates(): array
{
    $cache = read_json(FX_CACHE_FILE, ['ts' => 0, 'rates' => []]);
    $age   = time() - (int)($cache['ts'] ?? 0);

    if ($age < FX_CACHE_TTL && !empty($cache['rates'])) {
        $fiat = $cache['rates'];
    } else {
        $fiat = fx_fetch_live();
        if ($fiat) {
            $cache['ts']    = time();
            $cache['rates'] = $fiat;
            $cache['source'] = 'live';
            write_json(FX_CACHE_FILE, $cache);
        } elseif (!empty($cache['rates'])) {
            $fiat = $cache['rates'];
        } else {
            $fiat = fx_fallback_rates();
        }
    }

    return array_merge($fiat, fx_get_crypto_rates());
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

function fx_is_crypto(string $code): bool
{
    return in_array(strtoupper(trim($code)), ['BTC', 'ETH', 'USDT', 'USDC', 'BNB', 'SOL', 'LTC'], true);
}

function valid_donation_currency(string $code): bool
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^[A-Z0-9]{3,5}$/', $code)) {
        return false;
    }
    $rates = fx_get_rates();
    return isset($rates[$code]);
}
