<?php
require_once TEMPLATE_DIR . '/lib.php';
?>
<section class="sec">
    <div class="wrap" style="max-width:860px">
        <?php if (!empty($s['eyebrow'])): ?><span class="label" data-reveal><?= e($s['eyebrow']) ?></span><?php endif; ?>
        <?php if (!empty($s['title'])): ?><h2 data-split style="margin:14px 0 22px"><?= comp_md($s['title']) ?></h2><?php endif; ?>
        <div data-reveal style="display:grid;gap:18px;color:var(--fg-2);font-size:19px"><?= ts_paragraphs($s['text'] ?? '') ?></div>
        <?php if (!empty($s['button_label']) && !empty($s['button_url'])): ?>
        <p data-reveal style="margin-top:28px"><?= part_btn((string) $s['button_label'], ts_href($s['button_url'])) ?></p>
        <?php endif; ?>
    </div>
</section>
