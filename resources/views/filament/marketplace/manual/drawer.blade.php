{{--
    In-page manual drawer. Rendered through a scoped panels::body.end hook,
    outside the Livewire page component: opening it never touches the form or
    its unsaved changes. Opened by the "Manual pagină" header action
    (window event "ep-manual:open") or any [data-epm-open="anchor"] element.
--}}
@php
    $manualConfig = \App\Support\Manual\PageManual::config($page);
    $hubClass = $manualConfig['hub'] ?? null;
    $hubUrl = $hubClass ? $hubClass::getUrl() : null;
@endphp
@include('filament.marketplace.manual.assets')
<div id="ep-manual" class="epm-root" data-page="{{ $page }}" data-src="{{ route('marketplace.manual.content', ['page' => $page]) }}">
    <div class="epm-backdrop" data-epm-close></div>
    <aside class="epm-panel" role="dialog" aria-modal="true" aria-labelledby="epm-title">
        <header class="epm-head">
            <div class="epm-head-row">
                <div>
                    <p class="epm-kicker">Manual</p>
                    <h2 id="epm-title" class="epm-title">{{ $manualConfig['title'] }}</h2>
                </div>
                <div class="epm-head-actions">
                    @if ($hubUrl)
                        <a href="{{ $hubUrl }}" target="_blank" rel="noopener" class="epm-btn">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                            Pagină completă
                        </a>
                    @endif
                    <button type="button" class="epm-icon-btn" data-epm-close aria-label="Închide manualul">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <input type="search" class="epm-search" data-epm-search placeholder="Caută: reducere, sold out, comision, serie…" aria-label="Caută în manual" autocomplete="off">
            <p class="epm-notice" data-epm-notice hidden></p>
        </header>
        <div class="epm-body">
            <nav class="epm-toc" data-epm-toc aria-label="Cuprins"></nav>
            <div class="epm-content" data-epm-content>
                <p class="epm-loading">Se încarcă manualul…</p>
            </div>
        </div>
    </aside>
</div>
