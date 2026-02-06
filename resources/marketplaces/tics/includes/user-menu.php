<?php
/**
 * User Menu Component
 * Shared header user dropdown menu for logged-in users
 */

// Demo user data (in production this would come from session/database)
$demoUser = [
    'id' => 1,
    'name' => 'Alexandru Marin',
    'firstName' => 'Alexandru',
    'email' => 'alexandru.marin@example.com',
    'avatar' => 'https://i.pravatar.cc/40?img=68',
    'avatarLarge' => 'https://i.pravatar.cc/60?img=68',
    'points' => 1250,
    'memberSince' => '2024',
    'ticketsCount' => 3,
    'eventsAttended' => 12,
    'favorites' => 7
];

$currentUser = $demoUser;
?>

<!-- User Menu Dropdown -->
<div class="relative" id="userMenu">
    <button onclick="toggleUserMenu()" class="flex items-center gap-3 p-1.5 hover:bg-gray-100 rounded-xl transition-colors">
        <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" class="w-8 h-8 rounded-lg object-cover" alt="<?= htmlspecialchars($currentUser['firstName']) ?>">
        <div class="hidden sm:block text-left">
            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($currentUser['firstName']) ?></p>
            <p class="text-xs text-gray-500"><?= number_format($currentUser['points']) ?> puncte</p>
        </div>
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <!-- Dropdown -->
    <div id="userDropdown" class="hidden absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-2 z-50">
        <a href="/cont" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
            Dashboard
        </a>
        <a href="/cont/bilete" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            Biletele mele
        </a>
        <a href="/cont/comenzi" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Comenzile mele
        </a>
        <div class="border-t border-gray-100 my-2"></div>
        <a href="/cont/setari" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Setari
        </a>
        <div class="border-t border-gray-100 my-2"></div>
        <a href="/deconectare" class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Deconectare
        </a>
    </div>
</div>

<script>
function toggleUserMenu() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('hidden');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const menu = document.getElementById('userMenu');
    const dropdown = document.getElementById('userDropdown');
    if (menu && dropdown && !menu.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});
</script>
