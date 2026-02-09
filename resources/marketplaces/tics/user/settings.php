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
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900">Facebook</p>
                                                    <p class="text-sm text-gray-500">Conectat</p>
                                                </div>
                                            </div>
                                            <button class="text-sm text-red-600 font-medium hover:underline">Deconectează</button>
                                        </div>
                                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-red-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/></svg>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900">Google</p>
                                                    <p class="text-sm text-gray-500">Nu e conectat</p>
                                                </div>
                                            </div>
                                            <button class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors">Conectează</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Account -->
                                <div class="pt-6 border-t border-gray-100">
                                    <h3 class="text-lg font-semibold text-red-600 mb-4">Zona periculoasă</h3>
                                    <div class="p-4 bg-red-50 border border-red-100 rounded-xl">
                                        <p class="text-sm text-gray-700 mb-3">Odată ce îți ștergi contul, toate datele tale vor fi șterse permanent. Această acțiune nu poate fi anulată.</p>
                                        <button class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-xl hover:bg-red-700 transition-colors">
                                            Șterge contul
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
    </script>
</body>
</html>
