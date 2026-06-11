<?php
/**
 * Demo Organizer Data
 * Sample data for organizer account pages
 */

return [
    'organizer' => [
        'id' => 1,
        'companyName' => 'Live Events SRL',
        'displayName' => 'Live Events',
        'email' => 'contact@liveevents.ro',
        'phone' => '+40 21 123 4567',
        'avatar' => 'https://i.pravatar.cc/40?img=12',
        'avatarLarge' => 'https://i.pravatar.cc/60?img=12',
        'website' => 'https://liveevents.ro',
        'memberSince' => '2023',
        'isVerified' => true,
        'plan' => 'Professional',
        'commissionRate' => 2.5
    ],

    'stats' => [
        'totalEvents' => 5,
        'activeEvents' => 3,
        'draftEvents' => 2,
        'totalTicketsSold' => 1247,
        'totalRevenue' => 187450.00,
        'pendingPayout' => 12340.00,
        'newOrders' => 12,
        'totalOrders' => 458,
        'totalAttendees' => 1089,
        'checkInRate' => 87.5
    ],

    'events' => [
        [
            'id' => 'coldplay-music-of-the-spheres-bucuresti',
            'name' => 'Coldplay: Music of the Spheres',
            'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=300&fit=crop',
            'venue' => 'Arena Nationala',
            'city' => 'Bucuresti',
            'date' => '2026-02-14',
            'time' => '20:00',
            'status' => 'active',
            'ticketsSold' => 45000,
            'ticketsTotal' => 55000,
            'revenue' => 8550000.00,
            'soldPercentage' => 82
        ],
        [
            'id' => 'stand-up-comedy-night-bucuresti',
            'name' => 'Stand-up Comedy Night',
            'image' => 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=400&h=300&fit=crop',
            'venue' => 'Sala Palatului',
            'city' => 'Bucuresti',
            'date' => '2026-02-20',
            'time' => '19:00',
            'status' => 'active',
            'ticketsSold' => 892,
            'ticketsTotal' => 1200,
            'revenue' => 133800.00,
            'soldPercentage' => 74
        ],
        [
            'id' => 'jazz-festival-cluj',
            'name' => 'Jazz Festival Cluj',
            'image' => 'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?w=400&h=300&fit=crop',
            'venue' => 'Sala Polivalenta',
            'city' => 'Cluj-Napoca',
            'date' => '2026-03-15',
            'time' => '18:00',
            'status' => 'active',
            'ticketsSold' => 355,
            'ticketsTotal' => 800,
            'revenue' => 53250.00,
            'soldPercentage' => 44
        ],
        [
            'id' => 'summer-festival-2026',
            'name' => 'Summer Festival 2026',
            'image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=400&h=300&fit=crop',
            'venue' => 'Parcul Herastrau',
            'city' => 'Bucuresti',
            'date' => '2026-07-20',
            'time' => '16:00',
            'status' => 'draft',
            'ticketsSold' => 0,
            'ticketsTotal' => 10000,
            'revenue' => 0,
            'soldPercentage' => 0
        ],
        [
            'id' => 'rock-concert-timisoara',
            'name' => 'Rock Concert Timisoara',
            'image' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=400&h=300&fit=crop',
            'venue' => 'Sala Olimpia',
            'city' => 'Timisoara',
            'date' => '2026-04-10',
            'time' => '20:00',
            'status' => 'draft',
            'ticketsSold' => 0,
            'ticketsTotal' => 3000,
            'revenue' => 0,
            'soldPercentage' => 0
        ]
    ],

    'recentOrders' => [
        [
            'id' => 'ORD-2026-00892',
            'customerName' => 'Maria Ionescu',
            'customerEmail' => 'maria.ionescu@example.com',
            'event' => 'Coldplay: Music of the Spheres',
            'tickets' => 2,
            'total' => 798.00,
            'date' => '2026-02-05 14:32',
            'status' => 'completed'
        ],
        [
            'id' => 'ORD-2026-00891',
            'customerName' => 'Ion Popescu',
            'customerEmail' => 'ion.popescu@example.com',
            'event' => 'Stand-up Comedy Night',
            'tickets' => 4,
            'total' => 600.00,
            'date' => '2026-02-05 13:15',
            'status' => 'completed'
        ],
        [
            'id' => 'ORD-2026-00890',
            'customerName' => 'Elena Dumitrescu',
            'customerEmail' => 'elena.d@example.com',
            'event' => 'Coldplay: Music of the Spheres',
            'tickets' => 1,
            'total' => 399.00,
            'date' => '2026-02-05 12:48',
            'status' => 'completed'
        ],
        [
            'id' => 'ORD-2026-00889',
            'customerName' => 'Andrei Stanescu',
            'customerEmail' => 'andrei.s@example.com',
            'event' => 'Jazz Festival Cluj',
            'tickets' => 2,
            'total' => 300.00,
            'date' => '2026-02-05 11:22',
            'status' => 'completed'
        ],
        [
            'id' => 'ORD-2026-00888',
            'customerName' => 'Ana Gheorghe',
            'customerEmail' => 'ana.g@example.com',
            'event' => 'Stand-up Comedy Night',
            'tickets' => 2,
            'total' => 300.00,
            'date' => '2026-02-05 10:05',
            'status' => 'completed'
        ]
    ],

    'payouts' => [
        [
            'id' => 'PAY-2026-00045',
            'amount' => 25000.00,
            'status' => 'completed',
            'date' => '2026-02-01',
            'bankAccount' => 'RO49 AAAA 1B31 0075 9384 0000'
        ],
        [
            'id' => 'PAY-2026-00044',
            'amount' => 18500.00,
            'status' => 'completed',
            'date' => '2026-01-15',
            'bankAccount' => 'RO49 AAAA 1B31 0075 9384 0000'
        ],
        [
            'id' => 'PAY-2026-00043',
            'amount' => 12340.00,
            'status' => 'pending',
            'date' => '2026-02-15',
            'bankAccount' => 'RO49 AAAA 1B31 0075 9384 0000'
        ]
    ],

    'team' => [
        [
            'id' => 1,
            'name' => 'Alexandru Georgescu',
            'email' => 'alexandru@liveevents.ro',
            'role' => 'Administrator',
            'avatar' => 'https://i.pravatar.cc/40?img=12',
            'isOwner' => true
        ],
        [
            'id' => 2,
            'name' => 'Maria Popa',
            'email' => 'maria@liveevents.ro',
            'role' => 'Manager evenimente',
            'avatar' => 'https://i.pravatar.cc/40?img=25',
            'isOwner' => false
        ],
        [
            'id' => 3,
            'name' => 'Radu Ionescu',
            'email' => 'radu@liveevents.ro',
            'role' => 'Scanner',
            'avatar' => 'https://i.pravatar.cc/40?img=33',
            'isOwner' => false
        ]
    ]
];
