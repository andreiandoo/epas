<?php
/** PAGESET: 404 */
http_response_code(404);
layout('public', ['title' => 'Pagină negăsită — ' . kit_cfg('site_name')], function () {
    component('empty-state', ['icon' => '🔎', 'message' => 'Pagina căutată nu există.',
        'action' => ['label' => 'Acasă', 'url' => '/']]);
});
