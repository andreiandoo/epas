<?php
/** Portat din resources/tenant-demos/leisure/calendar.php (Nordvale). */
require_once TEMPLATE_DIR . '/lib.php';
// Produsele reale ale parcului; disponibilitatea vine din /leisure/availability și /leisure/slots
$calColors = ['access' => 'rgb(var(--c-primary))', 'activity' => '#f27b4a', 'rental' => '#b8dce0'];
$calIcons = ['access' => '⌁', 'activity' => '↗', 'rental' => '◒'];
$calItems = [];
$calEvent = null;
foreach (lv_tickets() as $b) {
    $cat = (string) $b['service_category'];
    $calEvent = $calEvent ?? $b['event'] ?? null;
    $calItems[] = [
        'id'             => (int) $b['ticket_type_id'],
        'ticket_type_id' => (int) $b['ticket_type_id'],
        'filter'         => $cat,
        'name'           => (string) $b['name'],
        'icon'           => $calIcons[$cat] ?? '✦',
        'color'          => $calColors[$cat] ?? 'rgb(var(--c-primary))',
        'from'           => ((int) $b['price_cents']) / 100,
        'note'           => lv_category_label($cat) . ' · ' . lv_duration($b),
        'variants'       => array_values(array_filter((array) ($b['duration_variants'] ?? []), fn ($v) => !empty($v['duration_minutes']))),
        'url'            => '/experienta/' . lv_slug($b),
    ];
}
$calFilterDefs = ['access' => ['Acces în parc', '⌁'], 'activity' => ['Activități', '↗'], 'rental' => ['Închirieri', '◒']];
$calFilters = [['id' => 'all', 'label' => 'Toate', 'icon' => '✦']];
foreach ($calFilterDefs as $id => [$label, $icon]) {
    foreach ($calItems as $it) {
        if ($it['filter'] === $id) { $calFilters[] = ['id' => $id, 'label' => $label, 'icon' => $icon]; break; }
    }
}
$calEventJs = [
    'id'     => (int) ($calEvent['id'] ?? 0),
    'slug'   => (string) ($calEvent['slug'] ?? ''),
    'title'  => SITE_NAME,
    'place'  => (string) ($calEvent['venue'] ?? SITE_NAME),
    'poster' => lv_photo(LV_PHOTOS['access'][0], 600),
    'url'    => '/calendar',
];
$calHours = trim((string) site('settings.today_hours', ''));
$calFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;

$nvNav='calendar'; $nvNoSpacer=true;
$pageTitle = 'Calendar și disponibilitate' . ' — ' . SITE_NAME;
$bodyAttrs = 'x-data="calendarPage()" @keydown.escape.window="closeBooking()" class="antialiased"';
$pageExtraStyles = <<<'CSS'
/*! tailwindcss v4.1.10 | MIT License | https://tailwindcss.com */
@layer properties;
@layer theme, base, components, utilities;
@layer theme {
  :root, :host {
    --font-sans: var(--f-body);
    --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono",
      "Courier New", monospace;
    --color-white: #fff;
    --spacing: 0.25rem;
    --container-sm: 24rem;
    --container-md: 28rem;
    --container-xl: 36rem;
    --container-2xl: 42rem;
    --container-3xl: 48rem;
    --container-4xl: 56rem;
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
    --font-weight-semibold: 600;
    --font-weight-bold: 700;
    --leading-tight: 1.25;
    --radius-xl: 0.75rem;
    --radius-2xl: 1rem;
    --blur-xl: 24px;
    --default-transition-duration: 150ms;
    --default-transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    --default-font-family: var(--font-sans);
    --default-mono-font-family: var(--font-mono);
    --font-display: var(--f-display);
    --color-pine-950: rgb(var(--c-pine-950));
    --color-pine-900: rgb(var(--c-pine-900));
    --color-pine-800: rgb(var(--c-pine-800));
    --color-pine-700: rgb(var(--c-pine-700));
    --color-acid: rgb(var(--c-primary));
    --color-ember: #f27b4a;
    --color-cream: rgb(var(--c-canvas));
    --color-oat: rgb(var(--c-surface));
    --color-sky: #b8dce0;
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
  .-top-8 {
    top: calc(var(--spacing) * -8);
  }
  .top-0 {
    top: calc(var(--spacing) * 0);
  }
  .top-5 {
    top: calc(var(--spacing) * 5);
  }
  .top-10 {
    top: calc(var(--spacing) * 10);
  }
  .top-24 {
    top: calc(var(--spacing) * 24);
  }
  .top-28 {
    top: calc(var(--spacing) * 28);
  }
  .-right-16 {
    right: calc(var(--spacing) * -16);
  }
  .-right-20 {
    right: calc(var(--spacing) * -20);
  }
  .-right-24 {
    right: calc(var(--spacing) * -24);
  }
  .right-4 {
    right: calc(var(--spacing) * 4);
  }
  .right-5 {
    right: calc(var(--spacing) * 5);
  }
  .bottom-0 {
    bottom: calc(var(--spacing) * 0);
  }
  .bottom-4 {
    bottom: calc(var(--spacing) * 4);
  }
  .bottom-5 {
    bottom: calc(var(--spacing) * 5);
  }
  .bottom-6 {
    bottom: calc(var(--spacing) * 6);
  }
  .-left-20 {
    left: calc(var(--spacing) * -20);
  }
  .left-0 {
    left: calc(var(--spacing) * 0);
  }
  .left-1\/2 {
    left: calc(1/2 * 100%);
  }
  .left-4 {
    left: calc(var(--spacing) * 4);
  }
  .z-10 {
    z-index: 10;
  }
  .z-\[150\] {
    z-index: 150;
  }
  .z-\[190\] {
    z-index: 190;
  }
  .z-\[210\] {
    z-index: 210;
  }
  .z-\[230\] {
    z-index: 230;
  }
  .mx-auto {
    margin-inline: auto;
  }
  .mt-0\.5 {
    margin-top: calc(var(--spacing) * 0.5);
  }
  .mt-1 {
    margin-top: calc(var(--spacing) * 1);
  }
  .mt-1\.5 {
    margin-top: calc(var(--spacing) * 1.5);
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
  .mt-9 {
    margin-top: calc(var(--spacing) * 9);
  }
  .mb-2 {
    margin-bottom: calc(var(--spacing) * 2);
  }
  .mb-5 {
    margin-bottom: calc(var(--spacing) * 5);
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
  .h-2 {
    height: calc(var(--spacing) * 2);
  }
  .h-4 {
    height: calc(var(--spacing) * 4);
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
  .h-80 {
    height: calc(var(--spacing) * 80);
  }
  .h-96 {
    height: calc(var(--spacing) * 96);
  }
  .h-\[3px\] {
    height: 3px;
  }
  .h-\[330px\] {
    height: 330px;
  }
  .h-full {
    height: 100%;
  }
  .h-px {
    height: 1px;
  }
  .max-h-\[92vh\] {
    max-height: 92vh;
  }
  .min-h-\[190px\] {
    min-height: 190px;
  }
  .min-h-\[520px\] {
    min-height: 520px;
  }
  .w-2 {
    width: calc(var(--spacing) * 2);
  }
  .w-4 {
    width: calc(var(--spacing) * 4);
  }
  .w-5 {
    width: calc(var(--spacing) * 5);
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
  .w-80 {
    width: calc(var(--spacing) * 80);
  }
  .w-96 {
    width: calc(var(--spacing) * 96);
  }
  .w-\[190px\] {
    width: 190px;
  }
  .w-\[550px\] {
    width: 550px;
  }
  .w-\[calc\(100\%-32px\)\] {
    width: calc(100% - 32px);
  }
  .w-full {
    width: 100%;
  }
  .max-w-2xl {
    max-width: var(--container-2xl);
  }
  .max-w-3xl {
    max-width: var(--container-3xl);
  }
  .max-w-4xl {
    max-width: var(--container-4xl);
  }
  .max-w-\[1540px\] {
    max-width: 1540px;
  }
  .max-w-md {
    max-width: var(--container-md);
  }
  .max-w-sm {
    max-width: var(--container-sm);
  }
  .max-w-xl {
    max-width: var(--container-xl);
  }
  .min-w-0 {
    min-width: calc(var(--spacing) * 0);
  }
  .min-w-\[150px\] {
    min-width: 150px;
  }
  .flex-1 {
    flex: 1;
  }
  .flex-none {
    flex: none;
  }
  .-translate-x-1\/2 {
    --tw-translate-x: calc(calc(1/2 * 100%) * -1);
    translate: var(--tw-translate-x) var(--tw-translate-y);
  }
  .rotate-3 {
    rotate: 3deg;
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
  .flex-wrap {
    flex-wrap: wrap;
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
  .gap-1\.5 {
    gap: calc(var(--spacing) * 1.5);
  }
  .gap-2 {
    gap: calc(var(--spacing) * 2);
  }
  .gap-2\.5 {
    gap: calc(var(--spacing) * 2.5);
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
  .gap-6 {
    gap: calc(var(--spacing) * 6);
  }
  .gap-7 {
    gap: calc(var(--spacing) * 7);
  }
  .gap-10 {
    gap: calc(var(--spacing) * 10);
  }
  .space-y-2 {
    :where(& > :not(:last-child)) {
      --tw-space-y-reverse: 0;
      margin-block-start: calc(calc(var(--spacing) * 2) * var(--tw-space-y-reverse));
      margin-block-end: calc(calc(var(--spacing) * 2) * calc(1 - var(--tw-space-y-reverse)));
    }
  }
  .divide-x {
    :where(& > :not(:last-child)) {
      --tw-divide-x-reverse: 0;
      border-inline-style: var(--tw-border-style);
      border-inline-start-width: calc(1px * var(--tw-divide-x-reverse));
      border-inline-end-width: calc(1px * calc(1 - var(--tw-divide-x-reverse)));
    }
  }
  .divide-y {
    :where(& > :not(:last-child)) {
      --tw-divide-y-reverse: 0;
      border-bottom-style: var(--tw-border-style);
      border-top-style: var(--tw-border-style);
      border-top-width: calc(1px * var(--tw-divide-y-reverse));
      border-bottom-width: calc(1px * calc(1 - var(--tw-divide-y-reverse)));
    }
  }
  .divide-pine-900\/10 {
    :where(& > :not(:last-child)) {
      border-color: color-mix(in srgb, rgb(var(--c-pine-900)) 10%, transparent);
      @supports (color: color-mix(in lab, red, red)) {
        border-color: color-mix(in oklab, var(--color-pine-900) 10%, transparent);
      }
    }
  }
  .self-start {
    align-self: flex-start;
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
  .rounded-\[18px\] {
    border-radius: 18px;
  }
  .rounded-\[20px\] {
    border-radius: 20px;
  }
  .rounded-\[22px\] {
    border-radius: 22px;
  }
  .rounded-\[24px\] {
    border-radius: 24px;
  }
  .rounded-\[25px\] {
    border-radius: 25px;
  }
  .rounded-\[26px\] {
    border-radius: 26px;
  }
  .rounded-\[28px\] {
    border-radius: 28px;
  }
  .rounded-\[30px\] {
    border-radius: 30px;
  }
  .rounded-\[34px\] {
    border-radius: 34px;
  }
  .rounded-full {
    border-radius: calc(infinity * 1px);
  }
  .rounded-xl {
    border-radius: var(--radius-xl);
  }
  .rounded-t-\[30px\] {
    border-top-left-radius: 30px;
    border-top-right-radius: 30px;
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
  .border-acid\/10 {
    border-color: color-mix(in srgb, rgb(var(--c-primary)) 10%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-acid) 10%, transparent);
    }
  }
  .border-ember\/15 {
    border-color: color-mix(in srgb, #f27b4a 15%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-ember) 15%, transparent);
    }
  }
  .border-pine-900\/8 {
    border-color: color-mix(in srgb, rgb(var(--c-pine-900)) 8%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-pine-900) 8%, transparent);
    }
  }
  .border-pine-900\/10 {
    border-color: color-mix(in srgb, rgb(var(--c-pine-900)) 10%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-pine-900) 10%, transparent);
    }
  }
  .border-pine-900\/12 {
    border-color: color-mix(in srgb, rgb(var(--c-pine-900)) 12%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-pine-900) 12%, transparent);
    }
  }
  .border-white\/8 {
    border-color: color-mix(in srgb, #fff 8%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-white) 8%, transparent);
    }
  }
  .border-white\/10 {
    border-color: color-mix(in srgb, #fff 10%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-white) 10%, transparent);
    }
  }
  .border-white\/12 {
    border-color: color-mix(in srgb, #fff 12%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-white) 12%, transparent);
    }
  }
  .border-white\/15 {
    border-color: color-mix(in srgb, #fff 15%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-white) 15%, transparent);
    }
  }
  .border-white\/20 {
    border-color: color-mix(in srgb, #fff 20%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-white) 20%, transparent);
    }
  }
  .bg-\[\#9d998e\] {
    background-color: #9d998e;
  }
  .bg-\[\#61a87e\] {
    background-color: #61a87e;
  }
  .bg-\[\#c55745\] {
    background-color: #c55745;
  }
  .bg-acid {
    background-color: var(--color-acid);
  }
  .bg-cream {
    background-color: var(--color-cream);
  }
  .bg-ember {
    background-color: var(--color-ember);
  }
  .bg-oat {
    background-color: var(--color-oat);
  }
  .bg-pine-800 {
    background-color: var(--color-pine-800);
  }
  .bg-pine-900 {
    background-color: var(--color-pine-900);
  }
  .bg-pine-900\/6 {
    background-color: color-mix(in srgb, rgb(var(--c-pine-900)) 6%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-pine-900) 6%, transparent);
    }
  }
  .bg-pine-900\/10 {
    background-color: color-mix(in srgb, rgb(var(--c-pine-900)) 10%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-pine-900) 10%, transparent);
    }
  }
  .bg-pine-950 {
    background-color: var(--color-pine-950);
  }
  .bg-pine-950\/70 {
    background-color: color-mix(in srgb, rgb(var(--c-pine-950)) 70%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-pine-950) 70%, transparent);
    }
  }
  .bg-pine-950\/80 {
    background-color: color-mix(in srgb, rgb(var(--c-pine-950)) 80%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-pine-950) 80%, transparent);
    }
  }
  .bg-sky {
    background-color: var(--color-sky);
  }
  .bg-white {
    background-color: var(--color-white);
  }
  .bg-white\/\[\.05\] {
    background-color: color-mix(in srgb, #fff 5%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-white) 5%, transparent);
    }
  }
  .bg-white\/\[\.06\] {
    background-color: color-mix(in srgb, #fff 6%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-white) 6%, transparent);
    }
  }
  .bg-white\/\[\.08\] {
    background-color: color-mix(in srgb, #fff 8%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-white) 8%, transparent);
    }
  }
  .bg-white\/\[\.035\] {
    background-color: color-mix(in srgb, #fff 3.5000000000000004%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-white) 3.5000000000000004%, transparent);
    }
  }
  .bg-white\/\[\.055\] {
    background-color: color-mix(in srgb, #fff 5.5%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-white) 5.5%, transparent);
    }
  }
  .bg-gradient-to-t {
    --tw-gradient-position: to top in oklab;
    background-image: linear-gradient(var(--tw-gradient-stops));
  }
  .from-pine-950 {
    --tw-gradient-from: var(--color-pine-950);
    --tw-gradient-stops: var(--tw-gradient-via-stops, var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position));
  }
  .via-pine-950\/15 {
    --tw-gradient-via: color-mix(in srgb, rgb(var(--c-pine-950)) 15%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      --tw-gradient-via: color-mix(in oklab, var(--color-pine-950) 15%, transparent);
    }
    --tw-gradient-via-stops: var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-via) var(--tw-gradient-via-position), var(--tw-gradient-to) var(--tw-gradient-to-position);
    --tw-gradient-stops: var(--tw-gradient-via-stops);
  }
  .via-pine-950\/55 {
    --tw-gradient-via: color-mix(in srgb, rgb(var(--c-pine-950)) 55%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      --tw-gradient-via: color-mix(in oklab, var(--color-pine-950) 55%, transparent);
    }
    --tw-gradient-via-stops: var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-via) var(--tw-gradient-via-position), var(--tw-gradient-to) var(--tw-gradient-to-position);
    --tw-gradient-stops: var(--tw-gradient-via-stops);
  }
  .to-pine-950\/20 {
    --tw-gradient-to: color-mix(in srgb, rgb(var(--c-pine-950)) 20%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      --tw-gradient-to: color-mix(in oklab, var(--color-pine-950) 20%, transparent);
    }
    --tw-gradient-stops: var(--tw-gradient-via-stops, var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position));
  }
  .to-transparent {
    --tw-gradient-to: transparent;
    --tw-gradient-stops: var(--tw-gradient-via-stops, var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position));
  }
  .object-cover {
    object-fit: cover;
  }
  .p-1 {
    padding: calc(var(--spacing) * 1);
  }
  .p-2 {
    padding: calc(var(--spacing) * 2);
  }
  .p-3 {
    padding: calc(var(--spacing) * 3);
  }
  .p-3\.5 {
    padding: calc(var(--spacing) * 3.5);
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
  .px-1 {
    padding-inline: calc(var(--spacing) * 1);
  }
  .px-2 {
    padding-inline: calc(var(--spacing) * 2);
  }
  .px-2\.5 {
    padding-inline: calc(var(--spacing) * 2.5);
  }
  .px-3 {
    padding-inline: calc(var(--spacing) * 3);
  }
  .px-3\.5 {
    padding-inline: calc(var(--spacing) * 3.5);
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
  .px-7 {
    padding-inline: calc(var(--spacing) * 7);
  }
  .py-1 {
    padding-block: calc(var(--spacing) * 1);
  }
  .py-1\.5 {
    padding-block: calc(var(--spacing) * 1.5);
  }
  .py-2 {
    padding-block: calc(var(--spacing) * 2);
  }
  .py-2\.5 {
    padding-block: calc(var(--spacing) * 2.5);
  }
  .py-3 {
    padding-block: calc(var(--spacing) * 3);
  }
  .py-3\.5 {
    padding-block: calc(var(--spacing) * 3.5);
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
  .pt-5 {
    padding-top: calc(var(--spacing) * 5);
  }
  .pt-6 {
    padding-top: calc(var(--spacing) * 6);
  }
  .pt-14 {
    padding-top: calc(var(--spacing) * 14);
  }
  .pt-28 {
    padding-top: calc(var(--spacing) * 28);
  }
  .pr-4 {
    padding-right: calc(var(--spacing) * 4);
  }
  .pb-2 {
    padding-bottom: calc(var(--spacing) * 2);
  }
  .pb-4 {
    padding-bottom: calc(var(--spacing) * 4);
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
  .pl-4 {
    padding-left: calc(var(--spacing) * 4);
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
  .font-display {
    font-family: var(--font-display);
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
  .text-\[7px\] {
    font-size: 7px;
  }
  .text-\[8px\] {
    font-size: 8px;
  }
  .text-\[9px\] {
    font-size: 9px;
  }
  .text-\[10px\] {
    font-size: 10px;
  }
  .text-\[13px\] {
    font-size: 13px;
  }
  .text-\[19px\] {
    font-size: 19px;
  }
  .text-\[clamp\(3\.35rem\,7\.5vw\,7\.5rem\)\] {
    font-size: clamp(3.35rem, 7.5vw, 7.5rem);
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
  .leading-\[\.84\] {
    --tw-leading: .84;
    line-height: .84;
  }
  .leading-\[\.94\] {
    --tw-leading: .94;
    line-height: .94;
  }
  .leading-\[\.95\] {
    --tw-leading: .95;
    line-height: .95;
  }
  .leading-\[\.96\] {
    --tw-leading: .96;
    line-height: .96;
  }
  .leading-none {
    --tw-leading: 1;
    line-height: 1;
  }
  .leading-tight {
    --tw-leading: var(--leading-tight);
    line-height: var(--leading-tight);
  }
  .font-bold {
    --tw-font-weight: var(--font-weight-bold);
    font-weight: var(--font-weight-bold);
  }
  .font-semibold {
    --tw-font-weight: var(--font-weight-semibold);
    font-weight: var(--font-weight-semibold);
  }
  .tracking-\[-\.04em\] {
    --tw-tracking: -.04em;
    letter-spacing: -.04em;
  }
  .tracking-\[-\.035em\] {
    --tw-tracking: -.035em;
    letter-spacing: -.035em;
  }
  .tracking-\[-\.055em\] {
    --tw-tracking: -.055em;
    letter-spacing: -.055em;
  }
  .tracking-\[\.2em\] {
    --tw-tracking: .2em;
    letter-spacing: .2em;
  }
  .tracking-\[\.11em\] {
    --tw-tracking: .11em;
    letter-spacing: .11em;
  }
  .tracking-\[\.12em\] {
    --tw-tracking: .12em;
    letter-spacing: .12em;
  }
  .tracking-\[\.13em\] {
    --tw-tracking: .13em;
    letter-spacing: .13em;
  }
  .tracking-\[\.14em\] {
    --tw-tracking: .14em;
    letter-spacing: .14em;
  }
  .tracking-\[\.16em\] {
    --tw-tracking: .16em;
    letter-spacing: .16em;
  }
  .tracking-\[\.18em\] {
    --tw-tracking: .18em;
    letter-spacing: .18em;
  }
  .tracking-\[\.22em\] {
    --tw-tracking: .22em;
    letter-spacing: .22em;
  }
  .tracking-\[\.24em\] {
    --tw-tracking: .24em;
    letter-spacing: .24em;
  }
  .tracking-\[\.25em\] {
    --tw-tracking: .25em;
    letter-spacing: .25em;
  }
  .whitespace-nowrap {
    white-space: nowrap;
  }
  .text-acid {
    color: var(--color-acid);
  }
  .text-pine-700 {
    color: var(--color-pine-700);
  }
  .text-pine-900\/35 {
    color: color-mix(in srgb, rgb(var(--c-pine-900)) 35%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 35%, transparent);
    }
  }
  .text-pine-900\/38 {
    color: color-mix(in srgb, rgb(var(--c-pine-900)) 38%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 38%, transparent);
    }
  }
  .text-pine-900\/40 {
    color: color-mix(in srgb, rgb(var(--c-pine-900)) 40%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 40%, transparent);
    }
  }
  .text-pine-900\/42 {
    color: color-mix(in srgb, rgb(var(--c-pine-900)) 42%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 42%, transparent);
    }
  }
  .text-pine-900\/45 {
    color: color-mix(in srgb, rgb(var(--c-pine-900)) 45%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 45%, transparent);
    }
  }
  .text-pine-900\/48 {
    color: color-mix(in srgb, rgb(var(--c-pine-900)) 48%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 48%, transparent);
    }
  }
  .text-pine-900\/50 {
    color: color-mix(in srgb, rgb(var(--c-pine-900)) 50%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 50%, transparent);
    }
  }
  .text-pine-900\/60 {
    color: color-mix(in srgb, rgb(var(--c-pine-900)) 60%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 60%, transparent);
    }
  }
  .text-pine-900\/62 {
    color: color-mix(in srgb, rgb(var(--c-pine-900)) 62%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 62%, transparent);
    }
  }
  .text-pine-950 {
    color: var(--color-pine-950);
  }
  .text-pine-950\/55 {
    color: color-mix(in srgb, rgb(var(--c-pine-950)) 55%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-950) 55%, transparent);
    }
  }
  .text-pine-950\/66 {
    color: color-mix(in srgb, rgb(var(--c-pine-950)) 66%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-950) 66%, transparent);
    }
  }
  .text-white {
    color: var(--color-white);
  }
  .text-white\/34 {
    color: color-mix(in srgb, #fff 34%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 34%, transparent);
    }
  }
  .text-white\/35 {
    color: color-mix(in srgb, #fff 35%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 35%, transparent);
    }
  }
  .text-white\/36 {
    color: color-mix(in srgb, #fff 36%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 36%, transparent);
    }
  }
  .text-white\/40 {
    color: color-mix(in srgb, #fff 40%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 40%, transparent);
    }
  }
  .text-white\/42 {
    color: color-mix(in srgb, #fff 42%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 42%, transparent);
    }
  }
  .text-white\/45 {
    color: color-mix(in srgb, #fff 45%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 45%, transparent);
    }
  }
  .text-white\/46 {
    color: color-mix(in srgb, #fff 46%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 46%, transparent);
    }
  }
  .text-white\/48 {
    color: color-mix(in srgb, #fff 48%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 48%, transparent);
    }
  }
  .text-white\/50 {
    color: color-mix(in srgb, #fff 50%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 50%, transparent);
    }
  }
  .text-white\/52 {
    color: color-mix(in srgb, #fff 52%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 52%, transparent);
    }
  }
  .text-white\/55 {
    color: color-mix(in srgb, #fff 55%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 55%, transparent);
    }
  }
  .text-white\/58 {
    color: color-mix(in srgb, #fff 58%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 58%, transparent);
    }
  }
  .text-white\/64 {
    color: color-mix(in srgb, #fff 64%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 64%, transparent);
    }
  }
  .text-white\/65 {
    color: color-mix(in srgb, #fff 65%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 65%, transparent);
    }
  }
  .text-white\/70 {
    color: color-mix(in srgb, #fff 70%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 70%, transparent);
    }
  }
  .text-white\/85 {
    color: color-mix(in srgb, #fff 85%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 85%, transparent);
    }
  }
  .uppercase {
    text-transform: uppercase;
  }
  .italic {
    font-style: italic;
  }
  .underline {
    text-decoration-line: underline;
  }
  .decoration-pine-700\/25 {
    text-decoration-color: color-mix(in srgb, rgb(var(--c-pine-700)) 25%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      text-decoration-color: color-mix(in oklab, var(--color-pine-700) 25%, transparent);
    }
  }
  .underline-offset-4 {
    text-underline-offset: 4px;
  }
  .antialiased {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }
  .opacity-16 {
    opacity: 16%;
  }
  .opacity-38 {
    opacity: 38%;
  }
  .opacity-45 {
    opacity: 45%;
  }
  .opacity-48 {
    opacity: 48%;
  }
  .opacity-50 {
    opacity: 50%;
  }
  .shadow-2xl {
    --tw-shadow: 0 25px 50px -12px var(--tw-shadow-color, rgb(0 0 0 / 0.25));
    box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow);
  }
  .shadow-\[0_14px_35px_-20px_rgba\(223\,252\,98\,\.8\)\] {
    --tw-shadow: 0 14px 35px -20px var(--tw-shadow-color, rgb(var(--c-primary) / .8));
    box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow);
  }
  .shadow-\[0_34px_100px_-50px_rgba\(6\,26\,21\,\.9\)\] {
    --tw-shadow: 0 34px 100px -50px var(--tw-shadow-color, rgb(var(--c-pine-950) / .9));
    box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow);
  }
  .shadow-sm {
    --tw-shadow: 0 1px 3px 0 var(--tw-shadow-color, rgb(0 0 0 / 0.1)), 0 1px 2px -1px var(--tw-shadow-color, rgb(0 0 0 / 0.1));
    box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow);
  }
  .backdrop-blur {
    --tw-backdrop-blur: blur(8px);
    -webkit-backdrop-filter: var(--tw-backdrop-blur,) var(--tw-backdrop-brightness,) var(--tw-backdrop-contrast,) var(--tw-backdrop-grayscale,) var(--tw-backdrop-hue-rotate,) var(--tw-backdrop-invert,) var(--tw-backdrop-opacity,) var(--tw-backdrop-saturate,) var(--tw-backdrop-sepia,);
    backdrop-filter: var(--tw-backdrop-blur,) var(--tw-backdrop-brightness,) var(--tw-backdrop-contrast,) var(--tw-backdrop-grayscale,) var(--tw-backdrop-hue-rotate,) var(--tw-backdrop-invert,) var(--tw-backdrop-opacity,) var(--tw-backdrop-saturate,) var(--tw-backdrop-sepia,);
  }
  .backdrop-blur-xl {
    --tw-backdrop-blur: blur(var(--blur-xl));
    -webkit-backdrop-filter: var(--tw-backdrop-blur,) var(--tw-backdrop-brightness,) var(--tw-backdrop-contrast,) var(--tw-backdrop-grayscale,) var(--tw-backdrop-hue-rotate,) var(--tw-backdrop-invert,) var(--tw-backdrop-opacity,) var(--tw-backdrop-saturate,) var(--tw-backdrop-sepia,);
    backdrop-filter: var(--tw-backdrop-blur,) var(--tw-backdrop-brightness,) var(--tw-backdrop-contrast,) var(--tw-backdrop-grayscale,) var(--tw-backdrop-hue-rotate,) var(--tw-backdrop-invert,) var(--tw-backdrop-opacity,) var(--tw-backdrop-saturate,) var(--tw-backdrop-sepia,);
  }
  .transition {
    transition-property: color, background-color, border-color, outline-color, text-decoration-color, fill, stroke, --tw-gradient-from, --tw-gradient-via, --tw-gradient-to, opacity, box-shadow, transform, translate, scale, rotate, filter, -webkit-backdrop-filter, backdrop-filter, display, visibility, content-visibility, overlay, pointer-events;
    transition-timing-function: var(--tw-ease, var(--default-transition-timing-function));
    transition-duration: var(--tw-duration, var(--default-transition-duration));
  }
  .group-hover\:bg-acid {
    &:is(:where(.group):hover *) {
      @media (hover: hover) {
        background-color: var(--color-acid);
      }
    }
  }
  .group-hover\:text-pine-950 {
    &:is(:where(.group):hover *) {
      @media (hover: hover) {
        color: var(--color-pine-950);
      }
    }
  }
  .hover\:-translate-y-0\.5 {
    &:hover {
      @media (hover: hover) {
        --tw-translate-y: calc(var(--spacing) * -0.5);
        translate: var(--tw-translate-x) var(--tw-translate-y);
      }
    }
  }
  .hover\:border-white\/30 {
    &:hover {
      @media (hover: hover) {
        border-color: color-mix(in srgb, #fff 30%, transparent);
        @supports (color: color-mix(in lab, red, red)) {
          border-color: color-mix(in oklab, var(--color-white) 30%, transparent);
        }
      }
    }
  }
  .hover\:bg-pine-900 {
    &:hover {
      @media (hover: hover) {
        background-color: var(--color-pine-900);
      }
    }
  }
  .hover\:bg-white\/10 {
    &:hover {
      @media (hover: hover) {
        background-color: color-mix(in srgb, #fff 10%, transparent);
        @supports (color: color-mix(in lab, red, red)) {
          background-color: color-mix(in oklab, var(--color-white) 10%, transparent);
        }
      }
    }
  }
  .hover\:text-acid {
    &:hover {
      @media (hover: hover) {
        color: var(--color-acid);
      }
    }
  }
  .hover\:text-white {
    &:hover {
      @media (hover: hover) {
        color: var(--color-white);
      }
    }
  }
  .min-\[410px\]\:block {
    @media (width >= 410px) {
      display: block;
    }
  }
  .min-\[430px\]\:flex-row {
    @media (width >= 430px) {
      flex-direction: row;
    }
  }
  .sm\:top-0 {
    @media (width >= 40rem) {
      top: calc(var(--spacing) * 0);
    }
  }
  .sm\:top-7 {
    @media (width >= 40rem) {
      top: calc(var(--spacing) * 7);
    }
  }
  .sm\:right-0 {
    @media (width >= 40rem) {
      right: calc(var(--spacing) * 0);
    }
  }
  .sm\:right-auto {
    @media (width >= 40rem) {
      right: auto;
    }
  }
  .sm\:bottom-7 {
    @media (width >= 40rem) {
      bottom: calc(var(--spacing) * 7);
    }
  }
  .sm\:left-6 {
    @media (width >= 40rem) {
      left: calc(var(--spacing) * 6);
    }
  }
  .sm\:left-7 {
    @media (width >= 40rem) {
      left: calc(var(--spacing) * 7);
    }
  }
  .sm\:left-auto {
    @media (width >= 40rem) {
      left: auto;
    }
  }
  .sm\:block {
    @media (width >= 40rem) {
      display: block;
    }
  }
  .sm\:h-8 {
    @media (width >= 40rem) {
      height: calc(var(--spacing) * 8);
    }
  }
  .sm\:h-11 {
    @media (width >= 40rem) {
      height: calc(var(--spacing) * 11);
    }
  }
  .sm\:h-full {
    @media (width >= 40rem) {
      height: 100%;
    }
  }
  .sm\:max-h-none {
    @media (width >= 40rem) {
      max-height: none;
    }
  }
  .sm\:min-h-\[610px\] {
    @media (width >= 40rem) {
      min-height: 610px;
    }
  }
  .sm\:w-8 {
    @media (width >= 40rem) {
      width: calc(var(--spacing) * 8);
    }
  }
  .sm\:w-11 {
    @media (width >= 40rem) {
      width: calc(var(--spacing) * 11);
    }
  }
  .sm\:w-\[360px\] {
    @media (width >= 40rem) {
      width: 360px;
    }
  }
  .sm\:w-\[460px\] {
    @media (width >= 40rem) {
      width: 460px;
    }
  }
  .sm\:w-auto {
    @media (width >= 40rem) {
      width: auto;
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
  .sm\:grid-cols-4 {
    @media (width >= 40rem) {
      grid-template-columns: repeat(4, minmax(0, 1fr));
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
  .sm\:gap-2 {
    @media (width >= 40rem) {
      gap: calc(var(--spacing) * 2);
    }
  }
  .sm\:gap-2\.5 {
    @media (width >= 40rem) {
      gap: calc(var(--spacing) * 2.5);
    }
  }
  .sm\:gap-3 {
    @media (width >= 40rem) {
      gap: calc(var(--spacing) * 3);
    }
  }
  .sm\:self-auto {
    @media (width >= 40rem) {
      align-self: auto;
    }
  }
  .sm\:rounded-\[22px\] {
    @media (width >= 40rem) {
      border-radius: 22px;
    }
  }
  .sm\:rounded-\[34px\] {
    @media (width >= 40rem) {
      border-radius: 34px;
    }
  }
  .sm\:rounded-none {
    @media (width >= 40rem) {
      border-radius: 0;
    }
  }
  .sm\:p-4 {
    @media (width >= 40rem) {
      padding: calc(var(--spacing) * 4);
    }
  }
  .sm\:p-5 {
    @media (width >= 40rem) {
      padding: calc(var(--spacing) * 5);
    }
  }
  .sm\:p-6 {
    @media (width >= 40rem) {
      padding: calc(var(--spacing) * 6);
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
  .sm\:py-6 {
    @media (width >= 40rem) {
      padding-block: calc(var(--spacing) * 6);
    }
  }
  .sm\:py-20 {
    @media (width >= 40rem) {
      padding-block: calc(var(--spacing) * 20);
    }
  }
  .sm\:pt-32 {
    @media (width >= 40rem) {
      padding-top: calc(var(--spacing) * 32);
    }
  }
  .sm\:pb-20 {
    @media (width >= 40rem) {
      padding-bottom: calc(var(--spacing) * 20);
    }
  }
  .sm\:pl-6 {
    @media (width >= 40rem) {
      padding-left: calc(var(--spacing) * 6);
    }
  }
  .sm\:text-5xl {
    @media (width >= 40rem) {
      font-size: var(--text-5xl);
      line-height: var(--tw-leading, var(--text-5xl--line-height));
    }
  }
  .sm\:text-lg {
    @media (width >= 40rem) {
      font-size: var(--text-lg);
      line-height: var(--tw-leading, var(--text-lg--line-height));
    }
  }
  .sm\:text-sm {
    @media (width >= 40rem) {
      font-size: var(--text-sm);
      line-height: var(--tw-leading, var(--text-sm--line-height));
    }
  }
  .sm\:text-xs {
    @media (width >= 40rem) {
      font-size: var(--text-xs);
      line-height: var(--tw-leading, var(--text-xs--line-height));
    }
  }
  .sm\:text-\[8px\] {
    @media (width >= 40rem) {
      font-size: 8px;
    }
  }
  .sm\:text-\[10px\] {
    @media (width >= 40rem) {
      font-size: 10px;
    }
  }
  .sm\:text-\[21px\] {
    @media (width >= 40rem) {
      font-size: 21px;
    }
  }
  .sm\:leading-8 {
    @media (width >= 40rem) {
      --tw-leading: calc(var(--spacing) * 8);
      line-height: calc(var(--spacing) * 8);
    }
  }
  .md\:grid-cols-2 {
    @media (width >= 48rem) {
      grid-template-columns: repeat(2, minmax(0, 1fr));
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
  .lg\:min-h-\[650px\] {
    @media (width >= 64rem) {
      min-height: 650px;
    }
  }
  .lg\:min-h-\[790px\] {
    @media (width >= 64rem) {
      min-height: 790px;
    }
  }
  .lg\:grid-cols-4 {
    @media (width >= 64rem) {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
  }
  .lg\:grid-cols-\[\.8fr_1\.2fr\] {
    @media (width >= 64rem) {
      grid-template-columns: .8fr 1.2fr;
    }
  }
  .lg\:grid-cols-\[\.9fr_1\.1fr\] {
    @media (width >= 64rem) {
      grid-template-columns: .9fr 1.1fr;
    }
  }
  .lg\:grid-cols-\[\.75fr_1\.25fr\] {
    @media (width >= 64rem) {
      grid-template-columns: .75fr 1.25fr;
    }
  }
  .lg\:grid-cols-\[1\.15fr_\.85fr_\.85fr_\.85fr\] {
    @media (width >= 64rem) {
      grid-template-columns: 1.15fr .85fr .85fr .85fr;
    }
  }
  .lg\:grid-cols-\[1fr_auto\] {
    @media (width >= 64rem) {
      grid-template-columns: 1fr auto;
    }
  }
  .lg\:flex-row {
    @media (width >= 64rem) {
      flex-direction: row;
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
  .lg\:justify-between {
    @media (width >= 64rem) {
      justify-content: space-between;
    }
  }
  .lg\:gap-14 {
    @media (width >= 64rem) {
      gap: calc(var(--spacing) * 14);
    }
  }
  .lg\:divide-y-0 {
    @media (width >= 64rem) {
      :where(& > :not(:last-child)) {
        --tw-divide-y-reverse: 0;
        border-bottom-style: var(--tw-border-style);
        border-top-style: var(--tw-border-style);
        border-top-width: calc(0px * var(--tw-divide-y-reverse));
        border-bottom-width: calc(0px * calc(1 - var(--tw-divide-y-reverse)));
      }
    }
  }
  .lg\:self-auto {
    @media (width >= 64rem) {
      align-self: auto;
    }
  }
  .lg\:p-6 {
    @media (width >= 64rem) {
      padding: calc(var(--spacing) * 6);
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
  .lg\:pt-18 {
    @media (width >= 64rem) {
      padding-top: calc(var(--spacing) * 18);
    }
  }
  .lg\:pt-36 {
    @media (width >= 64rem) {
      padding-top: calc(var(--spacing) * 36);
    }
  }
  .lg\:pb-24 {
    @media (width >= 64rem) {
      padding-bottom: calc(var(--spacing) * 24);
    }
  }
  .lg\:text-6xl {
    @media (width >= 64rem) {
      font-size: var(--text-6xl);
      line-height: var(--tw-leading, var(--text-6xl--line-height));
    }
  }
  .xl\:sticky {
    @media (width >= 80rem) {
      position: sticky;
    }
  }
  .xl\:top-28 {
    @media (width >= 80rem) {
      top: calc(var(--spacing) * 28);
    }
  }
  .xl\:flex {
    @media (width >= 80rem) {
      display: flex;
    }
  }
  .xl\:hidden {
    @media (width >= 80rem) {
      display: none;
    }
  }
  .xl\:grid-cols-\[minmax\(0\,1\.35fr\)_minmax\(380px\,\.65fr\)\] {
    @media (width >= 80rem) {
      grid-template-columns: minmax(0,1.35fr) minmax(380px,.65fr);
    }
  }
  .xl\:items-start {
    @media (width >= 80rem) {
      align-items: flex-start;
    }
  }
  .xl\:gap-20 {
    @media (width >= 80rem) {
      gap: calc(var(--spacing) * 20);
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
@property --tw-divide-x-reverse {
  syntax: "*";
  inherits: false;
  initial-value: 0;
}
@property --tw-border-style {
  syntax: "*";
  inherits: false;
  initial-value: solid;
}
@property --tw-divide-y-reverse {
  syntax: "*";
  inherits: false;
  initial-value: 0;
}
@property --tw-gradient-position {
  syntax: "*";
  inherits: false;
}
@property --tw-gradient-from {
  syntax: "<color>";
  inherits: false;
  initial-value: #0000;
}
@property --tw-gradient-via {
  syntax: "<color>";
  inherits: false;
  initial-value: #0000;
}
@property --tw-gradient-to {
  syntax: "<color>";
  inherits: false;
  initial-value: #0000;
}
@property --tw-gradient-stops {
  syntax: "*";
  inherits: false;
}
@property --tw-gradient-via-stops {
  syntax: "*";
  inherits: false;
}
@property --tw-gradient-from-position {
  syntax: "<length-percentage>";
  inherits: false;
  initial-value: 0%;
}
@property --tw-gradient-via-position {
  syntax: "<length-percentage>";
  inherits: false;
  initial-value: 50%;
}
@property --tw-gradient-to-position {
  syntax: "<length-percentage>";
  inherits: false;
  initial-value: 100%;
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
@property --tw-shadow {
  syntax: "*";
  inherits: false;
  initial-value: 0 0 #0000;
}
@property --tw-shadow-color {
  syntax: "*";
  inherits: false;
}
@property --tw-shadow-alpha {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 100%;
}
@property --tw-inset-shadow {
  syntax: "*";
  inherits: false;
  initial-value: 0 0 #0000;
}
@property --tw-inset-shadow-color {
  syntax: "*";
  inherits: false;
}
@property --tw-inset-shadow-alpha {
  syntax: "<percentage>";
  inherits: false;
  initial-value: 100%;
}
@property --tw-ring-color {
  syntax: "*";
  inherits: false;
}
@property --tw-ring-shadow {
  syntax: "*";
  inherits: false;
  initial-value: 0 0 #0000;
}
@property --tw-inset-ring-color {
  syntax: "*";
  inherits: false;
}
@property --tw-inset-ring-shadow {
  syntax: "*";
  inherits: false;
  initial-value: 0 0 #0000;
}
@property --tw-ring-inset {
  syntax: "*";
  inherits: false;
}
@property --tw-ring-offset-width {
  syntax: "<length>";
  inherits: false;
  initial-value: 0px;
}
@property --tw-ring-offset-color {
  syntax: "*";
  inherits: false;
  initial-value: #fff;
}
@property --tw-ring-offset-shadow {
  syntax: "*";
  inherits: false;
  initial-value: 0 0 #0000;
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
@layer properties {
  @supports ((-webkit-hyphens: none) and (not (margin-trim: inline))) or ((-moz-orient: inline) and (not (color:rgb(from red r g b)))) {
    *, ::before, ::after, ::backdrop {
      --tw-translate-x: 0;
      --tw-translate-y: 0;
      --tw-translate-z: 0;
      --tw-space-y-reverse: 0;
      --tw-divide-x-reverse: 0;
      --tw-border-style: solid;
      --tw-divide-y-reverse: 0;
      --tw-gradient-position: initial;
      --tw-gradient-from: #0000;
      --tw-gradient-via: #0000;
      --tw-gradient-to: #0000;
      --tw-gradient-stops: initial;
      --tw-gradient-via-stops: initial;
      --tw-gradient-from-position: 0%;
      --tw-gradient-via-position: 50%;
      --tw-gradient-to-position: 100%;
      --tw-leading: initial;
      --tw-font-weight: initial;
      --tw-tracking: initial;
      --tw-shadow: 0 0 #0000;
      --tw-shadow-color: initial;
      --tw-shadow-alpha: 100%;
      --tw-inset-shadow: 0 0 #0000;
      --tw-inset-shadow-color: initial;
      --tw-inset-shadow-alpha: 100%;
      --tw-ring-color: initial;
      --tw-ring-shadow: 0 0 #0000;
      --tw-inset-ring-color: initial;
      --tw-inset-ring-shadow: 0 0 #0000;
      --tw-ring-inset: initial;
      --tw-ring-offset-width: 0px;
      --tw-ring-offset-color: #fff;
      --tw-ring-offset-shadow: 0 0 #0000;
      --tw-backdrop-blur: initial;
      --tw-backdrop-brightness: initial;
      --tw-backdrop-contrast: initial;
      --tw-backdrop-grayscale: initial;
      --tw-backdrop-hue-rotate: initial;
      --tw-backdrop-invert: initial;
      --tw-backdrop-opacity: initial;
      --tw-backdrop-saturate: initial;
      --tw-backdrop-sepia: initial;
    }
  }
}


    :root{--pine:rgb(var(--c-pine-900));--pine-dark:rgb(var(--c-pine-950));--acid:rgb(var(--c-primary));--ember:#f27b4a;--cream:rgb(var(--c-canvas));--oat:rgb(var(--c-surface));--ink:rgb(var(--c-fg));--moss:#b7c9a3;--sky:#b8dce0;--stone:#d7d2c2}
    *{box-sizing:border-box}html{background:var(--oat)}body{margin:0;overflow-x:hidden;background:var(--oat);color:var(--ink);font-family:var(--f-body);text-rendering:optimizeLegibility}[x-cloak]{display:none!important}::selection{background:var(--acid);color:var(--pine-dark)}button,a{-webkit-tap-highlight-color:transparent}.font-display{font-family:var(--f-display)}.safe-top{padding-top:max(12px,env(safe-area-inset-top))}.safe-bottom{padding-bottom:max(18px,env(safe-area-inset-bottom))}
    .topo-dark{background-image:radial-gradient(circle at 13% 18%,rgb(var(--c-primary) / .14),transparent 18%),radial-gradient(circle at 87% 16%,rgba(242,123,74,.11),transparent 17%),url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23fff' stroke-opacity='.052' stroke-width='1.1'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M119 231c31-83 127-143 219-107 76 30 95 118 53 180-40 58-121 56-160 115-42 61-28 159-107 197-77 37-165-25-154-110 10-83 94-98 102-174 4-44 0-74 47-101Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3Cpath d='M548 507c39-60 118-79 173-32 53 45 37 128-19 161-50 30-111 8-150 51-34 36-29 100-79 112-55 14-106-39-83-92 21-50 81-47 110-92 23-36 22-72 48-108Z'/%3E%3C/g%3E%3C/svg%3E");background-size:auto,auto,820px 820px}
    .paper-grid{background-image:linear-gradient(rgb(var(--c-pine-900) / .045) 1px,transparent 1px),linear-gradient(90deg,rgb(var(--c-pine-900) / .045) 1px,transparent 1px);background-size:30px 30px}.grain:after{content:'';position:absolute;inset:0;z-index:2;pointer-events:none;opacity:.075;mix-blend-mode:soft-light;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.58'/%3E%3C/svg%3E")}
    .nav-shell{background:rgb(var(--c-pine-950) / .82);border:1px solid rgba(255,255,255,.12);box-shadow:0 24px 70px -42px rgba(0,0,0,.9);backdrop-filter:blur(22px) saturate(130%);-webkit-backdrop-filter:blur(22px) saturate(130%)}
    .outline-word{color:transparent;-webkit-text-stroke:1px rgba(255,255,255,.4)}.hero-cut{clip-path:polygon(5% 0,100% 0,96% 90%,76% 100%,0 94%,0 8%)}
    .route{fill:none;stroke:var(--acid);stroke-width:2.5;stroke-linecap:round;stroke-dasharray:9 13;filter:drop-shadow(0 0 7px rgb(var(--c-primary) / .36));animation:route-flow 16s linear infinite}@keyframes route-flow{to{stroke-dashoffset:-220}}
    .orbit{animation:orbit 18s linear infinite;transform-origin:50% 50%}@keyframes orbit{to{transform:rotate(360deg)}}.float-card{animation:float-card 5.8s ease-in-out infinite}@keyframes float-card{50%{transform:translateY(-8px)}}
    .status-pulse{box-shadow:0 0 0 0 rgb(var(--c-primary) / .42);animation:status-pulse 2.2s ease-out infinite}@keyframes status-pulse{70%{box-shadow:0 0 0 11px rgb(var(--c-primary) / 0)}100%{box-shadow:0 0 0 0 rgb(var(--c-primary) / 0)}}
    .calendar-shell{box-shadow:0 34px 100px -54px rgb(var(--c-pine-950) / .48)}.calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}.calendar-day{position:relative;min-height:92px;border:1px solid rgb(var(--c-pine-900) / .09);background:rgb(var(--c-canvas) / .86);transition:transform .22s ease,border-color .22s ease,box-shadow .22s ease,background .22s ease;overflow:hidden}.calendar-day:not(:disabled):hover{transform:translateY(-3px);border-color:rgb(var(--c-pine-900) / .25);box-shadow:0 18px 35px -28px rgb(var(--c-pine-950) / .7)}.calendar-day.is-selected{background:var(--pine);border-color:var(--pine);color:white;box-shadow:0 24px 55px -32px rgb(var(--c-pine-950) / .9);transform:translateY(-3px)}.calendar-day.is-outside{opacity:.35}.calendar-day.is-closed{background:rgba(215,210,194,.48);color:rgb(var(--c-fg) / .46)}.calendar-day.is-soldout{background:rgba(242,123,74,.08)}.calendar-day.is-today:after{content:'AZI';position:absolute;right:8px;top:8px;border-radius:999px;background:var(--acid);padding:3px 6px;color:var(--pine-dark);font-size:7px;font-weight:800;letter-spacing:.12em}.calendar-day.is-selected.is-today:after{background:white}.calendar-day.is-selected.is-soldout{background:var(--pine);color:white}.capacity-bar{height:4px;border-radius:999px;background:rgb(var(--c-pine-900) / .1);overflow:hidden}.calendar-day.is-selected .capacity-bar{background:rgba(255,255,255,.18)}.capacity-fill{height:100%;border-radius:inherit;transition:width .4s ease}.availability-dot{width:7px;height:7px;border-radius:999px;flex:none}.dot-open{background:#61a87e}.dot-limited{background:var(--ember)}.dot-soldout{background:#c55745}.dot-closed{background:#9d998e}
    .filter-chip{transition:background .22s ease,color .22s ease,border-color .22s ease,transform .22s ease}.filter-chip:hover{transform:translateY(-1px)}.filter-chip.is-active{background:var(--pine);border-color:var(--pine);color:white}.filter-chip.is-active .chip-icon{background:var(--acid);color:var(--pine-dark)}
    .slot-button{transition:transform .2s ease,border-color .2s ease,background .2s ease,color .2s ease}.slot-button:not(:disabled):hover{transform:translateY(-2px);border-color:rgb(var(--c-pine-900) / .35)}.slot-button.is-active{background:var(--acid);border-color:var(--acid);color:var(--pine-dark);box-shadow:0 16px 34px -24px rgb(var(--c-pine-950) / .8)}.slot-button:disabled{cursor:not-allowed;opacity:.38;text-decoration:line-through}
    .experience-row{transition:background .25s ease,border-color .25s ease}.experience-row.is-open{background:white;border-color:rgb(var(--c-pine-900) / .18)}.experience-row__content{display:grid;grid-template-rows:0fr;transition:grid-template-rows .32s cubic-bezier(.2,.8,.2,1)}.experience-row__content>div{overflow:hidden}.experience-row.is-open .experience-row__content{grid-template-rows:1fr}.experience-row.is-open .row-chevron{transform:rotate(180deg)}.row-chevron{transition:transform .25s ease}
    .heat-card{transition:transform .22s ease,box-shadow .22s ease}.heat-card:hover{transform:translateY(-4px);box-shadow:0 24px 55px -36px rgb(var(--c-pine-950) / .7)}
    .reveal{opacity:0;transform:translateY(28px);transition:opacity .72s ease,transform .72s cubic-bezier(.2,.75,.2,1)}.reveal.is-visible{opacity:1;transform:none}.reveal-delay-1{transition-delay:.08s}.reveal-delay-2{transition-delay:.16s}.reveal-delay-3{transition-delay:.24s}.progress-line{transform:scaleX(0);transform-origin:left center}
    .drawer-backdrop{background:rgb(var(--c-pine-950) / .62);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}.drawer-panel{box-shadow:-50px 0 120px -60px rgb(var(--c-pine-950) / .85)}.scrollbar-hide::-webkit-scrollbar{display:none}.scrollbar-hide{scrollbar-width:none}
    @media(max-width:1023px){.hero-cut{clip-path:none;border-radius:28px}.calendar-day{min-height:82px}.calendar-grid{gap:6px}}
    @media(max-width:639px){.calendar-shell{margin-left:-8px;margin-right:-8px;border-radius:26px}.calendar-grid{gap:4px}.calendar-day{min-height:64px;border-radius:13px;padding:7px!important}.calendar-day.is-today:after{display:none}.calendar-day .day-meta{display:none}.calendar-day .capacity-label{display:none}.calendar-day .capacity-bar{margin-top:5px}.calendar-day .day-number{font-size:14px}.reveal{transform:translateY(18px)}}
    @media(max-width:374px){.calendar-day{min-height:58px;padding:6px!important}.calendar-grid{gap:3px}.calendar-day .day-number{font-size:13px}}
    @media(prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.01ms!important}.reveal{opacity:1!important;transform:none!important}}
  
CSS;
$pageExtraHead = <<<'HTML'

HTML;
include ts_layout('head');
include ts_layout('header');
?>
  <div id="pageProgress" class="progress-line fixed left-0 top-0 z-[190] h-[3px] w-full bg-acid"></div>

  

  <main>
    <section class="topo-dark grain relative overflow-hidden bg-pine-950 pb-16 pt-28 text-white sm:pb-20 sm:pt-32 lg:min-h-[790px] lg:pb-24 lg:pt-36">
      <div class="pointer-events-none absolute -left-20 top-28 h-80 w-80 rounded-full border border-acid/10"></div>
      <div class="pointer-events-none absolute -right-24 bottom-6 h-96 w-96 rounded-full border border-ember/15"></div>
      <div class="relative z-10 mx-auto grid max-w-[1540px] gap-10 px-4 sm:px-6 lg:grid-cols-[.9fr_1.1fr] lg:items-center lg:gap-14 lg:px-8 xl:gap-20">
        <div class="max-w-3xl reveal">
          <div class="mb-5 inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/[.06] px-4 py-2 text-[10px] font-bold uppercase tracking-[.24em] text-white/70 sm:text-xs"><span class="status-pulse h-2 w-2 rounded-full bg-acid"></span> Disponibilitate în timp real</div>
          <h1 class="font-display text-[clamp(3.35rem,7.5vw,7.5rem)] font-semibold leading-[.84] tracking-[-.055em]">Alege ziua.<br><span class="text-acid">Noi ținem</span><br><span class="outline-word italic">locul liber.</span></h1>
          <p class="mt-7 max-w-2xl text-base leading-7 text-white/65 sm:text-lg sm:leading-8">Vezi dintr-o privire ce zile mai au locuri, ce activități sunt libere și la ce oră. Rezervi direct din calendar.</p>
          <div class="mt-8 flex flex-col gap-3 min-[430px]:flex-row">
            <a href="#calendar" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full bg-acid px-6 py-3.5 font-bold text-pine-950 transition hover:-translate-y-0.5">Verifică disponibilitatea <span aria-hidden="true">↘</span></a>
            <a href="#ferestre" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full border border-white/20 px-6 py-3.5 font-semibold text-white transition hover:bg-white/10">Vezi zilele recomandate</a>
          </div>
          <div class="mt-8 grid max-w-xl grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-white/10 bg-white/[.05] p-3"><p class="text-[9px] font-bold uppercase tracking-[.2em] text-white/40">Următoarea zi</p><p class="mt-1 font-display text-xl" x-text="nextOpen ? nextOpen.short : '—'"></p></div>
            <div class="rounded-2xl border border-white/10 bg-white/[.05] p-3"><p class="text-[9px] font-bold uppercase tracking-[.2em] text-white/40">Locuri libere</p><p class="mt-1 font-display text-xl" x-text="nextOpen ? nextOpen.remaining : '—'"></p></div>
            <div class="rounded-2xl border border-white/10 bg-white/[.05] p-3"><p class="text-[9px] font-bold uppercase tracking-[.2em] text-white/40">Cea mai liberă</p><p class="mt-1 font-display text-xl" x-text="freest ? freest.short : '—'"></p></div>
            <div class="rounded-2xl border border-white/10 bg-white/[.05] p-3"><p class="text-[9px] font-bold uppercase tracking-[.2em] text-white/40">Program</p><p class="mt-1 font-display text-xl"><?= e($calHours !== '' ? $calHours : 'vezi ziua') ?></p></div>
          </div>
        </div>

        <div class="hero-cut relative min-h-[520px] overflow-hidden bg-pine-900 sm:min-h-[610px] lg:min-h-[650px] reveal reveal-delay-1">
          <img src="https://images.unsplash.com/photo-1500534314209-a25ddb2bd4297?w=1600&q=85" alt="Pădure luminată în timpul dimineții" class="absolute inset-0 h-full w-full object-cover opacity-50">
          <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/15 to-transparent"></div>
          <svg viewBox="0 0 780 650" class="absolute inset-0 h-full w-full" fill="none" aria-hidden="true">
            <path class="route" d="M95 530C165 470 150 382 246 345c77-30 87-116 178-137 80-18 132 49 211-30"/>
            <g class="orbit"><circle cx="392" cy="322" r="178" stroke="rgb(var(--c-primary) / .18)" stroke-width="1"/><circle cx="392" cy="322" r="128" stroke="rgba(255,255,255,.13)" stroke-width="1"/></g>
            <circle cx="246" cy="345" r="8" fill="rgb(var(--c-primary))"/><circle cx="424" cy="208" r="8" fill="#F27B4A"/><circle cx="635" cy="178" r="8" fill="rgb(var(--c-canvas))"/>
          </svg>
          <div class="absolute left-4 top-5 rounded-full border border-white/15 bg-pine-950/70 px-4 py-2 text-[10px] font-bold uppercase tracking-[.2em] text-white/70 backdrop-blur sm:left-6 sm:top-7">Disponibilitate live</div>

          <div class="absolute bottom-5 left-4 right-4 sm:bottom-7 sm:left-7 sm:right-auto sm:w-[360px]">
            <div class="float-card rounded-[26px] border border-white/15 bg-pine-950/80 p-4 shadow-2xl backdrop-blur-xl sm:p-5">
              <div class="flex items-center justify-between gap-4"><div><p class="text-[9px] font-bold uppercase tracking-[.2em] text-white/40">Ziua recomandată</p><p class="mt-1 font-display text-2xl" x-text="freest ? freest.long : 'Se verifică…'"></p></div><span class="grid h-11 min-w-[44px] flex-none place-items-center rounded-full bg-acid px-2 text-sm font-bold text-pine-950" x-text="freest ? freest.remaining : '…'"></span></div>
              <div class="mt-4 grid grid-cols-3 gap-2 text-center"><div class="rounded-xl bg-white/[.06] p-2"><p class="text-[8px] uppercase tracking-[.16em] text-white/35">Flux</p><p class="mt-1 text-sm font-bold" x-text="freest ? flowLabel(freest.status) : '—'"></p></div><div class="rounded-xl bg-white/[.06] p-2"><p class="text-[8px] uppercase tracking-[.16em] text-white/35">De la</p><p class="mt-1 text-sm font-bold text-acid" x-text="freest && freest.min_price_cents != null ? lvLei(freest.min_price_cents) : '—'"></p></div><div class="rounded-xl bg-white/[.06] p-2"><p class="text-[8px] uppercase tracking-[.16em] text-white/35">Produse</p><p class="mt-1 text-sm font-bold" x-text="experiences.length"></p></div></div>
            </div>
          </div>
          <div class="absolute right-5 top-24 hidden w-[190px] rotate-3 rounded-[24px] border border-white/15 bg-cream p-4 text-pine-950 shadow-2xl sm:block"><p class="text-[8px] font-bold uppercase tracking-[.22em] text-pine-900/45">Sfat</p><p class="mt-3 font-display text-xl leading-tight">Zilele din timpul săptămânii sunt de obicei cele mai liniștite.</p><div class="mt-4 h-px bg-pine-900/10"></div><p class="mt-3 text-xs leading-5 text-pine-900/60">Numărul de locuri se actualizează la fiecare rezervare.</p></div>
        </div>
      </div>
    </section>

    <section class="border-b border-pine-900/10 bg-cream">
      <div class="mx-auto grid max-w-[1540px] grid-cols-2 divide-x divide-y divide-pine-900/10 px-4 sm:px-6 lg:grid-cols-4 lg:divide-y-0 lg:px-8">
        <div class="py-5 pr-4 sm:py-6"><div class="flex items-center gap-3"><span class="availability-dot dot-open"></span><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/40">Disponibil</p><p class="mt-1 text-sm font-semibold">Locuri suficiente</p></div></div></div>
        <div class="py-5 pl-4 sm:px-6 sm:py-6"><div class="flex items-center gap-3"><span class="availability-dot dot-limited"></span><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/40">Locuri puține</p><p class="mt-1 text-sm font-semibold">Sub 20% din locuri</p></div></div></div>
        <div class="py-5 pr-4 sm:px-6 sm:py-6"><div class="flex items-center gap-3"><span class="availability-dot dot-soldout"></span><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/40">Complet</p><p class="mt-1 text-sm font-semibold">Alege altă zi</p></div></div></div>
        <div class="py-5 pl-4 sm:pl-6 sm:py-6"><div class="flex items-center gap-3"><span class="availability-dot dot-closed"></span><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/40">Închis</p><p class="mt-1 text-sm font-semibold">Fără program în ziua aceea</p></div></div></div>
      </div>
    </section>

    <section id="calendar" class="paper-grid py-16 sm:py-20 lg:py-28">
      <div class="mx-auto max-w-[1540px] px-4 sm:px-6 lg:px-8">
        <div class="reveal flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl"><p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700">Calendar operațional</p><h2 class="mt-3 font-display text-4xl font-semibold leading-[.95] tracking-[-.035em] sm:text-5xl lg:text-6xl">Ziua potrivită,<br><span class="italic text-pine-700">fără ghicit.</span></h2><p class="mt-5 max-w-2xl text-base leading-7 text-pine-900/62 sm:text-lg">Selectează o zi ca să vezi locurile rămase și intervalele libere pentru fiecare bilet, activitate sau închiriere.</p></div>
          <div class="flex items-center gap-2 self-start rounded-full border border-pine-900/10 bg-cream p-1 shadow-sm lg:self-auto"><button @click="changeMonth(-1)" :disabled="!canPrev" class="disabled:opacity-30 grid h-11 w-11 place-items-center rounded-full transition hover:bg-pine-900 hover:text-white" aria-label="Luna anterioară">←</button><div class="min-w-[150px] text-center"><p class="font-display text-lg font-semibold" x-text="monthLabel"></p><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/35" x-text="loadingMonth ? 'se încarcă…' : 'locuri reale'"></p></div><button @click="changeMonth(1)" class="grid h-11 w-11 place-items-center rounded-full transition hover:bg-pine-900 hover:text-white" aria-label="Luna următoare">→</button></div>
        </div>

        <div class="reveal reveal-delay-1 mt-9 flex gap-2 overflow-x-auto pb-2 scrollbar-hide" aria-label="Filtre experiențe">
          <template x-for="filter in filters" :key="filter.id">
            <button @click="setFilter(filter.id)" :class="{'is-active':activeFilter===filter.id}" class="filter-chip inline-flex flex-none items-center gap-2 whitespace-nowrap rounded-full border border-pine-900/12 bg-cream px-3 py-2.5 text-sm font-semibold">
              <span class="chip-icon grid h-7 w-7 place-items-center rounded-full bg-pine-900/6 text-xs" x-text="filter.icon"></span><span x-text="filter.label"></span>
            </button>
          </template>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(380px,.65fr)] xl:items-start">
          <div class="calendar-shell reveal rounded-[30px] border border-pine-900/10 bg-cream p-3 sm:rounded-[34px] sm:p-5 lg:p-6">
            <div class="calendar-grid mb-2 px-1 text-center"><template x-for="name in weekDays" :key="name"><div class="py-2 text-[8px] font-bold uppercase tracking-[.18em] text-pine-900/35 sm:text-[10px]" x-text="name"></div></template></div>
            <div class="calendar-grid">
              <template x-for="day in calendarDays" :key="day.key">
                <button @click="selectDay(day)" :disabled="day.outside || day.status==='closed'" :class="dayClasses(day)" class="calendar-day rounded-[18px] p-3 text-left" :aria-label="day.aria">
                  <div class="flex items-start justify-between gap-1"><span class="day-number font-display text-lg font-semibold" x-text="day.number"></span><span x-show="!day.outside && day.remaining" class="day-meta text-[8px] font-bold uppercase tracking-[.12em] opacity-45" x-text="day.remaining"></span></div>
                  <div x-show="!day.outside" class="mt-3">
                    <div class="flex items-center gap-1.5"><span class="availability-dot" :class="dotClass(day.status)"></span><span class="day-meta truncate text-[9px] font-bold uppercase tracking-[.11em]" x-text="statusLabel(day.status)"></span></div>
                    <div class="capacity-bar mt-3"><div class="capacity-fill" :style="`width:${day.capacity}%`" :class="capacityClass(day.status)"></div></div>
                    <p class="capacity-label mt-1.5 text-[9px] opacity-48" x-text="day.status==='closed' || !day.remaining ? '—' : (day.minPrice ? 'de la ' + day.minPrice : day.remaining + ' locuri')"></p>
                  </div>
                </button>
              </template>
            </div>
            <div class="mt-5 flex flex-col gap-3 border-t border-pine-900/8 pt-5 text-xs text-pine-900/48 sm:flex-row sm:items-center sm:justify-between"><p x-text="activeFilter==='all' ? 'Locurile însumează toate biletele și activitățile.' : 'Locurile sunt pentru categoria aleasă.'"></p><button @click="jumpToRecommended()" class="self-start font-bold text-pine-700 underline decoration-pine-700/25 underline-offset-4 sm:self-auto">Arată-mi cea mai liberă zi</button></div>
          </div>

          <aside class="reveal reveal-delay-2 xl:sticky xl:top-28">
            <div class="overflow-hidden rounded-[30px] bg-pine-950 text-white shadow-[0_34px_100px_-50px_rgb(var(--c-pine-950) / .9)] sm:rounded-[34px]">
              <div class="relative min-h-[190px] overflow-hidden p-5 sm:p-6">
                <img :src="selectedDay.image" alt="" class="absolute inset-0 h-full w-full object-cover opacity-38">
                <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/55 to-pine-950/20"></div>
                <div class="relative z-10"><div class="flex items-start justify-between gap-4"><div><p class="text-[9px] font-bold uppercase tracking-[.22em] text-white/45">Zi selectată</p><h3 class="mt-2 font-display text-3xl font-semibold leading-none" x-text="selectedDay.fullLabel"></h3></div><div class="rounded-2xl bg-acid px-3 py-2 text-center text-pine-950"><p class="text-[8px] font-bold uppercase tracking-[.14em]">Locuri</p><p class="font-display text-2xl font-semibold" x-text="selectedDay.remaining || 0"></p></div></div><div class="mt-8 flex flex-wrap gap-2"><span class="rounded-full border border-white/15 bg-white/[.08] px-3 py-1.5 text-xs font-semibold" x-text="statusLabel(selectedDay.status)"></span><span class="rounded-full border border-white/15 bg-white/[.08] px-3 py-1.5 text-xs font-semibold" x-text="selectedDay.hours"></span><span class="rounded-full border border-white/15 bg-white/[.08] px-3 py-1.5 text-xs font-semibold" x-text="selectedDay.flow"></span></div></div>
              </div>
              <div class="p-4 sm:p-5">
                <p x-show="!experiences.length" class="rounded-[22px] border border-white/10 bg-white/[.055] p-4 text-sm text-white/60">Biletele online nu sunt încă puse în vânzare.</p>

                <div x-show="selectedDay.status!=='soldout' && selectedDay.status!=='closed'" class="mt-4 space-y-2">
                  <template x-for="experience in visibleExperiences" :key="experience.id">
                    <div :class="{'is-open':openExperience===experience.id}" class="experience-row rounded-[22px] border border-white/10 bg-white/[.035]">
                      <button @click="toggleExperience(experience)" class="flex w-full items-center gap-3 p-3.5 text-left sm:p-4"><span class="grid h-10 w-10 flex-none place-items-center rounded-full text-sm" :style="`background:${experience.color};color:rgb(var(--c-pine-950))`" x-text="experience.icon"></span><span class="min-w-0 flex-1"><span class="block truncate font-semibold" x-text="experience.name"></span><span class="mt-0.5 block text-[10px] text-white/42" x-text="experience.note"></span></span><span class="text-right"><span class="block font-display text-lg text-acid" x-text="'de la ' + lvLei(experience.from * 100)"></span><span class="row-chevron mt-0.5 block text-xs text-white/40">⌄</span></span></button>
                      <div class="experience-row__content"><div><div class="border-t border-white/8 px-3.5 pb-4 pt-3 sm:px-4"><p class="mb-2 text-[8px] font-bold uppercase tracking-[.2em] text-white/35" x-text="slotsOf(experience).some(s => s.time_slot_start) ? 'Alege intervalul' : 'Acces toată ziua'"></p><p x-show="slotState[experience.id] === 'loading'" class="text-xs text-white/45">Se încarcă…</p><p x-show="slotState[experience.id] === 'done' && !slotsOf(experience).length" class="text-xs text-white/45">Fără locuri în ziua aleasă.</p><div class="grid grid-cols-2 gap-2 sm:grid-cols-3"><template x-for="slot in slotsOf(experience)" :key="slot.id"><button @click="slot.open && openBooking(experience, slot)" :disabled="!slot.open" class="slot-button rounded-xl border border-white/12 bg-white/[.06] px-2 py-2.5 text-center"><span class="block text-sm font-bold" x-text="slot.time_slot_start || 'Toată ziua'"></span><span class="mt-0.5 block text-[8px] text-white/42" x-text="slot.open ? slot.remaining + ' locuri' : 'Complet'"></span></button></template></div><a :href="experience.url" class="mt-3 inline-block text-[10px] font-bold uppercase tracking-[.16em] text-white/45 hover:text-acid">Detalii ↗</a></div></div></div>
                    </div>
                  </template>
                </div>
                <div x-show="selectedDay.status==='soldout' || selectedDay.status==='closed'" class="mt-4 rounded-[22px] border border-white/10 bg-white/[.055] p-4 text-center">
                  <p class="font-display text-xl" x-text="selectedDay.status==='soldout'?'Zi complet rezervată':'Fără program în această zi'"></p>
                  <p class="mt-2 text-xs leading-5 text-white/46">Alege o altă zi din calendar pentru a continua.</p>
                  <button @click="jumpToRecommended()" class="mt-4 rounded-full bg-acid px-5 py-2.5 text-sm font-bold text-pine-950">Arată-mi o zi liberă</button>
                </div>
              </div>
            </div>
            <p class="mt-3 px-2 text-center text-[10px] leading-5 text-pine-900/45">Locurile se actualizează la fiecare rezervare. Prețul final se confirmă în coș.</p>
          </aside>
        </div>
      </div>
    </section>

    <section id="ferestre" class="bg-cream py-16 sm:py-20 lg:py-28">
      <div class="mx-auto max-w-[1540px] px-4 sm:px-6 lg:px-8">
        <div class="reveal grid gap-7 lg:grid-cols-[.75fr_1.25fr] lg:items-end"><div><p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700">Ferestre recomandate</p><h2 class="mt-3 font-display text-4xl font-semibold leading-[.96] tracking-[-.035em] sm:text-5xl">Când pădurea<br><span class="italic text-pine-700">respiră mai larg.</span></h2></div><p class="max-w-2xl text-base leading-7 text-pine-900/60 sm:text-lg">Zilele din următoarele săptămâni cu cele mai multe locuri libere. Alege una și vezi ce poți rezerva.</p></div>
        <div class="reveal reveal-delay-1 mt-9 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <p x-show="!recommendedWindows.length" class="text-sm text-pine-900/55">Încă nu sunt zile cu locuri publicate.</p>
          <template x-for="window in recommendedWindows" :key="window.key">
            <button @click="selectRecommended(window)" class="heat-card group rounded-[25px] border border-pine-900/10 bg-oat p-4 text-left sm:p-5"><div class="flex items-start justify-between gap-3"><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/38" x-text="window.weekday"></p><p class="mt-1 font-display text-2xl font-semibold" x-text="window.date"></p></div><span class="rounded-full px-2.5 py-1 text-[9px] font-bold uppercase tracking-[.13em]" :style="`background:${window.badgeBg};color:${window.badgeText}`" x-text="window.flow"></span></div><div class="mt-6 flex items-end justify-between"><div><p class="text-xs text-pine-900/42" x-text="window.remaining + ' locuri libere'"></p><p class="mt-1 text-sm font-bold" x-text="window.min_price_cents != null ? 'de la ' + lvLei(window.min_price_cents) : ''"></p></div><div class="grid h-10 w-10 place-items-center rounded-full bg-pine-900 text-white transition group-hover:bg-acid group-hover:text-pine-950">↗</div></div></button>
          </template>
        </div>
      </div>
    </section>

    <section id="ghid" class="topo-dark relative overflow-hidden bg-pine-950 py-16 text-white sm:py-20 lg:py-28">
      <div class="pointer-events-none absolute -right-20 top-10 h-80 w-80 rounded-full border border-acid/10"></div>
      <div class="relative z-10 mx-auto max-w-[1540px] px-4 sm:px-6 lg:px-8">
        <div class="reveal grid gap-10 lg:grid-cols-[.8fr_1.2fr] lg:items-start">
          <div class="lg:sticky lg:top-28"><p class="text-[10px] font-bold uppercase tracking-[.24em] text-acid">Ghidul disponibilității</p><h2 class="mt-3 font-display text-4xl font-semibold leading-[.95] tracking-[-.035em] sm:text-5xl lg:text-6xl">Un calendar care<br><span class="outline-word italic">spune adevărul.</span></h2><p class="mt-6 max-w-xl text-base leading-7 text-white/58 sm:text-lg">Nu toate biletele funcționează la fel. Accesul e valabil toată ziua, iar activitățile și unele închirieri au intervale orare cu locuri limitate.</p></div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-[28px] border border-white/10 bg-white/[.055] p-5 sm:p-6"><span class="grid h-11 w-11 place-items-center rounded-full bg-acid font-bold text-pine-950">01</span><h3 class="mt-5 font-display text-2xl">Alege întâi ziua</h3><p class="mt-3 text-sm leading-6 text-white/52">Calendarul îți arată câte locuri mai sunt în fiecare zi, pentru toate produsele sau doar pentru categoria aleasă.</p></div>
            <div class="rounded-[28px] border border-white/10 bg-white/[.055] p-5 sm:p-6"><span class="grid h-11 w-11 place-items-center rounded-full bg-ember font-bold text-pine-950">02</span><h3 class="mt-5 font-display text-2xl">Apoi experiența</h3><p class="mt-3 text-sm leading-6 text-white/52">Activitățile cu oră fixă au intervalul lor; alegi ora direct din panoul zilei.</p></div>
            <div class="rounded-[28px] border border-white/10 bg-white/[.055] p-5 sm:p-6"><span class="grid h-11 w-11 place-items-center rounded-full bg-sky font-bold text-pine-950">03</span><h3 class="mt-5 font-display text-2xl">Păstrează marja</h3><p class="mt-3 text-sm leading-6 text-white/52">Vino cu 15–30 de minute înainte de interval, pentru intrare și, unde e cazul, echipare.</p></div>
            <div class="rounded-[28px] border border-white/10 bg-white/[.055] p-5 sm:p-6"><span class="grid h-11 w-11 place-items-center rounded-full bg-cream font-bold text-pine-950">04</span><h3 class="mt-5 font-display text-2xl">Meteo fără surprize</h3><p class="mt-3 text-sm leading-6 text-white/52">Dacă o activitate se suspendă din motive meteo, te anunțăm și îți propunem alt interval.</p></div>
          </div>
        </div>
      </div>
    </section>

    <section id="bilete" class="paper-grid py-16 sm:py-20 lg:py-28">
      <div class="mx-auto max-w-[1540px] px-4 sm:px-6 lg:px-8">
        <div class="reveal relative overflow-hidden rounded-[34px] bg-ember p-6 text-pine-950 sm:p-9 lg:p-12">
          <svg viewBox="0 0 500 300" class="pointer-events-none absolute -right-16 -top-8 h-[330px] w-[550px] opacity-16" fill="none"><path d="M20 236c75-150 173 21 247-91 67-102 120-29 206-115" stroke="rgb(var(--c-pine-950))" stroke-width="2" stroke-dasharray="9 13"/><circle cx="267" cy="145" r="82" stroke="rgb(var(--c-pine-950))"/><circle cx="267" cy="145" r="130" stroke="rgb(var(--c-pine-950))"/></svg>
          <div class="relative z-10 grid gap-7 lg:grid-cols-[1fr_auto] lg:items-end"><div><p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-950/55">Pregătit de plecare?</p><h2 class="mt-3 max-w-4xl font-display text-4xl font-semibold leading-[.94] tracking-[-.04em] sm:text-5xl lg:text-6xl">Alege ziua acum.<br><span class="italic">Pădurea se ocupă de restul.</span></h2><p class="mt-5 max-w-2xl text-base leading-7 text-pine-950/66" x-show="accessFrom">Biletul de acces pornește de la <span x-text="lvLei(accessFrom * 100)"></span>. Activitățile cu interval se adaugă în aceeași comandă.</p></div><a href="#calendar" class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-full bg-pine-950 px-7 py-4 font-bold text-white transition hover:-translate-y-0.5 sm:w-auto">Deschide calendarul <span>↑</span></a></div>
        </div>
      </div>
    </section>
  </main>

  
  <div x-show="bookingOpen" x-cloak class="fixed inset-0 z-[210]" role="dialog" aria-modal="true" aria-label="Rezervare rapidă">
    <button @click="closeBooking()" class="drawer-backdrop absolute inset-0 w-full" aria-label="Închide rezervarea"></button>
    <div class="drawer-panel safe-bottom absolute inset-x-0 bottom-0 max-h-[92vh] overflow-y-auto rounded-t-[30px] bg-cream p-5 sm:left-auto sm:right-0 sm:top-0 sm:h-full sm:max-h-none sm:w-[460px] sm:rounded-none sm:p-7">
      <div class="flex items-start justify-between gap-4"><div><p class="text-[9px] font-bold uppercase tracking-[.2em] text-pine-700">Rezervare rapidă</p><h2 class="mt-2 font-display text-3xl font-semibold">Adaugă în ziua ta</h2></div><button @click="closeBooking()" class="grid h-10 w-10 flex-none place-items-center rounded-full border border-pine-900/12 text-xl">×</button></div>
      <div x-show="!added">
      <div class="mt-6 overflow-hidden rounded-[24px] bg-pine-950 text-white"><div class="p-5"><p class="text-[8px] font-bold uppercase tracking-[.2em] text-white/35" x-text="selectedDay.fullLabel"></p><h3 class="mt-2 font-display text-2xl" x-text="bookingItem.name"></h3><p class="mt-2 text-sm text-white/55" x-text="bookingItem.slotLabel"></p><div class="mt-5 flex items-end justify-between"><span class="text-sm text-white/46">Preț / bucată</span><span class="font-display text-3xl text-acid" x-text="lvLei(unitCents)"></span></div></div></div>
      <div class="mt-6" x-show="bookingItem.variants && bookingItem.variants.length > 1"><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/42">Durata</p><div class="mt-3 grid grid-cols-2 gap-2"><template x-for="(v, vi) in bookingItem.variants" :key="vi"><button type="button" @click="variant = vi" class="rounded-2xl border border-pine-900/12 bg-white p-3 text-left text-sm font-semibold" :class="variant === vi ? 'bg-pine-900 text-white' : ''"><span class="block" x-text="v.label || (v.duration_minutes + ' min')"></span><span class="block text-xs font-normal opacity-60" x-text="lvLei(v.price_cents)"></span></button></template></div></div>
      <div class="mt-6"><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/42">Cantitate</p><div class="mt-3 flex items-center justify-between rounded-2xl border border-pine-900/12 bg-white p-3"><div><p class="font-semibold" x-text="bookingItem.filter === 'rental' ? 'Bucăți' : 'Persoane'"></p><p class="text-xs text-pine-900/45" x-text="'mai sunt ' + maxGuests + ' locuri'"></p></div><div class="flex items-center gap-3"><button @click="guests=Math.max(1,guests-1)" class="grid h-9 w-9 place-items-center rounded-full border border-pine-900/12">−</button><span class="w-5 text-center font-bold" x-text="guests"></span><button @click="guests=Math.min(maxGuests,guests+1)" class="grid h-9 w-9 place-items-center rounded-full bg-pine-900 text-white">+</button></div></div></div>
      <div class="mt-6 rounded-2xl bg-oat p-4"><div class="flex justify-between text-sm"><span class="text-pine-900/50" x-text="guests + ' × ' + lvLei(unitCents)"></span><span class="font-semibold" x-text="lvLei(subtotalCents)"></span></div><div class="mt-3 flex justify-between border-t border-pine-900/10 pt-3"><span class="font-bold">Total</span><span class="font-display text-2xl font-semibold" x-text="lvLei(subtotalCents)"></span></div></div>
      <button @click="confirmBooking()" class="mt-5 w-full rounded-full bg-acid px-6 py-4 font-bold text-pine-950 transition hover:-translate-y-0.5">Adaugă în coș</button>
      <p class="mt-3 text-center text-[10px] leading-5 text-pine-900/42">Locul se blochează când finalizezi comanda.</p>
      </div>
      <div x-show="added" x-cloak class="mt-8 rounded-[24px] bg-pine-950 p-6 text-center text-white"><span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-acid text-xl font-bold text-pine-950">✓</span><p class="mt-4 font-display text-2xl" x-text="bookingItem.name + ' e în coș.'"></p><p class="mt-2 text-sm text-white/55" x-text="selectedDay.fullLabel + (bookingItem.slot && bookingItem.slot.time_slot_start ? ' · ' + bookingItem.slot.time_slot_start : '')"></p><div class="mt-6 grid gap-2"><a href="/cos" class="rounded-full bg-acid px-6 py-3.5 font-bold text-pine-950">Mergi la coș</a><button type="button" @click="closeBooking()" class="rounded-full border border-white/20 px-6 py-3.5 font-semibold">Adaugă altceva</button></div></div>
    </div>
  </div>

  <div x-show="toast" x-cloak x-transition class="fixed bottom-4 left-1/2 z-[230] w-[calc(100%-32px)] max-w-md -translate-x-1/2 rounded-2xl bg-pine-950 px-4 py-3 text-center text-sm font-semibold text-white shadow-2xl safe-bottom" x-text="toastMessage"></div>

  <script>
    function calendarPage(){
      const MONTHS = ['ianuarie','februarie','martie','aprilie','mai','iunie','iulie','august','septembrie','octombrie','noiembrie','decembrie'];
      const WD = ['Dum','Lun','Mar','Mie','Joi','Vin','Sâm'];
      const WDL = ['Duminică','Luni','Marți','Miercuri','Joi','Vineri','Sâmbătă'];
      const pad = n => String(n).padStart(2,'0');
      const keyOf = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
      const parse = k => { const p = k.split('-'); return new Date(+p[0], +p[1]-1, +p[2]); };
      const lei = c => window.TS ? TS.lei((Number(c)||0)/100) : ((Number(c)||0)/100) + ' lei';
      const now = new Date(); now.setHours(0,0,0,0);
      const STATUS = { available:'open', limited:'limited', sold_out:'soldout', closed:'closed', unavailable:'closed' };
      return {
        menu:false, bookingOpen:false, added:false, toast:false, toastMessage:'', activeFilter:'all', openExperience:null, guests:2, variant:0,
        currentMonth:now.getMonth(), currentYear:now.getFullYear(), selectedKey:null, loadingMonth:false,
        bookingItem:{name:'',slotLabel:'',variants:[],filter:'',slot:null},
        weekDays:['Lun','Mar','Mie','Joi','Vin','Sâm','Dum'],
        filters: <?= json_encode($calFilters, $calFlags) ?>,
        experiences: <?= json_encode($calItems, $calFlags) ?>,
        event: <?= json_encode($calEventJs, $calFlags) ?>,
        hours: <?= json_encode($calHours, $calFlags) ?>,
        avail: {},          // 'all' | categorie → { 'YYYY-MM': { 'YYYY-MM-DD': {...} } }
        slots: {},          // `${ticketTypeId}|${date}` → [...]
        slotState: {},      // ticketTypeId → 'loading' | 'done'
        lvLei: lei,
        init(){
          this.loadMonth().then(() => {
            const first = this.calendarDays.find(d => !d.outside && (d.status === 'open' || d.status === 'limited'));
            if (first) this.selectedKey = first.key; else this.peekNext();
          });
          requestAnimationFrame(() => { this.setupReveal(); this.setupProgress(); });
          this.$watch('selectedKey', () => { this.slotState = {}; if (this.openExperience) this.loadSlots(this.experiences.find(e => e.id === this.openExperience)); });
        },
        get monthKey(){ return `${this.currentYear}-${pad(this.currentMonth+1)}`; },
        get canPrev(){ return this.monthKey > keyOf(now).slice(0,7); },
        // Disponibilitatea lunii: pe toate produsele sau, la un filtru, însumată pe produsele categoriei
        async loadMonth(month){
          month = month || this.monthKey;
          const f = this.activeFilter;
          this.avail[f] = this.avail[f] || {};
          if (this.avail[f][month]) return;
          this.loadingMonth = true;
          const fetchOne = async tt => {
            try {
              const r = await (await fetch('/api/proxy.php?action=leisure-availability&month=' + month + (tt ? '&ticket_type=' + tt : ''))).json();
              return (r && r.success && r.data && r.data.dates) || {};
            } catch (e) { return {}; }
          };
          let dates = {};
          if (f === 'all') {
            dates = await fetchOne(null);
          } else {
            const parts = await Promise.all(this.experiences.filter(e => e.filter === f).map(e => fetchOne(e.ticket_type_id)));
            parts.forEach(p => Object.keys(p).forEach(k => {
              const a = dates[k], b = p[k];
              if (!a) { dates[k] = Object.assign({}, b); return; }
              a.remaining += b.remaining;
              if (b.min_price_cents != null && (a.min_price_cents == null || b.min_price_cents < a.min_price_cents)) a.min_price_cents = b.min_price_cents;
              const rank = { available:4, limited:3, sold_out:2, closed:1, unavailable:0 };
              if ((rank[b.status]||0) > (rank[a.status]||0)) a.status = b.status;
            }));
          }
          this.avail[f] = Object.assign({}, this.avail[f], { [month]: dates });
          this.loadingMonth = false;
        },
        async peekNext(){
          // Dacă luna curentă nu mai are zile libere, mergi la următoarea
          const next = new Date(this.currentYear, this.currentMonth + 1, 1);
          await this.loadMonth(keyOf(next).slice(0,7));
          if (this.upcoming.length) { const d = parse(this.upcoming[0].key); this.currentYear = d.getFullYear(); this.currentMonth = d.getMonth(); this.selectedKey = this.upcoming[0].key; }
        },
        get monthData(){ return ((this.avail[this.activeFilter] || {})[this.monthKey]) || {}; },
        get allDays(){ const out = {}; Object.values(this.avail[this.activeFilter] || {}).forEach(m => Object.assign(out, m)); return out; },
        get upcoming(){
          const all = this.allDays, today = keyOf(now);
          return Object.keys(all).sort().filter(k => k >= today && ['available','limited'].includes(all[k].status)).map(k => this.windowOf(k, all[k]));
        },
        windowOf(k, d){ const dt = parse(k); return Object.assign({ key:k, short:`${WD[dt.getDay()]}, ${dt.getDate()}`, long:`${WDL[dt.getDay()]}, ${dt.getDate()} ${MONTHS[dt.getMonth()]}`, weekday:WDL[dt.getDay()], date:`${dt.getDate()} ${MONTHS[dt.getMonth()]}` }, d); },
        get nextOpen(){ return this.upcoming[0] || null; },
        get freest(){ return this.upcoming.slice(0, 21).reduce((best, d) => (!best || d.remaining > best.remaining) ? d : best, null); },
        get recommendedWindows(){
          return this.upcoming.slice(0, 28).slice().sort((a, b) => b.remaining - a.remaining).slice(0, 4).sort((a, b) => a.key < b.key ? -1 : 1).map(w => Object.assign({}, w, {
            flow: this.flowLabel(w.status),
            badgeBg: w.status === 'limited' ? '#f27b4a' : 'rgb(var(--c-primary))',
            badgeText: 'rgb(var(--c-pine-950))'
          }));
        },
        get accessFrom(){ const a = this.experiences.filter(e => e.filter === 'access').map(e => e.from); return a.length ? Math.min.apply(null, a) : 0; },
        flowLabel(st){ return { available:'Lejer', open:'Lejer', limited:'Aglomerat', sold_out:'Complet', soldout:'Complet' }[st] || '—'; },
        get monthLabel(){ return (MONTHS[this.currentMonth] + ' ' + this.currentYear).replace(/^./, m => m.toUpperCase()); },
        get calendarDays(){
          const first = new Date(this.currentYear, this.currentMonth, 1); const last = new Date(this.currentYear, this.currentMonth + 1, 0);
          const mondayIndex = (first.getDay() + 6) % 7; const total = Math.ceil((mondayIndex + last.getDate()) / 7) * 7; const days = [];
          const maxRemaining = Math.max(1, ...Object.values(this.monthData).map(d => d.remaining || 0));
          for (let i = 0; i < total; i++) {
            const date = new Date(this.currentYear, this.currentMonth, i - mondayIndex + 1);
            days.push(this.makeDayData(date, keyOf(date), date.getMonth() !== this.currentMonth, maxRemaining));
          }
          return days;
        },
        makeDayData(date, key, outside, maxRemaining){
          const d = this.monthData[key];
          const past = key < keyOf(now);
          let status = d ? (STATUS[d.status] || 'closed') : 'closed';
          if (outside || past) status = 'closed';
          const remaining = status === 'closed' ? 0 : (d ? d.remaining : 0);
          const fullLabel = `${WDL[date.getDay()]}, ${date.getDate()} ${MONTHS[date.getMonth()]}`;
          return { key, number: date.getDate(), outside, today: key === keyOf(now), fullLabel, status, remaining,
            capacity: status === 'closed' ? 0 : Math.max(6, Math.round(remaining / maxRemaining * 100)),
            minPrice: d && d.min_price_cents != null && status !== 'closed' ? lei(d.min_price_cents) : '',
            hours: status === 'closed' ? 'Fără program' : (this.hours || 'Program normal'),
            flow: this.flowLabel(status), image: this.dayImage(date.getDate()),
            aria: `${fullLabel}, ${this.statusLabel(status)}${remaining ? ', ' + remaining + ' locuri' : ''}` };
        },
        dayImage(number){ const images = <?= json_encode(array_map(fn ($id) => lv_photo($id, 1000), LV_PHOTOS['access']), $calFlags) ?>; return images[number % images.length]; },
        dateKey(date){ return keyOf(date); },
        get selectedDay(){ return this.calendarDays.find(day => day.key === this.selectedKey && !day.outside) || this.calendarDays.find(day => !day.outside && day.status !== 'closed') || this.calendarDays.find(day => !day.outside) || this.calendarDays[0]; },
        get visibleExperiences(){ return this.activeFilter === 'all' ? this.experiences : this.experiences.filter(item => item.filter === this.activeFilter); },
        slotsOf(exp){ return this.slots[exp.ticket_type_id + '|' + this.selectedDay.key] || []; },
        async loadSlots(exp){
          if (!exp) return;
          const date = this.selectedDay.key, k = exp.ticket_type_id + '|' + date;
          if (this.slots[k]) { this.slotState[exp.id] = 'done'; return; }
          this.slotState = Object.assign({}, this.slotState, { [exp.id]: 'loading' });
          let list = [];
          try {
            const r = await (await fetch('/api/proxy.php?action=leisure-slots&ticket_type=' + exp.ticket_type_id + '&date=' + date)).json();
            list = (r && r.success && r.data && r.data.slots || []).map(s => Object.assign({}, s, { open: ['available','limited'].includes(s.status) && s.remaining > 0 }));
          } catch (e) {}
          this.slots = Object.assign({}, this.slots, { [k]: list });
          this.slotState = Object.assign({}, this.slotState, { [exp.id]: 'done' });
        },
        toggleExperience(exp){ this.openExperience = this.openExperience === exp.id ? null : exp.id; if (this.openExperience) this.loadSlots(exp); },
        async setFilter(id){ this.activeFilter = id; this.openExperience = null; await this.loadMonth(); },
        selectDay(day){ if (day.outside || day.status === 'closed') return; this.selectedKey = day.key; this.openExperience = null; requestAnimationFrame(() => { if (window.innerWidth < 1280) { document.querySelector('aside')?.scrollIntoView({ behavior:'smooth', block:'start' }); } }); },
        async changeMonth(delta){
          const next = new Date(this.currentYear, this.currentMonth + delta, 1);
          if (delta < 0 && keyOf(next).slice(0,7) < keyOf(now).slice(0,7)) return;
          this.currentYear = next.getFullYear(); this.currentMonth = next.getMonth();
          await this.loadMonth();
          const first = this.calendarDays.find(day => !day.outside && day.status !== 'closed'); if (first) this.selectedKey = first.key;
        },
        dayClasses(day){ return { 'is-selected': day.key === this.selectedKey && !day.outside, 'is-outside': day.outside, 'is-closed': day.status === 'closed', 'is-soldout': day.status === 'soldout', 'is-today': day.today }; },
        dotClass(status){ return { 'dot-open': status === 'open', 'dot-limited': status === 'limited', 'dot-soldout': status === 'soldout', 'dot-closed': status === 'closed' }; },
        capacityClass(status){ return { 'bg-[#61a87e]': status === 'open', 'bg-ember': status === 'limited', 'bg-[#c55745]': status === 'soldout', 'bg-[#9d998e]': status === 'closed' }; },
        statusLabel(status){ return { open:'Disponibil', limited:'Locuri puține', soldout:'Complet', closed:'Fără program' }[status] || 'Disponibil'; },
        jumpToRecommended(){ const w = this.freest; if (w) this.selectRecommended(w); },
        selectRecommended(w){ const d = parse(w.key); this.currentYear = d.getFullYear(); this.currentMonth = d.getMonth(); this.loadMonth().then(() => { this.selectedKey = w.key; }); document.querySelector('#calendar')?.scrollIntoView({ behavior:'smooth', block:'start' }); },
        get unitCents(){ const v = (this.bookingItem.variants || [])[this.variant]; if (v) return v.price_cents; return this.bookingItem.slot ? this.bookingItem.slot.price_cents : Math.round(this.bookingItem.from * 100 || 0); },
        get subtotalCents(){ return this.unitCents * this.guests; },
        get maxGuests(){ return Math.max(1, Math.min(window.TS ? TS.MAX_PER_TYPE : 10, this.bookingItem.slot ? this.bookingItem.slot.remaining : 10)); },
        openBooking(exp, slot){
          this.bookingItem = Object.assign({}, exp, { slot, slotLabel: slot.time_slot_start ? 'Interval ' + slot.time_slot_start + (slot.time_slot_end ? '–' + slot.time_slot_end : '') : 'Valabil toată ziua' });
          this.variant = 0; this.added = false;
          this.guests = Math.min(exp.filter === 'rental' ? 1 : 2, this.maxGuests);
          this.bookingOpen = true; document.documentElement.style.overflow = 'hidden';
        },
        closeBooking(){ this.bookingOpen = false; this.added = false; document.documentElement.style.overflow = ''; },
        confirmBooking(){
          const b = this.bookingItem, sl = b.slot, v = (b.variants || [])[this.variant], date = this.selectedDay.key;
          if (!sl || !window.TS) return;
          const line = { ticket_type_id: b.ticket_type_id, name: b.name + (v ? ' · ' + (v.label || v.duration_minutes + ' min') : ''), price: this.unitCents / 100, qty: this.guests,
            visit_date: date, slot_time: sl.time_slot_start || null, capacity_id: sl.id, duration_minutes: v ? v.duration_minutes : null,
            when: this.selectedDay.fullLabel + (sl.time_slot_start ? ' · ' + sl.time_slot_start : '') };
          const prev = TS.Cart.get(), same = prev && prev.event && prev.event.id === this.event.id && !(prev.seats || []).length;
          const items = same ? prev.items.slice() : [];
          const k = TS.Cart.lineKey(line), found = items.find(i => TS.Cart.lineKey(i) === k && i.duration_minutes == line.duration_minutes);
          if (found) found.qty = Math.min(this.maxGuests, found.qty + line.qty); else items.push(line);
          TS.Cart.set({ event: Object.assign({ date }, this.event), items, expires_at: same ? prev.expires_at : null, coupon_code: same ? (prev.coupon_code || null) : null });
          this.added = true;
        },
        setupReveal(){const nodes=[...document.querySelectorAll('.reveal')];if(!('IntersectionObserver'in window)){nodes.forEach(n=>n.classList.add('is-visible'));return}const io=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');io.unobserve(entry.target)}}),{threshold:.12});nodes.forEach(node=>io.observe(node))},
        setupProgress(){const bar=document.getElementById('pageProgress');if(!bar)return;const update=()=>{const max=document.documentElement.scrollHeight-innerHeight;const ratio=max>0?scrollY/max:0;bar.style.transform=`scaleX(${Math.min(1,Math.max(0,ratio))})`};update();addEventListener('scroll',update,{passive:true})}
      }
    }
  </script>
<?php include ts_layout('footer');
