<?php
/** Adresele galei în sitemap.xml, pe lângă cele comune: galele trecute (rezultate), cu pagina lor. */
require_once TEMPLATE_DIR . '/lib.php';
return array_map(fn ($e) => ts_link('event', ['slug' => $e['slug']]), array_filter(gx_past(), fn ($e) => !empty($e['slug'])));
