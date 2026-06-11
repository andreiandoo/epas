document.addEventListener('DOMContentLoaded', async function () {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    const email = params.get('email');

    // If token + email present, process verification
    if (token && email) {
        document.getElementById('verify-pending').classList.add('hidden');
        document.getElementById('verify-processing').classList.remove('hidden');

        try {
            const response = await AmbiletAPI.customer.verifyEmail(token, email);
            document.getElementById('verify-processing').classList.add('hidden');
            document.getElementById('verify-result').classList.remove('hidden');

            if (response.success) {
                // Update stored customer data if logged in
                if (response.data && response.data.customer && AmbiletAuth.isLoggedIn()) {
                    localStorage.setItem(AmbiletAuth.KEYS.CUSTOMER_DATA, JSON.stringify(response.data.customer));
                }

                document.getElementById('verify-result-content').innerHTML = `
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-success/10">
                        <svg class="w-8 h-8 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-secondary">Email verificat!</h2>
                    <p class="mt-2 mb-6 text-muted">Contul tău este acum complet activ. Bucură-te de experiența completă!</p>
                    <a href="/cont" class="inline-block w-full btn btn-primary bg-primary btn-lg">Mergi la contul tău</a>
                `;
            } else {
                document.getElementById('verify-result-content').innerHTML = `
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-error/10">
                        <svg class="w-8 h-8 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-secondary">Verificare eșuată</h2>
                    <p class="mt-2 mb-6 text-muted">${response.message || 'Linkul de verificare este invalid sau a expirat.'}</p>
                    <button onclick="resendVerification()" class="inline-block w-full mb-3 btn btn-primary bg-primary btn-lg">Retrimite emailul de verificare</button>
                    <a href="/cont" class="block w-full text-center btn btn-lg bg-surface text-secondary">Mergi la contul tău</a>
                `;
            }
        } catch (error) {
            document.getElementById('verify-processing').classList.add('hidden');
            document.getElementById('verify-result').classList.remove('hidden');
            document.getElementById('verify-result-content').innerHTML = `
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-error/10">
                    <svg class="w-8 h-8 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-secondary">Eroare</h2>
                <p class="mt-2 mb-6 text-muted">A apărut o eroare la verificare. Te rugăm să încerci din nou.</p>
                <a href="/cont" class="inline-block w-full btn btn-primary bg-primary btn-lg">Mergi la contul tău</a>
            `;
        }
        return;
    }

    // Pending view: personalize with user email if available
    const user = AmbiletAuth.isLoggedIn() ? AmbiletAuth.getUser() : null;
    if (user && user.email) {
        document.getElementById('verify-email-text').innerHTML =
            `Ți-am trimis un email de verificare la <strong>${user.email}</strong>. Dă click pe linkul din email pentru a activa contul.`;
    }
});

async function resendVerification() {
    const btn = document.getElementById('resend-btn');
    const user = AmbiletAuth.isLoggedIn() ? AmbiletAuth.getUser() : null;
    if (!user || !user.email) {
        AmbiletNotifications.error('Te rugăm să te autentifici mai întâi.');
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Se trimite...';
    }

    try {
        const response = await AmbiletAPI.customer.resendVerification(user.email);
        if (response.success) {
            AmbiletNotifications.success('Email de verificare retrimis! Verifică inbox-ul.');
        } else {
            AmbiletNotifications.error(response.message || 'Nu s-a putut retrimite emailul.');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la retrimitere. Încearcă din nou.');
    }

    if (btn) {
        btn.disabled = false;
        btn.textContent = 'Retrimite emailul de verificare';
    }
}
