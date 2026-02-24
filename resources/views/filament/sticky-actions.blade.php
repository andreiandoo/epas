<style>
/* ─────────────────────────────────────────────────────────────────────────────
   Custom pages (.fi-form-actions) — full-width sticky footer bar
───────────────────────────────────────────────────────────────────────────── */
.fi-form-actions {
    position: sticky;
    bottom: 0;
    z-index: 10000;
    background: var(--fi-color-bg, #fff);
    border-top: 1px solid var(--fi-color-divider, #e5e7eb);
    padding: 0.75rem 1rem;
}

/* ─────────────────────────────────────────────────────────────────────────────
   #ep-float-save — floating fixed save button (bottom-right)
───────────────────────────────────────────────────────────────────────────── */
#ep-float-save {
    position: fixed;
    bottom: 1rem;
    right: 3rem;
    z-index: 10001;
    width: 21rem;
    display: none; /* shown by JS when form actions are present */
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    padding: 0.75rem 1.25rem;
    background: #009966;
    border-radius: 0.75rem;
    cursor: pointer;
    font-size: 1.25rem;
    font-weight: 700;
    color: #ffffff;
    box-shadow: 0 8px 24px rgba(0, 153, 102, 0.35);
    transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
    user-select: none;
    border: none;
    outline: none;
}
#ep-float-save:hover {
    background: #007a52;
    box-shadow: 0 12px 30px rgba(0, 153, 102, 0.45);
    transform: translateY(-1px);
}
#ep-float-save:active {
    transform: translateY(0);
    box-shadow: 0 4px 12px rgba(0, 153, 102, 0.3);
}

/* ── Pending state — animates while Livewire saves ── */
#ep-float-save.ep-pending {
    background: #007a52;
    cursor: wait;
    pointer-events: none;
    transform: none !important;
}
#ep-float-save.ep-pending .ep-save-icon {
    animation: ep-spin 0.8s linear infinite;
}
@keyframes ep-spin {
    to { transform: rotate(360deg); }
}

/* ── Success state — brief flash after save ── */
#ep-float-save.ep-success {
    background: #00cc88;
    pointer-events: none;
    transform: none !important;
}
</style>

<script>
(function () {
    'use strict';

    /* ── Create floating save button ── */
    var floatBtn = document.getElementById('ep-float-save');
    if (!floatBtn) {
        floatBtn = document.createElement('button');
        floatBtn.id = 'ep-float-save';
        floatBtn.type = 'button';
        floatBtn.title = 'Salvează modificările';
        document.body.appendChild(floatBtn);
    }

    var ICON_SAVE = '<svg class="ep-save-icon" xmlns="http://www.w3.org/2000/svg" style="width:1.25rem;height:1.25rem;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-8H7v8M7 3v5h8"/></svg>';
    var ICON_SPINNER = '<svg class="ep-save-icon" xmlns="http://www.w3.org/2000/svg" style="width:1.25rem;height:1.25rem;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3M4.22 4.22l2.12 2.12m11.32 11.32 2.12 2.12M3 12h3m12 0h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12"/></svg>';
    var ICON_CHECK = '<svg class="ep-save-icon" xmlns="http://www.w3.org/2000/svg" style="width:1.25rem;height:1.25rem;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';

    function setNormal() {
        floatBtn.classList.remove('ep-pending', 'ep-success');
        floatBtn.innerHTML = ICON_SAVE + '<span>Salvează</span>';
    }

    function setPending() {
        floatBtn.classList.add('ep-pending');
        floatBtn.classList.remove('ep-success');
        floatBtn.innerHTML = ICON_SPINNER + '<span>Se salvează…</span>';
    }

    function setSuccess() {
        floatBtn.classList.remove('ep-pending');
        floatBtn.classList.add('ep-success');
        floatBtn.innerHTML = ICON_CHECK + '<span>Salvat!</span>';
        setTimeout(setNormal, 1800);
    }

    function findSaveButton() {
        var actions = document.querySelectorAll('.fi-sc-actions');
        if (!actions.length) return null;
        var last = actions[actions.length - 1];
        return last.querySelector(
            '.fi-btn-color-primary, [data-color="primary"].fi-btn, ' +
            'button[type="submit"], button[wire\\:click*="save"], button[wire\\:click*="create"]'
        );
    }

    function onSaveClick() {
        var saveBtn = findSaveButton();
        if (saveBtn) {
            saveBtn.click();
        }
    }

    /* ── Show/hide based on whether the page has form actions ── */
    function syncVisibility() {
        var actions = document.querySelectorAll('.fi-sc-actions');
        var hasForm = false;
        actions.forEach(function (a) {
            if (a.querySelector('button')) hasForm = true;
        });
        floatBtn.style.display = hasForm ? 'flex' : 'none';
    }

    /* ── Livewire pending state ── */
    function setupLivewire() {
        if (typeof Livewire === 'undefined') return;
        Livewire.hook('commit', function (params) {
            var commit   = params.commit;
            var succeed  = params.succeed;
            var fail     = params.fail;

            var calls = (commit && commit.calls) ? commit.calls : [];
            var isSave = calls.some(function (c) {
                return c.method === 'save' ||
                       c.method === 'create' ||
                       c.method === 'saveAndGoBack' ||
                       c.method === 'saveAndCreateAnother';
            });

            if (!isSave) return;

            setPending();

            succeed(function () {
                setSuccess();
            });

            fail(function () {
                setNormal();
            });
        });
    }

    function init() {
        setNormal();
        floatBtn.addEventListener('click', onSaveClick);
        syncVisibility();
    }

    /* ── Boot ── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('livewire:init', setupLivewire);
    document.addEventListener('livewire:navigated', function () {
        setTimeout(syncVisibility, 400);
    });
    document.addEventListener('livewire:update', function () {
        setTimeout(syncVisibility, 150);
    });
})();
</script>
