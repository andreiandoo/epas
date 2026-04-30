/**
 * Artist Account — Settings page handler
 * Three forms: profile fields update, password change, account deletion.
 */
window.addEventListener('ambilet:artist-cont:ready', () => {
    populateForm();
    wireProfileForm();
    wirePasswordForm();
    wireDeleteFlow();
});

function populateForm() {
    const account = AmbiletAuth.getArtistData();
    if (!account) return;

    setVal('first_name', account.first_name);
    setVal('last_name', account.last_name);
    setVal('email', account.email);
    setVal('phone', account.phone);
    setVal('locale', account.locale || 'ro');
}

function wireProfileForm() {
    const form = document.getElementById('account-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Se salvează...';

        const data = {
            first_name: document.getElementById('first_name').value.trim(),
            last_name: document.getElementById('last_name').value.trim(),
            phone: document.getElementById('phone').value.trim() || null,
            locale: document.getElementById('locale').value,
        };

        try {
            const res = await AmbiletAPI.artist.updateAccount(data);
            if (res.success) {
                AmbiletAuth.updateArtistData(res.data.account);
                AmbiletNotifications.success('Setări salvate.');
            } else {
                AmbiletNotifications.error(res.message || 'Eroare la salvare.');
            }
        } catch (err) {
            AmbiletNotifications.error(err.message || 'Eroare la salvare.');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
}

function wirePasswordForm() {
    const form = document.getElementById('password-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const current = document.getElementById('current_password').value;
        const next = document.getElementById('new_password').value;
        const confirm = document.getElementById('new_password_confirmation').value;

        if (next !== confirm) {
            AmbiletNotifications.error('Parolele nu coincid.');
            return;
        }

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Se schimbă...';

        try {
            const res = await AmbiletAPI.artist.updatePassword({
                current_password: current,
                password: next,
                password_confirmation: confirm,
            });
            if (res.success) {
                AmbiletNotifications.success('Parolă schimbată cu succes.');
                form.reset();
            } else {
                AmbiletNotifications.error(res.message || 'Eroare la schimbare.');
            }
        } catch (err) {
            // The controller returns wrong_current_password as a 422 with code.
            const code = err.data?.errors?.code;
            if (code === 'wrong_current_password') {
                AmbiletNotifications.error('Parola curentă este incorectă.');
            } else {
                AmbiletNotifications.error(err.message || 'Eroare la schimbare.');
            }
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
}

function wireDeleteFlow() {
    const btn = document.getElementById('delete-account-btn');
    const modal = document.getElementById('delete-modal');
    const cancel = document.getElementById('delete-cancel');
    const confirm = document.getElementById('delete-confirm');
    const passwordInput = document.getElementById('delete-password');

    if (!btn || !modal) return;

    const open = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        passwordInput.value = '';
        passwordInput.focus();
    };
    const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    btn.addEventListener('click', open);
    cancel.addEventListener('click', close);

    confirm.addEventListener('click', async () => {
        const password = passwordInput.value;
        if (!password) {
            AmbiletNotifications.error('Introdu parola pentru a confirma.');
            return;
        }

        confirm.disabled = true;
        confirm.textContent = 'Se șterge...';

        try {
            const res = await AmbiletAPI.artist.deleteAccount(password);
            if (res.success) {
                AmbiletNotifications.success('Cont șters.');
                AmbiletAuth.clearArtistSession();
                setTimeout(() => window.location.href = '/', 1000);
            } else {
                AmbiletNotifications.error(res.message || 'Nu s-a putut șterge contul.');
                confirm.disabled = false;
                confirm.textContent = 'Șterge definitiv';
            }
        } catch (err) {
            const code = err.data?.errors?.code;
            if (code === 'wrong_password') {
                AmbiletNotifications.error('Parolă incorectă.');
            } else {
                AmbiletNotifications.error(err.message || 'Nu s-a putut șterge contul.');
            }
            confirm.disabled = false;
            confirm.textContent = 'Șterge definitiv';
        }
    });
}

function setVal(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}
