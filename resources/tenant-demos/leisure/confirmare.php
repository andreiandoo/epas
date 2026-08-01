<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$orderId = (int) ($_GET['order'] ?? 0);
$summary = $orderId ? tc_order_summary($orderId) : null;
$hasOrder = is_array($summary);

// Valori reale (cu fallback pe conținutul demo, ca pagina să se randeze mereu)
$ord_codeRaw  = $hasOrder ? (string) ($summary['order_id'] ?? $orderId) : 'NV-270726-1842';
$ord_code     = '#' . $ord_codeRaw;
$ord_email    = $hasOrder ? (string) ($summary['customer_email'] ?? '') : 'andrei@example.com';
$ord_paid     = $hasOrder ? (bool) ($summary['is_paid'] ?? false) : false;
$ord_currency = $hasOrder ? ($summary['currency'] ?? 'RON') : 'RON';
$ord_total    = $hasOrder ? price_fmt($summary['total'] ?? 0, $ord_currency) : '786 RON';
$ord_payment  = $hasOrder ? (string) ($summary['payment_method'] ?? 'Card (demo)') : 'cardul •••• 4242';

$ev           = $hasOrder ? ($summary['event'] ?? null) : null;
$ord_title    = ($ev && ! empty($ev['title'])) ? $ev['title'] : 'Nordvale Adventure Day';
$ord_venue    = ($ev && ! empty($ev['venue'])) ? $ev['venue'] : 'Poarta Nord';
$ord_custname = $hasOrder ? (string) ($summary['customer_name'] ?? '') : 'Andrei Popescu + 2 invitați';
if ($hasOrder && $ord_custname === '') { $ord_custname = 'Participant'; }

$ord_tickets  = ($hasOrder && ! empty($summary['tickets'])) ? $summary['tickets'] : [];
$ticketCount  = $hasOrder ? max(1, count($ord_tickets)) : 3;

$ord_dateFull = '15 august 2026';
$ord_time     = '09:30';
if ($ev && ! empty($ev['date']) && ($ts = strtotime($ev['date']))) { $ord_dateFull = date('d.m.Y', $ts); }
if ($ev && ! empty($ev['time'])) { $ord_time = substr((string) $ev['time'], 0, 5); }

// Bilete pentru grila JS (din comanda reală)
$ticketsJs = [];
if ($hasOrder && $ord_tickets) {
    $n = count($ord_tickets);
    foreach (array_values($ord_tickets) as $i => $t) {
        $type = $t['type'] ?? 'Bilet';
        if (! empty($t['seat_label'])) { $type .= ' · ' . $t['seat_label']; }
        $ticketsJs[] = [
            'no'   => sprintf('%02d / %02d', $i + 1, $n),
            'name' => $ord_custname ?: 'Participant',
            'type' => $type,
            'code' => $t['code'] ?? '',
            'seed' => ($i + 1) * 3,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ro" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#061a15">
  <title>Comandă confirmată — Nordvale</title>
  <meta name="description" content="Pagina de confirmare a comenzii pentru tenantul leisure Nordvale.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
  <style id="tailwind-compiled">

/*! tailwindcss v4.1.10 | MIT License | https://tailwindcss.com */
@layer properties;
:root, :host {
  --font-sans: "DM Sans", sans-serif;
  --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New',
    monospace;
  --color-white: #fff;
  --spacing: 0.25rem;
  --container-xl: 36rem;
  --container-2xl: 42rem;
  --container-3xl: 48rem;
  --container-4xl: 56rem;
  --container-7xl: 80rem;
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
  --font-weight-medium: 500;
  --font-weight-semibold: 600;
  --font-weight-bold: 700;
  --leading-tight: 1.25;
  --radius-xl: 0.75rem;
  --radius-2xl: 1rem;
  --blur-sm: 8px;
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
  --color-pine-600: #2e6e59;
  --color-acid: #dffc62;
  --color-ember: #f27b4a;
  --color-cream: #fffdf6;
  --color-oat: #f0ecdf;
  --color-sky: #b8dce0;
}
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
  font-family: var(--default-font-family, ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji');
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
  font-family: var(--default-mono-font-family, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace);
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
button, input:where([type='button'], [type='reset'], [type='submit']), ::file-selector-button {
  appearance: button;
}
::-webkit-inner-spin-button, ::-webkit-outer-spin-button {
  height: auto;
}
[hidden]:where(:not([hidden='until-found'])) {
  display: none !important;
}
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
.sticky {
  position: sticky;
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
.top-2 {
  top: calc(var(--spacing) * 2);
}
.top-20 {
  top: calc(var(--spacing) * 20);
}
.top-24 {
  top: calc(var(--spacing) * 24);
}
.-right-2 {
  right: calc(var(--spacing) * -2);
}
.-right-24 {
  right: calc(var(--spacing) * -24);
}
.bottom-0 {
  bottom: calc(var(--spacing) * 0);
}
.z-10 {
  z-index: 10;
}
.z-40 {
  z-index: 40;
}
.z-50 {
  z-index: 50;
}
.z-\[80\] {
  z-index: 80;
}
.z-\[85\] {
  z-index: 85;
}
.-mx-4 {
  margin-inline: calc(var(--spacing) * -4);
}
.mx-auto {
  margin-inline: auto;
}
.my-6 {
  margin-block: calc(var(--spacing) * 6);
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
.mt-12 {
  margin-top: calc(var(--spacing) * 12);
}
.mb-1 {
  margin-bottom: calc(var(--spacing) * 1);
}
.mb-2 {
  margin-bottom: calc(var(--spacing) * 2);
}
.mb-3 {
  margin-bottom: calc(var(--spacing) * 3);
}
.mb-4 {
  margin-bottom: calc(var(--spacing) * 4);
}
.mb-5 {
  margin-bottom: calc(var(--spacing) * 5);
}
.mb-7 {
  margin-bottom: calc(var(--spacing) * 7);
}
.mb-10 {
  margin-bottom: calc(var(--spacing) * 10);
}
.ml-1 {
  margin-left: calc(var(--spacing) * 1);
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
.h-3\.5 {
  height: calc(var(--spacing) * 3.5);
}
.h-4 {
  height: calc(var(--spacing) * 4);
}
.h-5 {
  height: calc(var(--spacing) * 5);
}
.h-6 {
  height: calc(var(--spacing) * 6);
}
.h-7 {
  height: calc(var(--spacing) * 7);
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
.h-16 {
  height: calc(var(--spacing) * 16);
}
.h-36 {
  height: calc(var(--spacing) * 36);
}
.h-\[58px\] {
  height: 58px;
}
.h-\[62px\] {
  height: 62px;
}
.h-\[76px\] {
  height: 76px;
}
.h-\[420px\] {
  height: 420px;
}
.h-full {
  height: 100%;
}
.max-h-\[88dvh\] {
  max-height: 88dvh;
}
.max-h-\[92dvh\] {
  max-height: 92dvh;
}
.min-h-\[42px\] {
  min-height: 42px;
}
.min-h-\[180px\] {
  min-height: 180px;
}
.w-3\.5 {
  width: calc(var(--spacing) * 3.5);
}
.w-4 {
  width: calc(var(--spacing) * 4);
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
.w-9 {
  width: calc(var(--spacing) * 9);
}
.w-10 {
  width: calc(var(--spacing) * 10);
}
.w-11 {
  width: calc(var(--spacing) * 11);
}
.w-12 {
  width: calc(var(--spacing) * 12);
}
.w-16 {
  width: calc(var(--spacing) * 16);
}
.w-36 {
  width: calc(var(--spacing) * 36);
}
.w-\[76px\] {
  width: 76px;
}
.w-\[660px\] {
  width: 660px;
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
.max-w-7xl {
  max-width: var(--container-7xl);
}
.max-w-\[1440px\] {
  max-width: 1440px;
}
.max-w-xl {
  max-width: var(--container-xl);
}
.min-w-0 {
  min-width: calc(var(--spacing) * 0);
}
.min-w-8 {
  min-width: calc(var(--spacing) * 8);
}
.min-w-\[76vw\] {
  min-width: 76vw;
}
.flex-1 {
  flex: 1;
}
.flex-none {
  flex: none;
}
.cursor-not-allowed {
  cursor: not-allowed;
}
.snap-x {
  scroll-snap-type: x var(--tw-scroll-snap-strictness);
}
.snap-start {
  scroll-snap-align: start;
}
.grid-cols-3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.flex-col {
  flex-direction: column;
}
.flex-row {
  flex-direction: row;
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
.gap-2\.5 {
  gap: calc(var(--spacing) * 2.5);
}
.gap-3 {
  gap: calc(var(--spacing) * 3);
}
.gap-4 {
  gap: calc(var(--spacing) * 4);
}
.gap-7 {
  gap: calc(var(--spacing) * 7);
}
.gap-8 {
  gap: calc(var(--spacing) * 8);
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
.space-y-7 {
  :where(& > :not(:last-child)) {
    --tw-space-y-reverse: 0;
    margin-block-start: calc(calc(var(--spacing) * 7) * var(--tw-space-y-reverse));
    margin-block-end: calc(calc(var(--spacing) * 7) * calc(1 - var(--tw-space-y-reverse)));
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
.rounded-\[26px\] {
  border-radius: 26px;
}
.rounded-\[28px\] {
  border-radius: 28px;
}
.rounded-\[30px\] {
  border-radius: 30px;
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
.border-dashed {
  --tw-border-style: dashed;
  border-style: dashed;
}
.border-acid {
  border-color: var(--color-acid);
}
.border-pine-900 {
  border-color: var(--color-pine-900);
}
.border-pine-900\/6 {
  border-color: color-mix(in srgb, #09251d 6%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-pine-900) 6%, transparent);
  }
}
.border-pine-900\/8 {
  border-color: color-mix(in srgb, #09251d 8%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-pine-900) 8%, transparent);
  }
}
.border-pine-900\/9 {
  border-color: color-mix(in srgb, #09251d 9%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-pine-900) 9%, transparent);
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
.border-pine-900\/14 {
  border-color: color-mix(in srgb, #09251d 14%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-pine-900) 14%, transparent);
  }
}
.border-pine-900\/15 {
  border-color: color-mix(in srgb, #09251d 15%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-pine-900) 15%, transparent);
  }
}
.border-pine-900\/16 {
  border-color: color-mix(in srgb, #09251d 16%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-pine-900) 16%, transparent);
  }
}
.border-pine-900\/18 {
  border-color: color-mix(in srgb, #09251d 18%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-pine-900) 18%, transparent);
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
.border-white\/14 {
  border-color: color-mix(in srgb, #fff 14%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-white) 14%, transparent);
  }
}
.border-white\/15 {
  border-color: color-mix(in srgb, #fff 15%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-white) 15%, transparent);
  }
}
.border-white\/16 {
  border-color: color-mix(in srgb, #fff 16%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-white) 16%, transparent);
  }
}
.border-white\/20 {
  border-color: color-mix(in srgb, #fff 20%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-white) 20%, transparent);
  }
}
.border-white\/35 {
  border-color: color-mix(in srgb, #fff 35%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    border-color: color-mix(in oklab, var(--color-white) 35%, transparent);
  }
}
.bg-acid {
  background-color: var(--color-acid);
}
.bg-cream {
  background-color: var(--color-cream);
}
.bg-cream\/94 {
  background-color: color-mix(in srgb, #fffdf6 94%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-cream) 94%, transparent);
  }
}
.bg-cream\/95 {
  background-color: color-mix(in srgb, #fffdf6 95%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-cream) 95%, transparent);
  }
}
.bg-ember {
  background-color: var(--color-ember);
}
.bg-oat {
  background-color: var(--color-oat);
}
.bg-oat\/55 {
  background-color: color-mix(in srgb, #f0ecdf 55%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-oat) 55%, transparent);
  }
}
.bg-oat\/70 {
  background-color: color-mix(in srgb, #f0ecdf 70%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-oat) 70%, transparent);
  }
}
.bg-pine-900 {
  background-color: var(--color-pine-900);
}
.bg-pine-950 {
  background-color: var(--color-pine-950);
}
.bg-pine-950\/72 {
  background-color: color-mix(in srgb, #061a15 72%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-pine-950) 72%, transparent);
  }
}
.bg-pine-950\/94 {
  background-color: color-mix(in srgb, #061a15 94%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-pine-950) 94%, transparent);
  }
}
.bg-sky {
  background-color: var(--color-sky);
}
.bg-white {
  background-color: var(--color-white);
}
.bg-white\/5 {
  background-color: color-mix(in srgb, #fff 5%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-white) 5%, transparent);
  }
}
.bg-white\/6 {
  background-color: color-mix(in srgb, #fff 6%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-white) 6%, transparent);
  }
}
.bg-white\/8 {
  background-color: color-mix(in srgb, #fff 8%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-white) 8%, transparent);
  }
}
.bg-white\/40 {
  background-color: color-mix(in srgb, #fff 40%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-white) 40%, transparent);
  }
}
.bg-white\/\[\.045\] {
  background-color: color-mix(in srgb, #fff 4.5%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-white) 4.5%, transparent);
  }
}
.bg-white\/\[\.055\] {
  background-color: color-mix(in srgb, #fff 5.5%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-white) 5.5%, transparent);
  }
}
.bg-white\/\[\.065\] {
  background-color: color-mix(in srgb, #fff 6.5%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    background-color: color-mix(in oklab, var(--color-white) 6.5%, transparent);
  }
}
.bg-gradient-to-t {
  --tw-gradient-position: to top in oklab;
  background-image: linear-gradient(var(--tw-gradient-stops));
}
.from-pine-950\/70 {
  --tw-gradient-from: color-mix(in srgb, #061a15 70%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    --tw-gradient-from: color-mix(in oklab, var(--color-pine-950) 70%, transparent);
  }
  --tw-gradient-stops: var(--tw-gradient-via-stops, var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position));
}
.via-transparent {
  --tw-gradient-via: transparent;
  --tw-gradient-via-stops: var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-via) var(--tw-gradient-via-position), var(--tw-gradient-to) var(--tw-gradient-to-position);
  --tw-gradient-stops: var(--tw-gradient-via-stops);
}
.to-transparent {
  --tw-gradient-to: transparent;
  --tw-gradient-stops: var(--tw-gradient-via-stops, var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position));
}
.object-cover {
  object-fit: cover;
}
.p-2\.5 {
  padding: calc(var(--spacing) * 2.5);
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
.p-7 {
  padding: calc(var(--spacing) * 7);
}
.p-\[5px\] {
  padding: 5px;
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
.py-10 {
  padding-block: calc(var(--spacing) * 10);
}
.py-12 {
  padding-block: calc(var(--spacing) * 12);
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
.pt-28 {
  padding-top: calc(var(--spacing) * 28);
}
.pb-1 {
  padding-bottom: calc(var(--spacing) * 1);
}
.pb-3 {
  padding-bottom: calc(var(--spacing) * 3);
}
.pb-20 {
  padding-bottom: calc(var(--spacing) * 20);
}
.pb-28 {
  padding-bottom: calc(var(--spacing) * 28);
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
.font-mono {
  font-family: var(--font-mono);
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
.text-\[8px\] {
  font-size: 8px;
}
.text-\[9px\] {
  font-size: 9px;
}
.text-\[10px\] {
  font-size: 10px;
}
.text-\[11px\] {
  font-size: 11px;
}
.text-\[17px\] {
  font-size: 17px;
}
.text-\[clamp\(3rem\,8vw\,7\.2rem\)\] {
  font-size: clamp(3rem, 8vw, 7.2rem);
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
.leading-\[\.88\] {
  --tw-leading: .88;
  line-height: .88;
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
.font-medium {
  --tw-font-weight: var(--font-weight-medium);
  font-weight: var(--font-weight-medium);
}
.font-semibold {
  --tw-font-weight: var(--font-weight-semibold);
  font-weight: var(--font-weight-semibold);
}
.tracking-\[-\.03em\] {
  --tw-tracking: -.03em;
  letter-spacing: -.03em;
}
.tracking-\[-\.04em\] {
  --tw-tracking: -.04em;
  letter-spacing: -.04em;
}
.tracking-\[-\.025em\] {
  --tw-tracking: -.025em;
  letter-spacing: -.025em;
}
.tracking-\[-\.055em\] {
  --tw-tracking: -.055em;
  letter-spacing: -.055em;
}
.tracking-\[\.1em\] {
  --tw-tracking: .1em;
  letter-spacing: .1em;
}
.tracking-\[\.2em\] {
  --tw-tracking: .2em;
  letter-spacing: .2em;
}
.tracking-\[\.08em\] {
  --tw-tracking: .08em;
  letter-spacing: .08em;
}
.tracking-\[\.12em\] {
  --tw-tracking: .12em;
  letter-spacing: .12em;
}
.tracking-\[\.14em\] {
  --tw-tracking: .14em;
  letter-spacing: .14em;
}
.tracking-\[\.15em\] {
  --tw-tracking: .15em;
  letter-spacing: .15em;
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
.tracking-\[\.23em\] {
  --tw-tracking: .23em;
  letter-spacing: .23em;
}
.tracking-\[\.28em\] {
  --tw-tracking: .28em;
  letter-spacing: .28em;
}
.whitespace-nowrap {
  white-space: nowrap;
}
.text-acid {
  color: var(--color-acid);
}
.text-ember {
  color: var(--color-ember);
}
.text-pine-600 {
  color: var(--color-pine-600);
}
.text-pine-700\/48 {
  color: color-mix(in srgb, #1b5242 48%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-pine-700) 48%, transparent);
  }
}
.text-pine-700\/50 {
  color: color-mix(in srgb, #1b5242 50%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-pine-700) 50%, transparent);
  }
}
.text-pine-700\/55 {
  color: color-mix(in srgb, #1b5242 55%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-pine-700) 55%, transparent);
  }
}
.text-pine-700\/60 {
  color: color-mix(in srgb, #1b5242 60%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-pine-700) 60%, transparent);
  }
}
.text-pine-900 {
  color: var(--color-pine-900);
}
.text-pine-900\/25 {
  color: color-mix(in srgb, #09251d 25%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-pine-900) 25%, transparent);
  }
}
.text-pine-900\/45 {
  color: color-mix(in srgb, #09251d 45%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-pine-900) 45%, transparent);
  }
}
.text-pine-950 {
  color: var(--color-pine-950);
}
.text-white {
  color: var(--color-white);
}
.text-white\/35 {
  color: color-mix(in srgb, #fff 35%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-white) 35%, transparent);
  }
}
.text-white\/38 {
  color: color-mix(in srgb, #fff 38%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-white) 38%, transparent);
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
.text-white\/55 {
  color: color-mix(in srgb, #fff 55%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-white) 55%, transparent);
  }
}
.text-white\/66 {
  color: color-mix(in srgb, #fff 66%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-white) 66%, transparent);
  }
}
.text-white\/72 {
  color: color-mix(in srgb, #fff 72%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-white) 72%, transparent);
  }
}
.text-white\/78 {
  color: color-mix(in srgb, #fff 78%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-white) 78%, transparent);
  }
}
.text-white\/80 {
  color: color-mix(in srgb, #fff 80%, transparent);
  @supports (color: color-mix(in lab, red, red)) {
    color: color-mix(in oklab, var(--color-white) 80%, transparent);
  }
}
.uppercase {
  text-transform: uppercase;
}
.antialiased {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.opacity-55 {
  opacity: 55%;
}
.opacity-70 {
  opacity: 70%;
}
.shadow-acid {
  --tw-shadow: 0 18px 54px -24px var(--tw-shadow-color, rgba(223,252,98,.58));
  box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow);
}
.shadow-card {
  --tw-shadow: 0 18px 48px -28px var(--tw-shadow-color, rgba(6,26,21,.46));
  box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow);
}
.shadow-lift {
  --tw-shadow: 0 36px 100px -38px var(--tw-shadow-color, rgba(6,26,21,.58));
  box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow);
}
.backdrop-blur-sm {
  --tw-backdrop-blur: blur(var(--blur-sm));
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
.outline-none {
  --tw-outline-style: none;
  outline-style: none;
}
.placeholder\:text-white\/28 {
  &::placeholder {
    color: color-mix(in srgb, #fff 28%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      color: color-mix(in oklab, var(--color-white) 28%, transparent);
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
.hover\:border-ember\/40 {
  &:hover {
    @media (hover: hover) {
      border-color: color-mix(in srgb, #f27b4a 40%, transparent);
      @supports (color: color-mix(in lab, red, red)) {
        border-color: color-mix(in oklab, var(--color-ember) 40%, transparent);
      }
    }
  }
}
.hover\:border-pine-900\/30 {
  &:hover {
    @media (hover: hover) {
      border-color: color-mix(in srgb, #09251d 30%, transparent);
      @supports (color: color-mix(in lab, red, red)) {
        border-color: color-mix(in oklab, var(--color-pine-900) 30%, transparent);
      }
    }
  }
}
.hover\:border-white\/35 {
  &:hover {
    @media (hover: hover) {
      border-color: color-mix(in srgb, #fff 35%, transparent);
      @supports (color: color-mix(in lab, red, red)) {
        border-color: color-mix(in oklab, var(--color-white) 35%, transparent);
      }
    }
  }
}
.hover\:bg-ember\/10 {
  &:hover {
    @media (hover: hover) {
      background-color: color-mix(in srgb, #f27b4a 10%, transparent);
      @supports (color: color-mix(in lab, red, red)) {
        background-color: color-mix(in oklab, var(--color-ember) 10%, transparent);
      }
    }
  }
}
.hover\:bg-pine-800 {
  &:hover {
    @media (hover: hover) {
      background-color: var(--color-pine-800);
    }
  }
}
.hover\:bg-white {
  &:hover {
    @media (hover: hover) {
      background-color: var(--color-white);
    }
  }
}
.hover\:bg-white\/5 {
  &:hover {
    @media (hover: hover) {
      background-color: color-mix(in srgb, #fff 5%, transparent);
      @supports (color: color-mix(in lab, red, red)) {
        background-color: color-mix(in oklab, var(--color-white) 5%, transparent);
      }
    }
  }
}
.hover\:text-ember {
  &:hover {
    @media (hover: hover) {
      color: var(--color-ember);
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
.focus\:border-acid\/60 {
  &:focus {
    border-color: color-mix(in srgb, #dffc62 60%, transparent);
    @supports (color: color-mix(in lab, red, red)) {
      border-color: color-mix(in oklab, var(--color-acid) 60%, transparent);
    }
  }
}
.min-\[385px\]\:block {
  @media (width >= 385px) {
    display: block;
  }
}
.sm\:mx-0 {
  @media (width >= 40rem) {
    margin-inline: calc(var(--spacing) * 0);
  }
}
.sm\:mb-12 {
  @media (width >= 40rem) {
    margin-bottom: calc(var(--spacing) * 12);
  }
}
.sm\:block {
  @media (width >= 40rem) {
    display: block;
  }
}
.sm\:grid {
  @media (width >= 40rem) {
    display: grid;
  }
}
.sm\:inline-flex {
  @media (width >= 40rem) {
    display: inline-flex;
  }
}
.sm\:h-10 {
  @media (width >= 40rem) {
    height: calc(var(--spacing) * 10);
  }
}
.sm\:h-\[68px\] {
  @media (width >= 40rem) {
    height: 68px;
  }
}
.sm\:w-10 {
  @media (width >= 40rem) {
    width: calc(var(--spacing) * 10);
  }
}
.sm\:min-w-0 {
  @media (width >= 40rem) {
    min-width: calc(var(--spacing) * 0);
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
.sm\:justify-end {
  @media (width >= 40rem) {
    justify-content: flex-end;
  }
}
.sm\:gap-3 {
  @media (width >= 40rem) {
    gap: calc(var(--spacing) * 3);
  }
}
.sm\:gap-5 {
  @media (width >= 40rem) {
    gap: calc(var(--spacing) * 5);
  }
}
.sm\:overflow-visible {
  @media (width >= 40rem) {
    overflow: visible;
  }
}
.sm\:rounded-\[22px\] {
  @media (width >= 40rem) {
    border-radius: 22px;
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
.sm\:px-0 {
  @media (width >= 40rem) {
    padding-inline: calc(var(--spacing) * 0);
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
.sm\:py-16 {
  @media (width >= 40rem) {
    padding-block: calc(var(--spacing) * 16);
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
.sm\:text-base {
  @media (width >= 40rem) {
    font-size: var(--text-base);
    line-height: var(--tw-leading, var(--text-base--line-height));
  }
}
.sm\:text-lg {
  @media (width >= 40rem) {
    font-size: var(--text-lg);
    line-height: var(--tw-leading, var(--text-lg--line-height));
  }
}
.sm\:text-xl {
  @media (width >= 40rem) {
    font-size: var(--text-xl);
    line-height: var(--tw-leading, var(--text-xl--line-height));
  }
}
.sm\:text-xs {
  @media (width >= 40rem) {
    font-size: var(--text-xs);
    line-height: var(--tw-leading, var(--text-xs--line-height));
  }
}
.sm\:text-\[9px\] {
  @media (width >= 40rem) {
    font-size: 9px;
  }
}
.sm\:leading-8 {
  @media (width >= 40rem) {
    --tw-leading: calc(var(--spacing) * 8);
    line-height: calc(var(--spacing) * 8);
  }
}
.lg\:inset-y-0 {
  @media (width >= 64rem) {
    inset-block: calc(var(--spacing) * 0);
  }
}
.lg\:left-auto {
  @media (width >= 64rem) {
    left: auto;
  }
}
.lg\:block {
  @media (width >= 64rem) {
    display: block;
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
.lg\:min-h-\[260px\] {
  @media (width >= 64rem) {
    min-height: 260px;
  }
}
.lg\:w-\[520px\] {
  @media (width >= 64rem) {
    width: 520px;
  }
}
.lg\:grid-cols-\[190px_minmax\(0\,1fr\)_155px\] {
  @media (width >= 64rem) {
    grid-template-columns: 190px minmax(0,1fr) 155px;
  }
}
.lg\:grid-cols-\[minmax\(0\,1fr\)_340px\] {
  @media (width >= 64rem) {
    grid-template-columns: minmax(0,1fr) 340px;
  }
}
.lg\:grid-cols-\[minmax\(0\,1fr\)_360px\] {
  @media (width >= 64rem) {
    grid-template-columns: minmax(0,1fr) 360px;
  }
}
.lg\:flex-col {
  @media (width >= 64rem) {
    flex-direction: column;
  }
}
.lg\:items-stretch {
  @media (width >= 64rem) {
    align-items: stretch;
  }
}
.lg\:justify-center {
  @media (width >= 64rem) {
    justify-content: center;
  }
}
.lg\:gap-14 {
  @media (width >= 64rem) {
    gap: calc(var(--spacing) * 14);
  }
}
.lg\:rounded-none {
  @media (width >= 64rem) {
    border-radius: 0;
  }
}
.lg\:p-6 {
  @media (width >= 64rem) {
    padding: calc(var(--spacing) * 6);
  }
}
.lg\:p-7 {
  @media (width >= 64rem) {
    padding: calc(var(--spacing) * 7);
  }
}
.lg\:px-8 {
  @media (width >= 64rem) {
    padding-inline: calc(var(--spacing) * 8);
  }
}
.lg\:py-20 {
  @media (width >= 64rem) {
    padding-block: calc(var(--spacing) * 20);
  }
}
.lg\:pt-36 {
  @media (width >= 64rem) {
    padding-top: calc(var(--spacing) * 36);
  }
}
.lg\:pb-0 {
  @media (width >= 64rem) {
    padding-bottom: calc(var(--spacing) * 0);
  }
}
.lg\:pb-28 {
  @media (width >= 64rem) {
    padding-bottom: calc(var(--spacing) * 28);
  }
}
.xl\:gap-12 {
  @media (width >= 80rem) {
    gap: calc(var(--spacing) * 12);
  }
}
@property --tw-scroll-snap-strictness {
  syntax: "*";
  inherits: false;
  initial-value: proximity;
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
@layer properties {
  @supports ((-webkit-hyphens: none) and (not (margin-trim: inline))) or ((-moz-orient: inline) and (not (color:rgb(from red r g b)))) {
    *, ::before, ::after, ::backdrop {
      --tw-scroll-snap-strictness: proximity;
      --tw-space-y-reverse: 0;
      --tw-border-style: solid;
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
      --tw-translate-x: 0;
      --tw-translate-y: 0;
      --tw-translate-z: 0;
    }
  }
}

    
  </style>
  <style>
    :root{--pine:#09251d;--pine-dark:#061a15;--pine-mid:#123b30;--acid:#dffc62;--ember:#f27b4a;--cream:#fffdf6;--oat:#f0ecdf;--ink:#16231e;--sky:#b8dce0}
    *{box-sizing:border-box}html{background:var(--oat)}body{margin:0;overflow-x:hidden;background:var(--oat);color:var(--ink);font-family:'DM Sans',sans-serif;text-rendering:optimizeLegibility}button,a,input{font:inherit;-webkit-tap-highlight-color:transparent}button{cursor:pointer}::selection{background:var(--acid);color:var(--pine-dark)}
    .font-display{font-family:'Fraunces',serif}.safe-top{padding-top:max(12px,env(safe-area-inset-top))}.safe-bottom{padding-bottom:max(16px,env(safe-area-inset-bottom))}
    .nav-shell{background:rgba(6,26,21,.84);border:1px solid rgba(255,255,255,.12);box-shadow:0 24px 70px -42px rgba(0,0,0,.9);backdrop-filter:blur(22px) saturate(130%);-webkit-backdrop-filter:blur(22px) saturate(130%)}
    .grain::after{content:'';position:absolute;inset:0;z-index:2;pointer-events:none;opacity:.08;mix-blend-mode:soft-light;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.58'/%3E%3C/svg%3E")}
    .topo-dark{background-image:radial-gradient(circle at 18% 22%,rgba(223,252,98,.15),transparent 20%),radial-gradient(circle at 84% 18%,rgba(242,123,74,.12),transparent 18%),url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='.055' stroke-width='1.1'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3C/g%3E%3C/svg%3E");background-size:auto,auto,820px 820px}
    .paper-grid{background-image:linear-gradient(rgba(9,37,29,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(9,37,29,.045) 1px,transparent 1px);background-size:30px 30px}
    .page-wrap{width:min(1180px,calc(100% - 32px));margin-inline:auto}.hero{position:relative;overflow:hidden;background:var(--pine-dark);color:white;padding:124px 0 72px}.hero-grid{display:grid;grid-template-columns:minmax(0,1fr) 390px;gap:56px;align-items:center}.eyebrow{font-size:11px;font-weight:700;letter-spacing:.26em;text-transform:uppercase;color:var(--acid)}.hero h1{font-family:'Fraunces',serif;font-size:clamp(3.3rem,7vw,6.6rem);line-height:.88;letter-spacing:-.055em;margin:16px 0 0}.hero h1 span{color:rgba(255,255,255,.42)}.hero-copy{max-width:690px;margin-top:24px;color:rgba(255,255,255,.68);font-size:18px;line-height:1.75}
    .progress{display:flex;align-items:center;justify-content:flex-end;gap:24px;margin-bottom:38px;overflow-x:auto;padding-bottom:2px}.progress-item{position:relative;display:flex;align-items:center;gap:8px;flex:none;font-size:11px;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:rgba(255,255,255,.65)}.progress-item::after{content:'';position:absolute;left:calc(100% + 10px);width:22px;height:1px;background:rgba(255,255,255,.18)}.progress-item:last-child::after{display:none}.progress-dot{display:grid;width:26px;height:26px;place-items:center;border-radius:999px;background:var(--acid);color:var(--pine-dark);font-size:12px}
    .success-pass{position:relative;min-height:410px;border-radius:34px;padding:28px;overflow:hidden;background:linear-gradient(145deg,#164838 0%,#061a15 72%);border:1px solid rgba(255,255,255,.13);box-shadow:0 44px 105px -58px rgba(0,0,0,.9)}.success-pass::before,.success-pass::after{content:'';position:absolute;border-radius:999px;border:1px solid rgba(223,252,98,.16)}.success-pass::before{width:300px;height:300px;right:-130px;top:-145px}.success-pass::after{width:190px;height:190px;left:-80px;bottom:-110px;border-color:rgba(242,123,74,.17)}.success-icon{position:relative;z-index:1;display:grid;width:86px;height:86px;place-items:center;border-radius:999px;background:var(--acid);color:var(--pine-dark);box-shadow:0 0 0 14px rgba(223,252,98,.09)}.success-icon svg{width:42px;height:42px}.success-icon path{stroke-dasharray:46;stroke-dashoffset:46;animation:drawCheck .75s .25s cubic-bezier(.2,.8,.2,1) forwards}@keyframes drawCheck{to{stroke-dashoffset:0}}.pass-meta{position:relative;z-index:1;margin-top:70px}.pass-code{display:inline-flex;align-items:center;gap:9px;border:1px solid rgba(255,255,255,.13);border-radius:999px;padding:8px 12px;color:rgba(255,255,255,.7);font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}.pass-title{font-family:'Fraunces',serif;font-size:31px;line-height:1.02;margin-top:18px}.pass-details{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:22px}.pass-detail{border-top:1px solid rgba(255,255,255,.14);padding-top:12px}.pass-detail small{display:block;color:rgba(255,255,255,.42);font-size:9px;font-weight:700;letter-spacing:.16em;text-transform:uppercase}.pass-detail strong{display:block;margin-top:5px;font-size:13px}.pass-footer{position:absolute;right:28px;bottom:24px;z-index:1;color:var(--acid);font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase}
    .route{position:absolute;inset:auto -90px 10px auto;width:620px;height:340px;opacity:.62;pointer-events:none}.route-path{fill:none;stroke:rgba(223,252,98,.62);stroke-width:2;stroke-linecap:round;stroke-dasharray:8 13;animation:routeMove 19s linear infinite}@keyframes routeMove{to{stroke-dashoffset:-420}}
    .action-row{display:flex;flex-wrap:wrap;gap:12px;margin-top:34px}.btn-primary,.btn-secondary,.btn-dark{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:50px;border-radius:999px;padding:0 20px;font-weight:700;text-decoration:none;transition:transform .2s ease,box-shadow .2s ease,background-color .2s ease}.btn-primary{background:var(--acid);color:var(--pine-dark);border:0}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 16px 35px -22px rgba(223,252,98,.8)}.btn-secondary{border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.06);color:white}.btn-secondary:hover{background:rgba(255,255,255,.11)}.btn-dark{background:var(--pine-dark);color:white;border:0}.btn-dark:hover{transform:translateY(-2px)}
    .main{padding:76px 0 96px}.section-head{display:flex;align-items:end;justify-content:space-between;gap:24px;margin-bottom:28px}.section-head h2{font-family:'Fraunces',serif;font-size:clamp(2.25rem,4vw,4.3rem);line-height:.95;letter-spacing:-.045em;margin:0}.section-head p{max-width:470px;margin:0;color:rgba(22,35,30,.62);line-height:1.7}.order-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:28px}.panel{border:1px solid rgba(9,37,29,.1);border-radius:30px;background:var(--cream);box-shadow:0 30px 80px -58px rgba(6,26,21,.7)}.panel-pad{padding:28px}.order-card{overflow:hidden}.order-banner{position:relative;min-height:210px;padding:26px;color:white;background:linear-gradient(105deg,rgba(6,26,21,.95),rgba(6,26,21,.42)),url('https://images.unsplash.com/photo-1486911278844-a81c5267e227?auto=format&fit=crop&w=1400&q=85') center/cover}.order-banner h3{font-family:'Fraunces',serif;font-size:30px;line-height:1.05;margin:60px 0 8px}.order-banner p{margin:0;color:rgba(255,255,255,.66)}.order-status{position:absolute;top:22px;left:22px;display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:8px 11px;background:rgba(223,252,98,.94);color:var(--pine-dark);font-size:10px;font-weight:800;letter-spacing:.13em;text-transform:uppercase}.summary-list{display:grid;gap:0}.summary-item{display:grid;grid-template-columns:42px minmax(0,1fr) auto;gap:14px;align-items:center;padding:18px 0;border-bottom:1px solid rgba(9,37,29,.09)}.summary-item:last-child{border-bottom:0}.summary-icon{display:grid;width:42px;height:42px;place-items:center;border-radius:14px;background:rgba(9,37,29,.06);color:var(--pine)}.summary-icon svg{width:20px;height:20px}.summary-item small{display:block;color:rgba(22,35,30,.5);font-size:9px;font-weight:700;letter-spacing:.15em;text-transform:uppercase}.summary-item strong{display:block;margin-top:4px;font-size:14px}.summary-value{text-align:right;font-size:13px;font-weight:700;color:var(--pine)}
    .receipt{position:sticky;top:96px;background:var(--pine-dark);color:white}.receipt .panel-pad{padding:26px}.receipt h3{font-family:'Fraunces',serif;font-size:25px;margin:0 0 20px}.receipt-line{display:flex;justify-content:space-between;gap:18px;padding:8px 0;color:rgba(255,255,255,.64);font-size:13px}.receipt-line strong{color:white}.receipt-total{display:flex;align-items:end;justify-content:space-between;gap:16px;border-top:1px solid rgba(255,255,255,.14);margin-top:12px;padding-top:18px}.receipt-total span{font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.5)}.receipt-total strong{font-family:'Fraunces',serif;font-size:34px;color:var(--acid)}.mini-note{margin-top:20px;border-radius:18px;background:rgba(255,255,255,.06);padding:14px;color:rgba(255,255,255,.64);font-size:12px;line-height:1.55}
    .tickets-section{margin-top:86px}.ticket-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.ticket{position:relative;overflow:hidden;min-height:420px;border-radius:28px;background:linear-gradient(150deg,#fffdf6 0%,#f4efdf 100%);border:1px solid rgba(9,37,29,.11);box-shadow:0 28px 70px -52px rgba(6,26,21,.72)}.ticket::before,.ticket::after{content:'';position:absolute;top:150px;width:28px;height:28px;border-radius:999px;background:var(--oat);border:1px solid rgba(9,37,29,.08)}.ticket::before{left:-15px}.ticket::after{right:-15px}.ticket-top{padding:22px 22px 20px;border-bottom:1px dashed rgba(9,37,29,.18)}.ticket-topline{display:flex;align-items:center;justify-content:space-between;gap:12px}.ticket-number{font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:rgba(22,35,30,.48)}.ticket-badge{border-radius:999px;background:var(--acid);padding:6px 9px;font-size:9px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--pine-dark)}.ticket h3{font-family:'Fraunces',serif;font-size:24px;line-height:1;margin:22px 0 5px}.ticket p{margin:0;color:rgba(22,35,30,.56);font-size:12px}.ticket-body{display:grid;place-items:center;padding:28px 22px}.qr{display:grid;width:142px;height:142px;place-items:center;padding:10px;border-radius:18px;background:white;border:1px solid rgba(9,37,29,.1)}.qr svg{width:100%;height:100%}.ticket-code{margin-top:18px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px;letter-spacing:.12em;color:rgba(22,35,30,.54)}.ticket-actions{display:flex;justify-content:center;gap:8px;margin-top:18px}.icon-btn{display:grid;width:42px;height:42px;place-items:center;border-radius:999px;border:1px solid rgba(9,37,29,.13);background:rgba(255,255,255,.65);color:var(--pine)}.icon-btn:hover{background:white}.icon-btn svg{width:18px;height:18px}
    .next-section{margin-top:86px}.next-grid{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(0,.92fr);gap:24px}.timeline{padding:28px}.timeline-step{display:grid;grid-template-columns:44px minmax(0,1fr);gap:16px;position:relative;padding-bottom:26px}.timeline-step:last-child{padding-bottom:0}.timeline-step:not(:last-child)::after{content:'';position:absolute;left:21px;top:45px;bottom:5px;width:1px;background:rgba(9,37,29,.14)}.timeline-icon{position:relative;z-index:1;display:grid;width:44px;height:44px;place-items:center;border-radius:999px;background:var(--pine);color:var(--acid)}.timeline-step small{display:block;color:rgba(22,35,30,.48);font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase}.timeline-step h4{font-family:'Fraunces',serif;font-size:21px;margin:5px 0 5px}.timeline-step p{margin:0;color:rgba(22,35,30,.6);font-size:13px;line-height:1.6}.prep{padding:28px;background:linear-gradient(150deg,#dffc62 0%,#cfe94f 100%)}.prep h3{font-family:'Fraunces',serif;font-size:30px;line-height:1;margin:0}.prep-list{display:grid;gap:12px;margin-top:24px}.prep-item{display:flex;align-items:flex-start;gap:12px;border-top:1px solid rgba(6,26,21,.13);padding-top:12px}.prep-check{display:grid;width:22px;height:22px;place-items:center;border-radius:999px;background:var(--pine-dark);color:var(--acid);flex:none;font-size:12px}.prep-item strong{display:block;font-size:13px}.prep-item span{display:block;margin-top:3px;font-size:12px;color:rgba(6,26,21,.65);line-height:1.45}
    .extras-section{margin-top:86px}.extras-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.extra-card{position:relative;min-height:310px;border-radius:28px;overflow:hidden;color:white;background:#163b31}.extra-card img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .6s cubic-bezier(.2,.7,.2,1)}.extra-card::after{content:'';position:absolute;inset:0;background:linear-gradient(to top,rgba(6,26,21,.96) 0%,rgba(6,26,21,.25) 72%)}.extra-card:hover img{transform:scale(1.035)}.extra-content{position:absolute;z-index:2;left:22px;right:22px;bottom:22px}.extra-content small{font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:var(--acid)}.extra-content h3{font-family:'Fraunces',serif;font-size:25px;margin:7px 0}.extra-content p{margin:0;color:rgba(255,255,255,.65);font-size:12px;line-height:1.5}.extra-link{display:inline-flex;margin-top:15px;color:white;font-size:12px;font-weight:700;text-decoration:none}
    .footer{background:var(--pine-dark);color:white;padding:42px 0 34px;margin-top:88px}.footer-row{display:flex;align-items:center;justify-content:space-between;gap:24px}.footer-brand{display:flex;align-items:center;gap:12px}.footer-brandmark{display:grid;width:40px;height:40px;place-items:center;border-radius:999px;background:var(--acid);color:var(--pine-dark)}.footer p{margin:0;color:rgba(255,255,255,.48);font-size:12px}.footer-links{display:flex;flex-wrap:wrap;gap:16px}.footer a{color:rgba(255,255,255,.66);font-size:12px;text-decoration:none}.footer a:hover{color:white}
    .mobile-actions{display:none}.toast{position:fixed;z-index:90;left:50%;bottom:24px;transform:translate(-50%,18px);opacity:0;pointer-events:none;max-width:calc(100% - 32px);border-radius:999px;background:var(--pine-dark);color:white;padding:12px 17px;box-shadow:0 20px 60px -25px rgba(0,0,0,.8);font-size:12px;font-weight:700;transition:.25s ease}.toast.show{opacity:1;transform:translate(-50%,0)}
    .modal{position:fixed;inset:0;z-index:80;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(6,26,21,.65);backdrop-filter:blur(12px)}.modal.open{display:flex}.modal-card{width:min(520px,100%);border-radius:30px;background:var(--cream);padding:26px;box-shadow:0 42px 120px -55px rgba(0,0,0,.9)}.modal-head{display:flex;align-items:center;justify-content:space-between;gap:16px}.modal h3{font-family:'Fraunces',serif;font-size:29px;margin:0}.close-btn{display:grid;width:40px;height:40px;place-items:center;border-radius:999px;border:1px solid rgba(9,37,29,.12);background:white}.share-fields{display:grid;gap:12px;margin-top:22px}.share-fields label{font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:rgba(22,35,30,.55)}.share-fields input{width:100%;min-height:50px;border-radius:16px;border:1px solid rgba(9,37,29,.14);background:white;padding:0 14px;outline:none}.share-fields input:focus{border-color:var(--pine);box-shadow:0 0 0 4px rgba(223,252,98,.28)}
    [data-reveal]{opacity:0;transform:translateY(24px)}
    @media(max-width:1023px){.hero-grid{grid-template-columns:1fr;gap:40px}.success-pass{max-width:580px}.order-grid{grid-template-columns:1fr}.receipt{position:relative;top:auto}.ticket-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.next-grid{grid-template-columns:1fr}.extras-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:639px){body{padding-bottom:88px}.page-wrap{width:min(100% - 24px,1180px)}.hero{padding:104px 0 48px}.progress{justify-content:flex-start;gap:17px;margin-bottom:30px}.progress-item{font-size:9px}.progress-item::after{left:calc(100% + 6px);width:10px}.progress-dot{width:23px;height:23px;font-size:10px}.hero h1{font-size:clamp(3.15rem,16vw,4.6rem)}.hero-copy{font-size:15px;line-height:1.65;margin-top:20px}.hero-grid{gap:30px}.success-pass{min-height:350px;padding:22px;border-radius:28px}.success-icon{width:72px;height:72px}.success-icon svg{width:35px;height:35px}.pass-meta{margin-top:54px}.pass-title{font-size:27px}.pass-details{gap:10px}.pass-footer{right:22px;bottom:20px}.action-row{display:grid;grid-template-columns:1fr;margin-top:24px}.action-row .btn-primary,.action-row .btn-secondary{width:100%}.route{width:500px;height:270px;right:-210px;bottom:10px}.main{padding:52px 0 72px}.section-head{display:block;margin-bottom:22px}.section-head h2{font-size:2.55rem}.section-head p{margin-top:13px;font-size:13px}.panel,.order-card{border-radius:24px}.panel-pad{padding:21px}.order-banner{min-height:195px;padding:21px}.order-banner h3{font-size:26px;margin-top:62px}.summary-item{grid-template-columns:38px minmax(0,1fr);gap:12px}.summary-value{grid-column:2;text-align:left;margin-top:-7px;font-size:12px}.receipt{display:none}.tickets-section,.next-section,.extras-section{margin-top:62px}.ticket-grid,.extras-grid{grid-template-columns:1fr}.ticket{min-height:398px}.ticket::before,.ticket::after{top:145px}.next-grid{gap:16px}.timeline,.prep{padding:21px}.timeline-step{grid-template-columns:40px minmax(0,1fr);gap:13px}.timeline-icon{width:40px;height:40px}.timeline-step:not(:last-child)::after{left:19px;top:41px}.prep h3{font-size:27px}.extra-card{min-height:285px}.footer{margin-top:66px;padding-bottom:110px}.footer-row{display:block}.footer-links{margin-top:20px}.mobile-actions{position:fixed;z-index:60;left:0;right:0;bottom:0;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;border-top:1px solid rgba(9,37,29,.1);background:rgba(255,253,246,.94);padding:10px 12px max(10px,env(safe-area-inset-bottom));box-shadow:0 -18px 55px -35px rgba(6,26,21,.9);backdrop-filter:blur(18px)}.mobile-actions .btn-primary,.mobile-actions .btn-dark{min-height:48px;padding:0 16px;font-size:12px}.toast{bottom:84px}.modal{align-items:flex-end;padding:10px}.modal-card{border-radius:28px 28px 20px 20px;padding:22px}.modal h3{font-size:26px}}
    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}[data-reveal]{opacity:1;transform:none}}
  </style>
</head>
<body>
<?php $nvNav=''; $nvNoSpacer=true; include __DIR__ . '/includes/header.php'; ?>

<main>
<section class="hero topo-dark grain">
  <svg class="route" viewBox="0 0 620 340" fill="none" aria-hidden="true"><path class="route-path" d="M22 292C110 250 126 109 235 113c94 4 117 101 212 78 73-17 88-98 154-154"/><circle cx="235" cy="113" r="5" fill="#dffc62"/><circle cx="447" cy="191" r="5" fill="#f27b4a"/></svg>
  <div class="page-wrap">
    <div class="progress" aria-label="Progres comandă">
      <div class="progress-item"><span class="progress-dot">✓</span><span>Coș</span></div>
      <div class="progress-item"><span class="progress-dot">✓</span><span>Date</span></div>
      <div class="progress-item"><span class="progress-dot">✓</span><span>Plată</span></div>
      <div class="progress-item" style="color:var(--acid)"><span class="progress-dot">✓</span><span>Gata</span></div>
    </div>
    <div class="hero-grid">
      <div data-reveal>
        <p class="eyebrow">Plată confirmată · 27 iulie 2026, 12:47</p>
        <h1>Traseul tău<br><span>este confirmat.</span></h1>
        <p class="hero-copy">Biletele au fost emise și trimise la <strong style="color:white"><?php echo e($ord_email); ?></strong>. Le găsești și mai jos, gata pentru telefon sau pentru membrii grupului.</p>
        <div class="action-row">
          <button type="button" class="btn-primary" data-download><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>Descarcă toate biletele</button>
          <button type="button" class="btn-secondary" data-wallet><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7h13a2 2 0 0 1 2 2v9H5a2 2 0 0 1-2-2V7h2Zm0 0V5a2 2 0 0 1 2-2h9v4M15 12h5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>Adaugă în Wallet</button>
        </div>
      </div>
      <article class="success-pass" data-reveal>
        <div class="success-icon"><svg viewBox="0 0 48 48" fill="none"><path d="m13 25 8 8 15-18" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <div class="pass-meta">
          <button type="button" class="pass-code" data-copy-code><span>Comanda</span><strong><?php echo e($ord_code); ?></strong><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M9 9h10v10H9V9Zm-4 6H4V5h10v1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <div class="pass-title"><?php echo e($ord_title); ?></div>
          <div class="pass-details"><div class="pass-detail"><small>Data</small><strong><?php echo e($ord_dateFull); ?></strong></div><div class="pass-detail"><small>Check-in</small><strong><?php echo e($ord_time); ?></strong></div><div class="pass-detail"><small>Acces</small><strong><?php echo e($ord_venue); ?></strong></div><div class="pass-detail"><small>Total</small><strong><?php echo e($ord_total); ?></strong></div></div>
        </div>
        <div class="pass-footer">Ticketing by Tixello</div>
      </article>
    </div>
  </div>
</section>

<section class="main paper-grid">
  <div class="page-wrap">
    <div class="section-head" data-reveal><div><p class="eyebrow" style="color:var(--pine)">Rezumatul expediției</p><h2>Totul într-un singur loc.</h2></div><p>Verifică detaliile, descarcă biletele și pregătește ziua fără să cauți prin emailuri.</p></div>
    <div class="order-grid">
      <article class="panel order-card" data-reveal>
        <div class="order-banner"><span class="order-status"><span style="width:7px;height:7px;border-radius:999px;background:var(--pine-dark)"></span><?php echo $ord_paid ? 'Confirmat' : ($hasOrder ? 'În procesare' : 'Confirmat'); ?></span><h3><?php echo e($ord_title); ?></h3><p><?php echo $hasOrder ? e($ord_custname . ' · ' . $ticketCount . ' ' . ($ticketCount === 1 ? 'bilet' : 'bilete')) : 'Acces parc + Canopy Run · 2 adulți și 1 copil'; ?></p></div>
        <div class="panel-pad summary-list">
          <div class="summary-item"><span class="summary-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span><div><small>Vizită</small><strong><?php echo e($ord_dateFull); ?></strong></div><div class="summary-value"><?php echo e($ord_time); ?></div></div>
          <div class="summary-item"><span class="summary-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 7a7 7 0 0 0-14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span><div><small>Participanți</small><strong><?php echo e($ord_custname); ?></strong></div><div class="summary-value"><?php echo e($ticketCount . ' ' . ($ticketCount === 1 ? 'bilet' : 'bilete')); ?></div></div>
          <div class="summary-item"><span class="summary-icon"><svg viewBox="0 0 24 24" fill="none"><path d="m5 17 7-13 7 13-7-3-7 3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 9v8" stroke="currentColor" stroke-width="1.7"/></svg></span><div><small>Experiență</small><strong>Canopy Run · interval 11:20</strong></div><div class="summary-value">3 locuri</div></div>
          <div class="summary-item"><span class="summary-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M5 5h14v14H5V5Zm3 4h8M8 13h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span><div><small>Suplimente</small><strong>Parcare rezervată + fotografii traseu</strong></div><div class="summary-value">Incluse</div></div>
        </div>
      </article>
      <aside class="panel receipt" data-reveal>
        <div class="panel-pad"><h3>Detalii plată</h3><?php if ($hasOrder): ?><div class="receipt-line"><span>Bilete și experiențe</span><strong><?php echo e($ord_total); ?></strong></div><?php else: ?><div class="receipt-line"><span>Bilete și experiențe</span><strong>735 RON</strong></div><div class="receipt-line"><span>Parcare rezervată</span><strong>25 RON</strong></div><div class="receipt-line"><span>Fotografii traseu</span><strong>49 RON</strong></div><div class="receipt-line"><span>Discount TRAIL10</span><strong style="color:var(--acid)">−23 RON</strong></div><?php endif; ?><div class="receipt-total"><span>Total achitat</span><strong><?php echo e($ord_total); ?></strong></div><div class="mini-note">Plată procesată securizat cu <?php echo e($ord_payment); ?>. Factura și confirmarea au fost trimise pe email.</div><a href="/cont/comenzi" class="btn-primary" style="width:100%;margin-top:18px">Vezi comanda în cont</a></div>
      </aside>
    </div>

    <section class="tickets-section">
      <div class="section-head" data-reveal><div><p class="eyebrow" style="color:var(--pine)">Biletele tale</p><h2>Trei permise. Un singur traseu.</h2></div><button type="button" class="btn-dark" data-share>Trimite biletele grupului</button></div>
      <div class="ticket-grid" id="ticketGrid"></div>
    </section>

    <section class="next-section">
      <div class="section-head" data-reveal><div><p class="eyebrow" style="color:var(--pine)">Ce urmează</p><h2>De aici, totul devine simplu.</h2></div><p>Primești exact informațiile utile, la momentul potrivit — fără notificări inutile.</p></div>
      <div class="next-grid">
        <article class="panel timeline" data-reveal>
          <div class="timeline-step"><span class="timeline-icon">1</span><div><small>Acum</small><h4>Confirmarea este în email</h4><p>Biletele, factura și linkul de administrare au fost trimise la adresa folosită în checkout.</p></div></div>
          <div class="timeline-step"><span class="timeline-icon">2</span><div><small>Cu 48 de ore înainte</small><h4>Primești briefingul vizitei</h4><p>Vreme, echipament recomandat, acces și eventuale ajustări operaționale.</p></div></div>
          <div class="timeline-step"><span class="timeline-icon">3</span><div><small>În ziua vizitei</small><h4>Arată codul QR la Poarta Nord</h4><p>Nu este nevoie să tipărești nimic. Luminozitatea ecranului va fi mărită automat de aplicația Wallet.</p></div></div>
        </article>
        <article class="panel prep" data-reveal>
          <p class="eyebrow" style="color:var(--pine-dark)">Checklist rapid</p><h3>Pregătește expediția.</h3>
          <div class="prep-list"><div class="prep-item"><span class="prep-check">✓</span><div><strong>Încălțăminte închisă</strong><span>Obligatorie pentru Canopy Run și traseele suspendate.</span></div></div><div class="prep-item"><span class="prep-check">✓</span><div><strong>Sosire cu 30 minute înainte</strong><span>Briefingul începe la 10:50, înaintea intervalului de 11:20.</span></div></div><div class="prep-item"><span class="prep-check">✓</span><div><strong>Act de identitate</strong><span>Necesar doar pentru titularul comenzii sau pentru abonamente nominale.</span></div></div><div class="prep-item"><span class="prep-check">✓</span><div><strong>Vreme schimbătoare</strong><span>O geacă ușoară este utilă chiar și în zilele calde din pădure.</span></div></div></div>
          <a href="/planifica" class="btn-dark" style="width:100%;margin-top:24px">Planifică vizita</a>
        </article>
      </div>
    </section>

    <section class="extras-section">
      <div class="section-head" data-reveal><div><p class="eyebrow" style="color:var(--pine)">Mai ai timp să adaugi</p><h2>Completează ziua.</h2></div><p>Aceste opțiuni pot fi adăugate ulterior din cont, fără să modifici biletele deja emise.</p></div>
      <div class="extras-grid">
        <a class="extra-card" href="/experienta" data-reveal><img src="https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=900&q=80" alt="Traseu forestier"><div class="extra-content"><small>După-amiază</small><h3>Forest Descent</h3><p>O coborâre ghidată prin sectorul vechi al pădurii.</p><span class="extra-link">Vezi experiența →</span></div></a>
        <a class="extra-card" href="/bilete" data-reveal><img src="https://images.unsplash.com/photo-1500534314209-a25ddb2bd4297?auto=format&fit=crop&w=900&q=80" alt="Masă în aer liber"><div class="extra-content"><small>Pauza de prânz</small><h3>Campfire Table</h3><p>Meniu cald rezervat la masa comună din Basecamp.</p><span class="extra-link">Adaugă în cont →</span></div></a>
        <a class="extra-card" href="/cont" data-reveal><img src="https://images.unsplash.com/photo-1497250681960-ef046c08a56e?auto=format&fit=crop&w=900&q=80" alt="Pădure verde"><div class="extra-content"><small>Cont Nordvale</small><h3>Administrează vizita</h3><p>Schimbă beneficiarii, distribuie biletele și descarcă factura.</p><span class="extra-link">Deschide contul →</span></div></a>
      </div>
    </section>
  </div>
</section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<div class="mobile-actions safe-bottom"><button type="button" class="btn-primary" data-download>Descarcă biletele</button><a href="/cont" class="btn-dark" aria-label="Cont"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 7a7 7 0 0 0-14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></a></div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<div class="modal" id="shareModal" aria-hidden="true"><div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="shareTitle"><div class="modal-head"><div><p class="eyebrow" style="color:var(--pine)">Distribuie biletele</p><h3 id="shareTitle">Trimite-le grupului.</h3></div><button type="button" class="close-btn" data-close-modal aria-label="Închide"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button></div><p style="color:rgba(22,35,30,.62);line-height:1.6;margin:12px 0 0">Introdu adresele de email, separate prin virgulă. Fiecare participant va primi propriul cod QR.</p><div class="share-fields"><label for="shareEmails">Adrese email</label><input id="shareEmails" type="text" placeholder="maria@email.ro, radu@email.ro"><button type="button" class="btn-dark" id="sendTickets">Trimite biletele</button></div></div></div>

<?php if ($hasOrder): ?><script>try{localStorage.removeItem('nordvale_cart')}catch(e){}</script><?php endif; ?>
<script>
(function(){
  'use strict';
  const orderCode = <?php echo json_encode($ord_codeRaw, JSON_UNESCAPED_UNICODE); ?>;
  const tickets=<?php echo (!empty($ticketsJs)) ? json_encode($ticketsJs, JSON_UNESCAPED_UNICODE) : "[
    {no:'01 / 03',name:'Andrei Popescu',type:'Adult · Adventure Pass',code:'NV-A8F2-19Q7',seed:2},
    {no:'02 / 03',name:'Maria Popescu',type:'Adult · Adventure Pass',code:'NV-K4M8-72P1',seed:5},
    {no:'03 / 03',name:'Luca Popescu',type:'Copil · Adventure Pass',code:'NV-C9R3-44T6',seed:8}
  ]"; ?>;
  const grid=document.getElementById('ticketGrid');
  const toast=document.getElementById('toast');
  const modal=document.getElementById('shareModal');
  let toastTimer;
  function showToast(message){clearTimeout(toastTimer);toast.textContent=message;toast.classList.add('show');toastTimer=setTimeout(()=>toast.classList.remove('show'),2600)}
  function qr(seed){let cells='';for(let y=0;y<21;y++){for(let x=0;x<21;x++){const finder=(x<7&&y<7)||(x>13&&y<7)||(x<7&&y>13);let on=false;if(finder){const fx=x<7?x:x-14,fy=y<7?y:y-14;on=fx===0||fy===0||fx===6||fy===6||(fx>=2&&fx<=4&&fy>=2&&fy<=4)}else{on=((x*7+y*11+seed*13+x*y)%9)<4}if(on)cells+=`<rect x="${x}" y="${y}" width="1" height="1"/>`}}return `<svg viewBox="0 0 21 21" aria-hidden="true"><rect width="21" height="21" fill="white"/><g fill="#061a15">${cells}</g></svg>`}
  function ticketTemplate(t){return `<article class="ticket" data-reveal><div class="ticket-top"><div class="ticket-topline"><span class="ticket-number">Bilet ${t.no}</span><span class="ticket-badge">Activ</span></div><h3>${t.name}</h3><p>${t.type}</p></div><div class="ticket-body"><div class="qr">${qr(t.seed)}</div><div class="ticket-code">${t.code}</div><div class="ticket-actions"><button type="button" class="icon-btn" data-ticket-download="${t.code}" aria-label="Descarcă biletul"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button><button type="button" class="icon-btn" data-ticket-wallet="${t.code}" aria-label="Adaugă în Wallet"><svg viewBox="0 0 24 24" fill="none"><path d="M5 7h13a2 2 0 0 1 2 2v9H5a2 2 0 0 1-2-2V7h2Zm0 0V5a2 2 0 0 1 2-2h9v4M15 12h5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg></button></div></div></article>`}
  grid.innerHTML=tickets.map(ticketTemplate).join('');
  function downloadSummary(){const text=['NORDVALE — COMANDĂ CONFIRMATĂ','#'+orderCode,'<?php echo e($ord_dateFull . " · " . $ord_time); ?>','<?php echo e($ord_venue); ?>','',...tickets.map(t=>`${t.name} — ${t.type} — ${t.code}`)].join('\n');const blob=new Blob([text],{type:'text/plain;charset=utf-8'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='nordvale-bilete-'+orderCode+'.txt';document.body.appendChild(a);a.click();a.remove();URL.revokeObjectURL(url);showToast('Biletele au fost pregătite pentru descărcare.')}
  document.querySelectorAll('[data-download]').forEach(b=>b.addEventListener('click',downloadSummary));
  document.querySelectorAll('[data-wallet]').forEach(b=>b.addEventListener('click',()=>showToast('Permisele au fost pregătite pentru Wallet.')));
  document.addEventListener('click',e=>{const d=e.target.closest('[data-ticket-download]');if(d)showToast(`Biletul ${d.dataset.ticketDownload} a fost pregătit.`);const w=e.target.closest('[data-ticket-wallet]');if(w)showToast(`Biletul ${w.dataset.ticketWallet} a fost adăugat în Wallet.`)});
  const copy=document.querySelector('[data-copy-code]');if(copy)copy.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(orderCode);showToast('Codul comenzii a fost copiat.')}catch(_){showToast('Cod comandă: '+orderCode)}});
  function openModal(){modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';setTimeout(()=>document.getElementById('shareEmails').focus(),80)}
  function closeModal(){modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.style.overflow=''}
  document.querySelectorAll('[data-share]').forEach(b=>b.addEventListener('click',openModal));document.querySelector('[data-close-modal]').addEventListener('click',closeModal);modal.addEventListener('click',e=>{if(e.target===modal)closeModal()});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal.classList.contains('open'))closeModal()});
  document.getElementById('sendTickets').addEventListener('click',()=>{const value=document.getElementById('shareEmails').value.trim();if(!value){showToast('Adaugă cel puțin o adresă de email.');return}closeModal();showToast('Biletele au fost trimise grupului.')});
  const reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;const els=[...document.querySelectorAll('[data-reveal]')];if(reduce){els.forEach(el=>{el.style.opacity='1';el.style.transform='none'})}else{const io=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.animate([{opacity:0,transform:'translateY(24px)'},{opacity:1,transform:'translateY(0)'}],{duration:720,easing:'cubic-bezier(.2,.75,.2,1)',fill:'forwards'});io.unobserve(entry.target)}}),{threshold:.12});els.forEach(el=>io.observe(el))}
})();
</script>
</body>
</html>