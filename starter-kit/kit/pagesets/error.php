<?php
/**
 * PAGESET: error — generic error page. $PAGE['code'] selects the message.
 * Used for 403/500/503 (404 has its own pageset with kind-appropriate copy).
 */
$PAGE = $PAGE ?? [];
$code = (int)($PAGE['code'] ?? 500);
$icons = [403 => '⛔', 500 => '⚠️', 503 => '🛠️'];
$c = in_array($code, [403, 500, 503], true) ? $code : 500;
$icon  = $icons[$c];
$title = t("error.$c.title");
$msg   = t("error.$c.msg");
http_response_code($code);
layout('public', ['title' => $code . ' — ' . $title], function () use ($icon, $title, $msg) { ?>
  <section class="kit-section"><div class="kit-container" style="text-align:center;padding:4rem 1rem">
    <div style="font-size:3rem"><?= $icon ?></div>
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin:.5rem 0"><?= e($title) ?></h1>
    <p class="kit-muted" style="max-width:32rem;margin:0 auto 1.5rem"><?= e($msg) ?></p>
    <a href="/" class="kit-btn kit-btn--primary">Acasă</a>
  </div></section>
<?php });
