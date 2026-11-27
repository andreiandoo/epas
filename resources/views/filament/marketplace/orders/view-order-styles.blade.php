{{-- Order page header: number + status pill, one meta line, actions on the right. --}}
<div class="ep-ord-style" aria-hidden="true">
<style>
.ep-ord-style, .fi-grid-col:has(> .ep-ord-style) { display: none; }
.ep-ord-page { --ord-soft: #4b5563; --ord-muted: #6b7280; --ord-sep: #d1d5db; }
.dark .ep-ord-page { --ord-soft: #aab3c0; --ord-muted: #8b95a3; --ord-sep: #4b5563; }
.fi-resource-view-record-page.ep-ord-page .fi-header { flex-direction: row !important; flex-wrap: wrap; align-items: center !important; justify-content: space-between; gap: 12px 16px; }
.ep-ord-page .fi-header-heading { font-size: 26px; line-height: 1.25; }
.ep-ord-page .fi-header-subheading { margin-top: 6px; max-width: none; }
.ep-ord-page .fi-header-actions-ctn { margin-inline-start: auto; }
.ep-ord-no { margin-left: 8px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; font-weight: 500; color: var(--ord-muted); vertical-align: middle; }
.ep-ord-pill { display: inline-flex; align-items: center; gap: 5px; height: 24px; padding: 0 9px; margin-left: 8px; border-radius: 999px; font-size: 12px; font-weight: 600; vertical-align: middle; }
.ep-ord-pill i { width: 6px; height: 6px; border-radius: 999px; background: currentColor; }
.ep-ord-pill--ok { background: rgba(16,185,129,.12); color: #059669; } .dark .ep-ord-pill--ok { color: #34d399; }
.ep-ord-pill--warn { background: rgba(245,158,11,.14); color: #b45309; } .dark .ep-ord-pill--warn { color: #fcd34d; }
.ep-ord-pill--bad { background: rgba(239,68,68,.12); color: #b91c1c; } .dark .ep-ord-pill--bad { color: #fca5a5; }
.ep-ord-pill--mute { background: rgba(107,114,128,.14); color: var(--ord-soft); }
.ep-ord-meta { font-size: 13px; font-weight: 400; color: var(--ord-soft); }
.ep-ord-meta a { color: inherit; text-decoration: underline; text-decoration-color: var(--ord-sep); }
.ep-ord-sep { color: var(--ord-sep); margin: 0 2px; }
</style>
</div>
