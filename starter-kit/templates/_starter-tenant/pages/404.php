<?php
require __DIR__ . '/../includes/bootstrap.php';
http_response_code(404);
layout('public', ['title' => 'Pagină negăsită'], function () {
    component('empty-state', ['icon' => '🎭', 'message' => 'Pagina căutată nu există.', 'action' => ['label' => 'Acasă', 'url' => '/']]);
});
