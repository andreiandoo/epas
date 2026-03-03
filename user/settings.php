<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Setari';
$currentPage = 'settings';
$cssBundle = 'account';
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
                <h1 class="text-2xl font-bold text-secondary">Setări cont</h1>
                <p class="mt-1 text-sm text-muted">Gestionează datele personale și preferințele contului tău</p>
            </div>

            <div class="space-y-6">
                <!-- Avatar Upload -->
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <h2 class="section-title">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Fotografie profil
                    </h2>

                    <div class="flex items-center gap-5">
                        <div class="relative group">
                            <div id="avatarPreview" class="flex items-center justify-center overflow-hidden text-2xl font-bold text-white rounded-full w-20 h-20 bg-primary" style="min-width:80px">
                                <span id="avatarInitials"></span>
                            </div>
                            <label for="avatarInput" class="absolute inset-0 flex items-center justify-center transition-opacity bg-black/50 rounded-full cursor-pointer opacity-0 group-hover:opacity-100">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </label>
                            <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden">
                        </div>
                        <div>
                            <p class="font-medium text-secondary">Schimbă fotografia</p>
                            <p class="text-sm text-muted">JPG, PNG sau WebP. Max 2MB.</p>
                            <p id="avatarStatus" class="hidden mt-1 text-sm text-primary"></p>
                        </div>
                    </div>
                </div>

                <!-- Profile Information -->
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <h2 class="section-title">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Informații personale
                    </h2>

                    <form id="profileForm">
                        <div class="grid gap-4 md:grid-cols-2 mobile:gap-y-0">
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
                                <p class="hint">Adresa de email nu poate fi modificată</p>
                            </div>
                            <div class="form-group">
                                <label for="birth_date">Data nașterii</label>
                                <input type="date" id="birth_date" name="birth_date">
                            </div>
                            <div class="form-group">
                                <label for="gender">Sex</label>
                                <select id="gender" name="gender">
                                    <option value="">Selectează</option>
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
                                <label for="state">Județ</label>
                                <select id="state" name="state">
                                    <option value="">Selectează județul</option>
                                    <?php foreach ($judete as $code => $name): ?>
                                    <option value="<?= $code ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="city">Oraș</label>
                                <input type="text" id="city" name="city" placeholder="București">
                            </div>
                        </div>

                        <div class="flex justify-end mt-4">
                            <button type="submit" class="btn btn-primary bg-primary" id="saveProfileBtn">
                                Salvează modificările
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
                        Schimbare parolă
                    </h2>

                    <form id="passwordForm">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="form-group md:col-span-2">
                                <label for="current_password">Parola curentă</label>
                                <input type="password" id="current_password" name="current_password" placeholder="Introdu parola curentă">
                            </div>
                            <div class="form-group">
                                <label for="new_password">Parola nouă</label>
                                <input type="password" id="new_password" name="password" placeholder="Minim 8 caractere">
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirmă parola nouă</label>
                                <input type="password" id="confirm_password" name="password_confirmation" placeholder="Repetă parola nouă">
                            </div>
                        </div>

                        <div class="flex justify-end mt-4">
                            <button type="submit" class="btn btn-primary bg-primary" id="changePasswordBtn">
                                Schimbă parola
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Billing Address -->
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <h2 class="section-title">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                        Adresa de facturare
                    </h2>
                    <p class="mb-4 text-sm text-muted">Aceasta adresă va fi folosită pentru generarea facturilor.</p>

                    <form id="billingAddressForm">
                        <div class="grid gap-4 md:grid-cols-2 mobile:gap-y-0">
                            <div class="form-group md:col-span-2">
                                <label for="billing_address">Adresă</label>
                                <input type="text" id="billing_address" name="billing_address" placeholder="Strada, număr, bloc, apartament">
                            </div>
                            <div class="form-group">
                                <label for="billing_city">Oraș</label>
                                <input type="text" id="billing_city" name="billing_city" placeholder="București">
                            </div>
                            <div class="form-group">
                                <label for="billing_state">Județ</label>
                                <select id="billing_state" name="billing_state">
                                    <option value="">Selectează județul</option>
                                    <?php foreach ($judete as $code => $name): ?>
                                    <option value="<?= $code ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="billing_postal_code">Cod poștal</label>
                                <input type="text" id="billing_postal_code" name="billing_postal_code" placeholder="012345">
                            </div>
                            <div class="form-group">
                                <label for="billing_country">Țara</label>
                                <select id="billing_country" name="billing_country">
                                    <option value="RO" selected>România</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end mt-4">
                            <button type="submit" class="btn btn-primary bg-primary" id="saveBillingBtn">
                                Salvează adresa
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Language & Region -->
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <h2 class="section-title">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                        </svg>
                        Limbă și regiune
                    </h2>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="form-group">
                            <label for="language">Limba</label>
                            <select id="language">
                                <option value="ro" selected>Română</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="currency">Moneda</label>
                            <select id="currency" disabled>
                                <option value="RON" selected>RON (Lei)</option>
                                <option value="EUR">EUR (Euro)</option>
                            </select>
                            <p class="hint">Moneda este determinată automat</p>
                        </div>
                    </div>
                </div>

                <!-- Privacy -->
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <h2 class="section-title">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Confidențialitate
                    </h2>

                    <div class="space-y-4">
                        <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                            <div>
                                <p class="font-medium text-secondary">Profil public</p>
                                <p class="text-sm text-muted">Permite altor utilizatori să vadă pagina ta de profil cu gusturile muzicale și statisticile tale</p>
                            </div>
                            <input type="checkbox" class="flex-none toggle" id="profile-public">
                        </label>
                    </div>

                    <div class="flex justify-end mt-4">
                        <button onclick="SettingsPage.savePrivacySettings()" class="btn btn-primary bg-primary" id="savePrivacyBtn">
                            Salvează
                        </button>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <h2 class="section-title">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        Notificări email
                    </h2>

                    <div class="space-y-4">
                        <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                            <div>
                                <p class="font-medium text-secondary">Confirmare comenzi</p>
                                <p class="text-sm text-muted">Primește email cu detaliile comenzii și biletele</p>
                            </div>
                            <input type="checkbox" class="flex-none toggle" id="notif-orders" checked disabled>
                        </label>

                        <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                            <div>
                                <p class="font-medium text-secondary">Remindere evenimente</p>
                                <p class="text-sm text-muted">Primește reminder cu 24h înainte de eveniment</p>
                            </div>
                            <input type="checkbox" class="flex-none toggle" id="notif-reminders">
                        </label>

                        <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                            <div>
                                <p class="font-medium text-secondary">Newsletter și oferte</p>
                                <p class="text-sm text-muted">Află despre evenimente noi și oferte speciale</p>
                            </div>
                            <input type="checkbox" class="flex-none toggle" id="notif-newsletter">
                        </label>

                        <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                            <div>
                                <p class="font-medium text-secondary">Actualizări favorite</p>
                                <p class="text-sm text-muted">Primește notificări când evenimentele favorite se apropie</p>
                            </div>
                            <input type="checkbox" class="flex-none toggle" id="notif-favorites">
                        </label>

                        <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                            <div>
                                <p class="font-medium text-secondary">Istoric navigare</p>
                                <p class="text-sm text-muted">Salvează evenimentele vizualizate pentru recomandări personalizate</p>
                            </div>
                            <input type="checkbox" class="flex-none toggle" id="notif-history">
                        </label>

                        <label class="flex items-center justify-between p-3 cursor-pointer bg-surface rounded-xl">
                            <div>
                                <p class="font-medium text-secondary">Cookie-uri marketing</p>
                                <p class="text-sm text-muted">Permite afișarea de reclame personalizate</p>
                            </div>
                            <input type="checkbox" class="flex-none toggle" id="notif-marketing">
                        </label>
                    </div>

                    <div class="flex justify-end mt-4">
                        <button onclick="saveNotificationSettings()" class="btn btn-primary bg-primary" id="saveNotificationsBtn">
                            Salvează preferințele
                        </button>
                    </div>
                </div>

                <!-- Delete Account -->
                <div class="p-6 bg-white border rounded-2xl border-error/30">
                    <h2 class="section-title text-error">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Ștergere cont
                    </h2>

                    <p class="mb-4 text-sm text-muted">
                        Odată ce contul este șters, toate datele tale vor fi șterse permanent. Aceasta acțiune este ireversibilă.
                        Dacă ai bilete pentru evenimente viitoare, nu vei putea șterge contul până când acestea nu au loc.
                    </p>

                    <button onclick="showDeleteConfirmation()" class="btn btn-danger" id="deleteAccountBtn">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Șterge contul meu
                    </button>
                </div>
            </div>

            <!-- Delete Account Modal -->
            <div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black/50">
                <div class="w-full max-w-md p-6 mx-4 bg-white rounded-2xl">
                    <h3 class="mb-4 text-lg font-bold text-error">Confirmă ștergerea contului</h3>
                    <p class="mb-4 text-sm text-muted">
                        Aceasta acțiune este <strong>permanentă și ireversibilă</strong>. Pentru a confirma, introdu parola ta.
                    </p>

                    <form id="deleteAccountForm">
                        <div class="form-group">
                            <label for="delete_password">Parola</label>
                            <input type="password" id="delete_password" name="password" placeholder="Introdu parola ta" required>
                        </div>
                        <div class="form-group">
                            <label for="delete_reason">Motiv (opțional)</label>
                            <select id="delete_reason" name="reason">
                                <option value="">Selectează un motiv</option>
                                <option value="Nu mai am nevoie de cont">Nu mai am nevoie de cont</option>
                                <option value="Am un alt cont">Am un alt cont</option>
                                <option value="Primesc prea multe emailuri">Primesc prea multe emailuri</option>
                                <option value="Probleme de confidențialitate">Probleme de confidențialitate</option>
                                <option value="Alt motiv">Alt motiv</option>
                            </select>
                        </div>

                        <div class="flex gap-3 mt-6">
                            <button type="button" onclick="hideDeleteConfirmation()" class="flex-1 btn bg-surface text-secondary hover:bg-gray-200">
                                Anulează
                            </button>
                            <button type="submit" class="flex-1 btn btn-danger" id="confirmDeleteBtn">
                                Șterge definitiv
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
            AmbiletNotifications.error('Eroare la încărcarea profilului');
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

        // Avatar
        this.updateAvatarPreview();

        // Privacy
        const profilePublic = c.settings?.profile_public ?? false;
        document.getElementById('profile-public').checked = profilePublic;

        // Load billing address
        const billing = c.settings?.billing_address || {};
        document.getElementById('billing_address').value = billing.address || c.address || '';
        document.getElementById('billing_city').value = billing.city || c.city || '';
        document.getElementById('billing_state').value = billing.state || c.state || '';
        document.getElementById('billing_postal_code').value = billing.postal_code || c.postal_code || '';
        document.getElementById('billing_country').value = billing.country || 'RO';

        // Load notification preferences from customer settings
        this.loadNotificationSettings();
    },

    updateAvatarPreview() {
        const c = this.customer;
        const preview = document.getElementById('avatarPreview');
        const initials = document.getElementById('avatarInitials');

        if (c?.avatar) {
            preview.innerHTML = `<img src="${c.avatar}" alt="Avatar" class="object-cover w-full h-full">`;
        } else {
            const fi = (c?.first_name || '?')[0].toUpperCase();
            const li = (c?.last_name || '')[0]?.toUpperCase() || '';
            initials.textContent = fi + li;
        }
    },

    loadNotificationSettings() {
        const c = this.customer;
        if (!c) return;

        // Get notification preferences from customer.settings.notification_preferences
        const prefs = c.settings?.notification_preferences || {};

        // Default values if not set
        const defaults = {
            reminders: true,
            newsletter: true,
            favorites: true,
            history: true,
            marketing: false
        };

        // Set checkbox values from DB or defaults
        document.getElementById('notif-reminders').checked = prefs.reminders ?? defaults.reminders;
        document.getElementById('notif-newsletter').checked = prefs.newsletter ?? c.accepts_marketing ?? defaults.newsletter;
        document.getElementById('notif-favorites').checked = prefs.favorites ?? defaults.favorites;
        document.getElementById('notif-history').checked = prefs.history ?? defaults.history;
        document.getElementById('notif-marketing').checked = prefs.marketing ?? defaults.marketing;
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

        // Billing address form
        document.getElementById('billingAddressForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.saveBillingAddress();
        });

        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', (e) => {
            this.updatePasswordStrength(e.target.value);
        });

        // Avatar upload
        document.getElementById('avatarInput').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (file) await this.uploadAvatar(file);
        });
    },

    async uploadAvatar(file) {
        if (file.size > 2 * 1024 * 1024) {
            AmbiletNotifications.error('Imaginea trebuie să fie mai mică de 2MB');
            return;
        }

        const status = document.getElementById('avatarStatus');
        status.textContent = 'Se încarcă...';
        status.classList.remove('hidden');

        try {
            const response = await AmbiletAPI.customer.uploadAvatar(file);
            if (response.success || response.data?.avatar_url) {
                if (response.data?.customer) {
                    this.customer = response.data.customer;
                }
                this.updateAvatarPreview();
                AmbiletNotifications.success('Fotografia a fost actualizată!');
                status.classList.add('hidden');
            }
        } catch (error) {
            console.error('Avatar upload error:', error);
            AmbiletNotifications.error(error.message || 'Eroare la încărcarea fotografiei');
            status.textContent = 'Eroare la încărcare';
        }
    },

    async savePrivacySettings() {
        const btn = document.getElementById('savePrivacyBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner"></span> Se salvează...';
        btn.disabled = true;

        try {
            const profilePublic = document.getElementById('profile-public').checked;
            const response = await AmbiletAPI.put('/customer/settings', {
                profile_public: profilePublic
            });

            if (response.success) {
                AmbiletNotifications.success('Setările de confidențialitate au fost salvate!');
            } else {
                AmbiletNotifications.error(response.message || 'Eroare la salvare');
            }
        } catch (error) {
            console.error('Save privacy settings error:', error);
            AmbiletNotifications.error(error.message || 'Eroare la salvare');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
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
        btn.innerHTML = '<span class="loading-spinner"></span> Se salvează...';
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
            AmbiletNotifications.error('Completează toate câmpurile');
            return;
        }

        if (newPassword.length < 8) {
            AmbiletNotifications.error('Parola trebuie să aibă minim 8 caractere');
            return;
        }

        if (newPassword !== confirmPassword) {
            AmbiletNotifications.error('Parolele nu coincid');
            return;
        }

        const btn = document.getElementById('changePasswordBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner"></span> Se schimbă...';
        btn.disabled = true;

        try {
            const response = await AmbiletAPI.customer.changePassword(currentPassword, newPassword, confirmPassword);

            if (response.success) {
                AmbiletNotifications.success('Parola a fost schimbată cu succes!');
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
        btn.innerHTML = '<span class="loading-spinner"></span> Se șterge...';
        btn.disabled = true;

        try {
            const response = await AmbiletAPI.customer.deleteAccount(password, reason);

            if (response.success) {
                AmbiletNotifications.success('Contul a fost șters');
                AmbiletAuth.logout();
                setTimeout(() => {
                    window.location.href = '/';
                }, 1500);
            } else {
                AmbiletNotifications.error(response.message || 'Eroare la ștergerea contului');
            }
        } catch (error) {
            console.error('Delete account error:', error);
            AmbiletNotifications.error(error.message || 'Eroare la ștergerea contului');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    },

    async saveBillingAddress() {
        const btn = document.getElementById('saveBillingBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner"></span> Se salvează...';
        btn.disabled = true;

        try {
            const billingAddress = {
                address: document.getElementById('billing_address').value,
                city: document.getElementById('billing_city').value,
                state: document.getElementById('billing_state').value,
                postal_code: document.getElementById('billing_postal_code').value,
                country: document.getElementById('billing_country').value
            };

            // Save to API using settings endpoint
            const response = await AmbiletAPI.put('/customer/settings', {
                billing_address: billingAddress
            });

            if (response.success) {
                AmbiletNotifications.success('Adresa de facturare a fost salvată!');
            } else {
                AmbiletNotifications.error(response.message || 'Eroare la salvare');
            }
        } catch (error) {
            console.error('Save billing address error:', error);
            AmbiletNotifications.error(error.message || 'Eroare la salvare');
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
    btn.innerHTML = '<span class="loading-spinner"></span> Se salvează...';
    btn.disabled = true;

    try {
        const notificationPreferences = {
            reminders: document.getElementById('notif-reminders').checked,
            newsletter: document.getElementById('notif-newsletter').checked,
            favorites: document.getElementById('notif-favorites').checked,
            history: document.getElementById('notif-history').checked,
            marketing: document.getElementById('notif-marketing').checked
        };

        // Save to API
        await AmbiletAPI.put('/customer/settings', {
            accepts_marketing: notificationPreferences.newsletter,
            notification_preferences: notificationPreferences
        });

        AmbiletNotifications.success('Preferintele au fost salvate!');
    } catch (error) {
        console.error('Save notification settings error:', error);
        AmbiletNotifications.error('Eroare la salvare. Încearcă din nou.');
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
