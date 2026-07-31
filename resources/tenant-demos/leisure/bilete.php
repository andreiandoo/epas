<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// Eveniment reprezentativ pentru pachete (ticket types)
$pkgList = tc_events(api_get('/tenant-client/events', ['per_page' => 1]));
$pkgEv = $pkgList[0] ?? null;
if ($pkgEv && empty($pkgEv['ticket_types']) && ! empty($pkgEv['slug'])) {
    $full = tc_event($pkgEv['slug']);
    if ($full) { $pkgEv = $full; }
}
$pkgFallbackImg = 'https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1400&q=86';
$pkgEventJs = json_encode([
    'id'    => $pkgEv['id'] ?? null,
    'title' => $pkgEv['title'] ?? 'Nordvale',
    'image' => $pkgEv ? asset_url($pkgEv['hero_image_url'] ?? $pkgEv['poster_url'] ?? null, $pkgFallbackImg) : $pkgFallbackImg,
    'venue' => $pkgEv['venue']['name'] ?? 'Nordvale',
], JSON_UNESCAPED_UNICODE);

$pkgStyles = [
    ['icon' => '⌁', 'color' => '#dffc62', 'textColor' => '#061a15', 'iconBg' => 'rgba(6,26,21,.12)', 'kicker' => 'Ritm liber',        'bestFor' => 'Acces general'],
    ['icon' => '↗', 'color' => '#f27b4a', 'textColor' => '#ffffff', 'iconBg' => 'rgba(255,255,255,.18)', 'kicker' => 'Recomandat',      'bestFor' => 'Cupluri & prieteni'],
    ['icon' => '⌂', 'color' => '#b8dce0', 'textColor' => '#061a15', 'iconBg' => 'rgba(6,26,21,.1)',  'kicker' => 'Cel mai bun raport', 'bestFor' => 'Familii'],
];
$pkgItems = [];
foreach (array_values($pkgEv['ticket_types'] ?? []) as $i => $tt) {
    $s = $pkgStyles[$i % 3];
    $pkgItems[] = [
        'id'             => 'tt_' . ($tt['id'] ?? $i),
        'ticket_type_id' => $tt['id'] ?? null,
        'name'           => $tt['name'] ?? 'Bilet',
        'kicker'         => $s['kicker'],
        'short'          => 'Acces ' . ($tt['name'] ?? 'general') . ' pentru o zi în Nordvale.',
        'description'    => 'Bilet ' . ($tt['name'] ?? 'general') . ' pentru accesul în parc.',
        'price'          => (float) ($tt['price'] ?? 0),
        'icon'           => $s['icon'],
        'color'          => $s['color'],
        'textColor'      => $s['textColor'],
        'iconBg'         => $s['iconBg'],
        'bestFor'        => $s['bestFor'],
        'includes'       => ['Acces general în parc', 'Trasee pietonale și belvederi'],
    ];
}
$pkgItemsJs = ! empty($pkgItems) ? json_encode($pkgItems, JSON_UNESCAPED_UNICODE) : '[]';
?>
<!DOCTYPE html>
<html lang="ro" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#061a15">
  <title>Bilete și pachete — Nordvale</title>
  <meta name="description" content="Alege accesul potrivit pentru o zi în Nordvale: bilete simple, pachete de aventură și opțiuni pentru familii.">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
  <style id="tailwind-build">
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

</style>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>

  <style>
    :root {
      --pine-950:#061a15;
      --pine-900:#09251d;
      --pine-800:#123b30;
      --pine-700:#1b5242;
      --acid:#dffc62;
      --ember:#f27b4a;
      --cream:#fffdf6;
      --oat:#f0ecdf;
      --moss:#b7c9a3;
      --sky:#b8dce0;
      --ink:#16231e;
      --line:rgba(9,37,29,.13);
    }

    * { box-sizing:border-box; }
    html { background:var(--cream); }
    body {
      margin:0;
      overflow-x:hidden;
      background:var(--cream);
      color:var(--ink);
      font-family:'DM Sans',sans-serif;
      text-rendering:optimizeLegibility;
    }
    [x-cloak] { display:none!important; }
    button,a { -webkit-tap-highlight-color:transparent; }
    button:focus-visible,a:focus-visible,input:focus-visible { outline:3px solid rgba(223,252,98,.75); outline-offset:3px; }
    ::selection { background:var(--acid);color:var(--pine-950); }
    .font-display { font-family:'Fraunces',serif; }
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
      background:rgba(6,26,21,.82);
      border:1px solid rgba(255,255,255,.12);
      box-shadow:0 22px 70px -42px rgba(0,0,0,.88);
      backdrop-filter:blur(22px) saturate(135%);
      -webkit-backdrop-filter:blur(22px) saturate(135%);
    }
    .topo-dark {
      background-color:var(--pine-950);
      background-image:
        radial-gradient(circle at 12% 18%,rgba(223,252,98,.15),transparent 18%),
        radial-gradient(circle at 86% 10%,rgba(242,123,74,.12),transparent 18%),
        url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23fff' stroke-opacity='.055' stroke-width='1.15'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3C/g%3E%3C/svg%3E");
      background-size:auto,auto,820px 820px;
    }
    .paper-grid {
      background-image:linear-gradient(rgba(9,37,29,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(9,37,29,.045) 1px,transparent 1px);
      background-size:30px 30px;
    }
    .grain:after {
      content:'';position:absolute;inset:0;z-index:2;pointer-events:none;opacity:.075;mix-blend-mode:soft-light;
      background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.58'/%3E%3C/svg%3E");
    }
    .outline-word { color:transparent;-webkit-text-stroke:1px rgba(255,255,255,.46); }
    .route-line { fill:none;stroke:var(--acid);stroke-width:2.4;stroke-linecap:round;stroke-dasharray:9 13;animation:route 15s linear infinite;filter:drop-shadow(0 0 8px rgba(223,252,98,.35)); }
    @keyframes route { to { stroke-dashoffset:-260; } }

    .hero-visual { min-height:530px; }
    .ticket-sculpture { position:absolute;border-radius:30px;box-shadow:0 38px 90px -48px rgba(0,0,0,.92);transform-style:preserve-3d; }
    .ticket-sculpture:before,.ticket-sculpture:after { content:'';position:absolute;top:50%;width:28px;height:28px;border-radius:50%;background:var(--pine-950);transform:translateY(-50%); }
    .ticket-sculpture:before { left:-14px; }
    .ticket-sculpture:after { right:-14px; }
    .ticket-dash { border-top:1px dashed rgba(9,37,29,.27); }
    .ticket-dash-light { border-top:1px dashed rgba(255,255,255,.28); }
    .float-one { animation:floatOne 6s ease-in-out infinite; }
    .float-two { animation:floatTwo 7.5s ease-in-out infinite; }
    @keyframes floatOne { 50% { transform:translateY(-9px) rotate(-7deg); } }
    @keyframes floatTwo { 50% { transform:translateY(9px) rotate(6deg); } }

    .trust-strip { border-top:1px solid rgba(255,255,255,.1);border-bottom:1px solid rgba(255,255,255,.1); }
    .trust-item + .trust-item { border-left:1px solid rgba(255,255,255,.1); }

    .choice-card {
      position:relative;overflow:hidden;border:1px solid var(--line);background:rgba(255,253,246,.92);
      box-shadow:0 24px 62px -48px rgba(6,26,21,.62);transition:transform .3s ease,border-color .3s ease,box-shadow .3s ease;
    }
    .choice-card:hover { transform:translateY(-5px);border-color:rgba(9,37,29,.3);box-shadow:0 38px 80px -50px rgba(6,26,21,.72); }
    .choice-card.selected { border-color:var(--pine-900);box-shadow:0 0 0 2px var(--pine-900),0 36px 80px -52px rgba(6,26,21,.82); }
    .choice-card:before,.choice-card:after { content:'';position:absolute;top:168px;width:26px;height:26px;border-radius:50%;background:var(--oat);z-index:4; }
    .choice-card:before { left:-14px; }.choice-card:after { right:-14px; }
    .choice-perf { position:absolute;left:20px;right:20px;top:181px;border-top:1px dashed rgba(9,37,29,.2); }
    .choice-visual { min-height:182px; }
    .choice-icon { transition:transform .35s cubic-bezier(.2,.8,.2,1); }
    .choice-card:hover .choice-icon { transform:rotate(8deg) scale(1.07); }
    .choice-description { min-height:52px; }

    .audience-chip { border:1px solid var(--line);background:white;transition:.22s ease; }
    .audience-chip:hover { transform:translateY(-2px);border-color:rgba(9,37,29,.3); }
    .audience-chip.active { background:var(--pine-900);border-color:var(--pine-900);color:white;box-shadow:0 16px 38px -28px rgba(6,26,21,.9); }
    .audience-chip.active .audience-badge { background:var(--acid);color:var(--pine-950); }

    .step-card { border:1px solid var(--line);background:rgba(255,255,255,.84); }
    .step-card.active { border-color:var(--pine-900);box-shadow:0 18px 60px -46px rgba(6,26,21,.65); }
    .step-index { background:var(--oat);color:var(--pine-900); }
    .step-card.active .step-index { background:var(--acid);color:var(--pine-950); }
    .qty-control { display:flex;align-items:center;gap:10px; }
    .qty-control button { width:38px;height:38px;border-radius:999px;border:1px solid var(--line);display:grid;place-items:center;background:white;font-size:20px;line-height:1;transition:.2s ease; }
    .qty-control button:hover { background:var(--pine-900);color:white;border-color:var(--pine-900); }
    .addon-card { border:1px solid var(--line);background:white;transition:.22s ease; }
    .addon-card:hover { transform:translateY(-2px);border-color:rgba(9,37,29,.3); }
    .addon-card.active { border-color:var(--pine-900);background:rgba(223,252,98,.23);box-shadow:0 15px 45px -36px rgba(6,26,21,.7); }
    .summary-panel { box-shadow:0 34px 90px -55px rgba(6,26,21,.8); }

    .intent-card { border:1px solid var(--line);background:white;transition:.25s ease; }
    .intent-card:hover { transform:translateY(-3px);border-color:rgba(9,37,29,.3); }
    .gift-panel { position:relative;overflow:hidden;box-shadow:0 38px 100px -58px rgba(6,26,21,.82); }
    .gift-panel:before { content:'';position:absolute;inset:-55%;background:conic-gradient(from 90deg,transparent,rgba(223,252,98,.18),transparent 33%,rgba(184,220,224,.16),transparent 65%);animation:spin 22s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }

    .faq-item { border-top:1px solid var(--line); }
    .faq-answer { display:grid;grid-template-rows:0fr;transition:grid-template-rows .3s ease; }
    .faq-answer>div { overflow:hidden; }
    .faq-item.open .faq-answer { grid-template-rows:1fr; }
    .faq-plus { transition:transform .25s ease; }
    .faq-item.open .faq-plus { transform:rotate(45deg); }

    .drawer-backdrop { background:rgba(6,26,21,.65);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px); }
    .drawer-panel { box-shadow:-52px 0 120px -64px rgba(6,26,21,.9); }
    .toast { box-shadow:0 24px 70px -34px rgba(6,26,21,.9); }

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
  </style>
</head>
<body x-data="ticketsPage()" x-init="init()" :class="drawerOpen || giftOpen ? 'overflow-hidden' : ''" class="antialiased">
  <div id="progress" class="fixed left-0 top-0 z-50 h-1 w-full origin-left" style="background:var(--acid);transform:scaleX(0)"></div>

  <header class="safe-top fixed inset-x-0 top-0 z-40 px-3 sm:px-5 lg:px-7">
    <nav class="nav-shell mx-auto flex max-w-screen-2xl items-center justify-between gap-2 rounded-2xl px-3 py-2 text-white sm:rounded-3xl sm:px-4 lg:px-5">
      <a href="/" class="flex min-w-0 items-center gap-2 sm:gap-3" aria-label="Nordvale — Acasă">
        <span class="grid h-10 w-10 flex-none place-items-center rounded-full" style="background:var(--pine-800)">
          <svg viewBox="0 0 48 48" class="h-7 w-7" fill="none" aria-hidden="true"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#DFFC62"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#FFFDF6"/></svg>
        </span>
        <span class="min-w-0"><span class="block truncate font-display text-xl font-semibold leading-none">Nordvale</span><span class="nav-subtitle mt-1 block truncate text-xs font-bold uppercase tracking-widest text-white opacity-40">wild park · forest reserve</span></span>
      </a>
      <div class="hidden items-center gap-7 text-sm lg:flex">
        <a href="/experiente" class="text-white opacity-70 transition hover:opacity-100">Experiențe</a>
        <a href="/planifica" class="text-white opacity-70 transition hover:opacity-100">Planifică vizita</a>
        <a href="/calendar" class="text-white opacity-70 transition hover:opacity-100">Calendar</a>
        <a href="#pachete" class="font-semibold" style="color:var(--acid)">Bilete & pachete</a>
      </div>
      <div class="flex flex-none items-center gap-2">
        <button type="button" @click="scrollBuilder()" class="nav-cta whitespace-nowrap rounded-full px-4 py-2 text-sm font-bold sm:px-5" style="background:var(--acid);color:var(--pine-950)">Bilete</button>
        <button type="button" @click="menu=!menu" class="grid h-10 w-10 place-items-center rounded-full border border-white border-opacity-10 lg:hidden" :aria-expanded="menu" aria-label="Deschide meniul">
          <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path x-show="!menu" d="M4 7h16M4 12h16M4 17h16"/><path x-show="menu" d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
      </div>
    </nav>
    <div x-show="menu" x-cloak x-transition.opacity class="nav-shell mx-auto mt-2 max-w-screen-2xl rounded-2xl p-3 text-white lg:hidden">
      <div class="grid gap-1 text-sm">
        <a href="/experiente" class="rounded-xl px-4 py-3 hover:bg-white hover:bg-opacity-10">Experiențe</a>
        <a href="/planifica" class="rounded-xl px-4 py-3 hover:bg-white hover:bg-opacity-10">Planifică vizita</a>
        <a href="/calendar" class="rounded-xl px-4 py-3 hover:bg-white hover:bg-opacity-10">Calendar</a>
        <a href="#pachete" @click="menu=false" class="rounded-xl px-4 py-3 font-semibold" style="color:var(--acid)">Bilete & pachete</a>
      </div>
    </div>
  </header>

  <main>
    <section class="topo-dark grain relative overflow-hidden pb-16 pt-32 text-white sm:pb-24 sm:pt-40 lg:min-h-screen lg:flex lg:items-center">
      <svg viewBox="0 0 1000 520" class="pointer-events-none absolute bottom-0 left-0 w-full opacity-30" fill="none" aria-hidden="true"><path class="route-line" d="M-30 434C130 292 226 442 374 274S627 110 795 207 909 150 1038 42"/><circle cx="374" cy="274" r="9" fill="#DFFC62"/><circle cx="795" cy="207" r="7" fill="#F27B4A"/></svg>
      <div class="page-shell relative z-10">
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center lg:gap-16">
          <div class="reveal">
            <div class="inline-flex items-center gap-2 rounded-full border border-white border-opacity-10 bg-white bg-opacity-5 px-3 py-2 text-xs font-bold uppercase tracking-widest text-white text-opacity-60"><span class="h-2 w-2 rounded-full" style="background:var(--acid)"></span> Sezon 2026 · schimbare gratuită a datei</div>
            <h1 class="hero-title mt-6 max-w-3xl font-display text-5xl font-semibold leading-none tracking-tight sm:text-6xl lg:text-7xl xl:text-8xl">Alege ce vrei<br><span class="outline-word italic">să simți.</span></h1>
            <p class="mt-6 max-w-xl text-base leading-7 text-white text-opacity-60 sm:text-lg">Acces relaxat în rezervație, aventură la înălțime sau o zi completă cu totul pregătit. Pachetul trebuie să se potrivească ritmului tău, nu invers.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
              <a href="#pachete" class="inline-flex items-center justify-center gap-2 rounded-full px-6 py-4 font-bold" style="background:var(--acid);color:var(--pine-950)">Descoperă pachetele <span aria-hidden="true">↓</span></a>
              <button type="button" @click="scrollBuilder()" class="inline-flex items-center justify-center rounded-full border border-white border-opacity-20 bg-white bg-opacity-5 px-6 py-4 font-bold">Construiește ziua</button>
            </div>
          </div>

          <div class="hero-visual relative reveal">
            <article class="ticket-sculpture float-one left-0 top-8 w-11/12 p-5 sm:w-4/5 sm:p-7" style="background:var(--acid);color:var(--pine-950);transform:rotate(-7deg)">
              <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest opacity-50">Explorer Pass</p><h2 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">Pădure, belvederi<br>și timp fără grabă.</h2></div><span class="rounded-full border border-current px-3 py-2 text-xs font-bold uppercase">Open trail</span></div>
              <div class="ticket-dash mt-7 flex items-end justify-between pt-5"><div><p class="text-xs opacity-50">Acces de o zi</p><p class="mt-1 font-semibold">Trasee libere incluse</p></div><div class="text-right"><p class="font-display text-4xl font-semibold">55</p><p class="text-xs opacity-50">lei / adult</p></div></div>
            </article>
            <article class="ticket-sculpture float-two right-0 top-48 w-11/12 p-5 text-white sm:w-4/5 sm:p-7" style="background:var(--ember);transform:rotate(6deg)">
              <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest text-white text-opacity-60">Adventure Pass</p><h2 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">Acces + experiența<br>care îți ridică pulsul.</h2></div><span class="grid h-12 w-12 place-items-center rounded-full bg-white bg-opacity-20 text-xl">↗</span></div>
              <div class="ticket-dash-light mt-7 flex items-end justify-between pt-5"><div><p class="text-xs text-white text-opacity-60">Recomandat</p><p class="mt-1 font-semibold">Pentru prima vizită</p></div><div class="text-right"><p class="font-display text-4xl font-semibold">125</p><p class="text-xs text-white text-opacity-60">lei / adult</p></div></div>
            </article>
            <div class="ticket-note absolute bottom-5 left-10 max-w-xs rounded-2xl border border-white border-opacity-10 bg-white bg-opacity-10 p-4 backdrop-filter sm:left-20">
              <p class="text-xs text-white text-opacity-45">Nu știi ce să alegi?</p><p class="mt-1 text-sm font-semibold">Configuratorul calculează formula potrivită în mai puțin de un minut.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="trust-strip text-white" style="background:var(--pine-900)">
      <div class="page-shell mobile-scroll flex overflow-x-auto">
        <div class="trust-item flex flex-1 items-center gap-3 px-5 py-5 sm:px-7"><span class="grid h-9 w-9 flex-none place-items-center rounded-full" style="background:rgba(223,252,98,.12);color:var(--acid)">↺</span><div><p class="text-sm font-semibold">Schimbă data gratuit</p><p class="mt-1 text-xs text-white text-opacity-40">Până la 48 de ore înainte</p></div></div>
        <div class="trust-item flex flex-1 items-center gap-3 px-5 py-5 sm:px-7"><span class="grid h-9 w-9 flex-none place-items-center rounded-full" style="background:rgba(223,252,98,.12);color:var(--acid)">✓</span><div><p class="text-sm font-semibold">Echipament verificat</p><p class="mt-1 text-xs text-white text-opacity-40">Inclus la experiențele tehnice</p></div></div>
        <div class="trust-item flex flex-1 items-center gap-3 px-5 py-5 sm:px-7"><span class="grid h-9 w-9 flex-none place-items-center rounded-full" style="background:rgba(223,252,98,.12);color:var(--acid)">✦</span><div><p class="text-sm font-semibold">Confirmare instant</p><p class="mt-1 text-xs text-white text-opacity-40">Biletele ajung direct pe email</p></div></div>
      </div>
    </div>

    <section id="pachete" class="paper-grid py-16 sm:py-20 lg:py-28">
      <div class="page-shell">
        <div class="reveal grid gap-8 lg:grid-cols-2 lg:items-end">
          <div><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--pine-700)">Trei moduri de a trăi Nordvale</p><h2 class="mt-3 font-display text-4xl font-semibold leading-none tracking-tight sm:text-5xl lg:text-6xl">Mai clar decât<br><span class="italic" style="color:var(--ember)">o listă de tarife.</span></h2></div>
          <p class="max-w-xl text-base leading-7 text-gray-600 lg:justify-self-end">Toate pachetele includ accesul în rezervație. Diferența este dată de experiențele rezervate, ritmul zilei și cât de mult vrei să fie pregătit în avans.</p>
        </div>

        <div class="mt-10 grid gap-5 lg:grid-cols-3">
          <template x-for="pkg in packages" :key="pkg.id">
            <article @click="selectPackage(pkg.id)" :class="selectedPackage===pkg.id?'selected':''" class="choice-card cursor-pointer rounded-3xl" tabindex="0" @keydown.enter="selectPackage(pkg.id)">
              <div class="choice-visual relative overflow-hidden p-6 sm:p-7" :style="'background:'+pkg.color+';color:'+pkg.textColor">
                <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest opacity-55" x-text="pkg.kicker"></p><h3 class="mt-2 font-display text-3xl font-semibold" x-text="pkg.name"></h3></div><span class="choice-icon grid h-12 w-12 flex-none place-items-center rounded-full text-xl" :style="'background:'+pkg.iconBg" x-text="pkg.icon"></span></div>
                <div class="mt-8 flex items-end justify-between gap-4"><p class="max-w-xs text-sm leading-6 opacity-70" x-text="pkg.short"></p><div class="text-right"><p class="font-display text-4xl font-semibold" x-text="pkg.price"></p><p class="text-xs opacity-55">lei / adult</p></div></div>
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
          <p class="max-w-xl text-base leading-7 text-gray-600 lg:justify-self-end">Configuratorul este demonstrativ, dar logica este pregătită pentru integrarea cu inventarul, capacitatea și regulile de preț Tixello.</p>
        </div>

        <div class="mt-10 grid gap-7 lg:grid-cols-12 lg:items-start">
          <div class="space-y-5 lg:col-span-8">
            <article class="step-card active rounded-3xl p-5 sm:p-7">
              <div class="flex items-start gap-4"><span class="step-index grid h-10 w-10 flex-none place-items-center rounded-full font-bold">1</span><div class="min-w-0 flex-1"><p class="text-xs font-bold uppercase tracking-widest text-gray-400">Cine vine?</p><h3 class="mt-1 font-display text-2xl font-semibold sm:text-3xl">Tipul grupului</h3></div></div>
              <div class="mobile-scroll mt-6 flex gap-2 overflow-x-auto pb-2">
                <template x-for="item in audiences" :key="item.id"><button type="button" @click="audience=item.id" :class="audience===item.id?'active':''" class="audience-chip flex min-w-max items-center gap-3 rounded-full px-4 py-3 text-sm font-semibold"><span class="audience-badge grid h-7 w-7 place-items-center rounded-full" style="background:var(--oat)" x-text="item.icon"></span><span x-text="item.label"></span></button></template>
              </div>
              <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4"><div><p class="font-semibold">Adulți</p><p class="mt-1 text-xs text-gray-400">18+ ani</p></div><div class="qty-control"><button type="button" @click="adults=Math.max(1,adults-1)" aria-label="Scade adulți">−</button><strong class="w-6 text-center" x-text="adults"></strong><button type="button" @click="adults=Math.min(12,adults+1)" aria-label="Adaugă adulți">+</button></div></div>
                <div class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4"><div><p class="font-semibold">Copii</p><p class="mt-1 text-xs text-gray-400">3–17 ani</p></div><div class="qty-control"><button type="button" @click="children=Math.max(0,children-1)" aria-label="Scade copii">−</button><strong class="w-6 text-center" x-text="children"></strong><button type="button" @click="children=Math.min(12,children+1)" aria-label="Adaugă copii">+</button></div></div>
              </div>
            </article>

            <article class="step-card rounded-3xl p-5 sm:p-7">
              <div class="flex items-start gap-4"><span class="step-index grid h-10 w-10 flex-none place-items-center rounded-full font-bold">2</span><div><p class="text-xs font-bold uppercase tracking-widest text-gray-400">Ce ritm vrei?</p><h3 class="mt-1 font-display text-2xl font-semibold sm:text-3xl">Pachetul de bază</h3></div></div>
              <div class="mt-6 grid gap-3 sm:grid-cols-3"><template x-for="pkg in packages" :key="pkg.id"><button type="button" @click="selectedPackage=pkg.id" :class="selectedPackage===pkg.id?'text-white':''" class="rounded-2xl border border-gray-200 p-4 text-left transition" :style="selectedPackage===pkg.id?'background:var(--pine-900);border-color:var(--pine-900)':''"><span class="block text-xs font-bold uppercase tracking-widest opacity-50" x-text="pkg.kicker"></span><span class="mt-2 block font-display text-xl font-semibold" x-text="pkg.name"></span><span class="mt-3 block text-sm opacity-65" x-text="pkg.price+' lei / adult'"></span></button></template></div>
            </article>

            <article class="step-card rounded-3xl p-5 sm:p-7">
              <div class="flex items-start gap-4"><span class="step-index grid h-10 w-10 flex-none place-items-center rounded-full font-bold">3</span><div><p class="text-xs font-bold uppercase tracking-widest text-gray-400">Opțional</p><h3 class="mt-1 font-display text-2xl font-semibold sm:text-3xl">Completează experiența</h3></div></div>
              <div class="mt-6 grid gap-3 sm:grid-cols-2"><template x-for="addon in addons" :key="addon.id"><button type="button" @click="toggleAddon(addon.id)" :class="selectedAddons.includes(addon.id)?'active':''" class="addon-card rounded-2xl p-4 text-left"><div class="flex items-start justify-between gap-4"><span class="grid h-10 w-10 flex-none place-items-center rounded-full text-lg" :style="'background:'+addon.color" x-text="addon.icon"></span><span class="rounded-full px-3 py-1 text-xs font-bold" style="background:var(--oat)" x-text="'+'+addon.price+' lei'"></span></div><p class="mt-4 font-semibold" x-text="addon.name"></p><p class="mt-1 text-xs leading-5 text-gray-500" x-text="addon.description"></p></button></template></div>
            </article>
          </div>

          <aside class="summary-panel rounded-3xl p-5 text-white sm:p-7 lg:sticky lg:top-28 lg:col-span-4" style="background:var(--pine-950)">
            <p class="text-xs font-bold uppercase tracking-widest text-white text-opacity-35">Rezumatul tău</p>
            <div class="mt-5 flex items-start justify-between gap-4"><div><h3 class="font-display text-3xl font-semibold" x-text="selectedPackageData.name"></h3><p class="mt-2 text-sm leading-6 text-white text-opacity-50" x-text="selectedPackageData.short"></p></div><span class="grid h-12 w-12 flex-none place-items-center rounded-full text-xl" :style="'background:'+selectedPackageData.color+';color:'+selectedPackageData.textColor" x-text="selectedPackageData.icon"></span></div>
            <div class="mt-6 space-y-3 border-t border-white border-opacity-10 pt-5 text-sm"><div class="flex justify-between gap-4"><span class="text-white text-opacity-45">Participanți</span><strong><span x-text="adults"></span> adulți · <span x-text="children"></span> copii</strong></div><div class="flex justify-between gap-4"><span class="text-white text-opacity-45">Pachet de bază</span><strong x-text="baseTotal+' lei'"></strong></div><div x-show="addonsTotal>0" class="flex justify-between gap-4"><span class="text-white text-opacity-45">Opțiuni adăugate</span><strong x-text="addonsTotal+' lei'"></strong></div><div x-show="discount>0" class="flex justify-between gap-4" style="color:var(--acid)"><span>Reducere pachet</span><strong x-text="'-'+discount+' lei'"></strong></div></div>
            <div class="mt-6 flex items-end justify-between border-t border-white border-opacity-10 pt-5"><div><p class="text-xs text-white text-opacity-35">Total estimat</p><p class="mt-1 text-xs text-white text-opacity-35">taxe incluse</p></div><p class="font-display text-4xl font-semibold" style="color:var(--acid)" x-text="grandTotal+' lei'"></p></div>
            <button type="button" @click="drawerOpen=true" class="desktop-builder-cta mt-6 w-full rounded-full px-6 py-4 font-bold" style="background:var(--acid);color:var(--pine-950)">Alege data</button>
            <p class="mt-3 text-center text-xs leading-5 text-white text-opacity-30">Nu se procesează nicio plată în acest demo.</p>
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
            <div><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--acid)">Nordvale Gift Pass</p><h2 class="mt-3 font-display text-4xl font-semibold leading-none tracking-tight sm:text-5xl lg:text-6xl">Un cadou bun<br><span class="outline-word italic">nu are nevoie de dată.</span></h2><p class="mt-5 max-w-xl text-base leading-7 text-white text-opacity-60">Voucherul este valabil 12 luni. Destinatarul alege ziua, pachetul și poate completa diferența pentru o experiență premium.</p><div class="mt-7 flex flex-col gap-3 sm:flex-row"><button type="button" @click="giftOpen=true" class="rounded-full px-6 py-4 font-bold" style="background:var(--acid);color:var(--pine-950)">Configurează voucherul</button><a href="#faq" class="rounded-full border border-white border-opacity-20 px-6 py-4 text-center font-bold">Cum funcționează</a></div></div>
            <article class="gift-ticket ticket-sculpture relative mx-auto w-full max-w-md p-6" style="background:var(--cream);color:var(--pine-950);transform:rotate(3deg)"><div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest opacity-40">Nordvale Gift Pass</p><h3 class="mt-3 font-display text-3xl font-semibold">O zi care încă<br>nu are program.</h3></div><span class="rounded-full border border-current px-3 py-2 text-xs font-bold">12 luni</span></div><div class="ticket-dash mt-7 flex items-end justify-between pt-5"><div><p class="text-xs opacity-45">Valoare flexibilă</p><p class="mt-1 font-semibold">de la 100 lei</p></div><svg viewBox="0 0 48 48" class="h-12 w-12" fill="none"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#09251D"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#F27B4A"/></svg></div></article>
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

  <footer class="px-4 pb-8 pt-14 text-white sm:px-6 lg:px-8" style="background:var(--pine-950)">
    <div class="mx-auto max-w-screen-2xl"><div class="grid gap-10 border-b border-white border-opacity-10 pb-10 md:grid-cols-2 lg:grid-cols-4"><div><a href="/" class="inline-flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-full" style="background:var(--pine-800)"><svg viewBox="0 0 48 48" class="h-8 w-8" fill="none"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#DFFC62"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#FFFDF6"/></svg></span><span><span class="block font-display text-2xl font-semibold leading-none">Nordvale</span><span class="mt-1 block text-xs font-bold uppercase tracking-widest text-white opacity-30">wild park · forest reserve</span></span></a><p class="mt-5 max-w-sm text-sm leading-6 text-white text-opacity-40">Un tenant Tixello leisure construit pentru aventuri, rezervații și experiențe care încep înainte de poarta parcului.</p></div><div><p class="text-xs font-bold uppercase tracking-widest text-white text-opacity-30">Explorează</p><div class="mt-4 grid gap-3 text-sm text-white text-opacity-60"><a href="/experiente">Experiențe</a><a href="/planifica">Planifică vizita</a><a href="/calendar">Calendar</a></div></div><div><p class="text-xs font-bold uppercase tracking-widest text-white text-opacity-30">Bilete</p><div class="mt-4 grid gap-3 text-sm text-white text-opacity-60"><a href="#pachete">Pachete</a><a href="#builder">Configurator</a><a href="#faq">Întrebări frecvente</a></div></div><div><p class="text-xs font-bold uppercase tracking-widest text-white text-opacity-30">Program</p><p class="mt-4 text-sm text-white text-opacity-60">Luni–Duminică<br>09:00–20:00</p><p class="mt-4 text-xs leading-5 text-white text-opacity-40">Ultima intrare diferă în funcție de experiență.</p></div></div><div class="flex flex-col gap-4 pt-6 text-xs text-white text-opacity-30 sm:flex-row sm:items-center sm:justify-between"><p>© 2026 Nordvale. Concept demonstrativ cu date dummy.</p><p>Ticketing by <span class="font-bold" style="color:var(--acid)">tixello</span></p></div></div>
  </footer>

  <div x-show="drawerOpen" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Alege data vizitei">
    <button type="button" @click="drawerOpen=false" class="drawer-backdrop absolute inset-0 w-full" aria-label="Închide"></button>
    <div class="drawer-panel safe-bottom absolute inset-x-0 bottom-0 max-h-screen overflow-y-auto rounded-t-3xl p-5 sm:left-auto sm:right-0 sm:top-0 sm:h-full sm:w-full sm:max-w-md sm:rounded-none sm:p-7" style="background:var(--cream)">
      <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest text-gray-400">Rezervare</p><h2 class="mt-2 font-display text-3xl font-semibold">Alege ziua potrivită</h2></div><button type="button" @click="drawerOpen=false" class="grid h-10 w-10 flex-none place-items-center rounded-full border border-gray-200 text-xl" aria-label="Închide">×</button></div>
      <div class="mt-6 rounded-3xl p-5 text-white" style="background:var(--pine-950)"><div class="flex items-center justify-between gap-4"><div><p class="text-xs text-white text-opacity-35">Pachet selectat</p><h3 class="mt-1 font-display text-2xl" x-text="selectedPackageData.name"></h3></div><span class="grid h-11 w-11 flex-none place-items-center rounded-full" :style="'background:'+selectedPackageData.color+';color:'+selectedPackageData.textColor" x-text="selectedPackageData.icon"></span></div><div class="mt-5 flex items-end justify-between border-t border-white border-opacity-10 pt-4"><p class="text-sm text-white text-opacity-45"><span x-text="adults+children"></span> participanți</p><p class="font-display text-3xl" style="color:var(--acid)" x-text="grandTotal+' lei'"></p></div></div>
      <p class="mt-6 text-xs font-bold uppercase tracking-widest text-gray-400">Date recomandate</p>
      <div class="mt-3 grid grid-cols-3 gap-2"><template x-for="date in dates" :key="date.id"><button type="button" @click="selectedDate=date.id" class="rounded-2xl border border-gray-200 p-3 text-center" :style="selectedDate===date.id?'background:var(--pine-900);color:white;border-color:var(--pine-900)':''"><span class="block text-xs opacity-55" x-text="date.day"></span><span class="mt-1 block font-display text-2xl" x-text="date.number"></span><span class="mt-1 block text-xs" x-text="date.status"></span></button></template></div>
      <div class="mt-6 rounded-2xl bg-gray-100 p-4"><div class="flex justify-between gap-4 text-sm"><span class="text-gray-500">Schimbare dată</span><strong>Până la 48h</strong></div><div class="mt-3 flex justify-between gap-4 border-t border-gray-200 pt-3 text-sm"><span class="text-gray-500">Bilete</span><strong>Instant pe email</strong></div></div>
      <button type="button" @click="confirmBooking()" class="mt-5 w-full rounded-full px-6 py-4 font-bold" style="background:var(--acid);color:var(--pine-950)">Continuă către coș</button>
      <p class="mt-3 text-center text-xs leading-5 text-gray-400">Flux demonstrativ. Nicio plată nu este procesată.</p>
    </div>
  </div>

  <div x-show="giftOpen" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Configurează voucherul cadou">
    <button type="button" @click="giftOpen=false" class="drawer-backdrop absolute inset-0 w-full" aria-label="Închide"></button>
    <div class="drawer-panel safe-bottom absolute inset-x-0 bottom-0 max-h-screen overflow-y-auto rounded-t-3xl p-5 sm:left-auto sm:right-0 sm:top-0 sm:h-full sm:w-full sm:max-w-md sm:rounded-none sm:p-7" style="background:var(--cream)">
      <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest text-gray-400">Nordvale Gift Pass</p><h2 class="mt-2 font-display text-3xl font-semibold">Configurează cadoul</h2></div><button type="button" @click="giftOpen=false" class="grid h-10 w-10 flex-none place-items-center rounded-full border border-gray-200 text-xl" aria-label="Închide">×</button></div>
      <p class="mt-5 text-sm leading-6 text-gray-600">Alege valoarea. Persoana care primește voucherul va selecta ulterior ziua și pachetul.</p>
      <div class="mt-6 grid grid-cols-2 gap-3"><template x-for="value in [100,200,300,500]" :key="value"><button type="button" @click="giftValue=value" class="rounded-2xl border border-gray-200 p-5 text-left" :style="giftValue===value?'background:var(--pine-900);color:white;border-color:var(--pine-900)':''"><span class="block text-xs opacity-50">Voucher</span><span class="mt-1 block font-display text-3xl" x-text="value+' lei'"></span></button></template></div>
      <label class="mt-6 block text-xs font-bold uppercase tracking-widest text-gray-400" for="gift-message">Mesaj pe voucher</label>
      <textarea id="gift-message" x-model="giftMessage" rows="4" class="mt-3 w-full rounded-2xl border border-gray-200 bg-white p-4" placeholder="Să fie o zi de neuitat..."></textarea>
      <button type="button" @click="confirmGift()" class="mt-5 w-full rounded-full px-6 py-4 font-bold" style="background:var(--acid);color:var(--pine-950)">Adaugă voucherul în coș</button>
    </div>
  </div>

  <div class="mobile-summary safe-bottom fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white bg-opacity-95 px-4 pt-3 backdrop-filter" x-show="!drawerOpen && !giftOpen">
    <div class="mx-auto flex max-w-screen-sm items-center justify-between gap-3"><div class="min-w-0"><p class="truncate text-xs text-gray-400" x-text="selectedPackageData.name+' · '+(adults+children)+' pers.'"></p><p class="font-display text-2xl font-semibold" x-text="grandTotal+' lei'"></p></div><button type="button" @click="drawerOpen=true" class="flex-none whitespace-nowrap rounded-full px-5 py-3 text-sm font-bold" style="background:var(--acid);color:var(--pine-950)">Alege data</button></div>
  </div>

  <div x-show="toast" x-cloak x-transition.opacity class="toast safe-bottom fixed bottom-5 left-1/2 z-50 w-11/12 max-w-md -translate-x-1/2 rounded-2xl px-4 py-3 text-center text-sm font-semibold text-white" style="background:var(--pine-950)" x-text="toastMessage"></div>

  <script>
    function ticketsPage() {
      return {
        menu:false, drawerOpen:false, giftOpen:false, toast:false, toastMessage:'', openFaq:0,
        audience:'family', adults:2, children:2, selectedPackage:'family', selectedAddons:['meal'], selectedDate:'d2', giftValue:200, giftMessage:'',
        audiences:[
          {id:'solo',label:'Singur',icon:'●'}, {id:'couple',label:'În doi',icon:'∞'}, {id:'family',label:'Familie',icon:'⌂'}, {id:'group',label:'Grup',icon:'✦'}
        ],
        packages:[
          {id:'explorer',name:'Explorer Pass',kicker:'Ritm liber',short:'Pentru trasee, belvederi și o zi fără program fix.',description:'Intrarea esențială pentru cei care vor să exploreze rezervația în ritmul lor.',price:55,icon:'⌁',color:'#dffc62',textColor:'#061a15',iconBg:'rgba(6,26,21,.12)',bestFor:'Plimbare & natură',includes:['Acces general în parc','Trasee pietonale și belvederi','Zone de picnic și observatoare']},
          {id:'adventure',name:'Adventure Pass',kicker:'Prima vizită',short:'Acces general și o experiență rezervată.',description:'Formula echilibrată pentru o zi care combină natura cu o experiență memorabilă.',price:125,icon:'↗',color:'#f27b4a',textColor:'#ffffff',iconBg:'rgba(255,255,255,.18)',bestFor:'Cupluri & prieteni',includes:['Tot din Explorer Pass','O experiență la alegere','Echipament și briefing incluse']},
          {id:'family',name:'Family Trail',kicker:'Cel mai bun raport',short:'O zi gândită pentru 2 adulți și copii.',description:'Ritm flexibil, activitate pentru copii și spațiu suficient pentru pauze fără grabă.',price:89,icon:'⌂',color:'#b8dce0',textColor:'#061a15',iconBg:'rgba(6,26,21,.1)',bestFor:'Familii',includes:['Acces general','Junior Canopy sau Nature Lab','Gustare pentru copii inclusă']}
        ],
        addons:[
          {id:'canopy',name:'Canopy Run',description:'Circuit la înălțime, echipament și briefing incluse.',price:70,icon:'↗',color:'#f27b4a'},
          {id:'guided',name:'Tur ghidat',description:'90 de minute cu un ghid naturalist al rezervației.',price:38,icon:'⌁',color:'#b8dce0'},
          {id:'meal',name:'Picnic forestier',description:'Sandwich, fruct, apă și desert de casă.',price:32,icon:'◒',color:'#dffc62'},
          {id:'photo',name:'Galerie foto',description:'Fotografii digitale livrate după experiență.',price:25,icon:'◎',color:'#d7d2c2'}
        ],
        intents:[
          {title:'Vreau o zi liniștită',package:'Explorer',icon:'⌁',color:'#dffc62',description:'Mai multă pădure, mai puțină presiune. Trasee libere, belvederi și opriri când ai nevoie.',includes:'Explorer Pass + tur ghidat'},
          {title:'Este prima noastră vizită',package:'Adventure',icon:'↗',color:'#f27b4a',description:'Cea mai clară formulă pentru a înțelege parcul și a trăi o experiență reprezentativă.',includes:'Adventure Pass + picnic'},
          {title:'Venim cu copiii',package:'Family',icon:'⌂',color:'#b8dce0',description:'Activitate adaptată, pauze flexibile și suficient spațiu pentru ca ziua să rămână plăcută.',includes:'Family Trail + Nature Lab'}
        ],
        dates:[
          {id:'d1',day:'Vineri',number:'31',status:'Foarte liber'},
          {id:'d2',day:'Sâmbătă',number:'1',status:'Locuri bune'},
          {id:'d3',day:'Duminică',number:'2',status:'Ultimele locuri'}
        ],
        faqs:[
          {q:'Trebuie să aleg data când cumpăr?',a:'Pentru biletele de o zi, da. Data poate fi schimbată gratuit până la 48 de ore înainte, în limita disponibilității. Voucherul cadou nu necesită o dată la cumpărare.'},
          {q:'Copiii sub 3 ani au nevoie de bilet?',a:'Accesul general este gratuit pentru copiii sub 3 ani. Pentru experiențele tehnice se aplică separat condițiile de vârstă, înălțime și greutate.'},
          {q:'Ce se întâmplă dacă plouă?',a:'Ploaia ușoară nu închide automat parcul. Dacă o experiență este suspendată din motive de siguranță, poți alege reprogramarea, credit în cont sau rambursarea componentei afectate.'},
          {q:'Pot cumpăra doar o experiență, fără acces general?',a:'Nu. Experiențele se desfășoară în interiorul parcului și necesită un bilet de acces valabil pentru aceeași zi.'}
        ],
        packageEvent: <?php echo $pkgEventJs; ?>,
        serverPackages: <?php echo $pkgItemsJs; ?>,
        init() {
          if (Array.isArray(this.serverPackages) && this.serverPackages.length) {
            this.packages = this.serverPackages;
            this.selectedPackage = this.serverPackages[0].id;
          }
          this.$watch('audience', value => {
            if (!this.serverPackages || !this.serverPackages.length) {
              if (value==='solo') { this.adults=1; this.children=0; this.selectedPackage='explorer'; }
              if (value==='couple') { this.adults=2; this.children=0; this.selectedPackage='adventure'; }
              if (value==='family') { this.adults=2; this.children=Math.max(1,this.children); this.selectedPackage='family'; }
              if (value==='group') { this.adults=Math.max(4,this.adults); this.children=0; this.selectedPackage='adventure'; }
            } else {
              if (value==='solo') { this.adults=1; this.children=0; }
              if (value==='couple') { this.adults=2; this.children=0; }
              if (value==='family') { this.adults=2; this.children=Math.max(1,this.children); }
              if (value==='group') { this.adults=Math.max(4,this.adults); this.children=0; }
            }
          });
          this.setupReveal();
          this.setupProgress();
        },
        get selectedPackageData() { return this.packages.find(item=>item.id===this.selectedPackage) || this.packages[0]; },
        get participantCount() { return this.adults + this.children; },
        get baseTotal() {
          const adultPrice=this.selectedPackageData.price;
          const childMultiplier=this.selectedPackage==='family' ? .58 : .7;
          return Math.round((this.adults*adultPrice)+(this.children*adultPrice*childMultiplier));
        },
        get addonsTotal() { return this.addons.filter(item=>this.selectedAddons.includes(item.id)).reduce((sum,item)=>sum+(item.price*this.participantCount),0); },
        get discount() { return this.selectedPackage==='family' && this.participantCount>=4 ? 45 : this.audience==='group' && this.participantCount>=6 ? 60 : 0; },
        get grandTotal() { return Math.max(0,this.baseTotal+this.addonsTotal-this.discount); },
        selectPackage(id) { this.selectedPackage=id; },
        chooseAndBook(id) { this.selectedPackage=id; this.scrollBuilder(); },
        toggleAddon(id) { this.selectedAddons=this.selectedAddons.includes(id) ? this.selectedAddons.filter(item=>item!==id) : [...this.selectedAddons,id]; },
        scrollBuilder() { document.getElementById('builder')?.scrollIntoView({behavior:'smooth',block:'start'}); },
        confirmBooking() {
          const pkg=this.selectedPackageData;
          if (this.packageEvent && this.packageEvent.id && pkg && pkg.ticket_type_id) {
            const dateObj=this.dates.find(d=>d.id===this.selectedDate);
            const dateLabel=dateObj?`${dateObj.day} ${dateObj.number}`:'';
            const qty=Math.max(1,this.participantCount);
            const cart={
              event:{id:this.packageEvent.id,title:this.packageEvent.title,image:this.packageEvent.image,date:dateLabel,venue:this.packageEvent.venue},
              items:[{ticket_type_id:pkg.ticket_type_id,title:pkg.name,date:dateLabel,slot:'',qty:qty,unit_price:pkg.price}],
              subtotal:qty*pkg.price
            };
            try{localStorage.setItem('nordvale_cart',JSON.stringify(cart));}catch(e){}
            window.location.href='/cos';
            return;
          }
          this.drawerOpen=false; this.showToast('Pachetul a fost adăugat în coșul demonstrativ.');
        },
        confirmGift() { this.giftOpen=false; this.showToast('Voucherul de '+this.giftValue+' lei a fost adăugat în coș.'); },
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
      };
    }
  </script>
</body>
</html>
