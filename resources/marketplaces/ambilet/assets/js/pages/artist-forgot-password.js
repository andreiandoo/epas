/**
 * Artist Account — Forgot Password page handler
 * The Laravel controller always returns success (anti-enumeration), so we
 * just swap to the success state regardless of whether the email exists.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('artist-forgot-form');
    const formWrap = document.getElementById('forgot-form-wrap');
    const successWrap = document.getElementById('forgot-success');
    const sentEmailEl = document.getElementById('sentEmail');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Se trimite...';

        const email = document.getElementById('email').value.trim().toLowerCase();

        try {
            await AmbiletAPI.artist.forgotPassword(email);
            if (sentEmailEl) sentEmailEl.textContent = email;
            formWrap.classList.add('hidden');
            successWrap.classList.remove('hidden');
        } catch (error) {
            // Even on error, the API is intentionally vague — surface a friendly message.
            AmbiletNotifications.error('Eroare la trimitere. Încearcă din nou.');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
});
