<!DOCTYPE html>
<html lang="ro" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#061a15">
    <title>Coșul expediției — Nordvale</title>
    <meta name="description" content="Template Tixello pentru coșul unui tenant leisure Nordvale.">

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
        }

        * { box-sizing: border-box; }
        html { background: var(--oat); }
        body {
            margin: 0;
            overflow-x: hidden;
            background: var(--oat);
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            text-rendering: optimizeLegibility;
        }
        [x-cloak] { display: none !important; }
        ::selection { background: var(--acid); color: var(--pine-dark); }
        button, a, input { -webkit-tap-highlight-color: transparent; }

        .safe-top { padding-top: max(12px, env(safe-area-inset-top)); }
        .safe-bottom { padding-bottom: max(18px, env(safe-area-inset-bottom)); }

        .grain::after {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 2;
            pointer-events: none;
            opacity: .08;
            mix-blend-mode: soft-light;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='.58'/%3E%3C/svg%3E");
        }

        .topo-dark {
            background-image:
                radial-gradient(circle at 18% 22%, rgba(223,252,98,.14), transparent 20%),
                radial-gradient(circle at 84% 18%, rgba(242,123,74,.12), transparent 17%),
                url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='.055' stroke-width='1.1'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M119 231c31-83 127-143 219-107 76 30 95 118 53 180-40 58-121 56-160 115-42 61-28 159-107 197-77 37-165-25-154-110 10-83 94-98 102-174 4-44 0-74 47-101Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3Cpath d='M548 507c39-60 118-79 173-32 53 45 37 128-19 161-50 30-111 8-150 51-34 36-29 100-79 112-55 14-106-39-83-92 21-50 81-47 110-92 23-36 22-72 48-108Z'/%3E%3C/g%3E%3C/svg%3E");
            background-size: auto, auto, 820px 820px;
        }

        .paper-grid {
            background-image:
                linear-gradient(rgba(9,37,29,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(9,37,29,.05) 1px, transparent 1px);
            background-size: 30px 30px;
        }

        .nav-shell {
            background: rgba(6,26,21,.82);
            border: 1px solid rgba(255,255,255,.12);
            box-shadow: 0 24px 70px -42px rgba(0,0,0,.9);
            backdrop-filter: blur(22px) saturate(130%);
            -webkit-backdrop-filter: blur(22px) saturate(130%);
        }

        .progress-node::after {
            content: '';
            position: absolute;
            top: 50%;
            left: calc(100% + 12px);
            width: 38px;
            height: 1px;
            background: rgba(255,255,255,.18);
        }
        .progress-node:last-child::after { display: none; }

        .field-ticket {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            background: var(--cream);
            border: 1px solid rgba(9,37,29,.11);
            box-shadow: 0 28px 80px -54px rgba(6,26,21,.7);
        }
        .field-ticket::before,
        .field-ticket::after {
            content: '';
            position: absolute;
            z-index: 4;
            right: 154px;
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: var(--oat);
            border: 1px solid rgba(9,37,29,.08);
        }
        .field-ticket::before { top: -14px; }
        .field-ticket::after { bottom: -14px; }
        .ticket-seam {
            border-left: 1px dashed rgba(9,37,29,.22);
        }

        .timer-ring {
            --progress: 80%;
            background: conic-gradient(var(--acid) var(--progress), rgba(255,255,255,.1) 0);
        }

        .counter-button {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 1px solid rgba(9,37,29,.14);
            background: rgba(255,255,255,.7);
            transition: transform .2s ease, border-color .2s ease, background-color .2s ease;
        }
        .counter-button:hover { transform: translateY(-1px); border-color: rgba(9,37,29,.32); background: white; }
        .counter-button:disabled { opacity: .34; cursor: not-allowed; transform: none; }

        .upsell-card {
            transition: transform .35s cubic-bezier(.18,.8,.22,1), box-shadow .35s ease, border-color .3s ease;
        }
        .upsell-card:hover { transform: translateY(-5px); box-shadow: 0 28px 68px -48px rgba(6,26,21,.75); }

        .reveal-ready [data-reveal] { opacity: 0; transform: translateY(28px); }
        .route-dash {
            fill: none;
            stroke: rgba(223,252,98,.65);
            stroke-width: 2;
            stroke-linecap: round;
            stroke-dasharray: 7 12;
            animation: routeMove 20s linear infinite;
        }
        @keyframes routeMove { to { stroke-dashoffset: -380; } }

        .drawer-shadow { box-shadow: -50px 0 120px -60px rgba(6,26,21,.82); }

        @media (max-width: 1023px) {
            .field-ticket::before,
            .field-ticket::after { display: none; }
            .ticket-seam { border-left: 0; border-top: 1px dashed rgba(9,37,29,.2); }
        }

        @media (max-width: 639px) {
            .progress-node::after { width: 16px; left: calc(100% + 6px); }
            .field-ticket { border-radius: 24px; }
            .mobile-scroll-shadow {
                mask-image: linear-gradient(90deg, #000 0, #000 90%, transparent 100%);
                -webkit-mask-image: linear-gradient(90deg, #000 0, #000 90%, transparent 100%);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
            .reveal-ready [data-reveal] { opacity: 1; transform: none; }
        }
    </style>

    <script>
        document.documentElement.classList.add('reveal-ready');
    </script>
</head>
<body x-data="cartPage()" x-init="init()" class="antialiased pb-28 lg:pb-0">

    <!-- Header -->
    <header class="fixed inset-x-0 top-0 z-50 safe-top px-3 sm:px-5 lg:px-8">
        <div class="nav-shell mx-auto flex h-[62px] max-w-[1440px] items-center justify-between rounded-[20px] px-3 sm:h-[68px] sm:rounded-[22px] sm:px-5">
            <a href="/" class="flex min-w-0 items-center gap-2.5 sm:gap-3" aria-label="Nordvale homepage">
                <span class="grid h-9 w-9 flex-none place-items-center rounded-full bg-acid text-pine-950 sm:h-10 sm:w-10">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 17.5 12 4l7 13.5-7-3-7 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 8.5v8.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <span class="min-w-0 text-white">
                    <span class="block truncate font-display text-[17px] font-semibold leading-none sm:text-xl">Nordvale</span>
                    <span class="mt-1 hidden text-[8px] font-bold uppercase tracking-[.23em] text-white/45 min-[385px]:block sm:text-[9px]">Wild park & reserve</span>
                </span>
            </a>

            <nav class="hidden items-center gap-7 text-sm font-semibold text-white/72 lg:flex" aria-label="Navigație principală">
                <a href="/experiente" class="transition hover:text-white">Experiențe</a>
                <a href="/planifica" class="transition hover:text-white">Planifică</a>
                <a href="/calendar" class="transition hover:text-white">Calendar</a>
                <a href="/bilete" class="transition hover:text-white">Bilete</a>
            </nav>

            <div class="flex flex-none items-center gap-2 sm:gap-3">
                <a href="#cart-content" class="hidden whitespace-nowrap rounded-full border border-white/15 px-4 py-2 text-xs font-bold text-white/80 transition hover:border-white/35 hover:text-white sm:inline-flex">2 rezervări</a>
                <button type="button" @click="mobileMenu = !mobileMenu" class="grid h-10 w-10 flex-none place-items-center rounded-full border border-white/15 text-white lg:hidden" :aria-expanded="mobileMenu" aria-label="Deschide meniul">
                    <svg x-show="!mobileMenu" class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M5 8h14M5 16h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <svg x-show="mobileMenu" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>
        </div>

        <div x-show="mobileMenu" x-cloak x-transition.opacity @click.outside="mobileMenu = false" class="nav-shell mx-auto mt-2 max-w-[1440px] rounded-[22px] p-4 lg:hidden">
            <div class="grid gap-1 text-sm font-semibold text-white/78">
                <a href="/experiente" class="rounded-xl px-4 py-3 hover:bg-white/5 hover:text-white">Experiențe</a>
                <a href="/planifica" class="rounded-xl px-4 py-3 hover:bg-white/5 hover:text-white">Planifică vizita</a>
                <a href="/calendar" class="rounded-xl px-4 py-3 hover:bg-white/5 hover:text-white">Calendar</a>
                <a href="/bilete" class="rounded-xl px-4 py-3 hover:bg-white/5 hover:text-white">Bilete și pachete</a>
            </div>
        </div>
    </header>

    <main>
        <!-- Dark heading -->
        <section class="relative overflow-hidden bg-pine-950 pb-20 pt-28 text-white topo-dark grain sm:pb-24 sm:pt-32 lg:pb-28 lg:pt-36">
            <svg class="pointer-events-none absolute -right-24 top-20 h-[420px] w-[660px] opacity-70" viewBox="0 0 660 420" fill="none" aria-hidden="true">
                <path class="route-dash" d="M22 322C115 266 117 141 219 128c103-14 123 93 221 77 84-13 104-106 196-154"/>
                <circle cx="219" cy="128" r="5" fill="#dffc62"/>
                <circle cx="440" cy="205" r="5" fill="#f27b4a"/>
            </svg>

            <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-10 flex items-center gap-2 overflow-x-auto pb-1 text-[10px] font-bold uppercase tracking-[.18em] text-white/48 sm:mb-12 sm:justify-end sm:gap-5 sm:text-xs">
                    <div class="progress-node relative flex flex-none items-center gap-2 text-acid">
                        <span class="grid h-6 w-6 place-items-center rounded-full bg-acid text-[10px] text-pine-950">1</span>
                        <span>Coș</span>
                    </div>
                    <div class="progress-node relative flex flex-none items-center gap-2">
                        <span class="grid h-6 w-6 place-items-center rounded-full border border-white/20">2</span>
                        <span>Date</span>
                    </div>
                    <div class="progress-node relative flex flex-none items-center gap-2">
                        <span class="grid h-6 w-6 place-items-center rounded-full border border-white/20">3</span>
                        <span>Plată</span>
                    </div>
                    <div class="progress-node relative flex flex-none items-center gap-2">
                        <span class="grid h-6 w-6 place-items-center rounded-full border border-white/20">4</span>
                        <span>Gata</span>
                    </div>
                </div>

                <div class="grid items-end gap-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-14">
                    <div data-reveal>
                        <p class="mb-4 text-[11px] font-bold uppercase tracking-[.28em] text-acid sm:text-xs">Field folio · rezervare activă</p>
                        <h1 class="max-w-4xl font-display text-[clamp(3rem,8vw,7.2rem)] font-semibold leading-[.88] tracking-[-.055em]">
                            Expediția ta<br><span class="text-white/42">prinde contur.</span>
                        </h1>
                        <p class="mt-6 max-w-2xl text-base leading-7 text-white/66 sm:text-lg sm:leading-8">
                            Am păstrat accesul și experiențele selectate. Verifică participanții, adaugă ce îți face ziua mai ușoară și continuă spre plată.
                        </p>
                    </div>

                    <div data-reveal class="rounded-[26px] border border-white/12 bg-white/[.065] p-4 backdrop-blur-sm sm:p-5">
                        <div class="flex items-center gap-4">
                            <div class="timer-ring grid h-[76px] w-[76px] flex-none place-items-center rounded-full p-[5px]" :style="`--progress:${timerProgress}%`">
                                <div class="grid h-full w-full place-items-center rounded-full bg-pine-950 text-center">
                                    <span class="font-display text-lg leading-none text-acid" x-text="timerText"></span>
                                    <span class="mt-1 text-[8px] font-bold uppercase tracking-[.16em] text-white/38">minute</span>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[.2em] text-white/38">Locuri păstrate</p>
                                <p class="mt-1 font-display text-xl leading-tight sm:text-2xl">Până la expirarea timpului</p>
                                <p class="mt-2 text-xs leading-5 text-white/50">Timerul se oprește după ce începi plata.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Cart -->
        <section id="cart-content" class="paper-grid py-12 sm:py-16 lg:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <template x-if="items.length > 0">
                    <div class="grid min-w-0 gap-8 lg:grid-cols-[minmax(0,1fr)_360px] xl:gap-12">
                        <div class="min-w-0">
                            <div class="mb-7 flex items-end justify-between gap-4" data-reveal>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[.23em] text-pine-600">Permise și experiențe</p>
                                    <h2 class="mt-2 font-display text-3xl font-semibold tracking-[-.03em] sm:text-4xl">În coșul tău</h2>
                                </div>
                                <span class="flex-none rounded-full bg-pine-900 px-3 py-1.5 text-xs font-bold text-white" x-text="`${totalTickets} ${totalTickets === 1 ? 'bilet' : 'bilete'}`"></span>
                            </div>

                            <div class="space-y-5">
                                <template x-for="item in items" :key="item.id">
                                    <article class="field-ticket rounded-[28px]" data-reveal>
                                        <div class="grid lg:grid-cols-[190px_minmax(0,1fr)_155px]">
                                            <div class="relative min-h-[180px] overflow-hidden lg:min-h-[260px]">
                                                <img :src="item.image" :alt="item.title" class="absolute inset-0 h-full w-full object-cover">
                                                <div class="absolute inset-0 bg-gradient-to-t from-pine-950/70 via-transparent to-transparent"></div>
                                                <div class="absolute inset-x-0 bottom-0 p-4 text-white">
                                                    <span class="rounded-full bg-acid px-2.5 py-1 text-[9px] font-bold uppercase tracking-[.16em] text-pine-950" x-text="item.tag"></span>
                                                </div>
                                            </div>

                                            <div class="p-5 sm:p-6 lg:p-7">
                                                <div class="flex items-start justify-between gap-4">
                                                    <div>
                                                        <p class="text-[10px] font-bold uppercase tracking-[.2em] text-pine-600" x-text="item.category"></p>
                                                        <h3 class="mt-2 font-display text-2xl font-semibold tracking-[-.025em] sm:text-3xl" x-text="item.title"></h3>
                                                    </div>
                                                    <button type="button" @click="removeItem(item.id)" class="grid h-9 w-9 flex-none place-items-center rounded-full border border-pine-900/10 text-pine-900/45 transition hover:border-ember/40 hover:bg-ember/10 hover:text-ember" aria-label="Elimină din coș">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="m7 7 10 10M17 7 7 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                                    </button>
                                                </div>

                                                <div class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                                                    <div class="rounded-2xl bg-oat/70 p-3.5">
                                                        <p class="text-[9px] font-bold uppercase tracking-[.16em] text-pine-700/55">Data și ora</p>
                                                        <p class="mt-1.5 font-semibold text-pine-900" x-text="`${item.date} · ${item.time}`"></p>
                                                    </div>
                                                    <div class="rounded-2xl bg-oat/70 p-3.5">
                                                        <p class="text-[9px] font-bold uppercase tracking-[.16em] text-pine-700/55">Punct de acces</p>
                                                        <p class="mt-1.5 font-semibold text-pine-900" x-text="item.gate"></p>
                                                    </div>
                                                </div>

                                                <div class="mt-5 flex flex-col gap-4 border-t border-pine-900/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <p class="text-[9px] font-bold uppercase tracking-[.16em] text-pine-700/55">Participanți</p>
                                                        <div class="mt-2 flex items-center gap-2.5">
                                                            <button type="button" class="counter-button" @click="changeQuantity(item.id, -1)" :disabled="item.quantity <= 1" aria-label="Scade numărul de participanți">−</button>
                                                            <span class="min-w-8 text-center font-display text-xl font-semibold" x-text="item.quantity"></span>
                                                            <button type="button" class="counter-button" @click="changeQuantity(item.id, 1)" :disabled="item.quantity >= item.max" aria-label="Crește numărul de participanți">+</button>
                                                            <span class="ml-1 text-xs text-pine-700/55" x-text="item.personLabel"></span>
                                                        </div>
                                                    </div>
                                                    <button type="button" @click="openEditor(item)" class="inline-flex items-center justify-center gap-2 rounded-full border border-pine-900/14 px-4 py-2.5 text-xs font-bold text-pine-900 transition hover:border-pine-900/30 hover:bg-white">
                                                        Modifică rezervarea
                                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="ticket-seam flex flex-row items-center justify-between gap-4 p-5 lg:flex-col lg:items-stretch lg:justify-center lg:p-6">
                                                <div>
                                                    <p class="text-[9px] font-bold uppercase tracking-[.16em] text-pine-700/55">Subtotal</p>
                                                    <p class="mt-1 font-display text-2xl font-semibold text-pine-900" x-text="money(item.price * item.quantity)"></p>
                                                    <p class="mt-1 text-[10px] text-pine-700/48" x-text="`${money(item.price)} / ${item.unit}`"></p>
                                                </div>
                                                <div class="rounded-2xl border border-dashed border-pine-900/18 px-3 py-2 text-center">
                                                    <p class="font-mono text-[10px] font-bold tracking-[.15em] text-pine-700/55" x-text="item.code"></p>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </template>
                            </div>

                            <!-- Upsell -->
                            <div class="mt-12" data-reveal>
                                <div class="mb-5 flex items-end justify-between gap-4">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-[.23em] text-pine-600">Mai puțină logistică</p>
                                        <h2 class="mt-2 font-display text-3xl font-semibold tracking-[-.03em]">Adaugă înainte să pleci</h2>
                                    </div>
                                    <span class="hidden text-xs text-pine-700/50 sm:block">Poți elimina oricând</span>
                                </div>

                                <div class="mobile-scroll-shadow -mx-4 flex w-full min-w-0 snap-x gap-3 overflow-x-auto px-4 pb-3 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0">
                                    <template x-for="extra in extras" :key="extra.id">
                                        <button type="button" @click="toggleExtra(extra.id)" class="upsell-card relative min-w-[76vw] snap-start overflow-hidden rounded-[24px] border bg-cream p-4 text-left sm:min-w-0" :class="extra.selected ? 'border-pine-900 shadow-card' : 'border-pine-900/10'">
                                            <div class="flex items-start justify-between gap-3">
                                                <span class="grid h-11 w-11 place-items-center rounded-2xl" :class="extra.selected ? 'bg-pine-900 text-acid' : 'bg-oat text-pine-900'">
                                                    <span x-html="extra.icon"></span>
                                                </span>
                                                <span class="grid h-7 w-7 place-items-center rounded-full border text-sm font-bold" :class="extra.selected ? 'border-pine-900 bg-pine-900 text-acid' : 'border-pine-900/15 text-pine-900'" x-text="extra.selected ? '✓' : '+'"></span>
                                            </div>
                                            <p class="mt-5 font-display text-xl font-semibold" x-text="extra.title"></p>
                                            <p class="mt-2 min-h-[42px] text-xs leading-5 text-pine-700/60" x-text="extra.copy"></p>
                                            <p class="mt-4 text-sm font-bold text-pine-900" x-text="money(extra.price)"></p>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Continue shopping -->
                            <div class="mt-8 flex flex-col gap-3 rounded-[24px] border border-dashed border-pine-900/18 bg-white/40 p-5 sm:flex-row sm:items-center sm:justify-between" data-reveal>
                                <div>
                                    <p class="font-semibold text-pine-900">Mai este loc pentru o aventură.</p>
                                    <p class="mt-1 text-xs leading-5 text-pine-700/55">Rezervările noi vor folosi aceeași dată, când există disponibilitate.</p>
                                </div>
                                <a href="/experiente" class="inline-flex flex-none items-center justify-center gap-2 rounded-full bg-pine-900 px-5 py-3 text-xs font-bold text-white transition hover:bg-pine-800">
                                    Explorează experiențe
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            </div>
                        </div>

                        <!-- Summary desktop -->
                        <aside class="hidden lg:block" data-reveal>
                            <div class="sticky top-24 overflow-hidden rounded-[30px] bg-pine-950 text-white shadow-lift">
                                <div class="relative overflow-hidden border-b border-white/10 p-7 topo-dark">
                                    <p class="relative z-10 text-[10px] font-bold uppercase tracking-[.22em] text-acid">Rezumat expediție</p>
                                    <h2 class="relative z-10 mt-2 font-display text-3xl font-semibold">Nordvale · 14 august</h2>
                                    <p class="relative z-10 mt-3 text-sm leading-6 text-white/55">Intrarea este permisă în orice moment după deschiderea porților. Activitățile păstrează orele selectate.</p>
                                </div>

                                <div class="p-7">
                                    <div class="space-y-3 text-sm">
                                        <div class="flex items-center justify-between gap-4">
                                            <span class="text-white/55">Bilete și experiențe</span>
                                            <span class="font-semibold" x-text="money(itemsSubtotal)"></span>
                                        </div>
                                        <div x-show="extrasTotal > 0" class="flex items-center justify-between gap-4">
                                            <span class="text-white/55">Suplimente</span>
                                            <span class="font-semibold" x-text="money(extrasTotal)"></span>
                                        </div>
                                        <div x-show="discount > 0" class="flex items-center justify-between gap-4 text-acid">
                                            <span>Cod promo</span>
                                            <span class="font-semibold" x-text="`− ${money(discount)}`"></span>
                                        </div>
                                        <div class="flex items-center justify-between gap-4">
                                            <span class="text-white/55">Taxă procesare</span>
                                            <span class="font-semibold" x-text="money(serviceFee)"></span>
                                        </div>
                                    </div>

                                    <div class="my-6 border-t border-dashed border-white/16"></div>

                                    <div class="flex items-end justify-between gap-4">
                                        <div>
                                            <p class="text-[9px] font-bold uppercase tracking-[.18em] text-white/38">Total de plată</p>
                                            <p class="mt-1 font-display text-4xl font-semibold text-acid" x-text="money(total)"></p>
                                        </div>
                                        <span class="mb-1 rounded-full bg-white/8 px-3 py-1 text-[9px] font-bold uppercase tracking-[.14em] text-white/50">TVA inclus</span>
                                    </div>

                                    <div class="mt-6">
                                        <div class="flex gap-2">
                                            <input x-model.trim="promo" @keydown.enter.prevent="applyPromo()" type="text" placeholder="Cod promo" class="min-w-0 flex-1 rounded-full border border-white/14 bg-white/6 px-4 py-3 text-sm text-white outline-none placeholder:text-white/28 focus:border-acid/60">
                                            <button type="button" @click="applyPromo()" class="flex-none rounded-full border border-white/15 px-4 text-xs font-bold text-white transition hover:border-white/35">Aplică</button>
                                        </div>
                                        <p x-show="promoMessage" x-cloak class="mt-2 text-xs" :class="promoApplied ? 'text-acid' : 'text-ember'" x-text="promoMessage"></p>
                                    </div>

                                    <a href="/finalizare" class="mt-6 flex w-full items-center justify-between rounded-full bg-acid px-5 py-4 text-sm font-bold text-pine-950 shadow-acid transition hover:-translate-y-0.5">
                                        <span>Continuă spre checkout</span>
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </a>

                                    <div class="mt-5 grid grid-cols-3 gap-2 text-center text-[9px] font-bold uppercase tracking-[.1em] text-white/35">
                                        <div class="rounded-xl bg-white/[.045] p-2.5">Plată SSL</div>
                                        <div class="rounded-xl bg-white/[.045] p-2.5">Bilete instant</div>
                                        <div class="rounded-xl bg-white/[.045] p-2.5">Suport local</div>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </template>

                <!-- Empty -->
                <template x-if="items.length === 0">
                    <div class="mx-auto max-w-3xl py-10 text-center sm:py-20">
                        <div class="relative mx-auto grid h-36 w-36 place-items-center rounded-full bg-pine-900 text-acid shadow-lift">
                            <svg class="h-16 w-16" viewBox="0 0 24 24" fill="none"><path d="M5 17.5 12 4l7 13.5-7-3-7 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M12 8.5v8.7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            <span class="absolute -right-2 top-2 grid h-12 w-12 place-items-center rounded-full bg-ember font-display text-xl text-white">0</span>
                        </div>
                        <p class="mt-8 text-[10px] font-bold uppercase tracking-[.23em] text-pine-600">Folio gol</p>
                        <h2 class="mt-3 font-display text-4xl font-semibold tracking-[-.04em] sm:text-5xl">Încă nu ai ales traseul.</h2>
                        <p class="mx-auto mt-4 max-w-xl text-sm leading-7 text-pine-700/60 sm:text-base">Pornește de la experiențele Nordvale sau alege un pachet complet pentru o zi întreagă în rezervație.</p>
                        <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                            <a href="/experiente" class="rounded-full bg-pine-900 px-6 py-3.5 text-sm font-bold text-white">Vezi experiențele</a>
                            <a href="/bilete" class="rounded-full border border-pine-900/16 px-6 py-3.5 text-sm font-bold text-pine-900">Compară pachetele</a>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <!-- Reassurance -->
        <section class="overflow-hidden bg-cream py-12 sm:py-16">
            <div class="mx-auto grid max-w-7xl gap-4 px-4 sm:grid-cols-3 sm:px-6 lg:px-8">
                <div class="rounded-[24px] border border-pine-900/9 bg-oat/55 p-5" data-reveal>
                    <span class="grid h-10 w-10 place-items-center rounded-2xl bg-acid text-pine-950">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <h3 class="mt-4 font-display text-xl font-semibold">Confirmare instant</h3>
                    <p class="mt-2 text-xs leading-5 text-pine-700/55">Biletele QR ajung imediat în email și în contul Nordvale.</p>
                </div>
                <div class="rounded-[24px] border border-pine-900/9 bg-oat/55 p-5" data-reveal>
                    <span class="grid h-10 w-10 place-items-center rounded-2xl bg-sky text-pine-950">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M12 3v18M5.5 7.5 12 3l6.5 4.5M5.5 16.5 12 21l6.5-4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <h3 class="mt-4 font-display text-xl font-semibold">Vreme schimbătoare</h3>
                    <p class="mt-2 text-xs leading-5 text-pine-700/55">Dacă suspendăm o activitate, poți reprograma sau primi credit integral.</p>
                </div>
                <div class="rounded-[24px] border border-pine-900/9 bg-oat/55 p-5" data-reveal>
                    <span class="grid h-10 w-10 place-items-center rounded-2xl bg-ember text-white">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 12h7M12 8.5v7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    </span>
                    <h3 class="mt-4 font-display text-xl font-semibold">Ajutor uman</h3>
                    <p class="mt-2 text-xs leading-5 text-pine-700/55">Echipa locală răspunde înainte, în timpul și după vizită.</p>
                </div>
            </div>
        </section>
    </main>

    <!-- Mobile checkout bar -->
    <div x-show="items.length > 0" class="fixed inset-x-0 bottom-0 z-40 border-t border-pine-900/10 bg-cream/95 px-3 pt-3 backdrop-blur-xl safe-bottom lg:hidden">
        <div class="mx-auto flex max-w-xl items-center gap-3">
            <button type="button" @click="summaryOpen = true" class="min-w-0 flex-1 rounded-[18px] border border-pine-900/10 bg-oat/70 px-4 py-3 text-left">
                <span class="block text-[9px] font-bold uppercase tracking-[.15em] text-pine-700/50">Total · vezi sumarul</span>
                <span class="mt-1 block font-display text-2xl font-semibold leading-none text-pine-900" x-text="money(total)"></span>
            </button>
            <a href="/finalizare" class="inline-flex h-[58px] flex-none items-center gap-2 whitespace-nowrap rounded-[18px] bg-pine-900 px-5 text-sm font-bold text-white shadow-card">
                Continuă
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        </div>
    </div>

    <!-- Edit drawer -->
    <div x-show="editorOpen" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true" aria-label="Modifică rezervarea">
        <div x-show="editorOpen" x-transition.opacity class="absolute inset-0 bg-pine-950/72 backdrop-blur-sm" @click="editorOpen = false"></div>
        <div x-show="editorOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full lg:translate-y-0 lg:translate-x-full" x-transition:enter-end="translate-y-0 lg:translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0 lg:translate-x-0" x-transition:leave-end="translate-y-full lg:translate-y-0 lg:translate-x-full" class="drawer-shadow absolute inset-x-0 bottom-0 max-h-[92dvh] overflow-y-auto rounded-t-[30px] bg-cream lg:inset-y-0 lg:left-auto lg:w-[520px] lg:rounded-none">
            <template x-if="draftItem">
                <div>
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-pine-900/8 bg-cream/94 px-5 py-4 backdrop-blur-xl sm:px-7">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-[.18em] text-pine-600">Modifică rezervarea</p>
                            <h2 class="mt-1 font-display text-2xl font-semibold" x-text="draftItem.title"></h2>
                        </div>
                        <button type="button" @click="editorOpen = false" class="grid h-10 w-10 place-items-center rounded-full border border-pine-900/12" aria-label="Închide">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="m7 7 10 10M17 7 7 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </button>
                    </div>

                    <div class="space-y-7 p-5 sm:p-7">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[.18em] text-pine-700/55">1. Data</p>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <template x-for="date in editDates" :key="date.value">
                                    <button type="button" @click="draftItem.date = date.label" class="rounded-[18px] border px-2 py-3 text-center" :class="draftItem.date === date.label ? 'border-pine-900 bg-pine-900 text-white' : 'border-pine-900/10 bg-white'">
                                        <span class="block text-[9px] font-bold uppercase tracking-[.12em] opacity-55" x-text="date.weekday"></span>
                                        <span class="mt-1 block font-display text-lg font-semibold" x-text="date.short"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[.18em] text-pine-700/55">2. Ora</p>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <template x-for="slot in editSlots" :key="slot.time">
                                    <button type="button" @click="!slot.soldOut && (draftItem.time = slot.time)" :disabled="slot.soldOut" class="rounded-[18px] border px-2 py-3 text-sm font-bold" :class="slot.soldOut ? 'cursor-not-allowed border-pine-900/6 bg-oat text-pine-900/25' : draftItem.time === slot.time ? 'border-acid bg-acid text-pine-950' : 'border-pine-900/10 bg-white'">
                                        <span x-text="slot.time"></span>
                                        <span class="mt-1 block text-[8px] font-medium uppercase tracking-[.08em] opacity-55" x-text="slot.soldOut ? 'ocupat' : `${slot.spots} locuri`"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[.18em] text-pine-700/55">3. Participanți</p>
                            <div class="mt-3 flex items-center justify-between rounded-[20px] border border-pine-900/10 bg-white p-4">
                                <div>
                                    <p class="font-semibold">Număr persoane</p>
                                    <p class="mt-1 text-xs text-pine-700/50" x-text="draftItem.personLabel"></p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="counter-button" @click="draftItem.quantity = Math.max(1, draftItem.quantity - 1)" :disabled="draftItem.quantity <= 1">−</button>
                                    <span class="min-w-8 text-center font-display text-xl font-semibold" x-text="draftItem.quantity"></span>
                                    <button type="button" class="counter-button" @click="draftItem.quantity = Math.min(draftItem.max, draftItem.quantity + 1)" :disabled="draftItem.quantity >= draftItem.max">+</button>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-[22px] bg-pine-900 p-5 text-white">
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <p class="text-[9px] font-bold uppercase tracking-[.15em] text-white/40">Subtotal actualizat</p>
                                    <p class="mt-1 font-display text-3xl font-semibold text-acid" x-text="money(draftItem.price * draftItem.quantity)"></p>
                                </div>
                                <p class="text-right text-xs leading-5 text-white/45">Disponibilitatea este<br>verificată în timp real.</p>
                            </div>
                        </div>
                    </div>

                    <div class="sticky bottom-0 border-t border-pine-900/8 bg-cream/94 p-4 backdrop-blur-xl sm:p-5">
                        <button type="button" @click="saveEditor()" class="flex w-full items-center justify-between rounded-full bg-pine-900 px-5 py-4 text-sm font-bold text-white">
                            Salvează modificările
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Mobile summary sheet -->
    <div x-show="summaryOpen" x-cloak class="fixed inset-0 z-[85] lg:hidden" role="dialog" aria-modal="true" aria-label="Rezumat comandă">
        <div x-show="summaryOpen" x-transition.opacity class="absolute inset-0 bg-pine-950/72 backdrop-blur-sm" @click="summaryOpen = false"></div>
        <div x-show="summaryOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full" class="absolute inset-x-0 bottom-0 max-h-[88dvh] overflow-y-auto rounded-t-[30px] bg-pine-950 text-white safe-bottom">
            <div class="sticky top-0 flex items-center justify-between border-b border-white/10 bg-pine-950/94 px-5 py-4 backdrop-blur-xl">
                <div>
                    <p class="text-[9px] font-bold uppercase tracking-[.18em] text-acid">Rezumat expediție</p>
                    <h2 class="mt-1 font-display text-2xl font-semibold">14 august · Nordvale</h2>
                </div>
                <button type="button" @click="summaryOpen = false" class="grid h-10 w-10 place-items-center rounded-full border border-white/14">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="m7 7 10 10M17 7 7 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>

            <div class="p-5">
                <div class="space-y-3 text-sm">
                    <template x-for="item in items" :key="`summary-${item.id}`">
                        <div class="flex items-start justify-between gap-4 rounded-[18px] bg-white/[.055] p-4">
                            <div class="min-w-0">
                                <p class="truncate font-semibold" x-text="item.title"></p>
                                <p class="mt-1 text-xs text-white/42" x-text="`${item.quantity} × ${money(item.price)}`"></p>
                            </div>
                            <span class="flex-none font-semibold" x-text="money(item.quantity * item.price)"></span>
                        </div>
                    </template>
                </div>

                <div x-show="selectedExtras.length > 0" class="mt-4 border-t border-white/10 pt-4">
                    <p class="mb-3 text-[9px] font-bold uppercase tracking-[.16em] text-white/35">Suplimente</p>
                    <template x-for="extra in selectedExtras" :key="`sheet-${extra.id}`">
                        <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                            <span class="text-white/55" x-text="extra.title"></span>
                            <span x-text="money(extra.price)"></span>
                        </div>
                    </template>
                </div>

                <div class="mt-5 border-t border-dashed border-white/16 pt-5">
                    <div class="flex items-center justify-between text-sm"><span class="text-white/55">Subtotal</span><span x-text="money(itemsSubtotal + extrasTotal)"></span></div>
                    <div class="mt-3 flex items-center justify-between text-sm"><span class="text-white/55">Taxă procesare</span><span x-text="money(serviceFee)"></span></div>
                    <div x-show="discount > 0" class="mt-3 flex items-center justify-between text-sm text-acid"><span>Reducere</span><span x-text="`− ${money(discount)}`"></span></div>
                    <div class="mt-5 flex items-end justify-between border-t border-white/10 pt-5">
                        <span class="text-sm font-semibold">Total</span>
                        <span class="font-display text-4xl font-semibold text-acid" x-text="money(total)"></span>
                    </div>
                </div>

                <div class="mt-5 flex gap-2">
                    <input x-model.trim="promo" @keydown.enter.prevent="applyPromo()" type="text" placeholder="Cod promo" class="min-w-0 flex-1 rounded-full border border-white/14 bg-white/6 px-4 py-3 text-sm text-white outline-none placeholder:text-white/28">
                    <button type="button" @click="applyPromo()" class="flex-none rounded-full border border-white/15 px-4 text-xs font-bold">Aplică</button>
                </div>
                <p x-show="promoMessage" x-cloak class="mt-2 text-xs" :class="promoApplied ? 'text-acid' : 'text-ember'" x-text="promoMessage"></p>

                <a href="/finalizare" class="mt-6 flex w-full items-center justify-between rounded-full bg-acid px-5 py-4 text-sm font-bold text-pine-950">
                    Continuă spre checkout
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            </div>
        </div>
    </div>

    <script>
        function cartPage() {
            return {
                mobileMenu: false,
                editorOpen: false,
                summaryOpen: false,
                draftItem: null,
                cartEvent: null,
                promo: '',
                promoApplied: false,
                promoMessage: '',
                timerSeconds: 14 * 60 + 52,
                timerInitial: 15 * 60,
                timerHandle: null,
                items: [
                    {
                        id: 1,
                        title: 'Adventure Pass',
                        category: 'Acces de o zi',
                        tag: 'Pachet complet',
                        date: 'Joi, 14 august',
                        time: '09:30',
                        gate: 'Poarta Nord · Check-in general',
                        quantity: 2,
                        max: 8,
                        price: 189,
                        unit: 'adult',
                        personLabel: 'adulți',
                        code: 'NV-A14-0930',
                        image: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=85'
                    },
                    {
                        id: 2,
                        title: 'Canopy Run',
                        category: 'Experiență ghidată',
                        tag: 'Ora rezervată',
                        date: 'Joi, 14 august',
                        time: '12:40',
                        gate: 'Stația Canopy · sectorul estic',
                        quantity: 2,
                        max: 6,
                        price: 84,
                        unit: 'participant',
                        personLabel: 'participanți',
                        code: 'CR-1408-1240',
                        image: 'https://images.unsplash.com/photo-1486911278844-a81c5267e227?auto=format&fit=crop&w=900&q=85'
                    }
                ],
                extras: [
                    {
                        id: 'parking',
                        title: 'Parcare rezervată',
                        copy: 'Loc garantat în parcarea P1, la patru minute de intrare.',
                        price: 24,
                        selected: false,
                        icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M7 20V4h6a5 5 0 0 1 0 10H7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                    },
                    {
                        id: 'lunch',
                        title: 'Trail Lunch',
                        copy: 'Sandwich cald, fruct, apă și snack pentru fiecare participant.',
                        price: 58,
                        selected: false,
                        icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M6 4v7a3 3 0 0 0 3 3V4M9 14v6M16 4v16M16 4c2.5 1.8 2.5 6.2 0 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>'
                    },
                    {
                        id: 'photos',
                        title: 'Photo Trail',
                        copy: 'Fotografii automate din traseu, livrate digital în aceeași zi.',
                        price: 39,
                        selected: true,
                        icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M4 8h4l1.5-2h5L16 8h4v10H4V8Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="12" cy="13" r="3" stroke="currentColor" stroke-width="1.7"/></svg>'
                    }
                ],
                editDates: [
                    { value: '2026-08-14', weekday: 'Joi', short: '14 aug', label: 'Joi, 14 august' },
                    { value: '2026-08-15', weekday: 'Vineri', short: '15 aug', label: 'Vineri, 15 august' },
                    { value: '2026-08-16', weekday: 'Sâmbătă', short: '16 aug', label: 'Sâmbătă, 16 august' }
                ],
                editSlots: [
                    { time: '09:30', spots: 18, soldOut: false },
                    { time: '11:10', spots: 7, soldOut: false },
                    { time: '12:40', spots: 4, soldOut: false },
                    { time: '14:20', spots: 0, soldOut: true },
                    { time: '15:50', spots: 12, soldOut: false },
                    { time: '17:10', spots: 9, soldOut: false }
                ],

                init() {
                    this.loadCart();
                    this.startTimer();
                    this.$nextTick(() => this.initReveals());
                },

                loadCart() {
                    let loaded = null;
                    try {
                        const raw = localStorage.getItem('nordvale_cart');
                        if (raw) loaded = JSON.parse(raw);
                    } catch (e) {}
                    if (loaded && Array.isArray(loaded.items) && loaded.items.length) {
                        const ev = loaded.event || {};
                        this.cartEvent = ev;
                        this.items = loaded.items.map((it, idx) => ({
                            id: it.id ?? it.ticket_type_id ?? (idx + 1),
                            ticket_type_id: it.ticket_type_id ?? null,
                            title: it.title || ev.title || 'Experiență',
                            category: it.category || ev.title || 'Nordvale',
                            tag: it.tag || 'Bilet',
                            date: it.date || ev.date || '',
                            time: it.slot || it.time || '',
                            gate: it.gate || ev.venue || 'Check-in general',
                            quantity: parseInt(it.qty ?? it.quantity ?? 1, 10) || 1,
                            max: parseInt(it.max ?? 20, 10) || 20,
                            price: parseFloat(it.unit_price ?? it.price ?? 0) || 0,
                            unit: it.unit || 'bilet',
                            personLabel: it.personLabel || 'bilete',
                            code: it.code || '',
                            image: it.image || ev.image || 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=85'
                        }));
                    } else {
                        // Coș gol → afișează empty-state (nu datele demo)
                        this.items = [];
                    }
                },

                persist() {
                    try {
                        if (!this.items.length) { localStorage.removeItem('nordvale_cart'); return; }
                        const cart = {
                            event: this.cartEvent || {},
                            items: this.items.map(it => ({
                                id: it.id,
                                ticket_type_id: it.ticket_type_id ?? null,
                                title: it.title,
                                date: it.date,
                                slot: it.time,
                                qty: it.quantity,
                                unit_price: it.price,
                                image: it.image,
                                category: it.category,
                                tag: it.tag,
                                gate: it.gate,
                                unit: it.unit,
                                personLabel: it.personLabel,
                                code: it.code,
                                max: it.max
                            })),
                            subtotal: this.itemsSubtotal
                        };
                        localStorage.setItem('nordvale_cart', JSON.stringify(cart));
                    } catch (e) {}
                },

                startTimer() {
                    if (this.timerHandle) clearInterval(this.timerHandle);
                    this.timerHandle = setInterval(() => {
                        if (this.timerSeconds > 0) this.timerSeconds -= 1;
                    }, 1000);
                },

                initReveals() {
                    const nodes = Array.from(document.querySelectorAll('[data-reveal]'));
                    if (!nodes.length) {
                        document.documentElement.classList.remove('reveal-ready');
                        return;
                    }
                    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    if (reduced || !('IntersectionObserver' in window)) {
                        nodes.forEach(node => { node.style.opacity = '1'; node.style.transform = 'none'; });
                        document.documentElement.classList.remove('reveal-ready');
                        return;
                    }
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (!entry.isIntersecting) return;
                            entry.target.animate([
                                { opacity: 0, transform: 'translateY(28px)' },
                                { opacity: 1, transform: 'translateY(0)' }
                            ], { duration: 720, easing: 'cubic-bezier(.18,.8,.22,1)', fill: 'forwards' });
                            observer.unobserve(entry.target);
                        });
                    }, { threshold: .12, rootMargin: '0px 0px -5% 0px' });
                    nodes.forEach(node => observer.observe(node));
                    document.documentElement.classList.remove('reveal-ready');
                },

                get timerText() {
                    const minutes = Math.floor(this.timerSeconds / 60).toString().padStart(2, '0');
                    const seconds = (this.timerSeconds % 60).toString().padStart(2, '0');
                    return `${minutes}:${seconds}`;
                },

                get timerProgress() {
                    return Math.max(0, Math.min(100, (this.timerSeconds / this.timerInitial) * 100));
                },

                get totalTickets() {
                    return this.items.reduce((sum, item) => sum + item.quantity, 0);
                },

                get itemsSubtotal() {
                    return this.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
                },

                get selectedExtras() {
                    return this.extras.filter(extra => extra.selected);
                },

                get extrasTotal() {
                    return this.selectedExtras.reduce((sum, extra) => sum + extra.price, 0);
                },

                get discount() {
                    return this.promoApplied ? Math.round((this.itemsSubtotal + this.extrasTotal) * .1) : 0;
                },

                get serviceFee() {
                    return this.items.length ? 9 : 0;
                },

                get total() {
                    return Math.max(0, this.itemsSubtotal + this.extrasTotal + this.serviceFee - this.discount);
                },

                money(value) {
                    return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON', maximumFractionDigits: 0 }).format(value);
                },

                changeQuantity(id, delta) {
                    const item = this.items.find(entry => entry.id === id);
                    if (!item) return;
                    item.quantity = Math.max(1, Math.min(item.max, item.quantity + delta));
                    this.persist();
                },

                removeItem(id) {
                    this.items = this.items.filter(item => item.id !== id);
                    this.persist();
                },

                toggleExtra(id) {
                    const extra = this.extras.find(entry => entry.id === id);
                    if (extra) extra.selected = !extra.selected;
                },

                applyPromo() {
                    const code = this.promo.toUpperCase();
                    if (code === 'TRAIL10') {
                        this.promoApplied = true;
                        this.promoMessage = 'Cod aplicat: 10% reducere.';
                    } else {
                        this.promoApplied = false;
                        this.promoMessage = code ? 'Codul introdus nu este valid.' : 'Introdu un cod promo.';
                    }
                },

                openEditor(item) {
                    this.draftItem = JSON.parse(JSON.stringify(item));
                    this.editorOpen = true;
                },

                saveEditor() {
                    if (!this.draftItem) return;
                    const index = this.items.findIndex(item => item.id === this.draftItem.id);
                    if (index !== -1) this.items.splice(index, 1, JSON.parse(JSON.stringify(this.draftItem)));
                    this.editorOpen = false;
                    this.draftItem = null;
                    this.persist();
                }
            };
        }
    </script>
</body>
</html>
