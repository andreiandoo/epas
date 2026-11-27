{{-- CSS strict pentru formularul de editare locatie; totul sub .ep-venue-form --}}
<div class="ep-vf-style" aria-hidden="true">
<style>
.fi-grid-col:has(> .ep-vf-style){display:none}

/* antet: titlu stanga, actiuni dreapta, rand de detalii mic sub titlu (bate regula globala a paginilor de editare) */
.fi-resource-edit-record-page.ep-venue-page .fi-header{flex-direction:row!important;flex-wrap:wrap;align-items:center!important;justify-content:space-between;gap:12px 16px}
.ep-venue-page .fi-header-heading{font-size:26px;line-height:1.2;font-weight:700;letter-spacing:-.01em}
.ep-venue-page .fi-header-subheading{margin-top:6px;font-size:13px;font-weight:400;color:#6b7280;max-width:none}
.dark .ep-venue-page .fi-header-subheading{color:#aab3c0}
.ep-venue-page .fi-header-actions-ctn{margin-inline-start:auto;justify-content:flex-end}
.ep-venue-page .fi-header-actions-ctn .fi-ac{justify-content:flex-end}
.ep-venue-page .fi-header-actions-ctn .fi-btn{height:34px;border-radius:8px;font-size:13px}
.ep-venue-page .fi-header-actions-ctn .fi-btn[class*="fi-color-gray"]:first-child{background:transparent;box-shadow:none}
.ep-venue-form{--ep-text:#111827;--ep-soft:#4b5563;--ep-muted:#6b7280;--ep-line:#e5e7eb;--ep-hover:#f3f4f6;--ep-on:#eef0f3;--ep-ok:#10b981;--ep-ring:#9ca3af;--ep-link:#2563eb;--ep-chip-bg:rgba(59,130,246,.10);--ep-chip-bd:#3b82f6;--ep-chip-tx:#1d4ed8;--ep-good:#059669}
.dark .ep-venue-form{--ep-text:#e6e9ee;--ep-soft:#aab3c0;--ep-muted:#8b95a3;--ep-line:#232a35;--ep-hover:#151a21;--ep-on:#171c23;--ep-ok:#10b981;--ep-ring:#6b7480;--ep-link:#7cb4ff;--ep-chip-bg:rgba(59,130,246,.14);--ep-chip-bd:#3b82f6;--ep-chip-tx:#cfe1ff;--ep-good:#34d399}

/* carduri */
.ep-venue-form .fi-sc-section{scroll-margin-top:5rem}
.ep-venue-form .fi-section{border-radius:12px}
.ep-venue-form .fi-section-header-heading{font-size:15px;font-weight:600}
.ep-venue-form .ep-vf-owner .fi-section{border-style:dashed}

/* navigatie sectiuni */
.ep-venue-form .ep-vf-nav{display:flex;flex-wrap:wrap;gap:2px 4px}
.ep-venue-form .ep-vf-nav a,.ep-venue-form .ep-vf-todo a{display:flex;align-items:center;gap:10px;min-height:32px;padding:0 10px;border-radius:8px;color:var(--ep-soft);font-size:13px;font-weight:500;text-decoration:none}
.ep-venue-form .ep-vf-nav a:hover,.ep-venue-form .ep-vf-todo a:hover{background:var(--ep-hover);color:var(--ep-text)}
.ep-venue-form .ep-vf-nav a.on{background:var(--ep-on);color:var(--ep-text)}
.ep-venue-form .ep-vf-nav a:focus-visible,.ep-venue-form .ep-vf-todo a:focus-visible{outline:2px solid var(--ep-chip-bd);outline-offset:1px}
.ep-venue-form .ep-ok,.ep-venue-form .ep-todo{width:7px;height:7px;border-radius:999px;flex:none;box-sizing:border-box}
.ep-venue-form .ep-ok{background:var(--ep-ok)}
.ep-venue-form .ep-todo{border:1.5px solid var(--ep-ring)}
.ep-venue-form .ep-vf-todo{display:flex;flex-direction:column;gap:2px}
.ep-venue-form .ep-vf-done{font-size:13px;color:var(--ep-good)}
@media (min-width:1536px){
  .ep-venue-form .ep-vf-nav{position:sticky;top:5rem;flex-direction:column;flex-wrap:nowrap}
}

/* statistici / informatii */
.ep-venue-form .ep-vf-owner a{color:var(--ep-link)}

/* facilitati ca bule */
.ep-venue-form .ep-vf-chips .fi-fo-checkbox-list-options{display:flex!important;flex-wrap:wrap;gap:8px;margin-top:0!important}
.ep-venue-form .ep-vf-chips .fi-fo-checkbox-list-option-ctn{padding-top:0!important;width:auto}
.ep-venue-form .ep-vf-chips .fi-fo-checkbox-list-option{display:inline-flex;align-items:center;gap:0;min-height:32px;padding:0 14px;border-radius:999px;border:1px solid var(--ep-line);color:var(--ep-soft);cursor:pointer;transition:background .12s,border-color .12s,color .12s}
.ep-venue-form .ep-vf-chips .fi-fo-checkbox-list-option:hover{border-color:var(--ep-ring);color:var(--ep-text)}
.ep-venue-form .ep-vf-chips .fi-fo-checkbox-list-option .fi-checkbox-input{position:absolute;opacity:0;width:1px;height:1px;margin:0;pointer-events:none}
.ep-venue-form .ep-vf-chips .fi-fo-checkbox-list-option .fi-fo-checkbox-list-option-label{font-size:13px;font-weight:500;color:inherit}
.ep-venue-form .ep-vf-chips .fi-fo-checkbox-list-option:has(.fi-checkbox-input:checked){background:var(--ep-chip-bg);border-color:var(--ep-chip-bd);color:var(--ep-chip-tx)}
.ep-venue-form .ep-vf-chips .fi-fo-checkbox-list-option:has(.fi-checkbox-input:focus-visible){outline:2px solid var(--ep-chip-bd);outline-offset:2px}
</style>
</div>
