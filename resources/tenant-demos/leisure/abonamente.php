<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/api.php';

// Planuri de abonament reale ale tenantului (overlay peste cele demo).
// Mapam pe forma folosita in Alpine; pastram etichetele de design (level/short).
$subs = tc_subscriptions();
$injectedPlans = [];
foreach (array_slice(is_array($subs) ? $subs : [], 0, 3) as $s) {
    if (!is_array($s)) { continue; }
    $row = [];
    if (!empty($s['name']))  { $row['name']  = $s['name']; }
    if (isset($s['price']))  { $row['price'] = (float) $s['price']; }
    if (!empty($s['slug']))  { $row['slug']  = $s['slug']; }
    $copy = $s['description'] ?? ($s['subtitle'] ?? '');
    if (!empty($copy))       { $row['drawerCopy'] = $copy; }
    if (!empty($s['benefits']) && is_array($s['benefits'])) {
        $row['mobileFeatures'] = array_values($s['benefits']);
    }
    if (!empty($row)) { $injectedPlans[] = $row; }
}
?>
<!DOCTYPE html>
<html lang="ro" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#061a15">
  <title>Abonamente Nordvale — Un an în pădure</title>
  <meta name="description" content="Abonamente anuale Nordvale pentru exploratori, familii și oaspeți care vor să revină în parc în fiecare sezon.">
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
      --pine-850:#0d3027;
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
      --soft-shadow:0 28px 76px -50px rgba(6,26,21,.62);
      --deep-shadow:0 42px 110px -60px rgba(6,26,21,.82);
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
    body.modal-open { overflow:hidden; }
    [x-cloak] { display:none!important; }
    button,a,input { -webkit-tap-highlight-color:transparent; }
    button:focus-visible,a:focus-visible,input:focus-visible { outline:3px solid rgba(223,252,98,.85);outline-offset:3px; }
    ::selection { background:var(--acid);color:var(--pine-950); }
    .font-display { font-family:'Fraunces',serif; }
    .page-shell { width:min(1480px,100%);margin-inline:auto;padding-inline:clamp(16px,3.1vw,44px); }
    .safe-top { padding-top:max(12px,env(safe-area-inset-top)); }
    .safe-bottom { padding-bottom:max(18px,env(safe-area-inset-bottom)); }
    .mobile-scroll { scrollbar-width:none; }
    .mobile-scroll::-webkit-scrollbar { display:none; }

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
        radial-gradient(circle at 13% 18%,rgba(223,252,98,.15),transparent 20%),
        radial-gradient(circle at 87% 9%,rgba(242,123,74,.14),transparent 18%),
        url("data:image/svg+xml,%3Csvg width='920' height='920' viewBox='0 0 920 920' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23fff' stroke-opacity='.055' stroke-width='1.2'%3E%3Cpath d='M98 237c42-119 177-203 307-151 107 42 132 168 73 254-55 82-169 79-225 162-58 87-38 226-151 279-107 51-232-35-217-155 14-117 133-137 144-244 5-62-2-104 69-145Z'/%3E%3Cpath d='M565 530c59-91 180-120 263-49 81 68 56 194-30 245-77 45-168 12-228 77-51 54-44 151-119 170-83 21-160-60-126-141 32-76 123-71 167-139 36-55 34-109 73-163Z'/%3E%3Cpath d='M248 676c28-57 95-84 151-56 56 29 69 101 34 150-34 47-100 48-133 92-36 48-27 116-84 139-61 24-126-22-119-88 7-65 74-82 82-141 6-41 2-66 69-96Z'/%3E%3C/g%3E%3C/svg%3E");
      background-size:auto,auto,920px 920px;
    }
    .paper-grid {
      background-image:linear-gradient(rgba(9,37,29,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(9,37,29,.045) 1px,transparent 1px);
      background-size:30px 30px;
    }
    .grain:after {
      content:'';position:absolute;inset:0;z-index:2;pointer-events:none;opacity:.075;mix-blend-mode:soft-light;
      background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.58'/%3E%3C/svg%3E");
    }
    .route-line { fill:none;stroke:var(--acid);stroke-width:2.5;stroke-linecap:round;stroke-dasharray:10 14;animation:route 16s linear infinite;filter:drop-shadow(0 0 8px rgba(223,252,98,.36)); }
    @keyframes route { to { stroke-dashoffset:-280; } }
    .outline-word { color:transparent;-webkit-text-stroke:1px rgba(255,255,255,.48); }

    .hero {
      min-height:860px;
      display:flex;
      align-items:center;
      padding-top:130px;
      padding-bottom:74px;
    }
    .hero-grid { display:grid;grid-template-columns:minmax(0,1.06fr) minmax(430px,.94fr);gap:clamp(42px,6vw,96px);align-items:center; }
    .hero-title { font-size:clamp(64px,7.2vw,112px);line-height:.88;letter-spacing:-.055em; }
    .hero-copy { max-width:690px;font-size:clamp(17px,1.35vw,21px);line-height:1.65;color:rgba(255,255,255,.68); }
    .hero-actions { display:flex;flex-wrap:wrap;gap:12px;margin-top:32px; }
    .btn-primary,.btn-secondary,.btn-light {
      display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:52px;border-radius:999px;padding:0 23px;font-weight:700;white-space:nowrap;transition:transform .22s ease,box-shadow .22s ease,background .22s ease;
    }
    .btn-primary { background:var(--acid);color:var(--pine-950);box-shadow:0 20px 52px -28px rgba(223,252,98,.72); }
    .btn-primary:hover { transform:translateY(-2px);box-shadow:0 25px 58px -28px rgba(223,252,98,.88); }
    .btn-secondary { border:1px solid rgba(255,255,255,.22);color:white;background:rgba(255,255,255,.06); }
    .btn-secondary:hover { background:rgba(255,255,255,.12);transform:translateY(-2px); }
    .btn-light { border:1px solid var(--line);background:white;color:var(--pine-900); }
    .btn-light:hover { transform:translateY(-2px);box-shadow:var(--soft-shadow); }

    .member-visual { position:relative;min-height:590px;perspective:1200px; }
    .member-card {
      position:absolute;width:min(420px,82%);aspect-ratio:1.58;border-radius:30px;padding:28px;overflow:hidden;transform-style:preserve-3d;
      box-shadow:0 48px 110px -56px rgba(0,0,0,.9);transition:transform .28s ease-out;
    }
    .member-card:before,.member-card:after { content:'';position:absolute;width:100px;height:100px;border-radius:50%;border:1px solid currentColor;opacity:.12; }
    .member-card:before { right:-35px;top:-28px; }.member-card:after { left:-46px;bottom:-35px; }
    .member-card--main { right:4%;top:12%;background:linear-gradient(145deg,var(--acid),#efffa4);color:var(--pine-950);transform:rotate(4deg);z-index:3; }
    .member-card--shadow { left:0;top:30%;background:linear-gradient(145deg,#133b31,#071d17);color:white;transform:rotate(-8deg);z-index:2; }
    .member-card--ember { right:18%;bottom:0;background:linear-gradient(145deg,#f39066,var(--ember));color:var(--pine-950);transform:rotate(12deg);z-index:1;width:min(360px,72%); }
    .card-chip { width:48px;height:36px;border-radius:10px;background:linear-gradient(135deg,rgba(255,255,255,.65),rgba(255,255,255,.15));border:1px solid rgba(255,255,255,.25); }
    .card-number { letter-spacing:.23em;font-size:12px;font-weight:700;opacity:.64; }
    .orbit { position:absolute;border:1px solid rgba(223,252,98,.18);border-radius:50%;pointer-events:none;animation:orbit 12s linear infinite; }
    @keyframes orbit { to { transform:rotate(360deg); } }
    .hero-note { position:absolute;left:50%;top:4%;transform:translateX(-50%);background:rgba(255,253,246,.96);color:var(--pine-950);padding:11px 16px;border-radius:999px;box-shadow:0 18px 50px -28px rgba(0,0,0,.9);font-size:13px;font-weight:700;white-space:nowrap;z-index:5; }

    .status-strip { display:grid;grid-template-columns:repeat(4,1fr);border-top:1px solid rgba(255,255,255,.11);border-bottom:1px solid rgba(255,255,255,.11); }
    .status-item { padding:22px 26px;display:flex;align-items:center;gap:13px;min-width:0; }
    .status-item + .status-item { border-left:1px solid rgba(255,255,255,.11); }
    .status-dot { width:10px;height:10px;border-radius:50%;background:var(--acid);box-shadow:0 0 0 6px rgba(223,252,98,.1);flex:none; }

    .section { padding:clamp(78px,9vw,140px) 0; }
    .section-head { display:grid;grid-template-columns:minmax(0,1.1fr) minmax(280px,.55fr);gap:30px;align-items:end;margin-bottom:50px; }
    .eyebrow { display:inline-flex;align-items:center;gap:10px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:var(--pine-700); }
    .eyebrow:before { content:'';width:34px;height:1px;background:currentColor; }
    .section-title { margin:12px 0 0;font-size:clamp(44px,5.5vw,82px);line-height:.96;letter-spacing:-.045em;color:var(--pine-950); }
    .section-copy { margin:0;font-size:17px;line-height:1.7;color:rgba(22,35,30,.64); }

    .finder {
      display:grid;grid-template-columns:minmax(0,1.2fr) minmax(340px,.8fr);gap:26px;border:1px solid var(--line);border-radius:34px;background:rgba(255,255,255,.84);padding:clamp(22px,3vw,38px);box-shadow:var(--soft-shadow);
    }
    .finder-controls { display:grid;gap:26px; }
    .control-label { display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:12px;font-size:13px;font-weight:700;color:var(--pine-900); }
    .chip-row { display:flex;flex-wrap:wrap;gap:9px; }
    .choice-chip { border:1px solid var(--line);background:white;border-radius:999px;padding:11px 15px;font-weight:700;font-size:14px;transition:.2s ease; }
    .choice-chip:hover { transform:translateY(-2px);border-color:rgba(9,37,29,.32); }
    .choice-chip.active { background:var(--pine-900);color:white;border-color:var(--pine-900);box-shadow:0 16px 40px -30px rgba(6,26,21,.9); }
    .range-shell { display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center; }
    .range-shell input[type=range] { width:100%;accent-color:var(--pine-900); }
    .range-value { min-width:70px;text-align:center;padding:9px 12px;border-radius:14px;background:var(--oat);font-weight:700; }
    .finder-result { position:relative;overflow:hidden;border-radius:28px;padding:28px;background:var(--pine-950);color:white;min-height:360px;display:flex;flex-direction:column;justify-content:space-between; }
    .finder-result:before { content:'';position:absolute;inset:-35%;background:conic-gradient(from 120deg,transparent,rgba(223,252,98,.16),transparent 34%,rgba(184,220,224,.12),transparent 68%);animation:spin 20s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .finder-result>* { position:relative;z-index:1; }

    .plans-layout { display:grid;grid-template-columns:minmax(0,1.08fr) minmax(0,.92fr);gap:24px;align-items:start; }
    .plan-featured {
      position:relative;overflow:hidden;border-radius:36px;background:var(--pine-950);color:white;padding:clamp(26px,4vw,48px);min-height:680px;box-shadow:var(--deep-shadow);display:flex;flex-direction:column;
    }
    .plan-featured:before { content:'';position:absolute;right:-15%;bottom:-24%;width:430px;height:430px;border-radius:50%;background:rgba(223,252,98,.12);filter:blur(6px); }
    .plan-featured>* { position:relative;z-index:1; }
    .plan-stack { display:grid;gap:24px; }
    .plan-card { position:relative;overflow:hidden;border:1px solid var(--line);border-radius:30px;background:white;padding:28px;min-height:328px;box-shadow:var(--soft-shadow);transition:transform .25s ease,border-color .25s ease; }
    .plan-card:hover { transform:translateY(-4px);border-color:rgba(9,37,29,.3); }
    .plan-card--ember { background:linear-gradient(145deg,#fff8f2,#ffe3d7); }
    .plan-card--sky { background:linear-gradient(145deg,#f6fbfb,#deeff0); }
    .plan-price { display:flex;align-items:flex-end;gap:8px;margin-top:20px; }
    .plan-price strong { font-family:'Fraunces',serif;font-size:58px;line-height:.9;letter-spacing:-.05em; }
    .plan-price span { padding-bottom:5px;font-size:13px;opacity:.58; }
    .benefit-list { display:grid;gap:12px;margin:28px 0 0;padding:0;list-style:none; }
    .benefit-list li { display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:1.5; }
    .benefit-check { width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:rgba(223,252,98,.18);color:var(--acid);font-size:12px;flex:none;margin-top:1px; }
    .plan-card .benefit-check { background:rgba(9,37,29,.07);color:var(--pine-900); }
    .plan-badge { display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:8px 12px;background:rgba(223,252,98,.14);color:var(--acid);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.14em; }
    .plan-card .plan-badge { background:rgba(9,37,29,.07);color:var(--pine-900); }

    .season-track { display:grid;grid-template-columns:repeat(4,1fr);gap:18px; }
    .season-card { position:relative;min-height:330px;border-radius:28px;overflow:hidden;padding:24px;display:flex;flex-direction:column;justify-content:flex-end;color:white;box-shadow:var(--soft-shadow); }
    .season-card img { position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .8s cubic-bezier(.2,.75,.2,1); }
    .season-card:hover img { transform:scale(1.06); }
    .season-card:after { content:'';position:absolute;inset:0;background:linear-gradient(to top,rgba(6,26,21,.94),rgba(6,26,21,.15) 68%); }
    .season-card>*:not(img) { position:relative;z-index:1; }
    .season-index { position:absolute!important;right:18px;top:14px;font-family:'Fraunces',serif;font-size:58px;opacity:.38; }

    .value-grid { display:grid;grid-template-columns:minmax(0,.86fr) minmax(0,1.14fr);gap:28px;align-items:stretch; }
    .value-copy { border-radius:34px;background:var(--pine-950);color:white;padding:clamp(28px,4vw,48px);display:flex;flex-direction:column;justify-content:space-between;min-height:500px; }
    .value-calculator { border:1px solid var(--line);border-radius:34px;background:white;padding:clamp(24px,3vw,38px);box-shadow:var(--soft-shadow); }
    .counter-row { display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px 0;border-bottom:1px solid var(--line); }
    .qty { display:flex;align-items:center;gap:10px; }
    .qty button { width:38px;height:38px;border-radius:50%;border:1px solid var(--line);background:white;display:grid;place-items:center;font-size:19px;transition:.2s ease; }
    .qty button:hover { background:var(--pine-900);color:white;border-color:var(--pine-900); }
    .value-result { margin-top:24px;padding:22px;border-radius:24px;background:var(--oat);display:grid;grid-template-columns:1fr auto;gap:16px;align-items:end; }
    .value-result strong { font-family:'Fraunces',serif;font-size:48px;line-height:.9;color:var(--pine-950); }

    .community-grid { display:grid;grid-template-columns:1.1fr .9fr;gap:24px; }
    .community-feature { min-height:520px;border-radius:34px;overflow:hidden;position:relative;padding:32px;display:flex;align-items:flex-end;color:white;box-shadow:var(--deep-shadow); }
    .community-feature img { position:absolute;inset:0;width:100%;height:100%;object-fit:cover; }
    .community-feature:after { content:'';position:absolute;inset:0;background:linear-gradient(to top,rgba(6,26,21,.95),rgba(6,26,21,.08) 70%); }
    .community-feature>div { position:relative;z-index:1;max-width:560px; }
    .event-stack { display:grid;gap:18px; }
    .event-card { display:grid;grid-template-columns:92px 1fr;gap:20px;align-items:center;border:1px solid var(--line);border-radius:26px;background:white;padding:18px;box-shadow:var(--soft-shadow); }
    .event-date { min-height:92px;border-radius:20px;background:var(--pine-900);color:white;display:grid;place-items:center;text-align:center; }
    .event-date strong { display:block;font-family:'Fraunces',serif;font-size:34px;line-height:1; }

    .compare-desktop { border:1px solid var(--line);border-radius:32px;overflow:hidden;background:white;box-shadow:var(--soft-shadow); }
    .compare-row { display:grid;grid-template-columns:1.15fr repeat(3,1fr);min-height:68px;align-items:stretch; }
    .compare-row + .compare-row { border-top:1px solid var(--line); }
    .compare-cell { padding:18px 20px;display:flex;align-items:center;justify-content:center;text-align:center; }
    .compare-cell:first-child { justify-content:flex-start;text-align:left;font-weight:700;background:rgba(240,236,223,.48); }
    .compare-head .compare-cell { min-height:120px;flex-direction:column;gap:6px;background:var(--pine-950);color:white; }
    .compare-head .compare-cell:first-child { background:var(--pine-900); }
    .compare-mobile { display:none; }
    .compare-mobile-card { border:1px solid var(--line);border-radius:26px;background:white;padding:22px; }

    .faq { max-width:980px;margin-inline:auto; }
    .faq-item { border-top:1px solid var(--line); }
    .faq-button { width:100%;display:flex;align-items:center;justify-content:space-between;gap:20px;padding:24px 0;text-align:left;font-weight:700;font-size:17px; }
    .faq-answer { display:grid;grid-template-rows:0fr;transition:grid-template-rows .3s ease; }
    .faq-answer>div { overflow:hidden; }
    .faq-item.open .faq-answer { grid-template-rows:1fr; }
    .faq-plus { transition:transform .25s ease; }
    .faq-item.open .faq-plus { transform:rotate(45deg); }

    .drawer-backdrop { position:fixed;inset:0;z-index:80;background:rgba(6,26,21,.68);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px); }
    .drawer { position:fixed;z-index:90;right:0;top:0;height:100%;width:min(520px,100%);background:var(--cream);box-shadow:-50px 0 120px -65px rgba(6,26,21,.95);overflow-y:auto; }
    .drawer-head { position:sticky;top:0;z-index:4;background:rgba(255,253,246,.92);backdrop-filter:blur(18px);border-bottom:1px solid var(--line); }
    .drawer-plan { border:1px solid var(--line);border-radius:26px;padding:20px;background:white; }
    .drawer-plan.active { border-color:var(--pine-900);box-shadow:0 0 0 2px var(--pine-900); }
    .checkbox-row { display:flex;align-items:flex-start;gap:12px;padding:14px;border:1px solid var(--line);border-radius:18px;background:white; }

    .mobile-bar { display:none;position:fixed;left:10px;right:10px;bottom:max(10px,env(safe-area-inset-bottom));z-index:60;border:1px solid rgba(255,255,255,.13);border-radius:24px;background:rgba(6,26,21,.94);color:white;padding:10px;box-shadow:0 28px 70px -30px rgba(0,0,0,.92);backdrop-filter:blur(18px); }
    .reveal { opacity:0;transform:translateY(25px);transition:opacity .72s ease,transform .72s cubic-bezier(.2,.75,.2,1); }
    .reveal.is-visible { opacity:1;transform:none; }

    @media (max-width:1180px) {
      .hero-grid { grid-template-columns:1fr 1fr;gap:38px; }
      .plans-layout { grid-template-columns:1fr; }
      .plan-featured { min-height:580px; }
      .plan-stack { grid-template-columns:1fr 1fr; }
      .season-track { grid-template-columns:repeat(2,1fr); }
    }
    @media (max-width:900px) {
      .hero { min-height:auto;padding-top:120px; }
      .hero-grid,.finder,.value-grid,.community-grid { grid-template-columns:1fr; }
      .hero-copy { max-width:none; }
      .member-visual { min-height:560px;max-width:680px;width:100%;margin-inline:auto; }
      .status-strip { grid-template-columns:repeat(2,1fr); }
      .status-item:nth-child(3) { border-left:0; }
      .status-item:nth-child(n+3) { border-top:1px solid rgba(255,255,255,.11); }
      .section-head { grid-template-columns:1fr;align-items:start; }
      .finder-result { min-height:300px; }
      .community-feature { min-height:450px; }
    }
    @media (max-width:720px) {
      body { padding-bottom:88px; }
      .hero { padding-top:108px;padding-bottom:54px; }
      .hero-title { font-size:clamp(48px,15vw,68px); }
      .member-visual { min-height:auto;display:grid;gap:14px;perspective:none; }
      .member-card { position:relative!important;inset:auto!important;width:100%!important;aspect-ratio:auto;min-height:228px;transform:none!important;border-radius:24px;padding:22px; }
      .member-card--shadow { order:2; }.member-card--main { order:1; }.member-card--ember { order:3; }
      .hero-note,.orbit { display:none; }
      .status-strip { display:flex;overflow-x:auto;scroll-snap-type:x mandatory; }
      .status-item { min-width:235px;scroll-snap-align:start;border-left:0!important;border-top:0!important; }
      .status-item + .status-item { border-left:1px solid rgba(255,255,255,.11)!important; }
      .section { padding:76px 0; }
      .section-head { margin-bottom:34px; }
      .section-title { font-size:clamp(42px,12vw,58px); }
      .finder { padding:18px;border-radius:26px;overflow:hidden; }
      .finder-controls,.finder-controls>div { min-width:0; }
      .chip-row { width:100%;max-width:100%;min-width:0;flex-wrap:nowrap;overflow-x:auto;padding-bottom:3px;scrollbar-width:none; }
      .choice-chip { flex:none; }
      .plan-featured { min-height:auto;border-radius:28px; }
      .plan-stack { grid-template-columns:1fr; }
      .plan-card { min-height:0;border-radius:26px; }
      .plan-price strong { font-size:52px; }
      .season-track { width:100%;max-width:100%;display:flex;overflow-x:auto;scroll-snap-type:x mandatory;margin-inline:0;padding-inline:0;padding-bottom:8px; }
      .season-card { min-width:82vw;scroll-snap-align:center; }
      .value-copy { min-height:390px;border-radius:28px; }
      .value-calculator { border-radius:28px; }
      .value-result { grid-template-columns:1fr;align-items:start; }
      .event-card { grid-template-columns:76px 1fr;gap:14px;padding:14px;border-radius:22px; }
      .event-date { min-height:76px;border-radius:17px; }
      .compare-desktop { display:none; }
      .compare-mobile { display:grid;gap:14px; }
      .mobile-bar { display:flex;align-items:center;justify-content:space-between;gap:12px; }
      .drawer { top:auto;bottom:0;height:min(90vh,820px);width:100%;border-radius:28px 28px 0 0; }
    }
    @media (max-width:390px) {
      .page-shell { padding-inline:14px; }
      .nav-subtitle { display:none; }
      .nav-shell { padding-left:10px!important;padding-right:10px!important; }
      .nav-cta { padding-left:13px!important;padding-right:13px!important;font-size:13px!important; }
      .member-card { min-height:212px;padding:19px; }
      .hero-actions { display:grid;grid-template-columns:1fr; }
      .btn-primary,.btn-secondary,.btn-light { width:100%; }
      .finder-result { padding:22px; }
      .season-card { min-width:88vw; }
      .event-card { grid-template-columns:68px 1fr; }
      .event-date { min-height:68px; }
    }

    /* Compatibility utilities used by this standalone build. */
    .bg-white.bg-opacity-5{background-color:rgba(255,255,255,.05)!important}
    .bg-white.bg-opacity-20{background-color:rgba(255,255,255,.20)!important}
    .text-white.text-opacity-35{color:rgba(255,255,255,.35)!important}
    .text-white.text-opacity-40{color:rgba(255,255,255,.40)!important}
    .text-white.text-opacity-45{color:rgba(255,255,255,.45)!important}
    .text-white.text-opacity-50{color:rgba(255,255,255,.50)!important}
    .text-white.text-opacity-55{color:rgba(255,255,255,.55)!important}
    .text-white.text-opacity-60{color:rgba(255,255,255,.60)!important}
    .border-white.border-opacity-10{border-color:rgba(255,255,255,.10)!important}
    .border-white.border-opacity-20{border-color:rgba(255,255,255,.20)!important}
    .bottom-6{bottom:1.5rem}.gap-x-6{column-gap:1.5rem}.gap-y-3{row-gap:.75rem}
    .h-14{height:3.5rem}.w-14{width:3.5rem}.max-w-lg{max-width:32rem}.max-w-2xl{max-width:42rem}.max-w-4xl{max-width:56rem}
    .mb-2{margin-bottom:.5rem}.mb-3{margin-bottom:.75rem}.mb-5{margin-bottom:1.25rem}.mb-6{margin-bottom:1.5rem}
    .mt-9{margin-top:2.25rem}.mt-12{margin-top:3rem}.mt-14{margin-top:3.5rem}.mt-16{margin-top:4rem}.mt-auto{margin-top:auto}
    .opacity-35{opacity:.35}.opacity-60{opacity:.60}.pr-10{padding-right:2.5rem}.pt-12{padding-top:3rem}.py-12{padding-top:3rem;padding-bottom:3rem}.py-24{padding-top:6rem;padding-bottom:6rem}
    .z-\[120\]{z-index:120}
    @media(min-width:640px){.sm\:p-6{padding:1.5rem}.sm\:text-7xl{font-size:4.5rem;line-height:1}}
    @media(min-width:768px){.md\:grid-cols-\[1fr_auto\]{grid-template-columns:1fr auto}.md\:items-end{align-items:flex-end}.md\:justify-end{justify-content:flex-end}.md\:text-right{text-align:right}.md\:w-auto{width:auto}}

    @media (prefers-reduced-motion:reduce) {
      *,*:before,*:after { animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important; }
      .reveal { opacity:1!important;transform:none!important; }
    }
  </style>
</head>
<body x-data="membershipPage()" x-init="init()" :class="drawerOpen ? 'modal-open' : ''">
  <div id="scroll-progress" style="position:fixed;left:0;top:0;z-index:100;height:3px;width:100%;background:var(--acid);transform:scaleX(0);transform-origin:left"></div>

  <?php $nvNav='abonamente'; $nvNoSpacer=true; include __DIR__ . '/includes/header.php'; ?>

  <main>
    <section class="hero topo-dark grain relative overflow-hidden text-white">
      <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-50" viewBox="0 0 1600 900" preserveAspectRatio="none" aria-hidden="true">
        <path class="route-line" d="M-40 740C200 650 235 355 480 390c222 32 245 280 474 229 210-47 182-337 448-377 102-15 188 9 260 54"/>
      </svg>
      <div class="page-shell relative z-10">
        <div class="hero-grid">
          <div>
            <div class="mb-6 inline-flex items-center gap-3 rounded-full border border-white border-opacity-20 bg-white bg-opacity-5 px-4 py-2 text-xs font-bold uppercase tracking-widest">
              <span class="status-dot"></span>
              Abonamentele 2026 sunt disponibile
            </div>
            <p class="mb-5 text-xs font-bold uppercase tracking-widest" style="color:var(--acid)">Pentru cei care nu vin o singură dată</p>
            <h1 class="hero-title font-display font-semibold">Un an<br>în <span class="outline-word">pădure.</span></h1>
            <p class="hero-copy mt-7">Nu cumperi doar intrări. Îți rezervi libertatea de a reveni când se schimbă lumina, traseele, anotimpul sau pur și simplu starea ta.</p>
            <div class="hero-actions">
              <a href="#abonamente" class="btn-primary">Descoperă nivelurile <span aria-hidden="true">↘</span></a>
              <button type="button" @click="scrollToFinder()" class="btn-secondary">Găsește abonamentul tău</button>
            </div>
            <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-white text-opacity-50">
              <span>✓ Activare instant</span><span>✓ Valabil 12 luni</span><span>✓ Card digital în cont</span>
            </div>
          </div>

          <div class="member-visual" data-tilt-area>
            <div class="orbit" style="width:470px;height:470px;right:2%;top:9%"></div>
            <div class="orbit" style="width:320px;height:320px;left:5%;bottom:2%;animation-direction:reverse;animation-duration:17s"></div>
            <div class="hero-note">3 niveluri · beneficii în toate anotimpurile</div>

            <article class="member-card member-card--shadow" data-card-tilt="-0.45">
              <div class="flex items-start justify-between"><div class="card-chip"></div><span class="text-xs font-bold uppercase tracking-widest text-white text-opacity-50">Trail Key</span></div>
              <div class="mt-14"><p class="font-display text-3xl font-semibold">Nordvale Member</p><p class="mt-2 text-sm text-white text-opacity-50">4 vizite · acces anticipat</p></div>
              <div class="mt-10 flex items-end justify-between"><span class="card-number">NV · 0007 · TRAIL</span><svg viewBox="0 0 48 48" class="h-10 w-10"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#DFFC62"/></svg></div>
            </article>

            <article class="member-card member-card--main" data-card-tilt="0.7">
              <div class="flex items-start justify-between"><div class="card-chip"></div><span class="text-xs font-bold uppercase tracking-widest">Wild Circle</span></div>
              <div class="mt-14"><p class="font-display text-4xl font-semibold">Come back wild.</p><p class="mt-2 text-sm opacity-60">Acces recurent · guest pass · 20% extras</p></div>
              <div class="mt-10 flex items-end justify-between"><span class="card-number">NV · 0426 · WILD</span><span class="rounded-full bg-white bg-opacity-20 px-3 py-1 text-xs font-bold">2026—27</span></div>
            </article>

            <article class="member-card member-card--ember" data-card-tilt="0.35">
              <div class="flex items-start justify-between"><div class="card-chip"></div><span class="text-xs font-bold uppercase tracking-widest">Summit Society</span></div>
              <div class="mt-12"><p class="font-display text-3xl font-semibold">All seasons.</p><p class="mt-2 text-sm opacity-60">Prioritate maximă · evenimente private</p></div>
            </article>
          </div>
        </div>
      </div>
    </section>

    <section class="topo-dark text-white">
      <div class="page-shell">
        <div class="status-strip mobile-scroll">
          <div class="status-item"><span class="status-dot"></span><div><strong class="block text-sm">Valabilitate reală</strong><span class="text-xs text-white text-opacity-40">12 luni de la activare</span></div></div>
          <div class="status-item"><span class="status-dot" style="background:var(--sky)"></span><div><strong class="block text-sm">Card digital</strong><span class="text-xs text-white text-opacity-40">În cont și în telefon</span></div></div>
          <div class="status-item"><span class="status-dot" style="background:var(--ember)"></span><div><strong class="block text-sm">Beneficii dinamice</strong><span class="text-xs text-white text-opacity-40">Se schimbă cu sezonul</span></div></div>
          <div class="status-item"><span class="status-dot"></span><div><strong class="block text-sm">Transfer controlat</strong><span class="text-xs text-white text-opacity-40">O schimbare de titular/an</span></div></div>
        </div>
      </div>
    </section>

    <section id="finder" class="section paper-grid">
      <div class="page-shell">
        <div class="section-head reveal">
          <div><span class="eyebrow">Membership finder</span><h2 class="section-title font-display font-semibold">Cât de des te întorci?</h2></div>
          <p class="section-copy">Nu recomandăm automat cel mai scump nivel. Recomandarea se schimbă după grup, ritmul vizitelor și cât de mult contează accesul prioritar.</p>
        </div>

        <div class="finder reveal">
          <div class="finder-controls">
            <div>
              <div class="control-label"><span>Cine folosește abonamentul?</span><span x-text="audienceLabel" class="opacity-50"></span></div>
              <div class="chip-row mobile-scroll">
                <button type="button" @click="audience='solo'" :class="audience==='solo'?'active':''" class="choice-chip active">Doar eu</button><button type="button" @click="audience='couple'" :class="audience==='couple'?'active':''" class="choice-chip">Cuplu</button><button type="button" @click="audience='family'" :class="audience==='family'?'active':''" class="choice-chip">Familie</button><button type="button" @click="audience='friends'" :class="audience==='friends'?'active':''" class="choice-chip">Grup de prieteni</button>
              </div>
            </div>
            <div>
              <div class="control-label"><span>Câte vizite estimezi în următoarele 12 luni?</span><span class="range-value"><span x-text="visits"></span> vizite</span></div>
              <div class="range-shell"><input type="range" min="2" max="16" step="1" x-model.number="visits" aria-label="Număr estimat de vizite"><span class="text-xs opacity-50">2—16</span></div>
            </div>
            <div>
              <div class="control-label"><span>Ce beneficiu contează cel mai mult?</span></div>
              <div class="chip-row mobile-scroll">
                <button type="button" @click="priority='price'" :class="priority==='price'?'active':''" class="choice-chip">Preț minim</button><button type="button" @click="priority='flexibility'" :class="priority==='flexibility'?'active':''" class="choice-chip active">Flexibilitate</button><button type="button" @click="priority='priority'" :class="priority==='priority'?'active':''" class="choice-chip">Acces prioritar</button><button type="button" @click="priority='community'" :class="priority==='community'?'active':''" class="choice-chip">Evenimente private</button>
              </div>
            </div>
          </div>

          <aside class="finder-result">
            <div>
              <span class="inline-flex rounded-full px-3 py-2 text-xs font-bold uppercase tracking-widest" style="background:rgba(223,252,98,.14);color:var(--acid)">Recomandarea Nordvale</span>
              <p class="mt-8 text-sm text-white text-opacity-45" x-text="recommendation.reason">Echilibrul bun între frecvență și flexibilitate.</p>
              <h3 class="mt-3 font-display text-5xl font-semibold" x-text="recommendation.name">Wild Circle</h3>
              <p class="mt-4 max-w-sm text-sm leading-6 text-white text-opacity-60" x-text="recommendation.summary">Ai suficient acces pentru vizite spontane, weekenduri și un oaspete, fără costul nivelului premium.</p>
            </div>
            <div class="mt-10 flex items-end justify-between gap-5">
              <div><strong class="font-display text-5xl" x-text="recommendation.price + ' RON'">649 RON</strong><span class="block text-xs text-white text-opacity-40">/ 12 luni</span></div>
              <button type="button" @click="choosePlan(recommendation.id)" class="grid h-14 w-14 flex-none place-items-center rounded-full" style="background:var(--acid);color:var(--pine-950)" aria-label="Alege abonamentul recomandat">↗</button>
            </div>
          </aside>
        </div>
      </div>
    </section>

    <section id="abonamente" class="section" style="background:var(--oat)">
      <div class="page-shell">
        <div class="section-head reveal">
          <div><span class="eyebrow">Niveluri 2026—2027</span><h2 class="section-title font-display font-semibold">Trei feluri de a aparține.</h2></div>
          <p class="section-copy">Fiecare nivel are o logică diferită. Trail Key este pentru reveniri planificate, Wild Circle pentru ritm constant, Summit Society pentru acces fără compromisuri.</p>
        </div>

        <div class="plans-layout">
          <article class="plan-featured reveal">
            <div class="flex flex-wrap items-start justify-between gap-5"><span class="plan-badge">Cel mai ales</span><span class="text-xs font-bold uppercase tracking-widest text-white text-opacity-35">Nivel 02</span></div>
            <div class="mt-16 max-w-2xl">
              <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--acid)">Wild Circle</p>
              <h3 class="mt-4 font-display text-6xl font-semibold leading-none sm:text-7xl">Parcul devine locul tău obișnuit.</h3>
              <p class="mt-6 max-w-xl text-base leading-7 text-white text-opacity-60">Acces recurent în timpul săptămânii, opt zile de weekend incluse și suficientă flexibilitate pentru vizite spontane.</p>
            </div>
            <div class="mt-auto grid gap-8 pt-12 md:grid-cols-[1fr_auto] md:items-end">
              <ul class="benefit-list">
                <li><span class="benefit-check">✓</span><span>Acces nelimitat luni—vineri, în programul public</span></li>
                <li><span class="benefit-check">✓</span><span>8 intrări de weekend sau sărbătoare legală</span></li>
                <li><span class="benefit-check">✓</span><span>1 guest pass și 20% reducere la experiențe</span></li>
                <li><span class="benefit-check">✓</span><span>Rezervări cu 7 zile înaintea publicului</span></li>
              </ul>
              <div class="md:text-right"><div class="plan-price md:justify-end"><strong>649</strong><span>RON / an</span></div><button type="button" @click="choosePlan('wild')" class="btn-primary mt-6 w-full md:w-auto">Alege Wild Circle</button></div>
            </div>
          </article>

          <div class="plan-stack">
            <article class="plan-card plan-card--sky reveal">
              <div class="flex items-start justify-between gap-4"><span class="plan-badge">Start inteligent</span><span class="text-xs font-bold opacity-35">01</span></div>
              <h3 class="mt-7 font-display text-4xl font-semibold">Trail Key</h3>
              <p class="mt-3 text-sm leading-6 opacity-60">Pentru cei care știu că vor reveni, dar nu vor un abonament nelimitat.</p>
              <div class="plan-price"><strong>329</strong><span>RON / an</span></div>
              <div class="mt-6 flex flex-wrap gap-2 text-xs font-bold"><span class="rounded-full bg-white px-3 py-2">4 vizite</span><span class="rounded-full bg-white px-3 py-2">10% extras</span><span class="rounded-full bg-white px-3 py-2">48h early access</span></div>
              <button type="button" @click="choosePlan('trail')" class="btn-light mt-7 w-full">Alege Trail Key</button>
            </article>

            <article class="plan-card plan-card--ember reveal">
              <div class="flex items-start justify-between gap-4"><span class="plan-badge">Fără compromis</span><span class="text-xs font-bold opacity-35">03</span></div>
              <h3 class="mt-7 font-display text-4xl font-semibold">Summit Society</h3>
              <p class="mt-3 text-sm leading-6 opacity-60">Acces oricând, prioritate maximă și evenimente rezervate comunității.</p>
              <div class="plan-price"><strong>1.090</strong><span>RON / an</span></div>
              <div class="mt-6 flex flex-wrap gap-2 text-xs font-bold"><span class="rounded-full bg-white px-3 py-2">Acces nelimitat</span><span class="rounded-full bg-white px-3 py-2">4 guest passes</span><span class="rounded-full bg-white px-3 py-2">Concierge</span></div>
              <button type="button" @click="choosePlan('summit')" class="btn-light mt-7 w-full">Alege Summit Society</button>
            </article>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="page-shell">
        <div class="section-head reveal">
          <div><span class="eyebrow">Un an, patru versiuni</span><h2 class="section-title font-display font-semibold">Beneficiile se schimbă cu pădurea.</h2></div>
          <p class="section-copy">Membership-ul nu este o reducere statică. Fiecare sezon deschide alt tip de acces, alt program și alte întâlniri.</p>
        </div>
        <div class="season-track mobile-scroll">
          <article class="season-card reveal"><img src="https://images.unsplash.com/photo-1497250681960-ef046c08a56e?w=900&q=85" alt="Pădure verde primăvara"><span class="season-index">01</span><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--acid)">Primăvară</p><h3 class="mt-2 font-display text-3xl font-semibold">First trails</h3><p class="mt-2 text-sm text-white text-opacity-60">Tururi de redeschidere și acces anticipat la traseele proaspăt verificate.</p></article>
          <article class="season-card reveal"><img src="https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?w=900&q=85" alt="Aventură în pădure vara"><span class="season-index">02</span><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--acid)">Vară</p><h3 class="mt-2 font-display text-3xl font-semibold">Long light</h3><p class="mt-2 text-sm text-white text-opacity-60">Sesiuni extinse, seri de membru și acces la intervale înainte de deschidere.</p></article>
          <article class="season-card reveal"><img src="https://images.unsplash.com/photo-1504788363733-507549153474?w=900&q=85" alt="Pădure toamna"><span class="season-index">03</span><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--acid)">Toamnă</p><h3 class="mt-2 font-display text-3xl font-semibold">Amber routes</h3><p class="mt-2 text-sm text-white text-opacity-60">Trasee foto, tururi de orientare și seri în jurul focului.</p></article>
          <article class="season-card reveal"><img src="https://images.unsplash.com/photo-1483664852095-d6cc6870702d?w=900&q=85" alt="Pădure iarna"><span class="season-index">04</span><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--acid)">Iarnă</p><h3 class="mt-2 font-display text-3xl font-semibold">Quiet season</h3><p class="mt-2 text-sm text-white text-opacity-60">Acces la poteci deschise, cabană și evenimente restrânse pentru comunitate.</p></article>
        </div>
      </div>
    </section>

    <section class="section topo-dark text-white">
      <div class="page-shell">
        <div class="value-grid">
          <article class="value-copy reveal">
            <div><span class="inline-flex rounded-full px-3 py-2 text-xs font-bold uppercase tracking-widest" style="background:rgba(223,252,98,.14);color:var(--acid)">Calculator de valoare</span><h2 class="mt-7 font-display text-6xl font-semibold leading-none">Când începe să merite?</h2><p class="mt-5 max-w-md text-base leading-7 text-white text-opacity-55">Comparația folosește un cost mediu dummy pentru acces și activități. Este un instrument de orientare, nu un preț final de checkout.</p></div>
            <div class="mt-10"><p class="text-xs font-bold uppercase tracking-widest text-white text-opacity-35">Pragul recomandat</p><p class="mt-2 font-display text-3xl" x-text="breakEvenText">Aproximativ 4 vizite pentru grupul configurat</p></div>
          </article>

          <div class="value-calculator reveal">
            <div class="counter-row"><div><strong class="block">Adulți</strong><span class="text-sm opacity-50">titulari principali</span></div><div class="qty"><button type="button" @click="adults=Math.max(1,adults-1)">−</button><strong x-text="adults">2</strong><button type="button" @click="adults=Math.min(6,adults+1)">+</button></div></div>
            <div class="counter-row"><div><strong class="block">Copii</strong><span class="text-sm opacity-50">6—17 ani</span></div><div class="qty"><button type="button" @click="children=Math.max(0,children-1)">−</button><strong x-text="children">1</strong><button type="button" @click="children=Math.min(6,children+1)">+</button></div></div>
            <div class="counter-row"><div><strong class="block">Vizite estimate</strong><span class="text-sm opacity-50">în următoarele 12 luni</span></div><div class="qty"><button type="button" @click="calcVisits=Math.max(2,calcVisits-1)">−</button><strong x-text="calcVisits">6</strong><button type="button" @click="calcVisits=Math.min(18,calcVisits+1)">+</button></div></div>
            <div class="mt-6 grid gap-3 sm:grid-cols-3">
              <button type="button" @click="calculatorPlan='trail'" :class="calculatorPlan==='trail'?'choice-chip active':'choice-chip'" class="choice-chip">Trail</button><button type="button" @click="calculatorPlan='wild'" :class="calculatorPlan==='wild'?'choice-chip active':'choice-chip'" class="choice-chip active">Wild</button><button type="button" @click="calculatorPlan='summit'" :class="calculatorPlan==='summit'?'choice-chip active':'choice-chip'" class="choice-chip">Summit</button>
            </div>
            <div class="value-result">
              <div><span class="text-xs font-bold uppercase tracking-widest opacity-45">Economie estimată</span><strong class="mt-2 block" x-text="estimatedSavings + ' RON'">886 RON</strong><p class="mt-2 text-sm opacity-55" x-text="savingsCopy">Față de aproximativ 1.602 RON în vizite separate.</p></div>
              <button type="button" @click="choosePlan(calculatorPlan)" class="btn-primary">Continuă</button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section" style="background:var(--oat)">
      <div class="page-shell">
        <div class="section-head reveal"><div><span class="eyebrow">Mai mult decât acces</span><h2 class="section-title font-display font-semibold">O comunitate cu propriul calendar.</h2></div><p class="section-copy">Unele momente nu apar în calendarul public. Membrii primesc invitații la tururi tehnice, seri de traseu și sesiuni cu echipa parcului.</p></div>
        <div class="community-grid">
          <article class="community-feature reveal"><img src="https://images.unsplash.com/photo-1529156069898-49953e39b3ac?w=1400&q=88" alt="Grup de prieteni în natură"><div><span class="text-xs font-bold uppercase tracking-widest" style="color:var(--acid)">Member night · august</span><h3 class="mt-4 font-display text-5xl font-semibold">Pădurea după ultima intrare.</h3><p class="mt-4 max-w-lg text-sm leading-6 text-white text-opacity-60">O seară cu trasee deschise doar comunității, lumină joasă și cină simplă la refugiu.</p></div></article>
          <div class="event-stack">
            <article class="event-card reveal"><div class="event-date"><div><strong>12</strong><span class="text-xs uppercase tracking-widest text-white text-opacity-50">apr</span></div></div><div><p class="text-xs font-bold uppercase tracking-widest opacity-45">Trail Key+</p><h3 class="mt-1 font-display text-2xl font-semibold">Walk the reset</h3><p class="mt-2 text-sm opacity-55">Tur de primăvară cu echipa care verifică traseele.</p></div></article>
            <article class="event-card reveal"><div class="event-date" style="background:var(--ember);color:var(--pine-950)"><div><strong>18</strong><span class="text-xs uppercase tracking-widest opacity-55">iul</span></div></div><div><p class="text-xs font-bold uppercase tracking-widest opacity-45">Wild Circle+</p><h3 class="mt-1 font-display text-2xl font-semibold">Sunset canopy</h3><p class="mt-2 text-sm opacity-55">Ultima tură începe când parcul public se închide.</p></div></article>
            <article class="event-card reveal"><div class="event-date" style="background:var(--sky);color:var(--pine-950)"><div><strong>03</strong><span class="text-xs uppercase tracking-widest opacity-55">oct</span></div></div><div><p class="text-xs font-bold uppercase tracking-widest opacity-45">Summit only</p><h3 class="mt-1 font-display text-2xl font-semibold">Map room</h3><p class="mt-2 text-sm opacity-55">Seară restrânsă despre hărți, conservare și trasee noi.</p></div></article>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="page-shell">
        <div class="section-head reveal"><div><span class="eyebrow">Comparație clară</span><h2 class="section-title font-display font-semibold">Ce deblochează fiecare nivel?</h2></div><p class="section-copy">Fără asteriscuri ascunse. Beneficiile esențiale sunt comparate direct.</p></div>
        <div class="compare-desktop reveal">
          <div class="compare-row compare-head"><div class="compare-cell">Beneficiu</div><div class="compare-cell"><strong class="font-display text-2xl">Trail Key</strong><span class="text-xs text-white text-opacity-40">329 RON</span></div><div class="compare-cell"><strong class="font-display text-2xl">Wild Circle</strong><span class="text-xs" style="color:var(--acid)">649 RON</span></div><div class="compare-cell"><strong class="font-display text-2xl">Summit</strong><span class="text-xs text-white text-opacity-40">1.090 RON</span></div></div>
          <div class="compare-row"><div class="compare-cell">Acces inclus</div><div class="compare-cell">4 vizite</div><div class="compare-cell">Nelimitat L—V + 8 weekend</div><div class="compare-cell">Nelimitat oricând</div></div>
          <div class="compare-row"><div class="compare-cell">Reducere experiențe</div><div class="compare-cell">10%</div><div class="compare-cell">20%</div><div class="compare-cell">30%</div></div>
          <div class="compare-row"><div class="compare-cell">Guest passes</div><div class="compare-cell">—</div><div class="compare-cell">1</div><div class="compare-cell">4</div></div>
          <div class="compare-row"><div class="compare-cell">Acces anticipat</div><div class="compare-cell">48 ore</div><div class="compare-cell">7 zile</div><div class="compare-cell">14 zile</div></div>
          <div class="compare-row"><div class="compare-cell">Evenimente private</div><div class="compare-cell">Selectate</div><div class="compare-cell">Incluse</div><div class="compare-cell">Incluse + concierge</div></div>
        </div>
        <div class="compare-mobile">
          <article class="compare-mobile-card reveal"><div class="flex items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest opacity-45">Nivel 01</p><h3 class="mt-1 font-display text-3xl font-semibold">Trail Key</h3></div><strong class="font-display text-3xl">329 RON</strong></div><ul class="mt-6 grid gap-3 text-sm"><li class="flex gap-3"><span class="benefit-check">✓</span><span>4 vizite incluse</span></li><li class="flex gap-3"><span class="benefit-check">✓</span><span>10% reducere la experiențe</span></li><li class="flex gap-3"><span class="benefit-check">✓</span><span>Acces anticipat cu 48 de ore</span></li></ul><button type="button" @click="choosePlan('trail')" class="btn-light mt-6 w-full">Alege nivelul</button></article>
          <article class="compare-mobile-card reveal"><div class="flex items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest opacity-45">Nivel 02</p><h3 class="mt-1 font-display text-3xl font-semibold">Wild Circle</h3></div><strong class="font-display text-3xl">649 RON</strong></div><ul class="mt-6 grid gap-3 text-sm"><li class="flex gap-3"><span class="benefit-check">✓</span><span>Acces nelimitat luni—vineri</span></li><li class="flex gap-3"><span class="benefit-check">✓</span><span>8 intrări de weekend</span></li><li class="flex gap-3"><span class="benefit-check">✓</span><span>20% reducere și 1 guest pass</span></li></ul><button type="button" @click="choosePlan('wild')" class="btn-light mt-6 w-full">Alege nivelul</button></article>
          <article class="compare-mobile-card reveal"><div class="flex items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest opacity-45">Nivel 03</p><h3 class="mt-1 font-display text-3xl font-semibold">Summit Society</h3></div><strong class="font-display text-3xl">1.090 RON</strong></div><ul class="mt-6 grid gap-3 text-sm"><li class="flex gap-3"><span class="benefit-check">✓</span><span>Acces nelimitat oricând</span></li><li class="flex gap-3"><span class="benefit-check">✓</span><span>4 guest passes</span></li><li class="flex gap-3"><span class="benefit-check">✓</span><span>30% reducere și concierge</span></li></ul><button type="button" @click="choosePlan('summit')" class="btn-light mt-6 w-full">Alege nivelul</button></article>
        </div>
      </div>
    </section>

    <section class="section" style="background:var(--oat)">
      <div class="page-shell">
        <div class="mx-auto max-w-3xl text-center reveal"><span class="eyebrow">Întrebări frecvente</span><h2 class="section-title font-display font-semibold">Înainte să devii membru.</h2></div>
        <div class="faq mt-12">
          <article class="faq-item" :class="openFaq===0?'open':''"><button type="button" @click="openFaq=openFaq===0?null:0" class="faq-button"><span>Când începe valabilitatea abonamentului?</span><span class="faq-plus text-2xl">＋</span></button><div class="faq-answer"><div><p class="pb-6 pr-10 text-sm leading-7 opacity-60">La prima activare, nu la data cumpărării. Abonamentul poate fi activat în maximum 6 luni de la achiziție și rămâne valabil 12 luni.</p></div></div></article>
          <article class="faq-item" :class="openFaq===1?'open':''"><button type="button" @click="openFaq=openFaq===1?null:1" class="faq-button"><span>Este abonamentul nominal?</span><span class="faq-plus text-2xl">＋</span></button><div class="faq-answer"><div><p class="pb-6 pr-10 text-sm leading-7 opacity-60">Da. Cardul digital este nominal și include fotografia titularului după activare. Summit Society permite și guest passes, dar acestea nu transferă abonamentul.</p></div></div></article>
          <article class="faq-item" :class="openFaq===2?'open':''"><button type="button" @click="openFaq=openFaq===2?null:2" class="faq-button"><span>Trebuie să rezerv fiecare vizită?</span><span class="faq-plus text-2xl">＋</span></button><div class="faq-answer"><div><p class="pb-6 pr-10 text-sm leading-7 opacity-60">Da, pentru controlul capacității și siguranță. Membrii au acces la rezervări înaintea publicului, în funcție de nivel.</p></div></div></article>
          <article class="faq-item" :class="openFaq===3?'open':''"><button type="button" @click="openFaq=openFaq===3?null:3" class="faq-button"><span>Ce se întâmplă dacă parcul se închide din cauza vremii?</span><span class="faq-plus text-2xl">＋</span></button><div class="faq-answer"><div><p class="pb-6 pr-10 text-sm leading-7 opacity-60">Vizitele rezervate nu sunt consumate. Pentru nivelurile cu număr limitat de intrări, creditul revine automat în cont.</p></div></div></article>
          <article class="faq-item" :class="openFaq===4?'open':''"><button type="button" @click="openFaq=openFaq===4?null:4" class="faq-button"><span>Pot schimba titularul?</span><span class="faq-plus text-2xl">＋</span></button><div class="faq-answer"><div><p class="pb-6 pr-10 text-sm leading-7 opacity-60">Este permisă o singură schimbare de titular în perioada de valabilitate, înainte ca abonamentul să fi fost folosit de mai mult de două ori.</p></div></div></article>
        </div>
      </div>
    </section>

    <section class="topo-dark relative overflow-hidden py-24 text-white">
      <div class="page-shell relative z-10 text-center reveal"><p class="text-xs font-bold uppercase tracking-widest" style="color:var(--acid)">Membership 2026—2027</p><h2 class="mx-auto mt-5 max-w-4xl font-display text-6xl font-semibold leading-none sm:text-7xl">Nu trebuie să alegi acum următoarea aventură. Doar dreptul de a reveni.</h2><div class="mt-9 flex flex-wrap justify-center gap-3"><button type="button" @click="choosePlan(recommendation.id)" class="btn-primary">Alege nivelul recomandat</button><a href="/bilete" class="btn-secondary">Prefer o singură vizită</a></div></div>
    </section>
  </main>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <div class="mobile-bar safe-bottom">
    <div class="min-w-0"><span class="block truncate text-xs text-white text-opacity-45">Recomandat pentru tine</span><strong class="block truncate font-display text-xl" x-text="recommendation.name">Wild Circle</strong></div>
    <button type="button" @click="choosePlan(recommendation.id)" class="flex-none rounded-full px-5 py-3 text-sm font-bold whitespace-nowrap" style="background:var(--acid);color:var(--pine-950)">Alege</button>
  </div>

  <template x-if="drawerOpen">
    <div x-cloak>
      <div class="drawer-backdrop" @click="drawerOpen=false"></div>
      <aside class="drawer safe-bottom" role="dialog" aria-modal="true" aria-label="Configurare abonament">
        <div class="drawer-head flex items-center justify-between gap-4 p-5 sm:p-6"><div><p class="text-xs font-bold uppercase tracking-widest opacity-45">Configurare membership</p><h2 class="mt-1 font-display text-3xl font-semibold">Alege nivelul</h2></div><button type="button" @click="drawerOpen=false" class="grid h-11 w-11 place-items-center rounded-full border" style="border-color:var(--line)" aria-label="Închide">✕</button></div>
        <div class="p-5 sm:p-6">
          <div class="grid" style="gap:12px">
            <button type="button" @click="selectedPlan='trail'" class="drawer-plan text-left" :class="selectedPlan==='trail'?'active':''"><div class="flex items-end justify-between gap-4"><div><span class="text-xs font-bold uppercase tracking-widest opacity-45">Nivel 01</span><h3 class="mt-1 font-display text-2xl font-semibold">Trail Key</h3></div><strong class="font-display text-2xl">329 RON</strong></div><p class="mt-3 text-sm leading-6 opacity-55">4 vizite, acces anticipat cu 48 de ore și 10% reducere la activități.</p></button>
            <button type="button" @click="selectedPlan='wild'" class="drawer-plan text-left active" :class="selectedPlan==='wild'?'active':''"><div class="flex items-end justify-between gap-4"><div><span class="text-xs font-bold uppercase tracking-widest opacity-45">Nivel 02</span><h3 class="mt-1 font-display text-2xl font-semibold">Wild Circle</h3></div><strong class="font-display text-2xl">649 RON</strong></div><p class="mt-3 text-sm leading-6 opacity-55">Acces nelimitat în timpul săptămânii, 8 zile de weekend și 1 guest pass.</p></button>
            <button type="button" @click="selectedPlan='summit'" class="drawer-plan text-left" :class="selectedPlan==='summit'?'active':''"><div class="flex items-end justify-between gap-4"><div><span class="text-xs font-bold uppercase tracking-widest opacity-45">Nivel 03</span><h3 class="mt-1 font-display text-2xl font-semibold">Summit Society</h3></div><strong class="font-display text-2xl">1.090 RON</strong></div><p class="mt-3 text-sm leading-6 opacity-55">Acces nelimitat oricând, 4 guest passes, 30% reducere și concierge.</p></button>
          </div>

          <div class="mt-7"><p class="mb-3 text-sm font-bold">Titular</p><div class="grid gap-3 sm:grid-cols-2"><label><span class="mb-2 block text-xs font-bold uppercase tracking-widest opacity-45">Prenume</span><input type="text" x-model="holder.firstName" class="w-full rounded-2xl border bg-white px-4 py-3" style="border-color:var(--line)" placeholder="Andrei"></label><label><span class="mb-2 block text-xs font-bold uppercase tracking-widest opacity-45">Nume</span><input type="text" x-model="holder.lastName" class="w-full rounded-2xl border bg-white px-4 py-3" style="border-color:var(--line)" placeholder="Popescu"></label></div></div>

          <div class="mt-7"><p class="mb-3 text-sm font-bold">Opțiuni</p><div class="grid gap-3"><label class="checkbox-row"><input type="checkbox" x-model="physicalCard" class="mt-1"><span><strong class="block">Card fizic din material reciclat</strong><span class="text-sm opacity-50">+25 RON · cardul digital este oricum inclus</span></span></label><label class="checkbox-row"><input type="checkbox" x-model="giftMode" class="mt-1"><span><strong class="block">Este un cadou</strong><span class="text-sm opacity-50">Activează ulterior, în maximum 6 luni</span></span></label></div></div>

          <div class="mt-7 rounded-3xl p-5 text-white" style="background:var(--pine-950)"><div class="flex items-center justify-between gap-4"><span class="text-sm text-white text-opacity-55">Total</span><strong class="font-display text-4xl" x-text="drawerTotal + ' RON'">649 RON</strong></div><p class="mt-2 text-xs text-white text-opacity-35">Plată unică · valabilitate 12 luni de la activare</p><button type="button" @click="completeSelection()" class="btn-primary mt-5 w-full">Continuă spre checkout</button></div>
        </div>
      </aside>
    </div>
  </template>

  <div x-show="toast" x-cloak x-transition class="fixed bottom-6 left-1/2 z-[120] -translate-x-1/2 rounded-full px-5 py-3 text-sm font-bold text-white" style="background:var(--pine-900);box-shadow:0 20px 60px -24px rgba(6,26,21,.9)" x-text="toast"></div>

  <script>
    function membershipPage() {
      return {
        menuOpen:false,
        drawerOpen:false,
        toast:'',
        audience:'solo',
        visits:6,
        priority:'flexibility',
        adults:2,
        children:1,
        calcVisits:6,
        calculatorPlan:'wild',
        selectedPlan:'wild',
        physicalCard:false,
        giftMode:false,
        openFaq:0,
        holder:{ firstName:'', lastName:'' },
        audiences:[
          {id:'solo',label:'Doar eu'},
          {id:'couple',label:'Cuplu'},
          {id:'family',label:'Familie'},
          {id:'friends',label:'Grup de prieteni'}
        ],
        priorities:[
          {id:'price',label:'Preț minim'},
          {id:'flexibility',label:'Flexibilitate'},
          {id:'priority',label:'Acces prioritar'},
          {id:'community',label:'Evenimente private'}
        ],
        plans:[
          {id:'trail',level:'Nivel 01',short:'Trail',name:'Trail Key',price:329,drawerCopy:'4 vizite, acces anticipat cu 48 de ore și 10% reducere la activități.',mobileFeatures:['4 vizite incluse','10% reducere la experiențe','Acces anticipat cu 48 de ore']},
          {id:'wild',level:'Nivel 02',short:'Wild',name:'Wild Circle',price:649,drawerCopy:'Acces nelimitat în timpul săptămânii, 8 zile de weekend și 1 guest pass.',mobileFeatures:['Acces nelimitat luni—vineri','8 intrări de weekend','20% reducere și 1 guest pass']},
          {id:'summit',level:'Nivel 03',short:'Summit',name:'Summit Society',price:1090,drawerCopy:'Acces nelimitat oricând, 4 guest passes, 30% reducere și concierge.',mobileFeatures:['Acces nelimitat oricând','4 guest passes','30% reducere și concierge']}
        ],
        faqs:[
          {q:'Când începe valabilitatea abonamentului?',a:'La prima activare, nu la data cumpărării. Abonamentul poate fi activat în maximum 6 luni de la achiziție și rămâne valabil 12 luni.'},
          {q:'Este abonamentul nominal?',a:'Da. Cardul digital este nominal și include fotografia titularului după activare. Summit Society permite și guest passes, dar acestea nu transferă abonamentul.'},
          {q:'Trebuie să rezerv fiecare vizită?',a:'Da, pentru controlul capacității și siguranță. Membrii au acces la rezervări înaintea publicului, în funcție de nivel.'},
          {q:'Ce se întâmplă dacă parcul se închide din cauza vremii?',a:'Vizitele rezervate nu sunt consumate. Pentru nivelurile cu număr limitat de intrări, creditul revine automat în cont.'},
          {q:'Pot schimba titularul?',a:'Este permisă o singură schimbare de titular în perioada de valabilitate, înainte ca abonamentul să fi fost folosit de mai mult de două ori.'}
        ],
        init() {
          const serverPlans = <?php echo json_encode($injectedPlans, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          if (Array.isArray(serverPlans) && serverPlans.length) {
            this.plans = this.plans.map((p, i) => serverPlans[i] ? { ...p, ...serverPlans[i] } : p);
          }
          this.setupReveal();
          this.setupProgress();
          this.setupTilt();
        },
        get audienceLabel() {
          return this.audiences.find(a=>a.id===this.audience)?.label || '';
        },
        get recommendation() {
          let id='trail';
          if (this.visits>=9 || this.priority==='community' || this.priority==='priority') id='summit';
          else if (this.visits>=5 || this.audience==='family' || this.audience==='friends' || this.priority==='flexibility') id='wild';
          const plan=this.plans.find(p=>p.id===id);
          const text={
            trail:{reason:'Pentru un ritm de câteva reveniri planificate.',summary:'Plătești mai puțin decât pentru vizite separate și păstrezi libertatea de a alege datele.'},
            wild:{reason:'Echilibrul bun între frecvență și flexibilitate.',summary:'Ai suficient acces pentru vizite spontane, weekenduri și oaspete, fără costul nivelului premium.'},
            summit:{reason:'Pentru frecvență mare și acces fără compromisuri.',summary:'Are sens când prioritatea, evenimentele private și vizitele dese contează mai mult decât prețul minim.'}
          }[id];
          return {...plan,...text};
        },
        get selectedPlanData() {
          return this.plans.find(p=>p.id===this.selectedPlan) || this.plans[1];
        },
        get calculatorPlanData() {
          return this.plans.find(p=>p.id===this.calculatorPlan) || this.plans[1];
        },
        get estimatedPayAsYouGo() {
          return Math.round(this.calcVisits * ((this.adults*99)+(this.children*69)));
        },
        get membershipGroupCost() {
          const base=this.calculatorPlanData.price;
          const extraAdults=Math.max(0,this.adults-1)*Math.round(base*.72);
          const childRate=this.calculatorPlan==='trail'?189:this.calculatorPlan==='wild'?329:499;
          return base+extraAdults+(this.children*childRate);
        },
        get estimatedSavings() {
          return Math.max(0,this.estimatedPayAsYouGo-this.membershipGroupCost);
        },
        get savingsCopy() {
          if (this.estimatedSavings<=0) return 'Pentru acest ritm, biletele individuale pot rămâne mai potrivite.';
          return `Față de aproximativ ${this.estimatedPayAsYouGo} RON în vizite separate.`;
        },
        get breakEvenText() {
          const perVisit=(this.adults*99)+(this.children*69);
          const visits=Math.max(1,Math.ceil(this.membershipGroupCost/perVisit));
          return `Aproximativ ${visits} vizite pentru grupul configurat`;
        },
        get drawerTotal() {
          return this.selectedPlanData.price+(this.physicalCard?25:0);
        },
        scrollToFinder() {
          document.querySelector('#finder')?.scrollIntoView({behavior:'smooth'});
        },
        choosePlan(id) {
          this.selectedPlan=id;
          this.drawerOpen=true;
          this.menuOpen=false;
        },
        async completeSelection() {
          // Abonarea reală necesită autentificare + plan_slug (endpoint tenant-client subscribe).
          let auth=null;try{auth=JSON.parse(localStorage.getItem('nordvale_auth')||'null')}catch(e){}
          if(!auth||!auth.token){window.location.href='/autentificare?next=/abonamente';return;}
          const plan=this.selectedPlanData;
          if(plan&&plan.slug){
            try{
              const res=await fetch('/api/proxy.php?action=subscribe',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+auth.token},body:JSON.stringify({plan_slug:plan.slug,success_url:location.origin+'/cont',cancel_url:location.origin+'/abonamente'})});
              const data=await res.json().catch(()=>null);
              if(data&&data.success&&data.redirect_url){window.location.href=data.redirect_url;return;}
              this.drawerOpen=false;
              this.toast=(data&&data.error)?data.error:'Nu am putut iniția abonamentul. Flux demonstrativ.';
              setTimeout(()=>this.toast='',3600);
              return;
            }catch(e){}
          }
          this.drawerOpen=false;
          this.toast=`${this.selectedPlanData.name} a fost configurat. Flux demonstrativ.`;
          setTimeout(()=>this.toast='',3200);
        },
        setupReveal() {
          const items=[...document.querySelectorAll('.reveal')];
          if (!('IntersectionObserver' in window)) { items.forEach(el=>el.classList.add('is-visible'));return; }
          const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}}),{threshold:.12,rootMargin:'0px 0px -30px'});
          items.forEach(el=>observer.observe(el));
        },
        setupProgress() {
          const bar=document.querySelector('#scroll-progress');
          if(!bar) return;
          const update=()=>{const max=document.documentElement.scrollHeight-innerHeight;const p=max>0?scrollY/max:0;bar.style.transform=`scaleX(${Math.min(1,Math.max(0,p))})`;};
          addEventListener('scroll',update,{passive:true});update();
        },
        setupTilt() {
          if (matchMedia('(pointer:coarse)').matches || matchMedia('(prefers-reduced-motion:reduce)').matches) return;
          const area=document.querySelector('[data-tilt-area]');
          const cards=[...document.querySelectorAll('[data-card-tilt]')];
          if(!area || !cards.length) return;
          area.addEventListener('mousemove',e=>{
            const r=area.getBoundingClientRect();
            const x=(e.clientX-r.left)/r.width-.5;
            const y=(e.clientY-r.top)/r.height-.5;
            cards.forEach(card=>{
              const factor=Number(card.dataset.cardTilt||.4);
              card.style.transform=`rotate(${factor*10}deg) rotateX(${-y*7}deg) rotateY(${x*9}deg) translate3d(${x*factor*18}px,${y*factor*14}px,0)`;
            });
          });
          area.addEventListener('mouseleave',()=>cards.forEach(card=>card.style.transform=''));
        }
      };
    }
  </script>
</body>
</html>
