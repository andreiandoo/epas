<?php
/**
 * Currencies.
 *
 * The owner's rule (2026-10-07): a price is shown in the currency of the country the thing is in (pounds in the
 * United Kingdom, francs in Switzerland, lei in Romania…); filters and sorting work on its value in euro, so that
 * "under €25" means the same in every country.
 *
 * Since 2026-10-08 the visitor can choose one currency for the whole site (the menu in the header, the form in the
 * footer). The choice is kept in the cookie V2_CURRENCY_COOKIE and every price is then shown in it, converted from
 * its euro value. Without a choice the rule above applies. The page cache keys on the cookie (includes/page-cache.php).
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

const V2_CURRENCY_COOKIE = 'vq_cur';

/** The currency the visitor chose for the whole site, or null when prices follow each place's own currency. */
function v2_display_currency(): ?string
{
    $c = $_COOKIE[V2_CURRENCY_COOKIE] ?? '';
    return is_string($c) && isset(V2_CURRENCIES[$c]) ? $c : null;
}

/** The currency prices are shown in for a country: the visitor's choice, else the country's own. */
function v2_currency_of(?string $countryCode): string
{
    return v2_display_currency() ?? (V2_COUNTRY_CURRENCY[strtoupper((string) $countryCode)] ?? 'EUR');
}

/** The currencies offered in the selector, the common ones first: code => "£ · Pound sterling". */
function v2_currency_choices(): array
{
    $names = [
        'EUR' => 'Euro', 'GBP' => 'Pound sterling', 'CHF' => 'Swiss franc', 'PLN' => 'Polish złoty', 'CZK' => 'Czech koruna',
        'HUF' => 'Hungarian forint', 'RON' => 'Romanian leu', 'SEK' => 'Swedish krona', 'NOK' => 'Norwegian krone',
        'DKK' => 'Danish krone', 'ISK' => 'Icelandic króna', 'RSD' => 'Serbian dinar', 'UAH' => 'Ukrainian hryvnia',
        'MDL' => 'Moldovan leu', 'ALL' => 'Albanian lek', 'MKD' => 'Macedonian denar', 'BAM' => 'Convertible mark',
    ];
    return array_intersect_key($names, V2_CURRENCIES);
}

/** The address that sets the visitor's currency and comes back to the page ('' = each place's own currency). */
function v2_currency_href(string $currency): string
{
    $back = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '#');
    // a page reached with ?nocache or ?preview comes back without them
    $back = preg_replace('/([?&])(nocache|preview)=[^&]*&?/', '$1', $back);
    return '/currency?c=' . rawurlencode($currency !== '' ? $currency : 'local') . '&back=' . rawurlencode(rtrim($back, '?&') ?: '/');
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
 * The line under a price filter.
 *   a country outside the euro: "Prices here are in pounds. The price filter is in euro: €10 is about £8.48."
 *   a currency the visitor chose: "Prices are shown in pounds, as you chose…"
 * Empty when prices are in euro and nothing was chosen.
 */
function v2_fx_note(?string $countryCode): string
{
    $chosen = v2_display_currency();
    $currency = v2_currency_of($countryCode);
    if ($chosen === null && $currency === 'EUR') {
        return '';
    }
    $rate = $currency === 'EUR' ? '' : ' The price filter is in euro: €10 is about ' . v2_money_in(10 * v2_rate($currency), $currency) . '.';
    if ($chosen !== null) {
        return 'Prices are shown in ' . V2_CURRENCIES[$currency][3] . ', as you chose. Converted prices are approximate; the amount to pay is confirmed before you pay.' . $rate;
    }
    return 'Prices here are in ' . V2_CURRENCIES[$currency][3] . '.' . $rate;
}

/* ------------------------------------------------------------------ our own listings
 * The API sends a product's cheapest price in cents, the operator's currency and the value in euro cents
 * (`cheapest_price_cents`, `currency`, `cheapest_price_eur_cents`). Older API versions send only the first; the price is
 * then in the site currency.
 */

/**
 * What is printed for one of our own prices: "£12.50", "€9". Empty for no price.
 * In the operator's currency; in the visitor's when he chose one (converted from the euro value).
 */
function v2_own_price_label($cents, ?string $currency = null, $eurCents = null): string
{
    $cents = (int) $cents;
    if ($cents <= 0) {
        return '';
    }
    $currency = strtoupper((string) $currency);
    $currency = $currency !== '' ? $currency : (defined('SITE_CURRENCY') ? SITE_CURRENCY : 'EUR');
    $chosen = v2_display_currency();
    if ($chosen !== null && $chosen !== $currency) {
        return v2_money_in(v2_own_price_eur($cents, $currency, $eurCents) * v2_rate($chosen), $chosen);
    }
    return v2_money_in($cents / 100, $currency);
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
