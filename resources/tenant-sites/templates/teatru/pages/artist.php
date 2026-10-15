<?php

$slug = $_GET['id'] ?? $_GET['slug'] ?? '';
$artist = $slug !== '' ? tc_artist((string) $slug) : null;

if (!$artist) {
    http_response_code(404);
    $pageTitle = 'Artist inexistent — ' . SITE_NAME;
    include ts_layout('head');
    include ts_layout('header');
    ?>
    <section class="pt-40 pb-32 px-4 text-center">
        <h1 class="font-display text-4xl mb-4">Artistul nu a fost găsit</h1>
        <p class="text-muted mb-8">Este posibil ca profilul să fi fost mutat sau eliminat.</p>
        <a href="/trupa" class="btn-primary px-6 py-3 rounded-lg inline-block">Înapoi la trupă</a>
    </section>
    <?php
    include ts_layout('footer');
    return;
}

$name    = $artist['name'] ?? '';
$role    = $artist['role'] ?? '';
$photo   = $artist['photo_url'] ?? null;
$bioHtml = trim((string) ($artist['bio'] ?? ''));
$gallery = array_values(array_filter($artist['gallery'] ?? []));

$pageTitle = $name . ' — ' . SITE_NAME;
$placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='533'%3E%3Crect width='400' height='533' fill='%23181818'/%3E%3Cpath d='M200 150a50 50 0 100 100 50 50 0 000-100zm0 125c-57 0-102 28-102 64v10h204v-10c0-36-45-64-102-64z' fill='%23D4AF37' opacity='.35'/%3E%3C/svg%3E";

include ts_layout('head');
include ts_layout('header');
?>
<section class="pt-28 pb-2 px-4 lg:px-8">
    <div class="max-w-5xl mx-auto text-sm text-muted">
        <a href="/trupa" class="hover:text-primary transition-colors">Trupa</a>
        <span class="mx-2">/</span><span class="text-fg/70"><?= e($name) ?></span>
    </div>
</section>

<section class="py-8 px-4 lg:px-8">
    <div class="max-w-5xl mx-auto grid md:grid-cols-[320px_1fr] gap-10 items-start">
        <div class="rounded-xl overflow-hidden border border-primary/10 bg-surface">
            <img src="<?= e($photo ?: $placeholder) ?>" alt="<?= e($name) ?>" class="w-full aspect-[3/4] object-cover">
        </div>
        <div>
            <?php if ($role): ?>
            <p class="text-primary tracking-[0.2em] text-sm mb-3 uppercase"><?= e($role) ?></p>
            <?php endif; ?>
            <h1 class="font-display text-4xl lg:text-5xl mb-6"><?= e($name) ?></h1>
            <?php if ($bioHtml !== ''): ?>
            <div class="prose prose-invert prose-lg max-w-none text-fg/70 space-y-4">
                <?= $bioHtml ?>
            </div>
            <?php else: ?>
            <p class="text-muted">Biografie în curs de completare.</p>
            <?php endif; ?>
            <div class="flex flex-wrap items-center gap-4 mt-8">
                <a href="/program" class="btn-primary px-6 py-3 rounded-lg">Vezi spectacolele</a>
                <button x-data="favBtn('artist', <?= (int) ($artist['id'] ?? $slug) ?>, <?= htmlspecialchars(json_encode(['title' => $name, 'id' => $artist['id'] ?? $slug, 'role' => $role], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)" x-init="init()" @click="toggle()" :aria-pressed="on"
                    class="inline-flex items-center gap-2 rounded-lg border px-6 py-3 transition-colors"
                    :class="on ? 'border-primary bg-primary/15 text-primary' : 'border-primary/40 text-primary hover:bg-primary hover:text-canvas'">
                    <svg class="h-5 w-5" :fill="on ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 21s-7.5-4.6-10-9.3C.5 8.4 2 4.8 5.3 4.8c2 0 3.3 1.2 4.2 2.5.9-1.3 2.2-2.5 4.2-2.5 3.3 0 4.8 3.6 3.3 6.9C19.5 16.4 12 21 12 21Z"/></svg>
                    <span x-text="on ? 'Urmărești' : 'Urmărește'">Urmărește</span>
                </button>
                <a href="/trupa" class="btn-outline px-6 py-3 rounded-lg">Înapoi la trupă</a>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($gallery)): ?>
<!-- Galerie foto -->
<section class="pb-24 px-4 lg:px-8">
    <div class="max-w-5xl mx-auto">
        <div class="divider-ornate mb-10"><span class="text-primary font-display text-2xl">Galerie</span></div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <?php foreach ($gallery as $img): ?>
            <a href="<?= e($img) ?>" target="_blank" class="block aspect-[4/3] overflow-hidden rounded-lg border border-primary/10 group">
                <img src="<?= e($img) ?>" alt="<?= e($name) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include ts_layout('footer');
