/**
 * Artist Account — Login page handler
 * Inspects the structured 403 codes returned by the Laravel controller and
 * routes the user to the right place:
 *   email_not_verified -> /artist/verifica-email?email=...
 *   pending_approval   -> /artist/in-asteptare?email=...
 *   rejected           -> show inline rejection reason from response
 *   suspended          -> show inline suspended notice
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('artist-login-form');
    const statusEl = document.getElementById('login-status');

    /** Render an inline status banner above the form. */
    function showStatus(type, message) {
        if (!statusEl) return;
        const colors = {
            error: 'bg-red-50 text-red-700 border border-red-200',
            warning: 'bg-amber-50 text-amber-700 border border-amber-200',
            info: 'bg-blue-50 text-blue-700 border border-blue-200',
        };
        statusEl.className = 'p-3 mb-5 text-sm rounded-lg ' + (colors[type] || colors.info);
        statusEl.innerHTML = message;
        statusEl.classList.remove('hidden');
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Se conectează...';
        statusEl?.classList.add('hidden');

        const email = document.getElementById('email').value.trim().toLowerCase();
        const password = document.getElementById('password').value;

        try {
            const result = await AmbiletAuth.loginArtist(email, password);

            if (result.success) {
                AmbiletNotifications.success('Conectare reușită!');
                const redirect = AmbiletUtils.getUrlParam('redirect') || '/artist/cont/dashboard';
                setTimeout(() => window.location.href = redirect, 500);
                return;
            }

            // Branch on the structured error code (set by AmbiletAuth.loginArtist
            // from the controller's `errors.code`).
            switch (result.code) {
                case 'email_not_verified':
                    setTimeout(() => {
                        window.location.href = '/artist/verifica-email?email=' + encodeURIComponent(email);
                    }, 800);
                    showStatus('warning', 'Verifică-ți emailul înainte de conectare. Te redirecționăm…');
                    break;

                case 'pending_approval':
                    setTimeout(() => {
                        window.location.href = '/artist/in-asteptare?email=' + encodeURIComponent(email);
                    }, 800);
                    showStatus('info', 'Cererea ta este încă în review. Te redirecționăm…');
                    break;

                case 'rejected':
                    showStatus('error',
                        '<strong>Cererea ta a fost respinsă.</strong>'
                        + (result.reason ? '<br><span class="text-xs">Motiv: ' + escapeHtml(result.reason) + '</span>' : '')
                        + '<br><a href="mailto:contact@ambilet.ro" class="underline">Contactează echipa</a> pentru clarificări.'
                    );
                    btn.disabled = false;
                    btn.textContent = originalText;
                    break;

                case 'suspended':
                    showStatus('error', '<strong>Contul tău este suspendat.</strong><br>Contactează echipa la <a href="mailto:contact@ambilet.ro" class="underline">contact@ambilet.ro</a> pentru reactivare.');
                    btn.disabled = false;
                    btn.textContent = originalText;
                    break;

                default:
                    AmbiletNotifications.error(result.message || 'Email sau parolă incorectă');
                    btn.disabled = false;
                    btn.textContent = originalText;
            }
        } catch (error) {
            AmbiletNotifications.error('Eroare la conectare. Încearcă din nou.');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
