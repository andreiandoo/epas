<?php
require_once __DIR__ . '/includes/boot.php';

$pageTitle = 'Setează parola — ' . SITE_SHORT;
$bodyClass = 'is-light';
$headSolid = true;
$pageExtraHead = '<meta name="robots" content="noindex">';
include __DIR__ . '/includes/head.php';
?>
<main class="is-light auth">
    <div class="auth__art">
        <span class="label" style="color:rgba(255,255,255,.7)">Contul meu</span>
        <h2 style="margin-top:18px">Biletele tale, la un loc</h2>
        <p>Toate comenzile pentru competițiile federației, cu codurile QR pregătite pentru intrare.</p>
    </div>
    <div class="auth__form" x-data="passwordLink">
        <span class="label">Contul meu</span>
        <h1>Setează parola</h1>
        <p>Scrie adresa de email cu care ai cumpărat sau ți-ai făcut cont. Îți trimitem un link cu care îți setezi o parolă nouă.</p>
        <form @submit.prevent="submit()" style="display:grid;gap:18px" x-show="!sent">
            <div class="field">
                <label for="r-email">Email</label>
                <input id="r-email" type="email" autocomplete="email" inputmode="email" spellcheck="false" autocapitalize="off" x-model="email" required>
            </div>
            <div class="alert" x-show="error" x-cloak x-text="error" role="alert"></div>
            <button type="submit" class="btn btn--block" :disabled="busy">
                <span x-text="busy ? 'Un moment…' : 'Trimite linkul'"></span>
            </button>
        </form>
        <div class="alert alert--ok" x-show="sent" x-cloak role="status">
            Dacă există un cont sau o comandă cu adresa <b x-text="email"></b>, linkul a plecat spre ea. E valabil 2 ore; verifică și folderul de spam.
        </div>
        <p style="margin-top:24px"><a class="link" href="/autentificare">Înapoi la autentificare</a></p>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
