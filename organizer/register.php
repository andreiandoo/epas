<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Inregistrare Organizator';
$bodyClass = 'min-h-screen bg-surface';
require_once dirname(__DIR__) . '/includes/head.php';
?>
    <header class="bg-white border-b border-border">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg></div>
                <span class="text-xl font-extrabold text-secondary"><?= strtoupper(SITE_NAME) ?></span>
            </a>
            <a href="/organizator/login" class="text-sm text-muted hover:text-primary">Am deja cont &rarr;</a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-12">
        <!-- Step Indicators -->
        <div class="flex items-center justify-center mb-12 overflow-x-auto pb-2" id="step-indicators">
            <div class="flex items-center"><div id="step1-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-primary text-white font-bold text-sm">1</div><span class="ml-2 text-sm font-medium text-secondary hidden sm:block">Cont</span></div>
            <div class="w-8 sm:w-12 h-0.5 bg-border ml-2" id="step1-line"></div>
            <div class="flex items-center"><div id="step2-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-border text-muted font-bold text-sm">2</div><span class="ml-2 text-sm text-muted hidden sm:block">Tip</span></div>
            <div class="w-8 sm:w-12 h-0.5 bg-border ml-2" id="step2-line"></div>
            <div class="flex items-center" id="step3-container"><div id="step3-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-border text-muted font-bold text-sm">3</div><span class="ml-2 text-sm text-muted hidden sm:block">Companie</span></div>
            <div class="w-8 sm:w-12 h-0.5 bg-border ml-2" id="step3-line"></div>
            <div class="flex items-center"><div id="step4-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-border text-muted font-bold text-sm">4</div><span class="ml-2 text-sm text-muted hidden sm:block">Garant</span></div>
            <div class="w-8 sm:w-12 h-0.5 bg-border ml-2" id="step4-line"></div>
            <div class="flex items-center"><div id="step5-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-border text-muted font-bold text-sm">5</div><span class="ml-2 text-sm text-muted hidden sm:block">Plata</span></div>
            <div class="w-8 sm:w-12 h-0.5 bg-border ml-2" id="step5-line"></div>
            <div class="flex items-center"><div id="step6-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-border text-muted font-bold text-sm">6</div><span class="ml-2 text-sm text-muted hidden sm:block">Final</span></div>
        </div>

        <div id="error-message" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-error"></div>

        <div class="max-w-2xl mx-auto">
            <!-- Step 1: Account Info -->
            <div id="step1" class="step-content">
                <div class="bg-white rounded-2xl border border-border p-8">
                    <h2 class="text-2xl font-bold text-secondary mb-2">Creeaza contul tau</h2>
                    <p class="text-muted mb-6">Informatii pentru autentificare in platforma</p>
                    <form id="step1-form" class="space-y-4">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div><label class="label">Prenume *</label><input type="text" name="first_name" required class="input" placeholder="Ion"></div>
                            <div><label class="label">Nume *</label><input type="text" name="last_name" required class="input" placeholder="Popescu"></div>
                        </div>
                        <div><label class="label">Email *</label><input type="email" name="email" required class="input" placeholder="organizator@email.com"></div>
                        <div>
                            <label class="label">Telefon *</label>
                            <div class="flex gap-2">
                                <select id="phone-country" class="input w-auto min-w-[140px]" onchange="updatePhonePrefix()">
                                    <option value="RO" data-prefix="+40" selected>🇷🇴 Romania (+40)</option>
                                    <option value="MD" data-prefix="+373">🇲🇩 Moldova (+373)</option>
                                    <option value="HU" data-prefix="+36">🇭🇺 Ungaria (+36)</option>
                                    <option value="BG" data-prefix="+359">🇧🇬 Bulgaria (+359)</option>
                                    <option value="UA" data-prefix="+380">🇺🇦 Ucraina (+380)</option>
                                    <option value="RS" data-prefix="+381">🇷🇸 Serbia (+381)</option>
                                    <option value="DE" data-prefix="+49">🇩🇪 Germania (+49)</option>
                                    <option value="AT" data-prefix="+43">🇦🇹 Austria (+43)</option>
                                    <option value="IT" data-prefix="+39">🇮🇹 Italia (+39)</option>
                                    <option value="ES" data-prefix="+34">🇪🇸 Spania (+34)</option>
                                    <option value="FR" data-prefix="+33">🇫🇷 Franta (+33)</option>
                                    <option value="GB" data-prefix="+44">🇬🇧 Marea Britanie (+44)</option>
                                    <option value="US" data-prefix="+1">🇺🇸 SUA (+1)</option>
                                </select>
                                <div class="flex-1 relative">
                                    <span id="phone-prefix" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted font-medium">+40</span>
                                    <input type="tel" name="phone" id="phone-input" required class="input pl-12" placeholder="7XX XXX XXX">
                                </div>
                            </div>
                            <input type="hidden" name="phone_country" id="phone-country-code" value="RO">
                        </div>
                        <div><label class="label">Parola *</label><input type="password" name="password" required class="input" placeholder="Minim 8 caractere" minlength="8"></div>
                        <div><label class="label">Confirma parola *</label><input type="password" name="password_confirmation" required class="input" placeholder="Repeta parola"></div>
                        <button type="submit" class="btn btn-primary w-full mt-6">Continua <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></button>
                    </form>
                </div>
            </div>

            <!-- Step 2: Organizer Type -->
            <div id="step2" class="step-content hidden">
                <div class="bg-white rounded-2xl border border-border p-8">
                    <h2 class="text-2xl font-bold text-secondary mb-2">Tip Organizator</h2>
                    <p class="text-muted mb-6">Selecteaza tipul de organizator si modul de lucru</p>
                    <form id="step2-form" class="space-y-6">
                        <!-- Person Type Selection -->
                        <div>
                            <label class="label mb-3">Tip persoana *</label>
                            <div class="grid md:grid-cols-2 gap-4">
                                <label class="relative flex items-center p-4 border-2 border-border rounded-xl cursor-pointer hover:border-primary/50 transition has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                    <input type="radio" name="person_type" value="pj" class="sr-only" required>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-primary/10 has-[:checked]:bg-primary flex items-center justify-center">
                                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-secondary">Persoana Juridica</p>
                                            <p class="text-sm text-muted">SRL, SA, PFA, II, etc.</p>
                                        </div>
                                    </div>
                                    <div class="absolute top-3 right-3 w-5 h-5 rounded-full border-2 border-border has-[:checked]:border-primary has-[:checked]:bg-primary flex items-center justify-center">
                                        <svg class="w-3 h-3 text-white hidden" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    </div>
                                </label>
                                <label class="relative flex items-center p-4 border-2 border-border rounded-xl cursor-pointer hover:border-primary/50 transition has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                    <input type="radio" name="person_type" value="pf" class="sr-only">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-secondary">Persoana Fizica</p>
                                            <p class="text-sm text-muted">Fara forma juridica</p>
                                        </div>
                                    </div>
                                    <div class="absolute top-3 right-3 w-5 h-5 rounded-full border-2 border-border flex items-center justify-center"></div>
                                </label>
                            </div>
                        </div>

                        <!-- Work Mode Selection -->
                        <div>
                            <label class="label mb-3">Mod de lucru *</label>
                            <div class="grid md:grid-cols-2 gap-4">
                                <label class="relative flex items-start p-4 border-2 border-border rounded-xl cursor-pointer hover:border-primary/50 transition has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                    <input type="radio" name="work_mode" value="exclusive" class="sr-only" required>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <p class="font-semibold text-secondary">Lucru Exclusiv</p>
                                            <span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded-full">Recomandat</span>
                                        </div>
                                        <p class="text-sm text-muted">Vinzi bilete doar prin <?= SITE_NAME ?>. Beneficiezi de comision redus si invitatii gratuite.</p>
                                    </div>
                                    <div class="absolute top-3 right-3 w-5 h-5 rounded-full border-2 border-border flex items-center justify-center"></div>
                                </label>
                                <label class="relative flex items-start p-4 border-2 border-border rounded-xl cursor-pointer hover:border-primary/50 transition has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                    <input type="radio" name="work_mode" value="non_exclusive" class="sr-only">
                                    <div class="flex-1">
                                        <p class="font-semibold text-secondary">Lucru Neexclusiv</p>
                                        <p class="text-sm text-muted">Vinzi bilete si prin alte platforme sau canale proprii.</p>
                                    </div>
                                    <div class="absolute top-3 right-3 w-5 h-5 rounded-full border-2 border-border flex items-center justify-center"></div>
                                </label>
                            </div>
                        </div>

                        <div class="flex gap-4 mt-6">
                            <button type="button" onclick="goToStep(1)" class="btn btn-secondary flex-1"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>Inapoi</button>
                            <button type="submit" class="btn btn-primary flex-1">Continua <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Step 3: Company Details (only for PJ) -->
            <div id="step3" class="step-content hidden">
                <div class="bg-white rounded-2xl border border-border p-8">
                    <h2 class="text-2xl font-bold text-secondary mb-2">Detalii Companie</h2>
                    <p class="text-muted mb-6">Verifica datele companiei tale la ANAF</p>
                    <form id="step3-form" class="space-y-6">
                        <!-- CUI Verification -->
                        <div>
                            <label class="label">CUI / CIF *</label>
                            <div class="flex gap-2">
                                <input type="text" name="cui" id="cui-input" class="input flex-1" placeholder="12345678 sau RO12345678" required>
                                <button type="button" onclick="verifyCUI()" class="btn btn-secondary whitespace-nowrap" id="verify-cui-btn">
                                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Verificare ANAF
                                </button>
                            </div>
                            <p class="text-xs text-muted mt-1">Introdu CUI-ul si apasa verificare pentru a prelua datele automat</p>
                        </div>

                        <!-- Company Details (hidden until ANAF verification) -->
                        <div id="company-details" class="hidden space-y-4">
                            <div class="p-4 bg-green-50 border border-green-200 rounded-xl">
                                <div class="flex items-center gap-2 text-green-700 mb-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="font-medium">Date verificate la ANAF</span>
                                </div>
                            </div>

                            <div>
                                <label class="label">Denumire companie</label>
                                <input type="text" name="company_name" id="company-name" class="input" readonly>
                            </div>

                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Nr. Reg. Comertului</label>
                                    <input type="text" name="reg_com" id="company-reg" class="input" readonly>
                                </div>
                                <div>
                                    <label class="label flex items-center gap-2">
                                        Platitor TVA
                                        <span id="vat-badge" class="hidden px-2 py-0.5 text-xs rounded-full"></span>
                                    </label>
                                    <input type="hidden" name="vat_payer" id="company-vat" value="0">
                                    <div id="vat-display" class="input bg-gray-50 cursor-not-allowed">-</div>
                                </div>
                            </div>

                            <div>
                                <label class="label">Adresa sediului</label>
                                <input type="text" name="company_address" id="company-address" class="input" readonly>
                            </div>

                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Oras</label>
                                    <input type="text" name="company_city" id="company-city" class="input" readonly>
                                </div>
                                <div>
                                    <label class="label">Judet</label>
                                    <input type="text" name="company_county" id="company-county" class="input" readonly>
                                </div>
                            </div>

                            <!-- Organizer Type -->
                            <div>
                                <label class="label">Tip organizator *</label>
                                <select name="organizer_type" id="organizer-type-pj" class="input" required>
                                    <option value="">Selecteaza...</option>
                                    <option value="agency">Agentie de evenimente</option>
                                    <option value="promoter">Promoter independent</option>
                                    <option value="venue">Locatie / Sala</option>
                                    <option value="artist">Artist / Manager</option>
                                    <option value="ngo">ONG / Fundatie</option>
                                    <option value="other">Altele</option>
                                </select>
                            </div>

                            <!-- Company Representative -->
                            <div class="pt-4 border-t border-border">
                                <h3 class="text-lg font-semibold text-secondary mb-4">Reprezentant Legal</h3>
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label">Prenume reprezentant *</label>
                                        <input type="text" name="representative_first_name" id="rep-first-name" class="input" required placeholder="Ion">
                                    </div>
                                    <div>
                                        <label class="label">Nume reprezentant *</label>
                                        <input type="text" name="representative_last_name" id="rep-last-name" class="input" required placeholder="Popescu">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-4 mt-6">
                            <button type="button" onclick="goToStep(2)" class="btn btn-secondary flex-1"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>Inapoi</button>
                            <button type="submit" class="btn btn-primary flex-1" id="step3-submit" disabled>Continua <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Step 4: Guarantor / Personal Details -->
            <div id="step4" class="step-content hidden">
                <div class="bg-white rounded-2xl border border-border p-8">
                    <h2 class="text-2xl font-bold text-secondary mb-2" id="step4-title">Date Personale</h2>
                    <p class="text-muted mb-6" id="step4-subtitle">Informatii pentru identificare si contract</p>
                    <form id="step4-form" class="space-y-4">
                        <!-- Organizer Type (only for PF) -->
                        <div id="organizer-type-pf-container" class="hidden">
                            <label class="label">Tip organizator *</label>
                            <select name="organizer_type_pf" id="organizer-type-pf" class="input">
                                <option value="">Selecteaza...</option>
                                <option value="agency">Agentie de evenimente</option>
                                <option value="promoter">Promoter independent</option>
                                <option value="venue">Locatie / Sala</option>
                                <option value="artist">Artist / Manager</option>
                                <option value="ngo">ONG / Fundatie</option>
                                <option value="other">Altele</option>
                            </select>
                        </div>

                        <!-- Personal Name -->
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="label">Prenume *</label>
                                <input type="text" name="guarantor_first_name" id="guarantor-first-name" required class="input" placeholder="Ion">
                            </div>
                            <div>
                                <label class="label">Nume *</label>
                                <input type="text" name="guarantor_last_name" id="guarantor-last-name" required class="input" placeholder="Popescu">
                            </div>
                        </div>

                        <!-- CNP -->
                        <div>
                            <label class="label">CNP *</label>
                            <input type="text" name="cnp" id="guarantor-cnp" required class="input" placeholder="1234567890123" maxlength="13" pattern="[0-9]{13}">
                            <p class="text-xs text-muted mt-1">Codul Numeric Personal - 13 cifre</p>
                        </div>

                        <!-- Address -->
                        <div>
                            <label class="label">Adresa de domiciliu *</label>
                            <input type="text" name="guarantor_address" id="guarantor-address" required class="input" placeholder="Str. Exemplu, Nr. 1, Bl. A, Ap. 10">
                        </div>

                        <div>
                            <label class="label">Localitate *</label>
                            <input type="text" name="guarantor_city" id="guarantor-city" required class="input" placeholder="Bucuresti">
                        </div>

                        <!-- ID Card Details -->
                        <div class="pt-4 border-t border-border">
                            <h3 class="text-lg font-semibold text-secondary mb-4">Act de Identitate</h3>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Tip act *</label>
                                    <select name="id_type" id="guarantor-id-type" required class="input">
                                        <option value="ci">Carte de Identitate (CI)</option>
                                        <option value="bi">Buletin de Identitate (BI)</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="label">Serie *</label>
                                        <input type="text" name="id_series" id="guarantor-id-series" required class="input" placeholder="RX" maxlength="2" style="text-transform: uppercase;">
                                    </div>
                                    <div>
                                        <label class="label">Numar *</label>
                                        <input type="text" name="id_number" id="guarantor-id-number" required class="input" placeholder="123456" maxlength="6">
                                    </div>
                                </div>
                            </div>
                            <div class="grid md:grid-cols-2 gap-4 mt-4">
                                <div>
                                    <label class="label">Eliberat de *</label>
                                    <input type="text" name="id_issued_by" id="guarantor-id-issued-by" required class="input" placeholder="SPCLEP Sector 1">
                                </div>
                                <div>
                                    <label class="label">La data de *</label>
                                    <input type="date" name="id_issued_date" id="guarantor-id-issued-date" required class="input">
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-4 mt-6">
                            <button type="button" onclick="goToPreviousStep()" class="btn btn-secondary flex-1"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>Inapoi</button>
                            <button type="submit" class="btn btn-primary flex-1">Continua <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Step 5: Payment / Bank Details -->
            <div id="step5" class="step-content hidden">
                <div class="bg-white rounded-2xl border border-border p-8">
                    <h2 class="text-2xl font-bold text-secondary mb-2">Detalii Bancare</h2>
                    <p class="text-muted mb-6">Contul in care vei primi platile pentru biletele vandute</p>
                    <form id="step5-form" class="space-y-4">
                        <div>
                            <label class="label">IBAN *</label>
                            <input type="text" name="iban" id="bank-iban" required class="input" placeholder="RO49AAAA1B31007593840000" maxlength="24" oninput="validateIBAN(this)">
                            <div id="iban-validation" class="mt-1 text-xs hidden"></div>
                        </div>

                        <div>
                            <label class="label">Titular cont *</label>
                            <input type="text" name="account_holder" id="bank-holder" required class="input" placeholder="SC Exemplu SRL sau Popescu Ion">
                            <p class="text-xs text-muted mt-1">Numele exact al titularului contului bancar</p>
                        </div>

                        <div>
                            <label class="label">Banca</label>
                            <input type="text" name="bank_name" id="bank-name" class="input" placeholder="Se completeaza automat" readonly>
                        </div>

                        <div class="p-4 bg-accent/10 rounded-xl">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-accent flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="font-medium text-secondary">Cand primesc banii?</p>
                                    <p class="text-sm text-muted">Platile sunt procesate in maxim 5 zile lucratoare dupa eveniment. Pentru evenimente cu valoare mare, poti solicita plati partiale in avans.</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-4 mt-6">
                            <button type="button" onclick="goToStep(4)" class="btn btn-secondary flex-1"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>Inapoi</button>
                            <button type="submit" class="btn btn-primary flex-1" id="step5-submit">Continua <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Step 6: Finalization -->
            <div id="step6" class="step-content hidden">
                <div class="bg-white rounded-2xl border border-border p-8">
                    <h2 class="text-2xl font-bold text-secondary mb-2">Finalizare Inregistrare</h2>
                    <p class="text-muted mb-6">Verifica datele si confirma inregistrarea</p>

                    <!-- Summary Sections -->
                    <div class="space-y-4 mb-6">
                        <!-- Account Summary -->
                        <div class="p-4 bg-surface rounded-xl">
                            <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Informatii Cont
                            </h4>
                            <div class="text-sm text-muted space-y-1" id="summary-account"></div>
                        </div>

                        <!-- Organizer Type Summary -->
                        <div class="p-4 bg-surface rounded-xl">
                            <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Tip Organizator
                            </h4>
                            <div class="text-sm text-muted space-y-1" id="summary-type"></div>
                        </div>

                        <!-- Company Summary (only for PJ) -->
                        <div class="p-4 bg-surface rounded-xl" id="summary-company-section">
                            <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                Informatii Companie
                            </h4>
                            <div class="text-sm text-muted space-y-1" id="summary-company"></div>
                        </div>

                        <!-- Guarantor Summary -->
                        <div class="p-4 bg-surface rounded-xl">
                            <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                                <span id="summary-guarantor-title">Date Personale / Garant</span>
                            </h4>
                            <div class="text-sm text-muted space-y-1" id="summary-guarantor"></div>
                        </div>

                        <!-- Bank Summary -->
                        <div class="p-4 bg-surface rounded-xl">
                            <h4 class="font-medium text-secondary mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                Detalii Bancare
                            </h4>
                            <div class="text-sm text-muted space-y-1" id="summary-bank"></div>
                        </div>
                    </div>

                    <!-- Commission Info -->
                    <div class="space-y-3 mb-6 p-4 bg-primary/5 border border-primary/20 rounded-xl">
                        <h4 class="font-semibold text-secondary">Informatii Tarifare</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-primary flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span><strong>Comision:</strong> maxim 6% din valoarea biletului, dar nu mai putin de 2.50 lei per bilet emis</span>
                            </div>
                            <div class="flex items-start gap-2" id="invitation-cost-info">
                                <svg class="w-4 h-4 text-primary flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span><strong>Cost invitatie:</strong> <span id="invitation-cost-text">0.25 lei per bucata</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Contract Info -->
                    <div class="p-4 bg-accent/10 border border-accent/20 rounded-xl mb-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-accent flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <div>
                                <p class="font-medium text-secondary">Contract Electronic</p>
                                <p class="text-sm text-muted">Prin finalizarea inregistrarii, iti vom genera un contract electronic personalizat. Contractul va trebui semnat (electronic sau olograf) si incarcat in platforma pentru validarea contului.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="space-y-3 mb-6">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="terms" required class="mt-1 w-4 h-4 rounded border-border text-primary focus:ring-primary">
                            <span class="text-sm text-muted">Accept <a href="/terms.php" class="text-primary hover:underline" target="_blank">Termenii si Conditiile</a> pentru organizatori si <a href="/privacy.php" class="text-primary hover:underline" target="_blank">Politica de Confidentialitate</a> *</span>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="contract" required class="mt-1 w-4 h-4 rounded border-border text-primary focus:ring-primary">
                            <span class="text-sm text-muted">Sunt de acord cu generarea <a href="/contract.php" class="text-primary hover:underline" target="_blank">Contractului de prestari servicii</a> <?= SITE_NAME ?> *</span>
                        </label>
                    </div>

                    <div class="flex gap-4">
                        <button type="button" onclick="goToStep(5)" class="btn btn-secondary flex-1"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>Inapoi</button>
                        <button type="button" onclick="submitRegistration()" class="btn btn-primary flex-1" id="final-submit">
                            <span id="final-btn-text">Finalizeaza Inregistrarea</span>
                            <div id="final-btn-spinner" class="hidden spinner"></div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
<?php
$scriptsExtra = <<<'JS'
<script>
let currentStep = 1;
let formData = {
    person_type: null,
    work_mode: null,
    anaf_verified: false
};
const totalSteps = 6;

// Bank code mappings
const bankNames = {
    'INGB': 'ING Bank',
    'BTRL': 'Banca Transilvania',
    'BRDE': 'BRD - Groupe Societe Generale',
    'RNCB': 'BCR',
    'RZBR': 'Raiffeisen Bank',
    'BACX': 'UniCredit Bank',
    'CECE': 'CEC Bank',
    'BPOS': 'Banca Romaneasca',
    'PIRB': 'First Bank (Piraeus)',
    'OTPV': 'OTP Bank',
    'WBAN': 'Intesa Sanpaolo',
    'PORL': 'Banca Romaneasca',
    'UGBI': 'Garanti BBVA',
    'EXIM': 'Eximbank',
    'CRCO': 'Credit Europe Bank',
    'VBBU': 'Vista Bank'
};

// Organizer type labels
const organizerTypeLabels = {
    'agency': 'Agentie de evenimente',
    'promoter': 'Promoter independent',
    'venue': 'Locatie / Sala',
    'artist': 'Artist / Manager',
    'ngo': 'ONG / Fundatie',
    'other': 'Altele'
};

function goToStep(step) {
    // Hide all steps
    document.querySelectorAll('.step-content').forEach(el => el.classList.add('hidden'));

    // Show target step
    const targetStep = document.getElementById(`step${step}`);
    if (targetStep) {
        targetStep.classList.remove('hidden');
    }

    // Update step indicators
    for (let i = 1; i <= totalSteps; i++) {
        const indicator = document.getElementById(`step${i}-indicator`);
        const line = document.getElementById(`step${i}-line`);
        const container = document.getElementById(`step${i}-container`);

        if (!indicator) continue;

        // Skip step 3 for PF
        if (i === 3 && formData.person_type === 'pf') {
            if (container) container.classList.add('hidden');
            if (line) line.classList.add('hidden');
            continue;
        } else if (i === 3) {
            if (container) container.classList.remove('hidden');
            if (line) line.classList.remove('hidden');
        }

        if (i < step) {
            indicator.className = 'flex items-center justify-center w-10 h-10 rounded-full bg-success text-white font-bold text-sm';
            indicator.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
            if (line) line.className = 'w-8 sm:w-12 h-0.5 bg-success ml-2';
        } else if (i === step) {
            indicator.className = 'flex items-center justify-center w-10 h-10 rounded-full bg-primary text-white font-bold text-sm';
            indicator.textContent = i;
        } else {
            indicator.className = 'flex items-center justify-center w-10 h-10 rounded-full bg-border text-muted font-bold text-sm';
            indicator.textContent = i;
            if (line) line.className = 'w-8 sm:w-12 h-0.5 bg-border ml-2';
        }
    }

    currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToPreviousStep() {
    if (formData.person_type === 'pf' && currentStep === 4) {
        goToStep(2);
    } else {
        goToStep(currentStep - 1);
    }
}

// Phone country/prefix handling
function updatePhonePrefix() {
    const select = document.getElementById('phone-country');
    const prefixSpan = document.getElementById('phone-prefix');
    const countryInput = document.getElementById('phone-country-code');
    const phoneInput = document.getElementById('phone-input');

    const selectedOption = select.options[select.selectedIndex];
    const prefix = selectedOption.getAttribute('data-prefix');
    const countryCode = select.value;

    prefixSpan.textContent = prefix;
    countryInput.value = countryCode;

    // Adjust padding based on prefix length
    const prefixLength = prefix.length;
    if (prefixLength <= 3) {
        phoneInput.style.paddingLeft = '2.5rem'; // +40
    } else if (prefixLength <= 4) {
        phoneInput.style.paddingLeft = '3rem'; // +373
    } else {
        phoneInput.style.paddingLeft = '3.5rem'; // +380 etc
    }
}

function getFullPhoneNumber() {
    const prefix = document.getElementById('phone-prefix').textContent;
    const phone = document.getElementById('phone-input').value.replace(/\s/g, '');
    return prefix + ' ' + phone;
}

// Step 1: Account Form
document.getElementById('step1-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;

    if (form.password.value !== form.password_confirmation.value) {
        showError('Parolele nu coincid.');
        return;
    }

    if (form.password.value.length < 8) {
        showError('Parola trebuie sa aiba minim 8 caractere.');
        return;
    }

    hideError();
    formData.first_name = form.first_name.value;
    formData.last_name = form.last_name.value;
    formData.email = form.email.value;
    formData.phone = getFullPhoneNumber();
    formData.phone_country = document.getElementById('phone-country-code').value;
    formData.password = form.password.value;
    formData.password_confirmation = form.password_confirmation.value;
    goToStep(2);
});

// Step 2: Organizer Type Form
document.getElementById('step2-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;

    const personType = form.person_type.value;
    const workMode = form.work_mode.value;

    if (!personType) {
        showError('Selecteaza tipul de persoana.');
        return;
    }

    if (!workMode) {
        showError('Selecteaza modul de lucru.');
        return;
    }

    hideError();
    formData.person_type = personType;
    formData.work_mode = workMode;

    // Update step 4 based on person type
    updateStep4ForPersonType(personType);

    // If PF, skip to step 4 (Garant)
    if (personType === 'pf') {
        goToStep(4);
    } else {
        goToStep(3);
    }
});

// Step 3: Company Form
document.getElementById('step3-form').addEventListener('submit', (e) => {
    e.preventDefault();

    if (!formData.anaf_verified) {
        showError('Trebuie sa verifici CUI-ul la ANAF.');
        return;
    }

    const form = e.target;
    const organizerType = document.getElementById('organizer-type-pj').value;
    const repFirstName = document.getElementById('rep-first-name').value;
    const repLastName = document.getElementById('rep-last-name').value;

    if (!organizerType) {
        showError('Selecteaza tipul de organizator.');
        return;
    }

    if (!repFirstName || !repLastName) {
        showError('Completeaza datele reprezentantului legal.');
        return;
    }

    hideError();
    formData.organizer_type = organizerType;
    formData.representative_first_name = repFirstName;
    formData.representative_last_name = repLastName;

    goToStep(4);
});

// Step 4: Guarantor Form
document.getElementById('step4-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;

    // Get organizer type for PF
    if (formData.person_type === 'pf') {
        const organizerType = document.getElementById('organizer-type-pf').value;
        if (!organizerType) {
            showError('Selecteaza tipul de organizator.');
            return;
        }
        formData.organizer_type = organizerType;
    }

    // Validate CNP
    const cnp = document.getElementById('guarantor-cnp').value;
    if (!/^[0-9]{13}$/.test(cnp)) {
        showError('CNP-ul trebuie sa aiba exact 13 cifre.');
        return;
    }

    hideError();
    formData.guarantor_first_name = document.getElementById('guarantor-first-name').value;
    formData.guarantor_last_name = document.getElementById('guarantor-last-name').value;
    formData.guarantor_cnp = cnp;
    formData.guarantor_address = document.getElementById('guarantor-address').value;
    formData.guarantor_city = document.getElementById('guarantor-city').value;
    formData.guarantor_id_type = document.getElementById('guarantor-id-type').value;
    formData.guarantor_id_series = document.getElementById('guarantor-id-series').value.toUpperCase();
    formData.guarantor_id_number = document.getElementById('guarantor-id-number').value;
    formData.guarantor_id_issued_by = document.getElementById('guarantor-id-issued-by').value;
    formData.guarantor_id_issued_date = document.getElementById('guarantor-id-issued-date').value;

    goToStep(5);
});

// Step 5: Bank Form
document.getElementById('step5-form').addEventListener('submit', (e) => {
    e.preventDefault();

    const iban = document.getElementById('bank-iban').value.toUpperCase().replace(/\s/g, '');

    if (!validateIBANChecksum(iban)) {
        showError('IBAN-ul introdus nu este valid.');
        return;
    }

    hideError();
    formData.iban = iban;
    formData.account_holder = document.getElementById('bank-holder').value;
    formData.bank_name = document.getElementById('bank-name').value;

    // Build summary and go to final step
    buildSummary();
    goToStep(6);
});

function updateStep4ForPersonType(personType) {
    const title = document.getElementById('step4-title');
    const subtitle = document.getElementById('step4-subtitle');
    const organizerTypePfContainer = document.getElementById('organizer-type-pf-container');

    if (personType === 'pf') {
        title.textContent = 'Date Personale';
        subtitle.textContent = 'Informatii pentru identificare si contract';
        organizerTypePfContainer.classList.remove('hidden');
        document.getElementById('organizer-type-pf').required = true;
    } else {
        title.textContent = 'Date Garant';
        subtitle.textContent = 'Persoana care garanteaza pentru companie';
        organizerTypePfContainer.classList.add('hidden');
        document.getElementById('organizer-type-pf').required = false;
    }
}

async function verifyCUI() {
    const cuiInput = document.getElementById('cui-input');
    const btn = document.getElementById('verify-cui-btn');
    let cui = cuiInput.value.trim().toUpperCase();

    // Remove RO prefix if present for the API call
    cui = cui.replace(/^RO/, '');

    if (!cui || !/^[0-9]+$/.test(cui)) {
        AmbiletNotifications.error('Introdu un CUI valid (doar cifre)');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<div class="spinner w-5 h-5"></div> Verificare...';

    try {
        const response = await AmbiletAPI.post('/organizer/settings/verify-cui', { cui: cui });

        if (response.success && response.data) {
            // Populate fields
            document.getElementById('company-name').value = response.data.company_name || '';
            document.getElementById('company-reg').value = response.data.reg_com || '';
            document.getElementById('company-address').value = response.data.address || '';
            document.getElementById('company-city').value = response.data.city || '';
            document.getElementById('company-county').value = response.data.county || '';

            // VAT status
            const isVatPayer = response.data.vat_payer || false;
            document.getElementById('company-vat').value = isVatPayer ? '1' : '0';
            document.getElementById('vat-display').textContent = isVatPayer ? 'Da' : 'Nu';

            const vatBadge = document.getElementById('vat-badge');
            vatBadge.classList.remove('hidden');
            if (isVatPayer) {
                vatBadge.className = 'px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded-full';
                vatBadge.textContent = 'Platitor TVA';
            } else {
                vatBadge.className = 'px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded-full';
                vatBadge.textContent = 'Neplatitor TVA';
            }

            // Store in formData
            formData.cui = cui;
            formData.company_name = response.data.company_name || '';
            formData.reg_com = response.data.reg_com || '';
            formData.company_address = response.data.address || '';
            formData.company_city = response.data.city || '';
            formData.company_county = response.data.county || '';
            formData.vat_payer = isVatPayer;
            formData.anaf_verified = true;

            // Show company details
            document.getElementById('company-details').classList.remove('hidden');
            document.getElementById('step3-submit').disabled = false;

            AmbiletNotifications.success('Date verificate cu succes la ANAF');
        } else {
            AmbiletNotifications.error(response.message || 'CUI invalid sau nu a fost gasit');
            formData.anaf_verified = false;
        }
    } catch (error) {
        console.error('ANAF verification error:', error);
        AmbiletNotifications.error('Eroare la verificarea ANAF. Incearca din nou.');
        formData.anaf_verified = false;
    }

    btn.disabled = false;
    btn.innerHTML = '<svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>Verificare ANAF';
}

function validateIBAN(input) {
    const value = input.value.toUpperCase().replace(/\s/g, '');
    input.value = value;
    const validation = document.getElementById('iban-validation');
    const bankNameInput = document.getElementById('bank-name');
    const submitBtn = document.getElementById('step5-submit');

    if (!value) {
        validation.classList.add('hidden');
        input.classList.remove('border-green-500', 'border-red-500');
        bankNameInput.value = '';
        return;
    }

    // Must start with RO for Romanian IBAN
    if (!value.startsWith('RO')) {
        validation.textContent = 'IBAN-ul romanesc trebuie sa inceapa cu RO';
        validation.className = 'mt-1 text-xs text-red-600';
        input.classList.add('border-red-500');
        input.classList.remove('border-green-500');
        bankNameInput.value = '';
        return;
    }

    // Check length (Romanian IBAN is exactly 24 characters)
    if (value.length < 24) {
        validation.textContent = `Mai sunt necesare ${24 - value.length} caractere`;
        validation.className = 'mt-1 text-xs text-amber-600';
        input.classList.remove('border-green-500', 'border-red-500');
        bankNameInput.value = '';
        return;
    }

    if (value.length > 24) {
        validation.textContent = 'IBAN-ul romanesc are exact 24 caractere';
        validation.className = 'mt-1 text-xs text-red-600';
        input.classList.add('border-red-500');
        input.classList.remove('border-green-500');
        bankNameInput.value = '';
        return;
    }

    // Check format
    const ibanRegex = /^RO[0-9]{2}[A-Z]{4}[A-Z0-9]{16}$/;
    if (!ibanRegex.test(value)) {
        validation.textContent = 'Format invalid. Structura: RO + 2 cifre control + 4 litere banca + 16 caractere cont';
        validation.className = 'mt-1 text-xs text-red-600';
        input.classList.add('border-red-500');
        input.classList.remove('border-green-500');
        bankNameInput.value = '';
        return;
    }

    // IBAN checksum validation
    if (!validateIBANChecksum(value)) {
        validation.textContent = 'Cifrele de control sunt invalide';
        validation.className = 'mt-1 text-xs text-red-600';
        input.classList.add('border-red-500');
        input.classList.remove('border-green-500');
        bankNameInput.value = '';
        return;
    }

    // Extract and display bank code
    const bankCode = value.substring(4, 8);
    const bankName = bankNames[bankCode] || bankCode;

    bankNameInput.value = bankName;

    validation.textContent = `IBAN valid - ${bankName}`;
    validation.className = 'mt-1 text-xs text-green-600';
    input.classList.add('border-green-500');
    input.classList.remove('border-red-500');
}

function validateIBANChecksum(iban) {
    if (!iban || iban.length !== 24) return false;

    // Move first 4 chars to end, replace letters with numbers
    const rearranged = iban.substring(4) + iban.substring(0, 4);
    let numericStr = '';

    for (const char of rearranged) {
        if (char >= 'A' && char <= 'Z') {
            numericStr += (char.charCodeAt(0) - 55).toString();
        } else {
            numericStr += char;
        }
    }

    // Calculate MOD 97-10
    let remainder = 0;
    for (let i = 0; i < numericStr.length; i++) {
        remainder = (remainder * 10 + parseInt(numericStr[i])) % 97;
    }

    return remainder === 1;
}

function buildSummary() {
    // Account summary
    document.getElementById('summary-account').innerHTML = `
        <p><strong>Nume:</strong> ${formData.first_name} ${formData.last_name}</p>
        <p><strong>Email:</strong> ${formData.email}</p>
        <p><strong>Telefon:</strong> ${formData.phone}</p>
    `;

    // Organizer type summary
    const personTypeLabel = formData.person_type === 'pj' ? 'Persoana Juridica' : 'Persoana Fizica';
    const workModeLabel = formData.work_mode === 'exclusive' ? 'Lucru Exclusiv' : 'Lucru Neexclusiv';
    const organizerTypeLabel = organizerTypeLabels[formData.organizer_type] || formData.organizer_type;

    document.getElementById('summary-type').innerHTML = `
        <p><strong>Tip persoana:</strong> ${personTypeLabel}</p>
        <p><strong>Mod de lucru:</strong> ${workModeLabel}</p>
        <p><strong>Tip organizator:</strong> ${organizerTypeLabel}</p>
    `;

    // Company summary (only for PJ)
    const companySection = document.getElementById('summary-company-section');
    if (formData.person_type === 'pj') {
        companySection.classList.remove('hidden');
        document.getElementById('summary-company').innerHTML = `
            <p><strong>Denumire:</strong> ${formData.company_name}</p>
            <p><strong>CUI:</strong> ${formData.cui}</p>
            <p><strong>Reg. Com.:</strong> ${formData.reg_com || '-'}</p>
            <p><strong>Platitor TVA:</strong> ${formData.vat_payer ? 'Da' : 'Nu'}</p>
            <p><strong>Adresa:</strong> ${formData.company_address}, ${formData.company_city}, ${formData.company_county}</p>
            <p><strong>Reprezentant:</strong> ${formData.representative_first_name} ${formData.representative_last_name}</p>
        `;
    } else {
        companySection.classList.add('hidden');
    }

    // Guarantor summary
    const guarantorTitle = formData.person_type === 'pj' ? 'Garant' : 'Date Personale';
    document.getElementById('summary-guarantor-title').textContent = guarantorTitle;

    const idTypeLabel = formData.guarantor_id_type === 'ci' ? 'CI' : 'BI';
    const issuedDate = formData.guarantor_id_issued_date ? new Date(formData.guarantor_id_issued_date).toLocaleDateString('ro-RO') : '-';

    document.getElementById('summary-guarantor').innerHTML = `
        <p><strong>Nume:</strong> ${formData.guarantor_first_name} ${formData.guarantor_last_name}</p>
        <p><strong>CNP:</strong> ${formData.guarantor_cnp}</p>
        <p><strong>Adresa:</strong> ${formData.guarantor_address}, ${formData.guarantor_city}</p>
        <p><strong>Act identitate:</strong> ${idTypeLabel} seria ${formData.guarantor_id_series} nr. ${formData.guarantor_id_number}</p>
        <p><strong>Eliberat de:</strong> ${formData.guarantor_id_issued_by} la ${issuedDate}</p>
    `;

    // Bank summary
    document.getElementById('summary-bank').innerHTML = `
        <p><strong>IBAN:</strong> ${formData.iban}</p>
        <p><strong>Titular:</strong> ${formData.account_holder}</p>
        <p><strong>Banca:</strong> ${formData.bank_name}</p>
    `;

    // Update invitation cost based on work mode
    const invitationCostText = document.getElementById('invitation-cost-text');
    if (formData.work_mode === 'exclusive') {
        invitationCostText.innerHTML = '<span class="text-green-600 font-semibold">GRATUIT</span> (beneficiu lucru exclusiv)';
    } else {
        invitationCostText.textContent = '0.25 lei per bucata';
    }
}

async function submitRegistration() {
    const termsChecked = document.querySelector('input[name="terms"]').checked;
    const contractChecked = document.querySelector('input[name="contract"]').checked;

    if (!termsChecked || !contractChecked) {
        AmbiletNotifications.error('Trebuie sa accepti termenii si contractul');
        return;
    }

    const btn = document.getElementById('final-submit');
    const btnText = document.getElementById('final-btn-text');
    const btnSpinner = document.getElementById('final-btn-spinner');

    btn.disabled = true;
    btnText.classList.add('hidden');
    btnSpinner.classList.remove('hidden');

    try {
        // Prepare registration data
        const registrationData = {
            // Account info
            first_name: formData.first_name,
            last_name: formData.last_name,
            email: formData.email,
            phone: formData.phone,
            phone_country: formData.phone_country,
            password: formData.password,
            password_confirmation: formData.password_confirmation,

            // Organizer type
            person_type: formData.person_type,
            work_mode: formData.work_mode,
            organizer_type: formData.organizer_type,

            // Company info (if PJ)
            cui: formData.cui || null,
            company_name: formData.company_name || null,
            reg_com: formData.reg_com || null,
            company_address: formData.company_address || null,
            company_city: formData.company_city || null,
            company_county: formData.company_county || null,
            vat_payer: formData.vat_payer || false,
            representative_first_name: formData.representative_first_name || null,
            representative_last_name: formData.representative_last_name || null,

            // Guarantor info
            guarantor_first_name: formData.guarantor_first_name,
            guarantor_last_name: formData.guarantor_last_name,
            guarantor_cnp: formData.guarantor_cnp,
            guarantor_address: formData.guarantor_address,
            guarantor_city: formData.guarantor_city,
            guarantor_id_type: formData.guarantor_id_type,
            guarantor_id_series: formData.guarantor_id_series,
            guarantor_id_number: formData.guarantor_id_number,
            guarantor_id_issued_by: formData.guarantor_id_issued_by,
            guarantor_id_issued_date: formData.guarantor_id_issued_date,

            // Bank info
            iban: formData.iban,
            account_holder: formData.account_holder,
            bank_name: formData.bank_name
        };

        const result = await AmbiletAuth.registerOrganizer(registrationData);

        if (result.success) {
            AmbiletNotifications.success('Contul a fost creat cu succes!');
            setTimeout(() => {
                window.location.href = '/organizator/login?registered=1';
            }, 1500);
        } else {
            showError(result.message || 'Eroare la inregistrare.');
            btn.disabled = false;
            btnText.classList.remove('hidden');
            btnSpinner.classList.add('hidden');
        }
    } catch (error) {
        console.error('Registration error:', error);
        AmbiletNotifications.error('A aparut o eroare. Incearca din nou.');
        btn.disabled = false;
        btnText.classList.remove('hidden');
        btnSpinner.classList.add('hidden');
    }
}

function showError(message) {
    const errorEl = document.getElementById('error-message');
    errorEl.textContent = message;
    errorEl.classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function hideError() {
    document.getElementById('error-message').classList.add('hidden');
}

// Auto-fill guarantor name from account info for PF
document.querySelectorAll('input[name="person_type"]').forEach(radio => {
    radio.addEventListener('change', (e) => {
        if (e.target.value === 'pf') {
            // Pre-fill guarantor with account holder info
            setTimeout(() => {
                const gFirstName = document.getElementById('guarantor-first-name');
                const gLastName = document.getElementById('guarantor-last-name');
                if (gFirstName && !gFirstName.value) gFirstName.value = formData.first_name || '';
                if (gLastName && !gLastName.value) gLastName.value = formData.last_name || '';
            }, 100);
        }
    });
});

// Update radio button styling
document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const name = this.name;
        document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
            const label = r.closest('label');
            const checkIcon = label.querySelector('.absolute svg');
            const circleDiv = label.querySelector('.absolute.rounded-full');
            if (r.checked) {
                label.classList.add('border-primary', 'bg-primary/5');
                label.classList.remove('border-border');
                if (checkIcon) checkIcon.classList.remove('hidden');
                if (circleDiv) {
                    circleDiv.classList.add('border-primary', 'bg-primary');
                    circleDiv.classList.remove('border-border');
                }
            } else {
                label.classList.remove('border-primary', 'bg-primary/5');
                label.classList.add('border-border');
                if (checkIcon) checkIcon.classList.add('hidden');
                if (circleDiv) {
                    circleDiv.classList.remove('border-primary', 'bg-primary');
                    circleDiv.classList.add('border-border');
                }
            }
        });
    });
});
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
