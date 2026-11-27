{{-- Aspect comun pentru listele Evenimente si Locatii; totul sub .ep-lst (clasa paginii), nimic global --}}
<div class="ep-lst-style" aria-hidden="true">
<style>
.ep-lst-style,.fi-grid-col:has(> .ep-lst-style){display:none}

.ep-lst{--l-text:#111827;--l-soft:#4b5563;--l-muted:#6b7280;--l-faint:#9ca3af;--l-line:#e5e7eb;--l-hover:#f3f4f6;--l-on:#eceff3;--l-ctl:#ffffff;--l-ctl-bd:#d1d5db;--l-ok-bg:rgba(16,185,129,.12);--l-ok:#047857;--l-warn-bg:rgba(245,158,11,.14);--l-warn:#b45309;--l-bad-bg:rgba(239,68,68,.10);--l-bad:#b91c1c;--l-mute-bg:#f3f4f6;--l-mute:#4b5563;--l-track:#e5e7eb;--l-menu:#ffffff;--l-menu-hover:#f3f4f6;--l-sel-bg:rgba(59,130,246,.07);--l-sel-bd:#bfdbfe}
.dark .ep-lst{--l-text:#e6e9ee;--l-soft:#aab3c0;--l-muted:#8b95a3;--l-faint:#6b7480;--l-line:#232a35;--l-hover:#151a21;--l-on:#1d232c;--l-ctl:#171c23;--l-ctl-bd:#2c3441;--l-ok-bg:rgba(16,185,129,.12);--l-ok:#34d399;--l-warn-bg:rgba(245,158,11,.14);--l-warn:#fcd34d;--l-bad-bg:rgba(248,113,113,.12);--l-bad:#fca5a5;--l-mute-bg:#1d232c;--l-mute:#aab3c0;--l-track:#232a35;--l-menu:#1a2029;--l-menu-hover:#242b36;--l-sel-bg:rgba(59,130,246,.10);--l-sel-bd:#23406a}

/* antet: titlu + numar, actiuni la dreapta */
.ep-lst .fi-header{display:flex;flex-direction:row;flex-wrap:wrap;align-items:center;gap:12px}
.ep-lst .fi-header-heading{display:flex;align-items:baseline;gap:12px;font-size:26px;line-height:1.2;font-weight:700;letter-spacing:-.01em}
.ep-lst .ep-lst-count{font-size:14px;font-weight:500;color:var(--l-muted);font-variant-numeric:tabular-nums}
.ep-lst .fi-header-actions-ctn{margin-inline-start:auto}
.ep-lst .fi-header-actions-ctn .fi-btn{height:34px;border-radius:8px;font-size:13px;font-weight:600}
.ep-lst .fi-header-actions-ctn .fi-btn.fi-color-gray{background:transparent;box-shadow:none;font-weight:500;color:var(--l-soft)}
.ep-lst .fi-header-actions-ctn .fi-btn.fi-color-gray:hover{background:var(--l-hover);color:var(--l-text)}

/* bara tabelului: vederi la stanga, cautare/filtre/coloane la dreapta */
.ep-lst .fi-ta-header-toolbar{flex-wrap:wrap;align-items:center;gap:10px 16px}
.ep-lst .fi-ta-header-toolbar>.ep-qv{order:0}
.ep-lst .fi-ta-header-toolbar>.fi-ta-actions{order:1}
.ep-lst .fi-ta-header-toolbar>div:not(.fi-ta-actions):not(.ep-qv){order:2;display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-inline-start:auto}
.ep-lst .fi-ta-search-field{min-width:260px}
.ep-lst .fi-ta-search-field .fi-input-wrp{border-radius:8px}
.ep-lst .fi-ta-header-toolbar .fi-btn{height:34px;border-radius:8px;font-size:13px;font-weight:500;padding-inline:12px}
.ep-lst .fi-ta-header-toolbar .fi-ta-col-manager-dropdown .fi-btn{background:transparent;box-shadow:none;color:var(--l-soft)}
.ep-lst .fi-ta-header-toolbar .fi-ta-col-manager-dropdown .fi-btn:hover{background:var(--l-hover);color:var(--l-text)}

/* vederi rapide */
.ep-qv{display:flex;flex-wrap:wrap;align-items:center;gap:4px}
.ep-qv .ep-view,.ep-todo .ep-view{display:inline-flex;align-items:center;gap:7px;height:32px;padding:0 12px;border:0;border-radius:8px;background:transparent;color:var(--l-muted);font-size:13px;font-weight:500;line-height:1;cursor:pointer;white-space:nowrap}
.ep-qv .ep-view:hover{color:var(--l-text)}
.ep-qv .ep-view.on{background:var(--l-on);color:var(--l-text)}
.ep-qv .ep-view:focus-visible,.ep-menu button:focus-visible{outline:2px solid #3b82f6;outline-offset:1px}
.ep-qv .n{font-size:12px;color:var(--l-faint);font-variant-numeric:tabular-nums}
.ep-qv .ep-view.on .n{color:var(--l-soft)}
.ep-todo{position:relative}
.ep-menu{position:absolute;left:0;top:38px;z-index:30;display:flex;flex-direction:column;width:220px;padding:6px;border:1px solid var(--l-ctl-bd);border-radius:10px;background:var(--l-menu);box-shadow:0 16px 36px rgba(0,0,0,.25)}
.ep-menu button{display:flex;align-items:center;justify-content:space-between;height:32px;padding:0 10px;border:0;border-radius:6px;background:transparent;color:var(--l-text);font-size:13px;font-weight:500;cursor:pointer}
.ep-menu button:hover,.ep-menu button.on{background:var(--l-menu-hover)}
.ep-menu .n{font-size:12px;color:var(--l-muted);font-variant-numeric:tabular-nums}

/* selectia */
.ep-lst .fi-ta-selection-indicator{border:1px solid var(--l-sel-bd);background:var(--l-sel-bg);border-radius:10px;margin:0 14px 10px}

/* antetul tabelului */
.ep-lst .fi-ta-header-cell{padding-block:10px}
.ep-lst .fi-ta-header-cell-label,.ep-lst .fi-ta-header-cell .fi-ta-header-cell-sort-btn{font-size:12px;font-weight:500;text-transform:none}
.ep-lst tr.event-row-active>td,.ep-lst tr.event-row-ended>td,.ep-lst tr.event-row-unpublished>td,.ep-lst tr.event-row-cancelled>td,
.dark .ep-lst tr.event-row-active>td,.dark .ep-lst tr.event-row-ended>td,.dark .ep-lst tr.event-row-unpublished>td,.dark .ep-lst tr.event-row-cancelled>td{background-color:transparent!important}
.ep-lst .fi-ta-row:hover>td{background-color:var(--l-hover)!important}

/* celule: titlu si rand secundar */
.ep-lst .ep-sub{margin-top:3px;font-size:12px;color:var(--l-muted)}
.ep-lst .ep-nowrap{white-space:nowrap}
.ep-lst .ep-alert{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;margin-inline-start:8px;border-radius:999px;background:#dc2626;color:#fff;font-size:11px;font-weight:700;line-height:1;vertical-align:middle;box-sizing:border-box}

/* disponibil: bara + rest/cota */
.ep-lst .ep-cap{display:flex;align-items:center;gap:6px;min-width:104px}
.ep-lst .ep-bar{flex:1;max-width:48px;height:5px;border-radius:999px;background:var(--l-track);overflow:hidden}
.ep-lst .ep-bar-fill{height:100%;border-radius:999px}
.ep-lst .ep-cap-n{font-size:12px;color:var(--l-muted);font-variant-numeric:tabular-nums;white-space:nowrap}

/* pastile de stare */
.ep-lst .ep-stare{display:flex;flex-wrap:wrap;gap:4px}
.ep-lst .ep-pill{display:inline-flex;align-items:center;gap:5px;height:22px;padding:0 8px;border-radius:999px;font-size:12px;font-weight:500;line-height:1;white-space:nowrap}
.ep-lst .ep-pill--ok{background:var(--l-ok-bg);color:var(--l-ok)}
.ep-lst .ep-pill--warn{background:var(--l-warn-bg);color:var(--l-warn)}
.ep-lst .ep-pill--bad{background:var(--l-bad-bg);color:var(--l-bad)}
.ep-lst .ep-pill--mute{background:var(--l-mute-bg);color:var(--l-mute)}
.ep-lst .ep-dot{width:6px;height:6px;border-radius:999px;background:currentColor}

/* locatii: avatar + nume + oras */
.ep-lst .ep-venue{display:flex;align-items:center;gap:12px;min-width:240px}
.ep-lst .ep-av{display:inline-flex;align-items:center;justify-content:center;flex:none;width:40px;height:40px;border-radius:8px;background:var(--l-mute-bg);color:var(--l-mute);font-size:13px;font-weight:600;overflow:hidden}
.ep-lst .ep-av img{width:100%;height:100%;object-fit:cover;display:block}
.ep-lst .ep-venue-name{font-weight:600}

/* actiunile de rand apar la hover / focus (doar pe dispozitive cu hover); aprobarea ramane vizibila */
@media (hover:hover){
  .ep-lst .fi-ta-row:not(:hover):not(:focus-within) .fi-ta-actions .fi-icon-btn:not(.fi-color-success):not(.fi-color-danger){opacity:0}
  .ep-lst .fi-ta-row .fi-ta-actions .fi-icon-btn{transition:opacity .12s}
}

/* evenimente: poster + titlu lat, iconita harta, data mica cu ziua in majuscule */
.ep-lst .ep-title-cell{white-space:normal;overflow-wrap:anywhere}
.ep-lst .ep-ev{display:flex;align-items:flex-start;gap:12px}
.ep-lst .ep-ev-body{min-width:0;flex:1}
.ep-lst .ep-poster{display:block;flex:none;width:36px;height:48px;border-radius:6px;background:var(--l-mute-bg);overflow:hidden}
.ep-lst .ep-poster img{width:100%;height:100%;object-fit:cover;display:block}
.ep-lst .ep-seat{display:inline-flex;vertical-align:middle;margin-inline-start:6px;color:#10b981}
.ep-lst .ep-seat svg{width:15px;height:15px}
.ep-lst .ep-date,.ep-lst .ep-date *{font-size:12px}
.ep-lst .ep-date .fi-ta-text-description,.ep-lst .ep-date [class*=description]{font-size:10.5px;letter-spacing:.06em;color:var(--l-muted)}
/* total in fata filtrelor active */
.ep-lst .ep-lst-total{font-size:13px;color:var(--l-soft);white-space:nowrap}
.ep-lst .ep-lst-total strong{color:var(--l-text);font-weight:600}
.ep-lst .ep-lst-total-sep{color:var(--l-faint);margin-inline:6px}

/* total + filtre active pe acelasi ax */
.ep-lst .fi-ta-filter-indicators>div{display:flex;flex-wrap:wrap;align-items:center;gap:6px 8px}
.ep-lst .fi-ta-filter-indicators .ep-lst-total,.ep-lst .fi-ta-filter-indicators .ep-lst-total-sep,.ep-lst .fi-ta-filter-indicators-label{display:inline-flex;align-items:center;line-height:1;margin:0}
.ep-lst .fi-ta-filter-indicators .ep-lst-total strong{margin-inline-start:4px}

/* coloana de actiuni: doar cat ii trebuie */
.ep-lst .fi-ta-table td:last-child,.ep-lst .fi-ta-table th:last-child{width:1%;white-space:nowrap}

/* bara de jos: rezumat stanga, pe pagina + paginatie dreapta, butoane fara chenar */
.ep-lst .fi-pagination{display:flex!important;flex-wrap:wrap;align-items:center;gap:10px 12px;padding:12px 14px}
.ep-lst .fi-pagination-overview{margin-inline-end:auto;font-size:13px;font-weight:400;color:var(--l-muted)}
.ep-lst .fi-pagination-records-per-page-select-ctn{justify-self:auto}
.ep-lst .fi-pagination-records-per-page-select .fi-input-wrp{min-height:30px;border-radius:8px;box-shadow:none;border:1px solid var(--l-ctl-bd);background:var(--l-ctl)}
.ep-lst .fi-pagination-records-per-page-select .fi-input-wrp-label{font-size:12px;color:var(--l-muted)}
.ep-lst .fi-pagination-records-per-page-select .fi-select-input{padding-block:4px;font-size:13px}
.ep-lst .fi-pagination-items{display:flex;gap:4px;border:0!important;box-shadow:none!important;background:transparent!important;border-radius:0!important;overflow:visible!important}
.ep-lst .fi-pagination-item{border:0!important;background:transparent!important}
.ep-lst .fi-pagination-item-btn{display:inline-flex;align-items:center;justify-content:center;height:30px;min-width:30px;padding:0 9px;border-radius:8px;background:transparent!important;color:var(--l-soft);font-size:13px;font-weight:500}
.ep-lst .fi-pagination-item-btn:hover{background:var(--l-hover)!important;color:var(--l-text)}
.ep-lst .fi-pagination-item.fi-active .fi-pagination-item-btn{background:var(--l-on)!important;color:var(--l-text)}
.ep-lst .fi-pagination-item-label{color:inherit}

/* epas-skin.css face celulele de titlu/nume display:block (ies din grila tabelului si se suprapun peste coloana urmatoare) */
.ep-lst td.fi-ta-cell.fi-ta-cell-title,.ep-lst td.fi-ta-cell.fi-ta-cell-name{display:table-cell!important}
/* titlul ia tot spatiul ramas; restul coloanelor au latimi fixe */
.ep-lst .fi-ta-header-cell-title,.ep-lst .fi-ta-header-cell-name{width:100%}
.ep-lst .fi-ta-cell-title{min-width:420px}
.ep-lst .fi-ta-header-cell-event-date,.ep-lst .fi-ta-cell-event-date{width:120px}
.ep-lst [class*=fi-ta-header-cell-marketplace-organizer],.ep-lst [class*=fi-ta-cell-marketplace-organizer]{width:150px}
.ep-lst .fi-ta-header-cell-general-quota,.ep-lst .fi-ta-cell-general-quota{width:130px}
.ep-lst .fi-ta-header-cell-stare,.ep-lst .fi-ta-cell-stare{width:180px}
.ep-lst [class*=fi-ta-header-cell-tics-listing],.ep-lst [class*=fi-ta-cell-tics-listing]{width:100px}
.ep-lst .ep-av--round{border-radius:999px}
/* facturi: celula Organizator / Eveniment / Perioada ia spatiul ramas */
.ep-lst .fi-ta-header-cell-organizer\.name{width:100%}
/* bilete: codul in monospace, locul ca eticheta separata cu pin */
.ep-lst td.fi-ta-cell-code .fi-ta-text-item{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;font-weight:600;letter-spacing:.04em;color:var(--l-text)}
.ep-lst td.fi-ta-cell-code .fi-ta-text-description{display:inline-flex;width:fit-content;align-items:center;gap:5px;margin-top:5px;padding:2px 8px 2px 6px;border-radius:6px;background:var(--l-mute-bg);color:var(--l-soft);font-size:11.5px;line-height:1.5}
.ep-lst td.fi-ta-cell-code .fi-ta-text-description::before{content:"";width:11px;height:11px;flex:none;background:currentColor;-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z'/%3E%3Ccircle cx='12' cy='10' r='2.5'/%3E%3C/svg%3E") center/contain no-repeat;mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z'/%3E%3Ccircle cx='12' cy='10' r='2.5'/%3E%3C/svg%3E") center/contain no-repeat}
</style>
</div>
