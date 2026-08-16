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
    | Baza după care se taie „azi" pe comenzi: `created_at` (implicit, identic
    | cu panoul de admin) sau `paid_at` (când a intrat banul).
    */
    'today_basis' => env('TIXELLO_WIDGET_TODAY_BASIS', 'created_at'),

    /*
    | Cât timp (secunde) se ţin agregatele în cache.
    |
    | Sunt două cache-uri, cu vieţi diferite, şi asta contează pe producţie:
    | cifrele „all time" cer COUNT/SUM peste tot tabelul (pe `tickets` nu există
    | index pe `status`, deci scanare completă), pe când cifrele de azi sunt
    | mărginite de un interval de dată. Un TTL scurt pe amândouă ar ţine
    | scanarea completă în buclă cât timp există un telefon care întreabă.
    |
    | Lista de comisioane NU trece prin cache: ea e semnalul de alertă şi
    | trebuie să fie proaspătă la fiecare cerere (e ieftină — index pe `id`).
    */
    'cache_ttl' => (int) env('TIXELLO_WIDGET_CACHE_TTL', 20),
    'cache_ttl_all_time' => (int) env('TIXELLO_WIDGET_CACHE_TTL_ALL_TIME', 120),

    /*
    | Câte comisioane recente întoarce endpoint-ul implicit şi cât acceptă
    | maximum (widget-ul cere 5).
    */
    'commissions_limit' => 5,
    'commissions_max_limit' => 50,

    /*
    | Câte comisioane pot declanşa alerte într-o singură rundă. Plafon, ca un
    | telefon întors după o săptămână offline să nu sune de 300 de ori.
    */
    'new_commissions_cap' => (int) env('TIXELLO_WIDGET_NEW_COMMISSIONS_CAP', 20),

    /*
    | Intervalul de polling recomandat, trimis în payload. Aplicaţia îl
    | foloseşte ca valoare implicită, deci poţi încetini toate telefoanele
    | dintr-un singur loc dacă serverul geme.
    */
    'poll_interval_seconds' => (int) env('TIXELLO_WIDGET_POLL_INTERVAL', 60),

];
