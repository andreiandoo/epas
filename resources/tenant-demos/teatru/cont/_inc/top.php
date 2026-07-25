<?php
/**
 * Header + sidebar cont client. Necesită $navActive (cheia item-ului activ).
 */
$navGroups = require __DIR__ . '/nav.php';
$navActive = $navActive ?? '';

// clase pentru un item de sidebar
$itemClass = fn (bool $active) => $active
    ? 'flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition border-brass/30 bg-brass/10 text-brass-light'
    : 'flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition border-transparent text-paper/58 hover:bg-white/[.045] hover:text-paper';
?>
<header class="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-ink/92 backdrop-blur-xl">
    <div class="mx-auto flex h-[76px] max-w-[1500px] items-center justify-between gap-4 px-4 sm:px-6">
        <a href="/" class="flex min-w-0 items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-full border border-brass/70 bg-brass/5 font-display italic text-brass-light"><?= e(defined('SITE_LOGO_TEXT') ? SITE_LOGO_TEXT : 'TC') ?></span>
            <span class="min-w-0"><strong class="block truncate font-display text-lg"><?= e(SITE_NAME) ?></strong><small class="block text-[10px] font-bold tracking-[.22em] text-brass">CONTUL SPECTATORULUI</small></span>
        </a>
        <div class="flex items-center gap-2">
            <a href="/program" class="hidden rounded-full border border-white/10 px-4 py-2 text-xs text-paper/65 transition hover:border-brass/40 hover:text-paper sm:inline-flex">Vezi programul</a>
            <button @click="searchOpen=true;$nextTick(()=>$refs.search?.focus())" class="grid h-10 w-10 place-items-center rounded-full border border-white/10 text-paper/60 hover:border-brass/40 hover:text-brass-light" aria-label="Caută"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m21 21-4.4-4.4M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg></button>
            <a href="/cont/notificari" class="relative grid h-10 w-10 place-items-center rounded-full border border-white/10 text-paper/60 hover:border-brass/40 hover:text-brass-light" aria-label="Notificări"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 12h4"/></svg><span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-wine"></span></a>
            <button @click="mobileMenu=!mobileMenu" class="grid h-10 w-10 place-items-center rounded-full border border-white/10 lg:hidden" aria-label="Meniu cont"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 7h16M4 12h16M4 17h16"/></svg></button>
            <a href="/cont/profil" class="hidden items-center gap-2 rounded-full border border-white/10 py-1.5 pl-1.5 pr-3 lg:flex"><span class="grid h-8 w-8 place-items-center rounded-full bg-wine text-xs font-bold" x-text="initials">AP</span><span class="text-xs" x-text="firstName">Cont</span></a>
        </div>
    </div>
</header>

<!-- Mobile account nav (horizontal) -->
<div class="mobile-account-nav fixed inset-x-0 top-[76px] z-40 border-b border-white/10 bg-coal/95 px-4 py-3 backdrop-blur lg:hidden">
    <div class="flex gap-2 overflow-x-auto">
        <?php foreach ($navGroups as $items): foreach ($items as $it):
            $active = $it['key'] === $navActive; ?>
            <a href="<?= e($it['url']) ?>" class="whitespace-nowrap rounded-full border px-4 py-2 text-xs <?= $active ? 'border-brass/40 bg-brass/10 text-brass-light' : 'border-white/10 text-paper/55' ?>"><?= e($it['label']) ?></a>
        <?php endforeach; endforeach; ?>
    </div>
</div>

<main id="main" class="pt-[136px] lg:pt-[76px]">
    <div class="mx-auto grid max-w-[1500px] lg:grid-cols-[278px_minmax(0,1fr)]">
        <aside class="hidden min-h-[calc(100vh-76px)] border-r border-white/8 bg-coal/45 px-4 py-6 lg:block">
            <div class="sticky top-[100px]">
                <div class="mb-4 flex items-center gap-3 rounded-2xl border border-white/8 bg-white/[.025] p-4">
                    <span class="grid h-12 w-12 place-items-center rounded-full bg-wine font-display text-lg" x-text="initials">AP</span>
                    <div class="min-w-0"><strong class="block truncate" x-text="userName">Cont client</strong><small class="block truncate text-paper/35" x-text="userEmail">—</small></div>
                </div>
                <nav>
                    <?php foreach ($navGroups as $group => $items): ?>
                    <p class="mb-2 mt-5 px-3 text-[10px] font-bold tracking-[.18em] text-paper/25"><?= e($group) ?></p>
                    <?php foreach ($items as $it): $active = $it['key'] === $navActive; ?>
                    <a href="<?= e($it['url']) ?>"<?= $active ? ' aria-current="page"' : '' ?> class="<?= $itemClass($active) ?>"><?= $it['icon'] ?><span><?= e($it['label']) ?></span><?= $active ? '<span class="ml-auto h-1.5 w-1.5 rounded-full bg-brass"></span>' : '' ?></a>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </nav>
                <a href="#" @click.prevent="logout()" class="mt-6 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-paper/35 hover:bg-white/[.04] hover:text-paper"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M10 17l5-5-5-5m5 5H3m12-9h5v18h-5"/></svg>Ieșire din cont</a>
            </div>
        </aside>
        <section class="min-w-0 px-5 pb-24 pt-8 sm:px-8 lg:px-10 lg:pb-16 lg:pt-12">
