<?php
/**
 * Politica de cookies (comună tuturor template-urilor), după modelul bilete.online.
 * Operatorul datelor e organizatorul (SITE_NAME); Tixello furnizează platforma.
 */
$pageTitle = 'Politica de cookies — ' . SITE_NAME;
$pageDescription = 'Cum folosește ' . SITE_NAME . ' cookie-urile și cum îți schimbi alegerea.';
include ts_layout('head');
include ts_layout('header');
$contact = trim((string) site('footer.email', ''));
?>
<main class="pt-32 pb-24 px-4 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="font-display text-4xl lg:text-5xl mb-4">Politica de cookies</h1>
        <p class="text-lg text-fg/70 mb-8">Folosim cookie-uri ca site-ul să funcționeze (coș, plată, cont) și, doar cu acordul tău, pentru statistici și reclame. Îți poți schimba alegerea oricând.</p>
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('site:cookie-settings'))" class="btn-primary rounded-lg px-6 py-3">Schimbă preferințele</button>

        <div class="mt-12 space-y-10 text-fg/80 leading-relaxed">
            <section>
                <h2 class="font-display text-2xl text-fg mb-3">Ce sunt cookie-urile</h2>
                <p>Sunt fișiere mici pe care browserul le păstrează ca să țină minte, de exemplu, ce bilete ai pus în coș sau că ești autentificat. Pe lângă cookie-uri, site-ul păstrează în browser (local storage) coșul, contul și alegerea ta despre cookie-uri.</p>
            </section>
            <section>
                <h2 class="font-display text-2xl text-fg mb-3">Ce folosim</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-fg/10 bg-surface/60 p-5"><b class="block text-fg mb-1">Necesare</b><p class="text-sm">Coș, plată, autentificare, rezervarea locurilor, securitate și memorarea alegerii tale. Mereu active.</p></div>
                    <div class="rounded-xl border border-fg/10 bg-surface/60 p-5"><b class="block text-fg mb-1">Analiză</b><p class="text-sm">Statistici despre folosirea site-ului (de exemplu Google Analytics). Doar cu acordul tău.</p></div>
                    <div class="rounded-xl border border-fg/10 bg-surface/60 p-5"><b class="block text-fg mb-1">Marketing</b><p class="text-sm">Măsurarea campaniilor (Meta, Google, TikTok). Doar cu acordul tău explicit.</p></div>
                </div>
            </section>
            <section>
                <h2 class="font-display text-2xl text-fg mb-3">Cum îți schimbi alegerea</h2>
                <p>La prima vizită apare o bară în care poți alege: <b>Accept toate</b>, <b>Doar necesare</b> sau <b>Personalizează</b>. O poți redeschide oricând din butonul de mai sus sau din linkul „Setări cookies” din subsolul fiecărei pagini.</p>
            </section>
            <section>
                <h2 class="font-display text-2xl text-fg mb-3">Cine prelucrează datele</h2>
                <p>Operatorul datelor este <b><?= e(SITE_NAME) ?></b>, organizatorul evenimentelor de pe acest site. Platforma de ticketing (site, coș, plată, bilete) este furnizată de Tixello, care prelucrează datele în numele organizatorului.</p>
            </section>
            <section>
                <h2 class="font-display text-2xl text-fg mb-3">Drepturile tale</h2>
                <p>Conform GDPR, ai dreptul să fii informat, să ai acces la date, să le rectifici, să le ștergi sau să te opui prelucrării. <?php if ($contact !== ''): ?>Pentru orice solicitare scrie la <a href="mailto:<?= e($contact) ?>" class="text-primary underline"><?= e($contact) ?></a>. <?php endif; ?>Detalii în <a href="/confidentialitate" class="text-primary underline">Politica de confidențialitate</a>.</p>
            </section>
        </div>
    </div>
</main>
<?php include ts_layout('footer');
