<?php
/**
 * Demo User Data
 * Sample data for user account pages
 */

return [
    'user' => [
        'id' => 1,
        'name' => 'Alexandru Marin',
        'firstName' => 'Alexandru',
        'lastName' => 'Marin',
        'email' => 'alexandru.marin@example.com',
        'phone' => '+40 721 123 456',
        'avatar' => 'https://i.pravatar.cc/40?img=68',
        'avatarLarge' => 'https://i.pravatar.cc/60?img=68',
        'points' => 1250,
        'memberSince' => '2024',
        'ticketsCount' => 3,
        'eventsAttended' => 12,
        'favorites' => 7,
        'isVerified' => true
    ],

    'tickets' => [
        [
            'id' => 'TICS-2026-00458',
            'eventId' => 'coldplay-music-of-the-spheres-bucuresti',
            'eventName' => 'Coldplay: Music of the Spheres',
            'eventImage' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=300&fit=crop',
            'venue' => 'Arena Nationala',
            'city' => 'Bucuresti',
            'date' => '2026-02-14',
            'time' => '20:00',
            'category' => 'Concert',
            'categoryColor' => 'indigo',
            'ticketType' => 'General Admission',
            'quantity' => 2,
            'status' => 'valid',
            'daysUntil' => 9,
            'isUrgent' => true,
            'tickets' => [
                ['code' => 'TICS-2026-00458-001', 'type' => 'General Admission', 'status' => 'valid'],
                ['code' => 'TICS-2026-00458-002', 'type' => 'General Admission', 'status' => 'valid']
            ]
        ],
        [
            'id' => 'TICS-2026-00423',
            'eventId' => 'micutzu-show-nou-2026-bucuresti',
            'eventName' => 'Micutzu - Show Nou 2026',
            'eventImage' => 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=400&h=300&fit=crop',
            'venue' => 'Sala Palatului',
            'city' => 'Bucuresti',
            'date' => '2026-02-11',
            'time' => '19:00',
            'category' => 'Stand-up',
            'categoryColor' => 'amber',
            'ticketType' => 'Categorie A',
            'seatInfo' => 'Rand 12 | Loc 15-16',
            'quantity' => 2,
            'status' => 'valid',
            'daysUntil' => 6,
            'isUrgent' => true,
            'tickets' => [
                ['code' => 'TICS-2026-00423-001', 'type' => 'Categorie A', 'seat' => 'Rand 12, Loc 15', 'status' => 'valid'],
                ['code' => 'TICS-2026-00423-002', 'type' => 'Categorie A', 'seat' => 'Rand 12, Loc 16', 'status' => 'valid']
            ]
        ],
        [
            'id' => 'TICS-2026-00512',
            'eventId' => 'imagine-dragons-bucuresti',
            'eventName' => 'Imagine Dragons',
            'eventImage' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=400&h=300&fit=crop',
            'venue' => 'Arena Nationala',
            'city' => 'Bucuresti',
            'date' => '2026-03-23',
            'time' => '19:30',
            'category' => 'Concert',
            'categoryColor' => 'indigo',
            'ticketType' => 'VIP Experience',
            'quantity' => 1,
            'status' => 'valid',
            'daysUntil' => 46,
            'isUrgent' => false,
            'tickets' => [
                ['code' => 'TICS-2026-00512-001', 'type' => 'VIP Experience', 'status' => 'valid']
            ]
        ]
    ],

    'pastTickets' => [
        [
            'id' => 'TICS-2025-00234',
            'eventName' => 'Ed Sheeran - Mathematics Tour',
            'eventImage' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400&h=300&fit=crop',
            'venue' => 'Arena Nationala',
            'city' => 'Bucuresti',
            'date' => '2025-06-15',
            'time' => '20:00',
            'category' => 'Concert',
            'ticketType' => 'Golden Circle',
            'quantity' => 2,
            'status' => 'used'
        ],
        [
            'id' => 'TICS-2025-00156',
            'eventName' => 'Untold Festival 2025',
            'eventImage' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=400&h=300&fit=crop',
            'venue' => 'Cluj Arena',
            'city' => 'Cluj-Napoca',
            'date' => '2025-08-01',
            'time' => '18:00',
            'category' => 'Festival',
            'ticketType' => '4 Day Pass',
            'quantity' => 1,
            'status' => 'used'
        ]
    ],

    'orders' => [
        [
            'id' => 'ORD-2026-00458',
            'date' => '2026-01-15',
            'status' => 'completed',
            'total' => 798.00,
            'items' => [
                ['name' => 'Coldplay: Music of the Spheres - General Admission', 'quantity' => 2, 'price' => 399.00]
            ]
        ],
        [
            'id' => 'ORD-2026-00423',
            'date' => '2026-01-10',
            'status' => 'completed',
            'total' => 350.00,
            'items' => [
                ['name' => 'Micutzu - Show Nou 2026 - Categorie A', 'quantity' => 2, 'price' => 175.00]
            ]
        ],
        [
            'id' => 'ORD-2026-00512',
            'date' => '2026-01-20',
            'status' => 'completed',
            'total' => 599.00,
            'items' => [
                ['name' => 'Imagine Dragons - VIP Experience', 'quantity' => 1, 'price' => 599.00]
            ]
        ]
    ],

    'recommendations' => [
        [
            'id' => 'the-weeknd-bucuresti',
            'name' => 'The Weeknd',
            'date' => '15 Apr 2026',
            'city' => 'Bucuresti',
            'price' => 349,
            'matchScore' => 96
        ],
        [
            'id' => 'electric-castle-2026',
            'name' => 'Electric Castle',
            'date' => '17-20 Iul 2026',
            'city' => 'Cluj',
            'price' => 449,
            'matchScore' => 91
        ],
        [
            'id' => 'muse-bucuresti',
            'name' => 'Muse - Will of the People',
            'date' => '15 Mai 2026',
            'city' => 'Bucuresti',
            'price' => 349,
            'matchScore' => 87
        ]
    ],

    'stats' => [
        'activeTickets' => 3,
        'newTickets' => 1,
        'points' => 1250,
        'eventsAttended' => 12,
        'favorites' => 7
    ]
];
