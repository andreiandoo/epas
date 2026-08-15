<?php

/*
|--------------------------------------------------------------------------
| Tixello Widget — API pentru widget-ul de Android
|--------------------------------------------------------------------------
|
| Widget-ul de pe ecranul telefonului citeşte un singur endpoint
| (`GET /api/tixello-widget/summary`) şi afişează cifrele agregate peste
| TOATE tenant-urile şi marketplace-urile. Aici stau parametrii pe care îi
| poţi schimba fără să atingi codul.
|
*/

return [

    /*
    | Fusul orar în care se taie „azi".
    |
    | `config('app.timezone')` e UTC, deci `today()` în dashboard-ul Filament
    | rupe ziua la 03:00 ora României vara. Pentru un widget de telefon, „azi"
    | trebuie să însemne ziua calendaristică a proprietarului, nu ziua UTC.
    */
    'timezone' => env('TIXELLO_WIDGET_TIMEZONE', 'Europe/Bucharest'),

    /*
    | Moneda în care se raportează sumele. Comenzile în alte monede sunt
    | convertite prin `ExchangeRate` (acelaşi mecanism ca widget-urile din
    | panoul de admin).
    */
    'currency' => env('TIXELLO_WIDGET_CURRENCY', 'EUR'),

    /*
    | A doua monedă afişată, informativ (widget-ul o arată sub cifra
    | principală). `null` o scoate din payload.
    */
    'secondary_currency' => env('TIXELLO_WIDGET_SECONDARY_CURRENCY', 'RON'),

    /*
    | Statusurile de comandă considerate „vânzare încasată". Identice cu
    | App\Filament\Widgets\StatsOverview, ca să nu existe două adevăruri.
    */
    'paid_statuses' => ['paid', 'confirmed', 'completed'],

    /*
    | Cât timp (secunde) se ţin agregatele în cache. Widget-ul întreabă des
    | (implicit la 60 s), iar `orders`/`tickets` sunt tabele mari — fără cache
    | fiecare telefon ar declanşa un full scan.
    |
    | Lista de comisioane NU trece prin cache-ul ăsta: ea e semnalul de alertă
    | şi trebuie să fie proaspătă la fiecare cerere.
    */
    'cache_ttl' => (int) env('TIXELLO_WIDGET_CACHE_TTL', 20),

    /*
    | Câte comisioane recente întoarce endpoint-ul implicit şi cât acceptă
    | maximum (widget-ul cere 5).
    */
    'commissions_limit' => 5,
    'commissions_max_limit' => 50,

    /*
    | Intervalul de polling recomandat, trimis în payload. Aplicaţia îl
    | foloseşte ca valoare implicită, deci poţi încetini toate telefoanele
    | dintr-un singur loc dacă serverul geme.
    */
    'poll_interval_seconds' => (int) env('TIXELLO_WIDGET_POLL_INTERVAL', 60),

];
