<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$calFallbackImg = 'https://images.unsplash.com/photo-1448375240586-882707db888b?w=1000&q=80';
$calColors = ['#dffc62', '#b8dce0', '#f27b4a', '#fffdf6'];
$calEvents = tc_events(api_get('/tenant-client/events', ['per_page' => 12]));
$calItems = [];
foreach (array_values($calEvents) as $i => $ev) {
    $tt = $ev['ticket_types'][0] ?? null;
    $price = (float) ($tt['price'] ?? $ev['price_from'] ?? 0);
    $time = ! empty($ev['start_time']) ? substr((string) $ev['start_time'], 0, 5) : '10:00';
    $calItems[] = [
        'id'             => $ev['slug'] ?? ('e' . ($ev['id'] ?? $i)),
        'slug'           => $ev['slug'] ?? '',
        'event_id'       => $ev['id'] ?? null,
        'ticket_type_id' => $tt['id'] ?? null,
        'filter'         => 'height',
        'name'           => $ev['title'] ?? 'Experiență',
        'icon'           => '↗',
        'color'          => $calColors[$i % 4],
        'from'           => $price,
        'image'          => asset_url($ev['hero_image_url'] ?? $ev['poster_url'] ?? null, $calFallbackImg),
        'note'           => ($ev['venue']['name'] ?? 'Nordvale'),
        'start_date'     => $ev['start_date'] ?? null,
        'slots'          => [
            ['time' => $time, 'left' => 12, 'price' => $price],
        ],
    ];
}
$calItemsJs = ! empty($calItems) ? json_encode($calItems, JSON_UNESCAPED_UNICODE) : '[]';
?>
<!DOCTYPE html>
<html lang="ro" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#061a15">
  <title>Calendar și disponibilitate — Nordvale</title>
  <meta name="description" content="Alege data, verifică disponibilitatea și rezervă experiențele Nordvale într-un calendar operațional complet.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style id="tailwind-compiled">/*! tailwindcss v4.1.10 | MIT License | https://tailwindcss.com */
@layer properties;
@layer theme, base, components, utilities;
@layer theme {
  :root, :host {
    --font-sans: "DM Sans", sans-serif;
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
    --font-display: "Fraunces", serif;
    --color-pine-950: #061a15;
    --color-pine-900: #09251d;
    --color-pine-800: #123b30;
    --color-pine-700: #1b5242;
    --color-acid: #dffc62;
    --color-ember: #f27b4a;
    --color-cream: #fffdf6;
    --color-oat: #f0ecdf;
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
      border-color: color-mix(in srgb, #09251d 10%, transparent);
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
    border-color: color-mix(in srgb, #dffc62 10%, transparent);
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
    border-color: color-mix(in srgb, #09251d 8%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-pine-900) 8%, transparent);
    }
  }
  .border-pine-900\/10 {
    border-color: color-mix(in srgb, #09251d 10%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-pine-900) 10%, transparent);
    }
  }
  .border-pine-900\/12 {
    border-color: color-mix(in srgb, #09251d 12%, transparent);
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
    background-color: color-mix(in srgb, #09251d 6%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-pine-900) 6%, transparent);
    }
  }
  .bg-pine-900\/10 {
    background-color: color-mix(in srgb, #09251d 10%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-pine-900) 10%, transparent);
    }
  }
  .bg-pine-950 {
    background-color: var(--color-pine-950);
  }
  .bg-pine-950\/70 {
    background-color: color-mix(in srgb, #061a15 70%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      background-color: color-mix(in oklab, var(--color-pine-950) 70%, transparent);
    }
  }
  .bg-pine-950\/80 {
    background-color: color-mix(in srgb, #061a15 80%, transparent);
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
    --tw-gradient-via: color-mix(in srgb, #061a15 15%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      --tw-gradient-via: color-mix(in oklab, var(--color-pine-950) 15%, transparent);
    }
    --tw-gradient-via-stops: var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-via) var(--tw-gradient-via-position), var(--tw-gradient-to) var(--tw-gradient-to-position);
    --tw-gradient-stops: var(--tw-gradient-via-stops);
  }
  .via-pine-950\/55 {
    --tw-gradient-via: color-mix(in srgb, #061a15 55%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      --tw-gradient-via: color-mix(in oklab, var(--color-pine-950) 55%, transparent);
    }
    --tw-gradient-via-stops: var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-via) var(--tw-gradient-via-position), var(--tw-gradient-to) var(--tw-gradient-to-position);
    --tw-gradient-stops: var(--tw-gradient-via-stops);
  }
  .to-pine-950\/20 {
    --tw-gradient-to: color-mix(in srgb, #061a15 20%, transparent);
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
    color: color-mix(in srgb, #09251d 35%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 35%, transparent);
    }
  }
  .text-pine-900\/38 {
    color: color-mix(in srgb, #09251d 38%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 38%, transparent);
    }
  }
  .text-pine-900\/40 {
    color: color-mix(in srgb, #09251d 40%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 40%, transparent);
    }
  }
  .text-pine-900\/42 {
    color: color-mix(in srgb, #09251d 42%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 42%, transparent);
    }
  }
  .text-pine-900\/45 {
    color: color-mix(in srgb, #09251d 45%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 45%, transparent);
    }
  }
  .text-pine-900\/48 {
    color: color-mix(in srgb, #09251d 48%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 48%, transparent);
    }
  }
  .text-pine-900\/50 {
    color: color-mix(in srgb, #09251d 50%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 50%, transparent);
    }
  }
  .text-pine-900\/60 {
    color: color-mix(in srgb, #09251d 60%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 60%, transparent);
    }
  }
  .text-pine-900\/62 {
    color: color-mix(in srgb, #09251d 62%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-900) 62%, transparent);
    }
  }
  .text-pine-950 {
    color: var(--color-pine-950);
  }
  .text-pine-950\/55 {
    color: color-mix(in srgb, #061a15 55%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-pine-950) 55%, transparent);
    }
  }
  .text-pine-950\/66 {
    color: color-mix(in srgb, #061a15 66%, transparent);
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
    text-decoration-color: color-mix(in srgb, #1b5242 25%, transparent);
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
    --tw-shadow: 0 14px 35px -20px var(--tw-shadow-color, rgba(223,252,98,.8));
    box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow);
  }
  .shadow-\[0_34px_100px_-50px_rgba\(6\,26\,21\,\.9\)\] {
    --tw-shadow: 0 34px 100px -50px var(--tw-shadow-color, rgba(6,26,21,.9));
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
</style>
  <style>
    :root{--pine:#09251d;--pine-dark:#061a15;--acid:#dffc62;--ember:#f27b4a;--cream:#fffdf6;--oat:#f0ecdf;--ink:#16231e;--moss:#b7c9a3;--sky:#b8dce0;--stone:#d7d2c2}
    *{box-sizing:border-box}html{background:var(--oat)}body{margin:0;overflow-x:hidden;background:var(--oat);color:var(--ink);font-family:'DM Sans',sans-serif;text-rendering:optimizeLegibility}[x-cloak]{display:none!important}::selection{background:var(--acid);color:var(--pine-dark)}button,a{-webkit-tap-highlight-color:transparent}.font-display{font-family:'Fraunces',serif}.safe-top{padding-top:max(12px,env(safe-area-inset-top))}.safe-bottom{padding-bottom:max(18px,env(safe-area-inset-bottom))}
    .topo-dark{background-image:radial-gradient(circle at 13% 18%,rgba(223,252,98,.14),transparent 18%),radial-gradient(circle at 87% 16%,rgba(242,123,74,.11),transparent 17%),url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23fff' stroke-opacity='.052' stroke-width='1.1'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M119 231c31-83 127-143 219-107 76 30 95 118 53 180-40 58-121 56-160 115-42 61-28 159-107 197-77 37-165-25-154-110 10-83 94-98 102-174 4-44 0-74 47-101Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3Cpath d='M548 507c39-60 118-79 173-32 53 45 37 128-19 161-50 30-111 8-150 51-34 36-29 100-79 112-55 14-106-39-83-92 21-50 81-47 110-92 23-36 22-72 48-108Z'/%3E%3C/g%3E%3C/svg%3E");background-size:auto,auto,820px 820px}
    .paper-grid{background-image:linear-gradient(rgba(9,37,29,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(9,37,29,.045) 1px,transparent 1px);background-size:30px 30px}.grain:after{content:'';position:absolute;inset:0;z-index:2;pointer-events:none;opacity:.075;mix-blend-mode:soft-light;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.58'/%3E%3C/svg%3E")}
    .nav-shell{background:rgba(6,26,21,.82);border:1px solid rgba(255,255,255,.12);box-shadow:0 24px 70px -42px rgba(0,0,0,.9);backdrop-filter:blur(22px) saturate(130%);-webkit-backdrop-filter:blur(22px) saturate(130%)}
    .outline-word{color:transparent;-webkit-text-stroke:1px rgba(255,255,255,.4)}.hero-cut{clip-path:polygon(5% 0,100% 0,96% 90%,76% 100%,0 94%,0 8%)}
    .route{fill:none;stroke:var(--acid);stroke-width:2.5;stroke-linecap:round;stroke-dasharray:9 13;filter:drop-shadow(0 0 7px rgba(223,252,98,.36));animation:route-flow 16s linear infinite}@keyframes route-flow{to{stroke-dashoffset:-220}}
    .orbit{animation:orbit 18s linear infinite;transform-origin:50% 50%}@keyframes orbit{to{transform:rotate(360deg)}}.float-card{animation:float-card 5.8s ease-in-out infinite}@keyframes float-card{50%{transform:translateY(-8px)}}
    .status-pulse{box-shadow:0 0 0 0 rgba(223,252,98,.42);animation:status-pulse 2.2s ease-out infinite}@keyframes status-pulse{70%{box-shadow:0 0 0 11px rgba(223,252,98,0)}100%{box-shadow:0 0 0 0 rgba(223,252,98,0)}}
    .calendar-shell{box-shadow:0 34px 100px -54px rgba(6,26,21,.48)}.calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}.calendar-day{position:relative;min-height:92px;border:1px solid rgba(9,37,29,.09);background:rgba(255,253,246,.86);transition:transform .22s ease,border-color .22s ease,box-shadow .22s ease,background .22s ease;overflow:hidden}.calendar-day:not(:disabled):hover{transform:translateY(-3px);border-color:rgba(9,37,29,.25);box-shadow:0 18px 35px -28px rgba(6,26,21,.7)}.calendar-day.is-selected{background:var(--pine);border-color:var(--pine);color:white;box-shadow:0 24px 55px -32px rgba(6,26,21,.9);transform:translateY(-3px)}.calendar-day.is-outside{opacity:.35}.calendar-day.is-closed{background:rgba(215,210,194,.48);color:rgba(22,35,30,.46)}.calendar-day.is-soldout{background:rgba(242,123,74,.08)}.calendar-day.is-today:after{content:'AZI';position:absolute;right:8px;top:8px;border-radius:999px;background:var(--acid);padding:3px 6px;color:var(--pine-dark);font-size:7px;font-weight:800;letter-spacing:.12em}.calendar-day.is-selected.is-today:after{background:white}.calendar-day.is-selected.is-soldout{background:var(--pine);color:white}.capacity-bar{height:4px;border-radius:999px;background:rgba(9,37,29,.1);overflow:hidden}.calendar-day.is-selected .capacity-bar{background:rgba(255,255,255,.18)}.capacity-fill{height:100%;border-radius:inherit;transition:width .4s ease}.availability-dot{width:7px;height:7px;border-radius:999px;flex:none}.dot-open{background:#61a87e}.dot-limited{background:var(--ember)}.dot-soldout{background:#c55745}.dot-closed{background:#9d998e}
    .filter-chip{transition:background .22s ease,color .22s ease,border-color .22s ease,transform .22s ease}.filter-chip:hover{transform:translateY(-1px)}.filter-chip.is-active{background:var(--pine);border-color:var(--pine);color:white}.filter-chip.is-active .chip-icon{background:var(--acid);color:var(--pine-dark)}
    .slot-button{transition:transform .2s ease,border-color .2s ease,background .2s ease,color .2s ease}.slot-button:not(:disabled):hover{transform:translateY(-2px);border-color:rgba(9,37,29,.35)}.slot-button.is-active{background:var(--acid);border-color:var(--acid);color:var(--pine-dark);box-shadow:0 16px 34px -24px rgba(6,26,21,.8)}.slot-button:disabled{cursor:not-allowed;opacity:.38;text-decoration:line-through}
    .experience-row{transition:background .25s ease,border-color .25s ease}.experience-row.is-open{background:white;border-color:rgba(9,37,29,.18)}.experience-row__content{display:grid;grid-template-rows:0fr;transition:grid-template-rows .32s cubic-bezier(.2,.8,.2,1)}.experience-row__content>div{overflow:hidden}.experience-row.is-open .experience-row__content{grid-template-rows:1fr}.experience-row.is-open .row-chevron{transform:rotate(180deg)}.row-chevron{transition:transform .25s ease}
    .heat-card{transition:transform .22s ease,box-shadow .22s ease}.heat-card:hover{transform:translateY(-4px);box-shadow:0 24px 55px -36px rgba(6,26,21,.7)}
    .reveal{opacity:0;transform:translateY(28px);transition:opacity .72s ease,transform .72s cubic-bezier(.2,.75,.2,1)}.reveal.is-visible{opacity:1;transform:none}.reveal-delay-1{transition-delay:.08s}.reveal-delay-2{transition-delay:.16s}.reveal-delay-3{transition-delay:.24s}.progress-line{transform:scaleX(0);transform-origin:left center}
    .drawer-backdrop{background:rgba(6,26,21,.62);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}.drawer-panel{box-shadow:-50px 0 120px -60px rgba(6,26,21,.85)}.scrollbar-hide::-webkit-scrollbar{display:none}.scrollbar-hide{scrollbar-width:none}
    @media(max-width:1023px){.hero-cut{clip-path:none;border-radius:28px}.calendar-day{min-height:82px}.calendar-grid{gap:6px}}
    @media(max-width:639px){.calendar-shell{margin-left:-8px;margin-right:-8px;border-radius:26px}.calendar-grid{gap:4px}.calendar-day{min-height:64px;border-radius:13px;padding:7px!important}.calendar-day.is-today:after{display:none}.calendar-day .day-meta{display:none}.calendar-day .capacity-label{display:none}.calendar-day .capacity-bar{margin-top:5px}.calendar-day .day-number{font-size:14px}.reveal{transform:translateY(18px)}}
    @media(max-width:374px){.calendar-day{min-height:58px;padding:6px!important}.calendar-grid{gap:3px}.calendar-day .day-number{font-size:13px}}
    @media(prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.01ms!important}.reveal{opacity:1!important;transform:none!important}}
  </style>
</head>
<body x-data="calendarPage()" x-init="init()" @keydown.escape.window="closeBooking()" class="antialiased">
  <div id="pageProgress" class="progress-line fixed left-0 top-0 z-[190] h-[3px] w-full bg-acid"></div>

  <?php $nvNav='calendar'; $nvNoSpacer=true; include __DIR__ . '/includes/header.php'; ?>

  <main>
    <section class="topo-dark grain relative overflow-hidden bg-pine-950 pb-16 pt-28 text-white sm:pb-20 sm:pt-32 lg:min-h-[790px] lg:pb-24 lg:pt-36">
      <div class="pointer-events-none absolute -left-20 top-28 h-80 w-80 rounded-full border border-acid/10"></div>
      <div class="pointer-events-none absolute -right-24 bottom-6 h-96 w-96 rounded-full border border-ember/15"></div>
      <div class="relative z-10 mx-auto grid max-w-[1540px] gap-10 px-4 sm:px-6 lg:grid-cols-[.9fr_1.1fr] lg:items-center lg:gap-14 lg:px-8 xl:gap-20">
        <div class="max-w-3xl reveal">
          <div class="mb-5 inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/[.06] px-4 py-2 text-[10px] font-bold uppercase tracking-[.24em] text-white/70 sm:text-xs"><span class="status-pulse h-2 w-2 rounded-full bg-acid"></span> Calendar operațional · sezon 2026</div>
          <h1 class="font-display text-[clamp(3.35rem,7.5vw,7.5rem)] font-semibold leading-[.84] tracking-[-.055em]">Alege ziua.<br><span class="text-acid">Noi ținem</span><br><span class="outline-word italic">locul liber.</span></h1>
          <p class="mt-7 max-w-2xl text-base leading-7 text-white/65 sm:text-lg sm:leading-8">Vezi într-o singură privire când parcul este aerisit, ce experiențe mai au locuri și care interval se potrivește ritmului grupului tău.</p>
          <div class="mt-8 flex flex-col gap-3 min-[430px]:flex-row">
            <a href="#calendar" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full bg-acid px-6 py-3.5 font-bold text-pine-950 transition hover:-translate-y-0.5">Verifică disponibilitatea <span aria-hidden="true">↘</span></a>
            <a href="#ferestre" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full border border-white/20 px-6 py-3.5 font-semibold text-white transition hover:bg-white/10">Vezi zilele recomandate</a>
          </div>
          <div class="mt-8 grid max-w-xl grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-white/10 bg-white/[.05] p-3"><p class="text-[9px] font-bold uppercase tracking-[.2em] text-white/40">Următoarea zi</p><p class="mt-1 font-display text-xl">Marți, 28</p></div>
            <div class="rounded-2xl border border-white/10 bg-white/[.05] p-3"><p class="text-[9px] font-bold uppercase tracking-[.2em] text-white/40">Locuri parc</p><p class="mt-1 font-display text-xl">412</p></div>
            <div class="rounded-2xl border border-white/10 bg-white/[.05] p-3"><p class="text-[9px] font-bold uppercase tracking-[.2em] text-white/40">Cel mai liber</p><p class="mt-1 font-display text-xl">09:00</p></div>
            <div class="rounded-2xl border border-white/10 bg-white/[.05] p-3"><p class="text-[9px] font-bold uppercase tracking-[.2em] text-white/40">Vreme</p><p class="mt-1 font-display text-xl">22° · senin</p></div>
          </div>
        </div>

        <div class="hero-cut relative min-h-[520px] overflow-hidden bg-pine-900 sm:min-h-[610px] lg:min-h-[650px] reveal reveal-delay-1">
          <img src="https://images.unsplash.com/photo-1500534314209-a25ddb2bd4297?w=1600&q=85" alt="Pădure luminată în timpul dimineții" class="absolute inset-0 h-full w-full object-cover opacity-50">
          <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/15 to-transparent"></div>
          <svg viewBox="0 0 780 650" class="absolute inset-0 h-full w-full" fill="none" aria-hidden="true">
            <path class="route" d="M95 530C165 470 150 382 246 345c77-30 87-116 178-137 80-18 132 49 211-30"/>
            <g class="orbit"><circle cx="392" cy="322" r="178" stroke="rgba(223,252,98,.18)" stroke-width="1"/><circle cx="392" cy="322" r="128" stroke="rgba(255,255,255,.13)" stroke-width="1"/></g>
            <circle cx="246" cy="345" r="8" fill="#DFFC62"/><circle cx="424" cy="208" r="8" fill="#F27B4A"/><circle cx="635" cy="178" r="8" fill="#FFFDF6"/>
          </svg>
          <div class="absolute left-4 top-5 rounded-full border border-white/15 bg-pine-950/70 px-4 py-2 text-[10px] font-bold uppercase tracking-[.2em] text-white/70 backdrop-blur sm:left-6 sm:top-7">Disponibilitate live · demo</div>

          <div class="absolute bottom-5 left-4 right-4 sm:bottom-7 sm:left-7 sm:right-auto sm:w-[360px]">
            <div class="float-card rounded-[26px] border border-white/15 bg-pine-950/80 p-4 shadow-2xl backdrop-blur-xl sm:p-5">
              <div class="flex items-center justify-between gap-4"><div><p class="text-[9px] font-bold uppercase tracking-[.2em] text-white/40">Fereastra recomandată</p><p class="mt-1 font-display text-2xl">Marți · 09:00–12:30</p></div><span class="grid h-11 w-11 flex-none place-items-center rounded-full bg-acid font-bold text-pine-950">84%</span></div>
              <div class="mt-4 grid grid-cols-3 gap-2 text-center"><div class="rounded-xl bg-white/[.06] p-2"><p class="text-[8px] uppercase tracking-[.16em] text-white/35">Flux</p><p class="mt-1 text-sm font-bold">Lejer</p></div><div class="rounded-xl bg-white/[.06] p-2"><p class="text-[8px] uppercase tracking-[.16em] text-white/35">Canopy</p><p class="mt-1 text-sm font-bold text-acid">7 locuri</p></div><div class="rounded-xl bg-white/[.06] p-2"><p class="text-[8px] uppercase tracking-[.16em] text-white/35">Vreme</p><p class="mt-1 text-sm font-bold">22°</p></div></div>
            </div>
          </div>
          <div class="absolute right-5 top-24 hidden w-[190px] rotate-3 rounded-[24px] border border-white/15 bg-cream p-4 text-pine-950 shadow-2xl sm:block"><p class="text-[8px] font-bold uppercase tracking-[.22em] text-pine-900/45">Nordvale note</p><p class="mt-3 font-display text-xl leading-tight">Diminețile de marți sunt cele mai liniștite.</p><div class="mt-4 h-px bg-pine-900/10"></div><p class="mt-3 text-xs leading-5 text-pine-900/60">Recomandare calculată din capacitatea dummy a calendarului.</p></div>
        </div>
      </div>
    </section>

    <section class="border-b border-pine-900/10 bg-cream">
      <div class="mx-auto grid max-w-[1540px] grid-cols-2 divide-x divide-y divide-pine-900/10 px-4 sm:px-6 lg:grid-cols-4 lg:divide-y-0 lg:px-8">
        <div class="py-5 pr-4 sm:py-6"><div class="flex items-center gap-3"><span class="availability-dot dot-open"></span><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/40">Disponibil</p><p class="mt-1 text-sm font-semibold">Peste 35% locuri</p></div></div></div>
        <div class="py-5 pl-4 sm:px-6 sm:py-6"><div class="flex items-center gap-3"><span class="availability-dot dot-limited"></span><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/40">Locuri puține</p><p class="mt-1 text-sm font-semibold">Sub 35% locuri</p></div></div></div>
        <div class="py-5 pr-4 sm:px-6 sm:py-6"><div class="flex items-center gap-3"><span class="availability-dot dot-soldout"></span><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/40">Complet</p><p class="mt-1 text-sm font-semibold">Activează alerta</p></div></div></div>
        <div class="py-5 pl-4 sm:pl-6 sm:py-6"><div class="flex items-center gap-3"><span class="availability-dot dot-closed"></span><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/40">Închis</p><p class="mt-1 text-sm font-semibold">Mentenanță / meteo</p></div></div></div>
      </div>
    </section>

    <section id="calendar" class="paper-grid py-16 sm:py-20 lg:py-28">
      <div class="mx-auto max-w-[1540px] px-4 sm:px-6 lg:px-8">
        <div class="reveal flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl"><p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700">Calendar operațional</p><h2 class="mt-3 font-display text-4xl font-semibold leading-[.95] tracking-[-.035em] sm:text-5xl lg:text-6xl">Ziua potrivită,<br><span class="italic text-pine-700">fără ghicit.</span></h2><p class="mt-5 max-w-2xl text-base leading-7 text-pine-900/62 sm:text-lg">Selectează o zi pentru a vedea capacitatea parcului, prognoza și intervalele disponibile pentru fiecare experiență.</p></div>
          <div class="flex items-center gap-2 self-start rounded-full border border-pine-900/10 bg-cream p-1 shadow-sm lg:self-auto"><button @click="changeMonth(-1)" class="grid h-11 w-11 place-items-center rounded-full transition hover:bg-pine-900 hover:text-white" aria-label="Luna anterioară">←</button><div class="min-w-[150px] text-center"><p class="font-display text-lg font-semibold" x-text="monthLabel"></p><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/35">Sezon 2026</p></div><button @click="changeMonth(1)" class="grid h-11 w-11 place-items-center rounded-full transition hover:bg-pine-900 hover:text-white" aria-label="Luna următoare">→</button></div>
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
                  <div class="flex items-start justify-between gap-1"><span class="day-number font-display text-lg font-semibold" x-text="day.number"></span><span x-show="!day.outside" class="day-meta text-[8px] font-bold uppercase tracking-[.12em] opacity-45" x-text="day.shortWeather"></span></div>
                  <div x-show="!day.outside" class="mt-3">
                    <div class="flex items-center gap-1.5"><span class="availability-dot" :class="dotClass(day.status)"></span><span class="day-meta truncate text-[9px] font-bold uppercase tracking-[.11em]" x-text="statusLabel(day.status)"></span></div>
                    <div class="capacity-bar mt-3"><div class="capacity-fill" :style="`width:${day.capacity}%`" :class="capacityClass(day.status)"></div></div>
                    <p class="capacity-label mt-1.5 text-[9px] opacity-48" x-text="day.status==='closed'?'—':day.capacity+'% liber'"></p>
                  </div>
                </button>
              </template>
            </div>
            <div class="mt-5 flex flex-col gap-3 border-t border-pine-900/8 pt-5 text-xs text-pine-900/48 sm:flex-row sm:items-center sm:justify-between"><p>Capacitatea afișată este dummy și se modifică după filtrul selectat.</p><button @click="jumpToRecommended()" class="self-start font-bold text-pine-700 underline decoration-pine-700/25 underline-offset-4 sm:self-auto">Arată-mi cea mai liberă zi</button></div>
          </div>

          <aside class="reveal reveal-delay-2 xl:sticky xl:top-28">
            <div class="overflow-hidden rounded-[30px] bg-pine-950 text-white shadow-[0_34px_100px_-50px_rgba(6,26,21,.9)] sm:rounded-[34px]">
              <div class="relative min-h-[190px] overflow-hidden p-5 sm:p-6">
                <img :src="selectedDay.image" alt="Peisaj Nordvale" class="absolute inset-0 h-full w-full object-cover opacity-38">
                <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/55 to-pine-950/20"></div>
                <div class="relative z-10"><div class="flex items-start justify-between gap-4"><div><p class="text-[9px] font-bold uppercase tracking-[.22em] text-white/45">Zi selectată</p><h3 class="mt-2 font-display text-3xl font-semibold leading-none" x-text="selectedDay.fullLabel"></h3></div><div class="rounded-2xl bg-acid px-3 py-2 text-center text-pine-950"><p class="text-[8px] font-bold uppercase tracking-[.14em]">Liber</p><p class="font-display text-2xl font-semibold" x-text="selectedDay.capacity+'%'"></p></div></div><div class="mt-8 flex flex-wrap gap-2"><span class="rounded-full border border-white/15 bg-white/[.08] px-3 py-1.5 text-xs font-semibold" x-text="selectedDay.weather"></span><span class="rounded-full border border-white/15 bg-white/[.08] px-3 py-1.5 text-xs font-semibold" x-text="selectedDay.hours"></span><span class="rounded-full border border-white/15 bg-white/[.08] px-3 py-1.5 text-xs font-semibold" x-text="selectedDay.flow"></span></div></div>
              </div>
              <div class="p-4 sm:p-5">
                <div class="rounded-[22px] border border-white/10 bg-white/[.055] p-4"><div class="flex items-center justify-between gap-3"><div><p class="text-[8px] font-bold uppercase tracking-[.2em] text-white/35">Acces general</p><p class="mt-1 font-display text-xl">Intrare parc + rezervație</p></div><p class="font-display text-2xl text-acid">55 lei</p></div><div class="mt-4 flex items-center justify-between text-xs text-white/55"><span x-text="selectedDay.generalTickets+' bilete disponibile'"></span><button @click="selectedDay.status==='soldout'?showAlert():openBooking('Acces general','Oricând între 09:00–18:00',55)" class="font-bold text-acid" x-text="selectedDay.status==='soldout'?'Activează alerta':'Adaugă ↗'"></button></div></div>

                <div x-show="selectedDay.status!=='soldout' && selectedDay.status!=='closed'" class="mt-4 space-y-2">
                  <template x-for="experience in visibleExperiences" :key="experience.id">
                    <div :class="{'is-open':openExperience===experience.id}" class="experience-row rounded-[22px] border border-white/10 bg-white/[.035]">
                      <button @click="openExperience=openExperience===experience.id?null:experience.id" class="flex w-full items-center gap-3 p-3.5 text-left sm:p-4"><span class="grid h-10 w-10 flex-none place-items-center rounded-full text-sm" :style="`background:${experience.color};color:#061a15`" x-text="experience.icon"></span><span class="min-w-0 flex-1"><span class="block truncate font-semibold" x-text="experience.name"></span><span class="mt-0.5 block text-[10px] text-white/42" x-text="experience.note"></span></span><span class="text-right"><span class="block font-display text-lg text-acid" x-text="experience.from+' lei'"></span><span class="row-chevron mt-0.5 block text-xs text-white/40">⌄</span></span></button>
                      <div class="experience-row__content"><div><div class="border-t border-white/8 px-3.5 pb-4 pt-3 sm:px-4"><p class="mb-2 text-[8px] font-bold uppercase tracking-[.2em] text-white/35">Alege intervalul</p><div class="grid grid-cols-2 gap-2 sm:grid-cols-3"><template x-for="slot in experience.slots" :key="slot.time"><button @click="!slot.sold&&openBooking(experience,slot.time,slot.price)" :disabled="slot.sold" class="slot-button rounded-xl border border-white/12 bg-white/[.06] px-2 py-2.5 text-center"><span class="block text-sm font-bold" x-text="slot.time"></span><span class="mt-0.5 block text-[8px] text-white/42" x-text="slot.sold?'Complet':slot.left+' locuri'"></span></button></template></div></div></div></div>
                    </div>
                  </template>
                </div>
                <div x-show="selectedDay.status==='soldout' || selectedDay.status==='closed'" class="mt-4 rounded-[22px] border border-white/10 bg-white/[.055] p-4 text-center">
                  <p class="font-display text-xl" x-text="selectedDay.status==='soldout'?'Zi complet rezervată':'Parcul este închis'"></p>
                  <p class="mt-2 text-xs leading-5 text-white/46" x-text="selectedDay.status==='soldout'?'Activează alerta și te anunțăm dacă apar locuri.':'Alege o altă zi din calendar pentru a continua.'"></p>
                  <button x-show="selectedDay.status==='soldout'" @click="showAlert()" class="mt-4 rounded-full bg-acid px-5 py-2.5 text-sm font-bold text-pine-950">Activează alerta</button>
                </div>
              </div>
            </div>
            <p class="mt-3 px-2 text-center text-[10px] leading-5 text-pine-900/45">Intervalele și capacitățile sunt date dummy pentru demonstrarea interfeței Tixello.</p>
          </aside>
        </div>
      </div>
    </section>

    <section id="ferestre" class="bg-cream py-16 sm:py-20 lg:py-28">
      <div class="mx-auto max-w-[1540px] px-4 sm:px-6 lg:px-8">
        <div class="reveal grid gap-7 lg:grid-cols-[.75fr_1.25fr] lg:items-end"><div><p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700">Ferestre recomandate</p><h2 class="mt-3 font-display text-4xl font-semibold leading-[.96] tracking-[-.035em] sm:text-5xl">Când pădurea<br><span class="italic text-pine-700">respiră mai larg.</span></h2></div><p class="max-w-2xl text-base leading-7 text-pine-900/60 sm:text-lg">Am transformat următoarele două săptămâni într-un ghid rapid: flux estimat, vreme și experiențe cu cea mai bună disponibilitate.</p></div>
        <div class="reveal reveal-delay-1 mt-9 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <template x-for="window in recommendedWindows" :key="window.date">
            <button @click="selectRecommended(window)" class="heat-card group rounded-[25px] border border-pine-900/10 bg-oat p-4 text-left sm:p-5"><div class="flex items-start justify-between gap-3"><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/38" x-text="window.weekday"></p><p class="mt-1 font-display text-2xl font-semibold" x-text="window.date"></p></div><span class="rounded-full px-2.5 py-1 text-[9px] font-bold uppercase tracking-[.13em]" :style="`background:${window.badgeBg};color:${window.badgeText}`" x-text="window.flow"></span></div><div class="mt-6 flex items-end justify-between"><div><p class="text-xs text-pine-900/42" x-text="window.weather"></p><p class="mt-1 text-sm font-bold" x-text="window.best"></p></div><div class="grid h-10 w-10 place-items-center rounded-full bg-pine-900 text-white transition group-hover:bg-acid group-hover:text-pine-950">↗</div></div></button>
          </template>
        </div>
      </div>
    </section>

    <section id="ghid" class="topo-dark relative overflow-hidden bg-pine-950 py-16 text-white sm:py-20 lg:py-28">
      <div class="pointer-events-none absolute -right-20 top-10 h-80 w-80 rounded-full border border-acid/10"></div>
      <div class="relative z-10 mx-auto max-w-[1540px] px-4 sm:px-6 lg:px-8">
        <div class="reveal grid gap-10 lg:grid-cols-[.8fr_1.2fr] lg:items-start">
          <div class="lg:sticky lg:top-28"><p class="text-[10px] font-bold uppercase tracking-[.24em] text-acid">Ghidul disponibilității</p><h2 class="mt-3 font-display text-4xl font-semibold leading-[.95] tracking-[-.035em] sm:text-5xl lg:text-6xl">Un calendar care<br><span class="outline-word italic">spune adevărul.</span></h2><p class="mt-6 max-w-xl text-base leading-7 text-white/58 sm:text-lg">Nu toate biletele funcționează la fel. Accesul general permite intrarea în parc, iar experiențele ghidate și traseele tehnice folosesc intervale rezervate.</p></div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-[28px] border border-white/10 bg-white/[.055] p-5 sm:p-6"><span class="grid h-11 w-11 place-items-center rounded-full bg-acid font-bold text-pine-950">01</span><h3 class="mt-5 font-display text-2xl">Alege întâi ziua</h3><p class="mt-3 text-sm leading-6 text-white/52">Capacitatea zilei îți arată fluxul general din parc și disponibilitatea biletelor de acces.</p></div>
            <div class="rounded-[28px] border border-white/10 bg-white/[.055] p-5 sm:p-6"><span class="grid h-11 w-11 place-items-center rounded-full bg-ember font-bold text-pine-950">02</span><h3 class="mt-5 font-display text-2xl">Apoi experiența</h3><p class="mt-3 text-sm leading-6 text-white/52">Canopy Run, tururile ghidate și activitățile de copii necesită un interval separat.</p></div>
            <div class="rounded-[28px] border border-white/10 bg-white/[.055] p-5 sm:p-6"><span class="grid h-11 w-11 place-items-center rounded-full bg-sky font-bold text-pine-950">03</span><h3 class="mt-5 font-display text-2xl">Păstrează marja</h3><p class="mt-3 text-sm leading-6 text-white/52">Ajungi cu 30 de minute înainte pentru check-in, echipare și briefing.</p></div>
            <div class="rounded-[28px] border border-white/10 bg-white/[.055] p-5 sm:p-6"><span class="grid h-11 w-11 place-items-center rounded-full bg-cream font-bold text-pine-950">04</span><h3 class="mt-5 font-display text-2xl">Meteo fără surprize</h3><p class="mt-3 text-sm leading-6 text-white/52">Dacă o activitate este suspendată, biletul poate fi mutat într-un alt interval disponibil.</p></div>
          </div>
        </div>
      </div>
    </section>

    <section id="bilete" class="paper-grid py-16 sm:py-20 lg:py-28">
      <div class="mx-auto max-w-[1540px] px-4 sm:px-6 lg:px-8">
        <div class="reveal relative overflow-hidden rounded-[34px] bg-ember p-6 text-pine-950 sm:p-9 lg:p-12">
          <svg viewBox="0 0 500 300" class="pointer-events-none absolute -right-16 -top-8 h-[330px] w-[550px] opacity-16" fill="none"><path d="M20 236c75-150 173 21 247-91 67-102 120-29 206-115" stroke="#061A15" stroke-width="2" stroke-dasharray="9 13"/><circle cx="267" cy="145" r="82" stroke="#061A15"/><circle cx="267" cy="145" r="130" stroke="#061A15"/></svg>
          <div class="relative z-10 grid gap-7 lg:grid-cols-[1fr_auto] lg:items-end"><div><p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-950/55">Pregătit de plecare?</p><h2 class="mt-3 max-w-4xl font-display text-4xl font-semibold leading-[.94] tracking-[-.04em] sm:text-5xl lg:text-6xl">Alege ziua acum.<br><span class="italic">Pădurea se ocupă de restul.</span></h2><p class="mt-5 max-w-2xl text-base leading-7 text-pine-950/66">Biletul de acces pornește de la 55 lei. Experiențele cu interval se adaugă separat în aceeași comandă.</p></div><a href="#calendar" class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-full bg-pine-950 px-7 py-4 font-bold text-white transition hover:-translate-y-0.5 sm:w-auto">Deschide calendarul <span>↑</span></a></div>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <div x-show="bookingOpen" x-cloak class="fixed inset-0 z-[210]" role="dialog" aria-modal="true" aria-label="Rezervare rapidă">
    <button @click="closeBooking()" class="drawer-backdrop absolute inset-0 w-full" aria-label="Închide rezervarea"></button>
    <div class="drawer-panel safe-bottom absolute inset-x-0 bottom-0 max-h-[92vh] overflow-y-auto rounded-t-[30px] bg-cream p-5 sm:left-auto sm:right-0 sm:top-0 sm:h-full sm:max-h-none sm:w-[460px] sm:rounded-none sm:p-7">
      <div class="flex items-start justify-between gap-4"><div><p class="text-[9px] font-bold uppercase tracking-[.2em] text-pine-700">Rezervare rapidă</p><h2 class="mt-2 font-display text-3xl font-semibold">Adaugă în ziua ta</h2></div><button @click="closeBooking()" class="grid h-10 w-10 flex-none place-items-center rounded-full border border-pine-900/12 text-xl">×</button></div>
      <div class="mt-6 overflow-hidden rounded-[24px] bg-pine-950 text-white"><div class="p-5"><p class="text-[8px] font-bold uppercase tracking-[.2em] text-white/35" x-text="selectedDay.fullLabel"></p><h3 class="mt-2 font-display text-2xl" x-text="bookingItem.name"></h3><p class="mt-2 text-sm text-white/55" x-text="bookingItem.slot"></p><div class="mt-5 flex items-end justify-between"><span class="text-sm text-white/46">Preț / persoană</span><span class="font-display text-3xl text-acid" x-text="bookingItem.price+' lei'"></span></div></div></div>
      <div class="mt-6"><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-900/42">Participanți</p><div class="mt-3 flex items-center justify-between rounded-2xl border border-pine-900/12 bg-white p-3"><div><p class="font-semibold">Persoane</p><p class="text-xs text-pine-900/45">Maximum 8 în rezervarea demo</p></div><div class="flex items-center gap-3"><button @click="guests=Math.max(1,guests-1)" class="grid h-9 w-9 place-items-center rounded-full border border-pine-900/12">−</button><span class="w-5 text-center font-bold" x-text="guests"></span><button @click="guests=Math.min(8,guests+1)" class="grid h-9 w-9 place-items-center rounded-full bg-pine-900 text-white">+</button></div></div></div>
      <div class="mt-6 rounded-2xl bg-oat p-4"><div class="flex justify-between text-sm"><span class="text-pine-900/50" x-text="guests+' × '+bookingItem.price+' lei'"></span><span class="font-semibold" x-text="subtotal+' lei'"></span></div><div class="mt-3 flex justify-between border-t border-pine-900/10 pt-3"><span class="font-bold">Total</span><span class="font-display text-2xl font-semibold" x-text="subtotal+' lei'"></span></div></div>
      <button @click="confirmBooking()" class="mt-5 w-full rounded-full bg-acid px-6 py-4 font-bold text-pine-950 transition hover:-translate-y-0.5">Adaugă în planul meu</button>
      <p class="mt-3 text-center text-[10px] leading-5 text-pine-900/42">Acesta este un flux demonstrativ. Nu se procesează nicio plată.</p>
    </div>
  </div>

  <div x-show="toast" x-cloak x-transition class="fixed bottom-4 left-1/2 z-[230] w-[calc(100%-32px)] max-w-md -translate-x-1/2 rounded-2xl bg-pine-950 px-4 py-3 text-center text-sm font-semibold text-white shadow-2xl safe-bottom" x-text="toastMessage"></div>

  <script>
    function calendarPage(){
      return {
        menu:false, bookingOpen:false, toast:false, toastMessage:'', activeFilter:'all', openExperience:'canopy', guests:2,
        currentMonth:7, currentYear:2026, selectedKey:'2026-08-04',
        bookingItem:{name:'Canopy Run',slot:'09:30',price:95},
        weekDays:['Lun','Mar','Mie','Joi','Vin','Sâm','Dum'],
        filters:[
          {id:'all',label:'Toate experiențele',icon:'✦'},
          {id:'height',label:'La înălțime',icon:'↗'},
          {id:'family',label:'Familie',icon:'⌂'},
          {id:'guided',label:'Ghidate',icon:'◎'},
          {id:'nature',label:'Natură',icon:'⌁'}
        ],
        experiences:[
          {id:'canopy',filter:'height',name:'Canopy Run',icon:'↗',color:'#dffc62',from:95,note:'Traseu tehnic · 90 minute',slots:[{time:'09:30',left:7,price:95},{time:'11:30',left:3,price:95},{time:'14:00',left:0,price:95,sold:true},{time:'16:00',left:5,price:95}]},
          {id:'junior',filter:'family',name:'Junior Grove',icon:'⌂',color:'#b8dce0',from:45,note:'Copii 4–9 ani · 60 minute',slots:[{time:'10:00',left:12,price:45},{time:'12:00',left:8,price:45},{time:'15:00',left:4,price:45},{time:'17:00',left:9,price:45}]},
          {id:'wildlife',filter:'guided',name:'Wildlife Walk',icon:'◎',color:'#f27b4a',from:70,note:'Tur ghidat · grup restrâns',slots:[{time:'08:30',left:5,price:70},{time:'13:30',left:0,price:70,sold:true},{time:'18:00',left:6,price:70}]},
          {id:'forest',filter:'nature',name:'Forest Quest',icon:'⌁',color:'#fffdf6',from:55,note:'Explorare autonomă · 2 ore',slots:[{time:'09:00',left:18,price:55},{time:'11:00',left:14,price:55},{time:'13:00',left:9,price:55},{time:'15:00',left:11,price:55}]}
        ],
        recommendedWindows:[
          {weekday:'Marți',date:'4 august',flow:'Lejer',weather:'22° · senin',best:'09:00–12:30',badgeBg:'#dffc62',badgeText:'#061a15',key:'2026-08-04'},
          {weekday:'Joi',date:'6 august',flow:'Lejer',weather:'23° · variabil',best:'09:30–13:00',badgeBg:'#dffc62',badgeText:'#061a15',key:'2026-08-06'},
          {weekday:'Miercuri',date:'12 august',flow:'Moderat',weather:'21° · senin',best:'10:00–14:00',badgeBg:'#b8dce0',badgeText:'#061a15',key:'2026-08-12'},
          {weekday:'Vineri',date:'14 august',flow:'Moderat',weather:'24° · senin',best:'09:00–11:30',badgeBg:'#b8dce0',badgeText:'#061a15',key:'2026-08-14'}
        ],
        dayOverrides:{
          '2026-08-01':{capacity:18,status:'limited',weather:'26° · senin',flow:'Intens',generalTickets:94},
          '2026-08-02':{capacity:0,status:'soldout',weather:'27° · senin',flow:'Complet',generalTickets:0},
          '2026-08-03':{capacity:0,status:'closed',weather:'—',flow:'Închis',generalTickets:0},
          '2026-08-04':{capacity:84,status:'open',weather:'22° · senin',flow:'Lejer',generalTickets:412},
          '2026-08-05':{capacity:62,status:'open',weather:'23° · nori',flow:'Moderat',generalTickets:306},
          '2026-08-06':{capacity:78,status:'open',weather:'23° · variabil',flow:'Lejer',generalTickets:382},
          '2026-08-07':{capacity:44,status:'open',weather:'24° · senin',flow:'Moderat',generalTickets:220},
          '2026-08-08':{capacity:22,status:'limited',weather:'25° · senin',flow:'Intens',generalTickets:108},
          '2026-08-09':{capacity:9,status:'limited',weather:'25° · senin',flow:'Foarte intens',generalTickets:44},
          '2026-08-10':{capacity:0,status:'closed',weather:'—',flow:'Închis',generalTickets:0},
          '2026-08-11':{capacity:69,status:'open',weather:'21° · senin',flow:'Lejer',generalTickets:340},
          '2026-08-12':{capacity:72,status:'open',weather:'21° · senin',flow:'Lejer',generalTickets:356},
          '2026-08-13':{capacity:55,status:'open',weather:'23° · nori',flow:'Moderat',generalTickets:270},
          '2026-08-14':{capacity:64,status:'open',weather:'24° · senin',flow:'Moderat',generalTickets:315},
          '2026-08-15':{capacity:12,status:'limited',weather:'26° · senin',flow:'Foarte intens',generalTickets:60},
          '2026-08-16':{capacity:0,status:'soldout',weather:'26° · senin',flow:'Complet',generalTickets:0},
          '2026-08-17':{capacity:0,status:'closed',weather:'—',flow:'Închis',generalTickets:0}
        },
        serverExperiences: <?php echo $calItemsJs; ?>,
        init(){
          if (Array.isArray(this.serverExperiences) && this.serverExperiences.length) {
            this.experiences = this.serverExperiences;
            this.openExperience = this.serverExperiences[0].id;
          }
          requestAnimationFrame(()=>{
            const selected=this.calendarDays.find(day=>day.key===this.selectedKey); if(selected) this.selectedKey=selected.key;
            this.setupReveal(); this.setupProgress();
          });
        },
        get monthLabel(){return new Intl.DateTimeFormat('ro-RO',{month:'long',year:'numeric'}).format(new Date(this.currentYear,this.currentMonth,1)).replace(/^./,m=>m.toUpperCase())},
        get calendarDays(){
          const first=new Date(this.currentYear,this.currentMonth,1); const last=new Date(this.currentYear,this.currentMonth+1,0); const mondayIndex=(first.getDay()+6)%7; const total=Math.ceil((mondayIndex+last.getDate())/7)*7; const days=[];
          for(let i=0;i<total;i++){
            const number=i-mondayIndex+1; const date=new Date(this.currentYear,this.currentMonth,number); const outside=date.getMonth()!==this.currentMonth; const key=this.dateKey(date); const data=this.makeDayData(date,key,outside); days.push(data);
          }
          return days;
        },
        makeDayData(date,key,outside){
          const number=date.getDate(); const dow=date.getDay(); const baseCapacity=Math.max(8,86-((number*17+dow*13)%72)); let status=dow===1?'closed':baseCapacity<18?'limited':'open'; let capacity=status==='closed'?0:baseCapacity; let generalTickets=Math.round(capacity*4.8); let weather=['21° · senin','22° · variabil','23° · nori','24° · senin'][number%4]; let flow=capacity>70?'Lejer':capacity>40?'Moderat':capacity>0?'Intens':'Închis';
          if(outside){status='closed';capacity=0;generalTickets=0}
          const override=this.dayOverrides[key]||{}; const final={...{capacity,status,weather,flow,generalTickets},...override};
          const fullLabel=new Intl.DateTimeFormat('ro-RO',{weekday:'long',day:'numeric',month:'long'}).format(date).replace(/^./,m=>m.toUpperCase());
          return {key,number,outside,today:key==='2026-07-26',fullLabel,shortWeather:final.weather.split(' ')[0],hours:final.status==='closed'?'Închis':'09:00–20:00',image:this.dayImage(number),aria:`${fullLabel}, ${this.statusLabel(final.status)}, ${final.capacity}% locuri libere`,...final};
        },
        dayImage(number){const images=['https://images.unsplash.com/photo-1448375240586-882707db888b?w=1000&q=80','https://images.unsplash.com/photo-1501854140801-50d01698950b?w=1000&q=80','https://images.unsplash.com/photo-1473448912268-2022ce9509d8?w=1000&q=80'];return images[number%images.length]},
        dateKey(date){return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`},
        get selectedDay(){return this.calendarDays.find(day=>day.key===this.selectedKey&&!day.outside)||this.calendarDays.find(day=>!day.outside&&day.status!=='closed')||this.calendarDays[0]},
        get visibleExperiences(){return this.activeFilter==='all'?this.experiences:this.experiences.filter(item=>item.filter===this.activeFilter)},
        get subtotal(){return this.bookingItem.price*this.guests},
        setFilter(id){this.activeFilter=id;this.openExperience=(id==='all'?this.experiences:this.experiences.filter(item=>item.filter===id))[0]?.id||null},
        selectDay(day){if(day.outside||day.status==='closed')return;this.selectedKey=day.key;this.openExperience=this.visibleExperiences[0]?.id||null;requestAnimationFrame(()=>{if(window.innerWidth<1280){document.querySelector('aside')?.scrollIntoView({behavior:'smooth',block:'start'})}})},
        changeMonth(delta){const next=new Date(this.currentYear,this.currentMonth+delta,1);this.currentYear=next.getFullYear();this.currentMonth=next.getMonth();requestAnimationFrame(()=>{const first=this.calendarDays.find(day=>!day.outside&&day.status!=='closed');if(first)this.selectedKey=first.key})},
        dayClasses(day){return {'is-selected':day.key===this.selectedKey&&!day.outside,'is-outside':day.outside,'is-closed':day.status==='closed','is-soldout':day.status==='soldout','is-today':day.today}},
        dotClass(status){return {'dot-open':status==='open','dot-limited':status==='limited','dot-soldout':status==='soldout','dot-closed':status==='closed'}},
        capacityClass(status){return {'bg-[#61a87e]':status==='open','bg-ember':status==='limited','bg-[#c55745]':status==='soldout','bg-[#9d998e]':status==='closed'}},
        statusLabel(status){return {open:'Disponibil',limited:'Locuri puține',soldout:'Complet',closed:'Închis'}[status]||'Disponibil'},
        jumpToRecommended(){this.selectedKey='2026-08-04';this.currentYear=2026;this.currentMonth=7;requestAnimationFrame(()=>document.querySelector('aside')?.scrollIntoView({behavior:'smooth',block:'start'}))},
        selectRecommended(windowData){this.currentYear=2026;this.currentMonth=7;this.selectedKey=windowData.key;document.querySelector('#calendar')?.scrollIntoView({behavior:'smooth',block:'start'})},
        openBooking(exp,slot,price){
          const isObj = exp && typeof exp === 'object';
          this.bookingItem={
            name: isObj ? exp.name : exp,
            slot, price,
            slug: isObj ? (exp.slug||'') : '',
            event_id: isObj ? (exp.event_id||null) : null,
            ticket_type_id: isObj ? (exp.ticket_type_id||null) : null,
            image: isObj ? (exp.image||'') : '',
            venue: isObj ? (exp.note||'Nordvale') : 'Nordvale'
          };
          this.guests=2;this.bookingOpen=true;document.documentElement.style.overflow='hidden'
        },
        closeBooking(){this.bookingOpen=false;document.documentElement.style.overflow=''},
        showAlert(){this.toastMessage='Alerta de disponibilitate a fost activată pentru această zi.';this.toast=true;setTimeout(()=>this.toast=false,3200)},
        confirmBooking(){
          const b=this.bookingItem;
          if(b.event_id && b.ticket_type_id){
            const dateLabel=(this.selectedDay && this.selectedDay.fullLabel) ? this.selectedDay.fullLabel : (this.selectedKey||'');
            const qty=Math.max(1,this.guests);
            const cart={
              event:{id:b.event_id,title:b.name,image:b.image,date:dateLabel,venue:b.venue},
              items:[{ticket_type_id:b.ticket_type_id,title:b.name,date:dateLabel,slot:b.slot,qty:qty,unit_price:b.price}],
              subtotal:qty*b.price
            };
            try{localStorage.setItem('nordvale_cart',JSON.stringify(cart));}catch(e){}
            window.location.href='/cos';
            return;
          }
          this.closeBooking();this.toastMessage=`${this.bookingItem.name} a fost adăugat în planul tău.`;this.toast=true;setTimeout(()=>this.toast=false,3200)
        },
        setupReveal(){const nodes=[...document.querySelectorAll('.reveal')];if(!('IntersectionObserver'in window)){nodes.forEach(n=>n.classList.add('is-visible'));return}const io=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');io.unobserve(entry.target)}}),{threshold:.12});nodes.forEach(node=>io.observe(node))},
        setupProgress(){const bar=document.getElementById('pageProgress');if(!bar)return;const update=()=>{const max=document.documentElement.scrollHeight-innerHeight;const ratio=max>0?scrollY/max:0;bar.style.transform=`scaleX(${Math.min(1,Math.max(0,ratio))})`};update();addEventListener('scroll',update,{passive:true})}
      }
    }
  </script>
</body>
</html>
