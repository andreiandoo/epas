<?php
require_once __DIR__ . '/includes/boot.php';

$pageTitle = 'Intră în cont — ' . SITE_SHORT;
$pageExtraHead = '<meta name="robots" content="noindex">';
include __DIR__ . '/includes/head.php';
?>
<main class="auth">
    <div class="auth__art on-dark">
        <h2>Biletele tale, la un loc</h2>
        <p>Toate comenzile pentru competițiile federației, cu codurile QR pregătite pentru intrare.</p>
    </div>
    <div class="auth__form" x-data="authForm('login')">
        <span class="eyebrow">Contul meu</span>
        <h1>Intră în cont</h1>
        <p>Vezi comenzile și biletele cumpărate cu adresa ta de email.</p>
        <form @submit.prevent="submit()" style="display:grid;gap:16px">
            <div class="field">
                <label for="a-email">Email</label>
                <input id="a-email" type="email" autocomplete="email" inputmode="email" x-model="form.email" required>
            </div>
            <div class="field">
                <label for="a-pass">Parolă</label>
                <input id="a-pass" type="password" autocomplete="current-password" x-model="form.password" required>
            </div>
            <div class="alert" x-show="error" x-cloak x-text="error" role="alert"></div>
            <button type="submit" class="btn btn--red btn--block" :disabled="busy">
                <span x-text="busy ? 'Un moment…' : 'Intră în cont'"></span>
            </button>
        </form>
        <p style="margin-top:22px;font-size:15px">Nu ai cont? <a class="link" href="/inregistrare">Creează unul</a></p>
        <p class="fine">Poți cumpăra bilete și fără cont: ai nevoie doar de o adresă de email.</p>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
