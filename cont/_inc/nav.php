<?php
/**
 * Structura de navigație a contului client. Fiecare item: key, label, url, icon (SVG inner).
 * Reutilizat de sidebar (desktop) + meniul mobil.
 */
$ico = fn (string $d) => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="' . $d . '"/></svg>';

return [
    'ESENȚIAL' => [
        ['key' => 'dashboard',     'label' => 'Panou principal',    'url' => '/cont',              'icon' => $ico('m3 11 9-8 9 8M5 10v10h14V10M9 20v-6h6v6')],
        ['key' => 'tickets',       'label' => 'Biletele mele',      'url' => '/cont/bilete',       'icon' => $ico('M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5V9a3 3 0 0 0 0 6v1.5a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5V15a3 3 0 0 0 0-6V7.5ZM14 8v8')],
        ['key' => 'subscriptions', 'label' => 'Abonamentele mele',  'url' => '/cont/abonamente',   'icon' => $ico('M4 5h16v14H4zM8 5v14M12 9h5M12 13h4')],
        ['key' => 'calendar',      'label' => 'Calendarul meu',     'url' => '/cont/calendar',     'icon' => $ico('M6 3v3m12-3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Zm3 7h3v3H8z')],
        ['key' => 'orders',        'label' => 'Comenzile mele',     'url' => '/cont/comenzi',      'icon' => $ico('M5 8h14l1 12H4L5 8Zm4 0V6a3 3 0 0 1 6 0v2')],
    ],
    'DESCOPERĂ' => [
        ['key' => 'favorites',     'label' => 'Favorite și alerte', 'url' => '/cont/favorite',     'icon' => $ico('M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z')],
        ['key' => 'notifications', 'label' => 'Notificări',         'url' => '/cont/notificari',   'icon' => $ico('M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 12h4')],
        ['key' => 'rewards',       'label' => 'Puncte și recompense','url' => '/cont/puncte',      'icon' => $ico('m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3Z')],
        ['key' => 'gift-cards',    'label' => 'Carduri cadou',      'url' => '/cont/carduri-cadou','icon' => $ico('M4 10h16v10H4V10Zm-1-4h18v4H3V6Zm9 0v14M12 6H8.5A2.5 2.5 0 1 1 11 3.5V6Zm0 0h3.5A2.5 2.5 0 1 0 13 3.5V6Z')],
        ['key' => 'referrals',     'label' => 'Invită prieteni',    'url' => '/cont/invita',       'icon' => $ico('M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75')],
        ['key' => 'reviews',       'label' => 'Recenziile mele',    'url' => '/cont/recenzii',     'icon' => $ico('M4 4h16v13H8l-4 4V4Zm4 4h8M8 12h5')],
    ],
    'CONT' => [
        ['key' => 'profile',       'label' => 'Profil cultural',    'url' => '/cont/profil',       'icon' => $ico('M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z')],
        ['key' => 'family',        'label' => 'Cont de familie',    'url' => '/cont/familie',      'icon' => $ico('M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75')],
        ['key' => 'payments',      'label' => 'Plăți și facturare', 'url' => '/cont/plati',        'icon' => $ico('M3 6h18v12H3V6Zm0 4h18M7 15h3')],
        ['key' => 'settings',      'label' => 'Setări',             'url' => '/cont/setari',       'icon' => $ico('M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.4-3.5a7.5 7.5 0 0 0-.1-1l2-1.5-2-3.4-2.4 1a8 8 0 0 0-1.7-1L15 3.5h-4l-.4 2.6a8 8 0 0 0-1.7 1l-2.4-1-2 3.4 2 1.5a7.5 7.5 0 0 0 0 2l-2 1.5 2 3.4 2.4-1a8 8 0 0 0 1.7 1l.4 2.6h4l.4-2.6a8 8 0 0 0 1.7-1l2.4 1 2-3.4-2-1.5a7.5 7.5 0 0 0 .1-1Z')],
        ['key' => 'help',          'label' => 'Ajutor și suport',   'url' => '/cont/ajutor',       'icon' => $ico('M9.1 9a3 3 0 1 1 4.8 2.4c-1 .8-1.9 1.3-1.9 2.6M12 18h.01M22 12A10 10 0 1 1 2 12a10 10 0 0 1 20 0Z')],
    ],
];
