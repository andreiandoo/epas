<?php
/** Antetul unei pagini interioare: supratitlu, titlu pe rânduri, text, imagine, buton. */
require_once TEMPLATE_DIR . '/lib.php';
$lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($s['title'] ?? '')))));
$img = trim((string) ($s['image'] ?? ''));
$small = ($s['size'] ?? 'large') === 'small';
?>
    <div class="fixed inset-x-0 top-0 z-[140] h-[3px]"><div id="page-progress" class="progress-line h-full w-full bg-acid"></div></div>
    <section class="grain topo-dark relative overflow-hidden bg-pine-950 text-white <?= $small ? 'pb-14 pt-32 sm:pb-16 sm:pt-36' : 'pb-16 pt-32 sm:pb-24 sm:pt-40 lg:min-h-[78svh]' ?>">
        <div class="glow-orbit -left-[140px] top-[20%] h-[420px] w-[420px]"></div>
        <div class="glow-orbit -right-[180px] bottom-[5%] h-[520px] w-[520px]"></div>
        <div class="relative z-10 mx-auto grid max-w-[1540px] gap-10 px-4 sm:px-6 lg:px-8 xl:px-12 <?= $img !== '' && !$small ? 'lg:grid-cols-[1fr_.9fr] lg:items-center lg:gap-14' : '' ?>">
            <div class="min-w-0">
                <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?>
                <div data-hero-kicker class="mb-5 inline-flex max-w-full items-center gap-2 rounded-full border border-white/[.14] bg-white/[.07] px-3 py-2 text-[9px] font-bold uppercase tracking-[.2em] text-white/[.74] sm:px-4 sm:text-[10px] sm:tracking-[.24em]"><span class="pulse-dot h-2 w-2 flex-none rounded-full bg-acid"></span><span class="truncate"><?= e($s['eyebrow']) ?></span></div>
                <?php endif; ?>
                <h1 class="font-display <?= $small ? 'text-[clamp(44px,8vw,84px)]' : 'text-[clamp(50px,11vw,92px)] lg:text-[clamp(70px,7vw,118px)]' ?> font-semibold leading-[.92] tracking-[-.052em]">
                    <?php foreach ($lines as $i => $line): ?><span data-hero-title class="block pb-1 <?= $i === 1 ? 'text-acid' : ($i === 2 ? 'outline-word italic' : '') ?>"><?= ts_md($line) ?></span><?php endforeach; ?>
                </h1>
                <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p data-hero-copy class="mt-7 max-w-2xl text-[15px] leading-7 text-white/[.66] sm:text-lg sm:leading-8"><?= ts_md($s['lead']) ?></p><?php endif; ?>
                <?php if (trim((string) ($s['button_label'] ?? '')) !== ''): ?>
                <div data-hero-cta class="mt-8"><a href="<?= e(ts_href($s['button_url'] ?? '', '#')) ?>" class="inline-flex min-h-[52px] items-center justify-center gap-2 whitespace-nowrap rounded-full bg-acid px-6 text-sm font-bold text-pine-950 shadow-acid transition hover:-translate-y-1 sm:text-base"><?= e($s['button_label']) ?> <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></a></div>
                <?php endif; ?>
            </div>
            <?php if ($img !== '' && !$small): ?>
            <div data-hero-visual class="relative min-h-[380px] sm:min-h-[480px] lg:min-h-[560px]">
                <div class="hero-mask absolute inset-0 overflow-hidden border border-white/10 bg-pine-850 shadow-lift">
                    <img id="hero-bg" src="<?= e($img) ?>" alt="" class="h-full w-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-pine-950/70 via-transparent to-transparent"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
