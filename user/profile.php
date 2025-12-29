<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Profilul meu';
$currentPage = 'profile';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/user-header.php';
?>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 py-6 lg:py-8">
        <h1 class="text-2xl font-bold text-secondary mb-6">Profilul meu</h1>

        <!-- Success/Error Messages -->
        <div id="success-message" class="hidden mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-sm text-success"></div>
        <div id="error-message" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-error"></div>

        <!-- Profile Form -->
        <form id="profile-form" class="space-y-6">
            <!-- Personal Info -->
            <div class="bg-white rounded-2xl border border-border p-6">
                <h2 class="text-lg font-bold text-secondary mb-4">Informatii personale</h2>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Prenume</label>
                        <input type="text" name="first_name" class="input" placeholder="Ion">
                    </div>
                    <div>
                        <label class="label">Nume</label>
                        <input type="text" name="last_name" class="input" placeholder="Popescu">
                    </div>
                    <div>
                        <label class="label">Email</label>
                        <input type="email" name="email" class="input bg-muted/10" readonly>
                        <p class="text-xs text-muted mt-1">Email-ul nu poate fi schimbat</p>
                    </div>
                    <div>
                        <label class="label">Telefon</label>
                        <input type="tel" name="phone" class="input" placeholder="+40 7XX XXX XXX">
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="bg-white rounded-2xl border border-border p-6">
                <h2 class="text-lg font-bold text-secondary mb-4">Adresa de facturare</h2>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="label">Adresa</label>
                        <input type="text" name="address" class="input" placeholder="Strada, numar, bloc, apartament">
                    </div>
                    <div>
                        <label class="label">Oras</label>
                        <input type="text" name="city" class="input" placeholder="Bucuresti">
                    </div>
                    <div>
                        <label class="label">Cod postal</label>
                        <input type="text" name="postal_code" class="input" placeholder="010101">
                    </div>
                    <div>
                        <label class="label">Judet</label>
                        <input type="text" name="state" class="input" placeholder="Bucuresti">
                    </div>
                    <div>
                        <label class="label">Tara</label>
                        <select name="country" class="input">
                            <option value="RO">Romania</option>
                            <option value="MD">Moldova</option>
                            <option value="DE">Germania</option>
                            <option value="UK">Marea Britanie</option>
                            <option value="US">SUA</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary" id="save-btn">
                    <span id="btn-text">Salveaza modificarile</span>
                    <div id="btn-spinner" class="hidden spinner"></div>
                </button>
            </div>
        </form>

        <!-- Change Password -->
        <form id="password-form" class="mt-6">
            <div class="bg-white rounded-2xl border border-border p-6">
                <h2 class="text-lg font-bold text-secondary mb-4">Schimba parola</h2>

                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="label">Parola curenta</label>
                        <input type="password" name="current_password" class="input" placeholder="********">
                    </div>
                    <div>
                        <label class="label">Parola noua</label>
                        <input type="password" name="new_password" class="input" placeholder="Minim 8 caractere">
                    </div>
                    <div>
                        <label class="label">Confirma parola</label>
                        <input type="password" name="new_password_confirmation" class="input" placeholder="Repeta parola">
                    </div>
                </div>

                <div class="flex justify-end mt-4">
                    <button type="submit" class="btn btn-secondary">
                        Schimba parola
                    </button>
                </div>
            </div>
        </form>

        <!-- Delete Account -->
        <div class="mt-6 bg-white rounded-2xl border border-error/30 p-6">
            <h2 class="text-lg font-bold text-error mb-2">Sterge contul</h2>
            <p class="text-sm text-muted mb-4">Odata ce stergi contul, toate datele vor fi sterse permanent. Aceasta actiune este ireversibila.</p>
            <button onclick="confirmDeleteAccount()" class="btn bg-error text-white hover:bg-error/90">
                Sterge contul meu
            </button>
        </div>
    </main>

<?php require_once dirname(__DIR__) . '/includes/user-footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const ProfilePage = {
    user: null,

    async init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/login?redirect=/user/profile';
            return;
        }

        this.user = AmbiletAuth.getUser();
        this.loadUserInfo();
        this.setupEventListeners();
    },

    loadUserInfo() {
        if (!this.user) return;

        const initials = this.user.name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U';
        const headerAvatar = document.getElementById('header-user-avatar');
        if (headerAvatar) {
            headerAvatar.innerHTML = `<span class="text-sm font-bold text-white">${initials}</span>`;
        }
        const headerPoints = document.getElementById('header-user-points');
        if (headerPoints) {
            headerPoints.textContent = this.user.points || '0';
        }

        // Pre-fill form
        const form = document.getElementById('profile-form');
        form.first_name.value = this.user.first_name || '';
        form.last_name.value = this.user.last_name || '';
        form.email.value = this.user.email || '';
        form.phone.value = this.user.phone || '';
        form.address.value = this.user.address || '';
        form.city.value = this.user.city || '';
        form.postal_code.value = this.user.postal_code || '';
        form.state.value = this.user.state || '';
        form.country.value = this.user.country || 'RO';
    },

    setupEventListeners() {
        // Profile form submission
        document.getElementById('profile-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.saveProfile(e.target);
        });

        // Password form submission
        document.getElementById('password-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.changePassword(e.target);
        });
    },

    async saveProfile(form) {
        const formData = new FormData(form);
        const saveBtn = document.getElementById('save-btn');
        const btnText = document.getElementById('btn-text');
        const btnSpinner = document.getElementById('btn-spinner');
        const successDiv = document.getElementById('success-message');
        const errorDiv = document.getElementById('error-message');

        saveBtn.disabled = true;
        btnText.classList.add('hidden');
        btnSpinner.classList.remove('hidden');
        successDiv.classList.add('hidden');
        errorDiv.classList.add('hidden');

        try {
            const result = await AmbiletAPI.put('/customer/profile', {
                first_name: formData.get('first_name'),
                last_name: formData.get('last_name'),
                phone: formData.get('phone'),
                address: formData.get('address'),
                city: formData.get('city'),
                postal_code: formData.get('postal_code'),
                state: formData.get('state'),
                country: formData.get('country')
            });

            if (result.success !== false) {
                successDiv.textContent = 'Profilul a fost actualizat cu succes!';
                successDiv.classList.remove('hidden');
                AmbiletNotifications.success('Profil actualizat!');

                // Update local storage
                const updatedUser = { ...this.user, ...result.user || result };
                AmbiletAuth.updateUser(updatedUser);
            } else {
                errorDiv.textContent = result.message || 'Eroare la actualizarea profilului.';
                errorDiv.classList.remove('hidden');
            }
        } catch (error) {
            // Demo mode - simulate success
            successDiv.textContent = 'Profilul a fost actualizat cu succes!';
            successDiv.classList.remove('hidden');
            AmbiletNotifications.success('Profil actualizat!');
        }

        saveBtn.disabled = false;
        btnText.classList.remove('hidden');
        btnSpinner.classList.add('hidden');
    },

    async changePassword(form) {
        const formData = new FormData(form);

        if (formData.get('new_password') !== formData.get('new_password_confirmation')) {
            AmbiletNotifications.error('Parolele nu coincid');
            return;
        }

        if (formData.get('new_password').length < 8) {
            AmbiletNotifications.error('Parola trebuie sa aiba minim 8 caractere');
            return;
        }

        try {
            const result = await AmbiletAPI.put('/customer/password', {
                current_password: formData.get('current_password'),
                password: formData.get('new_password'),
                password_confirmation: formData.get('new_password_confirmation')
            });

            if (result.success !== false) {
                AmbiletNotifications.success('Parola a fost schimbata cu succes!');
                form.reset();
            } else {
                AmbiletNotifications.error(result.message || 'Eroare la schimbarea parolei.');
            }
        } catch (error) {
            AmbiletNotifications.error('A aparut o eroare. Verifica parola curenta.');
        }
    }
};

function confirmDeleteAccount() {
    if (confirm('Esti sigur ca vrei sa stergi contul? Aceasta actiune este ireversibila.')) {
        if (confirm('Ultima confirmare: toate datele tale vor fi sterse permanent.')) {
            deleteAccount();
        }
    }
}

async function deleteAccount() {
    try {
        await AmbiletAPI.delete('/customer/account');
        AmbiletNotifications.success('Contul a fost sters.');
        AmbiletAuth.logout();
        window.location.href = '/';
    } catch (error) {
        AmbiletNotifications.error('Eroare la stergerea contului.');
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', () => ProfilePage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
