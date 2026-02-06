<?php
/**
 * User Tickets Page
 */

// Helper functions for calendar URLs
function getGoogleCalendarUrl($ticket) {
    $startDate = date('Ymd', strtotime($ticket['date']));
    $startTime = str_replace(':', '', $ticket['time']) . '00';
    $endTime = date('His', strtotime($ticket['time']) + 7200); // +2 hours

    $params = [
        'action' => 'TEMPLATE',
        'text' => $ticket['eventName'],
        'dates' => $startDate . 'T' . $startTime . '/' . $startDate . 'T' . $endTime,
        'details' => 'Bilet cumparat de pe TICS.ro - Comanda #' . $ticket['id'],
        'location' => $ticket['venue'] . ', ' . $ticket['city'],
    ];

    return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
}

function getOutlookCalendarUrl($ticket) {
    $startDate = date('Y-m-d', strtotime($ticket['date']));
    $startTime = $ticket['time'] . ':00';
    $endTime = date('H:i:s', strtotime($ticket['time']) + 7200);

    $params = [
        'path' => '/calendar/action/compose',
        'rru' => 'addevent',
        'subject' => $ticket['eventName'],
        'startdt' => $startDate . 'T' . $startTime,
        'enddt' => $startDate . 'T' . $endTime,
        'body' => 'Bilet cumparat de pe TICS.ro - Comanda #' . $ticket['id'],
        'location' => $ticket['venue'] . ', ' . $ticket['city'],
    ];

    return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query($params);
}

function getYahooCalendarUrl($ticket) {
    $startDate = date('Ymd', strtotime($ticket['date']));
    $startTime = str_replace(':', '', $ticket['time']) . '00';

    $params = [
        'v' => '60',
        'title' => $ticket['eventName'],
        'st' => $startDate . 'T' . $startTime,
        'dur' => '0200', // 2 hours
        'desc' => 'Bilet cumparat de pe TICS.ro - Comanda #' . $ticket['id'],
        'in_loc' => $ticket['venue'] . ', ' . $ticket['city'],
    ];

    return 'https://calendar.yahoo.com/?' . http_build_query($params);
}

function getIcsDownloadUrl($ticket) {
    $startDate = date('Ymd', strtotime($ticket['date']));
    $startTime = str_replace(':', '', $ticket['time']) . '00';
    $endTime = date('His', strtotime($ticket['time']) + 7200);
    $uid = uniqid('tics-');

    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//TICS.ro//Tickets//RO\r\n";
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:{$uid}@tics.ro\r\n";
    $ics .= "DTSTART:{$startDate}T{$startTime}\r\n";
    $ics .= "DTEND:{$startDate}T{$endTime}\r\n";
    $ics .= "SUMMARY:" . $ticket['eventName'] . "\r\n";
    $ics .= "LOCATION:" . $ticket['venue'] . ', ' . $ticket['city'] . "\r\n";
    $ics .= "DESCRIPTION:Bilet cumparat de pe TICS.ro - Comanda #" . $ticket['id'] . "\r\n";
    $ics .= "END:VEVENT\r\n";
    $ics .= "END:VCALENDAR";

    return 'data:text/calendar;charset=utf-8,' . rawurlencode($ics);
}

// Load demo data
$demoData = include __DIR__ . '/../data/demo-user.php';
$currentUser = $demoData['user'];
$tickets = $demoData['tickets'];
$pastTickets = $demoData['pastTickets'];

// Current page for sidebar
$currentPage = 'tickets';

// Page config for head
$pageTitle = 'Biletele mele';
$pageDescription = 'Gestioneaza biletele tale pentru evenimente';

// Get tab from query string
$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'past' ? 'past' : 'upcoming';

// Get unique categories from both upcoming and past tickets for filters
$allTickets = array_merge($tickets, $pastTickets);
$ticketCategories = array_unique(array_column($allTickets, 'category'));

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
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 animate-fadeInUp">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Biletele mele</h1>
                        <p class="text-gray-500">Gestioneaza si acceseaza biletele tale</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="px-4 py-2 bg-white border border-gray-200 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Descarca toate
                        </button>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex items-center gap-6 border-b border-gray-200 mb-6 animate-fadeInUp" style="animation-delay: 0.1s">
                    <button class="tab-btn <?= $activeTab === 'upcoming' ? 'active' : 'text-gray-500' ?> pb-4 text-sm font-medium border-b-2 border-transparent" data-tab="upcoming">
                        Viitoare <span class="ml-1 px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs rounded-full"><?= count($tickets) ?></span>
                    </button>
                    <button class="tab-btn <?= $activeTab === 'past' ? 'active' : 'text-gray-500' ?> pb-4 text-sm font-medium border-b-2 border-transparent" data-tab="past">
                        Trecute <span class="ml-1 px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full"><?= count($pastTickets) ?></span>
                    </button>
                </div>

                <!-- Filters - Dynamic based on user's tickets -->
                <div class="flex flex-wrap items-center gap-2 mb-6 animate-fadeInUp" style="animation-delay: 0.15s">
                    <button class="filter-btn active px-4 py-2 text-sm font-medium rounded-full" data-filter="all">Toate</button>
                    <?php foreach ($ticketCategories as $category): ?>
                    <button class="filter-btn px-4 py-2 text-sm font-medium text-gray-600 rounded-full" data-filter="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></button>
                    <?php endforeach; ?>
                </div>

                <!-- Upcoming Tickets List -->
                <div class="space-y-4" id="upcomingTicketsList" style="<?= $activeTab === 'past' ? 'display: none;' : '' ?>">
                    <?php if (empty($tickets)): ?>
                    <div class="text-center py-16">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Nu ai bilete viitoare</h3>
                        <p class="text-gray-500 mb-6">Exploreaza evenimentele disponibile si cumpara un bilet!</p>
                        <a href="/evenimente" class="inline-flex items-center gap-2 px-6 py-3 bg-gray-900 text-white font-semibold rounded-xl hover:bg-gray-800 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Descopera evenimente
                        </a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($tickets as $index => $ticket): ?>
                    <div class="ticket-card bg-white rounded-2xl border border-gray-200 overflow-hidden animate-fadeInUp" style="animation-delay: <?= 0.2 + ($index * 0.05) ?>s" data-category="<?= htmlspecialchars($ticket['category']) ?>" data-ticket-id="<?= htmlspecialchars($ticket['id']) ?>">
                        <div class="flex flex-col md:flex-row">
                            <!-- Event Image -->
                            <div class="md:w-48 h-40 md:h-auto relative flex-shrink-0">
                                <img src="<?= htmlspecialchars($ticket['eventImage']) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($ticket['eventName']) ?>">
                                <div class="absolute top-3 left-3 <?= $ticket['isUrgent'] ? 'countdown-urgent animate-pulse' : 'bg-gradient-to-r from-indigo-500 to-purple-500' ?> px-3 py-1.5 text-white text-xs font-bold rounded-full flex items-center gap-1.5">
                                    <?php if ($ticket['isUrgent']): ?>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <?php endif; ?>
                                    <?= $ticket['daysUntil'] ?> zile
                                </div>
                            </div>

                            <!-- Ticket Info -->
                            <div class="flex-1 p-5">
                                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="px-2 py-1 bg-<?= $ticket['categoryColor'] ?>-100 text-<?= $ticket['categoryColor'] ?>-700 text-xs font-medium rounded-full"><?= htmlspecialchars($ticket['category']) ?></span>
                                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">✓ Valid</span>
                                        </div>
                                        <h3 class="text-lg font-bold text-gray-900 mb-1"><?= htmlspecialchars($ticket['eventName']) ?></h3>
                                        <p class="text-gray-500 text-sm mb-3"><?= htmlspecialchars($ticket['venue']) ?>, <?= htmlspecialchars($ticket['city']) ?></p>

                                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 mb-4">
                                            <span class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <?= date('j M Y', strtotime($ticket['date'])) ?>
                                            </span>
                                            <span class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <?= htmlspecialchars($ticket['time']) ?>
                                            </span>
                                            <span class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                                <?= $ticket['quantity'] ?> bilet<?= $ticket['quantity'] > 1 ? 'e' : '' ?>
                                            </span>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg">🎫 <?= htmlspecialchars($ticket['ticketType']) ?><?= isset($ticket['seatInfo']) ? ' | ' . htmlspecialchars($ticket['seatInfo']) : '' ?></span>
                                            <span class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg">Comanda #<?= htmlspecialchars($ticket['id']) ?></span>
                                        </div>
                                    </div>

                                    <!-- QR Code -->
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="qr-code w-28 h-28 rounded-xl flex items-center justify-center border-2 border-dashed border-gray-300">
                                            <div class="text-center">
                                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                                </svg>
                                                <span class="text-xs text-gray-500">QR Code</span>
                                            </div>
                                        </div>
                                        <button onclick="openTicketModal('<?= htmlspecialchars($ticket['id']) ?>')" class="text-sm text-indigo-600 font-medium hover:underline">
                                            Vezi bilet<?= $ticket['quantity'] > 1 ? 'ele' : 'ul' ?> →
                                        </button>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-gray-100">
                                    <button onclick="openTicketModal('<?= htmlspecialchars($ticket['id']) ?>')" class="action-btn px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-xl hover:bg-gray-800 transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Vezi biletele
                                    </button>
                                    <button class="action-btn px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Descarca PDF
                                    </button>
                                    <button onclick="openTransferModal('<?= htmlspecialchars($ticket['id']) ?>')" class="action-btn px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                        Transfera
                                    </button>
                                    <div class="relative">
                                        <button onclick="toggleCalendarMenu('<?= htmlspecialchars($ticket['id']) ?>')" class="action-btn px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            Calendar
                                        </button>
                                        <div id="calendarMenu-<?= htmlspecialchars($ticket['id']) ?>" class="calendar-dropdown hidden absolute bottom-full left-0 mb-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-2 z-10">
                                            <a href="<?= getGoogleCalendarUrl($ticket) ?>" target="_blank" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M19.5 3h-15A1.5 1.5 0 003 4.5v15A1.5 1.5 0 004.5 21h15a1.5 1.5 0 001.5-1.5v-15A1.5 1.5 0 0019.5 3zM12 17.25a.75.75 0 01-.75-.75v-3.75H7.5a.75.75 0 010-1.5h3.75V7.5a.75.75 0 011.5 0v3.75h3.75a.75.75 0 010 1.5h-3.75v3.75a.75.75 0 01-.75.75z" fill="#4285F4"/></svg>
                                                Google Calendar
                                            </a>
                                            <a href="<?= getOutlookCalendarUrl($ticket) ?>" target="_blank" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="#0078D4"><path d="M7.88 12.04q0 .45-.11.87-.1.41-.33.74-.22.33-.58.52-.37.2-.87.2t-.85-.2q-.35-.21-.57-.55-.22-.33-.33-.75-.1-.42-.1-.86t.1-.87q.1-.43.34-.76.22-.34.59-.54.36-.2.87-.2t.86.2q.35.21.57.55.22.34.31.77.1.43.1.88zM24 12v9.38q0 .46-.33.8-.33.32-.8.32H7.13q-.46 0-.8-.33-.32-.33-.32-.8V18H1q-.41 0-.7-.3-.3-.29-.3-.7V7q0-.41.3-.7Q.58 6 1 6h6.5V2.55q0-.44.3-.75.3-.3.75-.3h12.9q.44 0 .75.3.3.3.3.75V12zm-6-8.25v3h3v-3zm0 4.5v3h3v-3zm0 4.5v1.83l3.05-1.83zm-5.25-9v3h3.75v-3zm0 4.5v3h3.75v-3zm0 4.5v2.03l2.41 1.5 1.34-.8v-2.73zM9 3.75V6h2l.13.01.12.04v-2.3zM5.98 15.98q.9 0 1.6-.3.7-.32 1.19-.86.48-.55.73-1.28.25-.74.25-1.61 0-.83-.25-1.55-.24-.71-.71-1.24t-1.15-.83q-.68-.3-1.55-.3-.92 0-1.64.3-.71.3-1.2.85-.5.54-.75 1.3-.25.74-.25 1.63 0 .85.26 1.56.26.72.74 1.23.48.52 1.17.81.69.3 1.56.3zM7.5 21h12.39L12 16.08V17q0 .41-.3.7-.29.3-.7.3H7.5zm15-.13v-7.24l-5.9 3.54Z"/></svg>
                                                Outlook Calendar
                                            </a>
                                            <a href="<?= getYahooCalendarUrl($ticket) ?>" target="_blank" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="#6001D2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                                                Yahoo Calendar
                                            </a>
                                            <a href="<?= getIcsDownloadUrl($ticket) ?>" download="<?= htmlspecialchars($ticket['eventName']) ?>.ics" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                Descarca .ics
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Past Tickets List -->
                <div class="space-y-4" id="pastTicketsList" style="<?= $activeTab === 'upcoming' ? 'display: none;' : '' ?>">
                    <?php if (empty($pastTickets)): ?>
                    <div class="text-center py-16">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Nu ai bilete trecute</h3>
                        <p class="text-gray-500">Evenimentele la care ai participat vor aparea aici.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($pastTickets as $index => $ticket): ?>
                    <div class="ticket-card past-ticket bg-white rounded-2xl border border-gray-200 overflow-hidden animate-fadeInUp" style="animation-delay: <?= 0.2 + ($index * 0.05) ?>s" data-category="<?= htmlspecialchars($ticket['category']) ?>">
                        <div class="flex flex-col md:flex-row">
                            <!-- Event Image -->
                            <div class="md:w-48 h-40 md:h-auto relative flex-shrink-0">
                                <img src="<?= htmlspecialchars($ticket['eventImage']) ?>" class="w-full h-full object-cover grayscale" alt="<?= htmlspecialchars($ticket['eventName']) ?>">
                                <div class="absolute top-3 left-3 bg-gray-600 px-3 py-1.5 text-white text-xs font-bold rounded-full">
                                    Trecut
                                </div>
                            </div>

                            <!-- Ticket Info -->
                            <div class="flex-1 p-5">
                                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded-full"><?= htmlspecialchars($ticket['category']) ?></span>
                                            <span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded-full">Utilizat</span>
                                        </div>
                                        <h3 class="text-lg font-bold text-gray-900 mb-1"><?= htmlspecialchars($ticket['eventName']) ?></h3>
                                        <p class="text-gray-500 text-sm mb-3"><?= htmlspecialchars($ticket['venue']) ?>, <?= htmlspecialchars($ticket['city']) ?></p>

                                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 mb-4">
                                            <span class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <?= date('j M Y', strtotime($ticket['date'])) ?>
                                            </span>
                                            <span class="flex items-center gap-1.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                                <?= $ticket['quantity'] ?> bilet<?= $ticket['quantity'] > 1 ? 'e' : '' ?>
                                            </span>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg">🎫 <?= htmlspecialchars($ticket['ticketType']) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions for past tickets -->
                                <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-gray-100">
                                    <button class="action-btn px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Descarca PDF
                                    </button>
                                    <a href="/bilete/<?= htmlspecialchars($ticket['eventSlug'] ?? $ticket['id']) ?>" class="action-btn px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        Cumpara din nou
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Ticket Modal -->
    <div id="ticketModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto animate-scaleIn">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Biletele tale</h3>
                <button onclick="closeTicketModal()" class="p-2 hover:bg-gray-100 rounded-xl transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5" id="ticketModalContent">
                <!-- Dynamic content loaded here -->
            </div>
            <div class="p-5 border-t border-gray-100 flex gap-3">
                <button class="flex-1 py-3 bg-gray-900 text-white font-semibold rounded-xl hover:bg-gray-800 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descarca PDF
                </button>
                <button class="flex-1 py-3 bg-white border border-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Trimite email
                </button>
            </div>
        </div>
    </div>

    <!-- Transfer Modal -->
    <div id="transferModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto animate-scaleIn">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Transfera bilete</h3>
                <button onclick="closeTransferModal()" class="p-2 hover:bg-gray-100 rounded-xl transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="transferForm" onsubmit="submitTransfer(event)">
                <div class="p-5 space-y-5">
                    <!-- Step 1: Select tickets -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Selecteaza biletele de transferat</label>
                        <div id="transferTicketsList" class="space-y-2">
                            <!-- Dynamic tickets loaded here -->
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-5">
                        <p class="text-sm font-medium text-gray-700 mb-4">Detalii destinatar</p>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Prenume *</label>
                                <input type="text" name="recipientFirstName" required class="input-field w-full px-4 py-2.5 border border-gray-200 rounded-xl outline-none" placeholder="Ion">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Nume *</label>
                                <input type="text" name="recipientLastName" required class="input-field w-full px-4 py-2.5 border border-gray-200 rounded-xl outline-none" placeholder="Popescu">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Email destinatar *</label>
                            <input type="email" name="recipientEmail" required class="input-field w-full px-4 py-2.5 border border-gray-200 rounded-xl outline-none" placeholder="email@exemplu.com">
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <p class="text-sm text-amber-800">
                            <strong>Atentie:</strong> Dupa transfer, biletele selectate nu vor mai fi valide pentru contul tau. Destinatarul va primi un email cu biletele transferate.
                        </p>
                    </div>
                </div>

                <div class="p-5 border-t border-gray-100 flex gap-3">
                    <button type="button" onclick="closeTransferModal()" class="flex-1 py-3 bg-white border border-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-colors">
                        Anuleaza
                    </button>
                    <button type="submit" id="transferSubmitBtn" class="flex-1 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Transfera biletele
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        // Tickets data for modals
        const ticketsData = <?= json_encode($tickets) ?>;
        const pastTicketsData = <?= json_encode($pastTickets) ?>;
        let currentTransferTicketId = null;

        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.dataset.tab;

                // Update button states
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove('active');
                    b.classList.add('text-gray-500');
                });
                this.classList.add('active');
                this.classList.remove('text-gray-500');

                // Show/hide lists
                document.getElementById('upcomingTicketsList').style.display = tab === 'upcoming' ? '' : 'none';
                document.getElementById('pastTicketsList').style.display = tab === 'past' ? '' : 'none';

                // Update URL
                const url = new URL(window.location);
                url.searchParams.set('tab', tab);
                window.history.pushState({}, '', url);

                // Reset filters
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                document.querySelector('.filter-btn[data-filter="all"]').classList.add('active');
                document.querySelectorAll('.ticket-card').forEach(card => card.style.display = '');
            });
        });

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const filter = this.dataset.filter;
                const activeList = document.getElementById('upcomingTicketsList').style.display !== 'none'
                    ? document.getElementById('upcomingTicketsList')
                    : document.getElementById('pastTicketsList');

                activeList.querySelectorAll('.ticket-card').forEach(card => {
                    if (filter === 'all' || card.dataset.category === filter) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Ticket Modal
        function openTicketModal(ticketId) {
            const ticket = ticketsData.find(t => t.id === ticketId);
            if (!ticket) return;

            const content = document.getElementById('ticketModalContent');
            let html = '';

            ticket.tickets.forEach((t, index) => {
                html += `
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5 ${index < ticket.tickets.length - 1 ? 'mb-4' : ''} border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm text-gray-500">Bilet #${index + 1}</p>
                                <p class="font-semibold text-gray-900">${t.type}${t.seat ? ' - ' + t.seat : ''}</p>
                            </div>
                            <span class="px-3 py-1 bg-green-100 text-green-700 text-sm font-medium rounded-full">Valid</span>
                        </div>
                        <div class="bg-white rounded-xl p-4 flex items-center justify-center mb-4">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(t.code)}" alt="QR Code" class="w-36 h-36">
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-gray-500 mb-1">Cod bilet</p>
                            <p class="font-mono font-bold text-gray-900">${t.code}</p>
                        </div>
                    </div>
                `;
            });

            content.innerHTML = html;

            document.getElementById('ticketModal').classList.remove('hidden');
            document.getElementById('ticketModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeTicketModal() {
            document.getElementById('ticketModal').classList.add('hidden');
            document.getElementById('ticketModal').classList.remove('show');
            document.body.style.overflow = '';
        }

        // Transfer Modal
        function openTransferModal(ticketId) {
            currentTransferTicketId = ticketId;
            const ticket = ticketsData.find(t => t.id === ticketId);
            if (!ticket) return;

            const listContainer = document.getElementById('transferTicketsList');
            let html = '';

            ticket.tickets.forEach((t, index) => {
                html += `
                    <label class="ticket-select-item flex items-center gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer">
                        <input type="checkbox" name="selectedTickets[]" value="${index}" class="w-5 h-5 rounded border-gray-300 text-indigo-600">
                        <div class="flex-1">
                            <p class="font-medium text-gray-900">Bilet #${index + 1}</p>
                            <p class="text-sm text-gray-500">${t.type}${t.seat ? ' - ' + t.seat : ''}</p>
                        </div>
                        <span class="text-xs text-gray-400 font-mono">${t.code}</span>
                    </label>
                `;
            });

            listContainer.innerHTML = html;

            // Add selection styling
            document.querySelectorAll('.ticket-select-item input').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    this.closest('.ticket-select-item').classList.toggle('selected', this.checked);
                });
            });

            document.getElementById('transferModal').classList.remove('hidden');
            document.getElementById('transferModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeTransferModal() {
            document.getElementById('transferModal').classList.add('hidden');
            document.getElementById('transferModal').classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('transferForm').reset();
            currentTransferTicketId = null;
        }

        function submitTransfer(e) {
            e.preventDefault();

            const selectedTickets = document.querySelectorAll('#transferTicketsList input:checked');
            if (selectedTickets.length === 0) {
                alert('Te rugam sa selectezi cel putin un bilet pentru transfer.');
                return;
            }

            const btn = document.getElementById('transferSubmitBtn');
            btn.innerHTML = '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><span>Se proceseaza...</span>';
            btn.disabled = true;

            // Simulate transfer (in production this would be an API call)
            setTimeout(() => {
                alert('Biletele au fost transferate cu succes! Destinatarul va primi un email cu biletele.');
                closeTransferModal();
                btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>Transfera biletele';
                btn.disabled = false;
            }, 1500);
        }

        // Calendar Menu
        function toggleCalendarMenu(ticketId) {
            const menu = document.getElementById('calendarMenu-' + ticketId);
            const isVisible = menu.classList.contains('show');

            // Close all other menus
            document.querySelectorAll('.calendar-dropdown').forEach(m => {
                m.classList.remove('show');
                m.classList.add('hidden');
            });

            if (!isVisible) {
                menu.classList.remove('hidden');
                menu.classList.add('show');
            }
        }

        // Close calendar menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.relative')) {
                document.querySelectorAll('.calendar-dropdown').forEach(m => {
                    m.classList.remove('show');
                    m.classList.add('hidden');
                });
            }
        });

        // Close modals on backdrop click
        ['ticketModal', 'transferModal'].forEach(modalId => {
            document.getElementById(modalId).addEventListener('click', function(e) {
                if (e.target === this) {
                    if (modalId === 'ticketModal') closeTicketModal();
                    else closeTransferModal();
                }
            });
        });

        // Close modals on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTicketModal();
                closeTransferModal();
            }
        });
    </script>
</body>
</html>
