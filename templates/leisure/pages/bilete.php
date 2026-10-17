<?php
/** Portat din resources/tenant-demos/leisure/bilete.php (Nordvale). */
require_once TEMPLATE_DIR . '/lib.php';
// Pachetele = biletele de acces reale ale parcului; extra = activități și închirieri
$pkgStyles = [
    ['icon' => '⌁', 'color' => 'rgb(var(--c-primary))', 'textColor' => 'rgb(var(--c-pine-950))', 'iconBg' => 'rgb(var(--c-pine-950) / .12)', 'kicker' => 'Acces de o zi'],
    ['icon' => '↗', 'color' => '#f27b4a', 'textColor' => '#ffffff', 'iconBg' => 'rgba(255,255,255,.18)', 'kicker' => 'Acces de o zi'],
    ['icon' => '⌂', 'color' => '#b8dce0', 'textColor' => 'rgb(var(--c-pine-950))', 'iconBg' => 'rgb(var(--c-pine-950) / .1)', 'kicker' => 'Acces de o zi'],
];
$pkgAccess = lv_tickets('access');
$pkgEvent = null;
$pkgItems = [];
foreach ($pkgAccess as $i => $b) {
    $st = $pkgStyles[$i % 3];
    $desc = trim(strip_tags((string) ($b['description'] ?? '')));
    $pkgEvent = $pkgEvent ?? $b['event'] ?? null;
    $pkgItems[] = $st + [
        'id'             => (int) $b['ticket_type_id'],
        'ticket_type_id' => (int) $b['ticket_type_id'],
        'name'           => (string) $b['name'],
        'short'          => $desc !== '' ? mb_strimwidth($desc, 0, 90, '…') : 'Intrarea în parc pentru toată ziua aleasă.',
        'description'    => $desc !== '' ? $desc : 'Biletul de acces îți deschide parcul pentru toată ziua aleasă.',
        'price'          => ((int) $b['price_cents']) / 100,
        'unit'           => 'lei / bilet',
        'includes'       => ['Acces în parc toată ziua', 'Bilet pe email, cu cod QR'],
        'bestFor'        => lv_duration($b),
        'url'            => '/experienta/' . lv_slug($b),
    ];
}
$pkgExtras = [];
$extraIcons = ['activity' => '↗', 'rental' => '◒'];
$extraColors = ['activity' => '#f27b4a', 'rental' => '#b8dce0'];
foreach (array_merge(lv_tickets('activity'), lv_tickets('rental')) as $b) {
    $cat = (string) $b['service_category'];
    $pkgEvent = $pkgEvent ?? $b['event'] ?? null;
    $pkgExtras[] = [
        'id'             => (int) $b['ticket_type_id'],
        'ticket_type_id' => (int) $b['ticket_type_id'],
        'name'           => (string) $b['name'],
        'category'       => $cat,
        'description'    => mb_strimwidth(trim(strip_tags((string) ($b['description'] ?? ''))), 0, 110, '…'),
        'price'          => ((int) $b['price_cents']) / 100,
        'variants'       => array_values(array_filter((array) ($b['duration_variants'] ?? []), fn ($v) => !empty($v['duration_minutes']))),
        'icon'           => $extraIcons[$cat] ?? '✦',
        'color'          => $extraColors[$cat] ?? 'rgb(var(--c-primary))',
    ];
}
$pkgEventJs = [
    'id'     => (int) ($pkgEvent['id'] ?? 0),
    'slug'   => (string) ($pkgEvent['slug'] ?? ''),
    'title'  => SITE_NAME,
    'place'  => (string) ($pkgEvent['venue'] ?? SITE_NAME),
    'poster' => lv_photo(LV_PHOTOS['access'][0], 600),
    'url'    => '/bilete',
];
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;
$pkgHero = array_slice($pkgItems, 0, 2);
$firstAccess = $pkgItems[0]['name'] ?? 'biletul de acces';
$firstActivity = null; $firstRental = null;
foreach ($pkgExtras as $x) {
    if ($x['category'] === 'activity' && !$firstActivity) { $firstActivity = $x['name']; }
    if ($x['category'] === 'rental' && !$firstRental) { $firstRental = $x['name']; }
}
$familyPass = null;
foreach ($pkgItems as $p) {
    if (preg_match('/famil/i', $p['name'])) { $familyPass = $p['name']; break; }
}
$pkgIntents = [
    ['title' => 'Vreau o zi liniștită', 'package' => 'Acces', 'icon' => '⌁', 'color' => 'rgb(var(--c-primary))', 'description' => 'Mai multă natură, mai puțină presiune. Intri când vrei și îți faci programul pe loc.', 'includes' => $firstAccess],
    ['title' => 'Vrem și ceva activ', 'package' => 'Acces + extra', 'icon' => '↗', 'color' => '#f27b4a', 'description' => 'Adaugă o activitate sau o închiriere în aceeași zi și ai totul rezervat dinainte.', 'includes' => $firstAccess . ($firstActivity || $firstRental ? ' + ' . ($firstActivity ?: $firstRental) : '')],
    ['title' => 'Venim cu copiii', 'package' => 'Familie', 'icon' => '⌂', 'color' => '#b8dce0', 'description' => 'Pauze când e nevoie, spațiu de joacă și un bilet potrivit pentru toată familia.', 'includes' => $familyPass ?: $firstAccess],
];

$nvNav=''; $nvNoSpacer=true;
$pageTitle = 'Bilete și pachete' . ' — ' . SITE_NAME;
$bodyAttrs = 'x-data="ticketsPage()" :class="giftOpen ? \'overflow-hidden\' : \'\'" class="antialiased"';
$pageExtraStyles = <<<'CSS'
/*! tailwindcss v4.1.10 | MIT License | https://tailwindcss.com */
@layer properties;
@layer theme, base, components, utilities;
@layer theme {
  :root, :host {
    --font-sans: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji",
      "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
    --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono",
      "Courier New", monospace;
    --color-gray-100: oklch(96.7% 0.003 264.542);
    --color-gray-200: oklch(92.8% 0.006 264.531);
    --color-gray-400: oklch(70.7% 0.022 261.325);
    --color-gray-500: oklch(55.1% 0.027 264.364);
    --color-gray-600: oklch(44.6% 0.03 256.802);
    --color-white: #fff;
    --spacing: 0.25rem;
    --breakpoint-sm: 40rem;
    --breakpoint-2xl: 96rem;
    --container-xs: 20rem;
    --container-sm: 24rem;
    --container-md: 28rem;
    --container-xl: 36rem;
    --container-3xl: 48rem;
    --text-xs: 0.75rem;
    --text-xs--line-height: calc(1 / 0.75);
    --text-sm: 0.875rem;
    --text-sm--line-height: calc(1.25 / 0.875);
    --text-base: 1rem;
    --text-base--line-height: calc(1.5 / 1);
    --text-lg: 1.125rem;
    --text-lg--line-height: calc(1.75 / 1.125);
    --text-xl: 1.25rem;
    --text-xl--line-height: calc(1.75 / 1.25);
    --text-2xl: 1.5rem;
    --text-2xl--line-height: calc(2 / 1.5);
    --text-3xl: 1.875rem;
    --text-3xl--line-height: calc(2.25 / 1.875);
    --text-4xl: 2.25rem;
    --text-4xl--line-height: calc(2.5 / 2.25);
    --text-5xl: 3rem;
    --text-5xl--line-height: 1;
    --text-6xl: 3.75rem;
    --text-6xl--line-height: 1;
    --text-7xl: 4.5rem;
    --text-7xl--line-height: 1;
    --text-8xl: 6rem;
    --text-8xl--line-height: 1;
    --font-weight-semibold: 600;
    --font-weight-bold: 700;
    --tracking-tight: -0.025em;
    --tracking-widest: 0.1em;
    --radius-xl: 0.75rem;
    --radius-2xl: 1rem;
    --radius-3xl: 1.5rem;
    --ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
    --default-transition-duration: 150ms;
    --default-transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    --default-font-family: var(--font-sans);
    --default-mono-font-family: var(--font-mono);
  }
}
@layer base {
  *, ::after, ::before, ::backdrop, ::file-selector-button {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    border: 0 solid;
  }
  html, :host {
    line-height: 1.5;
    -webkit-text-size-adjust: 100%;
    tab-size: 4;
    font-family: var(--default-font-family, ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji");
    font-feature-settings: var(--default-font-feature-settings, normal);
    font-variation-settings: var(--default-font-variation-settings, normal);
    -webkit-tap-highlight-color: transparent;
  }
  hr {
    height: 0;
    color: inherit;
    border-top-width: 1px;
  }
  abbr:where([title]) {
    -webkit-text-decoration: underline dotted;
    text-decoration: underline dotted;
  }
  h1, h2, h3, h4, h5, h6 {
    font-size: inherit;
    font-weight: inherit;
  }
  a {
    color: inherit;
    -webkit-text-decoration: inherit;
    text-decoration: inherit;
  }
  b, strong {
    font-weight: bolder;
  }
  code, kbd, samp, pre {
    font-family: var(--default-mono-font-family, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace);
    font-feature-settings: var(--default-mono-font-feature-settings, normal);
    font-variation-settings: var(--default-mono-font-variation-settings, normal);
    font-size: 1em;
  }
  small {
    font-size: 80%;
  }
  sub, sup {
    font-size: 75%;
    line-height: 0;
    position: relative;
    vertical-align: baseline;
  }
  sub {
    bottom: -0.25em;
  }
  sup {
    top: -0.5em;
  }
  table {
    text-indent: 0;
    border-color: inherit;
    border-collapse: collapse;
  }
  :-moz-focusring {
    outline: auto;
  }
  progress {
    vertical-align: baseline;
  }
  summary {
    display: list-item;
  }
  ol, ul, menu {
    list-style: none;
  }
  img, svg, video, canvas, audio, iframe, embed, object {
    display: block;
    vertical-align: middle;
  }
  img, video {
    max-width: 100%;
    height: auto;
  }
  button, input, select, optgroup, textarea, ::file-selector-button {
    font: inherit;
    font-feature-settings: inherit;
    font-variation-settings: inherit;
    letter-spacing: inherit;
    color: inherit;
    border-radius: 0;
    background-color: transparent;
    opacity: 1;
  }
  :where(select:is([multiple], [size])) optgroup {
    font-weight: bolder;
  }
  :where(select:is([multiple], [size])) optgroup option {
    padding-inline-start: 20px;
  }
  ::file-selector-button {
    margin-inline-end: 4px;
  }
  ::placeholder {
    opacity: 1;
  }
  @supports (not (-webkit-appearance: -apple-pay-button))  or (contain-intrinsic-size: 1px) {
    ::placeholder {
      color: currentcolor;
      @supports (color: color-mix(in lab, red, red)) {
        color: color-mix(in oklab, currentcolor 50%, transparent);
      }
    }
  }
  textarea {
    resize: vertical;
  }
  ::-webkit-search-decoration {
    -webkit-appearance: none;
  }
  ::-webkit-date-and-time-value {
    min-height: 1lh;
    text-align: inherit;
  }
  ::-webkit-datetime-edit {
    display: inline-flex;
  }
  ::-webkit-datetime-edit-fields-wrapper {
    padding: 0;
  }
  ::-webkit-datetime-edit, ::-webkit-datetime-edit-year-field, ::-webkit-datetime-edit-month-field, ::-webkit-datetime-edit-day-field, ::-webkit-datetime-edit-hour-field, ::-webkit-datetime-edit-minute-field, ::-webkit-datetime-edit-second-field, ::-webkit-datetime-edit-millisecond-field, ::-webkit-datetime-edit-meridiem-field {
    padding-block: 0;
  }
  :-moz-ui-invalid {
    box-shadow: none;
  }
  button, input:where([type="button"], [type="reset"], [type="submit"]), ::file-selector-button {
    appearance: button;
  }
  ::-webkit-inner-spin-button, ::-webkit-outer-spin-button {
    height: auto;
  }
  [hidden]:where(:not([hidden="until-found"])) {
    display: none !important;
  }
}
@layer utilities {
  .pointer-events-none {
    pointer-events: none;
  }
  .absolute {
    position: absolute;
  }
  .fixed {
    position: fixed;
  }
  .relative {
    position: relative;
  }
  .inset-0 {
    inset: calc(var(--spacing) * 0);
  }
  .inset-x-0 {
    inset-inline: calc(var(--spacing) * 0);
  }
  .top-0 {
    top: calc(var(--spacing) * 0);
  }
  .top-8 {
    top: calc(var(--spacing) * 8);
  }
  .top-48 {
    top: calc(var(--spacing) * 48);
  }
  .right-0 {
    right: calc(var(--spacing) * 0);
  }
  .bottom-0 {
    bottom: calc(var(--spacing) * 0);
  }
  .bottom-5 {
    bottom: calc(var(--spacing) * 5);
  }
  .left-0 {
    left: calc(var(--spacing) * 0);
  }
  .left-1\/2 {
    left: calc(1/2 * 100%);
  }
  .left-10 {
    left: calc(var(--spacing) * 10);
  }
  .z-10 {
    z-index: 10;
  }
  .z-30 {
    z-index: 30;
  }
  .z-40 {
    z-index: 40;
  }
  .z-50 {
    z-index: 50;
  }
  .mx-auto {
    margin-inline: auto;
  }
  .mt-1 {
    margin-top: calc(var(--spacing) * 1);
  }
  .mt-2 {
    margin-top: calc(var(--spacing) * 2);
  }
  .mt-3 {
    margin-top: calc(var(--spacing) * 3);
  }
  .mt-4 {
    margin-top: calc(var(--spacing) * 4);
  }
  .mt-5 {
    margin-top: calc(var(--spacing) * 5);
  }
  .mt-6 {
    margin-top: calc(var(--spacing) * 6);
  }
  .mt-7 {
    margin-top: calc(var(--spacing) * 7);
  }
  .mt-8 {
    margin-top: calc(var(--spacing) * 8);
  }
  .mt-10 {
    margin-top: calc(var(--spacing) * 10);
  }
  .block {
    display: block;
  }
  .flex {
    display: flex;
  }
  .grid {
    display: grid;
  }
  .hidden {
    display: none;
  }
  .inline-flex {
    display: inline-flex;
  }
  .h-1 {
    height: calc(var(--spacing) * 1);
  }
  .h-2 {
    height: calc(var(--spacing) * 2);
  }
  .h-5 {
    height: calc(var(--spacing) * 5);
  }
  .h-7 {
    height: calc(var(--spacing) * 7);
  }
  .h-8 {
    height: calc(var(--spacing) * 8);
  }
  .h-9 {
    height: calc(var(--spacing) * 9);
  }
  .h-10 {
    height: calc(var(--spacing) * 10);
  }
  .h-11 {
    height: calc(var(--spacing) * 11);
  }
  .h-12 {
    height: calc(var(--spacing) * 12);
  }
  .max-h-screen {
    max-height: 100vh;
  }
  .w-2 {
    width: calc(var(--spacing) * 2);
  }
  .w-5 {
    width: calc(var(--spacing) * 5);
  }
  .w-6 {
    width: calc(var(--spacing) * 6);
  }
  .w-7 {
    width: calc(var(--spacing) * 7);
  }
  .w-8 {
    width: calc(var(--spacing) * 8);
  }
  .w-9 {
    width: calc(var(--spacing) * 9);
  }
  .w-10 {
    width: calc(var(--spacing) * 10);
  }
  .w-11 {
    width: calc(var(--spacing) * 11);
  }
  .w-11\/12 {
    width: calc(11/12 * 100%);
  }
  .w-12 {
    width: calc(var(--spacing) * 12);
  }
  .w-full {
    width: 100%;
  }
  .max-w-3xl {
    max-width: var(--container-3xl);
  }
  .max-w-md {
    max-width: var(--container-md);
  }
  .max-w-screen-2xl {
    max-width: var(--breakpoint-2xl);
  }
  .max-w-screen-sm {
    max-width: var(--breakpoint-sm);
  }
  .max-w-sm {
    max-width: var(--container-sm);
  }
  .max-w-xl {
    max-width: var(--container-xl);
  }
  .max-w-xs {
    max-width: var(--container-xs);
  }
  .min-w-0 {
    min-width: calc(var(--spacing) * 0);
  }
  .min-w-max {
    min-width: max-content;
  }
  .flex-1 {
    flex: 1;
  }
  .flex-none {
    flex: none;
  }
  .origin-left {
    transform-origin: left;
  }
  .-translate-x-1\/2 {
    --tw-translate-x: calc(calc(1/2 * 100%) * -1);
    translate: var(--tw-translate-x) var(--tw-translate-y);
  }
  .cursor-pointer {
    cursor: pointer;
  }
  .grid-cols-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .grid-cols-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .flex-col {
    flex-direction: column;
  }
  .place-items-center {
    place-items: center;
  }
  .items-center {
    align-items: center;
  }
  .items-end {
    align-items: flex-end;
  }
  .items-start {
    align-items: flex-start;
  }
  .justify-between {
    justify-content: space-between;
  }
  .justify-center {
    justify-content: center;
  }
  .gap-1 {
    gap: calc(var(--spacing) * 1);
  }
  .gap-2 {
    gap: calc(var(--spacing) * 2);
  }
  .gap-3 {
    gap: calc(var(--spacing) * 3);
  }
  .gap-4 {
    gap: calc(var(--spacing) * 4);
  }
  .gap-5 {
    gap: calc(var(--spacing) * 5);
  }
  .gap-7 {
    gap: calc(var(--spacing) * 7);
  }
  .gap-8 {
    gap: calc(var(--spacing) * 8);
  }
  .gap-10 {
    gap: calc(var(--spacing) * 10);
  }
  .gap-12 {
    gap: calc(var(--spacing) * 12);
  }
  .space-y-3 {
    :where(& > :not(:last-child)) {
      --tw-space-y-reverse: 0;
      margin-block-start: calc(calc(var(--spacing) * 3) * var(--tw-space-y-reverse));
      margin-block-end: calc(calc(var(--spacing) * 3) * calc(1 - var(--tw-space-y-reverse)));
    }
  }
  .space-y-5 {
    :where(& > :not(:last-child)) {
      --tw-space-y-reverse: 0;
      margin-block-start: calc(calc(var(--spacing) * 5) * var(--tw-space-y-reverse));
      margin-block-end: calc(calc(var(--spacing) * 5) * calc(1 - var(--tw-space-y-reverse)));
    }
  }
  .truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .overflow-hidden {
    overflow: hidden;
  }
  .overflow-x-auto {
    overflow-x: auto;
  }
  .overflow-y-auto {
    overflow-y: auto;
  }
  .scroll-smooth {
    scroll-behavior: smooth;
  }
  .rounded-2xl {
    border-radius: var(--radius-2xl);
  }
  .rounded-3xl {
    border-radius: var(--radius-3xl);
  }
  .rounded-full {
    border-radius: calc(infinity * 1px);
  }
  .rounded-xl {
    border-radius: var(--radius-xl);
  }
  .rounded-t-3xl {
    border-top-left-radius: var(--radius-3xl);
    border-top-right-radius: var(--radius-3xl);
  }
  .border {
    border-style: var(--tw-border-style);
    border-width: 1px;
  }
  .border-t {
    border-top-style: var(--tw-border-style);
    border-top-width: 1px;
  }
  .border-b {
    border-bottom-style: var(--tw-border-style);
    border-bottom-width: 1px;
  }
  .border-current {
    border-color: currentcolor;
  }
  .border-gray-100 {
    border-color: var(--color-gray-100);
  }
  .border-gray-200 {
    border-color: var(--color-gray-200);
  }
  .border-white {
    border-color: var(--color-white);
  }
  .bg-gray-100 {
    background-color: var(--color-gray-100);
  }
  .bg-white {
    background-color: var(--color-white);
  }
  .p-3 {
    padding: calc(var(--spacing) * 3);
  }
  .p-4 {
    padding: calc(var(--spacing) * 4);
  }
  .p-5 {
    padding: calc(var(--spacing) * 5);
  }
  .p-6 {
    padding: calc(var(--spacing) * 6);
  }
  .px-3 {
    padding-inline: calc(var(--spacing) * 3);
  }
  .px-4 {
    padding-inline: calc(var(--spacing) * 4);
  }
  .px-5 {
    padding-inline: calc(var(--spacing) * 5);
  }
  .px-6 {
    padding-inline: calc(var(--spacing) * 6);
  }
  .py-1 {
    padding-block: calc(var(--spacing) * 1);
  }
  .py-2 {
    padding-block: calc(var(--spacing) * 2);
  }
  .py-3 {
    padding-block: calc(var(--spacing) * 3);
  }
  .py-4 {
    padding-block: calc(var(--spacing) * 4);
  }
  .py-5 {
    padding-block: calc(var(--spacing) * 5);
  }
  .py-16 {
    padding-block: calc(var(--spacing) * 16);
  }
  .pt-3 {
    padding-top: calc(var(--spacing) * 3);
  }
  .pt-4 {
    padding-top: calc(var(--spacing) * 4);
  }
  .pt-5 {
    padding-top: calc(var(--spacing) * 5);
  }
  .pt-6 {
    padding-top: calc(var(--spacing) * 6);
  }
  .pt-8 {
    padding-top: calc(var(--spacing) * 8);
  }
  .pt-14 {
    padding-top: calc(var(--spacing) * 14);
  }
  .pt-32 {
    padding-top: calc(var(--spacing) * 32);
  }
  .pr-6 {
    padding-right: calc(var(--spacing) * 6);
  }
  .pb-2 {
    padding-bottom: calc(var(--spacing) * 2);
  }
  .pb-5 {
    padding-bottom: calc(var(--spacing) * 5);
  }
  .pb-6 {
    padding-bottom: calc(var(--spacing) * 6);
  }
  .pb-8 {
    padding-bottom: calc(var(--spacing) * 8);
  }
  .pb-10 {
    padding-bottom: calc(var(--spacing) * 10);
  }
  .pb-16 {
    padding-bottom: calc(var(--spacing) * 16);
  }
  .text-center {
    text-align: center;
  }
  .text-left {
    text-align: left;
  }
  .text-right {
    text-align: right;
  }
  .text-2xl {
    font-size: var(--text-2xl);
    line-height: var(--tw-leading, var(--text-2xl--line-height));
  }
  .text-3xl {
    font-size: var(--text-3xl);
    line-height: var(--tw-leading, var(--text-3xl--line-height));
  }
  .text-4xl {
    font-size: var(--text-4xl);
    line-height: var(--tw-leading, var(--text-4xl--line-height));
  }
  .text-5xl {
    font-size: var(--text-5xl);
    line-height: var(--tw-leading, var(--text-5xl--line-height));
  }
  .text-base {
    font-size: var(--text-base);
    line-height: var(--tw-leading, var(--text-base--line-height));
  }
  .text-lg {
    font-size: var(--text-lg);
    line-height: var(--tw-leading, var(--text-lg--line-height));
  }
  .text-sm {
    font-size: var(--text-sm);
    line-height: var(--tw-leading, var(--text-sm--line-height));
  }
  .text-xl {
    font-size: var(--text-xl);
    line-height: var(--tw-leading, var(--text-xl--line-height));
  }
  .text-xs {
    font-size: var(--text-xs);
    line-height: var(--tw-leading, var(--text-xs--line-height));
  }
  .leading-5 {
    --tw-leading: calc(var(--spacing) * 5);
    line-height: calc(var(--spacing) * 5);
  }
  .leading-6 {
    --tw-leading: calc(var(--spacing) * 6);
    line-height: calc(var(--spacing) * 6);
  }
  .leading-7 {
    --tw-leading: calc(var(--spacing) * 7);
    line-height: calc(var(--spacing) * 7);
  }
  .leading-none {
    --tw-leading: 1;
    line-height: 1;
  }
  .font-bold {
    --tw-font-weight: var(--font-weight-bold);
    font-weight: var(--font-weight-bold);
  }
  .font-semibold {
    --tw-font-weight: var(--font-weight-semibold);
    font-weight: var(--font-weight-semibold);
  }
  .tracking-tight {
    --tw-tracking: var(--tracking-tight);
    letter-spacing: var(--tracking-tight);
  }
  .tracking-widest {
    --tw-tracking: var(--tracking-widest);
    letter-spacing: var(--tracking-widest);
  }
  .whitespace-nowrap {
    white-space: nowrap;
  }
  .text-gray-400 {
    color: var(--color-gray-400);
  }
  .text-gray-500 {
    color: var(--color-gray-500);
  }
  .text-gray-600 {
    color: var(--color-gray-600);
  }
  .text-white {
    color: var(--color-white);
  }
  .uppercase {
    text-transform: uppercase;
  }
  .italic {
    font-style: italic;
  }
  .antialiased {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }
  .opacity-30 {
    opacity: 30%;
  }
  .opacity-40 {
    opacity: 40%;
  }
  .opacity-45 {
    opacity: 45%;
  }
  .opacity-50 {
    opacity: 50%;
  }
  .opacity-55 {
    opacity: 55%;
  }
  .opacity-65 {
    opacity: 65%;
  }
  .opacity-70 {
    opacity: 70%;
  }
  .backdrop-filter {
    -webkit-backdrop-filter: var(--tw-backdrop-blur,) var(--tw-backdrop-brightness,) var(--tw-backdrop-contrast,) var(--tw-backdrop-grayscale,) var(--tw-backdrop-hue-rotate,) var(--tw-backdrop-invert,) var(--tw-backdrop-opacity,) var(--tw-backdrop-saturate,) var(--tw-backdrop-sepia,);
    backdrop-filter: var(--tw-backdrop-blur,) var(--tw-backdrop-brightness,) var(--tw-backdrop-contrast,) var(--tw-backdrop-grayscale,) var(--tw-backdrop-hue-rotate,) var(--tw-backdrop-invert,) var(--tw-backdrop-opacity,) var(--tw-backdrop-saturate,) var(--tw-backdrop-sepia,);
  }
  .transition {
    transition-property: color, background-color, border-color, outline-color, text-decoration-color, fill, stroke, --tw-gradient-from, --tw-gradient-via, --tw-gradient-to, opacity, box-shadow, transform, translate, scale, rotate, filter, -webkit-backdrop-filter, backdrop-filter, display, visibility, content-visibility, overlay, pointer-events;
    transition-timing-function: var(--tw-ease, var(--default-transition-timing-function));
    transition-duration: var(--tw-duration, var(--default-transition-duration));
  }
  .ease-in-out {
    --tw-ease: var(--ease-in-out);
    transition-timing-function: var(--ease-in-out);
  }
  .hover\:bg-white {
    &:hover {
      @media (hover: hover) {
        background-color: var(--color-white);
      }
    }
  }
  .hover\:opacity-100 {
    &:hover {
      @media (hover: hover) {
        opacity: 100%;
      }
    }
  }
  .sm\:top-0 {
    @media (width >= 40rem) {
      top: calc(var(--spacing) * 0);
    }
  }
  .sm\:right-0 {
    @media (width >= 40rem) {
      right: calc(var(--spacing) * 0);
    }
  }
  .sm\:left-20 {
    @media (width >= 40rem) {
      left: calc(var(--spacing) * 20);
    }
  }
  .sm\:left-auto {
    @media (width >= 40rem) {
      left: auto;
    }
  }
  .sm\:h-full {
    @media (width >= 40rem) {
      height: 100%;
    }
  }
  .sm\:w-4\/5 {
    @media (width >= 40rem) {
      width: calc(4/5 * 100%);
    }
  }
  .sm\:w-full {
    @media (width >= 40rem) {
      width: 100%;
    }
  }
  .sm\:max-w-md {
    @media (width >= 40rem) {
      max-width: var(--container-md);
    }
  }
  .sm\:grid-cols-2 {
    @media (width >= 40rem) {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  .sm\:grid-cols-3 {
    @media (width >= 40rem) {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  .sm\:flex-row {
    @media (width >= 40rem) {
      flex-direction: row;
    }
  }
  .sm\:items-center {
    @media (width >= 40rem) {
      align-items: center;
    }
  }
  .sm\:justify-between {
    @media (width >= 40rem) {
      justify-content: space-between;
    }
  }
  .sm\:gap-3 {
    @media (width >= 40rem) {
      gap: calc(var(--spacing) * 3);
    }
  }
  .sm\:rounded-3xl {
    @media (width >= 40rem) {
      border-radius: var(--radius-3xl);
    }
  }
  .sm\:rounded-none {
    @media (width >= 40rem) {
      border-radius: 0;
    }
  }
  .sm\:p-7 {
    @media (width >= 40rem) {
      padding: calc(var(--spacing) * 7);
    }
  }
  .sm\:p-9 {
    @media (width >= 40rem) {
      padding: calc(var(--spacing) * 9);
    }
  }
  .sm\:px-4 {
    @media (width >= 40rem) {
      padding-inline: calc(var(--spacing) * 4);
    }
  }
  .sm\:px-5 {
    @media (width >= 40rem) {
      padding-inline: calc(var(--spacing) * 5);
    }
  }
  .sm\:px-6 {
    @media (width >= 40rem) {
      padding-inline: calc(var(--spacing) * 6);
    }
  }
  .sm\:px-7 {
    @media (width >= 40rem) {
      padding-inline: calc(var(--spacing) * 7);
    }
  }
  .sm\:py-20 {
    @media (width >= 40rem) {
      padding-block: calc(var(--spacing) * 20);
    }
  }
  .sm\:pt-40 {
    @media (width >= 40rem) {
      padding-top: calc(var(--spacing) * 40);
    }
  }
  .sm\:pr-12 {
    @media (width >= 40rem) {
      padding-right: calc(var(--spacing) * 12);
    }
  }
  .sm\:pb-7 {
    @media (width >= 40rem) {
      padding-bottom: calc(var(--spacing) * 7);
    }
  }
  .sm\:pb-24 {
    @media (width >= 40rem) {
      padding-bottom: calc(var(--spacing) * 24);
    }
  }
  .sm\:text-2xl {
    @media (width >= 40rem) {
      font-size: var(--text-2xl);
      line-height: var(--tw-leading, var(--text-2xl--line-height));
    }
  }
  .sm\:text-3xl {
    @media (width >= 40rem) {
      font-size: var(--text-3xl);
      line-height: var(--tw-leading, var(--text-3xl--line-height));
    }
  }
  .sm\:text-4xl {
    @media (width >= 40rem) {
      font-size: var(--text-4xl);
      line-height: var(--tw-leading, var(--text-4xl--line-height));
    }
  }
  .sm\:text-5xl {
    @media (width >= 40rem) {
      font-size: var(--text-5xl);
      line-height: var(--tw-leading, var(--text-5xl--line-height));
    }
  }
  .sm\:text-6xl {
    @media (width >= 40rem) {
      font-size: var(--text-6xl);
      line-height: var(--tw-leading, var(--text-6xl--line-height));
    }
  }
  .sm\:text-lg {
    @media (width >= 40rem) {
      font-size: var(--text-lg);
      line-height: var(--tw-leading, var(--text-lg--line-height));
    }
  }
  .md\:grid-cols-2 {
    @media (width >= 48rem) {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  .md\:grid-cols-3 {
    @media (width >= 48rem) {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  .lg\:sticky {
    @media (width >= 64rem) {
      position: sticky;
    }
  }
  .lg\:top-28 {
    @media (width >= 64rem) {
      top: calc(var(--spacing) * 28);
    }
  }
  .lg\:col-span-2 {
    @media (width >= 64rem) {
      grid-column: span 2 / span 2;
    }
  }
  .lg\:col-span-4 {
    @media (width >= 64rem) {
      grid-column: span 4 / span 4;
    }
  }
  .lg\:col-span-8 {
    @media (width >= 64rem) {
      grid-column: span 8 / span 8;
    }
  }
  .lg\:flex {
    @media (width >= 64rem) {
      display: flex;
    }
  }
  .lg\:hidden {
    @media (width >= 64rem) {
      display: none;
    }
  }
  .lg\:min-h-screen {
    @media (width >= 64rem) {
      min-height: 100vh;
    }
  }
  .lg\:grid-cols-2 {
    @media (width >= 64rem) {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  .lg\:grid-cols-3 {
    @media (width >= 64rem) {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  .lg\:grid-cols-4 {
    @media (width >= 64rem) {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
  }
  .lg\:grid-cols-12 {
    @media (width >= 64rem) {
      grid-template-columns: repeat(12, minmax(0, 1fr));
    }
  }
  .lg\:items-center {
    @media (width >= 64rem) {
      align-items: center;
    }
  }
  .lg\:items-end {
    @media (width >= 64rem) {
      align-items: flex-end;
    }
  }
  .lg\:items-start {
    @media (width >= 64rem) {
      align-items: flex-start;
    }
  }
  .lg\:gap-16 {
    @media (width >= 64rem) {
      gap: calc(var(--spacing) * 16);
    }
  }
  .lg\:justify-self-end {
    @media (width >= 64rem) {
      justify-self: flex-end;
    }
  }
  .lg\:p-12 {
    @media (width >= 64rem) {
      padding: calc(var(--spacing) * 12);
    }
  }
  .lg\:px-5 {
    @media (width >= 64rem) {
      padding-inline: calc(var(--spacing) * 5);
    }
  }
  .lg\:px-7 {
    @media (width >= 64rem) {
      padding-inline: calc(var(--spacing) * 7);
    }
  }
  .lg\:px-8 {
    @media (width >= 64rem) {
      padding-inline: calc(var(--spacing) * 8);
    }
  }
  .lg\:py-28 {
    @media (width >= 64rem) {
      padding-block: calc(var(--spacing) * 28);
    }
  }
  .lg\:text-6xl {
    @media (width >= 64rem) {
      font-size: var(--text-6xl);
      line-height: var(--tw-leading, var(--text-6xl--line-height));
    }
  }
  .lg\:text-7xl {
    @media (width >= 64rem) {
      font-size: var(--text-7xl);
      line-height: var(--tw-leading, var(--text-7xl--line-height));
    }
  }
  .xl\:text-8xl {
    @media (width >= 80rem) {
      font-size: var(--text-8xl);
      line-height: var(--tw-leading, var(--text-8xl--line-height));
    }
  }
}
@property --tw-translate-x {
  syntax: "*";
  inherits: false;
  initial-value: 0;
}
@property --tw-translate-y {
  syntax: "*";
  inherits: false;
  initial-value: 0;
}
@property --tw-translate-z {
  syntax: "*";
  inherits: false;
  initial-value: 0;
}
@property --tw-space-y-reverse {
  syntax: "*";
  inherits: false;
  initial-value: 0;
}
@property --tw-border-style {
  syntax: "*";
  inherits: false;
  initial-value: solid;
}
@property --tw-leading {
  syntax: "*";
  inherits: false;
}
@property --tw-font-weight {
  syntax: "*";
  inherits: false;
}
@property --tw-tracking {
  syntax: "*";
  inherits: false;
}
@property --tw-backdrop-blur {
  syntax: "*";
  inherits: false;
}
@property --tw-backdrop-brightness {
  syntax: "*";
  inherits: false;
}
@property --tw-backdrop-contrast {
  syntax: "*";
  inherits: false;
}
@property --tw-backdrop-grayscale {
  syntax: "*";
  inherits: false;
}
@property --tw-backdrop-hue-rotate {
  syntax: "*";
  inherits: false;
}
@property --tw-backdrop-invert {
  syntax: "*";
  inherits: false;
}
@property --tw-backdrop-opacity {
  syntax: "*";
  inherits: false;
}
@property --tw-backdrop-saturate {
  syntax: "*";
  inherits: false;
}
@property --tw-backdrop-sepia {
  syntax: "*";
  inherits: false;
}
@property --tw-ease {
  syntax: "*";
  inherits: false;
}
@layer properties {
  @supports ((-webkit-hyphens: none) and (not (margin-trim: inline))) or ((-moz-orient: inline) and (not (color:rgb(from red r g b)))) {
    *, ::before, ::after, ::backdrop {
      --tw-translate-x: 0;
      --tw-translate-y: 0;
      --tw-translate-z: 0;
      --tw-space-y-reverse: 0;
      --tw-border-style: solid;
      --tw-leading: initial;
      --tw-font-weight: initial;
      --tw-tracking: initial;
      --tw-backdrop-blur: initial;
      --tw-backdrop-brightness: initial;
      --tw-backdrop-contrast: initial;
      --tw-backdrop-grayscale: initial;
      --tw-backdrop-hue-rotate: initial;
      --tw-backdrop-invert: initial;
      --tw-backdrop-opacity: initial;
      --tw-backdrop-saturate: initial;
      --tw-backdrop-sepia: initial;
      --tw-ease: initial;
    }
  }
}



    :root {
      --pine-950:rgb(var(--c-pine-950));
      --pine-900:rgb(var(--c-pine-900));
      --pine-800:rgb(var(--c-pine-800));
      --pine-700:rgb(var(--c-pine-700));
      --acid:rgb(var(--c-primary));
      --ember:#f27b4a;
      --cream:rgb(var(--c-canvas));
      --oat:rgb(var(--c-surface));
      --moss:#b7c9a3;
      --sky:#b8dce0;
      --ink:rgb(var(--c-fg));
      --line:rgb(var(--c-pine-900) / .13);
    }

    * { box-sizing:border-box; }
    html { background:var(--cream); }
    body {
      margin:0;
      overflow-x:hidden;
      background:var(--cream);
      color:var(--ink);
      font-family:var(--f-body);
      text-rendering:optimizeLegibility;
    }
    [x-cloak] { display:none!important; }
    button,a { -webkit-tap-highlight-color:transparent; }
    button:focus-visible,a:focus-visible,input:focus-visible { outline:3px solid rgb(var(--c-primary) / .75); outline-offset:3px; }
    ::selection { background:var(--acid);color:var(--pine-950); }
    .font-display { font-family:var(--f-display); }
    /* Tailwind is compiled locally with v4; these compatibility rules preserve the
       alpha utilities used by the approved Nordvale visual system. */
    .bg-white.bg-opacity-5 { background-color:rgba(255,255,255,.05)!important; }
    .bg-white.bg-opacity-10 { background-color:rgba(255,255,255,.10)!important; }
    .bg-white.bg-opacity-20 { background-color:rgba(255,255,255,.20)!important; }
    .bg-white.bg-opacity-95 { background-color:rgba(255,255,255,.95)!important; }
    .text-white.text-opacity-30 { color:rgba(255,255,255,.30)!important; }
    .text-white.text-opacity-35 { color:rgba(255,255,255,.35)!important; }
    .text-white.text-opacity-40 { color:rgba(255,255,255,.40)!important; }
    .text-white.text-opacity-45 { color:rgba(255,255,255,.45)!important; }
    .text-white.text-opacity-50 { color:rgba(255,255,255,.50)!important; }
    .text-white.text-opacity-60 { color:rgba(255,255,255,.60)!important; }
    .border-white.border-opacity-10 { border-color:rgba(255,255,255,.10)!important; }
    .border-white.border-opacity-20 { border-color:rgba(255,255,255,.20)!important; }
    .hover\:bg-white.hover\:bg-opacity-10:hover { background-color:rgba(255,255,255,.10)!important; }
    .page-shell { width:min(1480px,100%);margin-inline:auto;padding-inline:clamp(16px,3.2vw,42px); }
    .safe-top { padding-top:max(12px,env(safe-area-inset-top)); }
    .safe-bottom { padding-bottom:max(18px,env(safe-area-inset-bottom)); }

    .nav-shell {
      background:rgb(var(--c-pine-950) / .82);
      border:1px solid rgba(255,255,255,.12);
      box-shadow:0 22px 70px -42px rgba(0,0,0,.88);
      backdrop-filter:blur(22px) saturate(135%);
      -webkit-backdrop-filter:blur(22px) saturate(135%);
    }
    .topo-dark {
      background-color:var(--pine-950);
      background-image:
        radial-gradient(circle at 12% 18%,rgb(var(--c-primary) / .15),transparent 18%),
        radial-gradient(circle at 86% 10%,rgba(242,123,74,.12),transparent 18%),
        url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23fff' stroke-opacity='.055' stroke-width='1.15'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3C/g%3E%3C/svg%3E");
      background-size:auto,auto,820px 820px;
    }
    .paper-grid {
      background-image:linear-gradient(rgb(var(--c-pine-900) / .045) 1px,transparent 1px),linear-gradient(90deg,rgb(var(--c-pine-900) / .045) 1px,transparent 1px);
      background-size:30px 30px;
    }
    .grain:after {
      content:'';position:absolute;inset:0;z-index:2;pointer-events:none;opacity:.075;mix-blend-mode:soft-light;
      background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.58'/%3E%3C/svg%3E");
    }
    .outline-word { color:transparent;-webkit-text-stroke:1px rgba(255,255,255,.46); }
    .route-line { fill:none;stroke:var(--acid);stroke-width:2.4;stroke-linecap:round;stroke-dasharray:9 13;animation:route 15s linear infinite;filter:drop-shadow(0 0 8px rgb(var(--c-primary) / .35)); }
    @keyframes route { to { stroke-dashoffset:-260; } }

    .hero-visual { min-height:530px; }
    .ticket-sculpture { position:absolute;border-radius:30px;box-shadow:0 38px 90px -48px rgba(0,0,0,.92);transform-style:preserve-3d; }
    .ticket-sculpture:before,.ticket-sculpture:after { content:'';position:absolute;top:50%;width:28px;height:28px;border-radius:50%;background:var(--pine-950);transform:translateY(-50%); }
    .ticket-sculpture:before { left:-14px; }
    .ticket-sculpture:after { right:-14px; }
    .ticket-dash { border-top:1px dashed rgb(var(--c-pine-900) / .27); }
    .ticket-dash-light { border-top:1px dashed rgba(255,255,255,.28); }
    .float-one { animation:floatOne 6s ease-in-out infinite; }
    .float-two { animation:floatTwo 7.5s ease-in-out infinite; }
    @keyframes floatOne { 50% { transform:translateY(-9px) rotate(-7deg); } }
    @keyframes floatTwo { 50% { transform:translateY(9px) rotate(6deg); } }

    .trust-strip { border-top:1px solid rgba(255,255,255,.1);border-bottom:1px solid rgba(255,255,255,.1); }
    .trust-item + .trust-item { border-left:1px solid rgba(255,255,255,.1); }

    .choice-card {
      position:relative;overflow:hidden;border:1px solid var(--line);background:rgb(var(--c-canvas) / .92);
      box-shadow:0 24px 62px -48px rgb(var(--c-pine-950) / .62);transition:transform .3s ease,border-color .3s ease,box-shadow .3s ease;
    }
    .choice-card:hover { transform:translateY(-5px);border-color:rgb(var(--c-pine-900) / .3);box-shadow:0 38px 80px -50px rgb(var(--c-pine-950) / .72); }
    .choice-card.selected { border-color:var(--pine-900);box-shadow:0 0 0 2px var(--pine-900),0 36px 80px -52px rgb(var(--c-pine-950) / .82); }
    .choice-card:before,.choice-card:after { content:'';position:absolute;top:168px;width:26px;height:26px;border-radius:50%;background:var(--oat);z-index:4; }
    .choice-card:before { left:-14px; }.choice-card:after { right:-14px; }
    .choice-perf { position:absolute;left:20px;right:20px;top:181px;border-top:1px dashed rgb(var(--c-pine-900) / .2); }
    .choice-visual { min-height:182px; }
    .choice-icon { transition:transform .35s cubic-bezier(.2,.8,.2,1); }
    .choice-card:hover .choice-icon { transform:rotate(8deg) scale(1.07); }
    .choice-description { min-height:52px; }

    .audience-chip { border:1px solid var(--line);background:white;transition:.22s ease; }
    .audience-chip:hover { transform:translateY(-2px);border-color:rgb(var(--c-pine-900) / .3); }
    .audience-chip.active { background:var(--pine-900);border-color:var(--pine-900);color:white;box-shadow:0 16px 38px -28px rgb(var(--c-pine-950) / .9); }
    .audience-chip.active .audience-badge { background:var(--acid);color:var(--pine-950); }

    .step-card { border:1px solid var(--line);background:rgba(255,255,255,.84); }
    .step-card.active { border-color:var(--pine-900);box-shadow:0 18px 60px -46px rgb(var(--c-pine-950) / .65); }
    .step-index { background:var(--oat);color:var(--pine-900); }
    .step-card.active .step-index { background:var(--acid);color:var(--pine-950); }
    .qty-control { display:flex;align-items:center;gap:10px; }
    .qty-control button { width:38px;height:38px;border-radius:999px;border:1px solid var(--line);display:grid;place-items:center;background:white;font-size:20px;line-height:1;transition:.2s ease; }
    .qty-control button:hover { background:var(--pine-900);color:white;border-color:var(--pine-900); }
    .addon-card { border:1px solid var(--line);background:white;transition:.22s ease; }
    .addon-card:hover { transform:translateY(-2px);border-color:rgb(var(--c-pine-900) / .3); }
    .addon-card.active { border-color:var(--pine-900);background:rgb(var(--c-primary) / .23);box-shadow:0 15px 45px -36px rgb(var(--c-pine-950) / .7); }
    .summary-panel { box-shadow:0 34px 90px -55px rgb(var(--c-pine-950) / .8); }

    .intent-card { border:1px solid var(--line);background:white;transition:.25s ease; }
    .intent-card:hover { transform:translateY(-3px);border-color:rgb(var(--c-pine-900) / .3); }
    .gift-panel { position:relative;overflow:hidden;box-shadow:0 38px 100px -58px rgb(var(--c-pine-950) / .82); }
    .gift-panel:before { content:'';position:absolute;inset:-55%;background:conic-gradient(from 90deg,transparent,rgb(var(--c-primary) / .18),transparent 33%,rgba(184,220,224,.16),transparent 65%);animation:spin 22s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }

    .faq-item { border-top:1px solid var(--line); }
    .faq-answer { display:grid;grid-template-rows:0fr;transition:grid-template-rows .3s ease; }
    .faq-answer>div { overflow:hidden; }
    .faq-item.open .faq-answer { grid-template-rows:1fr; }
    .faq-plus { transition:transform .25s ease; }
    .faq-item.open .faq-plus { transform:rotate(45deg); }

    .drawer-backdrop { background:rgb(var(--c-pine-950) / .65);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px); }
    .drawer-panel { box-shadow:-52px 0 120px -64px rgb(var(--c-pine-950) / .9); }
    .toast { box-shadow:0 24px 70px -34px rgb(var(--c-pine-950) / .9); }

    .mobile-summary { display:none; }
    .mobile-scroll { scrollbar-width:none; }
    .mobile-scroll::-webkit-scrollbar { display:none; }
    .reveal { opacity:0;transform:translateY(24px);transition:opacity .7s ease,transform .7s cubic-bezier(.2,.75,.2,1); }
    .reveal.is-visible { opacity:1;transform:none; }

    @media (max-width:1023px) {
      .hero-visual { min-height:500px;max-width:680px;margin-inline:auto; }
      .summary-panel { position:static!important; }
    }
    @media (max-width:767px) {
      body { padding-bottom:86px; }
      .hero-visual { min-height:auto;display:grid;gap:16px; }
      .ticket-sculpture { position:relative!important;inset:auto!important;width:100%!important;transform:none!important;animation:none!important;border-radius:24px; }
      .ticket-sculpture:before,.ticket-sculpture:after { width:20px;height:20px; }
      .ticket-sculpture:before { left:-10px; }.ticket-sculpture:after { right:-10px; }
      .hero-visual .ticket-note { position:relative!important;inset:auto!important;width:100%!important; }
      .trust-strip { overflow-x:auto;scroll-snap-type:x mandatory; }
      .trust-item { min-width:220px;scroll-snap-align:start; }
      .trust-item + .trust-item { border-left:0;border-top:0; }
      .choice-card:before,.choice-card:after { top:154px; }
      .choice-perf { top:167px; }
      .choice-visual { min-height:168px; }
      .choice-description { min-height:0; }
      .mobile-summary { display:block; }
      .desktop-builder-cta { display:none; }
      .gift-ticket { transform:none!important; }
    }
    @media (max-width:390px) {
      .page-shell { padding-inline:14px; }
      .nav-subtitle { display:none; }
      .nav-shell { padding-left:10px!important;padding-right:10px!important; }
      .nav-cta { padding-left:13px!important;padding-right:13px!important;font-size:13px!important; }
      .choice-card { border-radius:22px!important; }
      .qty-control { gap:7px; }.qty-control button { width:34px;height:34px; }
      .hero-title { font-size:46px!important; }
    }
    @media (prefers-reduced-motion:reduce) {
      *,*:before,*:after { animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important; }
      .reveal { opacity:1!important;transform:none!important; }
    }
  
CSS;
$pageExtraHead = <<<'HTML'

HTML;
include ts_layout('head');
include ts_layout('header');
?>
  <div id="progress" class="fixed left-0 top-0 z-50 h-1 w-full origin-left" style="background:var(--acid);transform:scaleX(0)"></div>

  

  <main>
    <section class="topo-dark grain relative overflow-hidden pb-16 pt-32 text-white sm:pb-24 sm:pt-40 lg:min-h-screen lg:flex lg:items-center">
      <svg viewBox="0 0 1000 520" class="pointer-events-none absolute bottom-0 left-0 w-full opacity-30" fill="none" aria-hidden="true"><path class="route-line" d="M-30 434C130 292 226 442 374 274S627 110 795 207 909 150 1038 42"/><circle cx="374" cy="274" r="9" fill="rgb(var(--c-primary))"/><circle cx="795" cy="207" r="7" fill="#F27B4A"/></svg>
      <div class="page-shell relative z-10">
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center lg:gap-16">
          <div class="reveal">
            <div class="inline-flex items-center gap-2 rounded-full border border-white border-opacity-10 bg-white bg-opacity-5 px-3 py-2 text-xs font-bold uppercase tracking-widest text-white text-opacity-60"><span class="h-2 w-2 rounded-full" style="background:var(--acid)"></span> Bilete online · confirmare pe loc</div>
            <h1 class="hero-title mt-6 max-w-3xl font-display text-5xl font-semibold leading-none tracking-tight sm:text-6xl lg:text-7xl xl:text-8xl">Alege ce vrei<br><span class="outline-word italic">să simți.</span></h1>
            <p class="mt-6 max-w-xl text-base leading-7 text-white text-opacity-60 sm:text-lg">Alege biletul de acces, adaugă ce vrei să faci în parc și rezervă ziua. Totul într-un singur coș, cu biletele direct pe email.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
              <a href="#pachete" class="inline-flex items-center justify-center gap-2 rounded-full px-6 py-4 font-bold" style="background:var(--acid);color:var(--pine-950)">Descoperă pachetele <span aria-hidden="true">↓</span></a>
              <button type="button" @click="scrollBuilder()" class="inline-flex items-center justify-center rounded-full border border-white border-opacity-20 bg-white bg-opacity-5 px-6 py-4 font-bold">Construiește ziua</button>
            </div>
          </div>

          <div class="hero-visual relative reveal">
            <?php foreach ($pkgHero as $hi => $hp): ?>
            <article class="ticket-sculpture <?= $hi === 0 ? 'float-one left-0 top-8' : 'float-two right-0 top-48 text-white' ?> w-11/12 p-5 sm:w-4/5 sm:p-7" style="<?= $hi === 0 ? 'background:var(--acid);color:var(--pine-950);transform:rotate(-7deg)' : 'background:var(--ember);transform:rotate(6deg)' ?>">
              <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest opacity-60"><?= e($hp['kicker']) ?></p><h2 class="mt-2 font-display text-3xl font-semibold sm:text-4xl"><?= e($hp['name']) ?></h2></div><span class="grid h-12 w-12 flex-none place-items-center rounded-full text-xl" style="background:<?= e($hp['iconBg']) ?>"><?= e($hp['icon']) ?></span></div>
              <div class="<?= $hi === 0 ? 'ticket-dash' : 'ticket-dash-light' ?> mt-7 flex items-end justify-between gap-4 pt-5"><div><p class="text-xs opacity-60"><?= e($hp['bestFor']) ?></p><p class="mt-1 font-semibold"><?= e($hp['short']) ?></p></div><div class="text-right"><p class="font-display text-4xl font-semibold"><?= e(rtrim(rtrim(number_format($hp['price'], 2, ',', '.'), '0'), ',')) ?></p><p class="text-xs opacity-60">lei / bilet</p></div></div>
            </article>
            <?php endforeach; ?>
            <div class="ticket-note absolute bottom-5 left-10 max-w-xs rounded-2xl border border-white border-opacity-10 bg-white bg-opacity-10 p-4 backdrop-filter sm:left-20">
              <p class="text-xs text-white text-opacity-45">Nu știi ce să alegi?</p><p class="mt-1 text-sm font-semibold">Configuratorul de mai jos îți arată totalul pe loc, cu locurile reale ale zilei.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="trust-strip text-white" style="background:var(--pine-900)">
      <div class="page-shell mobile-scroll flex overflow-x-auto">
        <div class="trust-item flex flex-1 items-center gap-3 px-5 py-5 sm:px-7"><span class="grid h-9 w-9 flex-none place-items-center rounded-full" style="background:rgb(var(--c-primary) / .12);color:var(--acid)">↺</span><div><p class="text-sm font-semibold">Locuri în timp real</p><p class="mt-1 text-xs text-white text-opacity-40">Vezi pe loc ce zile mai sunt libere</p></div></div>
        <div class="trust-item flex flex-1 items-center gap-3 px-5 py-5 sm:px-7"><span class="grid h-9 w-9 flex-none place-items-center rounded-full" style="background:rgb(var(--c-primary) / .12);color:var(--acid)">✓</span><div><p class="text-sm font-semibold">Plată securizată</p><p class="mt-1 text-xs text-white text-opacity-40">Cu cardul, online</p></div></div>
        <div class="trust-item flex flex-1 items-center gap-3 px-5 py-5 sm:px-7"><span class="grid h-9 w-9 flex-none place-items-center rounded-full" style="background:rgb(var(--c-primary) / .12);color:var(--acid)">✦</span><div><p class="text-sm font-semibold">Confirmare instant</p><p class="mt-1 text-xs text-white text-opacity-40">Biletele ajung direct pe email</p></div></div>
      </div>
    </div>

    <section id="pachete" class="paper-grid py-16 sm:py-20 lg:py-28">
      <div class="page-shell">
        <div class="reveal grid gap-8 lg:grid-cols-2 lg:items-end">
          <div><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--pine-700)">Biletele de acces</p><h2 class="mt-3 font-display text-4xl font-semibold leading-none tracking-tight sm:text-5xl lg:text-6xl">Mai clar decât<br><span class="italic" style="color:var(--ember)">o listă de tarife.</span></h2></div>
          <p class="max-w-xl text-base leading-7 text-gray-600 lg:justify-self-end">Fiecare bilet e valabil toată ziua aleasă. Activitățile și închirierile le adaugi mai jos, pentru aceeași zi.</p>
        </div>

        <div class="mt-10 grid gap-5 lg:grid-cols-3">
          <template x-for="pkg in packages" :key="pkg.id">
            <article @click="selectPackage(pkg.id)" :class="selectedPackage===pkg.id?'selected':''" class="choice-card cursor-pointer rounded-3xl" tabindex="0" @keydown.enter="selectPackage(pkg.id)">
              <div class="choice-visual relative overflow-hidden p-6 sm:p-7" :style="'background:'+pkg.color+';color:'+pkg.textColor">
                <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest opacity-55" x-text="pkg.kicker"></p><h3 class="mt-2 font-display text-3xl font-semibold" x-text="pkg.name"></h3></div><span class="choice-icon grid h-12 w-12 flex-none place-items-center rounded-full text-xl" :style="'background:'+pkg.iconBg" x-text="pkg.icon"></span></div>
                <div class="mt-8 flex items-end justify-between gap-4"><p class="max-w-xs text-sm leading-6 opacity-70" x-text="pkg.short"></p><div class="text-right"><p class="font-display text-4xl font-semibold" x-text="pkg.price"></p><p class="text-xs opacity-55" x-text="pkg.unit"></p></div></div>
              </div>
              <div class="choice-perf"></div>
              <div class="px-6 pb-6 pt-8 sm:px-7 sm:pb-7">
                <p class="choice-description text-sm leading-6 text-gray-600" x-text="pkg.description"></p>
                <ul class="mt-5 space-y-3 text-sm"><template x-for="item in pkg.includes" :key="item"><li class="flex items-start gap-3"><span class="mt-1 grid h-5 w-5 flex-none place-items-center rounded-full text-xs font-bold" style="background:var(--acid);color:var(--pine-950)">✓</span><span x-text="item"></span></li></template></ul>
                <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-5"><span class="text-xs font-bold uppercase tracking-widest text-gray-400" x-text="pkg.bestFor"></span><button type="button" @click.stop="chooseAndBook(pkg.id)" class="rounded-full px-4 py-2 text-sm font-bold" :style="selectedPackage===pkg.id?'background:var(--pine-900);color:white':'background:var(--oat);color:var(--pine-900)'">Alege</button></div>
              </div>
            </article>
          </template>
        </div>
      </div>
    </section>

    <section id="builder" class="bg-white py-16 sm:py-20 lg:py-28">
      <div class="page-shell">
        <div class="reveal grid gap-8 lg:grid-cols-2 lg:items-end">
          <div><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--pine-700)">Construiește ziua</p><h2 class="mt-3 font-display text-4xl font-semibold leading-none tracking-tight sm:text-5xl lg:text-6xl">Tu alegi oamenii.<br><span class="italic" style="color:var(--ember)">Noi calculăm restul.</span></h2></div>
          <p class="max-w-xl text-base leading-7 text-gray-600 lg:justify-self-end">Alegi biletele, ziua și ce vrei să mai faci în parc. Locurile și prețurile sunt cele reale ale zilei alese.</p>
        </div>

        <div class="mt-10 grid gap-7 lg:grid-cols-12 lg:items-start">
          <div class="space-y-5 lg:col-span-8">
            <article class="step-card active rounded-3xl p-5 sm:p-7">
              <div class="flex items-start gap-4"><span class="step-index grid h-10 w-10 flex-none place-items-center rounded-full font-bold">1</span><div class="min-w-0 flex-1"><p class="text-xs font-bold uppercase tracking-widest text-gray-400">Cine vine?</p><h3 class="mt-1 font-display text-2xl font-semibold sm:text-3xl">Biletele de acces</h3></div></div>
              <div class="mobile-scroll mt-6 flex gap-2 overflow-x-auto pb-2">
                <template x-for="item in audiences" :key="item.id"><button type="button" @click="setAudience(item.id)" :class="audience===item.id?'active':''" class="audience-chip flex min-w-max items-center gap-3 rounded-full px-4 py-3 text-sm font-semibold"><span class="audience-badge grid h-7 w-7 place-items-center rounded-full" style="background:var(--oat)" x-text="item.icon"></span><span x-text="item.label"></span></button></template>
              </div>
              <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <template x-for="pkg in packages" :key="pkg.id">
                  <div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4" :style="qty[pkg.id] > 0 ? 'border-color:var(--pine-900)' : ''"><div class="min-w-0"><p class="font-semibold" x-text="pkg.name"></p><p class="mt-1 text-xs" :class="soldOut(pkg) ? 'font-semibold' : 'text-gray-400'" :style="soldOut(pkg) ? 'color:var(--ember)' : ''" x-text="soldOut(pkg) ? 'complet în ziua aleasă' : lvLei(priceFor(pkg) * 100) + ' / bilet'"></p></div><div class="qty-control flex-none"><button type="button" @click="setQty(pkg.id, -1)" :aria-label="'Scade ' + pkg.name">−</button><strong class="w-6 text-center" x-text="qty[pkg.id]"></strong><button type="button" @click="setQty(pkg.id, 1)" :disabled="soldOut(pkg)" class="disabled:opacity-30" :aria-label="'Adaugă ' + pkg.name">+</button></div></div>
                </template>
              </div>
              <p x-show="!packages.length" class="mt-6 text-sm text-gray-500">Biletele online nu sunt încă puse în vânzare.</p>
            </article>

            <article class="step-card rounded-3xl p-5 sm:p-7" x-data="{ grid: false }">
              <div class="flex items-start justify-between gap-4"><div class="flex items-start gap-4"><span class="step-index grid h-10 w-10 flex-none place-items-center rounded-full font-bold">2</span><div><p class="text-xs font-bold uppercase tracking-widest text-gray-400">Când veniți?</p><h3 class="mt-1 font-display text-2xl font-semibold sm:text-3xl">Ziua vizitei</h3></div></div><button type="button" @click="grid=!grid" class="mt-2 text-xs font-bold underline-offset-4 hover:underline" style="color:var(--pine-700)" x-text="grid ? 'Zilele următoare' : 'Tot calendarul'"></button></div>
              <div x-show="lvLoadingMonth && !lvStrip.length" class="mt-6 grid grid-cols-4 gap-2 sm:grid-cols-7" aria-hidden="true"><template x-for="i in 7" :key="i"><div class="h-[74px] animate-pulse rounded-2xl bg-gray-100"></div></template></div>
              <p x-show="lvNoDates" class="mt-6 text-sm text-gray-500">Nu sunt zile cu locuri în următoarele două luni.</p>
              <div x-show="!grid" class="mobile-scroll mt-6 flex gap-2 overflow-x-auto pb-2">
                <template x-for="d in lvStrip" :key="d.date"><button type="button" @click="pickDay(d.date)" class="min-w-[74px] flex-none rounded-2xl border border-gray-200 p-3 text-center transition" :style="lvDate===d.date?'background:var(--pine-900);border-color:var(--pine-900);color:#fff':''"><span class="block text-xs font-bold uppercase tracking-widest opacity-50" x-text="d.day"></span><strong class="mt-1 block font-display text-2xl" x-text="d.number"></strong><span class="mt-1 block h-3 text-[10px] font-semibold" :style="d.limited?'color:var(--ember)':'opacity:.5'" x-text="d.limited ? 'puține locuri' : d.label"></span></button></template>
              </div>
              <div x-show="grid" x-cloak class="mt-6 max-w-md rounded-2xl border border-gray-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between"><button type="button" @click="lvShiftMonth(-1)" :disabled="!lvCanPrev" class="grid h-8 w-8 place-items-center rounded-full border border-gray-200 disabled:opacity-30" aria-label="Luna anterioară">‹</button><strong class="text-sm capitalize" x-text="lvMonthLabel"></strong><button type="button" @click="lvShiftMonth(1)" class="grid h-8 w-8 place-items-center rounded-full border border-gray-200" aria-label="Luna următoare">›</button></div>
                <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase text-gray-400"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span></div>
                <div class="mt-1 grid grid-cols-7 gap-1" :class="lvLoadingMonth ? 'opacity-50' : ''"><template x-for="c in lvCells" :key="c.key"><button type="button" @click="c.open && (pickDay(c.date), grid=false)" :disabled="c.empty || !c.open" class="aspect-square rounded-lg text-xs font-semibold" :class="c.empty ? 'invisible' : (c.open ? '' : 'text-gray-300 line-through')" :style="lvDate===c.date ? 'background:var(--pine-900);color:var(--acid)' : (c.open ? (c.status==='limited' ? 'background:rgb(242 123 74 / .18)' : 'background:rgb(var(--c-primary) / .45)') : '')" x-text="c.empty ? '' : c.day"></button></template></div>
              </div>
              <p x-show="dayError" x-text="dayError" class="mt-4 text-sm font-semibold" style="color:var(--ember)"></p>
            </article>

            <article class="step-card rounded-3xl p-5 sm:p-7" x-show="extras.length">
              <div class="flex items-start gap-4"><span class="step-index grid h-10 w-10 flex-none place-items-center rounded-full font-bold">3</span><div><p class="text-xs font-bold uppercase tracking-widest text-gray-400">Opțional</p><h3 class="mt-1 font-display text-2xl font-semibold sm:text-3xl">Completează ziua</h3></div></div>
              <div class="mt-6 grid gap-3 sm:grid-cols-2">
                <template x-for="addon in extras" :key="addon.id">
                  <div class="addon-card rounded-2xl p-4 text-left" :class="picked[addon.id] ? 'active' : ''">
                    <button type="button" @click="toggleExtra(addon)" class="block w-full text-left">
                      <div class="flex items-start justify-between gap-4"><span class="grid h-10 w-10 flex-none place-items-center rounded-full text-lg" :style="'background:'+addon.color" x-text="addon.icon"></span><span class="rounded-full px-3 py-1 text-xs font-bold" style="background:var(--oat)" x-text="'+' + lvLei(extraUnit(addon) * 100)"></span></div>
                      <p class="mt-4 font-semibold" x-text="addon.name"></p><p class="mt-1 text-xs leading-5 text-gray-500" x-text="addon.description"></p>
                    </button>
                    <div x-show="picked[addon.id]" x-cloak class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                      <div x-show="addon.variants.length > 1" class="flex flex-wrap gap-2"><template x-for="(v, vi) in addon.variants" :key="vi"><button type="button" @click="picked[addon.id].variant = vi" class="rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold" :style="picked[addon.id] && picked[addon.id].variant===vi ? 'background:var(--pine-900);color:#fff;border-color:var(--pine-900)' : ''" x-text="v.label || (v.duration_minutes + ' min')"></button></template></div>
                      <p x-show="picked[addon.id] && picked[addon.id].loading" class="text-xs text-gray-400">Se încarcă orele…</p>
                      <div x-show="picked[addon.id] && !picked[addon.id].loading && picked[addon.id].timed.length" class="flex flex-wrap gap-2"><template x-for="sl in (picked[addon.id] ? picked[addon.id].timed : [])" :key="sl.id"><button type="button" @click="sl.open && (picked[addon.id].slotId = sl.id)" :disabled="!sl.open" class="rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold disabled:opacity-30" :style="picked[addon.id] && picked[addon.id].slotId===sl.id ? 'background:var(--pine-900);color:#fff;border-color:var(--pine-900)' : ''" x-text="sl.time_slot_start"></button></template></div>
                      <p x-show="picked[addon.id] && !picked[addon.id].loading && picked[addon.id].none" class="text-xs font-semibold" style="color:var(--ember)">Nu mai sunt locuri în ziua aleasă.</p>
                      <div class="flex items-center justify-between"><span class="text-xs text-gray-500">Bucăți / persoane</span><div class="qty-control"><button type="button" @click="picked[addon.id].qty = Math.max(1, picked[addon.id].qty - 1)" aria-label="Scade">−</button><strong class="w-6 text-center" x-text="picked[addon.id] ? picked[addon.id].qty : 0"></strong><button type="button" @click="picked[addon.id].qty = Math.min(10, picked[addon.id].qty + 1)" aria-label="Adaugă">+</button></div></div>
                    </div>
                  </div>
                </template>
              </div>
            </article>
          </div>

          <aside class="summary-panel rounded-3xl p-5 text-white sm:p-7 lg:sticky lg:top-28 lg:col-span-4" style="background:var(--pine-950)">
            <p class="text-xs font-bold uppercase tracking-widest text-white text-opacity-35">Rezumatul tău</p>
            <div class="mt-5"><h3 class="font-display text-3xl font-semibold" x-text="lvDate ? lvDateLabel : 'Alege ziua'"></h3><p class="mt-2 text-sm leading-6 text-white text-opacity-50" x-text="ticketCount ? ticketCount + (ticketCount === 1 ? ' bilet de acces' : ' bilete de acces') : 'Niciun bilet ales încă'"></p></div>
            <div class="mt-6 space-y-3 border-t border-white border-opacity-10 pt-5 text-sm">
              <template x-for="line in summaryLines" :key="line.key"><div class="flex justify-between gap-4"><span class="text-white text-opacity-45" x-text="line.qty + ' × ' + line.name"></span><strong x-text="lvLei(line.total * 100)"></strong></div></template>
              <p x-show="!summaryLines.length" class="text-white text-opacity-40">Adaugă bilete la pasul 1.</p>
            </div>
            <div class="mt-6 flex items-end justify-between border-t border-white border-opacity-10 pt-5"><div><p class="text-xs text-white text-opacity-35">Total estimat</p><p class="mt-1 text-xs text-white text-opacity-35">TVA inclus</p></div><p class="font-display text-4xl font-semibold" style="color:var(--acid)" x-text="lvLei(grandTotal * 100)"></p></div>
            <button type="button" @click="confirmBooking()" :disabled="busy" class="desktop-builder-cta mt-6 w-full rounded-full px-6 py-4 font-bold disabled:opacity-50" style="background:var(--acid);color:var(--pine-950)" x-text="busy ? 'Se verifică locurile…' : 'Continuă către coș'"></button>
            <p x-show="bookError" x-text="bookError" class="mt-3 text-center text-xs font-semibold" style="color:var(--acid)"></p>
            <p class="mt-3 text-center text-xs leading-5 text-white text-opacity-30">Prețul final se confirmă în coș.</p>
          </aside>
        </div>
      </div>
    </section>

    <section class="paper-grid py-16 sm:py-20 lg:py-28">
      <div class="page-shell">
        <div class="reveal text-center"><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--pine-700)">Alege după intenție</p><h2 class="mt-3 font-display text-4xl font-semibold leading-none tracking-tight sm:text-5xl lg:text-6xl">Nu toate zilele bune<br><span class="italic" style="color:var(--ember)">arată la fel.</span></h2></div>
        <div class="mt-10 grid gap-4 md:grid-cols-3">
          <template x-for="intent in intents" :key="intent.title"><article class="intent-card rounded-3xl p-6 sm:p-7"><div class="flex items-start justify-between gap-4"><span class="grid h-12 w-12 place-items-center rounded-full text-xl" :style="'background:'+intent.color" x-text="intent.icon"></span><span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-widest" style="background:var(--oat)" x-text="intent.package"></span></div><h3 class="mt-6 font-display text-3xl font-semibold" x-text="intent.title"></h3><p class="mt-3 text-sm leading-6 text-gray-600" x-text="intent.description"></p><div class="mt-6 border-t border-gray-100 pt-5"><p class="text-xs font-bold uppercase tracking-widest text-gray-400">Include în mod ideal</p><p class="mt-2 text-sm font-semibold" x-text="intent.includes"></p></div></article></template>
        </div>
      </div>
    </section>

    <section class="bg-white py-16 sm:py-20 lg:py-28">
      <div class="page-shell">
        <div class="gift-panel reveal rounded-3xl p-6 text-white sm:p-9 lg:p-12" style="background:var(--pine-800)">
          <div class="relative z-10 grid gap-10 lg:grid-cols-2 lg:items-center">
            <div><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--acid)">Card cadou <?= e(SITE_NAME) ?></p><h2 class="mt-3 font-display text-4xl font-semibold leading-none tracking-tight sm:text-5xl lg:text-6xl">Un cadou bun<br><span class="outline-word italic">nu are nevoie de dată.</span></h2><p class="mt-5 max-w-xl text-base leading-7 text-white text-opacity-60">Oferi o valoare, nu o dată. Cine îl primește alege singur ziua și biletele, iar dacă vrea mai mult, completează diferența.</p><div class="mt-7 flex flex-col gap-3 sm:flex-row"><button type="button" @click="giftOpen=true" class="rounded-full px-6 py-4 font-bold" style="background:var(--acid);color:var(--pine-950)">Configurează voucherul</button><a href="#faq" class="rounded-full border border-white border-opacity-20 px-6 py-4 text-center font-bold">Cum funcționează</a></div></div>
            <article class="gift-ticket ticket-sculpture relative mx-auto w-full max-w-md p-6" style="background:var(--cream);color:var(--pine-950);transform:rotate(3deg)"><div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest opacity-40">Card cadou <?= e(SITE_NAME) ?></p><h3 class="mt-3 font-display text-3xl font-semibold">O zi care încă<br>nu are program.</h3></div><span class="rounded-full border border-current px-3 py-2 text-xs font-bold">cadou</span></div><div class="ticket-dash mt-7 flex items-end justify-between pt-5"><div><p class="text-xs opacity-45">Valoare flexibilă</p><p class="mt-1 font-semibold">de la 100 lei</p></div><svg viewBox="0 0 48 48" class="h-12 w-12" fill="none"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="rgb(var(--c-pine-900))"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#F27B4A"/></svg></div></article>
          </div>
        </div>
      </div>
    </section>

    <section id="faq" class="paper-grid py-16 sm:py-20 lg:py-28">
      <div class="page-shell">
        <div class="reveal grid gap-10 lg:grid-cols-3">
          <div><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--pine-700)">Detalii înainte de plată</p><h2 class="mt-3 font-display text-4xl font-semibold leading-none tracking-tight sm:text-5xl">Întrebări<br><span class="italic" style="color:var(--ember)">importante.</span></h2></div>
          <div class="lg:col-span-2"><template x-for="(item,index) in faqs" :key="item.q"><article :class="openFaq===index?'open':''" class="faq-item"><button type="button" @click="openFaq=openFaq===index?null:index" class="flex w-full items-center justify-between gap-4 py-5 text-left"><span class="font-display text-xl font-semibold sm:text-2xl" x-text="item.q"></span><span class="faq-plus grid h-9 w-9 flex-none place-items-center rounded-full border border-gray-200 text-xl">+</span></button><div class="faq-answer"><div><p class="pb-5 pr-6 text-sm leading-7 text-gray-600 sm:pr-12" x-text="item.a"></p></div></div></article></template></div>
        </div>
      </div>
    </section>
  </main>

  
  <div x-show="giftOpen" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Configurează voucherul cadou">
    <button type="button" @click="giftOpen=false" class="drawer-backdrop absolute inset-0 w-full" aria-label="Închide"></button>
    <div class="drawer-panel safe-bottom absolute inset-x-0 bottom-0 max-h-screen overflow-y-auto rounded-t-3xl p-5 sm:left-auto sm:right-0 sm:top-0 sm:h-full sm:w-full sm:max-w-md sm:rounded-none sm:p-7" style="background:var(--cream)">
      <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest text-gray-400">Card cadou</p><h2 class="mt-2 font-display text-3xl font-semibold">Configurează cadoul</h2></div><button type="button" @click="giftOpen=false" class="grid h-10 w-10 flex-none place-items-center rounded-full border border-gray-200 text-xl" aria-label="Închide">×</button></div>
      <p class="mt-5 text-sm leading-6 text-gray-600">Alege valoarea. Persoana care primește voucherul va selecta ulterior ziua și pachetul.</p>
      <div class="mt-6 grid grid-cols-2 gap-3"><template x-for="value in [100,200,300,500]" :key="value"><button type="button" @click="giftValue=value" class="rounded-2xl border border-gray-200 p-5 text-left" :style="giftValue===value?'background:var(--pine-900);color:white;border-color:var(--pine-900)':''"><span class="block text-xs opacity-50">Voucher</span><span class="mt-1 block font-display text-3xl" x-text="value+' lei'"></span></button></template></div>
      <label class="mt-6 block text-xs font-bold uppercase tracking-widest text-gray-400" for="gift-message">Mesaj pe voucher</label>
      <textarea id="gift-message" x-model="giftMessage" rows="4" class="mt-3 w-full rounded-2xl border border-gray-200 bg-white p-4" placeholder="Să fie o zi de neuitat..."></textarea>
      <button type="button" @click="confirmGift()" class="mt-5 w-full rounded-full px-6 py-4 font-bold" style="background:var(--acid);color:var(--pine-950)">Continuă cu cardul cadou</button>
    </div>
  </div>

  <div class="mobile-summary safe-bottom fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white bg-opacity-95 px-4 pt-3 backdrop-filter" x-show="!giftOpen">
    <div class="mx-auto flex max-w-screen-sm items-center justify-between gap-3"><div class="min-w-0"><p class="truncate text-xs text-gray-400" x-text="(lvDate ? lvDateLabel : 'Alege ziua') + ' · ' + ticketCount + ' bilete'"></p><p class="font-display text-2xl font-semibold" x-text="lvLei(grandTotal * 100)"></p></div><button type="button" @click="ticketCount && lvDate ? confirmBooking() : scrollBuilder()" class="flex-none rounded-full px-5 py-3 text-sm font-bold" style="background:var(--pine-900);color:#fff" x-text="ticketCount && lvDate ? 'Continuă' : 'Configurează'"></button></div>
  </div>
  <div x-show="toast" x-cloak x-transition.opacity class="toast safe-bottom fixed bottom-5 left-1/2 z-50 w-11/12 max-w-md -translate-x-1/2 rounded-2xl px-4 py-3 text-center text-sm font-semibold text-white" style="background:var(--pine-950)" x-text="toastMessage"></div>

  <script>
    function ticketsPage() {
      const packages = <?= json_encode($pkgItems, $jsonFlags) ?>;
      const first = packages[0] || null;
      return LVBooking.with({
        ticketTypeId: first ? first.ticket_type_id : 0,
        name: first ? first.name : '',
        priceCents: first ? Math.round(first.price * 100) : 0,
        variants: [],
        event: <?= json_encode($pkgEventJs, $jsonFlags) ?>
      }, {
        menu:false, giftOpen:false, toast:false, toastMessage:'', openFaq:0,
        audience:'couple', giftValue:200, giftMessage:'', busy:false, bookError:'', dayError:'',
        audiences:[
          {id:'solo',label:'Singur',icon:'●'}, {id:'couple',label:'În doi',icon:'∞'}, {id:'family',label:'Familie',icon:'⌂'}, {id:'group',label:'Grup',icon:'✦'}
        ],
        packages: packages,
        selectedPackage: first ? first.id : null,
        qty: Object.fromEntries(packages.map((p, i) => [p.id, i === 0 ? 2 : 0])),
        dayPrices: {}, dayLoaded: false,                 // ticket_type_id → {capacity_id, price_cents, remaining} pentru ziua aleasă
        extras: <?= json_encode($pkgExtras, $jsonFlags) ?>,
        picked: {},                    // extra_id → {variant, qty, slotId, timed[], loading, none}
        intents: <?= json_encode($pkgIntents, $jsonFlags) ?>,
        faqs:[
          {q:'Trebuie să aleg data când cumpăr?',a:'Da, biletele sunt pentru o zi anume, iar locurile se numără pe fiecare zi. Dacă vrei să oferi o vizită fără dată, alege cardul cadou.'},
          {q:'Cum primesc biletele?',a:'Imediat după plată, pe email, cu câte un cod QR. La intrare le arăți direct de pe telefon.'},
          {q:'Ce se întâmplă dacă vremea e rea?',a:'Parcul rămâne deschis pe ploaie ușoară. Dacă o activitate se suspendă, te anunțăm și îți propunem altă zi sau returnarea banilor pentru ea.'},
          {q:'Pot cumpăra doar o activitate, fără acces?',a:'Activitățile și închirierile se desfășoară în parc, așa că ai nevoie și de un bilet de acces pentru aceeași zi.'}
        ],
        init() {
          this.setupReveal();
          this.setupProgress();
          this.lvInit();
          this.$watch('lvDate', () => this.loadDay());
        },
        setAudience(id) {
          this.audience = id;
          const ids = this.packages.map(p => p.id);
          const fam = this.packages.find(p => /famil/i.test(p.name));
          const kid = this.packages.find(p => /copil|copii|junior|kid/i.test(p.name));
          ids.forEach(k => this.qty[k] = 0);
          if (!ids.length) return;
          if (id === 'solo') this.qty[ids[0]] = 1;
          if (id === 'couple') this.qty[ids[0]] = 2;
          if (id === 'group') this.qty[ids[0]] = 6;
          if (id === 'family') { if (fam) { this.qty[fam.id] = 1; } else { this.qty[ids[0]] = 2; if (kid) this.qty[kid.id] = 2; } }
        },
        soldOut(pkg) { return this.dayLoaded && !this.dayPrices[pkg.ticket_type_id]; },
        setQty(id, d) { this.qty[id] = Math.max(0, Math.min(10, (this.qty[id] || 0) + d)); },
        get ticketCount() { return this.packages.reduce((n, p) => n + (this.qty[p.id] || 0), 0); },
        get selectedPackageData() { return this.packages.find(p => p.id === this.selectedPackage) || this.packages[0] || {}; },
        priceFor(pkg) { const d = this.dayPrices[pkg.ticket_type_id]; return d ? d.price_cents / 100 : pkg.price; },
        extraUnit(x) {
          const p = this.picked[x.id];
          const v = x.variants[p ? p.variant : 0];
          if (v) return v.price_cents / 100;
          const sl = p && p.timed.concat(p.day || []).find(s => s.id === p.slotId);
          return sl ? sl.price_cents / 100 : x.price;
        },
        get summaryLines() {
          const lines = [];
          this.packages.forEach(p => { const q = this.qty[p.id] || 0; if (q) lines.push({ key: 'p' + p.id, name: p.name, qty: q, total: q * this.priceFor(p) }); });
          this.extras.forEach(x => { const p = this.picked[x.id]; if (p) lines.push({ key: 'x' + x.id, name: x.name, qty: p.qty, total: p.qty * this.extraUnit(x) }); });
          return lines;
        },
        get grandTotal() { return this.summaryLines.reduce((n, l) => n + l.total, 0); },
        selectPackage(id) { this.selectedPackage = id; },
        chooseAndBook(id) { this.selectedPackage = id; if (!this.qty[id]) this.qty[id] = 1; this.scrollBuilder(); },
        scrollBuilder() { document.getElementById('builder')?.scrollIntoView({behavior:'smooth',block:'start'}); },
        async slotsFor(ticketTypeId, date) {
          try {
            const r = await (await fetch('/api/proxy.php?action=leisure-slots&ticket_type=' + ticketTypeId + '&date=' + date)).json();
            return (r && r.success && r.data && r.data.slots) ? r.data.slots.map(s => Object.assign({}, s, { open: ['available','limited'].includes(s.status) && s.remaining > 0 })) : [];
          } catch (e) { return []; }
        },
        pickDay(date) { this.lvPickDate(date); },
        // Ziua aleasă: capacitatea fiecărui bilet de acces și orele fiecărui extra bifat
        async loadDay() {
          const date = this.lvDate;
          if (!date) return;
          this.dayError = ''; this.dayLoaded = false;
          const prices = {};
          await Promise.all(this.packages.map(async p => {
            const sl = (await this.slotsFor(p.ticket_type_id, date)).find(s => s.open);
            if (sl) prices[p.ticket_type_id] = sl;
          }));
          if (this.lvDate !== date) return;
          this.dayPrices = prices;
          this.dayLoaded = true;
          // Biletele fără locuri în ziua aleasă ies din calcul
          this.packages.forEach(p => { if (!prices[p.ticket_type_id] && this.qty[p.id]) { this.qty[p.id] = 0; this.dayError = 'Unele bilete nu mai au locuri în ziua aleasă.'; } });
          Object.keys(this.picked).forEach(id => this.loadExtra(this.extras.find(x => x.id == id)));
        },
        async loadExtra(x) {
          const p = this.picked[x.id];
          if (!p || !this.lvDate) return;
          p.loading = true; p.slotId = null; p.timed = []; p.day = []; p.none = false;
          const slots = await this.slotsFor(x.ticket_type_id, this.lvDate);
          p.timed = slots.filter(s => s.time_slot_start);
          p.day = slots.filter(s => !s.time_slot_start);
          const whole = p.day.find(s => s.open);
          if (whole) p.slotId = whole.id;
          p.none = !slots.some(s => s.open);
          p.loading = false;
        },
        toggleExtra(x) {
          if (this.picked[x.id]) { delete this.picked[x.id]; this.picked = Object.assign({}, this.picked); return; }
          this.picked = Object.assign({}, this.picked, { [x.id]: { variant: 0, qty: 1, slotId: null, timed: [], day: [], loading: false, none: false } });
          this.loadExtra(x);
        },
        confirmBooking() {
          this.bookError = '';
          if (!this.ticketCount) { this.bookError = 'Alege cel puțin un bilet de acces.'; this.scrollBuilder(); return; }
          if (!this.lvDate) { this.bookError = 'Alege ziua vizitei.'; this.dayError = 'Alege ziua vizitei.'; this.scrollBuilder(); return; }
          const items = [];
          for (const p of this.packages) {
            const q = this.qty[p.id] || 0;
            if (!q) continue;
            const sl = this.dayPrices[p.ticket_type_id];
            if (!sl) { this.bookError = p.name + ': nu mai sunt locuri în ziua aleasă.'; return; }
            if (sl.remaining < q) { this.bookError = p.name + ': mai sunt doar ' + sl.remaining + ' locuri.'; return; }
            items.push({ ticket_type_id: p.ticket_type_id, name: p.name, price: sl.price_cents / 100, qty: q, visit_date: this.lvDate, slot_time: null, capacity_id: sl.id, duration_minutes: null, when: this.lvDateLabel });
          }
          for (const x of this.extras) {
            const p = this.picked[x.id];
            if (!p) continue;
            const sl = p.timed.concat(p.day).find(s => s.id === p.slotId);
            if (!sl) { this.bookError = x.name + ': alege ora sau scoate-l din listă.'; return; }
            const v = x.variants[p.variant];
            items.push({ ticket_type_id: x.ticket_type_id, name: x.name + (v ? ' · ' + (v.label || v.duration_minutes + ' min') : ''), price: this.extraUnit(x), qty: p.qty, visit_date: this.lvDate, slot_time: sl.time_slot_start || null, capacity_id: sl.id, duration_minutes: v ? v.duration_minutes : null, when: this.lvDateLabel + (sl.time_slot_start ? ' · ' + sl.time_slot_start : '') });
          }
          const prev = TS.Cart.get();
          const same = prev && prev.event && prev.event.id === this.lv.event.id && !(prev.seats || []).length;
          const merged = same ? prev.items.slice() : [];
          items.forEach(it => {
            const k = TS.Cart.lineKey(it);
            const f = merged.find(m => TS.Cart.lineKey(m) === k && m.duration_minutes == it.duration_minutes);
            if (f) f.qty = Math.min(10, f.qty + it.qty); else merged.push(it);
          });
          TS.Cart.set({ event: Object.assign({ date: this.lvDate }, this.lv.event), items: merged, expires_at: same ? prev.expires_at : null, coupon_code: same ? (prev.coupon_code || null) : null });
          window.location.href = '/cos';
        },
        confirmGift() { window.location.href = '/card-cadou?valoare=' + this.giftValue; },
        showToast(message) { this.toastMessage=message;this.toast=true;window.clearTimeout(this.toastTimer);this.toastTimer=window.setTimeout(()=>this.toast=false,2800); },
        setupReveal() {
          const items=[...document.querySelectorAll('.reveal')];
          if (!('IntersectionObserver' in window)) { items.forEach(item=>item.classList.add('is-visible'));return; }
          const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}}),{threshold:.12});
          items.forEach(item=>observer.observe(item));
        },
        setupProgress() {
          const progress=document.getElementById('progress');
          const update=()=>{const max=document.documentElement.scrollHeight-window.innerHeight;const value=max>0?window.scrollY/max:0;progress.style.transform='scaleX('+Math.min(1,Math.max(0,value))+')';};
          update();window.addEventListener('scroll',update,{passive:true});window.addEventListener('resize',update,{passive:true});
        }
      });
    }
  </script>
<?php include ts_layout('footer');
