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
document.addEventListener('DOMContentLoaded', function() { loadSettings(); loadBankAccounts(); const hash = window.location.hash.replace('#', ''); if (hash) showSection(hash); });

function showSection(section) {
    document.querySelectorAll('.settings-tab').forEach(t => { t.classList.remove('active', 'bg-primary', 'text-white'); t.classList.add('text-muted', 'hover:bg-surface'); });
    document.querySelector(`.settings-tab[data-section="${section}"]`).classList.add('active', 'bg-primary', 'text-white');
    document.querySelector(`.settings-tab[data-section="${section}"]`).classList.remove('text-muted', 'hover:bg-surface');
    document.querySelectorAll('.settings-section').forEach(s => s.classList.add('hidden'));
    document.getElementById(`${section}-section`).classList.remove('hidden');
    window.location.hash = section;
}

function loadSettings() {
    document.getElementById('org-name').value = 'EventPro SRL';
    document.getElementById('org-email').value = 'contact@eventpro.ro';
    document.getElementById('org-phone').value = '+40721234567';
    document.getElementById('company-name').value = 'EVENTPRO SRL';
    document.getElementById('company-cui').value = 'RO12345678';
    document.getElementById('company-address').value = 'Str. Exemplu nr. 10';
    document.getElementById('company-city').value = 'Bucuresti';
    document.getElementById('company-county').value = 'Bucuresti';
}

function loadBankAccounts() {
    const accounts = [
        { id: 1, bank: 'ING Bank', iban: 'RO49INGB0000999900123456', holder: 'EVENTPRO SRL', is_primary: true },
        { id: 2, bank: 'BRD', iban: 'RO49BRDE0000999900654321', holder: 'EVENTPRO SRL', is_primary: false }
    ];
    document.getElementById('bank-accounts-list').innerHTML = accounts.map(a => `
        <div class="flex items-center justify-between p-4 bg-surface rounded-xl ${a.is_primary ? 'ring-2 ring-primary' : ''}">
            <div class="flex items-center gap-4"><div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center border border-border"><svg class="w-6 h-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></div><div><div class="flex items-center gap-2"><p class="font-medium text-secondary">${a.bank}</p>${a.is_primary ? '<span class="px-2 py-0.5 bg-primary/10 text-primary text-xs rounded-full">Principal</span>' : ''}</div><p class="text-sm text-muted font-mono">${a.iban}</p></div></div>
            <div class="flex items-center gap-2">${!a.is_primary ? `<button onclick="setPrimaryAccount(${a.id})" class="btn btn-secondary btn-sm">Seteaza Principal</button>` : ''}<button onclick="deleteAccount(${a.id})" class="p-2 text-muted hover:text-error"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></div>
        </div>
    `).join('');
}

function saveProfile(e) { e.preventDefault(); AmbiletNotifications.success('Profilul a fost salvat'); }
function saveCompany(e) { e.preventDefault(); AmbiletNotifications.success('Datele companiei au fost salvate'); }
function verifyCUI() { AmbiletNotifications.info('Verificare ANAF...'); setTimeout(() => AmbiletNotifications.success('Date verificate'), 1500); }
function openBankModal() { document.getElementById('bank-modal').classList.remove('hidden'); document.getElementById('bank-modal').classList.add('flex'); }
function closeBankModal() { document.getElementById('bank-modal').classList.add('hidden'); document.getElementById('bank-modal').classList.remove('flex'); }
function addBankAccount(e) { e.preventDefault(); closeBankModal(); AmbiletNotifications.success('Contul a fost adaugat'); loadBankAccounts(); }
function setPrimaryAccount(id) { AmbiletNotifications.success('Contul principal a fost schimbat'); loadBankAccounts(); }
function deleteAccount(id) { if (confirm('Stergi acest cont?')) { AmbiletNotifications.success('Contul a fost sters'); loadBankAccounts(); } }
function saveNotifications(e) { e.preventDefault(); AmbiletNotifications.success('Preferintele au fost salvate'); }
function changePassword(e) { e.preventDefault(); if (document.getElementById('new-password').value !== document.getElementById('confirm-password').value) { AmbiletNotifications.error('Parolele nu coincid'); return; } AmbiletNotifications.success('Parola a fost schimbata'); e.target.reset(); }
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
