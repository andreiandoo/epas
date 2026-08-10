<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Comisionul aplicaţiei pentru evenimentele de tenant
    |--------------------------------------------------------------------------
    |
    | Se aplică DOAR când tenantul nu are un comision propriu configurat.
    | Modelul cade altfel pe 5%, o valoare istorică nepotrivită pentru vânzarea
    | din aplicaţie.
    |
    | Evenimentele de marketplace NU trec pe aici: ele poartă comisionul
    | marketplace-ului, iar Tixello îşi încasează partea de la marketplace —
    | un al doilea comision ar taxa cumpărătorul de două ori.
    |
    */
    'app_commission_rate' => (float) env('TIXELLO_APP_COMMISSION_RATE', 2.0),
];
