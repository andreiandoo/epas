document.addEventListener('DOMContentLoaded', function () {
    // Phone field: only allow digits, +, spaces
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.setAttribute('inputmode', 'tel');
        phoneInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^\d+\s]/g, '');
        });
        phoneInput.addEventListener('paste', function (e) {
            setTimeout(() => {
                this.value = this.value.replace(/[^\d+\s]/g, '');
            }, 0);
        });
    }

    // Form submit
    document.getElementById('register-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn = e.target.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Se creeazÄƒ contul...';

        const formData = {
            first_name: document.getElementById('first_name').value.trim(),
            last_name: document.getElementById('last_name').value.trim(),
            email: document.getElementById('email').value.trim(),
            phone: document.getElementById('phone').value.replace(/\s/g, ''),
            password: document.getElementById('password').value,
            password_confirmation: document.getElementById('password_confirmation').value
        };

        if (formData.password !== formData.password_confirmation) {
            BileteOnlineNotifications.error('Parolele nu coincid');
            btn.disabled = false;
            btn.textContent = originalText;
            return;
        }

        // Validate phone: if provided, must be digits only (after stripping spaces)
        if (formData.phone && !/^\+?\d{7,15}$/.test(formData.phone)) {
            BileteOnlineNotifications.error('NumÄƒrul de telefon trebuie sÄƒ conÈ›inÄƒ doar cifre (7-15 cifre)');
            btn.disabled = false;
            btn.textContent = originalText;
            return;
        }

        try {
            const result = await BileteOnlineAuth.register(formData);
            if (result.success) {
                BileteOnlineNotifications.success('Cont creat cu succes!');
                // CAPI CompleteRegistration â€” fires once on successful signup
                try {
                    if (window.EPASTracking && typeof EPASTracking.trackSignUp === 'function') {
                        EPASTracking.trackSignUp('email', { email: formData.email });
                    }
                } catch (e) { /* never break signup */ }
                // Redirect to email verification page (user is already auto-logged-in)
                setTimeout(() => window.location.href = '/verify-email', 1500);
            } else {
                BileteOnlineNotifications.error(result.message || 'Eroare la Ã®nregistrare');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        } catch (error) {
            BileteOnlineNotifications.error('Eroare la Ã®nregistrare. ÃŽncearcÄƒ din nou.');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
});
