<?php
/**
 * Auth Pages Left Branding Panel
 *
 * Variables:
 * - $authTitle: Main heading
 * - $authSubtitle: Description text
 * - $authFeatures: Array of features to display (optional)
 */

$authTitle = $authTitle ?? 'Bine ai venit!';
$authSubtitle = $authSubtitle ?? 'Accesează contul tău pentru a vedea biletele, a descoperi evenimente noi și a folosi punctele acumulate.';
$authFeatures = $authFeatures ?? [
    ['icon' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z', 'text' => 'Bilete digitale cu cod QR'],
    ['icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Acumulează puncte la fiecare achiziție'],
    ['icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', 'text' => 'Salvează evenimentele preferate'],
];
?>

<div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary via-primary-dark to-secondary relative overflow-hidden">
    <div class="absolute inset-0">
        <div class="absolute top-20 left-20 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-accent/10 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 flex flex-col justify-between p-12 text-white">
        <div>
            <a href="/" class="flex items-center gap-3">
                <div class="w-12 h-12 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                </div>
                <span class="text-2xl font-extrabold"><?= strtoupper(SITE_NAME) ?></span>
            </a>
        </div>

        <div>
            <h1 class="text-4xl font-bold mb-4"><?= htmlspecialchars($authTitle) ?></h1>
            <p class="text-lg text-white/80 mb-8"><?= htmlspecialchars($authSubtitle) ?></p>

            <div class="space-y-4">
                <?php foreach ($authFeatures as $feature): ?>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $feature['icon'] ?>"/>
                        </svg>
                    </div>
                    <span class="text-white/90"><?= htmlspecialchars($feature['text']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center gap-6">
            <a href="/organizer/landing.php" class="text-white/60 hover:text-white transition-colors">
                Ești organizator?
            </a>
            <a href="/help.php" class="text-white/60 hover:text-white transition-colors">
                Ajutor
            </a>
        </div>
    </div>
</div>
