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
                <div class="flex items-center gap-2">
                    <a href="/organizator/echipa" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border border-border rounded-xl bg-white text-secondary hover:bg-surface hover:border-primary transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Echipa
                    </a>
                    <a href="/organizator/apidoc" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border border-border rounded-xl bg-white text-secondary hover:bg-surface hover:border-primary transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        API
                    </a>
                </div>
            </div>


            <div class="flex flex-wrap gap-2 mb-8 border-b border-border pb-4">
                <button onclick="showSection('profile')" class="settings-tab active px-4 py-2 rounded-lg text-sm font-medium bg-primary text-white" data-section="profile">Profil</button>
                <button onclick="showSection('company')" class="settings-tab px-4 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface" data-section="company">Companie</button>
                <button onclick="showSection('bank')" class="settings-tab px-4 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface" data-section="bank">Conturi Bancare</button>
                <button onclick="showSection('contract')" class="settings-tab px-4 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface" data-section="contract">Contract</button>
                <button onclick="showSection('notifications')" class="settings-tab px-4 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface" data-section="notifications">Notificari</button>
                <button onclick="showSection('security')" class="settings-tab px-4 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface" data-section="security">Securitate</button>
                <button onclick="showSection('sharelinks')" class="settings-tab px-4 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface" data-section="sharelinks">Link-uri Share</button>
            </div>

            <div id="profile-section" class="settings-section">
                <div class="bg-white rounded-2xl border border-border p-6 mb-6">
                    <h2 class="text-lg font-bold text-secondary mb-6">Profil Organizator</h2>
                    <form onsubmit="saveProfile(event)" class="space-y-4">
                        <div class="grid lg:grid-cols-2 gap-4"><div><label class="label">Nume Organizator *</label><input type="text" id="org-name" class="input w-full" required></div><div><label class="label">Email Contact *</label><input type="email" id="org-email" class="input w-full" required></div></div>
                        <div class="grid lg:grid-cols-2 gap-4"><div><label class="label">Telefon</label><input type="tel" id="org-phone" class="input w-full"></div><div><label class="label">Website</label><input type="url" id="org-website" class="input w-full"></div></div>
                        <div><label class="label">Descriere</label><textarea id="org-description" rows="4" class="input w-full"></textarea></div>
                        <div class="pt-4 flex justify-end"><button type="submit" class="btn btn-primary">Salveaza</button></div>
                    </form>
                </div>

                <!-- Personal / Guarantor Information (read-only) -->
                <div class="bg-white rounded-2xl border border-border p-6" id="personal-info-section">
                    <h2 class="text-lg font-bold text-secondary mb-2">Date Personale / Garant</h2>
                    <p class="text-sm text-muted mb-6">Informatii capturate la inregistrare. Contacteaza suportul pentru modificari.</p>
                    <div class="space-y-4">
                        <div class="grid lg:grid-cols-2 gap-4">
                            <div><label class="label">Prenume</label><input type="text" id="guarantor-first-name" class="input w-full bg-gray-50" readonly></div>
                            <div><label class="label">Nume</label><input type="text" id="guarantor-last-name" class="input w-full bg-gray-50" readonly></div>
                        </div>
                        <div class="grid lg:grid-cols-2 gap-4">
                            <div><label class="label">CNP</label><input type="text" id="guarantor-cnp" class="input w-full bg-gray-50" readonly></div>
                            <div><label class="label">Localitate</label><input type="text" id="guarantor-city" class="input w-full bg-gray-50" readonly></div>
                        </div>
                        <div><label class="label">Adresa domiciliu</label><input type="text" id="guarantor-address" class="input w-full bg-gray-50" readonly></div>
                        <div class="grid lg:grid-cols-4 gap-4">
                            <div><label class="label">Tip act</label><input type="text" id="guarantor-id-type" class="input w-full bg-gray-50" readonly></div>
                            <div><label class="label">Serie</label><input type="text" id="guarantor-id-series" class="input w-full bg-gray-50" readonly></div>
                            <div><label class="label">Numar</label><input type="text" id="guarantor-id-number" class="input w-full bg-gray-50" readonly></div>
                            <div><label class="label">Data eliberarii</label><input type="text" id="guarantor-id-issued-date" class="input w-full bg-gray-50" readonly></div>
                        </div>
                        <div><label class="label">Eliberat de</label><input type="text" id="guarantor-id-issued-by" class="input w-full bg-gray-50" readonly></div>
                    </div>
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
                        <button id="download-contract-btn" onclick="downloadContract()" class="btn btn-primary hidden">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Descarca Contract
                        </button>
                        <span id="no-contract-msg" class="text-sm text-muted hidden">Contract negenearat</span>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-sm text-blue-800">Contractul este generat automat pe baza datelor tale de companie si a conditiilor comerciale agreate cu <?= SITE_NAME ?>.</p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="grid md:grid-cols-3 gap-6">
                            <div class="bg-surface rounded-xl p-5">
                                <h3 class="font-semibold text-secondary mb-3 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Comision
                                </h3>
                                <p class="text-3xl font-bold text-primary mb-1" id="contract-commission">-</p>
                                <p class="text-sm text-muted" id="contract-commission-note">maxim 6% (minim 2.50 lei/bilet)</p>
                            </div>
                            <div class="bg-surface rounded-xl p-5">
                                <h3 class="font-semibold text-secondary mb-3 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    Mod de Lucru
                                </h3>
                                <p class="text-lg font-semibold text-secondary" id="contract-work-mode">-</p>
                                <p class="text-sm text-muted mt-1" id="contract-work-mode-desc">-</p>
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

                        <div class="border-t border-border pt-6">
                            <h3 class="font-semibold text-secondary mb-4">Documente Necesare</h3>
                            <p class="text-sm text-muted mb-4">Pentru activarea contului și procesarea plăților, avem nevoie de următoarele documente:</p>

                            <div class="grid md:grid-cols-2 gap-6">
                                <!-- ID Card Upload -->
                                <div>
                                    <label class="block text-sm font-medium text-secondary mb-2">
                                        Copie BI/CI (reprezentant legal) *
                                    </label>
                                    <div id="id-card-dropzone" class="dropzone border-2 border-dashed border-border rounded-xl p-6 text-center cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors"
                                         ondragover="handleDragOver(event, 'id-card')"
                                         ondragleave="handleDragLeave(event, 'id-card')"
                                         ondrop="handleDrop(event, 'id_card')"
                                         onclick="document.getElementById('id-card-input').click()">
                                        <input type="file" id="id-card-input" class="hidden" accept=".pdf,.jpg,.jpeg,.png" onchange="handleFileSelect(event, 'id_card')">
                                        <div id="id-card-preview" class="hidden">
                                            <div class="flex items-center justify-center gap-3">
                                                <svg class="w-8 h-8 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <div class="text-left">
                                                    <p id="id-card-filename" class="text-sm font-medium text-secondary"></p>
                                                    <p class="text-xs text-muted">Click pentru a schimba</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="id-card-placeholder">
                                            <svg class="w-10 h-10 text-muted mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                            <p class="text-sm text-muted">Trage fișierul aici sau click pentru a selecta</p>
                                            <p class="text-xs text-muted mt-1">PDF, JPG sau PNG (max 5MB)</p>
                                        </div>
                                    </div>
                                    <p id="id-card-status" class="mt-2 text-xs hidden"></p>
                                </div>

                                <!-- Company Registration Upload -->
                                <div>
                                    <label class="block text-sm font-medium text-secondary mb-2">
                                        Copie CUI / Certificat Înregistrare *
                                    </label>
                                    <div id="cui-dropzone" class="dropzone border-2 border-dashed border-border rounded-xl p-6 text-center cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors"
                                         ondragover="handleDragOver(event, 'cui')"
                                         ondragleave="handleDragLeave(event, 'cui')"
                                         ondrop="handleDrop(event, 'cui_document')"
                                         onclick="document.getElementById('cui-input').click()">
                                        <input type="file" id="cui-input" class="hidden" accept=".pdf,.jpg,.jpeg,.png" onchange="handleFileSelect(event, 'cui_document')">
                                        <div id="cui-preview" class="hidden">
                                            <div class="flex items-center justify-center gap-3">
                                                <svg class="w-8 h-8 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <div class="text-left">
                                                    <p id="cui-filename" class="text-sm font-medium text-secondary"></p>
                                                    <p class="text-xs text-muted">Click pentru a schimba</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="cui-placeholder">
                                            <svg class="w-10 h-10 text-muted mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                            <p class="text-sm text-muted">Trage fișierul aici sau click pentru a selecta</p>
                                            <p class="text-xs text-muted mt-1">PDF, JPG sau PNG (max 5MB)</p>
                                        </div>
                                    </div>
                                    <p id="cui-status" class="mt-2 text-xs hidden"></p>
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
                        <div class="flex items-center justify-between py-4 border-b border-border"><div><p class="font-medium text-secondary">Notificari Vanzari</p><p class="text-sm text-muted">Email la fiecare vanzare</p></div><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="notif-sales" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div></label></div>
                        <div class="flex items-center justify-between py-4 border-b border-border"><div><p class="font-medium text-secondary">Rapoarte Zilnice</p><p class="text-sm text-muted">Sumar zilnic al vanzarilor</p></div><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="notif-daily" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div></label></div>
                        <div class="flex items-center justify-between py-4 border-b border-border"><div><p class="font-medium text-secondary">Alerte Stoc</p><p class="text-sm text-muted">Notificare stoc sub 10%</p></div><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="notif-stock" class="sr-only peer" checked><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div></label></div>
                        <div class="flex items-center justify-between py-4 border-b border-border"><div><p class="font-medium text-secondary">Sunet Notificari</p><p class="text-sm text-muted">Reda sunet la primirea notificarilor</p></div><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="notif-sound" class="sr-only peer" checked onchange="toggleNotificationSound(this.checked)"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div></label></div>
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
            <div id="sharelinks-section" class="settings-section hidden">
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-secondary">Link-uri de Monitorizare</h2>
                            <p class="text-sm text-muted mt-1">Genereaza link-uri unice pentru a permite altora sa vada statisticile evenimentelor tale in timp real.</p>
                        </div>
                        <button onclick="openShareLinkModal()" class="btn btn-primary">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Link nou
                        </button>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div class="text-sm text-blue-800">
                                <p class="font-medium mb-1">Cum functioneaza?</p>
                                <p>Selecteaza unul sau mai multe evenimente si genereaza un link unic. Oricine acceseaza link-ul va putea vedea in timp real: numele evenimentelor, locatia, data/ora, numarul de bilete puse in vanzare si cate s-au vandut.</p>
                            </div>
                        </div>
                    </div>

                    <div id="share-links-list">
                        <div class="text-center py-8 text-muted">Se incarca...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Share Link Create Modal -->
    <div id="share-link-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-secondary">Creeaza Link de Monitorizare</h3>
                <button onclick="closeShareLinkModal()">
                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form onsubmit="createShareLink(event)" class="space-y-4">
                <div>
                    <label class="label">Nume link (optional)</label>
                    <input type="text" id="share-link-name" class="input w-full" placeholder="ex: Link pentru sponsor" maxlength="100">
                </div>
                <div>
                    <label class="label">Parola de acces (optional)</label>
                    <input type="text" id="share-link-password" class="input w-full" placeholder="Lasa gol pentru acces liber" maxlength="50">
                    <p class="text-xs text-muted mt-1">Daca setezi o parola, vizitatorii vor trebui sa o introduca pentru a vedea datele.</p>
                </div>
                <div>
                    <label class="label">Selecteaza evenimente *</label>
                    <div id="share-events-loading" class="text-sm text-muted py-2">Se incarca evenimentele...</div>
                    <div id="share-events-list" class="hidden max-h-60 overflow-y-auto border border-border rounded-xl divide-y divide-border"></div>
                    <p id="share-events-empty" class="hidden text-sm text-muted py-2">Nu ai evenimente active.</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeShareLinkModal()" class="btn btn-secondary flex-1">Anuleaza</button>
                    <button type="submit" id="create-share-btn" class="btn btn-primary flex-1" disabled>Genereaza Link</button>
                </div>
            </form>
        </div>
    </div>

    <div id="bank-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-6"><h3 class="text-xl font-bold text-secondary">Adauga Cont Bancar</h3><button onclick="closeBankModal()"><svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form onsubmit="addBankAccount(event)" class="space-y-4">
                <div><label class="label">Nume Banca *</label><input type="text" id="bank-name" class="input w-full" required></div>
                <div>
                    <label class="label">IBAN *</label>
                    <input type="text" id="bank-iban" class="input w-full" required maxlength="24" oninput="validateIBAN(this)" placeholder="RO49AAAA1B31007593840000">
                    <div id="iban-validation" class="mt-1 text-xs hidden"></div>
                </div>
                <div><label class="label">Titular Cont *</label><input type="text" id="bank-holder" class="input w-full" required></div>
                <div class="flex gap-3"><button type="button" onclick="closeBankModal()" class="btn btn-secondary flex-1">Anuleaza</button><button type="submit" class="btn btn-primary flex-1">Adauga</button></div>
            </form>
        </div>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
document.addEventListener('DOMContentLoaded', function() { loadSettings(); loadBankAccounts(); loadContract(); loadShareLinks(); initNotificationSoundToggle(); const hash = window.location.hash.replace('#', ''); const validSections = ['profile','company','bank','contract','notifications','security','sharelinks']; if (hash && validSections.includes(hash)) showSection(hash); });

// Initialize notification sound toggle from saved preference
function initNotificationSoundToggle() {
    const checkbox = document.getElementById('notif-sound');
    if (checkbox && typeof AmbiletNotificationSound !== 'undefined') {
        checkbox.checked = AmbiletNotificationSound.isEnabled();
    }
}

// Toggle notification sound on/off
function toggleNotificationSound(enabled) {
    if (typeof AmbiletNotificationSound !== 'undefined') {
        AmbiletNotificationSound.setEnabled(enabled);
        // Play a test sound when enabling
        if (enabled) {
            AmbiletNotificationSound.play('default');
        }
        AmbiletNotifications.success(enabled ? 'Sunetul de notificari a fost activat' : 'Sunetul de notificari a fost dezactivat');
    }
}

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
            const data = response.data?.organizer || response.data || {};
            // Profile/Organizer info
            document.getElementById('org-name').value = data.name || '';
            document.getElementById('org-email').value = data.email || '';
            document.getElementById('org-phone').value = data.phone || '';
            document.getElementById('org-website').value = data.website || '';
            document.getElementById('org-description').value = data.description || '';
            // Company info
            document.getElementById('company-name').value = data.company_name || '';
            document.getElementById('company-cui').value = data.company_tax_id || '';
            document.getElementById('company-reg').value = data.company_registration || '';
            document.getElementById('company-vat').value = data.company_vat_payer ? '1' : '0';
            document.getElementById('company-address').value = data.company_address || '';
            // Support both field naming conventions (company_city vs city)
            document.getElementById('company-city').value = data.company_city || data.city || '';
            document.getElementById('company-county').value = data.company_county || data.county || '';
            document.getElementById('company-zip').value = data.company_zip || data.zip || '';
            // Guarantor/Personal info (read-only)
            document.getElementById('guarantor-first-name').value = data.guarantor_first_name || '';
            document.getElementById('guarantor-last-name').value = data.guarantor_last_name || '';
            document.getElementById('guarantor-cnp').value = data.guarantor_cnp || '';
            document.getElementById('guarantor-city').value = data.guarantor_city || '';
            document.getElementById('guarantor-address').value = data.guarantor_address || '';
            const idTypeLabels = { 'ci': 'Carte de Identitate', 'passport': 'Pasaport' };
            document.getElementById('guarantor-id-type').value = idTypeLabels[data.guarantor_id_type] || data.guarantor_id_type || '';
            document.getElementById('guarantor-id-series').value = data.guarantor_id_series || '';
            document.getElementById('guarantor-id-number').value = data.guarantor_id_number || '';
            document.getElementById('guarantor-id-issued-date').value = data.guarantor_id_issued_date || '';
            document.getElementById('guarantor-id-issued-by').value = data.guarantor_id_issued_by || '';
            // Hide personal info section if no guarantor data
            const hasGuarantorData = data.guarantor_first_name || data.guarantor_last_name || data.guarantor_cnp;
            document.getElementById('personal-info-section').style.display = hasGuarantorData ? '' : 'none';
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
        const data = {
            company_name: document.getElementById('company-name').value,
            company_tax_id: document.getElementById('company-cui').value,
            company_registration: document.getElementById('company-reg').value,
            company_address: document.getElementById('company-address').value,
            company_city: document.getElementById('company-city').value,
            company_county: document.getElementById('company-county').value,
            company_zip: document.getElementById('company-zip').value
        };
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
        if (response.success && response.data) {
            AmbiletNotifications.success('Date verificate');
            document.getElementById('company-name').value = response.data.company_name || '';
            document.getElementById('company-reg').value = response.data.reg_com || '';
            document.getElementById('company-address').value = response.data.address || '';
            document.getElementById('company-city').value = response.data.city || '';
            document.getElementById('company-county').value = response.data.county || '';
            document.getElementById('company-vat').value = response.data.vat_payer ? '1' : '0';
        } else { AmbiletNotifications.error(response.message || 'CUI invalid'); }
    } catch (error) { AmbiletNotifications.error('Eroare la verificare'); }
}
// Romanian IBAN validation
function validateIBAN(input) {
    const value = input.value.toUpperCase().replace(/\s/g, '');
    input.value = value;
    const validation = document.getElementById('iban-validation');

    if (!value) {
        validation.classList.add('hidden');
        input.classList.remove('border-green-500', 'border-red-500');
        return;
    }

    validation.classList.remove('hidden');

    // Romanian IBAN: RO + 2 check digits + 4 bank code + 16 account
    if (value.length < 2) {
        validation.textContent = 'IBAN-ul trebuie să înceapă cu RO';
        validation.className = 'mt-1 text-xs text-red-600';
        input.classList.add('border-red-500');
        input.classList.remove('border-green-500');
        return;
    }

    if (!value.startsWith('RO')) {
        validation.textContent = 'IBAN-ul românesc trebuie să înceapă cu RO';
        validation.className = 'mt-1 text-xs text-red-600';
        input.classList.add('border-red-500');
        input.classList.remove('border-green-500');
        return;
    }

    if (value.length < 24) {
        const remaining = 24 - value.length;
        validation.textContent = `Mai sunt necesare ${remaining} caractere (total: 24)`;
        validation.className = 'mt-1 text-xs text-yellow-600';
        input.classList.remove('border-red-500', 'border-green-500');
        return;
    }

    if (value.length > 24) {
        validation.textContent = 'IBAN-ul are prea multe caractere (max 24)';
        validation.className = 'mt-1 text-xs text-red-600';
        input.classList.add('border-red-500');
        input.classList.remove('border-green-500');
        return;
    }

    // Validate structure: RO + 2 digits + 4 alphanumeric (bank) + 16 alphanumeric (account)
    const ibanRegex = /^RO[0-9]{2}[A-Z]{4}[A-Z0-9]{16}$/;
    if (!ibanRegex.test(value)) {
        validation.textContent = 'Format invalid. Structură: RO + 2 cifre control + 4 litere bancă + 16 caractere cont';
        validation.className = 'mt-1 text-xs text-red-600';
        input.classList.add('border-red-500');
        input.classList.remove('border-green-500');
        return;
    }

    // IBAN checksum validation (MOD 97-10)
    if (!validateIBANChecksum(value)) {
        validation.textContent = 'Cifrele de control sunt invalide';
        validation.className = 'mt-1 text-xs text-red-600';
        input.classList.add('border-red-500');
        input.classList.remove('border-green-500');
        return;
    }

    // Extract and display bank code
    const bankCode = value.substring(4, 8);
    const bankNames = {
        'BTRL': 'Banca Transilvania',
        'BRDE': 'BRD',
        'BACX': 'Unicredit',
        'RNCB': 'BCR',
        'INGB': 'ING Bank',
        'RZBR': 'Raiffeisen',
        'PIRB': 'First Bank',
        'UGBI': 'Garanti BBVA',
        'CECE': 'CEC Bank',
        'NBOR': 'BNR',
        'PORL': 'Banca Românească'
    };
    const bankName = bankNames[bankCode] || bankCode;

    validation.textContent = `✓ IBAN valid - ${bankName}`;
    validation.className = 'mt-1 text-xs text-green-600';
    input.classList.add('border-green-500');
    input.classList.remove('border-red-500');
}

function validateIBANChecksum(iban) {
    // Move first 4 chars to end, replace letters with numbers (A=10, B=11, etc.)
    const rearranged = iban.substring(4) + iban.substring(0, 4);
    let numericStr = '';
    for (const char of rearranged) {
        if (char >= 'A' && char <= 'Z') {
            numericStr += (char.charCodeAt(0) - 55).toString();
        } else {
            numericStr += char;
        }
    }
    // MOD 97 check - use BigInt for large numbers
    let remainder = 0n;
    for (let i = 0; i < numericStr.length; i += 7) {
        const chunk = numericStr.substring(i, Math.min(i + 7, numericStr.length));
        remainder = BigInt(remainder.toString() + chunk) % 97n;
    }
    return remainder === 1n;
}

function openBankModal() { document.getElementById('bank-modal').classList.remove('hidden'); document.getElementById('bank-modal').classList.add('flex'); document.getElementById('bank-iban').value = ''; document.getElementById('iban-validation').classList.add('hidden'); document.getElementById('bank-iban').classList.remove('border-green-500', 'border-red-500'); }
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
        const response = await AmbiletAPI.post('/organizer/bank-accounts/' + id + '/primary', {});
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
            // Commission mode (included vs on_top)
            const modeLabels = { 'included': 'Inclus in pretul biletului', 'on_top': 'Adaugat peste pretul biletului' };
            const modeDescs = { 'included': 'Comisionul este inclus in pretul afisat al biletului', 'on_top': 'Comisionul se adauga separat la pretul biletului' };
            document.getElementById('contract-mode').textContent = modeLabels[data.commission_mode] || data.commission_mode || '-';
            document.getElementById('contract-mode-desc').textContent = modeDescs[data.commission_mode] || '';
            // Work mode (exclusive vs non_exclusive)
            const workModeLabels = { 'exclusive': 'Exclusiv', 'non_exclusive': 'Non-exclusiv' };
            const workModeDescs = {
                'exclusive': 'Vinzi bilete doar pe aceasta platforma',
                'non_exclusive': 'Vinzi bilete si pe alte platforme'
            };
            document.getElementById('contract-work-mode').textContent = workModeLabels[data.work_mode] || data.work_mode || '-';
            document.getElementById('contract-work-mode-desc').textContent = workModeDescs[data.work_mode] || '';
            if (data.terms && data.terms.length) {
                document.getElementById('contract-terms').innerHTML = data.terms.map(t => `
                    <div class="flex items-start gap-3 text-sm text-muted">
                        <svg class="w-4 h-4 text-success mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>${t}</span>
                    </div>
                `).join('');
            }
            // Show/hide download contract button based on contract existence
            const downloadBtn = document.getElementById('download-contract-btn');
            const noContractMsg = document.getElementById('no-contract-msg');
            if (data.has_contract) {
                downloadBtn.classList.remove('hidden');
                noContractMsg.classList.add('hidden');
            } else {
                downloadBtn.classList.add('hidden');
                noContractMsg.classList.remove('hidden');
            }
            // Show existing documents status
            if (data.documents) {
                if (data.documents.id_card) {
                    showUploadedDocument('id-card', 'Document incarcat');
                }
                if (data.documents.cui) {
                    showUploadedDocument('cui', 'Document incarcat');
                }
            }
        }
    } catch (error) { /* Contract info will show defaults */ }
}

function showUploadedDocument(type, filename) {
    const preview = document.getElementById(type + '-preview');
    const placeholder = document.getElementById(type + '-placeholder');
    const filenameEl = document.getElementById(type + '-filename');
    if (preview && placeholder && filenameEl) {
        filenameEl.textContent = filename;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
    }
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

// ==================== SHARE LINKS ====================

let shareLinksData = [];
let organizerEventsForShare = [];

async function loadShareLinks() {
    try {
        const response = await AmbiletAPI.get('/organizer/share-links');
        if (response.success) {
            shareLinksData = response.data?.links || [];
            renderShareLinks();
        } else {
            document.getElementById('share-links-list').innerHTML = '<div class="text-center py-8 text-muted">Eroare la incarcare</div>';
        }
    } catch (error) {
        document.getElementById('share-links-list').innerHTML = '<div class="text-center py-8 text-muted">Eroare la incarcare</div>';
    }
}

function slEscapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function renderShareLinks() {
    const container = document.getElementById('share-links-list');
    if (!shareLinksData.length) {
        container.innerHTML = `
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-muted/30 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                <p class="text-muted">Nu ai creat inca niciun link de monitorizare.</p>
                <button onclick="openShareLinkModal()" class="btn btn-primary mt-4">Creeaza primul link</button>
            </div>
        `;
        return;
    }

    container.innerHTML = shareLinksData.map(link => {
        const url = window.AMBILET.siteUrl + '/view/' + link.code;
        const eventCount = (link.event_ids || []).length;
        const isActive = link.is_active !== false;
        const createdDate = link.created_at ? new Date(link.created_at).toLocaleDateString('ro-RO') : '-';
        const accessCount = link.access_count || 0;

        return `
            <div class="flex items-start gap-4 p-4 bg-surface rounded-xl mb-3 ${!isActive ? 'opacity-60' : ''}">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="font-semibold text-secondary truncate">${slEscapeHtml(link.name || 'Link')}</h3>
                        ${link.has_password ? '<span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs rounded-full flex-shrink-0 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>Parola</span>' : ''}
                        ${!isActive ? '<span class="px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full flex-shrink-0">Inactiv</span>' : '<span class="px-2 py-0.5 bg-green-100 text-green-600 text-xs rounded-full flex-shrink-0">Activ</span>'}
                    </div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                        <span>${eventCount} eveniment${eventCount !== 1 ? 'e' : ''}</span>
                        <span>&middot;</span>
                        <span>${accessCount} accesari</span>
                        <span>&middot;</span>
                        <span>Creat: ${createdDate}</span>
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <code class="text-xs bg-white px-2 py-1 rounded border border-border truncate block max-w-[300px]">${slEscapeHtml(url)}</code>
                    </div>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0 pt-1">
                    <button onclick="copyShareLink('${link.code}')" class="p-2 text-muted hover:text-primary rounded-lg hover:bg-white" title="Copiaza link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                    </button>
                    <button onclick="window.open('/view/${link.code}', '_blank')" class="p-2 text-muted hover:text-primary rounded-lg hover:bg-white" title="Deschide link">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </button>
                    <button onclick="toggleShareLink('${link.code}', ${isActive ? 'false' : 'true'})" class="p-2 text-muted hover:text-yellow-600 rounded-lg hover:bg-white" title="${isActive ? 'Dezactiveaza' : 'Activeaza'}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${isActive ? 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'}"/></svg>
                    </button>
                    <button onclick="deleteShareLink('${link.code}')" class="p-2 text-muted hover:text-error rounded-lg hover:bg-white" title="Sterge">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

async function openShareLinkModal() {
    document.getElementById('share-link-modal').classList.remove('hidden');
    document.getElementById('share-link-modal').classList.add('flex');
    document.getElementById('share-link-name').value = '';
    document.getElementById('share-link-password').value = '';
    document.getElementById('create-share-btn').disabled = true;

    // Load events
    document.getElementById('share-events-loading').classList.remove('hidden');
    document.getElementById('share-events-list').classList.add('hidden');
    document.getElementById('share-events-empty').classList.add('hidden');

    try {
        const response = await AmbiletAPI.get('/organizer/events', { per_page: 50 });
        if (response.success) {
            organizerEventsForShare = response.data?.events || response.data || [];
            if (Array.isArray(organizerEventsForShare) && organizerEventsForShare.length > 0) {
                renderEventCheckboxes(organizerEventsForShare);
            } else {
                document.getElementById('share-events-loading').classList.add('hidden');
                document.getElementById('share-events-empty').classList.remove('hidden');
            }
        } else {
            document.getElementById('share-events-loading').classList.add('hidden');
            document.getElementById('share-events-empty').classList.remove('hidden');
        }
    } catch (error) {
        document.getElementById('share-events-loading').classList.add('hidden');
        document.getElementById('share-events-empty').classList.remove('hidden');
        document.getElementById('share-events-empty').textContent = 'Eroare la incarcarea evenimentelor.';
    }
}

function renderEventCheckboxes(events) {
    const container = document.getElementById('share-events-list');
    container.innerHTML = events.map(ev => {
        const evTitle = ev.title || ev.name || 'Eveniment';
        const evDate = ev.start_date || ev.date || '';
        const evStatus = ev.status || '';
        return `
            <label class="flex items-center gap-3 p-3 hover:bg-surface cursor-pointer">
                <input type="checkbox" value="${ev.id}" class="share-event-checkbox rounded border-border text-primary focus:ring-primary" onchange="updateShareBtnState()">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-secondary truncate">${slEscapeHtml(evTitle)}</p>
                    <p class="text-xs text-muted">${slEscapeHtml(evDate)}${evStatus ? ' &middot; ' + slEscapeHtml(evStatus) : ''}</p>
                </div>
            </label>
        `;
    }).join('');

    document.getElementById('share-events-loading').classList.add('hidden');
    container.classList.remove('hidden');
}

function updateShareBtnState() {
    const checked = document.querySelectorAll('.share-event-checkbox:checked');
    document.getElementById('create-share-btn').disabled = checked.length === 0;
}

function closeShareLinkModal() {
    document.getElementById('share-link-modal').classList.add('hidden');
    document.getElementById('share-link-modal').classList.remove('flex');
}

async function createShareLink(e) {
    e.preventDefault();

    const checked = document.querySelectorAll('.share-event-checkbox:checked');
    if (checked.length === 0) {
        AmbiletNotifications.error('Selecteaza cel putin un eveniment');
        return;
    }

    const eventIds = Array.from(checked).map(cb => parseInt(cb.value));
    const name = document.getElementById('share-link-name').value.trim();
    const password = document.getElementById('share-link-password').value.trim();

    try {
        const payload = { event_ids: eventIds, name: name };
        if (password) payload.password = password;
        const response = await AmbiletAPI.post('/organizer/share-links', payload);

        if (response.success) {
            closeShareLinkModal();
            AmbiletNotifications.success('Link creat cu succes!');

            // Copy to clipboard
            if (response.url) {
                try {
                    await navigator.clipboard.writeText(response.url);
                    AmbiletNotifications.info('Link-ul a fost copiat in clipboard');
                } catch (clipErr) { /* clipboard may not be available */ }
            }

            loadShareLinks();
        } else {
            AmbiletNotifications.error(response.message || response.error || 'Eroare la creare');
        }
    } catch (error) {
        AmbiletNotifications.error(error.message || 'Eroare la creare');
    }
}

async function copyShareLink(code) {
    const url = window.AMBILET.siteUrl + '/view/' + code;
    try {
        await navigator.clipboard.writeText(url);
        AmbiletNotifications.success('Link copiat in clipboard!');
    } catch (e) {
        // Fallback for older browsers
        const input = document.createElement('input');
        input.value = url;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        AmbiletNotifications.success('Link copiat!');
    }
}

async function toggleShareLink(code, active) {
    try {
        const response = await AmbiletAPI.put('/organizer/share-links/' + code, { is_active: active });
        if (response.success) {
            AmbiletNotifications.success(active ? 'Link activat' : 'Link dezactivat');
            loadShareLinks();
        } else {
            AmbiletNotifications.error(response.message || response.error || 'Eroare');
        }
    } catch (error) {
        AmbiletNotifications.error(error.message || 'Eroare');
    }
}

async function deleteShareLink(code) {
    if (!confirm('Esti sigur ca vrei sa stergi acest link? Oricine il foloseste nu va mai putea accesa statisticile.')) return;
    try {
        const response = await AmbiletAPI.delete('/organizer/share-links/' + code);
        if (response.success) {
            AmbiletNotifications.success('Link sters');
            loadShareLinks();
        } else {
            AmbiletNotifications.error(response.message || response.error || 'Eroare la stergere');
        }
    } catch (error) {
        AmbiletNotifications.error(error.message || 'Eroare la stergere');
    }
}

// Document upload functions
function handleDragOver(e, type) {
    e.preventDefault();
    e.stopPropagation();
    const dropzone = document.getElementById(type + '-dropzone');
    dropzone.classList.add('border-primary', 'bg-primary/10');
}

function handleDragLeave(e, type) {
    e.preventDefault();
    e.stopPropagation();
    const dropzone = document.getElementById(type + '-dropzone');
    dropzone.classList.remove('border-primary', 'bg-primary/10');
}

function handleDrop(e, docType) {
    e.preventDefault();
    e.stopPropagation();
    const type = docType === 'id_card' ? 'id-card' : 'cui';
    const dropzone = document.getElementById(type + '-dropzone');
    dropzone.classList.remove('border-primary', 'bg-primary/10');

    const files = e.dataTransfer.files;
    if (files.length > 0) {
        processFile(files[0], docType);
    }
}

function handleFileSelect(e, docType) {
    const files = e.target.files;
    if (files.length > 0) {
        processFile(files[0], docType);
    }
}

function processFile(file, docType) {
    const type = docType === 'id_card' ? 'id-card' : 'cui';
    const status = document.getElementById(type + '-status');
    const preview = document.getElementById(type + '-preview');
    const placeholder = document.getElementById(type + '-placeholder');
    const filename = document.getElementById(type + '-filename');

    // Validate file type
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
        status.textContent = 'Tip de fișier invalid. Acceptăm doar PDF, JPG sau PNG.';
        status.className = 'mt-2 text-xs text-red-600';
        status.classList.remove('hidden');
        return;
    }

    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        status.textContent = 'Fișierul este prea mare. Dimensiunea maximă este 5MB.';
        status.className = 'mt-2 text-xs text-red-600';
        status.classList.remove('hidden');
        return;
    }

    // Show uploading state
    status.textContent = 'Se încarcă...';
    status.className = 'mt-2 text-xs text-blue-600';
    status.classList.remove('hidden');

    // Upload file
    uploadDocument(file, docType).then(response => {
        if (response.success) {
            filename.textContent = file.name;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
            status.textContent = 'Documentul a fost încărcat cu succes!';
            status.className = 'mt-2 text-xs text-green-600';
        } else {
            status.textContent = response.message || 'Eroare la încărcare';
            status.className = 'mt-2 text-xs text-red-600';
        }
    }).catch(error => {
        console.error('Upload error:', error);
        status.textContent = 'Eroare la încărcarea fișierului';
        status.className = 'mt-2 text-xs text-red-600';
    });
}

async function uploadDocument(file, docType) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', docType);

    try {
        const token = AmbiletAuth.getToken();
        const response = await fetch('/api/proxy.php?action=organizer.documents.upload', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: formData
        });
        return await response.json();
    } catch (error) {
        return { success: false, message: 'Eroare de rețea' };
    }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
