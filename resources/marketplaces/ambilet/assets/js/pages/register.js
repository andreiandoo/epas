document.getElementById('register-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = {
        first_name: document.getElementById('first_name').value,
        last_name: document.getElementById('last_name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        password: document.getElementById('password').value,
        password_confirmation: document.getElementById('password_confirmation').value
    };

    if (formData.password !== formData.password_confirmation) {
        AmbiletNotifications.error('Parolele nu coincid');
        return;
    }

    try {
        const result = await AmbiletAuth.register(formData);
        if (result.success) {
            AmbiletNotifications.success('Cont creat cu succes! Verifica email-ul pentru confirmare.');
            setTimeout(() => window.location.href = '/autentificare', 2000);
        } else {
            AmbiletNotifications.error(result.message || 'Eroare la inregistrare');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la inregistrare. Incearca din nou.');
    }
});
