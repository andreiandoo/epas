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
            <a href="/organizer/login.php" class="text-sm text-muted hover:text-primary">Am deja cont &rarr;</a>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-12">
        <div class="flex items-center justify-center mb-12">
            <div class="flex items-center"><div id="step1-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-primary text-white font-bold">1</div><span class="ml-2 text-sm font-medium text-secondary hidden sm:block">Cont</span></div>
            <div class="w-12 sm:w-20 h-0.5 bg-border mx-2" id="step1-line"></div>
            <div class="flex items-center"><div id="step2-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-border text-muted font-bold">2</div><span class="ml-2 text-sm text-muted hidden sm:block">Companie</span></div>
            <div class="w-12 sm:w-20 h-0.5 bg-border mx-2" id="step2-line"></div>
            <div class="flex items-center"><div id="step3-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-border text-muted font-bold">3</div><span class="ml-2 text-sm text-muted hidden sm:block">Plata</span></div>
            <div class="w-12 sm:w-20 h-0.5 bg-border mx-2" id="step3-line"></div>
            <div class="flex items-center"><div id="step4-indicator" class="flex items-center justify-center w-10 h-10 rounded-full bg-border text-muted font-bold">4</div><span class="ml-2 text-sm text-muted hidden sm:block">Finalizare</span></div>
        </div>

        <div id="error-message" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-error"></div>

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
                    <div><label class="label">Telefon *</label><input type="tel" name="phone" required class="input" placeholder="+40 7XX XXX XXX"></div>
                    <div><label class="label">Parola *</label><input type="password" name="password" required class="input" placeholder="Minim 8 caractere"></div>
                    <div><label class="label">Confirma parola *</label><input type="password" name="password_confirmation" required class="input" placeholder="Repeta parola"></div>
                    <button type="submit" class="btn btn-primary w-full mt-6">Continua <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></button>
                </form>
            </div>
        </div>

        <div id="step2" class="step-content hidden">
            <div class="bg-white rounded-2xl border border-border p-8">
                <h2 class="text-2xl font-bold text-secondary mb-2">Detalii companie</h2>
                <p class="text-muted mb-6">Informatii despre organizatia ta</p>
                <form id="step2-form" class="space-y-4">
                    <div><label class="label">CUI / CIF *</label><div class="flex gap-2"><input type="text" name="cui" required class="input flex-1" placeholder="RO12345678" id="cui-input"><button type="button" onclick="verifyCUI()" class="btn btn-secondary" id="verify-btn">Verifica</button></div><p class="text-xs text-muted mt-1">Vom prelua automat datele de la ANAF</p></div>
                    <div><label class="label">Denumire companie *</label><input type="text" name="company_name" required class="input" placeholder="SC Exemplu SRL" id="company-name"></div>
                    <div><label class="label">Adresa sediului *</label><input type="text" name="address" required class="input" placeholder="Str. Exemplu, Nr. 1" id="company-address"></div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div><label class="label">Oras *</label><input type="text" name="city" required class="input" placeholder="Bucuresti" id="company-city"></div>
                        <div><label class="label">Judet *</label><input type="text" name="county" required class="input" placeholder="Bucuresti" id="company-county"></div>
                    </div>
                    <div><label class="label">Tip organizator</label><select name="organizer_type" class="input"><option value="agency">Agentie de evenimente</option><option value="promoter">Promoter independent</option><option value="venue">Locatie / Sala</option><option value="artist">Artist / Manager</option><option value="ngo">ONG / Fundatie</option><option value="other">Altele</option></select></div>
                    <div class="flex gap-4 mt-6">
                        <button type="button" onclick="goToStep(1)" class="btn btn-secondary flex-1"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>Inapoi</button>
                        <button type="submit" class="btn btn-primary flex-1">Continua <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></button>
                    </div>
                </form>
            </div>
        </div>

        <div id="step3" class="step-content hidden">
            <div class="bg-white rounded-2xl border border-border p-8">
                <h2 class="text-2xl font-bold text-secondary mb-2">Detalii bancare</h2>
                <p class="text-muted mb-6">Informatii pentru primirea platilor</p>
                <form id="step3-form" class="space-y-4">
                    <div><label class="label">IBAN *</label><input type="text" name="iban" required class="input" placeholder="RO49AAAA1B31007593840000"><p class="text-xs text-muted mt-1">Contul in care vei primi platile pentru biletele vandute</p></div>
                    <div><label class="label">Nume titular cont *</label><input type="text" name="account_holder" required class="input" placeholder="SC Exemplu SRL"></div>
                    <div><label class="label">Banca</label><input type="text" name="bank_name" class="input" placeholder="ING Bank"></div>
                    <div class="p-4 bg-accent/10 rounded-xl"><div class="flex items-start gap-3"><svg class="w-5 h-5 text-accent flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><div><p class="font-medium text-secondary">Cand primesc banii?</p><p class="text-sm text-muted">Platile sunt procesate in maxim 5 zile lucratoare dupa eveniment. Pentru evenimente cu valoare mare, poti solicita plati partiale in avans.</p></div></div></div>
                    <div class="flex gap-4 mt-6">
                        <button type="button" onclick="goToStep(2)" class="btn btn-secondary flex-1"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>Inapoi</button>
                        <button type="submit" class="btn btn-primary flex-1">Continua <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></button>
                    </div>
                </form>
            </div>
        </div>

        <div id="step4" class="step-content hidden">
            <div class="bg-white rounded-2xl border border-border p-8">
                <h2 class="text-2xl font-bold text-secondary mb-2">Finalizare inregistrare</h2>
                <p class="text-muted mb-6">Verifica datele si confirma</p>
                <div class="space-y-4 mb-6">
                    <div class="p-4 bg-surface rounded-xl"><h4 class="font-medium text-secondary mb-2">Informatii cont</h4><p class="text-sm text-muted" id="summary-account"></p></div>
                    <div class="p-4 bg-surface rounded-xl"><h4 class="font-medium text-secondary mb-2">Informatii companie</h4><p class="text-sm text-muted" id="summary-company"></p></div>
                    <div class="p-4 bg-surface rounded-xl"><h4 class="font-medium text-secondary mb-2">Detalii bancare</h4><p class="text-sm text-muted" id="summary-bank"></p></div>
                </div>
                <div class="space-y-3 mb-6">
                    <label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" name="terms" required class="mt-1 w-4 h-4 rounded border-border text-primary focus:ring-primary"><span class="text-sm text-muted">Accept <a href="/terms.php" class="text-primary">Termenii si Conditiile</a> pentru organizatori si <a href="/privacy.php" class="text-primary">Politica de Confidentialitate</a> *</span></label>
                    <label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" name="contract" required class="mt-1 w-4 h-4 rounded border-border text-primary focus:ring-primary"><span class="text-sm text-muted">Accept <a href="/contract.php" class="text-primary">Contractul de prestari servicii</a> <?= SITE_NAME ?> *</span></label>
                </div>
                <div class="flex gap-4">
                    <button type="button" onclick="goToStep(3)" class="btn btn-secondary flex-1"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>Inapoi</button>
                    <button type="button" onclick="submitRegistration()" class="btn btn-primary flex-1" id="final-submit"><span id="final-btn-text">Finalizeaza inregistrarea</span><div id="final-btn-spinner" class="hidden spinner"></div></button>
                </div>
            </div>
        </div>
    </main>
<?php
$scriptsExtra = <<<'JS'
<script>
let currentStep = 1;
let formData = {};

function goToStep(step) {
    document.querySelectorAll('.step-content').forEach(el => el.classList.add('hidden'));
    document.getElementById(`step${step}`).classList.remove('hidden');
    for (let i = 1; i <= 4; i++) {
        const indicator = document.getElementById(`step${i}-indicator`);
        const line = document.getElementById(`step${i}-line`);
        if (i < step) { indicator.className = 'flex items-center justify-center w-10 h-10 rounded-full bg-success text-white font-bold'; indicator.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>'; if (line) line.className = 'w-12 sm:w-20 h-0.5 bg-success mx-2'; }
        else if (i === step) { indicator.className = 'flex items-center justify-center w-10 h-10 rounded-full bg-primary text-white font-bold'; indicator.textContent = i; }
        else { indicator.className = 'flex items-center justify-center w-10 h-10 rounded-full bg-border text-muted font-bold'; indicator.textContent = i; if (line) line.className = 'w-12 sm:w-20 h-0.5 bg-border mx-2'; }
    }
    currentStep = step;
}

document.getElementById('step1-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;
    if (form.password.value !== form.password_confirmation.value) { document.getElementById('error-message').textContent = 'Parolele nu coincid.'; document.getElementById('error-message').classList.remove('hidden'); return; }
    document.getElementById('error-message').classList.add('hidden');
    formData.first_name = form.first_name.value; formData.last_name = form.last_name.value; formData.email = form.email.value; formData.phone = form.phone.value; formData.password = form.password.value; formData.password_confirmation = form.password_confirmation.value;
    goToStep(2);
});

document.getElementById('step2-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;
    formData.cui = form.cui.value; formData.company_name = form.company_name.value; formData.address = form.address.value; formData.city = form.city.value; formData.county = form.county.value; formData.organizer_type = form.organizer_type.value;
    goToStep(3);
});

document.getElementById('step3-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;
    formData.iban = form.iban.value; formData.account_holder = form.account_holder.value; formData.bank_name = form.bank_name.value;
    document.getElementById('summary-account').textContent = `${formData.first_name} ${formData.last_name} - ${formData.email}`;
    document.getElementById('summary-company').textContent = `${formData.company_name} - CUI: ${formData.cui} - ${formData.city}`;
    document.getElementById('summary-bank').textContent = `IBAN: ${formData.iban} - ${formData.account_holder}`;
    goToStep(4);
});

async function verifyCUI() {
    const cui = document.getElementById('cui-input').value;
    const btn = document.getElementById('verify-btn');
    if (!cui) { AmbiletNotifications.error('Introdu CUI-ul'); return; }
    btn.disabled = true; btn.textContent = 'Verificare...';
    try {
        await new Promise(resolve => setTimeout(resolve, 1000));
        document.getElementById('company-name').value = 'SC Exemplu SRL';
        document.getElementById('company-address').value = 'Str. Exemplu, Nr. 1';
        document.getElementById('company-city').value = 'Bucuresti';
        document.getElementById('company-county').value = 'Bucuresti';
        AmbiletNotifications.success('Date preluate de la ANAF');
    } catch (error) { AmbiletNotifications.error('Eroare la verificarea CUI'); }
    btn.disabled = false; btn.textContent = 'Verifica';
}

async function submitRegistration() {
    const termsChecked = document.querySelector('input[name="terms"]').checked;
    const contractChecked = document.querySelector('input[name="contract"]').checked;
    if (!termsChecked || !contractChecked) { AmbiletNotifications.error('Trebuie sa accepti termenii si contractul'); return; }
    const btn = document.getElementById('final-submit');
    const btnText = document.getElementById('final-btn-text');
    const btnSpinner = document.getElementById('final-btn-spinner');
    btn.disabled = true; btnText.classList.add('hidden'); btnSpinner.classList.remove('hidden');
    try {
        const result = await AmbiletAuth.registerOrganizer(formData);
        if (result.success) { AmbiletNotifications.success('Contul a fost creat cu succes!'); setTimeout(() => { window.location.href = '/organizer/login.php?registered=1'; }, 1500); }
        else { document.getElementById('error-message').textContent = result.message || 'Eroare la inregistrare.'; document.getElementById('error-message').classList.remove('hidden'); goToStep(1); btn.disabled = false; btnText.classList.remove('hidden'); btnSpinner.classList.add('hidden'); }
    } catch (error) { AmbiletNotifications.error('A aparut o eroare. Incearca din nou.'); btn.disabled = false; btnText.classList.remove('hidden'); btnSpinner.classList.add('hidden'); }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
