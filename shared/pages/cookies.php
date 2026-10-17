<?php
/**
 * Politica de cookies (comună tuturor template-urilor), după modelul bilete.online.
 * Operatorul datelor e organizatorul (SITE_NAME); Tixello furnizează platforma.
 */
$pageTitle = t('Politica de cookies') . ' — ' . SITE_NAME;
$pageDescription = t('Cum folosește {name} cookie-urile și cum îți schimbi alegerea.', ['name' => SITE_NAME]);
include ts_layout('head');
include ts_layout('header');
$contact = trim((string) site('footer.email', ''));
?>
<?php if (ts_locale() === 'en'): ?>
<main class="pt-32 pb-24 px-4 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="font-display text-4xl lg:text-5xl mb-4">Cookie policy</h1>
        <p class="text-lg text-fg/70 mb-8">We use cookies so the site works (cart, checkout, account) and, only with your consent, for statistics and ads. You can change your choice at any time.</p>
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('site:cookie-settings'))" class="btn-primary rounded-lg px-6 py-3">Change preferences</button>

        <div class="mt-12 space-y-10 text-fg/80 leading-relaxed">
            <section>
                <h2 class="font-display text-2xl text-fg mb-3">What cookies are</h2>
                <p>Small files your browser keeps to remember, for example, which tickets you put in the cart or that you are signed in. Besides cookies, the site keeps the cart, your account and your cookie choice in the browser (local storage).</p>
            </section>
            <section>
                <h2 class="font-display text-2xl text-fg mb-3">What we use</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-fg/10 bg-surface/60 p-5"><b class="block text-fg mb-1">Necessary</b><p class="text-sm">Cart, checkout, sign-in, seat holds, security and remembering your choice. Always on.</p></div>
                    <div class="rounded-xl border border-fg/10 bg-surface/60 p-5"><b class="block text-fg mb-1">Analytics</b><p class="text-sm">Statistics about how the site is used (for example Google Analytics). Only with your consent.</p></div>
                    <div class="rounded-xl border border-fg/10 bg-surface/60 p-5"><b class="block text-fg mb-1">Marketing</b><p class="text-sm">Campaign measurement (Meta, Google, TikTok). Only with your explicit consent.</p></div>
                </div>
            </section>
            <section>
                <h2 class="font-display text-2xl text-fg mb-3">How to change your choice</h2>
                <p>On your first visit a bar lets you choose <b>Accept all</b>, <b>Necessary only</b> or <b>Customise</b>. You can reopen it any time from the button above or the “Cookie settings” link in the footer of every page.</p>
            </section>
            <section>
                <h2 class="font-display text-2xl text-fg mb-3">Who processes the data</h2>
                <p>The data controller is <b><?= e(SITE_NAME) ?></b>, the organiser of the events on this site. The ticketing platform (site, cart, payment, tickets) is provided by Tixello, which processes data on behalf of the organiser.</p>
            </section>
            <section>
                <h2 class="font-display text-2xl text-fg mb-3">Your rights</h2>
                <p>Under the GDPR you have the right to be informed, to access, rectify and erase your data, and to object to processing. <?php if ($contact !== ''): ?>For any request write to <a href="mailto:<?= e($contact) ?>" class="text-primary underline"><?= e($contact) ?></a>. <?php endif; ?>Details in the <a href="/confidentialitate" class="text-primary underline">Privacy policy</a>.</p>
            </section>
        </div>
    </div>
<?php else: ?>
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
<?php endif; ?>
</main>
<?php include ts_layout('footer');
