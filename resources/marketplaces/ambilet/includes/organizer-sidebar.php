<?php
/**
 * Organizer Dashboard Sidebar
 */

$organizerMenuItems = [
    ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['page' => 'events', 'label' => 'Evenimentele mele', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ['page' => 'sales', 'label' => 'Vanzari', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ['page' => 'participants', 'label' => 'Participanti', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
    ['page' => 'finance', 'label' => 'Finante', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['page' => 'promo', 'label' => 'Coduri promotionale', 'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'],
    ['page' => 'settings', 'label' => 'Setari', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
    ['page' => 'help', 'label' => 'Ajutor', 'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
];

$currentPage = $currentPage ?? getCurrentPage();
?>

<aside class="lg:w-64 flex-shrink-0">
    <div class="bg-white rounded-2xl border border-border p-4 sticky top-24">
        <!-- Organizer Info -->
        <div class="flex items-center gap-3 pb-4 border-b border-border mb-4">
            <div class="w-12 h-12 bg-accent/10 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-secondary" id="organizer-name">Organizator</p>
                <p class="text-sm text-muted" id="organizer-type">Tip cont</p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-2 gap-2 pb-4 border-b border-border mb-4">
            <div class="text-center p-2 bg-surface rounded-lg">
                <p class="text-lg font-bold text-secondary" id="stat-events">0</p>
                <p class="text-xs text-muted">Evenimente</p>
            </div>
            <div class="text-center p-2 bg-surface rounded-lg">
                <p class="text-lg font-bold text-success" id="stat-sold">0</p>
                <p class="text-xs text-muted">Bilete vandute</p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="space-y-1">
            <?php foreach ($organizerMenuItems as $item):
                $isActive = $currentPage === $item['page'];
                $activeClass = $isActive ? 'bg-accent/10 text-accent font-medium' : 'text-muted hover:bg-surface hover:text-secondary';
            ?>
            <a href="/organizer/<?= $item['page'] ?>.php" class="flex items-center gap-3 px-3 py-2 rounded-lg <?= $activeClass ?> transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/>
                </svg>
                <?= $item['label'] ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- Create Event CTA -->
        <div class="mt-4 pt-4 border-t border-border">
            <a href="/organizer/events.php?action=create" class="flex items-center justify-center gap-2 w-full px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent/90 transition-colors font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Creeaza eveniment
            </a>
        </div>

        <!-- Logout -->
        <div class="mt-2">
            <button onclick="AmbiletAuth.logoutOrganizer()" class="flex items-center gap-3 px-3 py-2 rounded-lg text-error hover:bg-error/10 transition-colors w-full">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Deconectare
            </button>
        </div>
    </div>
</aside>
