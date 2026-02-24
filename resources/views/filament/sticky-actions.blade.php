<style>
/* ─────────────────────────────────────────────────────────────────────────────
   Custom pages (.fi-form-actions) — full-width sticky footer bar
───────────────────────────────────────────────────────────────────────────── */
.fi-form-actions {
    position: sticky;
    bottom: 0;
    z-index: 10000;
    background-color: rgb(156 163 175); /* gray-400 */
    border-top: 1px solid rgba(0,0,0,0.1);
    padding: 0.5rem 1rem;
    border-radius: 0;
}
.dark .fi-form-actions {
    background-color: rgb(75 85 99); /* gray-600 */
}

/* ─────────────────────────────────────────────────────────────────────────────
   Filament 4 Schema forms (.fi-sc-actions) — fixed pill, bottom-right
───────────────────────────────────────────────────────────────────────────── */
.fi-sc-actions {
    position: fixed !important;
    bottom: 1rem !important;
    right: 3rem !important;
    height: auto !important;
    z-index: 10000 !important;
    width: 21rem !important;
    border-radius: 0.75rem !important;
    /* no border, no box-shadow, no margin-left */
    background-color: rgb(156 163 175) !important; /* fi-bg-color-400 */
    padding: 0.5rem !important;
}
.dark .fi-sc-actions {
    background-color: rgb(75 85 99) !important; /* fi-bg-color-600 */
}

/* Inner action wrappers — flex row, Save on right */
.fi-sc-actions > *,
.fi-sc-actions .fi-ac,
.fi-sc-actions .fi-ac-actions-ctn,
.fi-sc-actions .fi-ac-actions {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 0.5rem !important;
    width: 100% !important;
    justify-content: flex-end !important;
}

/* Put primary (Save) button on the far right using order */
.fi-sc-actions .fi-btn-color-primary,
.fi-sc-actions [data-color="primary"].fi-btn {
    order: 10 !important;
    /* White background, gray-600 text */
    background-color: #ffffff !important;
    color: rgb(75 85 99) !important;
    border: none !important;
    box-shadow: none !important;
}
.fi-sc-actions .fi-btn-color-primary:hover,
.fi-sc-actions [data-color="primary"].fi-btn:hover {
    background-color: rgb(249 250 251) !important;
}

/* Cancel / gray button — transparent, no border */
.fi-sc-actions .fi-btn-color-gray,
.fi-sc-actions [data-color="gray"].fi-btn {
    order: 1 !important;
    background-color: transparent !important;
    border: none !important;
    box-shadow: none !important;
    color: #ffffff !important;
}
.fi-sc-actions .fi-btn-color-gray:hover,
.fi-sc-actions [data-color="gray"].fi-btn:hover {
    background-color: rgba(255,255,255,0.12) !important;
}
</style>

<script>
(function () {
    'use strict';

    /* ── Floating quick-save button (visible when .fi-sc-actions is off-screen) ── */
    var floatBtn = document.getElementById('ep-float-save');
    if (!floatBtn) {
        floatBtn = document.createElement('div');
        floatBtn.id = 'ep-float-save';
        floatBtn.setAttribute('role', 'button');
        floatBtn.setAttribute('tabindex', '0');
        floatBtn.setAttribute('title', 'Salvează');
        floatBtn.style.cssText = [
            'display:none',
            'position:fixed',
            'bottom:1rem',
            'right:3rem',
            'z-index:10001',
            'align-items:center',
            'gap:0.5rem',
            'padding:0.5rem 1.25rem',
            'background:rgb(156 163 175)',
            'border-radius:0.75rem',
            'cursor:pointer',
            'font-size:0.875rem',
            'font-weight:600',
            'color:#fff',
            'box-shadow:0 8px 24px rgba(0,0,0,0.25)',
            'transition:transform 0.1s,box-shadow 0.15s',
        ].join(';');
        floatBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" style="width:1rem;height:1rem;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2M12 4v12m0 0-4-4m4 4 4-4"/></svg><span>Salvează</span>';
        document.body.appendChild(floatBtn);
    }

    var observer = null;

    function findPrimaryActions() {
        var all = document.querySelectorAll('.fi-sc-actions');
        return all.length ? all[all.length - 1] : null;
    }

    function findSaveButton(actionsEl) {
        if (!actionsEl) return null;
        return actionsEl.querySelector(
            '.fi-btn-color-primary, [data-color="primary"].fi-btn, ' +
            'button[type="submit"], button[wire\\:click*="save"], button[wire\\:click*="create"]'
        );
    }

    function triggerSave() {
        var actions = findPrimaryActions();
        var saveBtn = findSaveButton(actions);
        if (saveBtn) { saveBtn.click(); }
        else if (actions) { actions.scrollIntoView({ behavior: 'smooth', block: 'end' }); }
    }

    function setupObserver() {
        if (observer) { observer.disconnect(); observer = null; }
        var actions = findPrimaryActions();
        if (!actions || !floatBtn) return;

        /* .fi-sc-actions is now position:fixed so it's always "in viewport" visually.
           We observe it by checking if it's in the DOM and if there's a form on the page. */
        var hasActions = !!actions.querySelector('.fi-btn');

        /* Show float button only on pages that DON'T already have visible .fi-sc-actions
           (i.e. pages where it somehow fails to render) — for safety keep observer logic */
        observer = new IntersectionObserver(function (entries) {
            floatBtn.style.display = entries[0].isIntersecting ? 'none' : 'flex';
        }, { threshold: 0.1 });
        observer.observe(actions);
    }

    function init() {
        floatBtn.addEventListener('click', triggerSave);
        floatBtn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') triggerSave();
        });
        setTimeout(setupObserver, 400);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    document.addEventListener('livewire:navigated', function () { setTimeout(setupObserver, 400); });
    document.addEventListener('livewire:update', function () { setTimeout(setupObserver, 150); });
})();
</script>
