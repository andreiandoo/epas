document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const remember = document.getElementById('remember').checked;

    try {
        const result = await BileteOnlineAuth.login(email, password, remember);
        if (result.success) {
            BileteOnlineNotifications.success('Conectare reusita!');
            const redirect = BileteOnlineUtils.getUrlParam('redirect') || '/user/dashboard';
            setTimeout(() => window.location.href = redirect, 500);
        } else {
            BileteOnlineNotifications.error(result.message || 'Email sau parola incorecta');
        }
    } catch (error) {
        BileteOnlineNotifications.error('Eroare la conectare. Incearca din nou.');
    }
});
