const ForgotPage = {
    async submit(event) {
        event.preventDefault();
        const email = document.getElementById('emailInput').value;
        const btn = document.getElementById('submitBtn');

        btn.disabled = true;
        btn.textContent = 'Se trimite...';

        try {
            const response = await AmbiletAPI.post('/customer/forgot-password', { email });

            if (response.success) {
                document.getElementById('sentEmail').textContent = email;
                document.getElementById('requestForm').classList.add('hidden');
                document.getElementById('successState').classList.remove('hidden');
            } else {
                if (typeof AmbiletNotifications !== 'undefined') {
                    AmbiletNotifications.error(response.message || 'A aparut o eroare. Incearca din nou.');
                }
                btn.disabled = false;
                btn.textContent = 'Trimite link de resetare';
            }
        } catch (error) {
            // Even on error, show success to prevent email enumeration
            document.getElementById('sentEmail').textContent = email;
            document.getElementById('requestForm').classList.add('hidden');
            document.getElementById('successState').classList.remove('hidden');
        }
    },

    async resend() {
        const btn = document.getElementById('resendBtn');
        const email = document.getElementById('sentEmail').textContent;

        btn.disabled = true;
        btn.textContent = 'Se trimite...';

        try {
            await AmbiletAPI.post('/customer/forgot-password', { email });
        } catch (e) {}

        btn.textContent = 'Email retrimis!';
        btn.classList.add('bg-success/10', 'text-success', 'border-success');

        setTimeout(() => {
            btn.disabled = false;
            btn.textContent = 'Retrimite email-ul';
            btn.classList.remove('bg-success/10', 'text-success', 'border-success');
        }, 30000);
    }
};
