<?php
/**
 * QR scanner input — works with any HID Bluetooth scanner in keyboard mode.
 *
 * The scanner types the scanned code followed by Enter; we listen for Enter
 * and trigger the Livewire action passed via wire:submit on the parent form.
 * Auto-focus + auto-clear after submit keeps the operator's hands free.
 *
 * Usage:
 *   <x-leisure.qr-scanner-input wire-model="scanInput" placeholder="..." />
 *
 * Attributes:
 *   wire-model   — Livewire property to bind to (required)
 *   placeholder  — optional placeholder text
 *   autofocus    — boolean, default true
 */
?>
@props([
    'wireModel',
    'placeholder' => 'Scan QR sau introdu cod...',
    'autofocus' => true,
])

<div
    x-data="{
        focusInput() { $refs.scanInput && $refs.scanInput.focus(); },
    }"
    x-init="focusInput()"
    @click="focusInput()"
    class="relative"
>
    <input
        type="text"
        x-ref="scanInput"
        wire:model.live.debounce.0ms="{{ $wireModel }}"
        @if($autofocus) autofocus @endif
        autocomplete="off"
        autocorrect="off"
        autocapitalize="off"
        spellcheck="false"
        inputmode="text"
        placeholder="{{ $placeholder }}"
        class="fi-input w-full px-4 py-3 text-lg font-mono rounded-lg border-2 border-emerald-300 focus:border-emerald-500 bg-white dark:bg-gray-900"
    >
    <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400 text-xl">
        📷
    </div>
</div>
