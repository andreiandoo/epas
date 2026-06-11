<?php
/**
 * bilete.online — Organizator › Ajutor (v3).
 * Route: /organizator/help
 *
 * Static help center: searchable FAQ accordion grouped in 3 sections plus a
 * contact card. Activity-centric adaptation of the ambilet help page.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/api.php';
$pageTitle   = 'Ajutor';
$currentPage = 'help';

$configData   = api_cached('client_config', fn() => api_get('/config'), 3600);
$contactPhone = $configData['data']['contact']['phone'] ?? SUPPORT_PHONE;
$contactEmail = $configData['data']['contact']['email'] ?? SUPPORT_EMAIL;

require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';

// Faq helper to keep the markup compact and consistent.
$faq = function (string $q, string $a) {
    return '<div class="faq-item border-b border-ink/10 last:border-0">'
        . '<button onclick="toggleFaq(this)" class="flex w-full items-center justify-between gap-4 p-6 text-left transition hover:bg-paper-2/60">'
        . '<span class="font-medium">' . $q . '</span>'
        . '<svg class="faq-icon h-5 w-5 flex-none text-ink-soft transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>'
        . '</button>'
        . '<div class="faq-content hidden px-6 pb-6 text-sm leading-relaxed text-ink-soft">' . $a . '</div>'
        . '</div>';
};
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="font-display text-3xl font-bold leading-none">Centru de ajutor</h1>
            <p class="mt-1.5 text-sm text-ink-soft">Găsește răspunsuri rapide sau contactează-ne.</p>
        </div>

        <div class="mb-8 rounded-2xl border-2 border-ink bg-paper p-6">
            <div class="relative mx-auto max-w-2xl">
                <input type="text" id="help-search" placeholder="Caută în centrul de ajutor…" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 py-4 pl-12 pr-4 text-lg outline-none transition focus:border-ink">
                <svg class="absolute left-4 top-1/2 h-6 w-6 -translate-y-1/2 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
        </div>

        <div class="mb-8 grid gap-6 md:grid-cols-3">
            <a href="#getting-started" class="group rounded-2xl border-2 border-ink bg-paper p-6 transition hover:bg-vermilion hover:text-paper">
                <span class="mb-4 grid h-12 w-12 place-items-center rounded-xl bg-vermilion/10 text-vermilion transition group-hover:bg-paper/20 group-hover:text-paper"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></span>
                <h3 class="mb-1 font-display text-lg font-bold">Începe rapid</h3><p class="text-sm opacity-80">Ghid pentru prima activitate</p>
            </a>
            <a href="#activities" class="group rounded-2xl border-2 border-ink bg-paper p-6 transition hover:bg-ochre hover:text-paper">
                <span class="mb-4 grid h-12 w-12 place-items-center rounded-xl bg-ochre/10 text-ochre transition group-hover:bg-paper/20 group-hover:text-paper"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></span>
                <h3 class="mb-1 font-display text-lg font-bold">Gestionare activități</h3><p class="text-sm opacity-80">Tot despre activități</p>
            </a>
            <a href="#payments" class="group rounded-2xl border-2 border-ink bg-paper p-6 transition hover:bg-forest hover:text-paper">
                <span class="mb-4 grid h-12 w-12 place-items-center rounded-xl bg-forest/10 text-forest transition group-hover:bg-paper/20 group-hover:text-paper"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                <h3 class="mb-1 font-display text-lg font-bold">Plăți și finanțe</h3><p class="text-sm opacity-80">Comisioane și deconturi</p>
            </a>
        </div>

        <div class="space-y-6">
            <div id="getting-started" class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                <div class="border-b-2 border-ink/10 p-6"><h2 class="font-display text-lg font-bold">Începe rapid</h2></div>
                <?php
                echo $faq('Cum creez prima activitate?', '<ol class="list-inside list-decimal space-y-2"><li>Accesează „Activități" din meniu</li><li>Apasă pe „Creează activitate"</li><li>Completează detaliile</li><li>Adaugă tipuri de bilete</li><li>Încarcă o imagine</li><li>Publică activitatea</li></ol>');
                echo $faq('Ce tipuri de bilete pot crea?', '<p class="mb-3">Poți crea orice tip de bilet cu denumirea pe care o dorești. Titlul biletului apare pe biletul clientului și în procesul de cumpărare, așa că recomandăm să fie clar și ușor de înțeles.</p><p class="mb-2 font-medium text-ink">Exemple recomandate:</p><ul class="list-inside list-disc space-y-2"><li><strong>Acces general</strong> / <strong>Standard</strong> — acces obișnuit</li><li><strong>Early Bird</strong> / <strong>Presale</strong> — preț redus, perioadă limitată</li><li><strong>VIP</strong> — acces preferențial sau beneficii suplimentare</li><li><strong>Abonament</strong> — acces pe toată durata</li><li><strong>Bilet de 1 zi</strong> — valabil într-o anumită zi</li></ul><p class="mt-3">Poți adăuga câte tipuri dorești, fiecare cu preț, stoc și descriere proprie.</p>');
                echo $faq('Cum verific biletele la intrare?', '<p class="mb-2">Opțiuni de check-in:</p><ul class="list-inside list-disc space-y-2"><li><strong>Scanare QR</strong> — folosește camera telefonului din aplicația de staff</li><li><strong>Verificare manuală</strong> — introdu codul de control în pagina <a href="/organizator/participanti" class="font-bold text-vermilion underline">Participanți</a></li></ul>');
                ?>
            </div>

            <div id="activities" class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                <div class="border-b-2 border-ink/10 p-6"><h2 class="font-display text-lg font-bold">Gestionare activități</h2></div>
                <?php
                echo $faq('Cum modific o activitate publicată?', '<p>Poți modifica oricând detaliile unei activități din pagina Activități. Prețul biletelor deja vândute nu poate fi modificat.</p>');
                echo $faq('Pot anula o activitate?', '<p>Da — contactează suportul pentru anulare. Clienții vor fi notificați și rambursați conform politicii.</p>');
                echo $faq('Cum creez coduri promoționale?', '<p>Accesează „Promo" din meniu și apasă „Cod nou". Poți seta reduceri procentuale sau fixe.</p>');
                ?>
            </div>

            <div id="payments" class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                <div class="border-b-2 border-ink/10 p-6"><h2 class="font-display text-lg font-bold">Plăți și finanțe</h2></div>
                <?php
                echo $faq('Care sunt comisioanele ' . htmlspecialchars(SITE_NAME) . '?', '<p>Comisioanele sunt cele negociate și sunt afișate la fiecare activitate în parte, plus eventuale taxe legale aplicabile (ex: timbru, contribuții).</p>');
                echo $faq('Când primesc banii din vânzări?', '<p>Decontul se realizează după terminarea activității, conform contractului. La cerere, deconturile se pot face și când vânzările ating un prag minim agreat.</p>');
                echo $faq('Cum gestionez rambursările?', '<p>Rambursările pentru anulări se procesează automat. Pentru cereri individuale, contactează suportul.</p>');
                ?>
            </div>
        </div>

        <div class="mt-8 rounded-2xl border-2 border-ink bg-paper p-8 text-center">
            <h2 class="mb-3 font-display text-xl font-bold">Nu ai găsit ce căutai?</h2>
            <p class="mb-6 text-ink-soft">Echipa de suport e disponibilă L–V, 9:00–18:00.</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="/organizator/suport" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 3v-3z"/></svg>
                    Deschide un tichet
                </a>
                <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="inline-flex items-center gap-2 rounded-full border-2 border-ink px-5 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <?= htmlspecialchars($contactEmail) ?>
                </a>
                <?php if (!empty($contactPhone)): ?>
                <a href="tel:<?= htmlspecialchars($contactPhone) ?>" class="inline-flex items-center gap-2 rounded-full border-2 border-ink px-5 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <?= htmlspecialchars($contactPhone) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth) BileteOnlineAuth.requireOrganizerAuth();
    const search = document.getElementById('help-search');
    if (search) search.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.faq-item').forEach(item => {
            item.style.display = (q && !item.textContent.toLowerCase().includes(q)) ? 'none' : '';
        });
    });
});
function toggleFaq(button) {
    const item = button.closest('.faq-item');
    item.querySelector('.faq-content').classList.toggle('hidden');
    item.querySelector('.faq-icon').classList.toggle('rotate-180');
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
