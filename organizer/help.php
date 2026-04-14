<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/api.php';
$pageTitle = 'Ajutor';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'help';
$cssBundle = 'organizer';

// Fetch contact phone from marketplace settings
$configData = api_cached('client_config', fn() => api_get('/config'), 3600);
$contactPhone = $configData['data']['contact']['phone'] ?? SUPPORT_PHONE;
$contactEmail = $configData['data']['contact']['email'] ?? SUPPORT_EMAIL;

require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
                <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Centru de Ajutor</h1>
                    <p class="text-sm text-muted">Gaseste raspunsuri sau contacteaza-ne</p>
                </div>
                
            </div>


            <div class="p-6 mb-8 bg-white border rounded-2xl border-border">
                <div class="relative max-w-2xl mx-auto">
                    <input type="text" id="help-search" placeholder="Cauta in centrul de ajutor..." class="w-full py-4 pl-12 text-lg input">
                    <svg class="absolute w-6 h-6 -translate-y-1/2 left-4 top-1/2 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <div class="grid gap-6 mb-8 md:grid-cols-3">
                <a href="#getting-started" class="p-6 transition-colors bg-white border rounded-2xl border-border hover:border-primary group">
                    <div class="flex items-center justify-center w-12 h-12 mb-4 transition-colors bg-primary/10 rounded-xl group-hover:bg-primary"><svg class="w-6 h-6 text-primary group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
                    <h3 class="mb-2 font-bold text-secondary">Incepe Rapid</h3><p class="text-sm text-muted">Ghid pentru primul eveniment</p>
                </a>
                <a href="#events" class="p-6 transition-colors bg-white border rounded-2xl border-border hover:border-primary group">
                    <div class="flex items-center justify-center w-12 h-12 mb-4 transition-colors bg-accent/10 rounded-xl group-hover:bg-accent"><svg class="w-6 h-6 text-accent group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                    <h3 class="mb-2 font-bold text-secondary">Gestionare Evenimente</h3><p class="text-sm text-muted">Tot despre evenimente</p>
                </a>
                <a href="#payments" class="p-6 transition-colors bg-white border rounded-2xl border-border hover:border-primary group">
                    <div class="flex items-center justify-center w-12 h-12 mb-4 transition-colors bg-success/10 rounded-xl group-hover:bg-success"><svg class="w-6 h-6 text-success group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                    <h3 class="mb-2 font-bold text-secondary">Plati si Finante</h3><p class="text-sm text-muted">Comisioane si plati</p>
                </a>
            </div>

            <div class="space-y-6">
                <div id="getting-started" class="overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="p-6 border-b border-border"><h2 class="text-lg font-bold text-secondary">Incepe Rapid</h2></div>
                    <div class="divide-y divide-border">
                        <div class="faq-item"><button onclick="toggleFaq(this)" class="flex items-center justify-between w-full p-6 text-left hover:bg-surface"><span class="font-medium text-secondary">Cum creez primul eveniment?</span><svg class="w-5 h-5 transition-transform text-muted faq-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button><div class="hidden px-6 pb-6 faq-content"><ol class="space-y-2 list-decimal list-inside text-muted"><li>Acceseaza "Evenimente" din meniu</li><li>Click pe "Creeaza Eveniment"</li><li>Completeaza detaliile</li><li>Adauga tipuri de bilete</li><li>Incarca o imagine</li><li>Publica evenimentul</li></ol></div></div>
                        <div class="faq-item"><button onclick="toggleFaq(this)" class="flex items-center justify-between w-full p-6 text-left hover:bg-surface"><span class="font-medium text-secondary">Ce tipuri de bilete pot crea?</span><svg class="w-5 h-5 transition-transform text-muted faq-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button><div class="hidden px-6 pb-6 faq-content"><p class="mb-3 text-muted">Poti crea orice tip de bilet cu denumirea pe care o doresti. Titlul biletului apare pe biletul clientului si in procesul de cumparare, asa ca recomandam sa fie clar si usor de inteles.</p><p class="mb-2 font-medium text-secondary">Exemple de denumiri recomandate:</p><ul class="space-y-2 list-disc list-inside text-muted"><li><strong>Acces General</strong> sau <strong>Standard</strong> — bilet cu acces obisnuit</li><li><strong>Early Bird</strong> sau <strong>Presale</strong> — bilet la pret redus, disponibil pentru o perioada limitata</li><li><strong>VIP</strong> — acces preferential, zone speciale sau beneficii suplimentare</li><li><strong>Abonament</strong> — acces pe toata durata unui festival sau eveniment multi-day</li><li><strong>Bilet de 1 zi</strong> — valabil doar intr-o anumita zi (pentru festivaluri)</li><li><strong>Meet & Greet</strong> — include intalnire cu artistii</li></ul><p class="mt-3 text-sm text-muted">Poti adauga cate tipuri de bilete doresti, fiecare cu pret, stoc si descriere proprie.</p></div></div>
                        <div class="faq-item"><button onclick="toggleFaq(this)" class="flex items-center justify-between w-full p-6 text-left hover:bg-surface"><span class="font-medium text-secondary">Cum verific biletele la intrare?</span><svg class="w-5 h-5 transition-transform text-muted faq-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button><div class="hidden px-6 pb-6 faq-content"><p class="mb-2 text-muted">Optiuni check-in:</p><ul class="space-y-2 list-disc list-inside text-muted"><li><strong>Scanare QR</strong> - foloseste camera telefonului</li><li><strong>Verificare manuala</strong> - introduce codul in pagina Participanti</li></ul></div></div>
                    </div>
                </div>

                <div id="events" class="overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="p-6 border-b border-border"><h2 class="text-lg font-bold text-secondary">Gestionare Evenimente</h2></div>
                    <div class="divide-y divide-border">
                        <div class="faq-item"><button onclick="toggleFaq(this)" class="flex items-center justify-between w-full p-6 text-left hover:bg-surface"><span class="font-medium text-secondary">Cum modific un eveniment publicat?</span><svg class="w-5 h-5 transition-transform text-muted faq-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button><div class="hidden px-6 pb-6 faq-content"><p class="text-muted">Poti modifica oricand detaliile unui eveniment din pagina Evenimente. Pretul biletelor vandute nu poate fi modificat.</p></div></div>
                        <div class="faq-item"><button onclick="toggleFaq(this)" class="flex items-center justify-between w-full p-6 text-left hover:bg-surface"><span class="font-medium text-secondary">Pot anula un eveniment?</span><svg class="w-5 h-5 transition-transform text-muted faq-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button><div class="hidden px-6 pb-6 faq-content"><p class="text-muted">Da, contacteaza suportul pentru anulare. Clientii vor fi notificati si rambursati automat.</p></div></div>
                        <div class="faq-item"><button onclick="toggleFaq(this)" class="flex items-center justify-between w-full p-6 text-left hover:bg-surface"><span class="font-medium text-secondary">Cum creez coduri promotionale?</span><svg class="w-5 h-5 transition-transform text-muted faq-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button><div class="hidden px-6 pb-6 faq-content"><p class="text-muted">Acceseaza "Coduri Promo" din meniu si apasa "Creeaza Cod Nou". Poti seta reduceri procentuale sau fixe.</p></div></div>
                    </div>
                </div>

                <div id="payments" class="overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="p-6 border-b border-border"><h2 class="text-lg font-bold text-secondary">Plati si Finante</h2></div>
                    <div class="divide-y divide-border">
                        <div class="faq-item"><button onclick="toggleFaq(this)" class="flex items-center justify-between w-full p-6 text-left hover:bg-surface"><span class="font-medium text-secondary">Care sunt comisioanele <?= SITE_NAME ?>?</span><svg class="w-5 h-5 transition-transform text-muted faq-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button><div class="hidden px-6 pb-6 faq-content"><p>Comisioanele AmBilet.ro sunt cele negociate și sunt trecute la fiecare eveniment în parte</p><p class="mt-2 text-muted">Plus taxe legale care se pot aplica. Exemplu: 1% Crucea Rosie, 5% timbru muzical.</p></div></div>
                        <div class="faq-item"><button onclick="toggleFaq(this)" class="flex items-center justify-between w-full p-6 text-left hover:bg-surface"><span class="font-medium text-secondary">Cand primesc banii din vanzari?</span><svg class="w-5 h-5 transition-transform text-muted faq-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button><div class="hidden px-6 pb-6 faq-content"><p class="text-muted">Decontul se realizează în prima zi după terminarea evenimentului. Dacă evenimentul se desfășoară în weekend, decontul se face în ziua de luni. </p>
                        <p class="text-muted">La cerere, deconturile se realizează și când vânzările ajung la suma de minim 1000 lei.</p></div></div>
                        <div class="faq-item"><button onclick="toggleFaq(this)" class="flex items-center justify-between w-full p-6 text-left hover:bg-surface"><span class="font-medium text-secondary">Cum gestionez rambursarile?</span><svg class="w-5 h-5 transition-transform text-muted faq-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button><div class="hidden px-6 pb-6 faq-content"><p class="text-muted">Rambursarile pentru anulari se proceseaza automat. Pentru cereri individuale, contacteaza suportul.</p></div></div>
                    </div>
                </div>
            </div>

            <div class="p-8 mt-8 text-center bg-white border rounded-2xl border-border">
                <h2 class="mb-4 text-xl font-bold text-secondary">Nu ai gasit ce cautai?</h2>
                <p class="mb-6 text-muted">Echipa de suport e disponibila L-V, 9:00-18:00.</p>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="btn btn-secondary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg><?= htmlspecialchars($contactEmail) ?></a>
                    <a href="tel:<?= htmlspecialchars($contactPhone) ?>" class="btn btn-primary bg-primary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg><?= htmlspecialchars($contactPhone) ?></a>
                </div>
            </div>
        </main>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function() { AmbiletAuth.requireOrganizerAuth(); });

function toggleFaq(button) {
    const item = button.closest('.faq-item');
    const content = item.querySelector('.faq-content');
    const icon = item.querySelector('.faq-icon');
    content.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}

document.getElementById('help-search').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('.faq-item').forEach(item => {
        item.style.display = (query && !item.textContent.toLowerCase().includes(query)) ? 'none' : '';
    });
});
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
