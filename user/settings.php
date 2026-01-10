<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Setari';
$currentPage = 'settings';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';

// Romanian counties
$judete = [
    'AB' => 'Alba', 'AR' => 'Arad', 'AG' => 'Arges', 'BC' => 'Bacau', 'BH' => 'Bihor',
    'BN' => 'Bistrita-Nasaud', 'BT' => 'Botosani', 'BR' => 'Braila', 'BV' => 'Brasov',
    'B' => 'Bucuresti', 'BZ' => 'Buzau', 'CL' => 'Calarasi', 'CS' => 'Caras-Severin',
    'CJ' => 'Cluj', 'CT' => 'Constanta', 'CV' => 'Covasna', 'DB' => 'Dambovita',
    'DJ' => 'Dolj', 'GL' => 'Galati', 'GR' => 'Giurgiu', 'GJ' => 'Gorj', 'HR' => 'Harghita',
    'HD' => 'Hunedoara', 'IL' => 'Ialomita', 'IS' => 'Iasi', 'IF' => 'Ilfov',
    'MM' => 'Maramures', 'MH' => 'Mehedinti', 'MS' => 'Mures', 'NT' => 'Neamt',
    'OT' => 'Olt', 'PH' => 'Prahova', 'SJ' => 'Salaj', 'SM' => 'Satu Mare',
    'SB' => 'Sibiu', 'SV' => 'Suceava', 'TR' => 'Teleorman', 'TM' => 'Timis',
    'TL' => 'Tulcea', 'VL' => 'Valcea', 'VS' => 'Vaslui', 'VN' => 'Vrancea'
];
?>

<style>
    .toggle { appearance: none; width: 44px; height: 24px; background: #E2E8F0; border-radius: 12px; position: relative; cursor: pointer; transition: all 0.3s; }
    .toggle:checked { background: var(--primary); }
    .toggle::before { content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: white; border-radius: 50%; transition: all 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
    .toggle:checked::before { left: 22px; }
    .toggle:disabled { opacity: 0.5; cursor: not-allowed; }
    .section-title { font-size: 1.125rem; font-weight: 700; color: var(--secondary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--secondary); margin-bottom: 0.375rem; }
    .form-group input, .form-group select { width: 100%; padding: 0.625rem 0.875rem; border: 1px solid var(--border); border-radius: 0.75rem; font-size: 0.875rem; transition: all 0.2s; background: white; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(165, 28, 48, 0.1); }
    .form-group input:disabled { background: var(--surface); cursor: not-allowed; color: var(--muted); }
    .form-group .hint { font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem; }
    .btn-danger { background: #EF4444; color: white; }
    .btn-danger:hover { background: #DC2626; }
    .password-strength { height: 4px; border-radius: 2px; margin-top: 0.5rem; transition: all 0.3s; background: #E2E8F0; }
    .password-strength.weak { background: linear-gradient(to right, #EF4444 33%, #E2E8F0 33%); }
    .password-strength.medium { background: linear-gradient(to right, #F59E0B 66%, #E2E8F0 66%); }
    .password-strength.strong { background: #10B981; }
</style>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-secondary">Setari cont</h1>
                <p class="mt-1 text-sm text-muted">Gestioneaza datele personale si preferintele contului tau</p>
            </div>

            <div class="space-y-6">
                <!-- Profile Information -->
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <h2 class="section-title">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Informatii personale
                    </h2>

                    <form id="profileForm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="form-group">
                                <label for="last_name">Nume</label>
                                <input type="text" id="last_name" name="last_name" placeholder="Popescu">
                            </div>
                            <div class="form-group">
                                <label for="first_name">Prenume</label>
                                <input type="text" id="first_name" name="first_name" placeholder="Ion">
                            </div>
                            <div class="form-group md:col-span-2">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" disabled>
                                <p class="hint">Adresa de email nu poate fi modificata</p>
                            </div>
                            <div class="form-group">
                                <label for="birth_date">Data nasterii</label>
                                <input type="date" id="birth_date" name="birth_date">
                            </div>
                            <div class="form-group">
                                <label for="gender">Sex</label>
                                <select id="gender" name="gender">
                                    <option value="">Selecteaza</option>
                                    <option value="male">Masculin</option>
                                    <option value="female">Feminin</option>
                                    <option value="other">Altul</option>
                                </select>
                            </div>
                            <div class="form-group md:col-span-2">
                                <label for="phone">Telefon</label>
                                <input type="tel" id="phone" name="phone" placeholder="+40 7XX XXX XXX">
                            </div>
                            <div class="form-group">
                                <label for="state">Judet</label>
                                <select id="state" name="state">
                                    <option value="">Selecteaza judetul</option>
                                    <?php foreach ($judete as $code => $name): ?>
                                    <option value="<?= $code ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="city">Oras</label>
                                <input type="text" id="city" name="city" placeholder="Bucuresti">
                            </div>
                        </div>

                        <div class="flex justify-end mt-4">
                            <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                                Salveaza modificarile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Password Change -->
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <h2 class="section-title">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Schimbare parola
                    </h2>

                    <form id="passwordForm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="form-group md:col-span-2">
                                <label for="current_password">Parola curenta</label>
                                <input type="password" id="current_password" name="current_password" placeholder="Introdu parola curenta">
                            </div>
                            <div class="form-group">
                                <label for="new_password">Parola noua</label>
                                <input type="password" id="new_password" name="password" placeholder="Minim 8 caractere">
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirma parola noua</label>
                                <input type="password" id="confirm_password" name="password_confirmation" placeholder="Repeta parola noua">
                            </div>
                        </div>

                        <div class="flex justify-end mt-4">
                            <button type="submit" class="btn btn-primary" id="changePasswordBtn">
                                Schimba parola
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Notifications -->
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <h2 class="section-title">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        Notificari email
                    </h2>

                    <div class="space-y-4">
                        <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                            <div>
                                <p class="font-medium text-secondary">Confirmare comenzi</p>
                                <p class="text-sm text-muted">Primeste email cu detaliile comenzii si biletele</p>
                            </div>
                            <input type="checkbox" class="toggle" id="notif-orders" checked disabled>
                        </label>

                        <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                            <div>
                                <p class="font-medium text-secondary">Remindere evenimente</p>
                                <p class="text-sm text-muted">Primeste reminder cu 24h inainte de eveniment</p>
                            </div>
                            <input type="checkbox" class="toggle" id="notif-reminders" checked>
                        </label>

                        <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                            <div>
                                <p class="font-medium text-secondary">Newsletter si oferte</p>
                                <p class="text-sm text-muted">Afla despre evenimente noi si oferte speciale</p>
                            </div>
                            <input type="checkbox" class="toggle" id="notif-newsletter">
                        </label>

                        <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                            <div>
                                <p class="font-medium text-secondary">Actualizari favorite</p>
                                <p class="text-sm text-muted">Primeste notificari cand evenimentele favorite se apropie</p>
                            </div>
                            <input type="checkbox" class="toggle" id="notif-favorites" checked>
                        </label>
                    </div>

                    <div class="flex justify-end mt-4">
                        <button onclick="saveNotificationSettings()" class="btn btn-primary" id="saveNotificationsBtn">
                            Salveaza preferintele
                        </button>
                    </div>
                </div>

                <!-- Language & Region -->
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <h2 class="section-title">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                        </svg>
                        Limba si regiune
                    </h2>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="form-group">
                            <label for="language">Limba</label>
                            <select id="language">
                                <option value="ro" selected>Romana</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="currency">Moneda</label>
                            <select id="currency" disabled>
                                <option value="RON" selected>RON (Lei)</option>
                                <option value="EUR">EUR (Euro)</option>
                            </select>
                            <p class="hint">Moneda este determinata automat</p>
                        </div>
                    </div>
                </div>

                <!-- Delete Account -->
                <div class="p-6 bg-white border rounded-2xl border-error/30">
                    <h2 class="section-title text-error">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Stergere cont
                    </h2>

                    <p class="mb-4 text-sm text-muted">
                        Odata ce contul este sters, toate datele tale vor fi sterse permanent. Aceasta actiune este ireversibila.
                        Daca ai bilete pentru evenimente viitoare, nu vei putea sterge contul pana cand acestea nu au loc.
                    </p>

                    <button onclick="showDeleteConfirmation()" class="btn btn-danger" id="deleteAccountBtn">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Sterge contul meu
                    </button>
                </div>
            </div>

            <!-- Delete Account Modal -->
            <div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black/50">
                <div class="w-full max-w-md p-6 mx-4 bg-white rounded-2xl">
                    <h3 class="mb-4 text-lg font-bold text-error">Confirma stergerea contului</h3>
                    <p class="mb-4 text-sm text-muted">
                        Aceasta actiune este <strong>permanenta si ireversibila</strong>. Pentru a confirma, introdu parola ta.
                    </p>

                    <form id="deleteAccountForm">
                        <div class="form-group">
                            <label for="delete_password">Parola</label>
                            <input type="password" id="delete_password" name="password" placeholder="Introdu parola ta" required>
                        </div>
                        <div class="form-group">
                            <label for="delete_reason">Motiv (optional)</label>
                            <select id="delete_reason" name="reason">
                                <option value="">Selecteaza un motiv</option>
                                <option value="Nu mai am nevoie de cont">Nu mai am nevoie de cont</option>
                                <option value="Am un alt cont">Am un alt cont</option>
                                <option value="Primesc prea multe emailuri">Primesc prea multe emailuri</option>
                                <option value="Probleme de confidentialitate">Probleme de confidentialitate</option>
                                <option value="Alt motiv">Alt motiv</option>
                            </select>
                        </div>

                        <div class="flex gap-3 mt-6">
                            <button type="button" onclick="hideDeleteConfirmation()" class="flex-1 btn bg-surface text-secondary hover:bg-gray-200">
                                Anuleaza
                            </button>
                            <button type="submit" class="flex-1 btn btn-danger" id="confirmDeleteBtn">
                                Sterge definitiv
                            </button>
                        </div>
                    </form>
                </div>
            </div>
<?php
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php';
?>

<?php
$scriptsExtra = <<<'JS'
<script>
const SettingsPage = {
    isLoading: false,
    customer: null,

    async init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/setari';
            return;
        }

        await this.loadProfile();
        this.loadNotificationSettings();
        this.setupEventListeners();
    },

    async loadProfile() {
        try {
            const response = await AmbiletAPI.customer.getProfile();
            if (response.success && response.data?.customer) {
                this.customer = response.data.customer;
                this.populateForm();
            }
        } catch (error) {
            console.error('Failed to load profile:', error);
            AmbiletNotifications.error('Eroare la incarcarea profilului');
        }
    },

    populateForm() {
        const c = this.customer;
        if (!c) return;

        document.getElementById('first_name').value = c.first_name || '';
        document.getElementById('last_name').value = c.last_name || '';
        document.getElementById('email').value = c.email || '';
        document.getElementById('phone').value = c.phone || '';
        document.getElementById('birth_date').value = c.birth_date || '';
        document.getElementById('gender').value = c.gender || '';
        document.getElementById('state').value = c.state || '';
        document.getElementById('city').value = c.city || '';
        document.getElementById('language').value = c.locale || 'ro';

        // Newsletter preference
        if (c.accepts_marketing !== undefined) {
            document.getElementById('notif-newsletter').checked = c.accepts_marketing;
        }
    },

    loadNotificationSettings() {
        const settings = JSON.parse(localStorage.getItem('ambilet_settings') || '{}');
        if (settings.reminders !== undefined) {
            document.getElementById('notif-reminders').checked = settings.reminders;
        }
        if (settings.favorites !== undefined) {
            document.getElementById('notif-favorites').checked = settings.favorites;
        }
    },

    setupEventListeners() {
        // Profile form
        document.getElementById('profileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.saveProfile();
        });

        // Password form
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.changePassword();
        });

        // Delete account form
        document.getElementById('deleteAccountForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.deleteAccount();
        });

        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', (e) => {
            this.updatePasswordStrength(e.target.value);
        });
    },

    updatePasswordStrength(password) {
        const indicator = document.getElementById('passwordStrength');
        if (!password) {
            indicator.className = 'password-strength';
            return;
        }

        let strength = 0;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        if (strength <= 1) {
            indicator.className = 'password-strength weak';
        } else if (strength <= 2) {
            indicator.className = 'password-strength medium';
        } else {
            indicator.className = 'password-strength strong';
        }
    },

    async saveProfile() {
        const btn = document.getElementById('saveProfileBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner"></span> Se salveaza...';
        btn.disabled = true;

        try {
            const formData = {
                first_name: document.getElementById('first_name').value,
                last_name: document.getElementById('last_name').value,
                phone: document.getElementById('phone').value || null,
                birth_date: document.getElementById('birth_date').value || null,
                gender: document.getElementById('gender').value || null,
                state: document.getElementById('state').value || null,
                city: document.getElementById('city').value || null,
            };

            const response = await AmbiletAPI.customer.updateProfile(formData);

            if (response.success) {
                AmbiletNotifications.success('Profilul a fost actualizat!');
                // Update cached user data
                if (response.data?.customer) {
                    this.customer = response.data.customer;
                    const user = AmbiletAuth.getUser();
                    if (user) {
                        user.name = `${formData.first_name} ${formData.last_name}`;
                        localStorage.setItem('ambilet_user', JSON.stringify(user));
                    }
                }
            } else {
                AmbiletNotifications.error(response.message || 'Eroare la salvare');
            }
        } catch (error) {
            console.error('Save profile error:', error);
            if (error.errors) {
                const firstError = Object.values(error.errors)[0];
                AmbiletNotifications.error(Array.isArray(firstError) ? firstError[0] : firstError);
            } else {
                AmbiletNotifications.error(error.message || 'Eroare la salvare');
            }
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    },

    async changePassword() {
        const currentPassword = document.getElementById('current_password').value;
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (!currentPassword || !newPassword || !confirmPassword) {
            AmbiletNotifications.error('Completeaza toate campurile');
            return;
        }

        if (newPassword.length < 8) {
            AmbiletNotifications.error('Parola trebuie sa aiba minim 8 caractere');
            return;
        }

        if (newPassword !== confirmPassword) {
            AmbiletNotifications.error('Parolele nu coincid');
            return;
        }

        const btn = document.getElementById('changePasswordBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner"></span> Se schimba...';
        btn.disabled = true;

        try {
            const response = await AmbiletAPI.customer.changePassword(currentPassword, newPassword, confirmPassword);

            if (response.success) {
                AmbiletNotifications.success('Parola a fost schimbata cu succes!');
                document.getElementById('passwordForm').reset();
                document.getElementById('passwordStrength').className = 'password-strength';
            } else {
                AmbiletNotifications.error(response.message || 'Eroare la schimbarea parolei');
            }
        } catch (error) {
            console.error('Change password error:', error);
            AmbiletNotifications.error(error.message || 'Eroare la schimbarea parolei');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    },

    async deleteAccount() {
        const password = document.getElementById('delete_password').value;
        const reason = document.getElementById('delete_reason').value;

        if (!password) {
            AmbiletNotifications.error('Introdu parola pentru confirmare');
            return;
        }

        const btn = document.getElementById('confirmDeleteBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner"></span> Se sterge...';
        btn.disabled = true;

        try {
            const response = await AmbiletAPI.customer.deleteAccount(password, reason);

            if (response.success) {
                AmbiletNotifications.success('Contul a fost sters');
                AmbiletAuth.logout();
                setTimeout(() => {
                    window.location.href = '/';
                }, 1500);
            } else {
                AmbiletNotifications.error(response.message || 'Eroare la stergerea contului');
            }
        } catch (error) {
            console.error('Delete account error:', error);
            AmbiletNotifications.error(error.message || 'Eroare la stergerea contului');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
};

function showDeleteConfirmation() {
    document.getElementById('deleteModal').classList.remove('hidden');
}

function hideDeleteConfirmation() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteAccountForm').reset();
}

async function saveNotificationSettings() {
    const btn = document.getElementById('saveNotificationsBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="loading-spinner"></span> Se salveaza...';
    btn.disabled = true;

    try {
        const settings = {
            reminders: document.getElementById('notif-reminders').checked,
            newsletter: document.getElementById('notif-newsletter').checked,
            favorites: document.getElementById('notif-favorites').checked
        };

        // Save to localStorage
        localStorage.setItem('ambilet_settings', JSON.stringify(settings));

        // Save newsletter preference to API
        await AmbiletAPI.put('/customer/settings', {
            accepts_marketing: settings.newsletter,
            notification_preferences: settings
        });

        AmbiletNotifications.success('Preferintele au fost salvate!');
    } catch (error) {
        console.error('Save notification settings error:', error);
        AmbiletNotifications.error('Eroare la salvare. Incearca din nou.');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// Close modal on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        hideDeleteConfirmation();
    }
});

// Close modal on click outside
document.getElementById('deleteModal').addEventListener('click', (e) => {
    if (e.target.id === 'deleteModal') {
        hideDeleteConfirmation();
    }
});

// Initialize page
document.addEventListener('DOMContentLoaded', () => SettingsPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
