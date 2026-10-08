<?php
require_once __DIR__ . '/includes/boot.php';

$pageTitle = 'Cont nou — ' . SITE_SHORT;
$pageExtraHead = '<meta name="robots" content="noindex">';
include __DIR__ . '/includes/head.php';
?>
<main class="auth">
    <div class="auth__art on-dark">
        <h2>Biletele tale, la un loc</h2>
        <p>Toate comenzile pentru competițiile federației, cu codurile QR pregătite pentru intrare.</p>
    </div>
    <div class="auth__form" x-data="authForm('register')">
        <span class="eyebrow">Contul meu</span>
        <h1>Cont nou</h1>
        <p>Cu un cont găsești oricând biletele cumpărate, fără să cauți prin email.</p>
        <form @submit.prevent="submit()" style="display:grid;gap:16px">
            <div class="fields fields--2">
                <div class="field">
                    <label for="a-first">Prenume</label>
                    <input id="a-first" type="text" autocomplete="given-name" x-model="form.first_name" required>
                </div>
                <div class="field">
                    <label for="a-last">Nume</label>
                    <input id="a-last" type="text" autocomplete="family-name" x-model="form.last_name" required>
                </div>
            </div>
            <div class="field">
                <label for="a-email">Email</label>
                <input id="a-email" type="email" autocomplete="email" inputmode="email" x-model="form.email" required>
            </div>
            <div class="field">
                <label for="a-pass">Parolă</label>
                <input id="a-pass" type="password" autocomplete="new-password" x-model="form.password" required minlength="8">
                <small>Minimum 8 caractere.</small>
            </div>
            <div class="alert" x-show="error" x-cloak x-text="error" role="alert"></div>
            <button type="submit" class="btn btn--red btn--block" :disabled="busy">
                <span x-text="busy ? 'Un moment…' : 'Creează contul'"></span>
            </button>
        </form>
        <p style="margin-top:22px;font-size:15px">Ai deja cont? <a class="link" href="/autentificare">Intră în cont</a></p>
        <p class="fine">Poți cumpăra bilete și fără cont: ai nevoie doar de o adresă de email.</p>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
