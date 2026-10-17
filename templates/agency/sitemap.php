<?php
/** Adresele template-ului agency în sitemap.xml, pe lângă cele comune: paginile artiștilor și concertele trecute. */
require_once TEMPLATE_DIR . '/lib.php';
return array_merge(array_map(fn ($a) => ag_artist_url($a), ag_roster()), array_map(fn ($e) => ts_link('event', ['slug' => $e['slug']]), array_filter(ag_past(), fn ($e) => !empty($e['slug']))));
