<?php
/** Hero: gala următoare ca afiș (main event față în față, numărătoare, bilete). */
require_once TEMPLATE_DIR . '/lib.php';
$ev = gx_next();
if (!$ev) {
    echo '<section class="gx-hero gx-hero--empty"><div class="gx-wrap gx-hero__body"><h1 class="gx-hero__title"><span>' . e(SITE_NAME) . '</span></h1><p class="gx-lead">' . te('Următoarea gală se anunță în curând.') . '</p></div></section>';
    return;
}
$card = gx_card($ev['slug']);
$h1 = true;
$eyebrow = trim((string) ($s['eyebrow'] ?? '')) ?: null;
$buyLabel = trim((string) ($s['button_label'] ?? '')) ?: null;
$cardLabel = trim((string) ($s['card_label'] ?? '')) ?: null;
include TEMPLATE_DIR . '/layout/faceoff.php';
