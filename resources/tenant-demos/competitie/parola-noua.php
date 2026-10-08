<?php
require_once __DIR__ . '/includes/boot.php';

$pageTitle = 'Parolă nouă — ' . SITE_SHORT;
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
    <div class="auth__form" x-data="passwordSet">
        <span class="label">Contul meu</span>
        <h1>Parolă nouă</h1>
        <p x-show="!done && valid">Alege parola cu care te vei autentifica de acum.</p>
        <div class="alert" x-show="!valid" x-cloak>Linkul nu este complet. Deschide-l din nou din email sau <a class="link" href="/recuperare-parola">cere altul</a>.</div>
        <form @submit.prevent="submit()" style="display:grid;gap:18px" x-show="!done && valid">
            <div class="field">
                <label for="n-pass">Parola nouă</label>
                <input id="n-pass" type="password" autocomplete="new-password" x-model="password" minlength="8" required>
                <small>Minimum 8 caractere.</small>
            </div>
            <div class="field">
                <label for="n-pass2">Repetă parola</label>
                <input id="n-pass2" type="password" autocomplete="new-password" x-model="password2" minlength="8" required>
            </div>
            <div class="alert" x-show="error" x-cloak role="alert">
                <span x-text="error"></span> <a class="link" href="/recuperare-parola" x-show="expired">Cere un link nou</a>
            </div>
            <button type="submit" class="btn btn--block" :disabled="busy">
                <span x-text="busy ? 'Un moment…' : 'Salvează parola'"></span>
            </button>
        </form>
        <div x-show="done" x-cloak>
            <div class="alert alert--ok" role="status">Parola a fost salvată. Te poți autentifica cu adresa <b x-text="email"></b>.</div>
            <p style="margin-top:24px"><a class="btn btn--block" href="/autentificare">Intră în cont</a></p>
        </div>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
