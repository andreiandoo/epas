/**
 * Artist Account — Verify Email page handler
 * Auto-verifies if a token is in the URL; otherwise shows the "check your
 * email" prompt with a Resend button (rate-limited server-side).
 */
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    const email = params.get('email');

    const processingEl = document.getElementById('verify-processing');
    const pendingEl = document.getElementById('verify-pending');
    const resultEl = document.getElementById('verify-result');
    const resultContent = document.getElementById('verify-result-content');
    const emailText = document.getElementById('verify-email-text');
    const resendBtn = document.getElementById('resend-btn');

    if (email && emailText) {
        emailText.innerHTML = 'Ți-am trimis un link de verificare la <strong>' + escapeHtml(email) + '</strong>. Dă click pe el pentru a continua.';
    }

    // ---- Auto-verify flow when token is present ----
    if (token && email) {
        pendingEl.classList.add('hidden');
        processingEl.classList.remove('hidden');

        AmbiletAPI.artist.verifyEmail(email, token)
            .then((res) => {
                processingEl.classList.add('hidden');
                resultEl.classList.remove('hidden');

                if (res.success) {
                    resultContent.innerHTML = renderResult({
                        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                        color: 'green',
                        title: 'Email verificat!',
                        message: 'Adresa ta a fost confirmată. Cererea ta de cont artist este acum în review. Vei primi un email când contul va fi aprobat.',
                        cta: { href: '/artist/in-asteptare?email=' + encodeURIComponent(email), label: 'Vezi statusul cererii' }
                    });
                } else {
                    resultContent.innerHTML = renderResult({
                        icon: 'M6 18L18 6M6 6l12 12',
                        color: 'red',
                        title: 'Verificare eșuată',
                        message: res.message || 'Linkul este invalid sau a expirat.',
                        cta: { href: '#', label: 'Solicită un link nou', onclick: 'AmbiletArtistVerify.resend()' }
                    });
                }
            })
            .catch((err) => {
                processingEl.classList.add('hidden');
                resultEl.classList.remove('hidden');
                resultContent.innerHTML = renderResult({
                    icon: 'M6 18L18 6M6 6l12 12',
                    color: 'red',
                    title: 'Verificare eșuată',
                    message: err.message || 'Linkul este invalid sau a expirat.',
                    cta: { href: '#', label: 'Solicită un link nou', onclick: 'AmbiletArtistVerify.resend()' }
                });
            });
    }

    // ---- Resend button (no token, just the prompt) ----
    if (resendBtn) {
        resendBtn.addEventListener('click', () => resendVerification(email));
    }

    // ---- Globals so the result-state buttons can call resend ----
    window.AmbiletArtistVerify = {
        resend: () => {
            const e = email || prompt('Adresa ta de email:');
            if (!e) return;
            resendVerification(e);
        }
    };

    async function resendVerification(toEmail) {
        if (!toEmail) {
            AmbiletNotifications.error('Lipsește adresa de email.');
            return;
        }
        try {
            const res = await AmbiletAPI.artist.resendVerification(toEmail);
            if (res.success) {
                AmbiletNotifications.success('Email retrimis. Verifică inbox-ul.');
            } else {
                AmbiletNotifications.error(res.message || 'Nu s-a putut retrimite emailul.');
            }
        } catch (e) {
            // Surface the rate-limit message if the controller returned 429.
            AmbiletNotifications.error(e.message || 'Te rugăm să aștepți câteva momente înainte să retrimiți.');
        }
    }

    function renderResult({ icon, color, title, message, cta }) {
        const colors = {
            green: { bg: 'bg-green-100', text: 'text-green-600', btn: 'bg-green-600 hover:bg-green-700' },
            red: { bg: 'bg-red-100', text: 'text-red-600', btn: 'bg-red-600 hover:bg-red-700' },
        };
        const c = colors[color] || colors.green;
        return ''
            + '<div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full ' + c.bg + '">'
            + '<svg class="w-8 h-8 ' + c.text + '" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            + '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' + icon + '"/></svg>'
            + '</div>'
            + '<h2 class="text-2xl font-bold text-secondary">' + escapeHtml(title) + '</h2>'
            + '<p class="mt-2 text-muted">' + escapeHtml(message) + '</p>'
            + (cta ? '<a href="' + cta.href + '"' + (cta.onclick ? ' onclick="' + cta.onclick + '; return false;"' : '') + ' class="inline-block w-full px-6 py-3 mt-6 font-semibold text-white rounded-lg ' + c.btn + '">' + escapeHtml(cta.label) + '</a>' : '');
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
});
