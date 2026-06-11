<?php
/**
 * bilete.online — Organizator › Setări (v3).
 * Route: /organizator/setari
 *
 * 7-tab account settings: Profil, Companie (SC1+SC2), Conturi bancare,
 * Contract, Notificări, Securitate, Link-uri share. Ported from ambilet to
 * v3 + shell, wired to BileteOnlineAPI organizer endpoints (settings/profile/
 * verify-cui/bank-accounts/password/contract/share-links) + documents.upload.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Setări';
$currentPage = 'settings';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';

$inp   = 'w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink';
$inpRo = 'w-full rounded-xl border-2 border-ink/15 bg-paper px-4 py-2.5 text-sm text-ink-soft outline-none';
$lbl   = 'mb-1.5 block text-xs font-bold text-ink-soft';
$card  = 'rounded-2xl border-2 border-ink bg-paper p-6';
$btnP  = 'rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d';
$btnS  = 'inline-flex items-center gap-2 rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-3xl font-bold leading-none">Setări</h1>
                <p class="mt-1.5 text-sm text-ink-soft">Gestionează setările contului tău.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="/organizator/echipa" class="<?= $btnS ?>"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Echipă</a>
                <a href="/organizator/apidoc" class="<?= $btnS ?>"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>API</a>
            </div>
        </div>

        <div class="mb-8 flex flex-wrap gap-2 border-b-2 border-ink/10 pb-4">
            <button onclick="showSection('profile')" class="settings-tab active rounded-full bg-vermilion px-4 py-2 text-sm font-bold text-paper transition" data-section="profile">Profil</button>
            <button onclick="showSection('company')" class="settings-tab rounded-full px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink" data-section="company">Companie</button>
            <button onclick="showSection('bank')" class="settings-tab rounded-full px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink" data-section="bank">Conturi bancare</button>
            <button onclick="showSection('contract')" class="settings-tab rounded-full px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink" data-section="contract">Contract</button>
            <button onclick="showSection('notifications')" class="settings-tab rounded-full px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink" data-section="notifications">Notificări</button>
            <button onclick="showSection('security')" class="settings-tab rounded-full px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink" data-section="security">Securitate</button>
            <button onclick="showSection('sharelinks')" class="settings-tab rounded-full px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink" data-section="sharelinks">Link-uri share</button>
        </div>

        <!-- PROFILE -->
        <div id="profile-section" class="settings-section">
            <div class="<?= $card ?> mb-6">
                <h2 class="mb-6 font-display text-lg font-bold">Profil organizator</h2>
                <form onsubmit="saveProfile(event)" class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-2"><div><label class="<?= $lbl ?>">Nume organizator *</label><input type="text" id="org-name" class="<?= $inp ?>" required></div><div><label class="<?= $lbl ?>">Email contact *</label><input type="email" id="org-email" class="<?= $inp ?>" required></div></div>
                    <div class="grid gap-4 lg:grid-cols-2"><div><label class="<?= $lbl ?>">Telefon</label><input type="tel" id="org-phone" class="<?= $inp ?>"></div><div><label class="<?= $lbl ?>">Website</label><input type="url" id="org-website" class="<?= $inp ?>"></div></div>
                    <div><label class="<?= $lbl ?>">Descriere</label><textarea id="org-description" rows="4" class="<?= $inp ?>"></textarea></div>
                    <div class="flex justify-end pt-2"><button type="submit" class="<?= $btnP ?>">Salvează</button></div>
                </form>
            </div>
            <div class="<?= $card ?>" id="personal-info-section">
                <h2 class="mb-2 font-display text-lg font-bold">Date personale / garant</h2>
                <p class="mb-6 text-sm text-ink-soft">Informații capturate la înregistrare. Contactează suportul pentru modificări.</p>
                <div class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-2"><div><label class="<?= $lbl ?>">Prenume</label><input type="text" id="guarantor-first-name" class="<?= $inpRo ?>" readonly></div><div><label class="<?= $lbl ?>">Nume</label><input type="text" id="guarantor-last-name" class="<?= $inpRo ?>" readonly></div></div>
                    <div class="grid gap-4 lg:grid-cols-2"><div><label class="<?= $lbl ?>">CNP</label><input type="text" id="guarantor-cnp" class="<?= $inpRo ?>" readonly></div><div><label class="<?= $lbl ?>">Localitate</label><input type="text" id="guarantor-city" class="<?= $inpRo ?>" readonly></div></div>
                    <div><label class="<?= $lbl ?>">Adresă domiciliu</label><input type="text" id="guarantor-address" class="<?= $inpRo ?>" readonly></div>
                    <div class="grid gap-4 lg:grid-cols-4"><div><label class="<?= $lbl ?>">Tip act</label><input type="text" id="guarantor-id-type" class="<?= $inpRo ?>" readonly></div><div><label class="<?= $lbl ?>">Serie</label><input type="text" id="guarantor-id-series" class="<?= $inpRo ?>" readonly></div><div><label class="<?= $lbl ?>">Număr</label><input type="text" id="guarantor-id-number" class="<?= $inpRo ?>" readonly></div><div><label class="<?= $lbl ?>">Data eliberării</label><input type="text" id="guarantor-id-issued-date" class="<?= $inpRo ?>" readonly></div></div>
                    <div><label class="<?= $lbl ?>">Eliberat de</label><input type="text" id="guarantor-id-issued-by" class="<?= $inpRo ?>" readonly></div>
                </div>
            </div>
        </div>

        <!-- COMPANY -->
        <div id="company-section" class="settings-section hidden">
            <div class="<?= $card ?> mb-6">
                <h2 class="mb-6 font-display text-lg font-bold">Date companie principală (SC1)</h2>
                <form id="form-company-primary" onsubmit="saveCompany(event)" class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-2"><div><label class="<?= $lbl ?>">Denumire firmă *</label><input type="text" id="company-name" class="<?= $inp ?>" required></div><div><label class="<?= $lbl ?>">CUI / CIF *</label><div class="flex gap-2"><input type="text" id="company-cui" class="<?= $inp ?> flex-1" required><button type="button" onclick="verifyCUI()" class="rounded-full border-2 border-ink px-4 text-sm font-bold transition hover:bg-ink hover:text-paper">Verifică ANAF</button></div></div></div>
                    <div class="grid gap-4 lg:grid-cols-2"><div><label class="<?= $lbl ?>">Nr. Reg. Comerțului</label><input type="text" id="company-reg" class="<?= $inp ?>"></div><div><label class="<?= $lbl ?>">Plătitor TVA</label><select id="company-vat" class="<?= $inp ?>"><option value="0">Nu</option><option value="1">Da</option></select></div></div>
                    <div><label class="<?= $lbl ?>">Adresă sediu *</label><input type="text" id="company-address" class="<?= $inp ?>" required></div>
                    <div class="grid gap-4 lg:grid-cols-3"><div><label class="<?= $lbl ?>">Oraș *</label><input type="text" id="company-city" class="<?= $inp ?>" required></div><div><label class="<?= $lbl ?>">Județ *</label><input type="text" id="company-county" class="<?= $inp ?>" required></div><div><label class="<?= $lbl ?>">Cod poștal</label><input type="text" id="company-zip" class="<?= $inp ?>"></div></div>
                    <div class="flex justify-end pt-2"><button type="submit" class="<?= $btnP ?>">Salvează SC1</button></div>
                </form>
            </div>
            <div class="<?= $card ?>">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold">Companie secundară (SC2)</h2>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="has-secondary-issuer" onchange="toggleSecondaryCompany(this.checked)" class="h-4 w-4 accent-vermilion"><span>Am o a doua societate emitentă</span></label>
                </div>
                <p class="mb-4 text-xs text-ink-soft">Biletele de acces pot fi emise pe SC1, iar serviciile conexe (parcare, închirieri, activități) pe SC2. Asociază fiecare cont bancar cu societatea corespunzătoare din tab-ul „Conturi bancare".</p>
                <form id="form-company-secondary" onsubmit="saveSecondaryCompany(event)" class="pointer-events-none space-y-4 opacity-50">
                    <div class="grid gap-4 lg:grid-cols-2"><div><label class="<?= $lbl ?>">Denumire firmă</label><input type="text" id="secondary-company-name" class="<?= $inp ?>"></div><div><label class="<?= $lbl ?>">CUI / CIF</label><input type="text" id="secondary-company-cui" class="<?= $inp ?>"></div></div>
                    <div><label class="<?= $lbl ?>">Nr. Reg. Comerțului</label><input type="text" id="secondary-company-reg" class="<?= $inp ?>"></div>
                    <div><label class="<?= $lbl ?>">Adresă sediu</label><input type="text" id="secondary-company-address" class="<?= $inp ?>"></div>
                    <div class="grid gap-4 lg:grid-cols-3"><div><label class="<?= $lbl ?>">Oraș</label><input type="text" id="secondary-company-city" class="<?= $inp ?>"></div><div><label class="<?= $lbl ?>">Județ</label><input type="text" id="secondary-company-county" class="<?= $inp ?>"></div><div><label class="<?= $lbl ?>">Cod poștal</label><input type="text" id="secondary-company-zip" class="<?= $inp ?>"></div></div>
                    <div class="flex justify-end pt-2"><button type="submit" class="<?= $btnP ?>">Salvează SC2</button></div>
                </form>
            </div>
        </div>

        <!-- BANK -->
        <div id="bank-section" class="settings-section hidden">
            <div class="<?= $card ?>">
                <div class="mb-6 flex items-center justify-between"><h2 class="font-display text-lg font-bold">Conturi bancare</h2><button onclick="openBankModal()" class="<?= $btnP ?> inline-flex items-center gap-2"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Adaugă cont</button></div>
                <div id="bank-accounts-list" class="space-y-4"></div>
            </div>
        </div>

        <!-- CONTRACT -->
        <div id="contract-section" class="settings-section hidden">
            <div class="<?= $card ?>">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold">Contract marketplace</h2>
                    <button id="download-contract-btn" onclick="downloadContract()" class="<?= $btnP ?> hidden inline-flex items-center gap-2"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>Descarcă contract</button>
                    <span id="no-contract-msg" class="hidden text-sm text-ink-soft">Contract negenerat</span>
                </div>
                <div class="mb-6 flex items-start gap-3 rounded-xl border-2 border-sky/20 bg-sky/5 p-4">
                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-sky" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm text-ink">Contractul este generat automat pe baza datelor tale de companie și a condițiilor comerciale agreate cu <?= htmlspecialchars(SITE_NAME) ?>.</p>
                </div>
                <div class="space-y-6">
                    <div class="grid gap-6 md:grid-cols-3">
                        <div class="rounded-xl bg-paper-2 p-5"><h3 class="mb-3 font-bold">Comision</h3><p id="contract-commission" class="mb-1 font-display text-3xl font-bold text-vermilion">-</p><p id="contract-commission-note" class="text-sm text-ink-soft">conform contractului</p></div>
                        <div class="rounded-xl bg-paper-2 p-5"><h3 class="mb-3 font-bold">Mod de lucru</h3><p id="contract-work-mode" class="text-lg font-bold">-</p><p id="contract-work-mode-desc" class="mt-1 text-sm text-ink-soft">-</p></div>
                        <div class="rounded-xl bg-paper-2 p-5"><h3 class="mb-3 font-bold">Mod operare comision</h3><p id="contract-mode" class="text-lg font-bold">-</p><p id="contract-mode-desc" class="mt-1 text-sm text-ink-soft">-</p></div>
                    </div>
                    <div class="border-t-2 border-ink/10 pt-6">
                        <h3 class="mb-4 font-bold">Condiții contractuale</h3>
                        <div class="space-y-3" id="contract-terms">
                            <div class="flex items-start gap-3 text-sm text-ink-soft"><svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-forest" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><span>Plățile se efectuează conform termenelor agreate după activitate.</span></div>
                            <div class="flex items-start gap-3 text-sm text-ink-soft"><svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-forest" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><span>Rambursările se procesează conform politicii platformei.</span></div>
                            <div class="flex items-start gap-3 text-sm text-ink-soft"><svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-forest" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><span>Organizatorul este responsabil de livrarea activității conform descrierii.</span></div>
                        </div>
                    </div>
                    <div class="border-t-2 border-ink/10 pt-6">
                        <h3 class="mb-4 font-bold">Documente necesare</h3>
                        <p class="mb-4 text-sm text-ink-soft">Pentru activarea contului și procesarea plăților, avem nevoie de următoarele documente:</p>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-bold">Copie BI/CI (reprezentant legal) *</label>
                                <div id="id-card-dropzone" class="cursor-pointer rounded-xl border-2 border-dashed border-ink/20 p-6 text-center transition hover:border-vermilion hover:bg-vermilion/5" ondragover="handleDragOver(event,'id-card')" ondragleave="handleDragLeave(event,'id-card')" ondrop="handleDrop(event,'id_card')" onclick="document.getElementById('id-card-input').click()">
                                    <input type="file" id="id-card-input" class="hidden" accept=".pdf,.jpg,.jpeg,.png" onchange="handleFileSelect(event,'id_card')">
                                    <div id="id-card-preview" class="hidden"><div class="flex items-center justify-center gap-3"><svg class="h-8 w-8 text-forest" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><div class="text-left"><p id="id-card-filename" class="text-sm font-bold"></p><p class="text-xs text-ink-soft">Click pentru a schimba</p></div></div></div>
                                    <div id="id-card-placeholder"><svg class="mx-auto mb-2 h-10 w-10 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg><p class="text-sm text-ink-soft">Trage fișierul aici sau click pentru a selecta</p><p class="mt-1 text-xs text-ink-soft">PDF, JPG sau PNG (max 5MB)</p></div>
                                </div>
                                <p id="id-card-status" class="mt-2 hidden text-xs"></p>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-bold">Copie CUI / Certificat înregistrare *</label>
                                <div id="cui-dropzone" class="cursor-pointer rounded-xl border-2 border-dashed border-ink/20 p-6 text-center transition hover:border-vermilion hover:bg-vermilion/5" ondragover="handleDragOver(event,'cui')" ondragleave="handleDragLeave(event,'cui')" ondrop="handleDrop(event,'cui_document')" onclick="document.getElementById('cui-input').click()">
                                    <input type="file" id="cui-input" class="hidden" accept=".pdf,.jpg,.jpeg,.png" onchange="handleFileSelect(event,'cui_document')">
                                    <div id="cui-preview" class="hidden"><div class="flex items-center justify-center gap-3"><svg class="h-8 w-8 text-forest" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><div class="text-left"><p id="cui-filename" class="text-sm font-bold"></p><p class="text-xs text-ink-soft">Click pentru a schimba</p></div></div></div>
                                    <div id="cui-placeholder"><svg class="mx-auto mb-2 h-10 w-10 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg><p class="text-sm text-ink-soft">Trage fișierul aici sau click pentru a selecta</p><p class="mt-1 text-xs text-ink-soft">PDF, JPG sau PNG (max 5MB)</p></div>
                                </div>
                                <p id="cui-status" class="mt-2 hidden text-xs"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NOTIFICATIONS -->
        <div id="notifications-section" class="settings-section hidden">
            <div class="<?= $card ?>">
                <h2 class="mb-6 font-display text-lg font-bold">Preferințe notificări</h2>
                <form onsubmit="saveNotifications(event)" class="space-y-1">
                    <div class="flex items-center justify-between border-b border-ink/10 py-4"><div><p class="font-bold">Notificări vânzări</p><p class="text-sm text-ink-soft">Email la fiecare vânzare</p></div><label class="relative inline-flex cursor-pointer items-center"><input type="checkbox" id="notif-sales" class="peer sr-only"><div class="h-6 w-11 rounded-full bg-ink/15 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-paper after:transition-all after:content-[''] peer-checked:bg-vermilion peer-checked:after:translate-x-full"></div></label></div>
                    <div class="flex items-center justify-between border-b border-ink/10 py-4"><div><p class="font-bold">Rapoarte zilnice</p><p class="text-sm text-ink-soft">Sumar zilnic al vânzărilor</p></div><label class="relative inline-flex cursor-pointer items-center"><input type="checkbox" id="notif-daily" class="peer sr-only"><div class="h-6 w-11 rounded-full bg-ink/15 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-paper after:transition-all after:content-[''] peer-checked:bg-vermilion peer-checked:after:translate-x-full"></div></label></div>
                    <div class="flex items-center justify-between py-4"><div><p class="font-bold">Alerte stoc</p><p class="text-sm text-ink-soft">Notificare stoc sub 10%</p></div><label class="relative inline-flex cursor-pointer items-center"><input type="checkbox" id="notif-stock" class="peer sr-only" checked><div class="h-6 w-11 rounded-full bg-ink/15 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-paper after:transition-all after:content-[''] peer-checked:bg-vermilion peer-checked:after:translate-x-full"></div></label></div>
                    <div class="flex justify-end pt-4"><button type="submit" class="<?= $btnP ?>">Salvează</button></div>
                </form>
            </div>
        </div>

        <!-- SECURITY -->
        <div id="security-section" class="settings-section hidden">
            <div class="<?= $card ?>">
                <h2 class="mb-6 font-display text-lg font-bold">Schimbă parola</h2>
                <form onsubmit="changePassword(event)" class="max-w-md space-y-4">
                    <div><label class="<?= $lbl ?>">Parola curentă</label><input type="password" id="current-password" class="<?= $inp ?>" required></div>
                    <div><label class="<?= $lbl ?>">Parola nouă</label><input type="password" id="new-password" class="<?= $inp ?>" required minlength="8"></div>
                    <div><label class="<?= $lbl ?>">Confirmă parola</label><input type="password" id="confirm-password" class="<?= $inp ?>" required></div>
                    <button type="submit" class="<?= $btnP ?>">Schimbă parola</button>
                </form>
            </div>
        </div>

        <!-- SHARE LINKS -->
        <div id="sharelinks-section" class="settings-section hidden">
            <div class="<?= $card ?>">
                <div class="mb-6 flex items-center justify-between">
                    <div><h2 class="font-display text-lg font-bold">Link-uri de monitorizare</h2><p class="mt-1 text-sm text-ink-soft">Generează link-uri unice pentru a permite altora să vadă statisticile activităților tale în timp real.</p></div>
                    <button onclick="openShareLinkModal()" class="<?= $btnP ?> inline-flex items-center gap-2"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Link nou</button>
                </div>
                <div class="mb-6 flex items-start gap-3 rounded-xl border-2 border-sky/20 bg-sky/5 p-4">
                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-sky" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="text-sm text-ink"><p class="mb-1 font-bold">Cum funcționează?</p><p>Selectează una sau mai multe activități și generează un link unic. Oricine accesează link-ul va putea vedea în timp real: numele activităților, locația, data/ora, numărul de bilete puse în vânzare și câte s-au vândut.</p></div>
                </div>
                <div id="share-links-list"><div class="py-8 text-center text-ink-soft">Se încarcă…</div></div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<!-- Share link modal -->
<div id="share-link-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-deep">
        <div class="mb-6 flex items-center justify-between"><h3 class="font-display text-xl font-bold">Creează link de monitorizare</h3><button onclick="closeShareLinkModal()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button></div>
        <form onsubmit="createShareLink(event)" class="space-y-4">
            <div><label class="<?= $lbl ?>">Nume link (opțional)</label><input type="text" id="share-link-name" class="<?= $inp ?>" placeholder="ex: Link pentru sponsor" maxlength="100"></div>
            <div><label class="<?= $lbl ?>">Parolă de acces (opțional)</label><input type="text" id="share-link-password" class="<?= $inp ?>" placeholder="Lasă gol pentru acces liber" maxlength="50"><p class="mt-1 text-xs text-ink-soft">Dacă setezi o parolă, vizitatorii vor trebui să o introducă pentru a vedea datele.</p></div>
            <div>
                <label class="<?= $lbl ?>">Selectează activități *</label>
                <div id="share-events-loading" class="py-2 text-sm text-ink-soft">Se încarcă activitățile…</div>
                <div id="share-events-list" class="hidden max-h-60 divide-y divide-ink/10 overflow-y-auto rounded-xl border-2 border-ink/15"></div>
                <p id="share-events-empty" class="hidden py-2 text-sm text-ink-soft">Nu ai activități active.</p>
            </div>
            <label class="flex cursor-pointer items-center gap-3"><input type="checkbox" id="share-link-show-participants" class="rounded text-vermilion"><div><p class="text-sm font-bold">Afișează participanți</p><p class="text-xs text-ink-soft">Numele și telefonul participanților vor fi vizibile în pagina link-ului.</p></div></label>
            <label class="flex cursor-pointer items-center gap-3"><input type="checkbox" id="share-link-show-revenue" class="rounded text-vermilion"><div><p class="text-sm font-bold">Afișează încasări</p><p class="text-xs text-ink-soft">Cardul „Încasări nete" și suma încasată per activitate vor fi vizibile.</p></div></label>
            <div class="flex gap-3 pt-2"><button type="button" onclick="closeShareLinkModal()" class="flex-1 rounded-full border-2 border-ink py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Anulează</button><button type="submit" id="create-share-btn" class="flex-1 rounded-full bg-vermilion py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d disabled:cursor-not-allowed disabled:opacity-50" disabled>Generează link</button></div>
        </form>
    </div>
</div>

<!-- Bank modal -->
<div id="bank-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-deep">
        <div class="mb-6 flex items-center justify-between"><h3 class="font-display text-xl font-bold">Adaugă cont bancar</h3><button onclick="closeBankModal()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button></div>
        <form onsubmit="addBankAccount(event)" class="space-y-4">
            <div><label class="<?= $lbl ?>">Nume bancă *</label><input type="text" id="bank-name" class="<?= $inp ?>" required></div>
            <div><label class="<?= $lbl ?>">IBAN *</label><input type="text" id="bank-iban" class="<?= $inp ?>" required maxlength="24" oninput="validateIBAN(this)" placeholder="RO49AAAA1B31007593840000"><div id="iban-validation" class="mt-1 hidden text-xs"></div></div>
            <div><label class="<?= $lbl ?>">Titular cont *</label><input type="text" id="bank-holder" class="<?= $inp ?>" required></div>
            <div><label class="<?= $lbl ?>">Societate emitentă *</label><select id="bank-issuing-company" class="<?= $inp ?>"><option value="primary">Companie principală (SC1)</option><option value="secondary">Companie secundară (SC2)</option></select><p class="mt-1 text-xs text-ink-soft">Contul va fi folosit pentru încasări pentru această societate.</p></div>
            <div class="flex gap-3"><button type="button" onclick="closeBankModal()" class="flex-1 rounded-full border-2 border-ink py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Anulează</button><button type="submit" class="flex-1 rounded-full bg-vermilion py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Adaugă</button></div>
        </form>
    </div>
</div>

<?php
$scriptsExtra = "<script>\nwindow.__ORG = " . json_encode(['siteUrl' => rtrim(SITE_URL, '/')]) . ";\n</script>\n";
$scriptsExtra .= <<<'JS'
<script>
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error' || type === 'warning') alert(msg);
}
function siteUrl() { return (window.BILETEONLINE && window.BILETEONLINE.siteUrl) || (window.__ORG && window.__ORG.siteUrl) || ''; }
function slEscapeHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', function () {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
    loadSettings(); loadBankAccounts(); loadContract(); loadShareLinks();
    const hash = window.location.hash.replace('#', '');
    const valid = ['profile', 'company', 'bank', 'contract', 'notifications', 'security', 'sharelinks'];
    if (hash && valid.includes(hash)) showSection(hash);
});

function showSection(section) {
    document.querySelectorAll('.settings-tab').forEach(t => { t.classList.remove('active', 'bg-vermilion', 'text-paper'); t.classList.add('text-ink-soft'); });
    const tab = document.querySelector(`.settings-tab[data-section="${section}"]`);
    if (tab) { tab.classList.add('active', 'bg-vermilion', 'text-paper'); tab.classList.remove('text-ink-soft'); }
    document.querySelectorAll('.settings-section').forEach(s => s.classList.add('hidden'));
    document.getElementById(`${section}-section`).classList.remove('hidden');
    window.location.hash = section;
}

async function loadSettings() {
    try {
        const r = await BileteOnlineAPI.get('/organizer/settings');
        if (!r || !r.success) return;
        const d = (r.data && r.data.organizer) || r.data || {};
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v || ''; };
        set('org-name', d.name); set('org-email', d.email); set('org-phone', d.phone); set('org-website', d.website); set('org-description', d.description);
        set('company-name', d.company_name); set('company-cui', d.company_tax_id); set('company-reg', d.company_registration);
        document.getElementById('company-vat').value = d.company_vat_payer ? '1' : '0';
        set('company-address', d.company_address); set('company-city', d.company_city || d.city); set('company-county', d.company_county || d.county); set('company-zip', d.company_zip || d.zip);
        set('guarantor-first-name', d.guarantor_first_name); set('guarantor-last-name', d.guarantor_last_name); set('guarantor-cnp', d.guarantor_cnp); set('guarantor-city', d.guarantor_city); set('guarantor-address', d.guarantor_address);
        const idTypeLabels = { ci: 'Carte de identitate', passport: 'Pașaport' };
        set('guarantor-id-type', idTypeLabels[d.guarantor_id_type] || d.guarantor_id_type);
        set('guarantor-id-series', d.guarantor_id_series); set('guarantor-id-number', d.guarantor_id_number); set('guarantor-id-issued-date', d.guarantor_id_issued_date); set('guarantor-id-issued-by', d.guarantor_id_issued_by);
        document.getElementById('personal-info-section').style.display = (d.guarantor_first_name || d.guarantor_last_name || d.guarantor_cnp) ? '' : 'none';
        const secToggle = document.getElementById('has-secondary-issuer');
        if (secToggle) { secToggle.checked = !!d.has_secondary_issuer; toggleSecondaryCompany(!!d.has_secondary_issuer); }
        set('secondary-company-name', d.secondary_company_name); set('secondary-company-cui', d.secondary_company_tax_id); set('secondary-company-reg', d.secondary_company_registration);
        set('secondary-company-address', d.secondary_company_address); set('secondary-company-city', d.secondary_company_city); set('secondary-company-county', d.secondary_company_county); set('secondary-company-zip', d.secondary_company_zip);
    } catch (e) {}
}

function toggleSecondaryCompany(enabled) {
    const form = document.getElementById('form-company-secondary');
    if (!form) return;
    if (enabled) form.classList.remove('opacity-50', 'pointer-events-none');
    else form.classList.add('opacity-50', 'pointer-events-none');
}

async function saveProfile(e) {
    e.preventDefault();
    const data = { name: org('org-name'), email: org('org-email'), phone: org('org-phone'), website: org('org-website'), description: org('org-description') };
    try { const r = await BileteOnlineAPI.put('/organizer/settings/profile', data); ok(r, 'Profilul a fost salvat.'); } catch (e) { orgNotify('Eroare la salvare.', 'error'); }
}
async function saveCompany(e) {
    e.preventDefault();
    const data = { company_name: org('company-name'), company_tax_id: org('company-cui'), company_registration: org('company-reg'), company_address: org('company-address'), company_city: org('company-city'), company_county: org('company-county'), company_zip: org('company-zip') };
    try { const r = await BileteOnlineAPI.put('/organizer/settings/company', data); ok(r, 'Datele companiei au fost salvate.'); } catch (e) { orgNotify('Eroare la salvare.', 'error'); }
}
async function saveSecondaryCompany(e) {
    e.preventDefault();
    const data = { has_secondary_issuer: document.getElementById('has-secondary-issuer').checked, secondary_company_name: org('secondary-company-name'), secondary_company_tax_id: org('secondary-company-cui'), secondary_company_registration: org('secondary-company-reg'), secondary_company_address: org('secondary-company-address'), secondary_company_city: org('secondary-company-city'), secondary_company_county: org('secondary-company-county'), secondary_company_zip: org('secondary-company-zip') };
    try { const r = await BileteOnlineAPI.put('/organizer/profile', data); ok(r, 'Datele SC2 au fost salvate.'); } catch (e) { orgNotify('Eroare la salvare.', 'error'); }
}
function org(id) { return document.getElementById(id).value; }
function ok(r, msg) { if (r && r.success) orgNotify(msg, 'success'); else orgNotify((r && r.message) || 'Eroare la salvare.', 'error'); }

async function verifyCUI() {
    const cui = document.getElementById('company-cui').value;
    if (!cui) { orgNotify('Introdu CUI-ul.', 'error'); return; }
    orgNotify('Verificare ANAF…', 'info');
    try {
        const r = await BileteOnlineAPI.post('/organizer/settings/verify-cui', { cui });
        if (r && r.success && r.data) {
            orgNotify('Date verificate.', 'success');
            const s = (id, v) => { document.getElementById(id).value = v || ''; };
            s('company-name', r.data.company_name); s('company-reg', r.data.reg_com); s('company-address', r.data.address); s('company-city', r.data.city); s('company-county', r.data.county);
            document.getElementById('company-vat').value = r.data.vat_payer ? '1' : '0';
        } else orgNotify((r && r.message) || 'CUI invalid.', 'error');
    } catch (e) { orgNotify('Eroare la verificare.', 'error'); }
}

async function loadBankAccounts() {
    try { const r = await BileteOnlineAPI.get('/organizer/bank-accounts'); renderBankAccounts((r && r.success && r.data && r.data.accounts) || []); }
    catch (e) { renderBankAccounts([]); }
}
function renderBankAccounts(accounts) {
    const c = document.getElementById('bank-accounts-list');
    if (!accounts.length) { c.innerHTML = '<div class="p-6 text-center text-ink-soft">Nu ai conturi bancare adăugate.</div>'; return; }
    c.innerHTML = accounts.map(a => {
        const issuer = a.issuing_company || 'primary';
        const label = issuer === 'secondary' ? 'SC2' : 'SC1';
        const color = issuer === 'secondary' ? 'bg-ochre/15 text-ochre' : 'bg-sky/15 text-sky';
        return `<div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-paper-2 p-4 ${a.is_primary ? 'ring-2 ring-vermilion' : ''}">
            <div class="flex min-w-0 items-center gap-4"><span class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-xl border-2 border-ink/10 bg-paper"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></span>
            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="font-bold">${slEscapeHtml(a.bank)}</p><span class="rounded-full ${color} px-2 py-0.5 text-[10px] font-bold">${label}</span>${a.is_primary ? '<span class="rounded-full bg-vermilion/10 px-2 py-0.5 text-xs text-vermilion">Principal</span>' : ''}</div><p class="truncate font-mono text-xs text-ink-soft">${slEscapeHtml(a.iban)}</p></div></div>
            <div class="flex flex-wrap items-center gap-2">
                <select onchange="changeBankIssuer(${a.id}, this.value)" class="rounded-lg border-2 border-ink/15 bg-paper px-2 py-1 text-xs"><option value="primary" ${issuer === 'primary' ? 'selected' : ''}>SC1 (Principală)</option><option value="secondary" ${issuer === 'secondary' ? 'selected' : ''}>SC2 (Secundară)</option></select>
                ${!a.is_primary ? `<button onclick="setPrimaryAccount(${a.id})" class="rounded-full border-2 border-ink px-3 py-1 text-xs font-bold transition hover:bg-ink hover:text-paper">Setează principal</button>` : ''}
                <button onclick="deleteAccount(${a.id})" class="grid h-8 w-8 place-items-center rounded-lg text-ink-soft transition hover:text-vermilion" aria-label="Șterge"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
            </div></div>`;
    }).join('');
}
async function changeBankIssuer(id, issuing) {
    try { const r = await BileteOnlineAPI.put('/organizer/bank-accounts/' + id, { issuing_company: issuing }); if (r && r.success) { orgNotify('Emitent actualizat.', 'success'); loadBankAccounts(); } else orgNotify((r && r.message) || 'Eroare.', 'error'); }
    catch (e) { orgNotify('Eroare la actualizare emitent.', 'error'); }
}
function openBankModal() { const m = document.getElementById('bank-modal'); m.classList.remove('hidden'); m.classList.add('flex'); document.getElementById('bank-iban').value = ''; document.getElementById('iban-validation').classList.add('hidden'); }
function closeBankModal() { const m = document.getElementById('bank-modal'); m.classList.add('hidden'); m.classList.remove('flex'); }
async function addBankAccount(e) {
    e.preventDefault();
    const data = { bank: org('bank-name'), iban: org('bank-iban'), holder: org('bank-holder'), issuing_company: org('bank-issuing-company') || 'primary' };
    try { const r = await BileteOnlineAPI.post('/organizer/bank-accounts', data); if (r && r.success) { closeBankModal(); orgNotify('Contul a fost adăugat.', 'success'); loadBankAccounts(); } else orgNotify((r && r.message) || 'Eroare la adăugare.', 'error'); }
    catch (e) { orgNotify('Eroare la adăugare.', 'error'); }
}
async function setPrimaryAccount(id) {
    try { const r = await BileteOnlineAPI.post('/organizer/bank-accounts/' + id + '/primary', {}); if (r && r.success) { orgNotify('Contul principal a fost schimbat.', 'success'); loadBankAccounts(); } else orgNotify((r && r.message) || 'Eroare.', 'error'); }
    catch (e) { orgNotify('Eroare.', 'error'); }
}
async function deleteAccount(id) {
    if (!confirm('Ștergi acest cont?')) return;
    try { const r = await BileteOnlineAPI.delete('/organizer/bank-accounts/' + id); if (r && r.success) { orgNotify('Contul a fost șters.', 'success'); loadBankAccounts(); } else orgNotify((r && r.message) || 'Eroare la ștergere.', 'error'); }
    catch (e) { orgNotify('Eroare la ștergere.', 'error'); }
}

function validateIBAN(input) {
    const v = input.value.toUpperCase().replace(/\s/g, '');
    input.value = v;
    const el = document.getElementById('iban-validation');
    const setMsg = (txt, cls) => { el.textContent = txt; el.className = 'mt-1 text-xs ' + cls; el.classList.remove('hidden'); };
    if (!v) { el.classList.add('hidden'); return; }
    if (!v.startsWith('RO')) { setMsg('IBAN-ul românesc trebuie să înceapă cu RO.', 'text-vermilion'); return; }
    if (v.length < 24) { setMsg(`Mai sunt necesare ${24 - v.length} caractere (total: 24).`, 'text-ochre'); return; }
    if (v.length > 24) { setMsg('IBAN-ul are prea multe caractere (max 24).', 'text-vermilion'); return; }
    if (!/^RO[0-9]{2}[A-Z]{4}[A-Z0-9]{16}$/.test(v)) { setMsg('Format invalid. Structură: RO + 2 cifre + 4 litere bancă + 16 caractere cont.', 'text-vermilion'); return; }
    if (!validateIBANChecksum(v)) { setMsg('Cifrele de control sunt invalide.', 'text-vermilion'); return; }
    const banks = { BTRL: 'Banca Transilvania', BRDE: 'BRD', BACX: 'Unicredit', RNCB: 'BCR', INGB: 'ING Bank', RZBR: 'Raiffeisen', PIRB: 'First Bank', UGBI: 'Garanti BBVA', CECE: 'CEC Bank', NBOR: 'BNR', PORL: 'Banca Românească' };
    setMsg('✓ IBAN valid — ' + (banks[v.substring(4, 8)] || v.substring(4, 8)), 'text-forest');
}
function validateIBANChecksum(iban) {
    const r = iban.substring(4) + iban.substring(0, 4);
    let num = '';
    for (const ch of r) num += (ch >= 'A' && ch <= 'Z') ? (ch.charCodeAt(0) - 55).toString() : ch;
    let rem = 0n;
    for (let i = 0; i < num.length; i += 7) rem = BigInt(rem.toString() + num.substring(i, Math.min(i + 7, num.length))) % 97n;
    return rem === 1n;
}

async function saveNotifications(e) {
    e.preventDefault();
    const data = { sales: document.getElementById('notif-sales').checked, daily: document.getElementById('notif-daily').checked, stock: document.getElementById('notif-stock').checked };
    try { const r = await BileteOnlineAPI.put('/organizer/settings/notifications', data); ok(r, 'Preferințele au fost salvate.'); } catch (e) { orgNotify('Eroare la salvare.', 'error'); }
}
async function changePassword(e) {
    e.preventDefault();
    if (org('new-password') !== org('confirm-password')) { orgNotify('Parolele nu coincid.', 'error'); return; }
    const data = { current_password: org('current-password'), password: org('new-password'), password_confirmation: org('confirm-password') };
    try { const r = await BileteOnlineAPI.put('/organizer/settings/password', data); if (r && r.success) { orgNotify('Parola a fost schimbată.', 'success'); e.target.reset(); } else orgNotify((r && r.message) || 'Eroare la schimbare parolă.', 'error'); }
    catch (e) { orgNotify('Eroare la schimbare parolă.', 'error'); }
}

async function loadContract() {
    try {
        const r = await BileteOnlineAPI.get('/organizer/contract');
        if (!r || !r.success) return;
        const d = r.data || {};
        document.getElementById('contract-commission').textContent = (d.commission_rate || 0) + '%';
        const modeLabels = { included: 'Inclus în prețul biletului', on_top: 'Adăugat peste prețul biletului' };
        const modeDescs = { included: 'Comisionul este inclus în prețul afișat al biletului.', on_top: 'Comisionul se adaugă separat la prețul biletului.' };
        document.getElementById('contract-mode').textContent = modeLabels[d.commission_mode] || d.commission_mode || '-';
        document.getElementById('contract-mode-desc').textContent = modeDescs[d.commission_mode] || '';
        const wmLabels = { exclusive: 'Exclusiv', non_exclusive: 'Non-exclusiv' };
        const wmDescs = { exclusive: 'Vinzi bilete doar pe această platformă.', non_exclusive: 'Vinzi bilete și pe alte platforme.' };
        document.getElementById('contract-work-mode').textContent = wmLabels[d.work_mode] || d.work_mode || '-';
        document.getElementById('contract-work-mode-desc').textContent = wmDescs[d.work_mode] || '';
        if (d.terms && d.terms.length) {
            document.getElementById('contract-terms').innerHTML = d.terms.map(t => `<div class="flex items-start gap-3 text-sm text-ink-soft"><svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-forest" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><span>${slEscapeHtml(t)}</span></div>`).join('');
        }
        const dl = document.getElementById('download-contract-btn'), no = document.getElementById('no-contract-msg');
        if (d.has_contract) { dl.classList.remove('hidden'); no.classList.add('hidden'); } else { dl.classList.add('hidden'); no.classList.remove('hidden'); }
        if (d.documents) { if (d.documents.id_card) showUploadedDocument('id-card', 'Document încărcat'); if (d.documents.cui) showUploadedDocument('cui', 'Document încărcat'); }
    } catch (e) {}
}
function showUploadedDocument(type, filename) {
    const p = document.getElementById(type + '-preview'), ph = document.getElementById(type + '-placeholder'), fn = document.getElementById(type + '-filename');
    if (p && ph && fn) { fn.textContent = filename; p.classList.remove('hidden'); ph.classList.add('hidden'); }
}
async function downloadContract() {
    try { const r = await BileteOnlineAPI.get('/organizer/contract/download'); if (r && r.success && r.data && r.data.url) window.open(r.data.url, '_blank'); else orgNotify('Contractul nu este disponibil momentan. Contactează suportul.', 'info'); }
    catch (e) { orgNotify('Eroare la descărcare contract.', 'error'); }
}

// ===== Share links =====
let shareLinksData = [], organizerEventsForShare = [];
async function loadShareLinks() {
    try { const r = await BileteOnlineAPI.get('/organizer/share-links'); if (r && r.success) { shareLinksData = (r.data && r.data.links) || []; renderShareLinks(); } else document.getElementById('share-links-list').innerHTML = '<div class="py-8 text-center text-ink-soft">Eroare la încărcare.</div>'; }
    catch (e) { document.getElementById('share-links-list').innerHTML = '<div class="py-8 text-center text-ink-soft">Eroare la încărcare.</div>'; }
}
function renderShareLinks() {
    const c = document.getElementById('share-links-list');
    if (!shareLinksData.length) {
        c.innerHTML = `<div class="py-12 text-center"><svg class="mx-auto mb-4 h-16 w-16 text-ink/15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg><p class="text-ink-soft">Nu ai creat încă niciun link de monitorizare.</p><button onclick="openShareLinkModal()" class="mt-4 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Creează primul link</button></div>`;
        return;
    }
    c.innerHTML = shareLinksData.map(link => {
        const url = siteUrl() + '/view/' + link.code;
        const ec = (link.event_ids || []).length;
        const active = link.is_active !== false;
        const created = link.created_at ? new Date(link.created_at).toLocaleDateString('ro-RO') : '-';
        const access = link.access_count || 0;
        const ib = (fn, title, cls, path) => `<button onclick="${fn}" title="${title}" class="grid h-9 w-9 place-items-center rounded-lg text-ink-soft transition ${cls}"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">${path}</svg></button>`;
        return `<div class="mb-3 flex items-start gap-4 rounded-xl bg-paper-2 p-4 ${!active ? 'opacity-60' : ''}">
            <div class="min-w-0 flex-1">
                <div class="mb-1 flex items-center gap-2"><h3 class="truncate font-bold">${slEscapeHtml(link.name || 'Link')}</h3>${link.has_password ? '<span class="flex-shrink-0 rounded-full bg-ochre/15 px-2 py-0.5 text-xs text-ochre">Parolă</span>' : ''}${link.show_participants ? '<span class="flex-shrink-0 rounded-full bg-sky/15 px-2 py-0.5 text-xs text-sky">Participanți</span>' : ''}${active ? '<span class="flex-shrink-0 rounded-full bg-forest/15 px-2 py-0.5 text-xs text-forest">Activ</span>' : '<span class="flex-shrink-0 rounded-full bg-vermilion/15 px-2 py-0.5 text-xs text-vermilion">Inactiv</span>'}</div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-soft"><span>${ec} activitate${ec !== 1 ? '' : ''}</span><span>·</span><span>${access} accesări</span><span>·</span><span>Creat: ${created}</span></div>
                <div class="mt-2"><code class="block max-w-[300px] truncate rounded border-2 border-ink/10 bg-paper px-2 py-1 text-xs">${slEscapeHtml(url)}</code></div>
            </div>
            <div class="flex flex-shrink-0 items-center gap-1 pt-1">
                ${ib("copyShareLink('" + link.code + "')", 'Copiază link', 'hover:text-vermilion', '<path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>')}
                ${ib("window.open('" + siteUrl() + "/view/" + link.code + "','_blank')", 'Deschide', 'hover:text-vermilion', '<path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>')}
                ${ib("refreshShareLink('" + link.code + "')", 'Actualizează datele', 'hover:text-forest', '<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>')}
                ${ib("toggleShareLink('" + link.code + "'," + (active ? 'false' : 'true') + ")", active ? 'Dezactivează' : 'Activează', 'hover:text-ochre', active ? '<path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"/>' : '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>')}
                ${ib("deleteShareLink('" + link.code + "')", 'Șterge', 'hover:text-vermilion', '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>')}
            </div>
        </div>`;
    }).join('');
}
async function openShareLinkModal() {
    const m = document.getElementById('share-link-modal'); m.classList.remove('hidden'); m.classList.add('flex');
    document.getElementById('share-link-name').value = ''; document.getElementById('share-link-password').value = '';
    document.getElementById('share-link-show-participants').checked = false; document.getElementById('share-link-show-revenue').checked = false;
    document.getElementById('create-share-btn').disabled = true;
    document.getElementById('share-events-loading').classList.remove('hidden');
    document.getElementById('share-events-list').classList.add('hidden');
    document.getElementById('share-events-empty').classList.add('hidden');
    try {
        const r = await BileteOnlineAPI.get('/organizer/events', { per_page: 50 });
        const all = (r && r.success && (r.data.events || (Array.isArray(r.data) ? r.data : []))) || [];
        organizerEventsForShare = (Array.isArray(all) ? all : []).filter(ev => {
            const end = ev.ends_at || ev.starts_at || ev.start_date;
            const ended = ev.status === 'ended' || ev.is_past || ev.is_ended || (end && new Date(end) < new Date());
            return !ended && !ev.is_cancelled && ev.status !== 'cancelled' && !ev.is_postponed && ev.status !== 'postponed';
        });
        if (organizerEventsForShare.length) renderEventCheckboxes(organizerEventsForShare);
        else { document.getElementById('share-events-loading').classList.add('hidden'); document.getElementById('share-events-empty').classList.remove('hidden'); }
    } catch (e) { document.getElementById('share-events-loading').classList.add('hidden'); document.getElementById('share-events-empty').classList.remove('hidden'); document.getElementById('share-events-empty').textContent = 'Eroare la încărcarea activităților.'; }
}
function renderEventCheckboxes(events) {
    const fmt = (raw) => { if (!raw) return ''; const d = new Date(raw); return isNaN(d.getTime()) ? '' : d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' }); };
    document.getElementById('share-events-list').innerHTML = events.map(ev => {
        const title = ev.title || ev.name || 'Activitate';
        const meta = [fmt(ev.starts_at || ev.start_date || ev.event_date || ev.date), ev.status || ''].filter(Boolean).join(' · ');
        return `<label class="flex cursor-pointer items-center gap-3 p-3 transition hover:bg-paper"><input type="checkbox" value="${ev.id}" class="share-event-checkbox rounded text-vermilion" onchange="updateShareBtnState()"><div class="min-w-0 flex-1"><p class="truncate text-sm font-bold">${slEscapeHtml(title)}</p>${meta ? `<p class="mt-0.5 text-xs text-ink-soft">${slEscapeHtml(meta)}</p>` : ''}</div></label>`;
    }).join('');
    document.getElementById('share-events-loading').classList.add('hidden');
    document.getElementById('share-events-list').classList.remove('hidden');
}
function updateShareBtnState() { document.getElementById('create-share-btn').disabled = document.querySelectorAll('.share-event-checkbox:checked').length === 0; }
function closeShareLinkModal() { const m = document.getElementById('share-link-modal'); m.classList.add('hidden'); m.classList.remove('flex'); }
async function createShareLink(e) {
    e.preventDefault();
    const checked = document.querySelectorAll('.share-event-checkbox:checked');
    if (!checked.length) { orgNotify('Selectează cel puțin o activitate.', 'error'); return; }
    const payload = { event_ids: Array.from(checked).map(c => parseInt(c.value)), name: document.getElementById('share-link-name').value.trim() };
    const pwd = document.getElementById('share-link-password').value.trim();
    if (pwd) payload.password = pwd;
    if (document.getElementById('share-link-show-participants').checked) payload.show_participants = true;
    if (document.getElementById('share-link-show-revenue').checked) payload.show_revenue = true;
    try {
        const r = await BileteOnlineAPI.post('/organizer/share-links', payload);
        if (r && r.success) {
            closeShareLinkModal(); orgNotify('Link creat cu succes!', 'success');
            if (r.url) { try { await navigator.clipboard.writeText(r.url); orgNotify('Link-ul a fost copiat în clipboard.', 'info'); } catch (e) {} }
            loadShareLinks();
        } else orgNotify((r && (r.message || r.error)) || 'Eroare la creare.', 'error');
    } catch (e) { orgNotify((e && e.message) || 'Eroare la creare.', 'error'); }
}
async function copyShareLink(code) {
    const url = siteUrl() + '/view/' + code;
    try { await navigator.clipboard.writeText(url); orgNotify('Link copiat în clipboard!', 'success'); }
    catch (e) { const i = document.createElement('input'); i.value = url; i.style.position = 'fixed'; i.style.opacity = '0'; document.body.appendChild(i); i.select(); document.execCommand('copy'); document.body.removeChild(i); orgNotify('Link copiat!', 'success'); }
}
async function toggleShareLink(code, active) {
    try { const r = await BileteOnlineAPI.put('/organizer/share-links/' + code, { is_active: active }); if (r && r.success) { orgNotify(active ? 'Link activat.' : 'Link dezactivat.', 'success'); loadShareLinks(); } else orgNotify((r && (r.message || r.error)) || 'Eroare.', 'error'); }
    catch (e) { orgNotify('Eroare.', 'error'); }
}
async function deleteShareLink(code) {
    if (!confirm('Ești sigur că vrei să ștergi acest link?')) return;
    try { const r = await BileteOnlineAPI.delete('/organizer/share-links/' + code); if (r && r.success) { orgNotify('Link șters.', 'success'); loadShareLinks(); } else orgNotify((r && (r.message || r.error)) || 'Eroare la ștergere.', 'error'); }
    catch (e) { orgNotify('Eroare la ștergere.', 'error'); }
}
async function refreshShareLink(code) {
    try { const r = await BileteOnlineAPI.put('/organizer/share-links/' + code, { refresh_data: true }); if (r && r.success) { orgNotify('Datele au fost actualizate.', 'success'); loadShareLinks(); } else orgNotify((r && (r.message || r.error)) || 'Eroare.', 'error'); }
    catch (e) { orgNotify('Eroare la actualizare.', 'error'); }
}

// ===== Document upload =====
function handleDragOver(e, type) { e.preventDefault(); e.stopPropagation(); document.getElementById(type + '-dropzone').classList.add('border-vermilion', 'bg-vermilion/5'); }
function handleDragLeave(e, type) { e.preventDefault(); e.stopPropagation(); document.getElementById(type + '-dropzone').classList.remove('border-vermilion', 'bg-vermilion/5'); }
function handleDrop(e, docType) { e.preventDefault(); e.stopPropagation(); const type = docType === 'id_card' ? 'id-card' : 'cui'; document.getElementById(type + '-dropzone').classList.remove('border-vermilion', 'bg-vermilion/5'); if (e.dataTransfer.files.length) processFile(e.dataTransfer.files[0], docType); }
function handleFileSelect(e, docType) { if (e.target.files.length) processFile(e.target.files[0], docType); }
function processFile(file, docType) {
    const type = docType === 'id_card' ? 'id-card' : 'cui';
    const status = document.getElementById(type + '-status');
    const set = (txt, cls) => { status.textContent = txt; status.className = 'mt-2 text-xs ' + cls; status.classList.remove('hidden'); };
    if (!['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) { set('Tip de fișier invalid. Acceptăm doar PDF, JPG sau PNG.', 'text-vermilion'); return; }
    if (file.size > 5 * 1024 * 1024) { set('Fișierul este prea mare. Dimensiunea maximă este 5MB.', 'text-vermilion'); return; }
    set('Se încarcă…', 'text-sky');
    uploadDocument(file, docType).then(r => {
        if (r && r.success) { document.getElementById(type + '-filename').textContent = file.name; document.getElementById(type + '-preview').classList.remove('hidden'); document.getElementById(type + '-placeholder').classList.add('hidden'); set('Documentul a fost încărcat cu succes!', 'text-forest'); }
        else set((r && r.message) || 'Eroare la încărcare.', 'text-vermilion');
    }).catch(() => set('Eroare la încărcarea fișierului.', 'text-vermilion'));
}
async function uploadDocument(file, docType) {
    const fd = new FormData(); fd.append('file', file); fd.append('type', docType);
    try {
        const token = (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken) ? BileteOnlineAuth.getToken() : '';
        const resp = await fetch(siteUrl() + '/api/proxy.php?action=organizer.documents.upload', { method: 'POST', headers: token ? { 'Authorization': 'Bearer ' + token } : {}, body: fd });
        return await resp.json();
    } catch (e) { return { success: false, message: 'Eroare de rețea' }; }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
