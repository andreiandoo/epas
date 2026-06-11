<?php
/**
 * User Settings Page
 * Account settings and preference profiling for AI recommendations
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-user.php';
$currentUser = $demoData['user'];

// Extended user profile (would come from database)
$userProfile = array_merge($currentUser, [
    'phone' => '+40 721 234 567',
    'birthDate' => '1992-05-15',
    'gender' => 'male',
    'city' => 'bucuresti',
    // Preferences
    'preferredCategories' => ['concerte', 'stand-up', 'festivaluri'],
    'preferredGenres' => ['rock', 'electronic', 'pop'],
    'preferredArtistTypes' => ['international', 'dj'],
    'preferredVenues' => ['arena-nationala', 'beraria-h'],
    'preferredTicketTypes' => ['standard', 'vip'],
    'preferredDays' => ['vineri', 'sambata'],
    'preferredTimeOfMonth' => ['sfarsit'], // inceput, mijloc, sfarsit
    'preferredSeasons' => ['vara', 'primavara'],
    'preferredTransport' => 'masina', // masina, transport-public, uber
    'followedArtists' => ['coldplay', 'dua-lipa', 'carlas-dreams'],
    'followedVenues' => ['arena-nationala', 'sala-palatului'],
    'profileCompleteness' => 72,
]);

// Categories
$categories = [
    'concerte' => ['name' => 'Concerte', 'icon' => '🎸'],
    'festivaluri' => ['name' => 'Festivaluri', 'icon' => '🎪'],
    'stand-up' => ['name' => 'Stand-up', 'icon' => '😂'],
    'teatru' => ['name' => 'Teatru', 'icon' => '🎭'],
    'sport' => ['name' => 'Sport', 'icon' => '⚽'],
    'arta-muzee' => ['name' => 'Artă & Muzee', 'icon' => '🎨'],
    'familie' => ['name' => 'Familie', 'icon' => '👨‍👩‍👧'],
    'business' => ['name' => 'Business', 'icon' => '💼'],
];

// Music genres
$genres = [
    'rock' => 'Rock', 'pop' => 'Pop', 'electronic' => 'Electronic/EDM',
    'hip-hop' => 'Hip-Hop/Rap', 'jazz' => 'Jazz', 'classical' => 'Clasică',
    'folk' => 'Folk/Etno', 'alternative' => 'Alternative', 'metal' => 'Metal',
    'reggae' => 'Reggae', 'rnb' => 'R&B/Soul', 'latino' => 'Latino',
];

// Artist types
$artistTypes = [
    'international' => 'Artiști internaționali',
    'romanian' => 'Artiști români',
    'band' => 'Trupe/Formații',
    'solo' => 'Artiști solo',
    'dj' => 'DJ/Producători',
    'emerging' => 'Artiști emergenti',
];

// Ticket types
$ticketTypes = [
    'early-bird' => ['name' => 'Early Bird', 'desc' => 'Primele bilete la preț redus'],
    'standard' => ['name' => 'Standard', 'desc' => 'Acces general'],
    'vip' => ['name' => 'VIP', 'desc' => 'Experiență premium'],
    'meet-greet' => ['name' => 'Meet & Greet', 'desc' => 'Întâlnire cu artiștii'],
];

// Days of week
$daysOfWeek = [
    'luni' => 'Luni', 'marti' => 'Marți', 'miercuri' => 'Miercuri',
    'joi' => 'Joi', 'vineri' => 'Vineri', 'sambata' => 'Sâmbătă', 'duminica' => 'Duminică',
];

// Time of month
$timesOfMonth = [
    'inceput' => '1-10 (Început de lună)',
    'mijloc' => '11-20 (Mijlocul lunii)',
    'sfarsit' => '21-31 (Sfârșit de lună)',
];

// Seasons
$seasons = [
    'primavara' => 'Primăvară (Mar-Mai)',
    'vara' => 'Vară (Iun-Aug)',
    'toamna' => 'Toamnă (Sep-Nov)',
    'iarna' => 'Iarnă (Dec-Feb)',
];

// Transport options
$transportOptions = [
    'masina' => ['name' => 'Mașină personală', 'icon' => '🚗'],
    'transport-public' => ['name' => 'Transport public', 'icon' => '🚇'],
    'uber' => ['name' => 'Uber/Bolt/Taxi', 'icon' => '🚕'],
    'bicicleta' => ['name' => 'Bicicletă/Trotineta', 'icon' => '🚲'],
    'pe-jos' => ['name' => 'Pe jos', 'icon' => '🚶'],
];

// Cities
$cities = [
    'bucuresti' => 'București', 'cluj-napoca' => 'Cluj-Napoca',
    'timisoara' => 'Timișoara', 'iasi' => 'Iași',
    'constanta' => 'Constanța', 'brasov' => 'Brașov',
    'sibiu' => 'Sibiu', 'craiova' => 'Craiova',
];

// Current page for sidebar
$currentPage = 'settings';

// Page config for head
$pageTitle = 'Setări cont';
$pageDescription = 'Gestionează setările contului și preferințele tale TICS.ro';

// Include user head
include __DIR__ . '/../includes/user-head.php';
?>
<body class="bg-gray-50 min-h-screen">
    <?php
    // Set logged in state for header
    $isLoggedIn = true;
    $loggedInUser = $currentUser;
    include __DIR__ . '/../includes/header.php';
    ?>

    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar -->
            <?php include __DIR__ . '/../includes/user-sidebar.php'; ?>

            <!-- Main Content -->
            <main class="flex-1 min-w-0">
                <!-- Page Header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Setări cont</h1>
                    <p class="text-gray-500 mt-1">Gestionează informațiile contului și preferințele tale</p>
                </div>

                <!-- Profile Completeness Banner -->
                <div class="bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 rounded-2xl p-5 mb-6 border border-indigo-100">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Profil completat <?= $userProfile['profileCompleteness'] ?>%</h3>
                                <p class="text-sm text-gray-500">Completează profilul pentru recomandări mai bune</p>
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-indigo-600"><?= $userProfile['profileCompleteness'] ?>%</span>
                    </div>
                    <div class="w-full bg-white rounded-full h-2.5">
                        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 h-2.5 rounded-full transition-all" style="width: <?= $userProfile['profileCompleteness'] ?>%"></div>
                    </div>
                </div>

                <!-- Settings Tabs -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <!-- Tab Navigation -->
                    <div class="border-b border-gray-200">
                        <nav class="flex overflow-x-auto no-scrollbar" id="settingsTabs">
                            <button class="tab-btn active flex-shrink-0 px-6 py-4 text-sm font-medium border-b-2 border-indigo-600 text-indigo-600" data-tab="account">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    Cont
                                </span>
                            </button>
                            <button class="tab-btn flex-shrink-0 px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700" data-tab="preferences">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                    Preferințe
                                    <span class="w-2 h-2 bg-orange-400 rounded-full"></span>
                                </span>
                            </button>
                            <button class="tab-btn flex-shrink-0 px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700" data-tab="notifications">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                    Notificări
                                </span>
                            </button>
                            <button class="tab-btn flex-shrink-0 px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700" data-tab="privacy">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Securitate
                                </span>
                            </button>
                            <button class="tab-btn flex-shrink-0 px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700" data-tab="gdpr">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    GDPR
                                </span>
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-6">
                        <!-- Account Tab -->
                        <div id="tab-account" class="tab-content">
                            <form class="space-y-6">
                                <!-- Avatar Section -->
                                <div class="flex items-center gap-6 pb-6 border-b border-gray-100">
                                    <div class="relative">
                                        <img src="<?= htmlspecialchars($userProfile['avatarLarge']) ?>" class="w-24 h-24 rounded-2xl object-cover" alt="Avatar">
                                        <button type="button" class="absolute -bottom-2 -right-2 w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center hover:bg-indigo-700 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        </button>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Fotografie de profil</h3>
                                        <p class="text-sm text-gray-500">JPG sau PNG. Max 2MB</p>
                                    </div>
                                </div>

                                <!-- Personal Info -->
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informații personale</h3>
                                    <div class="grid sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Prenume</label>
                                            <input type="text" value="<?= htmlspecialchars($userProfile['firstName']) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Nume</label>
                                            <input type="text" value="<?= htmlspecialchars($userProfile['lastName']) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                            <input type="email" value="<?= htmlspecialchars($userProfile['email']) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                                            <input type="tel" value="<?= htmlspecialchars($userProfile['phone']) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Data nașterii</label>
                                            <input type="date" value="<?= htmlspecialchars($userProfile['birthDate']) ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Gen</label>
                                            <select class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                                <option value="">Prefer să nu spun</option>
                                                <option value="male" <?= $userProfile['gender'] === 'male' ? 'selected' : '' ?>>Masculin</option>
                                                <option value="female" <?= $userProfile['gender'] === 'female' ? 'selected' : '' ?>>Feminin</option>
                                                <option value="other" <?= $userProfile['gender'] === 'other' ? 'selected' : '' ?>>Altul</option>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Oraș</label>
                                            <select class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                                <option value="">Selectează orașul</option>
                                                <?php foreach ($cities as $slug => $name): ?>
                                                <option value="<?= $slug ?>" <?= $userProfile['city'] === $slug ? 'selected' : '' ?>><?= $name ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-4 border-t border-gray-100">
                                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                                        Salvează modificările
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Preferences Tab -->
                        <div id="tab-preferences" class="tab-content hidden">
                            <div class="space-y-8">
                                <!-- AI Intro -->
                                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl p-4 border border-indigo-100">
                                    <div class="flex items-start gap-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">Ajută-ne să te cunoaștem mai bine</h3>
                                            <p class="text-sm text-gray-600">Cu cât completezi mai multe preferințe, cu atât recomandările noastre vor fi mai personalizate pentru tine.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Categories -->
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <h3 class="font-semibold text-gray-900">Ce tip de evenimente îți plac?</h3>
                                            <p class="text-sm text-gray-500">Selectează toate categoriile care te interesează</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                        <?php foreach ($categories as $slug => $cat): ?>
                                        <label class="pref-chip cursor-pointer">
                                            <input type="checkbox" class="hidden" name="categories[]" value="<?= $slug ?>" <?= in_array($slug, $userProfile['preferredCategories']) ? 'checked' : '' ?>>
                                            <div class="flex items-center gap-2 px-4 py-3 border-2 rounded-xl transition-all <?= in_array($slug, $userProfile['preferredCategories']) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' ?>">
                                                <span class="text-xl"><?= $cat['icon'] ?></span>
                                                <span class="text-sm font-medium <?= in_array($slug, $userProfile['preferredCategories']) ? 'text-indigo-700' : 'text-gray-700' ?>"><?= $cat['name'] ?></span>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Music Genres -->
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <h3 class="font-semibold text-gray-900">Genuri muzicale preferate</h3>
                                            <p class="text-sm text-gray-500">Selectează genurile care îți plac</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($genres as $slug => $name): ?>
                                        <label class="pref-chip cursor-pointer">
                                            <input type="checkbox" class="hidden" name="genres[]" value="<?= $slug ?>" <?= in_array($slug, $userProfile['preferredGenres']) ? 'checked' : '' ?>>
                                            <div class="px-4 py-2 border-2 rounded-full transition-all <?= in_array($slug, $userProfile['preferredGenres']) ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' ?> text-sm font-medium">
                                                <?= $name ?>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Artist Types -->
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <h3 class="font-semibold text-gray-900">Ce tip de artiști preferi?</h3>
                                            <p class="text-sm text-gray-500">Selectează ce te interesează mai mult</p>
                                        </div>
                                    </div>
                                    <div class="grid sm:grid-cols-3 gap-3">
                                        <?php foreach ($artistTypes as $slug => $name): ?>
                                        <label class="pref-chip cursor-pointer">
                                            <input type="checkbox" class="hidden" name="artist_types[]" value="<?= $slug ?>" <?= in_array($slug, $userProfile['preferredArtistTypes']) ? 'checked' : '' ?>>
                                            <div class="px-4 py-3 border-2 rounded-xl text-center transition-all <?= in_array($slug, $userProfile['preferredArtistTypes']) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' ?>">
                                                <span class="text-sm font-medium <?= in_array($slug, $userProfile['preferredArtistTypes']) ? 'text-indigo-700' : 'text-gray-700' ?>"><?= $name ?></span>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Ticket Preferences -->
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <h3 class="font-semibold text-gray-900">Ce tip de bilete preferi?</h3>
                                            <p class="text-sm text-gray-500">Te vom notifica când apar oferte pentru acestea</p>
                                        </div>
                                    </div>
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <?php foreach ($ticketTypes as $slug => $ticket): ?>
                                        <label class="pref-chip cursor-pointer">
                                            <input type="checkbox" class="hidden" name="ticket_types[]" value="<?= $slug ?>" <?= in_array($slug, $userProfile['preferredTicketTypes']) ? 'checked' : '' ?>>
                                            <div class="p-4 border-2 rounded-xl transition-all <?= in_array($slug, $userProfile['preferredTicketTypes']) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' ?>">
                                                <p class="font-semibold <?= in_array($slug, $userProfile['preferredTicketTypes']) ? 'text-indigo-700' : 'text-gray-900' ?>"><?= $ticket['name'] ?></p>
                                                <p class="text-sm text-gray-500"><?= $ticket['desc'] ?></p>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Timing Preferences -->
                                <div class="grid sm:grid-cols-2 gap-6">
                                    <!-- Days of Week -->
                                    <div>
                                        <div class="mb-3">
                                            <h3 class="font-semibold text-gray-900">Zile preferate</h3>
                                            <p class="text-sm text-gray-500">Când mergi de obicei la evenimente?</p>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach ($daysOfWeek as $slug => $name): ?>
                                            <label class="pref-chip cursor-pointer">
                                                <input type="checkbox" class="hidden" name="days[]" value="<?= $slug ?>" <?= in_array($slug, $userProfile['preferredDays']) ? 'checked' : '' ?>>
                                                <div class="px-3 py-1.5 border-2 rounded-lg transition-all <?= in_array($slug, $userProfile['preferredDays']) ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' ?> text-sm font-medium">
                                                    <?= $name ?>
                                                </div>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Season Preference -->
                                    <div>
                                        <div class="mb-3">
                                            <h3 class="font-semibold text-gray-900">Anotimpuri preferate</h3>
                                            <p class="text-sm text-gray-500">Când îți place să mergi la evenimente?</p>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach ($seasons as $slug => $name): ?>
                                            <label class="pref-chip cursor-pointer">
                                                <input type="checkbox" class="hidden" name="seasons[]" value="<?= $slug ?>" <?= in_array($slug, $userProfile['preferredSeasons']) ? 'checked' : '' ?>>
                                                <div class="px-3 py-1.5 border-2 rounded-lg transition-all <?= in_array($slug, $userProfile['preferredSeasons']) ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' ?> text-sm font-medium">
                                                    <?= explode(' ', $name)[0] ?>
                                                </div>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Transport Preference -->
                                <div>
                                    <div class="mb-3">
                                        <h3 class="font-semibold text-gray-900">Cum ajungi la evenimente?</h3>
                                        <p class="text-sm text-gray-500">Vom afișa informații relevante despre transport</p>
                                    </div>
                                    <div class="flex flex-wrap gap-3">
                                        <?php foreach ($transportOptions as $slug => $transport): ?>
                                        <label class="pref-radio cursor-pointer">
                                            <input type="radio" class="hidden" name="transport" value="<?= $slug ?>" <?= $userProfile['preferredTransport'] === $slug ? 'checked' : '' ?>>
                                            <div class="flex items-center gap-2 px-4 py-2.5 border-2 rounded-xl transition-all <?= $userProfile['preferredTransport'] === $slug ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' ?>">
                                                <span class="text-xl"><?= $transport['icon'] ?></span>
                                                <span class="text-sm font-medium <?= $userProfile['preferredTransport'] === $slug ? 'text-indigo-700' : 'text-gray-700' ?>"><?= $transport['name'] ?></span>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-4 border-t border-gray-100">
                                    <button type="button" onclick="savePreferences()" class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                                        Salvează preferințele
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Notifications Tab -->
                        <div id="tab-notifications" class="tab-content hidden">
                            <div class="space-y-6">
                                <!-- Email Notifications -->
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Notificări Email</h3>
                                    <div class="space-y-4">
                                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                                            <div>
                                                <p class="font-medium text-gray-900">Recomandări personalizate</p>
                                                <p class="text-sm text-gray-500">Primești email-uri cu evenimente recomandate pentru tine</p>
                                            </div>
                                            <div class="relative">
                                                <input type="checkbox" class="sr-only peer" checked>
                                                <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                                            </div>
                                        </label>
                                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                                            <div>
                                                <p class="font-medium text-gray-900">Artiști urmăriți</p>
                                                <p class="text-sm text-gray-500">Notificări când artiștii tăi preferați au evenimente noi</p>
                                            </div>
                                            <div class="relative">
                                                <input type="checkbox" class="sr-only peer" checked>
                                                <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                                            </div>
                                        </label>
                                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                                            <div>
                                                <p class="font-medium text-gray-900">Oferte și reduceri</p>
                                                <p class="text-sm text-gray-500">Notificări despre early bird și promoții</p>
                                            </div>
                                            <div class="relative">
                                                <input type="checkbox" class="sr-only peer" checked>
                                                <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                                            </div>
                                        </label>
                                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                                            <div>
                                                <p class="font-medium text-gray-900">Reminder evenimente</p>
                                                <p class="text-sm text-gray-500">Primești reminder înainte de evenimentele la care participi</p>
                                            </div>
                                            <div class="relative">
                                                <input type="checkbox" class="sr-only peer" checked>
                                                <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                                            </div>
                                        </label>
                                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                                            <div>
                                                <p class="font-medium text-gray-900">Newsletter săptămânal</p>
                                                <p class="text-sm text-gray-500">Rezumat cu cele mai bune evenimente din săptămână</p>
                                            </div>
                                            <div class="relative">
                                                <input type="checkbox" class="sr-only peer">
                                                <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Push Notifications -->
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Notificări Push</h3>
                                    <div class="space-y-4">
                                        <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                                            <div>
                                                <p class="font-medium text-gray-900">Activează notificări push</p>
                                                <p class="text-sm text-gray-500">Primești notificări în browser pentru evenimente importante</p>
                                            </div>
                                            <div class="relative">
                                                <input type="checkbox" class="sr-only peer">
                                                <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-4 border-t border-gray-100">
                                    <button type="button" class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                                        Salvează setările
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Privacy Tab -->
                        <div id="tab-privacy" class="tab-content hidden">
                            <div class="space-y-6">
                                <!-- Change Password -->
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Schimbă parola</h3>
                                    <div class="max-w-md space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Parola curentă</label>
                                            <input type="password" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Parola nouă</label>
                                            <input type="password" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmă parola nouă</label>
                                            <input type="password" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        </div>
                                        <button type="button" class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                                            Actualizează parola
                                        </button>
                                    </div>
                                </div>

                                <!-- Two Factor Auth -->
                                <div class="pt-6 border-t border-gray-100">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Autentificare în doi pași</h3>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                        <div>
                                            <p class="font-medium text-gray-900">Activează 2FA</p>
                                            <p class="text-sm text-gray-500">Adaugă un nivel suplimentar de securitate contului tău</p>
                                        </div>
                                        <button class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors">
                                            Configurează
                                        </button>
                                    </div>
                                </div>

                                <!-- Connected Accounts -->
                                <div class="pt-6 border-t border-gray-100">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Conturi conectate</h3>
                                    <p class="text-sm text-gray-500 mb-4">Conectează-ți conturile pentru login rapid și funcționalități suplimentare.</p>
                                    <div class="space-y-3">
                                        <!-- Facebook -->
                                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl" id="account-facebook">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900">Facebook</p>
                                                    <p class="text-sm text-gray-500 account-status">Conectat ca Alexandru Marin</p>
                                                </div>
                                            </div>
                                            <button onclick="disconnectAccount('facebook')" class="text-sm text-red-600 font-medium hover:underline disconnect-btn">Deconectează</button>
                                            <button onclick="connectAccount('facebook')" class="hidden px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors connect-btn">
                                                <span class="flex items-center gap-2">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                                    Conectează cu Facebook
                                                </span>
                                            </button>
                                        </div>

                                        <!-- Google -->
                                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl" id="account-google">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-red-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/></svg>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900">Google</p>
                                                    <p class="text-sm text-gray-500 account-status">Nu e conectat</p>
                                                </div>
                                            </div>
                                            <button onclick="disconnectAccount('google')" class="hidden text-sm text-red-600 font-medium hover:underline disconnect-btn">Deconectează</button>
                                            <button onclick="connectAccount('google')" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors connect-btn">
                                                <span class="flex items-center gap-2">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/></svg>
                                                    Conectează cu Google
                                                </span>
                                            </button>
                                        </div>

                                        <!-- Spotify -->
                                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl" id="account-spotify">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-green-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900">Spotify</p>
                                                    <p class="text-sm text-gray-500 account-status">Nu e conectat</p>
                                                    <p class="text-xs text-green-600 mt-0.5">Importă preferințele muzicale automat</p>
                                                </div>
                                            </div>
                                            <button onclick="disconnectAccount('spotify')" class="hidden text-sm text-red-600 font-medium hover:underline disconnect-btn">Deconectează</button>
                                            <button onclick="connectAccount('spotify')" class="px-4 py-2 bg-green-600 text-white rounded-xl text-sm font-medium hover:bg-green-700 transition-colors connect-btn">
                                                <span class="flex items-center gap-2">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                                                    Conectează Spotify
                                                </span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Spotify Sync Info Box -->
                                    <div class="mt-4 p-4 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-100 rounded-xl" id="spotify-info-box">
                                        <div class="flex items-start gap-3">
                                            <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-gray-900 text-sm">De ce să conectez Spotify?</h4>
                                                <p class="text-sm text-gray-600 mt-1">Conectând Spotify, vom importa automat artiștii tăi preferați și genurile muzicale pentru recomandări personalizate. Nu vom posta nimic și nu vom accesa playlisturile tale.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Account -->
                                <div class="pt-6 border-t border-gray-100">
                                    <h3 class="text-lg font-semibold text-red-600 mb-4">Zona periculoasă</h3>
                                    <div class="p-4 bg-red-50 border border-red-100 rounded-xl">
                                        <p class="text-sm text-gray-700 mb-3">Odată ce îți ștergi contul, toate datele tale vor fi șterse permanent. Această acțiune nu poate fi anulată.</p>
                                        <button onclick="showDeleteModal()" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-xl hover:bg-red-700 transition-colors">
                                            Șterge contul
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- GDPR Tab -->
                        <div id="tab-gdpr" class="tab-content hidden">
                            <div class="space-y-6">
                                <!-- GDPR Intro -->
                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">
                                    <div class="flex items-start gap-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">Drepturile tale GDPR</h3>
                                            <p class="text-sm text-gray-600">Conform regulamentului GDPR, ai dreptul să accesezi, să modifici și să ștergi datele personale pe care le deținem despre tine.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stored Data Overview -->
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Datele tale stocate</h3>
                                    <p class="text-sm text-gray-500 mb-4">Acestea sunt toate informațiile pe care le avem despre tine:</p>

                                    <!-- Personal Information -->
                                    <div class="mb-4">
                                        <button onclick="toggleDataSection('personal')" class="w-full flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                </div>
                                                <span class="font-medium text-gray-900">Informații personale</span>
                                            </div>
                                            <svg class="w-5 h-5 text-gray-400 transition-transform data-section-arrow" id="arrow-personal" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div id="data-personal" class="hidden mt-2 p-4 bg-white border border-gray-200 rounded-xl">
                                            <table class="w-full text-sm">
                                                <tbody class="divide-y divide-gray-100">
                                                    <tr><td class="py-2 text-gray-500">Nume complet</td><td class="py-2 text-gray-900 text-right"><?= htmlspecialchars($userProfile['firstName'] . ' ' . $userProfile['lastName']) ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Email</td><td class="py-2 text-gray-900 text-right"><?= htmlspecialchars($userProfile['email']) ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Telefon</td><td class="py-2 text-gray-900 text-right"><?= htmlspecialchars($userProfile['phone']) ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Data nașterii</td><td class="py-2 text-gray-900 text-right"><?= htmlspecialchars($userProfile['birthDate']) ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Gen</td><td class="py-2 text-gray-900 text-right"><?= $userProfile['gender'] === 'male' ? 'Masculin' : ($userProfile['gender'] === 'female' ? 'Feminin' : 'Nespecificat') ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Oraș</td><td class="py-2 text-gray-900 text-right"><?= htmlspecialchars($cities[$userProfile['city']] ?? $userProfile['city']) ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Data înregistrării</td><td class="py-2 text-gray-900 text-right">15 Ianuarie 2024</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Preferences -->
                                    <div class="mb-4">
                                        <button onclick="toggleDataSection('preferences')" class="w-full flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-pink-100 rounded-lg flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                                </div>
                                                <span class="font-medium text-gray-900">Preferințe și interese</span>
                                            </div>
                                            <svg class="w-5 h-5 text-gray-400 transition-transform data-section-arrow" id="arrow-preferences" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div id="data-preferences" class="hidden mt-2 p-4 bg-white border border-gray-200 rounded-xl">
                                            <table class="w-full text-sm">
                                                <tbody class="divide-y divide-gray-100">
                                                    <tr><td class="py-2 text-gray-500">Categorii preferate</td><td class="py-2 text-gray-900 text-right"><?= implode(', ', array_map(fn($c) => $categories[$c]['name'] ?? $c, $userProfile['preferredCategories'])) ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Genuri muzicale</td><td class="py-2 text-gray-900 text-right"><?= implode(', ', array_map(fn($g) => $genres[$g] ?? $g, $userProfile['preferredGenres'])) ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Tipuri de artiști</td><td class="py-2 text-gray-900 text-right"><?= implode(', ', array_map(fn($t) => $artistTypes[$t] ?? $t, $userProfile['preferredArtistTypes'])) ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Tipuri de bilete</td><td class="py-2 text-gray-900 text-right"><?= implode(', ', array_map(fn($t) => $ticketTypes[$t]['name'] ?? $t, $userProfile['preferredTicketTypes'])) ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Zile preferate</td><td class="py-2 text-gray-900 text-right"><?= implode(', ', array_map(fn($d) => $daysOfWeek[$d] ?? $d, $userProfile['preferredDays'])) ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Anotimpuri preferate</td><td class="py-2 text-gray-900 text-right"><?= implode(', ', array_map(fn($s) => explode(' ', $seasons[$s] ?? $s)[0], $userProfile['preferredSeasons'])) ?></td></tr>
                                                    <tr><td class="py-2 text-gray-500">Transport preferat</td><td class="py-2 text-gray-900 text-right"><?= $transportOptions[$userProfile['preferredTransport']]['name'] ?? $userProfile['preferredTransport'] ?></td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Activity & Transactions -->
                                    <div class="mb-4">
                                        <button onclick="toggleDataSection('activity')" class="w-full flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                                </div>
                                                <span class="font-medium text-gray-900">Activitate și tranzacții</span>
                                            </div>
                                            <svg class="w-5 h-5 text-gray-400 transition-transform data-section-arrow" id="arrow-activity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div id="data-activity" class="hidden mt-2 p-4 bg-white border border-gray-200 rounded-xl">
                                            <table class="w-full text-sm">
                                                <tbody class="divide-y divide-gray-100">
                                                    <tr><td class="py-2 text-gray-500">Comenzi efectuate</td><td class="py-2 text-gray-900 text-right">12 comenzi</td></tr>
                                                    <tr><td class="py-2 text-gray-500">Bilete cumpărate</td><td class="py-2 text-gray-900 text-right">28 bilete</td></tr>
                                                    <tr><td class="py-2 text-gray-500">Valoare totală comenzi</td><td class="py-2 text-gray-900 text-right">2.450 RON</td></tr>
                                                    <tr><td class="py-2 text-gray-500">Evenimente vizualizate</td><td class="py-2 text-gray-900 text-right">156 evenimente</td></tr>
                                                    <tr><td class="py-2 text-gray-500">Căutări efectuate</td><td class="py-2 text-gray-900 text-right">89 căutări</td></tr>
                                                    <tr><td class="py-2 text-gray-500">Puncte de fidelitate</td><td class="py-2 text-gray-900 text-right"><?= number_format($userProfile['points']) ?> puncte</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Connected Services -->
                                    <div class="mb-4">
                                        <button onclick="toggleDataSection('connected')" class="w-full flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                                </div>
                                                <span class="font-medium text-gray-900">Servicii conectate</span>
                                            </div>
                                            <svg class="w-5 h-5 text-gray-400 transition-transform data-section-arrow" id="arrow-connected" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div id="data-connected" class="hidden mt-2 p-4 bg-white border border-gray-200 rounded-xl">
                                            <table class="w-full text-sm">
                                                <tbody class="divide-y divide-gray-100">
                                                    <tr><td class="py-2 text-gray-500">Facebook</td><td class="py-2 text-gray-900 text-right">Conectat</td></tr>
                                                    <tr><td class="py-2 text-gray-500">Google</td><td class="py-2 text-gray-900 text-right">Nu e conectat</td></tr>
                                                    <tr><td class="py-2 text-gray-500">Spotify</td><td class="py-2 text-gray-900 text-right">Nu e conectat</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Device & Session Data -->
                                    <div class="mb-4">
                                        <button onclick="toggleDataSection('devices')" class="w-full flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                </div>
                                                <span class="font-medium text-gray-900">Dispozitive și sesiuni</span>
                                            </div>
                                            <svg class="w-5 h-5 text-gray-400 transition-transform data-section-arrow" id="arrow-devices" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div id="data-devices" class="hidden mt-2 p-4 bg-white border border-gray-200 rounded-xl">
                                            <table class="w-full text-sm">
                                                <tbody class="divide-y divide-gray-100">
                                                    <tr><td class="py-2 text-gray-500">Ultima autentificare</td><td class="py-2 text-gray-900 text-right">Azi, 14:32</td></tr>
                                                    <tr><td class="py-2 text-gray-500">Dispozitiv curent</td><td class="py-2 text-gray-900 text-right">Chrome / Windows</td></tr>
                                                    <tr><td class="py-2 text-gray-500">IP ultima autentificare</td><td class="py-2 text-gray-900 text-right">86.124.xxx.xxx</td></tr>
                                                    <tr><td class="py-2 text-gray-500">Sesiuni active</td><td class="py-2 text-gray-900 text-right">2 dispozitive</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Export Data -->
                                <div class="pt-6 border-t border-gray-100">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Exportă datele tale</h3>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                        <div>
                                            <p class="font-medium text-gray-900">Descarcă o copie a datelor tale</p>
                                            <p class="text-sm text-gray-500">Vei primi un fișier JSON cu toate datele stocate</p>
                                        </div>
                                        <button onclick="exportUserData()" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-medium hover:bg-indigo-700 transition-colors flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            Exportă date
                                        </button>
                                    </div>
                                </div>

                                <!-- Request Data Deletion -->
                                <div class="pt-6 border-t border-gray-100">
                                    <h3 class="text-lg font-semibold text-red-600 mb-4">Cerere de ștergere date</h3>
                                    <div class="p-4 bg-red-50 border border-red-100 rounded-xl">
                                        <div class="flex items-start gap-3 mb-4">
                                            <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-700">Conform GDPR, ai dreptul să ceri ștergerea tuturor datelor personale. Această acțiune este ireversibilă și va dura până la 30 de zile pentru procesare.</p>
                                            </div>
                                        </div>
                                        <button onclick="showGdprDeleteModal()" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-xl hover:bg-red-700 transition-colors flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            Solicită ștergerea datelor
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <!-- OAuth Connect Modal -->
    <div id="oauthModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50" onclick="closeOauthModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 relative">
                <button onclick="closeOauthModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <div id="oauth-content" class="text-center">
                    <!-- Dynamic content will be inserted here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Spotify Success Modal -->
    <div id="spotifySuccessModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50" onclick="closeSpotifySuccessModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 relative">
                <button onclick="closeSpotifySuccessModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Spotify conectat cu succes!</h3>
                    <p class="text-gray-600 mb-4">Am importat preferințele tale muzicale.</p>

                    <div class="bg-gray-50 rounded-xl p-4 text-left mb-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Am detectat:</p>
                        <div class="flex flex-wrap gap-2">
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">Rock</span>
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">Electronic</span>
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">Pop</span>
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">Alternative</span>
                        </div>
                        <p class="text-sm font-medium text-gray-700 mt-3 mb-2">Artiști preferați:</p>
                        <div class="flex flex-wrap gap-2">
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium">Coldplay</span>
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium">Dua Lipa</span>
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium">The Weeknd</span>
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium">Arctic Monkeys</span>
                        </div>
                    </div>

                    <button onclick="closeSpotifySuccessModal()" class="w-full px-4 py-2.5 bg-green-600 text-white font-medium rounded-xl hover:bg-green-700 transition-colors">
                        Perfect, mulțumesc!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- GDPR Delete Modal - Step 1 -->
    <div id="gdprDeleteModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50" onclick="closeGdprDeleteModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 relative">
                <button onclick="closeGdprDeleteModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Solicită ștergerea datelor</h3>
                    <p class="text-gray-600">Această acțiune este ireversibilă. Te rugăm să ne spui motivul pentru care dorești ștergerea datelor.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Motivul cererii de ștergere *</label>
                        <select id="deleteReason" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent" onchange="updateDeleteReasonVisibility()">
                            <option value="">Selectează un motiv</option>
                            <option value="no-longer-use">Nu mai folosesc serviciul</option>
                            <option value="privacy-concerns">Îngrijorări legate de confidențialitate</option>
                            <option value="too-many-emails">Primesc prea multe email-uri</option>
                            <option value="bad-experience">Experiență negativă cu platforma</option>
                            <option value="switching-service">Folosesc alt serviciu</option>
                            <option value="other">Alt motiv</option>
                        </select>
                    </div>

                    <div id="deleteReasonOther" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Te rugăm să ne spui mai multe</label>
                        <textarea id="deleteReasonText" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none" placeholder="Descrie motivul cererii tale..."></textarea>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div class="text-sm text-amber-800">
                                <p class="font-medium">Ce se va întâmpla:</p>
                                <ul class="mt-1 list-disc list-inside space-y-1">
                                    <li>Toate datele personale vor fi șterse</li>
                                    <li>Biletele active vor fi anulate</li>
                                    <li>Punctele de fidelitate vor fi pierdute</li>
                                    <li>Procesarea durează până la 30 de zile</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button onclick="closeGdprDeleteModal()" class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        Anulează
                    </button>
                    <button onclick="showGdprConfirmModal()" id="continueDeleteBtn" class="flex-1 px-4 py-2.5 bg-red-600 text-white font-medium rounded-xl hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        Continuă
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- GDPR Delete Modal - Step 2 (Confirmation) -->
    <div id="gdprConfirmModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50" onclick="closeGdprConfirmModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 relative">
                <button onclick="closeGdprConfirmModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Confirmare finală</h3>
                    <p class="text-gray-600">Scrie <strong class="text-red-600">STERGE</strong> pentru a confirma ștergerea permanentă a datelor tale.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <input type="text" id="deleteConfirmInput" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-center text-lg font-mono focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Scrie STERGE" oninput="validateDeleteConfirmation()">
                    </div>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" id="deleteUnderstand" class="mt-1 w-4 h-4 text-red-600 rounded focus:ring-red-500" onchange="validateDeleteConfirmation()">
                        <span class="text-sm text-gray-600">Înțeleg că această acțiune este ireversibilă și toate datele mele vor fi șterse permanent.</span>
                    </label>
                </div>

                <div class="flex gap-3 mt-6">
                    <button onclick="closeGdprConfirmModal()" class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        Înapoi
                    </button>
                    <button onclick="submitDeleteRequest()" id="finalDeleteBtn" class="flex-1 px-4 py-2.5 bg-red-600 text-white font-medium rounded-xl hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        Șterge definitiv
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Request Submitted Modal -->
    <div id="deleteSubmittedModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 relative">
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Cerere înregistrată</h3>
                    <p class="text-gray-600 mb-4">Cererea ta de ștergere a datelor a fost înregistrată. Vei primi un email de confirmare și procesarea va dura până la 30 de zile.</p>
                    <p class="text-sm text-gray-500 mb-6">ID cerere: <span class="font-mono font-medium">#GDPR-<?= date('Ymd') ?>-<?= rand(1000, 9999) ?></span></p>

                    <button onclick="window.location.href='/'" class="w-full px-4 py-2.5 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                        Înțeles
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                // Update tabs
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove('active', 'border-indigo-600', 'text-indigo-600');
                    b.classList.add('border-transparent', 'text-gray-500');
                });
                btn.classList.add('active', 'border-indigo-600', 'text-indigo-600');
                btn.classList.remove('border-transparent', 'text-gray-500');

                // Update content
                document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
                document.getElementById('tab-' + btn.dataset.tab).classList.remove('hidden');
            });
        });

        // Preference chip toggle
        document.querySelectorAll('.pref-chip input[type="checkbox"]').forEach(input => {
            input.addEventListener('change', (e) => {
                const wrapper = e.target.nextElementSibling;
                if (e.target.checked) {
                    wrapper.classList.add('border-indigo-500', 'bg-indigo-50');
                    wrapper.classList.remove('border-gray-200');
                    wrapper.querySelectorAll('span').forEach(s => {
                        if (s.classList.contains('text-gray-700') || s.classList.contains('text-gray-600')) {
                            s.classList.remove('text-gray-700', 'text-gray-600');
                            s.classList.add('text-indigo-700');
                        }
                    });
                } else {
                    wrapper.classList.remove('border-indigo-500', 'bg-indigo-50');
                    wrapper.classList.add('border-gray-200');
                    wrapper.querySelectorAll('span').forEach(s => {
                        if (s.classList.contains('text-indigo-700')) {
                            s.classList.remove('text-indigo-700');
                            s.classList.add('text-gray-700');
                        }
                    });
                }
                updateProfileCompleteness();
            });
        });

        // Radio preference toggle
        document.querySelectorAll('.pref-radio input[type="radio"]').forEach(input => {
            input.addEventListener('change', (e) => {
                // Reset all in this group
                const name = e.target.name;
                document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                    const wrapper = r.nextElementSibling;
                    wrapper.classList.remove('border-indigo-500', 'bg-indigo-50');
                    wrapper.classList.add('border-gray-200');
                    wrapper.querySelectorAll('span').forEach(s => {
                        if (s.classList.contains('text-indigo-700')) {
                            s.classList.remove('text-indigo-700');
                            s.classList.add('text-gray-700');
                        }
                    });
                });

                // Activate selected
                const wrapper = e.target.nextElementSibling;
                wrapper.classList.add('border-indigo-500', 'bg-indigo-50');
                wrapper.classList.remove('border-gray-200');
                wrapper.querySelectorAll('span').forEach(s => {
                    if (s.classList.contains('text-gray-700')) {
                        s.classList.remove('text-gray-700');
                        s.classList.add('text-indigo-700');
                    }
                });
            });
        });

        // Calculate and update profile completeness
        function updateProfileCompleteness() {
            const totalSections = 7;
            let completed = 0;

            // Categories
            if (document.querySelectorAll('input[name="categories[]"]:checked').length > 0) completed++;
            // Genres
            if (document.querySelectorAll('input[name="genres[]"]:checked').length > 0) completed++;
            // Artist types
            if (document.querySelectorAll('input[name="artist_types[]"]:checked').length > 0) completed++;
            // Ticket types
            if (document.querySelectorAll('input[name="ticket_types[]"]:checked').length > 0) completed++;
            // Days
            if (document.querySelectorAll('input[name="days[]"]:checked').length > 0) completed++;
            // Seasons
            if (document.querySelectorAll('input[name="seasons[]"]:checked').length > 0) completed++;
            // Transport
            if (document.querySelector('input[name="transport"]:checked')) completed++;

            const percentage = Math.round((completed / totalSections) * 100);
            // Update UI here if needed
        }

        function savePreferences() {
            // Collect all preferences
            const preferences = {
                categories: [...document.querySelectorAll('input[name="categories[]"]:checked')].map(i => i.value),
                genres: [...document.querySelectorAll('input[name="genres[]"]:checked')].map(i => i.value),
                artistTypes: [...document.querySelectorAll('input[name="artist_types[]"]:checked')].map(i => i.value),
                ticketTypes: [...document.querySelectorAll('input[name="ticket_types[]"]:checked')].map(i => i.value),
                days: [...document.querySelectorAll('input[name="days[]"]:checked')].map(i => i.value),
                seasons: [...document.querySelectorAll('input[name="seasons[]"]:checked')].map(i => i.value),
                transport: document.querySelector('input[name="transport"]:checked')?.value
            };

            console.log('Saving preferences:', preferences);
            alert('Preferințele au fost salvate! (Demo)');
        }

        // =========================================
        // CONNECTED ACCOUNTS FUNCTIONALITY
        // =========================================

        const connectedAccounts = {
            facebook: { connected: true, name: 'Alexandru Marin' },
            google: { connected: false, name: null },
            spotify: { connected: false, name: null }
        };

        function connectAccount(provider) {
            const modal = document.getElementById('oauthModal');
            const content = document.getElementById('oauth-content');

            const providerInfo = {
                facebook: {
                    name: 'Facebook',
                    color: 'blue',
                    icon: '<svg class="w-8 h-8 text-blue-600" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'
                },
                google: {
                    name: 'Google',
                    color: 'red',
                    icon: '<svg class="w-8 h-8 text-red-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/></svg>'
                },
                spotify: {
                    name: 'Spotify',
                    color: 'green',
                    icon: '<svg class="w-8 h-8 text-green-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>'
                }
            };

            const info = providerInfo[provider];

            content.innerHTML = `
                <div class="w-16 h-16 bg-${info.color}-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    ${info.icon}
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Conectare ${info.name}</h3>
                <p class="text-gray-600 mb-6">Vei fi redirecționat către ${info.name} pentru autentificare.</p>
                <div class="space-y-3">
                    <button onclick="simulateOAuthFlow('${provider}')" class="w-full px-4 py-2.5 bg-${info.color}-600 text-white font-medium rounded-xl hover:bg-${info.color}-700 transition-colors">
                        Continuă cu ${info.name}
                    </button>
                    <button onclick="closeOauthModal()" class="w-full px-4 py-2.5 border border-gray-200 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors">
                        Anulează
                    </button>
                </div>
            `;

            modal.classList.remove('hidden');
        }

        function simulateOAuthFlow(provider) {
            const content = document.getElementById('oauth-content');

            // Show loading state
            content.innerHTML = `
                <div class="py-8">
                    <div class="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                    <p class="text-gray-600">Se conectează...</p>
                </div>
            `;

            // Simulate OAuth callback after delay
            setTimeout(() => {
                closeOauthModal();

                // Update state
                connectedAccounts[provider] = {
                    connected: true,
                    name: 'Alexandru Marin'
                };

                // Update UI
                updateAccountUI(provider, true);

                // Show Spotify success modal with preferences
                if (provider === 'spotify') {
                    setTimeout(() => {
                        document.getElementById('spotifySuccessModal').classList.remove('hidden');
                    }, 300);
                }
            }, 1500);
        }

        function disconnectAccount(provider) {
            if (confirm(`Sigur vrei să deconectezi contul ${provider.charAt(0).toUpperCase() + provider.slice(1)}?`)) {
                connectedAccounts[provider] = { connected: false, name: null };
                updateAccountUI(provider, false);
            }
        }

        function updateAccountUI(provider, isConnected) {
            const container = document.getElementById(`account-${provider}`);
            const status = container.querySelector('.account-status');
            const connectBtn = container.querySelector('.connect-btn');
            const disconnectBtn = container.querySelector('.disconnect-btn');

            if (isConnected) {
                status.textContent = 'Conectat ca Alexandru Marin';
                connectBtn.classList.add('hidden');
                disconnectBtn.classList.remove('hidden');
            } else {
                status.textContent = 'Nu e conectat';
                connectBtn.classList.remove('hidden');
                disconnectBtn.classList.add('hidden');
            }
        }

        function closeOauthModal() {
            document.getElementById('oauthModal').classList.add('hidden');
        }

        function closeSpotifySuccessModal() {
            document.getElementById('spotifySuccessModal').classList.add('hidden');
        }

        // =========================================
        // GDPR FUNCTIONALITY
        // =========================================

        function toggleDataSection(section) {
            const content = document.getElementById(`data-${section}`);
            const arrow = document.getElementById(`arrow-${section}`);

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                arrow.style.transform = 'rotate(180deg)';
            } else {
                content.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
            }
        }

        function exportUserData() {
            // Simulate data export
            const userData = {
                personal: {
                    name: 'Alexandru Marin',
                    email: 'alexandru.marin@example.com',
                    phone: '+40 721 234 567',
                    birthDate: '1992-05-15',
                    city: 'București'
                },
                preferences: {
                    categories: ['Concerte', 'Stand-up', 'Festivaluri'],
                    genres: ['Rock', 'Electronic', 'Pop'],
                    transport: 'Mașină personală'
                },
                activity: {
                    orders: 12,
                    tickets: 28,
                    totalValue: 2450
                },
                exportDate: new Date().toISOString()
            };

            const blob = new Blob([JSON.stringify(userData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `tics-date-personale-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            alert('Datele tale au fost exportate cu succes!');
        }

        function showGdprDeleteModal() {
            document.getElementById('gdprDeleteModal').classList.remove('hidden');
            document.getElementById('deleteReason').value = '';
            document.getElementById('deleteReasonOther').classList.add('hidden');
            document.getElementById('deleteReasonText').value = '';
            document.getElementById('continueDeleteBtn').disabled = true;
        }

        function closeGdprDeleteModal() {
            document.getElementById('gdprDeleteModal').classList.add('hidden');
        }

        function updateDeleteReasonVisibility() {
            const reason = document.getElementById('deleteReason').value;
            const otherContainer = document.getElementById('deleteReasonOther');
            const continueBtn = document.getElementById('continueDeleteBtn');

            if (reason === 'other') {
                otherContainer.classList.remove('hidden');
            } else {
                otherContainer.classList.add('hidden');
            }

            continueBtn.disabled = !reason;
        }

        function showGdprConfirmModal() {
            const reason = document.getElementById('deleteReason').value;
            if (!reason) {
                alert('Te rugăm să selectezi un motiv pentru ștergere.');
                return;
            }

            closeGdprDeleteModal();
            document.getElementById('gdprConfirmModal').classList.remove('hidden');
            document.getElementById('deleteConfirmInput').value = '';
            document.getElementById('deleteUnderstand').checked = false;
            document.getElementById('finalDeleteBtn').disabled = true;
        }

        function closeGdprConfirmModal() {
            document.getElementById('gdprConfirmModal').classList.add('hidden');
        }

        function validateDeleteConfirmation() {
            const input = document.getElementById('deleteConfirmInput').value;
            const checkbox = document.getElementById('deleteUnderstand').checked;
            const btn = document.getElementById('finalDeleteBtn');

            btn.disabled = !(input === 'STERGE' && checkbox);
        }

        function submitDeleteRequest() {
            const reason = document.getElementById('deleteReason').value;
            const reasonText = document.getElementById('deleteReasonText').value;

            console.log('Delete request submitted:', { reason, reasonText });

            closeGdprConfirmModal();
            document.getElementById('deleteSubmittedModal').classList.remove('hidden');
        }

        function showDeleteModal() {
            showGdprDeleteModal();
        }
    </script>
</body>
</html>
