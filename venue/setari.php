<?php
/**
 * Venue Owner — /venue/setari
 * Profile (read-only) + change password form.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle       = 'Setări cont';
$venuePageTitle  = 'Setări';
$bodyClass       = 'min-h-screen flex bg-slate-100';
$currentPage     = 'venue_setari';
$cssBundle       = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/venue-sidebar.php';
?>

    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/venue-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-900">Setări cont</h2>
                <p class="mt-1 text-sm text-slate-500">Informațiile contului tău de venue owner.</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Profile card -->
                <div class="p-6 bg-white border rounded-2xl border-slate-200">
                    <h3 class="mb-4 text-lg font-bold text-slate-900">Profil</h3>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Nume</dt>
                            <dd id="setari-name" class="mt-1 text-base font-medium text-slate-900">—</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Email</dt>
                            <dd id="setari-email" class="mt-1 text-base font-medium text-slate-900">—</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cont</dt>
                            <dd id="setari-tenant" class="mt-1 text-base font-medium text-slate-900">—</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Locații asociate</dt>
                            <dd id="setari-venues" class="mt-1 text-base font-medium text-slate-900">—</dd>
                        </div>
                    </dl>
                </div>

                <!-- Change password card -->
                <div class="p-6 bg-white border rounded-2xl border-slate-200">
                    <h3 class="mb-4 text-lg font-bold text-slate-900">Schimbă parola</h3>
                    <form id="pwd-form" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Parola curentă</label>
                            <input type="password" id="pwd-current" required
                                   class="mt-1 block w-full px-3 py-2 text-sm bg-white border rounded-lg border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Parola nouă</label>
                            <input type="password" id="pwd-new" required minlength="8"
                                   class="mt-1 block w-full px-3 py-2 text-sm bg-white border rounded-lg border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <p class="mt-1 text-xs text-slate-400">Minim 8 caractere.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Confirmă parola nouă</label>
                            <input type="password" id="pwd-confirm" required minlength="8"
                                   class="mt-1 block w-full px-3 py-2 text-sm bg-white border rounded-lg border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>
                        <div id="pwd-message" class="hidden text-sm"></div>
                        <button type="submit" id="pwd-submit"
                                class="w-full px-4 py-2.5 text-sm font-semibold text-white rounded-lg transition-colors" style="background:#3b82f6;">
                            Schimbă parola
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => (async function () {
    if (typeof AmbiletVenueAPI === 'undefined') return;

    // ── Load identity ──────────────────────────────────────────
    try {
        const res = await AmbiletVenueAPI.me();
        if (res && res.success && res.data) {
            const u = res.data.venue_owner || res.data;
            const name  = u.name || [u.first_name, u.last_name].filter(Boolean).join(' ') || u.email || '—';
            const email = u.email || '—';

            // tenant is an object { id, name, public_name, type } — pick a
            // sensible string. Fall back to raw name/email if it's missing.
            let tenantLabel = '—';
            if (u.tenant && typeof u.tenant === 'object') {
                tenantLabel = u.tenant.public_name || u.tenant.name || '—';
            } else if (typeof u.tenant === 'string') {
                tenantLabel = u.tenant;
            }

            const venuesCount = Array.isArray(u.venues) ? u.venues.length : (u.venue_count || 0);
            const venuesLabel = venuesCount + ' ' + (venuesCount === 1 ? 'locație' : 'locații');

            document.getElementById('setari-name').textContent = name;
            document.getElementById('setari-email').textContent = email;
            document.getElementById('setari-tenant').textContent = tenantLabel;
            document.getElementById('setari-venues').textContent = venuesLabel;
        }
    } catch (e) {
        console.error('me load failed', e);
    }

    // ── Change password ────────────────────────────────────────
    const form = document.getElementById('pwd-form');
    const msg  = document.getElementById('pwd-message');
    const btn  = document.getElementById('pwd-submit');

    function showMessage(text, kind) {
        msg.textContent = text;
        msg.className = 'text-sm p-3 rounded-lg ' + (kind === 'success'
            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
            : 'bg-red-50 text-red-600 border border-red-200');
        msg.classList.remove('hidden');
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        msg.classList.add('hidden');

        const current = document.getElementById('pwd-current').value;
        const next    = document.getElementById('pwd-new').value;
        const confirm = document.getElementById('pwd-confirm').value;

        if (next !== confirm) {
            showMessage('Parola nouă și confirmarea nu se potrivesc.', 'error');
            return;
        }
        if (next.length < 8) {
            showMessage('Parola nouă trebuie să aibă minim 8 caractere.', 'error');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Se schimbă…';

        try {
            const res = await AmbiletVenueAPI.changePassword(current, next, confirm);
            if (res && res.success) {
                showMessage('Parola a fost schimbată cu succes. Alte dispozitive vor fi deconectate.', 'success');
                form.reset();
            } else {
                showMessage((res && res.message) || 'Eroare la schimbare parolei.', 'error');
            }
        } catch (e) {
            console.error(e);
            showMessage('Eroare la schimbarea parolei.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Schimbă parola';
        }
    });
})());
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
