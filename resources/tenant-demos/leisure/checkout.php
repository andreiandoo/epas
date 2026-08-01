<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$paymentMethods = tc_payment_methods();
?>
<!DOCTYPE html>
<html lang="ro" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#061a15">
    <title>Checkout — Nordvale</title>
    <meta name="description" content="Checkout Tixello pentru tenantul leisure Nordvale.">

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
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    :root {
        --pine: #09251d;
        --pine-dark: #061a15;
        --acid: #dffc62;
        --ember: #f27b4a;
        --cream: #fffdf6;
        --oat: #f0ecdf;
        --ink: #16231e;
        --sky: #b8dce0;
    }
    * { box-sizing: border-box; }
    html { background: var(--oat); }
    body { margin: 0; overflow-x: hidden; background: var(--oat); color: var(--ink); font-family: 'DM Sans', sans-serif; text-rendering: optimizeLegibility; }
    [x-cloak] { display: none !important; }
    ::selection { background: var(--acid); color: var(--pine-dark); }
    button, a, input, select, textarea { -webkit-tap-highlight-color: transparent; }
    .safe-top { padding-top: max(12px, env(safe-area-inset-top)); }
    .safe-bottom { padding-bottom: max(18px, env(safe-area-inset-bottom)); }
    .grain::after { content:''; position:absolute; inset:0; z-index:2; pointer-events:none; opacity:.08; mix-blend-mode:soft-light; background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='.58'/%3E%3C/svg%3E"); }
    .topo-dark { background-image: radial-gradient(circle at 18% 22%, rgba(223,252,98,.14), transparent 20%), radial-gradient(circle at 84% 18%, rgba(242,123,74,.12), transparent 17%), url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='.055' stroke-width='1.1'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M119 231c31-83 127-143 219-107 76 30 95 118 53 180-40 58-121 56-160 115-42 61-28 159-107 197-77 37-165-25-154-110 10-83 94-98 102-174 4-44 0-74 47-101Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3Cpath d='M548 507c39-60 118-79 173-32 53 45 37 128-19 161-50 30-111 8-150 51-34 36-29 100-79 112-55 14-106-39-83-92 21-50 81-47 110-92 23-36 22-72 48-108Z'/%3E%3C/g%3E%3C/svg%3E"); background-size:auto,auto,820px 820px; }
    .paper-grid { background-image: linear-gradient(rgba(9,37,29,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(9,37,29,.05) 1px, transparent 1px); background-size:30px 30px; }
    .nav-shell { background:rgba(6,26,21,.82); border:1px solid rgba(255,255,255,.12); box-shadow:0 24px 70px -42px rgba(0,0,0,.9); backdrop-filter:blur(22px) saturate(130%); -webkit-backdrop-filter:blur(22px) saturate(130%); }
    .progress-node::after { content:''; position:absolute; top:50%; left:calc(100% + 12px); width:38px; height:1px; background:rgba(255,255,255,.18); }
    .progress-node:last-child::after { display:none; }
    .route-dash { fill:none; stroke:rgba(223,252,98,.65); stroke-width:2; stroke-linecap:round; stroke-dasharray:7 12; animation:routeMove 20s linear infinite; }
    @keyframes routeMove { to { stroke-dashoffset:-380; } }
    .reveal-ready [data-reveal] { opacity:0; transform:translateY(28px); }
    .checkout-shell { background:rgba(255,253,246,.88); border:1px solid rgba(9,37,29,.1); box-shadow:0 32px 90px -58px rgba(6,26,21,.75); }
    .section-card { background:var(--cream); border:1px solid rgba(9,37,29,.1); box-shadow:0 24px 70px -54px rgba(6,26,21,.6); }
    .form-label { display:block; margin-bottom:8px; font-size:10px; line-height:1.2; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:rgba(27,82,66,.72); }
    .form-control { width:100%; min-height:50px; border-radius:16px; border:1px solid rgba(9,37,29,.14); background:rgba(255,255,255,.78); padding:0 15px; color:var(--pine); outline:none; transition:border-color .2s ease, box-shadow .2s ease, background-color .2s ease; }
    textarea.form-control { min-height:110px; padding-top:14px; resize:vertical; }
    .form-control:focus { border-color:rgba(9,37,29,.48); background:white; box-shadow:0 0 0 4px rgba(223,252,98,.28); }
    .form-control.is-error { border-color:rgba(242,123,74,.8); box-shadow:0 0 0 4px rgba(242,123,74,.12); }
    .error-text { margin-top:6px; font-size:11px; color:#b94e29; }
    .option-card { border:1px solid rgba(9,37,29,.12); background:rgba(255,255,255,.64); transition:transform .22s ease,border-color .22s ease,background-color .22s ease,box-shadow .22s ease; }
    .option-card:hover { transform:translateY(-2px); border-color:rgba(9,37,29,.28); background:white; }
    .option-card.is-active { border-color:var(--pine); background:white; box-shadow:0 18px 45px -32px rgba(6,26,21,.65); }
    .check-mark { width:20px; height:20px; border-radius:999px; border:1px solid rgba(9,37,29,.25); display:grid; place-items:center; flex:none; }
    .option-card.is-active .check-mark { background:var(--acid); border-color:var(--acid); color:var(--pine-dark); }
    .participant-pass { position:relative; overflow:hidden; border:1px solid rgba(9,37,29,.11); background:linear-gradient(135deg,#fffdf6 0%,#f7f2e6 100%); }
    .participant-pass::after { content:''; position:absolute; right:-18px; top:50%; width:36px; height:36px; margin-top:-18px; border-radius:999px; background:var(--oat); border:1px solid rgba(9,37,29,.08); }
    .summary-panel { background:var(--pine-dark); color:white; box-shadow:0 38px 100px -56px rgba(6,26,21,.9); }
    .summary-row { display:flex; align-items:center; justify-content:space-between; gap:16px; font-size:14px; }
    .card-preview { position:relative; min-height:205px; overflow:hidden; border-radius:26px; background:linear-gradient(140deg,#123b30 0%,#061a15 68%); color:white; box-shadow:0 30px 65px -38px rgba(6,26,21,.8); }
    .card-preview::before { content:''; position:absolute; width:260px; height:260px; border:1px solid rgba(223,252,98,.16); border-radius:999px; right:-100px; top:-115px; }
    .card-preview::after { content:''; position:absolute; width:170px; height:170px; border:1px solid rgba(242,123,74,.16); border-radius:999px; left:-70px; bottom:-90px; }
    .card-chip { width:42px; height:32px; border-radius:8px; background:linear-gradient(135deg,#f1d98e,#bf9952); }
    .lock-badge { animation:lockPulse 2.5s ease-in-out infinite; }
    @keyframes lockPulse { 0%,100% { box-shadow:0 0 0 0 rgba(223,252,98,0); } 50% { box-shadow:0 0 0 8px rgba(223,252,98,.08); } }
    .mobile-summary-shadow { box-shadow:0 -22px 65px -42px rgba(6,26,21,.9); }
    .drawer-shadow { box-shadow:-50px 0 120px -60px rgba(6,26,21,.82); }
    .spinner { width:18px; height:18px; border:2px solid rgba(6,26,21,.25); border-top-color:var(--pine-dark); border-radius:999px; animation:spin .8s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }
    /* Small utility completion for classes not present in the retained Tailwind build */
    .bg-ember\/10 { background-color:rgba(242,123,74,.10); }
    .bg-oat\/60 { background-color:rgba(240,236,223,.60); }
    .bg-white\/12 { background-color:rgba(255,255,255,.12); }
    .bg-white\/70 { background-color:rgba(255,255,255,.70); }
    .border-ember\/30 { border-color:rgba(242,123,74,.30); }
    .cursor-pointer { cursor:pointer; }
    .flex-col-reverse { flex-direction:column-reverse; }
    .gap-6 { gap:1.5rem; }
    .inset-y-0 { top:0; bottom:0; }
    .right-0 { right:0; }
    .max-w-md { max-width:28rem; }
    .max-w-\[170px\] { max-width:170px; }
    .mb-6 { margin-bottom:1.5rem; }
    .mb-9 { margin-bottom:2.25rem; }
    .mt-0\.5 { margin-top:.125rem; }
    .my-5 { margin-top:1.25rem; margin-bottom:1.25rem; }
    .pb-4 { padding-bottom:1rem; }
    .pb-14 { padding-bottom:3.5rem; }
    .rounded-\[14px\] { border-radius:14px; }
    .space-y-4 > :not([hidden]) ~ :not([hidden]) { margin-top:1rem; }
    .text-\[clamp\(3rem\,8vw\,6\.7rem\)\] { font-size:clamp(3rem,8vw,6.7rem); }
    .text-pine-700\/65 { color:rgba(27,82,66,.65); }
    .underline { text-decoration-line:underline; }
    .disabled\:opacity-55:disabled { opacity:.55; }
    input[type=checkbox] { accent-color:#09251d; }
    @media (min-width:40rem) {
        .sm\:col-span-2 { grid-column:span 2 / span 2; }
        .sm\:grid-cols-\[42px_minmax\(0\,1fr\)_140px\] { grid-template-columns:42px minmax(0,1fr) 140px; }
        .sm\:mb-10 { margin-bottom:2.5rem; }
        .sm\:pb-18 { padding-bottom:4.5rem; }
        .sm\:py-14 { padding-top:3.5rem; padding-bottom:3.5rem; }
    }
    @media (min-width:64rem) {
        .lg\:grid-cols-\[minmax\(0\,1fr\)_300px\] { grid-template-columns:minmax(0,1fr) 300px; }
        .lg\:pb-20 { padding-bottom:5rem; }
        .lg\:py-18 { padding-top:4.5rem; padding-bottom:4.5rem; }
    }

    @media (max-width:639px) {
        .progress-node::after { width:16px; left:calc(100% + 6px); }
        .checkout-shell,.section-card { border-radius:24px; }
        .participant-pass::after { display:none; }
        .card-preview { min-height:184px; }
    }
    @media (prefers-reduced-motion:reduce) {
        html { scroll-behavior:auto; }
        *,*::before,*::after { animation-duration:.01ms !important; animation-iteration-count:1 !important; transition-duration:.01ms !important; }
        .reveal-ready [data-reveal] { opacity:1; transform:none; }
    }
</style>
<script>document.documentElement.classList.add('reveal-ready');</script>
</head>

<body x-data="checkoutPage()" x-init="init()" class="antialiased pb-28 lg:pb-0">
<?php $nvNav=''; $nvNoSpacer=true; include __DIR__ . '/includes/header.php'; ?>

<main>
<section class="relative overflow-hidden bg-pine-950 pb-14 pt-28 text-white topo-dark grain sm:pb-18 sm:pt-32 lg:pb-20 lg:pt-36">
  <svg class="pointer-events-none absolute -right-24 top-20 h-[420px] w-[660px] opacity-70" viewBox="0 0 660 420" fill="none" aria-hidden="true"><path class="route-dash" d="M22 322C115 266 117 141 219 128c103-14 123 93 221 77 84-13 104-106 196-154"/><circle cx="219" cy="128" r="5" fill="#dffc62"/><circle cx="440" cy="205" r="5" fill="#f27b4a"/></svg>
  <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mb-9 flex items-center gap-2 overflow-x-auto pb-1 text-[10px] font-bold uppercase tracking-[.18em] text-white/48 sm:mb-10 sm:justify-end sm:gap-5 sm:text-xs">
      <div class="progress-node relative flex flex-none items-center gap-2 text-white/72"><span class="grid h-6 w-6 place-items-center rounded-full bg-white/12 text-[10px] text-acid">✓</span><span>Coș</span></div>
      <div class="progress-node relative flex flex-none items-center gap-2" :class="internalStep === 1 ? 'text-acid' : 'text-white/72'"><span class="grid h-6 w-6 place-items-center rounded-full" :class="internalStep === 1 ? 'bg-acid text-pine-950' : 'bg-white/12 text-acid'" x-text="internalStep > 1 ? '✓' : '2'"></span><span>Date</span></div>
      <div class="progress-node relative flex flex-none items-center gap-2" :class="internalStep === 2 ? 'text-acid' : ''"><span class="grid h-6 w-6 place-items-center rounded-full" :class="internalStep === 2 ? 'bg-acid text-pine-950' : 'border border-white/20'">3</span><span>Plată</span></div>
      <div class="progress-node relative flex flex-none items-center gap-2"><span class="grid h-6 w-6 place-items-center rounded-full border border-white/20">4</span><span>Gata</span></div>
    </div>
    <div class="grid items-end gap-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-14">
      <div data-reveal>
        <p class="mb-4 text-[11px] font-bold uppercase tracking-[.28em] text-acid sm:text-xs">Checkout securizat · rezervare activă</p>
        <h1 class="max-w-4xl font-display text-[clamp(3rem,8vw,6.7rem)] font-semibold leading-[.88] tracking-[-.055em]">Ultimele detalii.<br><span class="text-white/42">Apoi începe traseul.</span></h1>
        <p class="mt-6 max-w-2xl text-base leading-7 text-white/66 sm:text-lg sm:leading-8">Completează datele pentru bilete și finalizează plata. Rezervarea rămâne blocată cât timp ești în acest flux.</p>
      </div>
      <div data-reveal class="rounded-[26px] border border-white/12 bg-white/[.065] p-4 backdrop-blur-sm sm:p-5">
        <div class="flex items-center gap-4"><span class="grid h-12 w-12 flex-none place-items-center rounded-2xl bg-acid text-pine-950"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"><path d="M7 10V7a5 5 0 0 1 10 0v3M6 10h12v10H6V10Z" stroke="currentColor" stroke-width="1.8"/><path d="M12 14v2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span><div><p class="text-[10px] font-bold uppercase tracking-[.2em] text-white/38">Conexiune protejată</p><p class="mt-1 font-display text-xl">Datele de plată nu sunt stocate</p><p class="mt-1 text-xs leading-5 text-white/50">Procesare criptată prin partenerul de plăți.</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="paper-grid py-10 sm:py-14 lg:py-18">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid min-w-0 gap-8 lg:grid-cols-[minmax(0,1fr)_360px] xl:gap-12">
      <div class="min-w-0">
        <div x-show="internalStep === 1" x-transition.opacity>
          <div class="mb-6 flex items-end justify-between gap-4" data-reveal><div><p class="text-[10px] font-bold uppercase tracking-[.23em] text-pine-600">Pasul 1 din 2</p><h2 class="mt-2 font-display text-3xl font-semibold tracking-[-.03em] sm:text-4xl">Cine merge în expediție?</h2></div><button type="button" @click="fillDemo()" class="hidden rounded-full border border-pine-900/16 px-4 py-2 text-xs font-bold text-pine-900 sm:inline-flex">Completează demo</button></div>

          <section class="section-card rounded-[28px] p-5 sm:p-7" data-reveal>
            <div class="flex items-start gap-4"><span class="grid h-11 w-11 flex-none place-items-center rounded-2xl bg-acid text-pine-950"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span><div><h3 class="font-display text-2xl font-semibold">Date de contact</h3><p class="mt-1 text-sm leading-6 text-pine-700/55">Aici trimitem confirmarea, biletele QR și eventualele actualizări meteo.</p></div></div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
              <div><label class="form-label" for="firstName">Prenume</label><input id="firstName" x-model.trim="form.firstName" @blur="touch('firstName')" :class="hasError('firstName') ? 'is-error' : ''" class="form-control" autocomplete="given-name" placeholder="Andrei"><p x-show="hasError('firstName')" x-cloak class="error-text">Introdu prenumele.</p></div>
              <div><label class="form-label" for="lastName">Nume</label><input id="lastName" x-model.trim="form.lastName" @blur="touch('lastName')" :class="hasError('lastName') ? 'is-error' : ''" class="form-control" autocomplete="family-name" placeholder="Popescu"><p x-show="hasError('lastName')" x-cloak class="error-text">Introdu numele.</p></div>
              <div><label class="form-label" for="email">Email</label><input id="email" type="email" x-model.trim="form.email" @blur="touch('email')" :class="hasError('email') ? 'is-error' : ''" class="form-control" autocomplete="email" placeholder="andrei@email.ro"><p x-show="hasError('email')" x-cloak class="error-text">Introdu un email valid.</p></div>
              <div><label class="form-label" for="phone">Telefon</label><input id="phone" type="tel" x-model.trim="form.phone" @blur="touch('phone')" :class="hasError('phone') ? 'is-error' : ''" class="form-control" autocomplete="tel" placeholder="07xx xxx xxx"><p x-show="hasError('phone')" x-cloak class="error-text">Introdu un număr de telefon.</p></div>
            </div>
            <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-[18px] bg-oat/60 p-4"><input type="checkbox" x-model="form.createAccount" class="mt-1 h-4 w-4 flex-none"><span><span class="block text-sm font-semibold text-pine-900">Creează automat un cont Nordvale</span><span class="mt-1 block text-xs leading-5 text-pine-700/55">Vei putea vedea biletele, comenzile și modificările într-un singur loc.</span></span></label>
          </section>

          <section class="section-card mt-5 rounded-[28px] p-5 sm:p-7" data-reveal>
            <div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-bold uppercase tracking-[.2em] text-pine-600">Beneficiari bilete</p><h3 class="mt-2 font-display text-2xl font-semibold">Participanți</h3><p class="mt-1 text-sm leading-6 text-pine-700/55">Numele poate fi modificat ulterior din cont, înainte de vizită.</p></div><span class="rounded-full bg-pine-900 px-3 py-1.5 text-xs font-bold text-white">3 persoane</span></div>
            <div class="mt-5 space-y-3">
              <template x-for="(person,index) in participants" :key="person.id"><div class="participant-pass rounded-[20px] p-4"><div class="grid gap-3 sm:grid-cols-[42px_minmax(0,1fr)_140px] sm:items-center"><span class="grid h-10 w-10 place-items-center rounded-full bg-pine-900 font-display text-lg text-acid" x-text="index + 1"></span><div><label class="form-label" :for="`person-${person.id}`" x-text="person.type"></label><input :id="`person-${person.id}`" x-model.trim="person.name" class="form-control" :placeholder="index === 0 ? 'Titular comandă' : 'Nume participant'"></div><div class="rounded-[14px] bg-white/70 px-3 py-3 text-xs"><span class="block font-bold text-pine-900" x-text="person.access"></span><span class="mt-1 block text-pine-700/55" x-text="person.note"></span></div></div></div></template>
            </div>
          </section>

          <section class="section-card mt-5 rounded-[28px] p-5 sm:p-7" data-reveal>
            <div class="flex items-start gap-4"><span class="grid h-11 w-11 flex-none place-items-center rounded-2xl bg-sky text-pine-950"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M4 8h16v11H4V8Zm3-4h10v4H7V4Z" stroke="currentColor" stroke-width="1.7"/><path d="M8 12h8M8 15h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span><div><h3 class="font-display text-2xl font-semibold">Facturare</h3><p class="mt-1 text-sm leading-6 text-pine-700/55">Factura simplă este emisă automat. Poți solicita date de companie.</p></div></div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
              <button type="button" @click="form.invoiceType='person'" class="option-card rounded-[20px] p-4 text-left" :class="form.invoiceType==='person' ? 'is-active' : ''"><div class="flex items-start gap-3"><span class="check-mark"><span x-show="form.invoiceType==='person'">✓</span></span><div><p class="font-semibold text-pine-900">Persoană fizică</p><p class="mt-1 text-xs leading-5 text-pine-700/55">Factura folosește datele titularului.</p></div></div></button>
              <button type="button" @click="form.invoiceType='company'" class="option-card rounded-[20px] p-4 text-left" :class="form.invoiceType==='company' ? 'is-active' : ''"><div class="flex items-start gap-3"><span class="check-mark"><span x-show="form.invoiceType==='company'">✓</span></span><div><p class="font-semibold text-pine-900">Companie</p><p class="mt-1 text-xs leading-5 text-pine-700/55">CUI, denumire și adresă fiscală.</p></div></div></button>
            </div>
            <div x-show="form.invoiceType==='company'" x-cloak x-transition.opacity class="mt-4 grid gap-4 sm:grid-cols-2"><div><label class="form-label">Denumire companie</label><input x-model.trim="form.company" class="form-control" placeholder="Nord Trail SRL"></div><div><label class="form-label">CUI</label><input x-model.trim="form.vat" class="form-control" placeholder="RO12345678"></div><div class="sm:col-span-2"><label class="form-label">Adresă fiscală</label><input x-model.trim="form.address" class="form-control" placeholder="Stradă, număr, localitate, județ"></div></div>
          </section>

          <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between" data-reveal><a href="/cos" class="inline-flex items-center justify-center rounded-full border border-pine-900/16 px-6 py-3.5 text-sm font-bold text-pine-900">Înapoi la coș</a><button type="button" @click="goPayment()" class="inline-flex items-center justify-center gap-2 rounded-full bg-pine-900 px-6 py-3.5 text-sm font-bold text-white shadow-card transition hover:bg-pine-800">Continuă la plată<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div>
        </div>

        <div x-show="internalStep === 2" x-cloak x-transition.opacity>
          <div class="mb-6" data-reveal><p class="text-[10px] font-bold uppercase tracking-[.23em] text-pine-600">Pasul 2 din 2</p><h2 class="mt-2 font-display text-3xl font-semibold tracking-[-.03em] sm:text-4xl">Alege metoda de plată</h2><p class="mt-2 text-sm leading-6 text-pine-700/55">Plata este procesată securizat. Nordvale nu stochează datele cardului.</p></div>

          <section class="section-card rounded-[28px] p-5 sm:p-7" data-reveal>
            <div class="grid gap-3 sm:grid-cols-3">
              <button type="button" @click="paymentMethod='card'" class="option-card rounded-[20px] p-4 text-left" :class="paymentMethod==='card' ? 'is-active' : ''"><div class="flex items-center gap-3"><span class="check-mark"><span x-show="paymentMethod==='card'">✓</span></span><div><p class="font-semibold text-pine-900">Card bancar</p><p class="mt-1 text-[11px] text-pine-700/55">Visa · Mastercard</p></div></div></button>
              <button type="button" @click="paymentMethod='wallet'" class="option-card rounded-[20px] p-4 text-left" :class="paymentMethod==='wallet' ? 'is-active' : ''"><div class="flex items-center gap-3"><span class="check-mark"><span x-show="paymentMethod==='wallet'">✓</span></span><div><p class="font-semibold text-pine-900">Wallet</p><p class="mt-1 text-[11px] text-pine-700/55">Apple / Google Pay</p></div></div></button>
              <button type="button" @click="paymentMethod='transfer'" class="option-card rounded-[20px] p-4 text-left" :class="paymentMethod==='transfer' ? 'is-active' : ''"><div class="flex items-center gap-3"><span class="check-mark"><span x-show="paymentMethod==='transfer'">✓</span></span><div><p class="font-semibold text-pine-900">Transfer</p><p class="mt-1 text-[11px] text-pine-700/55">Doar grupuri / firme</p></div></div></button>
            </div>

            <div x-show="paymentMethod==='card'" class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
              <div class="grid gap-4 sm:grid-cols-2"><div class="sm:col-span-2"><label class="form-label">Număr card</label><input x-model="card.number" @input="formatCard()" inputmode="numeric" autocomplete="cc-number" maxlength="19" class="form-control" placeholder="4242 4242 4242 4242"></div><div><label class="form-label">Expiră</label><input x-model="card.expiry" @input="formatExpiry()" inputmode="numeric" autocomplete="cc-exp" maxlength="5" class="form-control" placeholder="MM/AA"></div><div><label class="form-label">CVC</label><input x-model="card.cvc" inputmode="numeric" autocomplete="cc-csc" maxlength="4" class="form-control" placeholder="123"></div><div class="sm:col-span-2"><label class="form-label">Numele de pe card</label><input x-model.trim="card.name" autocomplete="cc-name" class="form-control" placeholder="ANDREI POPESCU"></div></div>
              <div class="card-preview p-5"><div class="relative z-10 flex h-full flex-col justify-between"><div class="flex items-start justify-between"><div class="card-chip"></div><span class="text-[9px] font-bold uppercase tracking-[.2em] text-white/45">Nordvale secure</span></div><div><p class="font-display text-xl tracking-[.08em]" x-text="maskedCard"></p><div class="mt-5 flex items-end justify-between gap-4"><div><p class="text-[8px] font-bold uppercase tracking-[.15em] text-white/35">Titular</p><p class="mt-1 max-w-[170px] truncate text-xs font-semibold" x-text="card.name || 'NUME TITULAR'"></p></div><div class="text-right"><p class="text-[8px] font-bold uppercase tracking-[.15em] text-white/35">Expiră</p><p class="mt-1 text-xs font-semibold" x-text="card.expiry || 'MM/AA'"></p></div></div></div></div></div>
            </div>
            <div x-show="paymentMethod==='wallet'" x-cloak class="mt-6 rounded-[22px] bg-pine-950 p-5 text-white"><div class="flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-white text-pine-950"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"><path d="M5 7h14v10H5V7Z" stroke="currentColor" stroke-width="1.7"/><path d="M8 11h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span><div><p class="font-display text-xl">Confirmi în wallet-ul dispozitivului</p><p class="mt-1 text-xs leading-5 text-white/55">Butonul final va deschide Apple Pay sau Google Pay, dacă este disponibil.</p></div></div></div>
            <div x-show="paymentMethod==='transfer'" x-cloak class="mt-6 rounded-[22px] border border-ember/30 bg-ember/10 p-5"><p class="font-semibold text-pine-900">Rezervare condiționată de confirmare</p><p class="mt-2 text-xs leading-5 text-pine-700/60">Pentru transfer bancar, comanda rămâne în așteptare maximum 24 de ore. Opțiunea este recomandată grupurilor și companiilor.</p></div>
          </section>

          <section class="section-card mt-5 rounded-[28px] p-5 sm:p-7" data-reveal><div class="flex items-start gap-4"><span class="grid h-11 w-11 flex-none place-items-center rounded-2xl bg-ember text-white"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M12 3 4.5 6v5.5c0 4.8 3.1 8.1 7.5 9.5 4.4-1.4 7.5-4.7 7.5-9.5V6L12 3Z" stroke="currentColor" stroke-width="1.7"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span><div><h3 class="font-display text-2xl font-semibold">Verificare finală</h3><p class="mt-1 text-sm leading-6 text-pine-700/55">Prin finalizare confirmi regulile de acces și politica de anulare.</p></div></div><div class="mt-5 space-y-3"><label class="flex cursor-pointer items-start gap-3 rounded-[18px] bg-oat/60 p-4"><input type="checkbox" x-model="acceptTerms" class="mt-1 h-4 w-4 flex-none"><span class="text-xs leading-5 text-pine-700/65">Am citit și accept <a href="#" class="font-bold text-pine-900 underline">Termenii și condițiile</a>, regulamentul parcului și politica de confidențialitate.</span></label><label class="flex cursor-pointer items-start gap-3 rounded-[18px] bg-oat/60 p-4"><input type="checkbox" x-model="marketing" class="mt-1 h-4 w-4 flex-none"><span class="text-xs leading-5 text-pine-700/65">Vreau să primesc rar recomandări și acces anticipat la experiențe noi.</span></label></div><p x-show="termsError" x-cloak class="error-text mt-3">Acceptarea termenilor este obligatorie.</p></section>

          <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between"><button type="button" @click="internalStep=1; window.scrollTo({top:260,behavior:'smooth'})" class="inline-flex items-center justify-center rounded-full border border-pine-900/16 px-6 py-3.5 text-sm font-bold text-pine-900">Modifică datele</button><button type="button" @click="pay()" :disabled="processing" class="inline-flex items-center justify-center gap-2 rounded-full bg-acid px-6 py-3.5 text-sm font-bold text-pine-950 shadow-acid transition hover:-translate-y-0.5 disabled:opacity-55"><span x-show="processing" class="spinner"></span><span x-text="processing ? 'Se procesează...' : paymentLabel"></span><svg x-show="!processing" class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div>
        </div>
      </div>

      <aside class="hidden lg:block" data-reveal><div class="summary-panel sticky top-24 overflow-hidden rounded-[30px]"><div class="relative overflow-hidden border-b border-white/10 p-7 topo-dark"><p class="relative z-10 text-[10px] font-bold uppercase tracking-[.22em] text-acid">Folio de călătorie</p><h2 class="relative z-10 mt-2 font-display text-3xl font-semibold">14 august · 3 persoane</h2><p class="relative z-10 mt-3 text-sm leading-6 text-white/55">Explorer Pass + Canopy Run, acces și suplimente incluse.</p></div><div class="p-7"><div class="space-y-4"><div class="rounded-[18px] bg-white/[.055] p-4"><div class="flex gap-3"><img src="https://images.unsplash.com/photo-1528654191889-1e7d7115c4f6?auto=format&fit=crop&w=300&q=80" alt="Canopy Run" class="h-16 w-16 flex-none rounded-xl object-cover"><div class="min-w-0"><p class="font-semibold">Adventure Pass</p><p class="mt-1 text-xs text-white/45">2 adulți · 1 copil</p><p class="mt-2 text-xs font-bold text-acid">420 lei</p></div></div></div><div class="rounded-[18px] bg-white/[.055] p-4"><p class="font-semibold">Canopy Run</p><p class="mt-1 text-xs text-white/45">14:30 · 3 participanți</p><p class="mt-2 text-xs font-bold text-acid">180 lei</p></div></div><div class="my-6 border-t border-dashed border-white/16"></div><div class="space-y-3"><div class="summary-row"><span class="text-white/55">Produse</span><span>600 lei</span></div><div class="summary-row"><span class="text-white/55">Suplimente</span><span>48 lei</span></div><div class="summary-row"><span class="text-white/55">Taxă procesare</span><span>12 lei</span></div></div><div class="my-6 border-t border-dashed border-white/16"></div><div class="flex items-end justify-between gap-4"><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-white/38">Total</p><p class="mt-1 font-display text-4xl font-semibold text-acid" x-text="cart.items.length ? money(cartTotal) : '660 lei'">660 lei</p></div><span class="mb-1 rounded-full bg-white/8 px-3 py-1 text-[9px] font-bold uppercase tracking-[.14em] text-white/50">TVA inclus</span></div><button type="button" @click="summaryOpen=true" class="mt-5 w-full rounded-full border border-white/15 px-4 py-3 text-xs font-bold text-white/72 transition hover:border-white/35 hover:text-white">Vezi toate detaliile</button><div class="mt-5 grid grid-cols-3 gap-2 text-center text-[9px] font-bold uppercase tracking-[.1em] text-white/35"><div class="rounded-xl bg-white/[.045] p-2.5">SSL</div><div class="rounded-xl bg-white/[.045] p-2.5">3D Secure</div><div class="rounded-xl bg-white/[.045] p-2.5">Instant QR</div></div></div></div></aside>
    </div>
  </div>
</section>

<section class="overflow-hidden bg-cream py-12 sm:py-16"><div class="mx-auto grid max-w-7xl gap-4 px-4 sm:grid-cols-3 sm:px-6 lg:px-8"><div class="rounded-[24px] border border-pine-900/9 bg-oat/55 p-5" data-reveal><span class="grid h-10 w-10 place-items-center rounded-2xl bg-acid text-pine-950"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></span><h3 class="mt-4 font-display text-xl font-semibold">Bilete instant</h3><p class="mt-2 text-xs leading-5 text-pine-700/55">Confirmarea și codurile QR sunt trimise imediat după autorizarea plății.</p></div><div class="rounded-[24px] border border-pine-900/9 bg-oat/55 p-5" data-reveal><span class="grid h-10 w-10 place-items-center rounded-2xl bg-sky text-pine-950"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M12 3v18M5.5 7.5 12 3l6.5 4.5M5.5 16.5 12 21l6.5-4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span><h3 class="mt-4 font-display text-xl font-semibold">Flexibilitate meteo</h3><p class="mt-2 text-xs leading-5 text-pine-700/55">Dacă o activitate este suspendată, primești reprogramare sau credit integral.</p></div><div class="rounded-[24px] border border-pine-900/9 bg-oat/55 p-5" data-reveal><span class="grid h-10 w-10 place-items-center rounded-2xl bg-ember text-white"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M4 8h16v10H4V8Zm4-4h8v4H8V4Z" stroke="currentColor" stroke-width="1.7"/><path d="M8 12h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span><h3 class="mt-4 font-display text-xl font-semibold">Suport local</h3><p class="mt-2 text-xs leading-5 text-pine-700/55">Echipa Nordvale poate corecta rapid nume, emailuri și detalii de acces.</p></div></div></section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Mobile commercial bar -->
<div class="mobile-summary-shadow fixed inset-x-0 bottom-0 z-40 border-t border-pine-900/10 bg-cream/95 px-3 pt-3 backdrop-blur-xl safe-bottom lg:hidden"><div class="mx-auto flex max-w-xl items-center gap-3"><button type="button" @click="summaryOpen=true" class="min-w-0 flex-1 text-left"><p class="text-[9px] font-bold uppercase tracking-[.15em] text-pine-600" x-text="totalTickets ? ('Total · ' + totalTickets + (totalTickets === 1 ? ' bilet' : ' bilete')) : 'Total'">Total · 3 persoane</p><p class="mt-0.5 font-display text-2xl font-semibold text-pine-900" x-text="cart.items.length ? money(cartTotal) : '660 lei'">660 lei</p></button><button type="button" @click="mobilePrimary()" :disabled="processing" class="inline-flex flex-none items-center justify-center gap-2 whitespace-nowrap rounded-full bg-pine-900 px-5 py-3.5 text-sm font-bold text-white"><span x-show="processing" class="spinner" style="border-color:rgba(255,255,255,.25);border-top-color:#fff"></span><span x-text="internalStep === 1 ? 'Continuă' : (processing ? 'Se procesează' : 'Plătește')"></span></button></div></div>

<!-- Summary drawer -->
<div x-show="summaryOpen" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true"><div x-show="summaryOpen" x-transition.opacity class="absolute inset-0 bg-pine-950/72 backdrop-blur-sm" @click="summaryOpen=false"></div><aside x-show="summaryOpen" x-transition:enter="transition duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="drawer-shadow absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-cream"><div class="safe-top flex items-center justify-between border-b border-pine-900/10 px-5 pb-4 pt-5"><div><p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-600">Comanda ta</p><h2 class="mt-1 font-display text-2xl font-semibold">14 august · Nordvale</h2></div><button type="button" @click="summaryOpen=false" class="grid h-10 w-10 place-items-center rounded-full border border-pine-900/12"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button></div><div class="flex-1 overflow-y-auto p-5"><div class="space-y-3"><div class="rounded-[20px] border border-pine-900/10 bg-white p-4"><p class="font-semibold">Adventure Pass</p><p class="mt-1 text-xs text-pine-700/55">2 adulți · 1 copil</p><p class="mt-3 font-bold text-pine-900">420 lei</p></div><div class="rounded-[20px] border border-pine-900/10 bg-white p-4"><p class="font-semibold">Canopy Run</p><p class="mt-1 text-xs text-pine-700/55">14:30 · 3 participanți</p><p class="mt-3 font-bold text-pine-900">180 lei</p></div><div class="rounded-[20px] border border-pine-900/10 bg-white p-4"><p class="font-semibold">Suplimente</p><p class="mt-1 text-xs text-pine-700/55">Parcare + pachet foto</p><p class="mt-3 font-bold text-pine-900">48 lei</p></div></div><div class="my-5 border-t border-dashed border-pine-900/18"></div><div class="space-y-3 text-sm"><div class="flex justify-between"><span class="text-pine-700/55">Subtotal</span><span>648 lei</span></div><div class="flex justify-between"><span class="text-pine-700/55">Taxă procesare</span><span>12 lei</span></div><div class="flex items-end justify-between"><span class="font-semibold">Total</span><span class="font-display text-3xl font-semibold text-pine-900">660 lei</span></div></div></div><div class="border-t border-pine-900/10 p-5 safe-bottom"><button type="button" @click="summaryOpen=false" class="w-full rounded-full bg-pine-900 px-5 py-4 text-sm font-bold text-white">Închide sumarul</button></div></aside></div>

<script>
function checkoutPage(){
  return {
    internalStep:1, summaryOpen:false, processing:false, paymentMethod:'card', acceptTerms:false, marketing:false, termsError:false,
    touched:{},
    paymentMethodsData: <?php echo json_encode($paymentMethods, JSON_UNESCAPED_UNICODE); ?>,
    cart:{event:{},items:[],subtotal:0},
    serviceFee:0,
    form:{firstName:'',lastName:'',email:'',phone:'',createAccount:true,invoiceType:'person',company:'',vat:'',address:''},
    participants:[
      {id:1,type:'Adult · titular',name:'',access:'Adventure Pass',note:'Acces complet'},
      {id:2,type:'Adult',name:'',access:'Adventure Pass',note:'Acces complet'},
      {id:3,type:'Copil 8–13 ani',name:'',access:'Adventure Pass',note:'Acces junior'}
    ],
    card:{number:'',expiry:'',cvc:'',name:''},
    money(v){return new Intl.NumberFormat('ro-RO',{style:'currency',currency:'RON',maximumFractionDigits:0}).format(v||0);},
    loadCart(){
      let parsed=null;
      try{const raw=localStorage.getItem('nordvale_cart'); if(raw) parsed=JSON.parse(raw);}catch(e){}
      if(parsed && Array.isArray(parsed.items) && parsed.items.length){
        this.cart.event = parsed.event || {};
        this.cart.items = parsed.items.map(it=>({
          ticket_type_id: it.ticket_type_id ?? null,
          title: it.title || '',
          qty: parseInt(it.qty ?? it.quantity ?? 1,10)||1,
          unit_price: parseFloat(it.unit_price ?? it.price ?? 0)||0
        }));
        this.cart.subtotal = this.cart.items.reduce((s,it)=>s+it.qty*it.unit_price,0);
        this.serviceFee = 9;
      } else {
        this.cart={event:{},items:[],subtotal:0}; this.serviceFee=0;
      }
    },
    loadAuth(){
      try{
        const a=JSON.parse(localStorage.getItem('nordvale_auth')||'null');
        if(a && a.user){
          const u=a.user;
          const parts=(u.name||'').trim().split(/\s+/);
          this.form.firstName = u.first_name || parts[0] || '';
          this.form.lastName  = u.last_name  || parts.slice(1).join(' ') || '';
          this.form.email     = u.email || '';
        }
      }catch(e){}
    },
    get cartTotal(){return this.cart.subtotal + this.serviceFee;},
    get totalTickets(){return this.cart.items.reduce((s,it)=>s+it.qty,0);},
    init(){
      this.loadCart();
      this.loadAuth();
      const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if(!reduced){
        requestAnimationFrame(()=>{
          document.querySelectorAll('[data-reveal]').forEach((el,i)=>{
            const obs=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.animate([{opacity:0,transform:'translateY(28px)'},{opacity:1,transform:'translateY(0)'}],{duration:650,delay:Math.min(i*45,240),easing:'cubic-bezier(.18,.8,.22,1)',fill:'forwards'});obs.unobserve(entry.target);}}),{threshold:.1});obs.observe(el);
          });
        });
      } else { document.documentElement.classList.remove('reveal-ready'); }
    },
    touch(field){this.touched[field]=true;},
    validEmail(){return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email);},
    hasError(field){if(!this.touched[field]) return false; if(field==='email') return !this.validEmail(); return !String(this.form[field]||'').trim();},
    validateContact(){['firstName','lastName','email','phone'].forEach(f=>this.touched[f]=true); return this.form.firstName && this.form.lastName && this.validEmail() && this.form.phone;},
    fillDemo(){this.form.firstName='Andrei';this.form.lastName='Popescu';this.form.email='andrei@example.ro';this.form.phone='0722 123 456';this.participants[0].name='Andrei Popescu';this.participants[1].name='Mara Popescu';this.participants[2].name='Luca Popescu';},
    goPayment(){if(!this.validateContact()){document.getElementById('firstName')?.focus();return;} if(!this.participants[0].name) this.participants[0].name=`${this.form.firstName} ${this.form.lastName}`; this.internalStep=2; window.scrollTo({top:260,behavior:'smooth'});},
    formatCard(){let v=this.card.number.replace(/\D/g,'').slice(0,16);this.card.number=v.replace(/(.{4})/g,'$1 ').trim();},
    formatExpiry(){let v=this.card.expiry.replace(/\D/g,'').slice(0,4);if(v.length>2)v=v.slice(0,2)+'/'+v.slice(2);this.card.expiry=v;},
    get maskedCard(){const digits=this.card.number.replace(/\s/g,''); if(!digits) return '••••  ••••  ••••  4242'; return this.card.number.padEnd(19,'•');},
    get paymentLabel(){if(this.paymentMethod==='wallet')return 'Continuă în wallet';if(this.paymentMethod==='transfer')return 'Trimite comanda';return 'Plătește '+this.money(this.cartTotal);},
    mobilePrimary(){if(this.internalStep===1)this.goPayment();else this.pay();},
    async pay(){
      this.termsError=!this.acceptTerms;if(this.termsError)return;
      if(this.paymentMethod==='card' && (this.card.number.replace(/\s/g,'').length<16 || this.card.expiry.length<5 || this.card.cvc.length<3 || !this.card.name)){alert('Completează datele cardului pentru demonstrație.');return;}
      if(!this.cart.items.length){alert('Coșul este gol.');window.location.href='/cos';return;}
      if(!this.validateContact()){this.internalStep=1;window.scrollTo({top:260,behavior:'smooth'});return;}
      this.processing=true;
      const origin=window.location.origin;
      const payload={
        event_id: this.cart.event && this.cart.event.id ? this.cart.event.id : null,
        customer:{
          first_name: this.form.firstName,
          last_name: this.form.lastName,
          email: this.form.email,
          phone: this.form.phone
        },
        items: this.cart.items
          .filter(it=>it.ticket_type_id)
          .map(it=>({ticket_type_id: it.ticket_type_id, quantity: it.qty, price: it.unit_price})),
        payment_method: this.paymentMethod==='card' ? 'card' : this.paymentMethod,
        create_account: !!this.form.createAccount,
        newsletter: !!this.marketing,
        success_url: origin+'/confirmare',
        cancel_url: origin+'/cos'
      };
      if(!payload.event_id || !payload.items.length){this.processing=false;alert('Coșul nu conține date valide. Reia selecția.');window.location.href='/cos';return;}
      try{
        const res=await fetch('/api/proxy.php?action=checkout',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        const data=await res.json();
        if(data && data.success && data.redirect_url){window.location.href=data.redirect_url;return;}
        this.processing=false;
        alert((data && (data.error||data.message)) || 'Plata nu a putut fi inițiată. Încearcă din nou.');
      }catch(e){
        this.processing=false;
        alert('Eroare de rețea. Încearcă din nou.');
      }
    }
  }
}
</script>
</body>
</html>
