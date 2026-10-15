<?php
$pageTitle = 'Termeni și condiții — ' . SITE_NAME;
$resp = api_get('/tenant-client/terms', [], 600);
$content = ($resp['success'] && !empty($resp['data']['content'])) ? $resp['data']['content'] : null;
include ts_layout('head');
include ts_layout('header');
?>
<section class="pt-32 pb-24 px-4 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="font-display text-4xl lg:text-5xl mb-8">Termeni și condiții</h1>
        <div class="prose prose-invert prose-lg max-w-none text-fg/70">
            <?php if ($content): echo $content; else: ?>
                <p>Prin achiziția biletelor sunteți de acord cu termenii de vânzare ai instituției. Biletele achiziționate nu se returnează, cu excepția anulării sau reprogramării spectacolului.</p>
                <p>Accesul în sală se face pe baza biletului valid (tipărit sau digital, cu cod QR). Vă rugăm să respectați ora de începere a reprezentației.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include ts_layout('footer');
