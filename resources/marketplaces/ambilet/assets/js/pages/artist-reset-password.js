/**
 * Artist Account — Reset Password page handler
 * Reads `email` and `token` from the URL query string and POSTs to
 * /artist/reset-password with the new password.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('artist-reset-form');
    const errorEl = document.getElementById('reset-error');
    const successEl = document.getElementById('reset-success');

    const params = new URLSearchParams(window.location.search);
    const email = params.get('email') || '';
    const token = params.get('token') || '';

    if (!email || !token) {
        errorEl.textContent = 'Link de resetare invalid. Lipsește emailul sau token-ul.';
        errorEl.classList.remove('hidden');
        form.querySelectorAll('input,button').forEach(el => el.disabled = true);
        return;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorEl.classList.add('hidden');
        successEl.classList.add('hidden');

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Se resetează...';

        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('password_confirmation').value;

        if (password !== passwordConfirm) {
            errorEl.textContent = 'Parolele nu coincid';
            errorEl.classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = originalText;
            return;
        }

        try {
            const res = await AmbiletAPI.artist.resetPassword({
                email,
                token,
                password,
                password_confirmation: passwordConfirm
            });

            if (res.success) {
                successEl.textContent = 'Parolă resetată. Te redirecționăm către pagina de autentificare…';
                successEl.classList.remove('hidden');
                setTimeout(() => window.location.href = '/artist/login', 1500);
                return;
            }

            errorEl.textContent = res.message || 'Token invalid sau expirat.';
            errorEl.classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = originalText;
        } catch (error) {
            errorEl.textContent = error.message || 'Token invalid sau expirat. Solicită un nou link.';
            errorEl.classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
});
