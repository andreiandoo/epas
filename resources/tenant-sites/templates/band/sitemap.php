<?php
/** Adresele template-ului band în sitemap.xml, pe lângă cele comune: concertele trecute (arhiva turneului). */
require_once TEMPLATE_DIR . '/lib.php';
return array_map(fn ($e) => ts_link('event', ['slug' => $e['slug']]), array_filter(bd_past(), fn ($e) => !empty($e['slug'])));
