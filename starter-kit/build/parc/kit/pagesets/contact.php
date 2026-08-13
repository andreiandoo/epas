<?php
/** PAGESET: contact — form (+ booking note if the kind supports booking). */
$PAGE = $PAGE ?? [];
layout('public', ['title' => 'Contact — ' . kit_cfg('site_name'), 'nav' => $PAGE['nav'] ?? 'contact'],
function () { ?>
  <section class="kit-section"><div class="kit-container" style="max-width:40rem">
    <h1 class="kit-display" style="font-size:clamp(2rem,5vw,3rem);margin-bottom:1rem">Contact<?= kit_feature('booking') ? ' / Booking' : '' ?></h1>
    <?php if (kit_feature('booking')): ?>
      <p class="kit-muted" style="margin-bottom:1.5rem">Pentru solicitări de booking, completează formularul — revenim în cel mai scurt timp.</p>
    <?php endif; ?>
    <form onsubmit="event.preventDefault(); alert('Demo — conectează un endpoint de contact');" style="display:flex;flex-direction:column;gap:.75rem">
      <input class="kit-search" style="max-width:none" name="name" placeholder="Nume" required>
      <input class="kit-search" style="max-width:none" type="email" name="email" placeholder="Email" required>
      <textarea class="kit-search" style="max-width:none;min-height:120px" name="msg" placeholder="Mesaj" required></textarea>
      <button class="kit-btn kit-btn--primary">Trimite</button>
    </form>
  </div></section>
<?php });
