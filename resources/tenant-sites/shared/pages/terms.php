<?php
/** Termeni și condiții: textul tenantului din core, altfel textul implicit (RO / EN). */
$pageTitle = t('Termeni și condiții') . ' — ' . SITE_NAME;
$resp = api_get('/tenant-client/terms', [], 600);
$content = ($resp['success'] && !empty($resp['data']['content'])) ? $resp['data']['content'] : null;
include ts_layout('head');
include ts_layout('header');
?>
<section class="pt-32 pb-24 px-4 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="font-display text-4xl lg:text-5xl mb-8"><?= te('Termeni și condiții') ?></h1>
        <div class="space-y-4 text-lg leading-relaxed text-fg/70">
            <?php if ($content): echo $content; elseif (ts_locale() === 'en'): ?>
                <p>By buying tickets you agree to the sales terms of <?= e(SITE_NAME) ?>. Tickets are non-refundable, except when the event is cancelled or rescheduled.</p>
                <p>Entry is based on a valid ticket (printed or on your phone, with a QR code). Each code can be scanned once. Please arrive in time for the start of the event.</p>
                <p>Payments are processed securely by card. Your order confirmation and tickets are sent to the email address entered at checkout.</p>
            <?php else: ?>
                <p>Prin cumpărarea biletelor sunteți de acord cu termenii de vânzare ai <?= e(SITE_NAME) ?>. Biletele nu se returnează, cu excepția anulării sau reprogramării evenimentului.</p>
                <p>Accesul se face pe baza biletului valid (tipărit sau pe telefon, cu cod QR). Fiecare cod se scanează o singură dată. Vă rugăm să ajungeți la timp pentru începerea evenimentului.</p>
                <p>Plata se face securizat, cu cardul. Confirmarea comenzii și biletele se trimit pe adresa de email completată la plată.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include ts_layout('footer');
