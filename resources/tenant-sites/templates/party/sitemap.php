<?php
/** Adresele template-ului party în sitemap.xml, pe lângă cele comune: galeria și edițiile trecute (cu albumele lor). */
require_once TEMPLATE_DIR . '/lib.php';
return array_merge(['/galerie'], array_map(fn ($e) => ts_link('event', ['slug' => $e['slug']]), array_filter(pt_past(), fn ($e) => !empty($e['slug']))));
