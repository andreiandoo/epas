<?php
/** PAGESET: 404 */
http_response_code(404);
layout('public', ['title' => '404 — ' . kit_cfg('site_name')], function () {
    component('empty-state', ['icon' => '🔎', 'message' => t('error.404.msg'),
        'action' => ['label' => t('common.home'), 'url' => '/']]);
});
