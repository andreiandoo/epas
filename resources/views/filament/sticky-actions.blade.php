<style>
/* Custom pages (.fi-form-actions) — sticky footer bar */
.fi-form-actions {
    position: sticky;
    bottom: 0;
    z-index: 10000;
    background: var(--fi-color-bg, #fff);
    border-top: 1px solid var(--fi-color-divider, #e5e7eb);
    padding: 0.75rem 1rem;
}

/* Filament 4 Schema forms (.fi-sc-actions) — compact sticky pill, right-aligned */
.fi-sc-actions {
    position: sticky;
    bottom: 1rem;
    z-index: 10000;
    width: fit-content;
    margin-left: auto;
    padding: 0.5rem 0.75rem;
    background: var(--fi-color-bg, #ffffff);
    border: 1px solid var(--fi-color-divider, #e5e7eb);
    border-radius: 0.75rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12), 0 1px 3px rgba(0, 0, 0, 0.08);
}

/* Floating quick-save bar (injected by JS when .fi-sc-actions is off-screen) */
#ep-float-save {
    position: fixed;
    bottom: 1rem;
    right: 1rem;
    z-index: 10000;
    display: none;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--fi-color-bg, #ffffff);
    border: 1px solid var(--fi-color-divider, #e5e7eb);
    border-radius: 0.75rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18), 0 2px 6px rgba(0, 0, 0, 0.10);
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--fi-primary-color, #4f46e5);
    transition: box-shadow 0.15s, transform 0.1s;
}
#ep-float-save:hover {
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.22), 0 2px 8px rgba(0, 0, 0, 0.12);
    transform: translateY(-1px);
}
#ep-float-save svg {
    width: 1rem;
    height: 1rem;
    flex-shrink: 0;
}
</style>

{{-- Floating save button (visible only when .fi-sc-actions is off-screen) --}}
<div id="ep-float-save" role="button" tabindex="0" title="Salvează">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2M12 4v12m0 0-4-4m4 4 4-4"/>
    </svg>
    <span>Salvează</span>
</div>

<script>
(function () {
    'use strict';

    var floatBtn = document.getElementById('ep-float-save');
    var observer = null;

    function findPrimaryActions() {
        // Find all .fi-sc-actions elements — return the last visible one in a form
        var all = document.querySelectorAll('.fi-sc-actions');
        if (!all.length) return null;
        // Prefer the last one (main form actions are typically last)
        return all[all.length - 1];
    }

    function findSaveButton(actionsEl) {
        if (!actionsEl) return null;
        // Look for submit button or wire:click="save" button
        return actionsEl.querySelector(
            'button[type="submit"], button[wire\\:click*="save"], button[wire\\:click*="create"]'
        ) || actionsEl.querySelector('button:not([wire\\:click*="cancel"]):not([wire\\:click*="back"])');
    }

    function triggerSave() {
        var actions = findPrimaryActions();
        var saveBtn = findSaveButton(actions);
        if (saveBtn) {
            saveBtn.click();
        } else if (actions) {
            // Scroll to actions as fallback
            actions.scrollIntoView({ behavior: 'smooth', block: 'end' });
        }
    }

    function setupObserver() {
        if (observer) {
            observer.disconnect();
            observer = null;
        }

        var actions = findPrimaryActions();
        if (!actions || !floatBtn) return;

        observer = new IntersectionObserver(function (entries) {
            var isVisible = entries[0].isIntersecting;
            floatBtn.style.display = isVisible ? 'none' : 'flex';
        }, {
            threshold: 0.5,
        });

        observer.observe(actions);
    }

    function init() {
        if (!floatBtn) return;
        floatBtn.addEventListener('click', triggerSave);
        floatBtn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') triggerSave();
        });
        // Small delay to allow Livewire to render form
        setTimeout(setupObserver, 300);
    }

    // Initial load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-init after Livewire navigation
    document.addEventListener('livewire:navigated', function () {
        setTimeout(setupObserver, 300);
    });

    // Re-init after Livewire updates (e.g. tab switch renders new form)
    document.addEventListener('livewire:update', function () {
        setTimeout(setupObserver, 100);
    });
})();
</script>
