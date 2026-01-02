/**
 * Ambilet Demo Data
 *
 * This file provides mock data for testing the marketplace without API connection.
 * Set DEMO_MODE = true in config.php to enable.
 *
 * To disable demo mode and use real API:
 * 1. Set DEMO_MODE = false in includes/config.php
 * 2. Set your real API_KEY in includes/config.php
 */

const DEMO_DATA = {
    // ===========================================
    // CATEGORIES
    // ===========================================
    categories: [
        { id: 1, name: 'Concerte', slug: 'concerte', icon: 'music', count: 45, color: '#A51C30' },
        { id: 2, name: 'Festivaluri', slug: 'festivaluri', icon: 'sparkles', count: 12, color: '#E67E22' },
        { id: 3, name: 'Teatru', slug: 'teatru', icon: 'masks', count: 28, color: '#8B5CF6' },
        { id: 4, name: 'Stand-up Comedy', slug: 'stand-up', icon: 'laugh', count: 35, color: '#10B981' },
        { id: 5, name: 'Sport', slug: 'sport', icon: 'trophy', count: 20, color: '#3B82F6' },
        { id: 6, name: 'Conferinte', slug: 'conferinte', icon: 'microphone', count: 15, color: '#6366F1' },
        { id: 7, name: 'Expozitii', slug: 'expozitii', icon: 'image', count: 8, color: '#EC4899' },
        { id: 8, name: 'Workshop-uri', slug: 'workshop', icon: 'wrench', count: 22, color: '#14B8A6' }
    ],

    // ===========================================
    // GENRES (for music events)
    // ===========================================
    genres: [
        { id: 1, name: 'Rock', slug: 'rock', count: 18 },
        { id: 2, name: 'Pop', slug: 'pop', count: 25 },
        { id: 3, name: 'Hip-Hop', slug: 'hip-hop', count: 12 },
        { id: 4, name: 'Electronic', slug: 'electronic', count: 20 },
        { id: 5, name: 'Jazz', slug: 'jazz', count: 8 },
        { id: 6, name: 'Clasic', slug: 'clasic', count: 15 },
        { id: 7, name: 'Folk', slug: 'folk', count: 10 },
        { id: 8, name: 'Metal', slug: 'metal', count: 6 }
    ],

    // ===========================================
    // CITIES
    // ===========================================
    cities: [
        { name: 'Bucuresti', count: 89 },
        { name: 'Cluj-Napoca', count: 45 },
        { name: 'Timisoara', count: 32 },
        { name: 'Iasi', count: 28 },
        { name: 'Brasov', count: 22 },
        { name: 'Constanta', count: 18 },
        { name: 'Sibiu', count: 15 },
        { name: 'Craiova', count: 12 }
    ],

    // ===========================================
    // EVENTS
    // ===========================================
    events: [
        {
            id: 1,
            title: 'Concert Massive Attack',
            slug: 'concert-massive-attack-bucuresti-2025',
            description: 'Legendara trupa britanica Massive Attack revine in Romania pentru un concert extraordinar. O experienta muzicala unica ce combina trip-hop, electronic si rock alternativ.',
            category_id: 1,
            category: { id: 1, name: 'Concerte', slug: 'concerte' },
            genre: { id: 4, name: 'Electronic', slug: 'electronic' },
            venue: { name: 'Arenele Romane', city: 'Bucuresti', address: 'Bd. Basarabia 2' },
            city: 'Bucuresti',
            start_date: '2025-03-15',
            start_time: '20:00',
            end_time: '23:30',
            image: 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=800',
            status: 'published',
            is_featured: true,
            tickets_sold: 2847,
            tickets_total: 5000,
            min_price: 250,
            max_price: 850,
            organizer: { id: 1, name: 'LiveNation Romania', logo: null }
        },
        {
            id: 2,
            title: 'Electric Castle 2025',
            slug: 'electric-castle-2025',
            description: 'Cel mai mare festival de muzica din Romania revine cu o editie de neuitat. 4 zile de muzica, arta si experiante unice la Castelul Banffy.',
            category_id: 2,
            category: { id: 2, name: 'Festivaluri', slug: 'festivaluri' },
            genre: { id: 4, name: 'Electronic', slug: 'electronic' },
            venue: { name: 'Castelul Banffy', city: 'Bontida', address: 'Bontida, Cluj' },
            city: 'Cluj-Napoca',
            start_date: '2025-07-16',
            end_date: '2025-07-20',
            start_time: '14:00',
            image: 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=800',
            status: 'published',
            is_featured: true,
            tickets_sold: 45000,
            tickets_total: 80000,
            min_price: 450,
            max_price: 1200,
            organizer: { id: 2, name: 'Electric Castle', logo: null }
        },
        {
            id: 3,
            title: 'Micutzu - Stand-up Comedy',
            slug: 'micutzu-stand-up-comedy-bucuresti',
            description: 'Unul dintre cei mai apreciati comediani din Romania intr-un show plin de umor si spontaneitate. Pregateste-te sa razi cu lacrimi!',
            category_id: 4,
            category: { id: 4, name: 'Stand-up Comedy', slug: 'stand-up' },
            venue: { name: 'Sala Palatului', city: 'Bucuresti', address: 'Str. Ion Campineanu 28' },
            city: 'Bucuresti',
            start_date: '2025-02-14',
            start_time: '19:00',
            end_time: '21:30',
            image: 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=800',
            status: 'published',
            is_featured: true,
            tickets_sold: 1890,
            tickets_total: 2500,
            min_price: 80,
            max_price: 250,
            organizer: { id: 3, name: 'Stand-Up Romania', logo: null }
        },
        {
            id: 4,
            title: 'O scrisoare pierduta - Teatrul National',
            slug: 'o-scrisoare-pierduta-teatrul-national',
            description: 'Capodopera lui I.L. Caragiale intr-o noua montare spectaculoasa. Regia: Alexandru Darie.',
            category_id: 3,
            category: { id: 3, name: 'Teatru', slug: 'teatru' },
            venue: { name: 'Teatrul National Bucuresti', city: 'Bucuresti', address: 'Bd. Nicolae Balcescu 2' },
            city: 'Bucuresti',
            start_date: '2025-01-25',
            start_time: '19:00',
            end_time: '21:30',
            image: 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=800',
            status: 'published',
            is_featured: false,
            tickets_sold: 320,
            tickets_total: 600,
            min_price: 60,
            max_price: 150,
            organizer: { id: 4, name: 'Teatrul National Bucuresti', logo: null }
        },
        {
            id: 5,
            title: 'Untold Festival 2025',
            slug: 'untold-festival-2025',
            description: 'Cel mai mare festival de muzica electronica din sud-estul Europei. 4 nopti magice in Cluj-Napoca.',
            category_id: 2,
            category: { id: 2, name: 'Festivaluri', slug: 'festivaluri' },
            genre: { id: 4, name: 'Electronic', slug: 'electronic' },
            venue: { name: 'Cluj Arena', city: 'Cluj-Napoca', address: 'Aleea Stadionului 2' },
            city: 'Cluj-Napoca',
            start_date: '2025-08-07',
            end_date: '2025-08-10',
            start_time: '18:00',
            image: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800',
            status: 'published',
            is_featured: true,
            tickets_sold: 78000,
            tickets_total: 100000,
            min_price: 550,
            max_price: 1500,
            organizer: { id: 5, name: 'Untold', logo: null }
        },
        {
            id: 6,
            title: 'Steaua vs Dinamo - Derby',
            slug: 'steaua-dinamo-derby-2025',
            description: 'Cel mai asteptat derby din fotbalul romanesc. Atmosfera incendiara garantata!',
            category_id: 5,
            category: { id: 5, name: 'Sport', slug: 'sport' },
            venue: { name: 'Arena Nationala', city: 'Bucuresti', address: 'Bd. Basarabia 37-39' },
            city: 'Bucuresti',
            start_date: '2025-02-22',
            start_time: '20:30',
            end_time: '22:30',
            image: 'https://images.unsplash.com/photo-1522778119026-d647f0596c20?w=800',
            status: 'published',
            is_featured: false,
            tickets_sold: 35000,
            tickets_total: 55000,
            min_price: 50,
            max_price: 300,
            organizer: { id: 6, name: 'FRF', logo: null }
        },
        {
            id: 7,
            title: 'The Motans - Turneu National',
            slug: 'the-motans-turneu-national-timisoara',
            description: 'The Motans in concert la Timisoara. Cele mai cunoscute hituri live.',
            category_id: 1,
            category: { id: 1, name: 'Concerte', slug: 'concerte' },
            genre: { id: 2, name: 'Pop', slug: 'pop' },
            venue: { name: 'Sala Capitol', city: 'Timisoara', address: 'Bd. C.D. Loga 2' },
            city: 'Timisoara',
            start_date: '2025-03-08',
            start_time: '20:00',
            end_time: '23:00',
            image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800',
            status: 'published',
            is_featured: false,
            tickets_sold: 890,
            tickets_total: 1500,
            min_price: 120,
            max_price: 280,
            organizer: { id: 7, name: 'Global Records', logo: null }
        },
        {
            id: 8,
            title: 'Carla\'s Dreams - Show Aniversar',
            slug: 'carlas-dreams-show-aniversar',
            description: '10 ani de Carla\'s Dreams! Concert aniversar cu surprize speciale.',
            category_id: 1,
            category: { id: 1, name: 'Concerte', slug: 'concerte' },
            genre: { id: 2, name: 'Pop', slug: 'pop' },
            venue: { name: 'Arenele Romane', city: 'Bucuresti', address: 'Bd. Basarabia 2' },
            city: 'Bucuresti',
            start_date: '2025-05-10',
            start_time: '20:00',
            end_time: '23:30',
            image: 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=800',
            status: 'published',
            is_featured: true,
            tickets_sold: 4200,
            tickets_total: 6000,
            min_price: 180,
            max_price: 450,
            organizer: { id: 7, name: 'Global Records', logo: null }
        },
        {
            id: 9,
            title: 'IT Conference 2025',
            slug: 'it-conference-2025-bucuresti',
            description: 'Cea mai mare conferinta de tehnologie din Romania. Speakeri internationali, networking si workshop-uri.',
            category_id: 6,
            category: { id: 6, name: 'Conferinte', slug: 'conferinte' },
            venue: { name: 'Romexpo', city: 'Bucuresti', address: 'Bd. Marasti 65-67' },
            city: 'Bucuresti',
            start_date: '2025-04-15',
            end_date: '2025-04-16',
            start_time: '09:00',
            end_time: '18:00',
            image: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800',
            status: 'published',
            is_featured: false,
            tickets_sold: 1200,
            tickets_total: 2000,
            min_price: 200,
            max_price: 800,
            organizer: { id: 8, name: 'TechEvents', logo: null }
        },
        {
            id: 10,
            title: 'Alternosfera - Turneu 20 de ani',
            slug: 'alternosfera-turneu-20-ani-cluj',
            description: 'Alternosfera sarbatoreste 20 de ani de muzica alternativa romaneasca.',
            category_id: 1,
            category: { id: 1, name: 'Concerte', slug: 'concerte' },
            genre: { id: 1, name: 'Rock', slug: 'rock' },
            venue: { name: 'BT Arena', city: 'Cluj-Napoca', address: 'Aleea Stadionului 2' },
            city: 'Cluj-Napoca',
            start_date: '2025-06-21',
            start_time: '20:00',
            end_time: '23:00',
            image: 'https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=800',
            status: 'published',
            is_featured: false,
            tickets_sold: 2100,
            tickets_total: 3500,
            min_price: 100,
            max_price: 350,
            organizer: { id: 9, name: 'Rock Events', logo: null }
        },
        {
            id: 11,
            title: 'Workshop Fotografie',
            slug: 'workshop-fotografie-brasov',
            description: 'Invata bazele fotografiei de la profesionisti. Workshop interactiv pentru incepatori.',
            category_id: 8,
            category: { id: 8, name: 'Workshop-uri', slug: 'workshop' },
            venue: { name: 'Casa Culturii', city: 'Brasov', address: 'Piata Sfatului 1' },
            city: 'Brasov',
            start_date: '2025-02-08',
            start_time: '10:00',
            end_time: '17:00',
            image: 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=800',
            status: 'published',
            is_featured: false,
            tickets_sold: 18,
            tickets_total: 30,
            min_price: 150,
            max_price: 150,
            organizer: { id: 10, name: 'PhotoArt', logo: null }
        },
        {
            id: 12,
            title: 'Expozitie Salvador Dali',
            slug: 'expozitie-salvador-dali-bucuresti',
            description: 'Expozitie impresionanta cu opere originale ale maestrului suprarealist Salvador Dali.',
            category_id: 7,
            category: { id: 7, name: 'Expozitii', slug: 'expozitii' },
            venue: { name: 'MNAC', city: 'Bucuresti', address: 'Calea Victoriei 49-53' },
            city: 'Bucuresti',
            start_date: '2025-02-01',
            end_date: '2025-04-30',
            start_time: '10:00',
            end_time: '20:00',
            image: 'https://images.unsplash.com/photo-1578926288207-a90a5366759d?w=800',
            status: 'published',
            is_featured: true,
            tickets_sold: 4500,
            tickets_total: 15000,
            min_price: 45,
            max_price: 80,
            organizer: { id: 11, name: 'MNAC', logo: null }
        }
    ],

    // ===========================================
    // TICKET TYPES (per event)
    // ===========================================
    ticketTypes: {
        1: [ // Massive Attack
            { id: 1, name: 'General Admission', price: 250, available: 1500, sold: 1200 },
            { id: 2, name: 'Golden Circle', price: 450, available: 800, sold: 650 },
            { id: 3, name: 'VIP Package', price: 850, available: 200, sold: 180 }
        ],
        2: [ // Electric Castle
            { id: 4, name: 'General Access - 4 zile', price: 450, available: 30000, sold: 22000 },
            { id: 5, name: 'VIP Pass - 4 zile', price: 850, available: 5000, sold: 3500 },
            { id: 6, name: 'Premium VIP - 4 zile', price: 1200, available: 1000, sold: 800 }
        ],
        3: [ // Micutzu
            { id: 7, name: 'Categoria 3', price: 80, available: 800, sold: 750 },
            { id: 8, name: 'Categoria 2', price: 120, available: 600, sold: 550 },
            { id: 9, name: 'Categoria 1', price: 180, available: 400, sold: 380 },
            { id: 10, name: 'VIP', price: 250, available: 200, sold: 190 }
        ],
        4: [ // Teatru
            { id: 11, name: 'Balcon', price: 60, available: 200, sold: 120 },
            { id: 12, name: 'Parter Spate', price: 90, available: 200, sold: 100 },
            { id: 13, name: 'Parter Fata', price: 150, available: 200, sold: 100 }
        ]
    },

    // ===========================================
    // DEMO CUSTOMER ACCOUNT (User Profile)
    // ===========================================
    customer: {
        id: 1,
        email: 'demo@ambilet.ro',
        password: 'demo123', // For testing only
        name: 'Andrei Popescu',
        first_name: 'Andrei',
        last_name: 'Popescu',
        phone: '+40 722 123 456',
        avatar: null,
        initials: 'AP',
        member_since: 'Ianuarie 2023',
        level: 12,
        level_name: 'Rock Star',
        points: 2450,
        next_level_xp: 3000,
        type: 'Rock Enthusiast',
        created_at: '2023-01-15',
        email_verified: true,
        stats: {
            events: 23,
            spent: 4850,
            cities: 7,
            artists: 15
        },
        address: {
            street: 'Str. Exemplu nr. 10',
            city: 'Bucuresti',
            county: 'Bucuresti',
            postal_code: '010101',
            country: 'Romania'
        },
        notifications: {
            orders: true,
            reminders: true,
            promo: false,
            newsletter: true
        }
    },

    // ===========================================
    // TASTE PROFILE (for profile page)
    // ===========================================
    tasteProfile: [
        { name: 'Rock / Metal', emoji: '🎸', percent: 65, gradient: 'from-primary to-primary-dark', events: 15, artists: 'Dirty Shirt, Cargo, Trooper, Iris' },
        { name: 'Pop / Dance', emoji: '🎤', percent: 20, gradient: 'from-accent to-warning', events: 5, artists: 'Festivaluri de vara' },
        { name: 'Teatru / Stand-up', emoji: '🎭', percent: 10, gradient: 'from-success to-teal-500', events: 2, artists: '' },
        { name: 'Clasic / Jazz', emoji: '🎻', percent: 5, gradient: 'from-blue-500 to-indigo-500', events: 1, artists: '' }
    ],

    // ===========================================
    // TOP ARTISTS (for profile page)
    // ===========================================
    topArtists: [
        { name: 'Dirty Shirt', concerts: 5, image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=100' },
        { name: 'Cargo', concerts: 4, image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=100' },
        { name: 'Trooper', concerts: 3, image: 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=100' },
        { name: 'Iris', concerts: 3, image: 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=100' }
    ],

    // ===========================================
    // CITIES VISITED (for profile page)
    // ===========================================
    citiesVisited: [
        { name: 'Bucuresti', count: 12, percent: 52 },
        { name: 'Cluj-Napoca', count: 5, percent: 22 },
        { name: 'Constanta', count: 3, percent: 13 },
        { name: 'Baia Mare', count: 2, percent: 9 },
        { name: 'Timisoara', count: 1, percent: 4 }
    ],

    // ===========================================
    // INSIGHTS (for profile page)
    // ===========================================
    insights: [
        { icon: '📅', label: 'Ziua preferata', value: 'Vineri seara', bg: 'bg-primary/10' },
        { icon: '🎫', label: 'Tip bilet preferat', value: 'VIP (45%)', bg: 'bg-accent/10' },
        { icon: '💰', label: 'Cheltuiala medie', value: '210 lei / eveniment', bg: 'bg-success/10' },
        { icon: '📍', label: 'Oras preferat', value: 'Bucuresti (12 evenimente)', bg: 'bg-blue-500/10' }
    ],

    // ===========================================
    // ACTIVITY DATA (for profile chart)
    // ===========================================
    activityData: [1, 0, 2, 1, 3, 4, 2, 3, 2, 2, 1, 2],

    // ===========================================
    // REWARDS
    // ===========================================
    rewards: [
        { id: 1, emoji: '🎫', title: '10 lei reducere', desc: 'Aplicabil la orice comanda de minim 50 lei', points: 500, status: 'available', gradient: 'from-accent/20 to-warning/20' },
        { id: 2, emoji: '🎁', title: '25 lei reducere', desc: 'Aplicabil la orice comanda de minim 100 lei', points: 1000, status: 'available', gradient: 'from-primary/20 to-accent/20' },
        { id: 3, emoji: '⬆️', title: 'Upgrade VIP', desc: 'Transforma un bilet Standard in VIP', points: 2000, status: 'available', gradient: 'from-purple-500/20 to-pink-500/20' },
        { id: 4, emoji: '🎤', title: 'Meet & Greet', desc: 'Acces la meet & greet cu artistii', points: 5000, status: 'locked', lock_reason: 'NIVEL 15+', gradient: 'from-blue-500/20 to-cyan-500/20' },
        { id: 5, emoji: '🎫', title: 'Bilet gratuit', desc: 'Un bilet Standard gratuit la orice eveniment', points: 4000, status: 'insufficient', missing: 1550, gradient: 'from-yellow-400/20 to-orange-500/20' },
        { id: 6, emoji: '👑', title: 'Gold Member', desc: 'Status Gold pentru 1 an - acces prioritar', points: 10000, status: 'exclusive', missing: 7550, gradient: 'from-yellow-400 to-orange-500' }
    ],

    // ===========================================
    // BADGES
    // ===========================================
    badges: {
        unlocked: [
            { id: 1, emoji: '🎸', name: 'Rock Veteran', desc: '10+ concerte rock', xp: 200, gradient: 'from-yellow-400 to-orange-500' },
            { id: 2, emoji: '🌟', name: 'Early Bird', desc: '5+ bilete early bird', xp: 150, gradient: 'from-purple-400 to-pink-500' },
            { id: 3, emoji: '💎', name: 'VIP Lover', desc: '3+ bilete VIP', xp: 300, gradient: 'from-green-400 to-emerald-500' },
            { id: 4, emoji: '🎪', name: 'Festival Fan', desc: '3+ festivaluri', xp: 250, gradient: 'from-blue-400 to-cyan-500' },
            { id: 5, emoji: '❤️', name: 'Loyal Fan', desc: '1 an pe platforma', xp: 500, gradient: 'from-red-400 to-pink-500' },
            { id: 6, emoji: '🎭', name: 'Eclectic', desc: '5+ genuri diferite', xp: 200, gradient: 'from-indigo-400 to-purple-500' },
            { id: 7, emoji: '⭐', name: 'First Timer', desc: 'Primul bilet', xp: 50, gradient: 'from-amber-400 to-yellow-500' }
        ],
        locked: [
            { id: 8, emoji: '🏆', name: 'Champion', desc: '50+ evenimente', missing: '27 lipsa' },
            { id: 9, emoji: '🌍', name: 'Explorer', desc: '10+ orase diferite', missing: '6 lipsa' },
            { id: 10, emoji: '👥', name: 'Social', desc: 'Invita 5 prieteni', missing: '5 lipsa' }
        ]
    },

    // ===========================================
    // POINTS HISTORY
    // ===========================================
    pointsHistory: [
        { id: 1, type: 'earned', icon: 'plus', desc: 'Achizitie bilet - Cargo Live', date: '20 Dec 2024, 10:12', points: 120 },
        { id: 2, type: 'badge', icon: 'badge', desc: 'Badge obtinut - Rock Veteran', date: '18 Dec 2024, 15:30', points: 200 },
        { id: 3, type: 'spent', icon: 'minus', desc: 'Reducere folosita - 10 lei', date: '15 Dec 2024, 09:45', points: -500 },
        { id: 4, type: 'earned', icon: 'plus', desc: 'Achizitie bilet - Halloween Rock Night', date: '28 Oct 2024, 18:45', points: 160 },
        { id: 5, type: 'checkin', icon: 'check', desc: 'Check-in efectuat - Halloween Rock Night', date: '31 Oct 2024, 19:15', points: 50 }
    ],

    // ===========================================
    // LEVELS SYSTEM
    // ===========================================
    levels: [
        { range: '1-5', name: 'Newbie', emoji: '🎵', xp: '0 - 500', rewards: '', status: 'completed', gradient: 'from-gray-300 to-gray-400' },
        { range: '6-10', name: 'Music Lover', emoji: '🎶', xp: '500 - 1,500', rewards: '10 lei reducere', status: 'completed', gradient: 'from-blue-400 to-cyan-500' },
        { range: '11-15', name: 'Rock Star', emoji: '🎸', xp: '1,500 - 4,000', rewards: 'Upgrade VIP, 25 lei reducere', status: 'current', gradient: 'from-primary to-accent' },
        { range: '16-20', name: 'Legend', emoji: '👑', xp: '4,000 - 8,000', rewards: 'Meet & Greet, Bilet gratuit', status: 'locked', gradient: 'from-purple-400 to-pink-500' },
        { range: '21+', name: 'Hall of Fame', emoji: '🏆', xp: '8,000+', rewards: 'Gold Member, Backstage Access', status: 'locked', gradient: 'from-yellow-400 to-orange-500' }
    ],

    // ===========================================
    // CUSTOMER ORDERS (for orders page)
    // ===========================================
    customerOrders: [
        {
            id: 1,
            reference: 'TIX-78453',
            status: 'confirmed',
            event: { title: 'Mos Craciun e Rocker', image: 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=200' },
            created_at: '2024-12-22T14:35:00',
            items: [{ name: 'VIP', quantity: 2, price: 150 }],
            subtotal: 300,
            discount: 30,
            discount_code: 'ROCK2024',
            total: 270,
            points_earned: 60,
            payment_method: 'Card •••• 4532',
            payment_date: '2024-12-22T14:36:00',
            transaction_id: 'TRX-8F4A2B'
        },
        {
            id: 2,
            reference: 'TIX-78501',
            status: 'confirmed',
            event: { title: 'Cargo Live', image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=200' },
            created_at: '2024-12-20T10:12:00',
            items: [{ name: 'Standard', quantity: 1, price: 80 }],
            subtotal: 80,
            discount: 0,
            total: 80,
            points_earned: 16,
            payment_method: 'Apple Pay',
            payment_date: '2024-12-20T10:13:00'
        },
        {
            id: 3,
            reference: 'TIX-77234',
            status: 'completed',
            event: { title: 'Halloween Rock Night', image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=200' },
            created_at: '2024-10-28T18:45:00',
            items: [{ name: 'Standard', quantity: 2, price: 80 }],
            subtotal: 160,
            total: 160,
            points_earned: 32,
            checked_in: true
        },
        {
            id: 4,
            reference: 'TIX-76890',
            status: 'refunded',
            event: { title: 'Concert Anulat - Festival X', image: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=200' },
            created_at: '2024-09-15T09:30:00',
            items: [{ name: 'Premium', quantity: 1, price: 200 }],
            subtotal: 200,
            total: 200,
            refunded_amount: 200,
            refund_date: '2024-09-16',
            refund_reason: 'Eveniment anulat de organizator'
        },
        {
            id: 5,
            reference: 'TIX-75123',
            status: 'completed',
            event: { title: 'Trooper - 30 Years Tour', image: 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=200' },
            created_at: '2024-09-10T12:20:00',
            items: [{ name: 'VIP', quantity: 1, price: 150 }],
            subtotal: 150,
            total: 150,
            points_earned: 30
        }
    ],

    // ===========================================
    // CUSTOMER TICKETS (for tickets page)
    // ===========================================
    customerTickets: {
        upcoming: [
            {
                id: 1,
                code: 'TIX-78453-VIP-001',
                event: {
                    title: 'Mos Craciun e Rocker',
                    subtitle: 'Concert Dirty Shirt & Friends',
                    date: '2024-12-27',
                    time: '19:00',
                    doors: '18:00',
                    venue: 'Grand Gala, Baia Mare',
                    image: 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=300',
                    genre: 'Rock'
                },
                ticket_type: 'VIP',
                price: 150,
                quantity: 2,
                days_until: 3,
                status: 'valid',
                tickets: [
                    { code: 'TIX-78453-VIP-001', type: 'VIP', status: 'valid' },
                    { code: 'TIX-78453-VIP-002', type: 'VIP', status: 'valid' }
                ]
            },
            {
                id: 2,
                code: 'TIX-78501-STD-001',
                event: {
                    title: 'Cargo Live',
                    subtitle: 'Concert aniversar 40 de ani',
                    date: '2025-01-15',
                    time: '20:00',
                    doors: '19:00',
                    venue: 'Arenele Romane, Bucuresti',
                    image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=300',
                    genre: 'Rock'
                },
                ticket_type: 'Standard',
                price: 80,
                quantity: 1,
                days_until: 22,
                status: 'valid',
                tickets: [
                    { code: 'TIX-78501-STD-001', type: 'Standard', status: 'valid' }
                ]
            }
        ],
        past: [
            { id: 3, event: { title: 'Halloween Rock Night', date: '2024-10-31', venue: 'Club Quantic, Bucuresti', image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=200' }, ticket_type: 'Standard', quantity: 2, checked_in: true },
            { id: 4, event: { title: 'Trooper - 30 Years Tour', date: '2024-09-15', venue: 'Sala Palatului', image: 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=200' }, ticket_type: 'VIP', quantity: 1, checked_in: true }
        ]
    },

    // ===========================================
    // CUSTOMER WATCHLIST (event IDs only - for quick lookup)
    // ===========================================
    customerWatchlist: [
        { event_id: 2, added_at: '2024-12-15' }, // Electric Castle
        { event_id: 8, added_at: '2024-12-10' }, // Carla's Dreams
        { event_id: 10, added_at: '2024-12-05' } // Alternosfera
    ],

    // ===========================================
    // WATCHLIST EVENTS (detailed for watchlist page)
    // ===========================================
    watchlistEvents: [
        { id: 1, title: 'Trooper Unplugged', image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400', date: '22 Ian 2025', venue: 'Hard Rock Cafe, Bucuresti', price: 80, genre: 'Rock', badge: '85% Sold', badge_color: 'bg-warning' },
        { id: 2, title: 'Dirty Shirt - Tour 2025', image: 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400', date: '5 Feb 2025', venue: 'Sala Palatului, Bucuresti', price: 120, genre: 'Metal', badge: 'NOU', badge_color: 'bg-success' },
        { id: 3, title: 'Iris - Romantic Tour', image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400', date: '15 Feb 2025', venue: 'Teatrul National, Cluj', price: 95, genre: 'Rock', badge: null },
        { id: 4, title: 'Rock la Mures 2025', image: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400', date: 'Iulie 2025', venue: 'Targu Mures', price: null, genre: 'Festival', badge: 'IN CURAND', badge_color: 'bg-blue-500' },
        { id: 5, title: 'Phoenix - Turneu National', image: 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=400', date: '28 Feb 2025', venue: 'Filarmonica, Timisoara', price: 150, genre: 'Rock', badge: null },
        { id: 6, title: 'Vita de Vie Acoustic', image: 'https://images.unsplash.com/photo-1506157786151-b8491531f063?w=400', date: '10 Ian 2025', venue: 'Control Club, Bucuresti', price: 100, genre: 'Rock', badge: 'SOLD OUT', badge_color: 'bg-error', sold_out: true }
    ],

    // ===========================================
    // WATCHLIST ARTISTS
    // ===========================================
    watchlistArtists: [
        { name: 'Dirty Shirt', image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=200', genre: 'Metal / Folk', events: 3 },
        { name: 'Trooper', image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=200', genre: 'Rock', events: 2 },
        { name: 'Cargo', image: 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=200', genre: 'Rock', events: 1 }
    ],

    // ===========================================
    // WATCHLIST VENUES
    // ===========================================
    watchlistVenues: [
        { name: 'Hard Rock Cafe', image: 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=400', city: 'Bucuresti', events: 5 },
        { name: 'Sala Palatului', image: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400', city: 'Bucuresti', events: 3 },
        { name: 'Arenele Romane', image: 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400', city: 'Bucuresti', events: 2 }
    ],

    // ===========================================
    // DEMO ORGANIZER ACCOUNT
    // ===========================================
    organizer: {
        id: 1,
        email: 'organizator@ambilet.ro',
        password: 'organizator123', // For testing only
        name: 'EventPro SRL',
        contact_name: 'Maria Ionescu',
        phone: '+40722123456',
        website: 'https://eventpro.ro',
        description: 'Organizator profesionist de evenimente din Bucuresti. Concerte, festivaluri si evenimente corporate.',
        logo: null,
        company: {
            name: 'EVENTPRO SRL',
            cui: 'RO12345678',
            reg_number: 'J40/1234/2020',
            address: 'Str. Exemplu nr. 10',
            city: 'Bucuresti',
            county: 'Bucuresti',
            zip: '010101',
            vat_payer: true
        },
        bank_accounts: [
            { id: 1, bank: 'ING Bank', iban: 'RO49INGB0000999900123456', holder: 'EVENTPRO SRL', is_primary: true },
            { id: 2, bank: 'BRD', iban: 'RO49BRDE0000999900654321', holder: 'EVENTPRO SRL', is_primary: false }
        ],
        commission_rate: 0.02, // 2%
        verified: true,
        created_at: '2023-06-01'
    },

    // ===========================================
    // ORGANIZER EVENTS
    // ===========================================
    organizerEvents: [
        {
            id: 101,
            title: 'Concert Demo Rock',
            slug: 'concert-demo-rock-2025',
            category: { id: 1, name: 'Concerte' },
            venue: { name: 'Club Control', city: 'Bucuresti' },
            start_date: '2025-04-20',
            start_time: '21:00',
            status: 'published',
            tickets_sold: 245,
            tickets_total: 400,
            revenue: 36750,
            image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400'
        },
        {
            id: 102,
            title: 'Stand-up Night',
            slug: 'stand-up-night-demo',
            category: { id: 4, name: 'Stand-up Comedy' },
            venue: { name: 'The Comedy Club', city: 'Bucuresti' },
            start_date: '2025-02-28',
            start_time: '20:00',
            status: 'published',
            tickets_sold: 89,
            tickets_total: 150,
            revenue: 8900,
            image: 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=400'
        },
        {
            id: 103,
            title: 'Workshop Marketing Digital',
            slug: 'workshop-marketing-digital',
            category: { id: 8, name: 'Workshop-uri' },
            venue: { name: 'Hub IT', city: 'Bucuresti' },
            start_date: '2025-03-15',
            start_time: '10:00',
            status: 'draft',
            tickets_sold: 0,
            tickets_total: 50,
            revenue: 0,
            image: 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=400'
        }
    ],

    // ===========================================
    // ORGANIZER SALES DATA
    // ===========================================
    organizerSales: {
        today: { tickets: 12, revenue: 1840 },
        week: { tickets: 78, revenue: 11560 },
        month: { tickets: 312, revenue: 45890 },
        total: { tickets: 2456, revenue: 367850 },
        chart_data: {
            labels: ['Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sam', 'Dum'],
            values: [1200, 1900, 1400, 2100, 2800, 3200, 1560]
        }
    },

    // ===========================================
    // ORGANIZER PARTICIPANTS
    // ===========================================
    organizerParticipants: [
        { id: 1, name: 'Ion Popescu', email: 'ion@example.com', ticket: 'VIP', event_id: 101, checked_in: true, check_in_time: '2025-04-20 20:45' },
        { id: 2, name: 'Maria Ionescu', email: 'maria@example.com', ticket: 'Standard', event_id: 101, checked_in: true, check_in_time: '2025-04-20 20:30' },
        { id: 3, name: 'Andrei Popa', email: 'andrei@example.com', ticket: 'Standard', event_id: 101, checked_in: false, check_in_time: null },
        { id: 4, name: 'Elena Dinu', email: 'elena@example.com', ticket: 'VIP', event_id: 101, checked_in: true, check_in_time: '2025-04-20 21:00' },
        { id: 5, name: 'Mihai Stan', email: 'mihai@example.com', ticket: 'Standard', event_id: 101, checked_in: false, check_in_time: null }
    ],

    // ===========================================
    // ORGANIZER FINANCE
    // ===========================================
    organizerFinance: {
        balance: 45890.50,
        pending: 12500.00,
        total_paid: 320000.00,
        next_payout: '2025-01-31',
        transactions: [
            { id: 1, type: 'sale', description: 'Vanzari Concert Demo Rock', amount: 5200, date: '2025-01-26' },
            { id: 2, type: 'sale', description: 'Vanzari Stand-up Night', amount: 1800, date: '2025-01-25' },
            { id: 3, type: 'payout', description: 'Transfer bancar', amount: -25000, date: '2025-01-20' },
            { id: 4, type: 'sale', description: 'Vanzari Concert Demo Rock', amount: 8400, date: '2025-01-18' },
            { id: 5, type: 'commission', description: 'Comision platforma (2%)', amount: -680, date: '2025-01-15' }
        ]
    },

    // ===========================================
    // ORGANIZER PROMO CODES
    // ===========================================
    organizerPromoCodes: [
        { id: 1, code: 'EARLY20', discount_type: 'percent', discount_value: 20, uses: 45, max_uses: 100, valid_until: '2025-02-28', active: true },
        { id: 2, code: 'VIP50', discount_type: 'fixed', discount_value: 50, uses: 12, max_uses: 50, valid_until: '2025-03-31', active: true },
        { id: 3, code: 'FRIENDS10', discount_type: 'percent', discount_value: 10, uses: 89, max_uses: null, valid_until: null, active: true },
        { id: 4, code: 'LAUNCH', discount_type: 'percent', discount_value: 30, uses: 100, max_uses: 100, valid_until: '2024-12-31', active: false }
    ]
};

// ===========================================
// DEMO API MOCK FUNCTIONS
// ===========================================

/**
 * Initialize demo mode - replaces API calls with mock data
 */
function initDemoMode() {
    if (!window.AMBILET_CONFIG?.DEMO_MODE) return;

    console.log('%c[DEMO MODE] Using mock data', 'color: #E67E22; font-weight: bold;');

    // Store demo user sessions
    if (!localStorage.getItem('demo_customer_logged_in')) {
        localStorage.setItem('demo_customer_logged_in', 'false');
    }
    if (!localStorage.getItem('demo_organizer_logged_in')) {
        localStorage.setItem('demo_organizer_logged_in', 'false');
    }
}

/**
 * Demo login for customer
 */
function demoCustomerLogin(email, password) {
    if (email === DEMO_DATA.customer.email && password === DEMO_DATA.customer.password) {
        localStorage.setItem('demo_customer_logged_in', 'true');
        localStorage.setItem('ambilet_customer_token', 'demo_token_customer');
        localStorage.setItem('ambilet_customer', JSON.stringify(DEMO_DATA.customer));
        return { success: true, data: DEMO_DATA.customer };
    }
    return { success: false, message: 'Email sau parola incorecta' };
}

/**
 * Demo login for organizer
 */
function demoOrganizerLogin(email, password) {
    if (email === DEMO_DATA.organizer.email && password === DEMO_DATA.organizer.password) {
        localStorage.setItem('demo_organizer_logged_in', 'true');
        localStorage.setItem('ambilet_organizer_token', 'demo_token_organizer');
        localStorage.setItem('ambilet_organizer', JSON.stringify(DEMO_DATA.organizer));
        return { success: true, data: DEMO_DATA.organizer };
    }
    return { success: false, message: 'Email sau parola incorecta' };
}

/**
 * Get events with optional filters
 */
function demoGetEvents(filters = {}) {
    let events = [...DEMO_DATA.events];

    if (filters.category) {
        events = events.filter(e => e.category.slug === filters.category);
    }
    if (filters.city) {
        events = events.filter(e => e.city === filters.city);
    }
    if (filters.featured) {
        events = events.filter(e => e.is_featured);
    }
    if (filters.search) {
        const q = filters.search.toLowerCase();
        events = events.filter(e =>
            e.title.toLowerCase().includes(q) ||
            e.description.toLowerCase().includes(q)
        );
    }

    return { success: true, data: events };
}

/**
 * Get single event by slug
 */
function demoGetEvent(slug) {
    const event = DEMO_DATA.events.find(e => e.slug === slug);
    if (event) {
        event.ticket_types = DEMO_DATA.ticketTypes[event.id] || [];
        return { success: true, data: event };
    }
    return { success: false, message: 'Eveniment negasit' };
}

// Initialize on load
document.addEventListener('DOMContentLoaded', initDemoMode);

// Export for use
window.DEMO_DATA = DEMO_DATA;
window.demoCustomerLogin = demoCustomerLogin;
window.demoOrganizerLogin = demoOrganizerLogin;
window.demoGetEvents = demoGetEvents;
window.demoGetEvent = demoGetEvent;
