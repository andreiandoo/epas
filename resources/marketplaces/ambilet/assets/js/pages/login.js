document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const remember = document.getElementById('remember').checked;

    try {
        const result = await AmbiletAuth.login(email, password, remember);
        if (result.success) {
            AmbiletNotifications.success('Conectare reusita!');
            const redirect = AmbiletUtils.getUrlParam('redirect') || '/user/dashboard';
            setTimeout(() => window.location.href = redirect, 500);
        } else {
            AmbiletNotifications.error(result.message || 'Email sau parola incorecta');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la conectare. Incearca din nou.');
    }
});
