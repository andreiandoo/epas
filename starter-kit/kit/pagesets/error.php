<?php
/**
 * PAGESET: error — generic error page. $PAGE['code'] selects the message.
 * Used for 403/500/503 (404 has its own pageset with kind-appropriate copy).
 */
$PAGE = $PAGE ?? [];
$code = (int)($PAGE['code'] ?? 500);
$map = [
    403 => ['⛔', 'Acces interzis', 'Nu ai permisiunea de a accesa această pagină.'],
    500 => ['⚠️', 'Eroare de server', 'Ceva n-a mers bine. Încearcă din nou în câteva momente.'],
    503 => ['🛠️', 'Mentenanță', 'Site-ul este temporar în mentenanță. Revenim imediat.'],
];
[$icon, $title, $msg] = $map[$code] ?? $map[500];
http_response_code($code);
layout('public', ['title' => $code . ' — ' . $title], function () use ($icon, $title, $msg) { ?>
  <section class="kit-section"><div class="kit-container" style="text-align:center;padding:4rem 1rem">
    <div style="font-size:3rem"><?= $icon ?></div>
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin:.5rem 0"><?= e($title) ?></h1>
    <p class="kit-muted" style="max-width:32rem;margin:0 auto 1.5rem"><?= e($msg) ?></p>
    <a href="/" class="kit-btn kit-btn--primary">Acasă</a>
  </div></section>
<?php });
