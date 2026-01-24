<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Setari';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'settings';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
                <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Setari</h1>
                    <p class="text-sm text-muted">Gestioneaza setarile contului tau</p>
                </div>
                
            </div>


            <div class="flex flex-wrap gap-2 mb-8 border-b border-border pb-4">
                <button onclick="showSection('profile')" class="settings-tab active px-4 py-2 rounded-lg text-sm font-medium bg-primary text-white" data-section="profile">Profil</button>
                <button onclick="showSection('company')" class="settings-tab px-4 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface" data-section="company">Companie</button>
                <button onclick="showSection('bank')" class="settings-tab px-4 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface" data-section="bank">Conturi Bancare</button>
                <button onclick="showSection('contract')" class="settings-tab px-4 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface" data-section="contract">Contract</button>
                <button onclick="showSection('notifications')" class="settings-tab px-4 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface" data-section="notifications">Notificari</button>
                <button onclick="showSection('security')" class="settings-tab px-4 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface" data-section="security">Securitate</button>
            </div>

            <div id="profile-section" class="settings-section">
                <div class="bg-white rounded-2xl border border-border p-6">
                    <h2 class="text-lg font-bold text-secondary mb-6">Profil Organizator</h2>
                    <form onsubmit="saveProfile(event)" class="space-y-4">
                        <div class="grid lg:grid-cols-2 gap-4"><div><label class="label">Nume Organizator *</label><input type="text" id="org-name" class="input w-full" required></div><div><label class="label">Email Contact *</label><input type="email" id="org-email" class="input w-full" required></div></div>
                        <div class="grid lg:grid-cols-2 gap-4"><div><label class="label">Telefon</label><input type="tel" id="org-phone" class="input w-full"></div><div><label class="label">Website</label><input type="url" id="org-website" class="input w-full"></div></div>
                        <div><label class="label">Descriere</label><textarea id="org-description" rows="4" class="input w-full"></textarea></div>
                        <div class="pt-4 flex justify-end"><button type="submit" class="btn btn-primary">Salveaza</button></div>
                    </form>
                </div>
            </div>

            <div id="company-section" class="settings-section hidden">
                <div class="bg-white rounded-2xl border border-border p-6">
                    <h2 class="text-lg font-bold text-secondary mb-6">Date Companie</h2>
                    <form onsubmit="saveCompany(event)" class="space-y-4">
                        <div class="grid lg:grid-cols-2 gap-4"><div><label class="label">Denumire Firma *</label><input type="text" id="company-name" class="input w-full" required></div><div><label class="label">CUI / CIF *</label><div class="flex gap-2"><input type="text" id="company-cui" class="input flex-1" required><button type="button" onclick="verifyCUI()" class="btn btn-secondary">Verifica ANAF</button></div></div></div>
                        <div class="grid lg:grid-cols-2 gap-4"><div><label class="label">Nr. Reg. Comertului</label><input type="text" id="company-reg" class="input w-full"></div><div><label class="label">Platitor TVA</label><select id="company-vat" class="input w-full"><option value="0">Nu</option><option value="1">Da</option></select></div></div>
                        <div><label class="label">Adresa Sediu *</label><input type="text" id="company-address" class="input w-full" required></div>
                        <div class="grid lg:grid-cols-3 gap-4"><div><label class="label">Oras *</label><input type="text" id="company-city" class="input w-full" required></div><div><label class="label">Judet *</label><input type="text" id="company-county" class="input w-full" required></div><div><label class="label">Cod Postal</label><input type="text" id="company-zip" class="input w-full"></div></div>
                        <div class="pt-4 flex justify-end"><button type="submit" class="btn btn-primary">Salveaza</button></div>
                    </form>
                </div>
            </div>

            <div id="bank-section" class="settings-section hidden">
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center justify-between mb-6"><h2 class="text-lg font-bold text-secondary">Conturi Bancare</h2><button onclick="openBankModal()" class="btn btn-primary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>Adauga Cont</button></div>
                    <div id="bank-accounts-list" class="space-y-4"></div>
                </div>
            </div>

            <div id="contract-section" class="settings-section hidden">
                <div class="bg-white rounded-2xl border border-border p-6 mb-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-bold text-secondary">Contract Marketplace</h2>
                        <button onclick="downloadContract()" class="btn btn-primary">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Descarca Contract
                        </button>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-sm text-blue-800">Contractul este generat automat pe baza datelor tale de companie si a conditiilor comerciale agreate cu <?= SITE_NAME ?>.</p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="bg-surface rounded-xl p-5">
                                <h3 class="font-semibold text-secondary mb-3 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Comision
                                </h3>
                                <p class="text-3xl font-bold text-primary mb-1" id="contract-commission">-</p>
                                <p class="text-sm text-muted">din valoarea fiecarui bilet vandut</p>
                            </div>
                            <div class="bg-surface rounded-xl p-5">
                                <h3 class="font-semibold text-secondary mb-3 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Mod Operare Comision
                                </h3>
                                <p class="text-lg font-semibold text-secondary" id="contract-mode">-</p>
                                <p class="text-sm text-muted mt-1" id="contract-mode-desc">-</p>
                            </div>
                        </div>

                        <div class="border-t border-border pt-6">
                            <h3 class="font-semibold text-secondary mb-4">Conditii Contractuale</h3>
                            <div class="space-y-3" id="contract-terms">
                                <div class="flex items-start gap-3 text-sm text-muted">
                                    <svg class="w-4 h-4 text-success mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Platile se efectueaza in termen de 7 zile lucratoare dupa eveniment</span>
                                </div>
                                <div class="flex items-start gap-3 text-sm text-muted">
                                    <svg class="w-4 h-4 text-success mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Rambursarile se proceseaza in maxim 14 zile de la solicitare</span>
                                </div>
                                <div class="flex items-start gap-3 text-sm text-muted">
                                    <svg class="w-4 h-4 text-success mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Organizatorul este responsabil de livrarea evenimentului conform descrierii</span>
                                </div>
                                <div class="flex items-start gap-3 text-sm text-muted">
                                    <svg class="w-4 h-4 text-success mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Contractul este valabil pe durata nedeterminata si poate fi reziliat cu preaviz de 30 zile</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="notifications-section" class="settings-section hidden">
                <div class="bg-white rounded-2xl border border-border p-6">
                    <h2 class="text-lg font-bold text-secondary mb-6">Preferinte Notificari</h2>
                    <form onsubmit="saveNotifications(event)" class="space-y-4">
                        <div class="flex items-center justify-between py-4 border-b border-border"><div><p class="font-medium text-secondary">Notificari Vanzari</p><p class="text-sm text-muted">Email la fiecare vanzare</p></div><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="notif-sales" class="sr-only peer" checked><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div></label></div>
                        <div class="flex items-center justify-between py-4 border-b border-border"><div><p class="font-medium text-secondary">Rapoarte Zilnice</p><p class="text-sm text-muted">Sumar zilnic al vanzarilor</p></div><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="notif-daily" class="sr-only peer" checked><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div></label></div>
                        <div class="flex items-center justify-between py-4 border-b border-border"><div><p class="font-medium text-secondary">Alerte Stoc</p><p class="text-sm text-muted">Notificare stoc sub 10%</p></div><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="notif-stock" class="sr-only peer" checked><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div></label></div>
                        <div class="pt-4 flex justify-end"><button type="submit" class="btn btn-primary">Salveaza</button></div>
                    </form>
                </div>
            </div>

            <div id="security-section" class="settings-section hidden">
                <div class="bg-white rounded-2xl border border-border p-6 mb-6">
                    <h2 class="text-lg font-bold text-secondary mb-6">Schimba Parola</h2>
                    <form onsubmit="changePassword(event)" class="max-w-md space-y-4">
                        <div><label class="label">Parola Curenta</label><input type="password" id="current-password" class="input w-full" required></div>
                        <div><label class="label">Parola Noua</label><input type="password" id="new-password" class="input w-full" required minlength="8"></div>
                        <div><label class="label">Confirma Parola</label><input type="password" id="confirm-password" class="input w-full" required></div>
                        <button type="submit" class="btn btn-primary">Schimba Parola</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <div id="bank-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-6"><h3 class="text-xl font-bold text-secondary">Adauga Cont Bancar</h3><button onclick="closeBankModal()"><svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form onsubmit="addBankAccount(event)" class="space-y-4">
                <div><label class="label">Nume Banca *</label><input type="text" id="bank-name" class="input w-full" required></div>
                <div><label class="label">IBAN *</label><input type="text" id="bank-iban" class="input w-full" required></div>
                <div><label class="label">Titular Cont *</label><input type="text" id="bank-holder" class="input w-full" required></div>
                <div class="flex gap-3"><button type="button" onclick="closeBankModal()" class="btn btn-secondary flex-1">Anuleaza</button><button type="submit" class="btn btn-primary flex-1">Adauga</button></div>
            </form>
        </div>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
document.addEventListener('DOMContentLoaded', function() { loadSettings(); loadBankAccounts(); loadContract(); const hash = window.location.hash.replace('#', ''); if (hash) showSection(hash); });

function showSection(section) {
    document.querySelectorAll('.settings-tab').forEach(t => { t.classList.remove('active', 'bg-primary', 'text-white'); t.classList.add('text-muted', 'hover:bg-surface'); });
    document.querySelector(`.settings-tab[data-section="${section}"]`).classList.add('active', 'bg-primary', 'text-white');
    document.querySelector(`.settings-tab[data-section="${section}"]`).classList.remove('text-muted', 'hover:bg-surface');
    document.querySelectorAll('.settings-section').forEach(s => s.classList.add('hidden'));
    document.getElementById(`${section}-section`).classList.remove('hidden');
    window.location.hash = section;
}

async function loadSettings() {
    try {
        const response = await AmbiletAPI.get('/organizer/settings');
        if (response.success) {
            const data = response.data;
            document.getElementById('org-name').value = data.name || '';
            document.getElementById('org-email').value = data.email || '';
            document.getElementById('org-phone').value = data.phone || '';
            document.getElementById('org-website').value = data.website || '';
            document.getElementById('org-description').value = data.description || '';
            document.getElementById('company-name').value = data.company_name || '';
            document.getElementById('company-cui').value = data.cui || '';
            document.getElementById('company-reg').value = data.reg_number || '';
            document.getElementById('company-vat').value = data.vat_payer ? '1' : '0';
            document.getElementById('company-address').value = data.address || '';
            document.getElementById('company-city').value = data.city || '';
            document.getElementById('company-county').value = data.county || '';
            document.getElementById('company-zip').value = data.zip || '';
        }
    } catch (error) { /* Fields remain empty */ }
}

async function loadBankAccounts() {
    try {
        const response = await AmbiletAPI.get('/organizer/bank-accounts');
        if (response.success) { renderBankAccounts(response.data.accounts || []); }
        else { renderBankAccounts([]); }
    } catch (error) { renderBankAccounts([]); }
}

function renderBankAccounts(accounts) {
    if (!accounts.length) { document.getElementById('bank-accounts-list').innerHTML = '<div class="p-6 text-center text-muted">Nu ai conturi bancare adaugate</div>'; return; }
    document.getElementById('bank-accounts-list').innerHTML = accounts.map(a => `
        <div class="flex items-center justify-between p-4 bg-surface rounded-xl ${a.is_primary ? 'ring-2 ring-primary' : ''}">
            <div class="flex items-center gap-4"><div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center border border-border"><svg class="w-6 h-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></div><div><div class="flex items-center gap-2"><p class="font-medium text-secondary">${a.bank}</p>${a.is_primary ? '<span class="px-2 py-0.5 bg-primary/10 text-primary text-xs rounded-full">Principal</span>' : ''}</div><p class="text-sm text-muted font-mono">${a.iban}</p></div></div>
            <div class="flex items-center gap-2">${!a.is_primary ? `<button onclick="setPrimaryAccount(${a.id})" class="btn btn-secondary btn-sm">Seteaza Principal</button>` : ''}<button onclick="deleteAccount(${a.id})" class="p-2 text-muted hover:text-error"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></div>
        </div>
    `).join('');
}

async function saveProfile(e) {
    e.preventDefault();
    try {
        const data = { name: document.getElementById('org-name').value, email: document.getElementById('org-email').value, phone: document.getElementById('org-phone').value, website: document.getElementById('org-website').value, description: document.getElementById('org-description').value };
        const response = await AmbiletAPI.put('/organizer/settings/profile', data);
        if (response.success) { AmbiletNotifications.success('Profilul a fost salvat'); }
        else { AmbiletNotifications.error(response.message || 'Eroare la salvare'); }
    } catch (error) { AmbiletNotifications.error('Eroare la salvare'); }
}
async function saveCompany(e) {
    e.preventDefault();
    try {
        const data = { company_name: document.getElementById('company-name').value, cui: document.getElementById('company-cui').value, reg_number: document.getElementById('company-reg').value, vat_payer: document.getElementById('company-vat').value === '1', address: document.getElementById('company-address').value, city: document.getElementById('company-city').value, county: document.getElementById('company-county').value, zip: document.getElementById('company-zip').value };
        const response = await AmbiletAPI.put('/organizer/settings/company', data);
        if (response.success) { AmbiletNotifications.success('Datele companiei au fost salvate'); }
        else { AmbiletNotifications.error(response.message || 'Eroare la salvare'); }
    } catch (error) { AmbiletNotifications.error('Eroare la salvare'); }
}
async function verifyCUI() {
    const cui = document.getElementById('company-cui').value;
    if (!cui) { AmbiletNotifications.error('Introdu CUI-ul'); return; }
    AmbiletNotifications.info('Verificare ANAF...');
    try {
        const response = await AmbiletAPI.post('/organizer/settings/verify-cui', { cui });
        if (response.success) { AmbiletNotifications.success('Date verificate'); if (response.data) { document.getElementById('company-name').value = response.data.company_name || ''; document.getElementById('company-address').value = response.data.address || ''; } }
        else { AmbiletNotifications.error(response.message || 'CUI invalid'); }
    } catch (error) { AmbiletNotifications.error('Eroare la verificare'); }
}
function openBankModal() { document.getElementById('bank-modal').classList.remove('hidden'); document.getElementById('bank-modal').classList.add('flex'); }
function closeBankModal() { document.getElementById('bank-modal').classList.add('hidden'); document.getElementById('bank-modal').classList.remove('flex'); }
async function addBankAccount(e) {
    e.preventDefault();
    try {
        const data = { bank: document.getElementById('bank-name').value, iban: document.getElementById('bank-iban').value, holder: document.getElementById('bank-holder').value };
        const response = await AmbiletAPI.post('/organizer/bank-accounts', data);
        if (response.success) { closeBankModal(); AmbiletNotifications.success('Contul a fost adaugat'); loadBankAccounts(); }
        else { AmbiletNotifications.error(response.message || 'Eroare la adaugare'); }
    } catch (error) { AmbiletNotifications.error('Eroare la adaugare'); }
}
async function setPrimaryAccount(id) {
    try {
        const response = await AmbiletAPI.put('/organizer/bank-accounts/' + id + '/primary', {});
        if (response.success) { AmbiletNotifications.success('Contul principal a fost schimbat'); loadBankAccounts(); }
        else { AmbiletNotifications.error(response.message || 'Eroare'); }
    } catch (error) { AmbiletNotifications.error('Eroare'); }
}
async function deleteAccount(id) {
    if (!confirm('Stergi acest cont?')) return;
    try {
        const response = await AmbiletAPI.delete('/organizer/bank-accounts/' + id);
        if (response.success) { AmbiletNotifications.success('Contul a fost sters'); loadBankAccounts(); }
        else { AmbiletNotifications.error(response.message || 'Eroare la stergere'); }
    } catch (error) { AmbiletNotifications.error('Eroare la stergere'); }
}
async function saveNotifications(e) {
    e.preventDefault();
    try {
        const data = { sales: document.getElementById('notif-sales').checked, daily: document.getElementById('notif-daily').checked, stock: document.getElementById('notif-stock').checked };
        const response = await AmbiletAPI.put('/organizer/settings/notifications', data);
        if (response.success) { AmbiletNotifications.success('Preferintele au fost salvate'); }
        else { AmbiletNotifications.error(response.message || 'Eroare'); }
    } catch (error) { AmbiletNotifications.error('Eroare la salvare'); }
}
async function changePassword(e) {
    e.preventDefault();
    if (document.getElementById('new-password').value !== document.getElementById('confirm-password').value) { AmbiletNotifications.error('Parolele nu coincid'); return; }
    try {
        const data = { current_password: document.getElementById('current-password').value, password: document.getElementById('new-password').value, password_confirmation: document.getElementById('confirm-password').value };
        const response = await AmbiletAPI.put('/organizer/settings/password', data);
        if (response.success) { AmbiletNotifications.success('Parola a fost schimbata'); e.target.reset(); }
        else { AmbiletNotifications.error(response.message || 'Eroare la schimbare parola'); }
    } catch (error) { AmbiletNotifications.error('Eroare la schimbare parola'); }
}

async function loadContract() {
    try {
        const response = await AmbiletAPI.get('/organizer/contract');
        if (response.success) {
            const data = response.data;
            document.getElementById('contract-commission').textContent = (data.commission_rate || 0) + '%';
            const modeLabels = { 'included': 'Inclus in pretul biletului', 'on_top': 'Adaugat peste pretul biletului' };
            const modeDescs = { 'included': 'Comisionul este inclus in pretul afisat al biletului', 'on_top': 'Comisionul se adauga separat la pretul biletului' };
            document.getElementById('contract-mode').textContent = modeLabels[data.commission_mode] || data.commission_mode || '-';
            document.getElementById('contract-mode-desc').textContent = modeDescs[data.commission_mode] || '';
            if (data.terms && data.terms.length) {
                document.getElementById('contract-terms').innerHTML = data.terms.map(t => `
                    <div class="flex items-start gap-3 text-sm text-muted">
                        <svg class="w-4 h-4 text-success mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>${t}</span>
                    </div>
                `).join('');
            }
        }
    } catch (error) { /* Contract info will show defaults */ }
}

async function downloadContract() {
    try {
        const response = await AmbiletAPI.get('/organizer/contract/download');
        if (response.success && response.data.url) {
            window.open(response.data.url, '_blank');
        } else {
            AmbiletNotifications.info('Contractul nu este disponibil momentan. Contacteaza suportul.');
        }
    } catch (error) { AmbiletNotifications.error('Eroare la descarcare contract'); }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
