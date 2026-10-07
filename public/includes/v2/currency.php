<?php
/**
 * Currencies.
 *
 * The owner's rule (2026-10-07): a price is shown in the currency of the country the thing is in (pounds in the
 * United Kingdom, francs in Switzerland, lei in Romania…); filters and sorting work on its value in euro, so that
 * "under €25" means the same in every country.
 *
 *   v2_currency_of('GB')            'GBP'
 *   v2_money_in(12.5, 'GBP')        '£12.50'
 *   v2_price_local(15.0, 'GB')      '£12.73'   — a euro amount, shown in the country's currency
 *   v2_to_eur(12.5, 'GBP')          14.73      — what the filters compare
 *   v2_fx_note('GB')                the line that tells the visitor the filter is in euro, with the rate
 *
 * Rates: one euro in each currency, from open.er-api.com (free, daily), kept a day; the table below is used when the
 * service does not answer. Converted prices are indicative, which is why every one of them is shown as "from".
 */

/** ISO country code => currency. Countries not listed use the euro. */
const V2_COUNTRY_CURRENCY = [
    'GB' => 'GBP', 'GI' => 'GBP', 'IM' => 'GBP', 'JE' => 'GBP', 'GG' => 'GBP',
    'CH' => 'CHF', 'LI' => 'CHF',
    'CZ' => 'CZK', 'PL' => 'PLN', 'HU' => 'HUF', 'RO' => 'RON',
    'SE' => 'SEK', 'NO' => 'NOK', 'SJ' => 'NOK', 'DK' => 'DKK', 'FO' => 'DKK', 'IS' => 'ISK',
    'RS' => 'RSD', 'UA' => 'UAH', 'MD' => 'MDL', 'AL' => 'ALL', 'MK' => 'MKD', 'BA' => 'BAM',
];

/** currency => [symbol or word, written before the amount?, decimals when the amount is not whole, name in a sentence] */
const V2_CURRENCIES = [
    'EUR' => ['€', true, 2, 'euro'],
    'GBP' => ['£', true, 2, 'pounds'],
    'CHF' => ['CHF ', true, 2, 'Swiss francs'],
    'CZK' => [' Kč', false, 0, 'Czech koruna'],
    'PLN' => [' zł', false, 0, 'Polish złoty'],
    'HUF' => [' Ft', false, 0, 'Hungarian forint'],
    'RON' => [' lei', false, 0, 'Romanian lei'],
    'SEK' => [' kr', false, 0, 'Swedish kronor'],
    'NOK' => [' kr', false, 0, 'Norwegian kroner'],
    'DKK' => [' kr', false, 0, 'Danish kroner'],
    'ISK' => [' kr', false, 0, 'Icelandic krónur'],
    'RSD' => [' RSD', false, 0, 'Serbian dinars'],
    'UAH' => ['₴', true, 0, 'Ukrainian hryvnias'],
    'MDL' => [' MDL', false, 0, 'Moldovan lei'],
    'ALL' => [' L', false, 0, 'Albanian lek'],
    'MKD' => [' den', false, 0, 'Macedonian denars'],
    'BAM' => [' KM', false, 2, 'convertible marks'],
];

/** One euro in each currency on 2026-10-07; used only when the rate service does not answer. */
const V2_RATES_FALLBACK = [
    'EUR' => 1.0, 'GBP' => 0.8485, 'CHF' => 0.9361, 'CZK' => 24.40, 'PLN' => 4.370, 'HUF' => 365.3, 'RON' => 5.346,
    'SEK' => 11.25, 'NOK' => 10.76, 'DKK' => 7.475, 'ISK' => 137.1, 'RSD' => 117.5, 'UAH' => 50.60, 'MDL' => 20.09,
    'ALL' => 91.96, 'MKD' => 61.50, 'BAM' => 1.9558,
];

function v2_currency_of(?string $countryCode): string
{
    return V2_COUNTRY_CURRENCY[strtoupper((string) $countryCode)] ?? 'EUR';
}

/** One euro in $currency. */
function v2_rate(string $currency): float
{
    static $rates = null;
    if ($currency === 'EUR') {
        return 1.0;
    }
    if ($rates === null) {
        $res = api_cached('fx_eur_rates', function () {
            $ch = curl_init('https://open.er-api.com/v6/latest/EUR');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = is_string($body) ? json_decode($body, true) : null;
            if ($code !== 200 || ($data['result'] ?? '') !== 'success' || !is_array($data['rates'] ?? null)) {
                error_log('Exchange rates: HTTP ' . $code);
                return ['success' => false];
            }
            return ['success' => true, 'rates' => array_intersect_key($data['rates'], V2_CURRENCIES)];
        }, 86400);
        $rates = !empty($res['success']) ? $res['rates'] : [];
    }
    $rate = (float) ($rates[$currency] ?? 0);
    // a rate far from the one we know is a broken answer, not a market move
    $known = (float) (V2_RATES_FALLBACK[$currency] ?? 0);
    if ($rate <= 0 || ($known > 0 && ($rate < $known * 0.5 || $rate > $known * 2))) {
        $rate = $known;
    }
    return $rate > 0 ? $rate : 1.0;
}

/** An amount in a currency, as it is written: "£12.50", "CHF 12", "290 Kč", "4,500 Ft". */
function v2_money_in($amount, string $currency): string
{
    [$sign, $before, $decimals] = V2_CURRENCIES[$currency] ?? [' ' . $currency, false, 2];
    $amount = (float) $amount;
    if ($decimals === 0 || abs($amount - round($amount)) < 0.005) {
        $n = number_format(round($amount), 0, '.', ',');
    } else {
        $n = number_format($amount, $decimals, '.', ',');
    }
    return $before ? $sign . $n : $n . $sign;
}

/** A euro amount, shown in the currency of a country. */
function v2_price_local($eur, ?string $countryCode): string
{
    $currency = v2_currency_of($countryCode);
    return v2_money_in((float) $eur * v2_rate($currency), $currency);
}

/** An amount in a currency, in euro: the number filters and sorting compare. */
function v2_to_eur($amount, string $currency): float
{
    return (float) $amount / v2_rate($currency);
}

/**
 * The line under a price filter, for a country that does not use the euro:
 * "Prices here are in pounds. The price filter is in euro: €10 is about £8.48."  Empty for euro countries.
 */
function v2_fx_note(?string $countryCode): string
{
    $currency = v2_currency_of($countryCode);
    if ($currency === 'EUR') {
        return '';
    }
    return 'Prices here are in ' . V2_CURRENCIES[$currency][3] . '. The price filter is in euro: €10 is about '
        . v2_money_in(10 * v2_rate($currency), $currency) . '.';
}

/* ------------------------------------------------------------------ our own listings
 * The API sends a product's cheapest price in cents, the operator's currency and the value in euro cents
 * (`cheapest_price_cents`, `currency`, `cheapest_price_eur_cents`). Older API versions send only the first; the price is
 * then in the site currency.
 */

/** What is printed for one of our own prices: "£12.50", "€9". Empty for no price. */
function v2_own_price_label($cents, ?string $currency = null): string
{
    $cents = (int) $cents;
    if ($cents <= 0) {
        return '';
    }
    $currency = strtoupper((string) $currency);
    return v2_money_in($cents / 100, $currency !== '' ? $currency : (defined('SITE_CURRENCY') ? SITE_CURRENCY : 'EUR'));
}

/** The euro value of one of our own prices, for filters and sorting: the API's when it sends it, else our rate. */
function v2_own_price_eur($cents, ?string $currency = null, $eurCents = null): float
{
    if ($eurCents !== null && $eurCents !== '') {
        return (int) $eurCents / 100;
    }
    $currency = strtoupper((string) $currency);
    $site = defined('SITE_CURRENCY') ? SITE_CURRENCY : 'EUR';
    return v2_to_eur((int) $cents / 100, $currency !== '' ? $currency : $site);
}
