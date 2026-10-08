<?php
require_once __DIR__ . '/includes/boot.php';

$pageTitle = 'Profilul meu — ' . SITE_SHORT;
$bodyClass = 'is-light';
$pageExtraHead = '<meta name="robots" content="noindex">';
include __DIR__ . '/includes/head.php';
?>
<main class="is-light" x-data="profilePage" style="min-height:80vh">
    <?= part_account_head('Profilul meu', 'profil') ?>

    <div class="wrap">
        <section class="split" style="grid-template-columns:minmax(0,1fr);max-width:760px">
            <form class="panel" @submit.prevent="save()" novalidate>
                <div class="panel__head"><h2>Date personale</h2></div>
                <div class="panel__body" style="display:grid;gap:18px">
                    <div class="fields fields--2">
                        <div class="field">
                            <label for="p-first">Prenume</label>
                            <input id="p-first" type="text" autocomplete="given-name" x-model="form.first_name" required>
                        </div>
                        <div class="field">
                            <label for="p-last">Nume</label>
                            <input id="p-last" type="text" autocomplete="family-name" x-model="form.last_name" required>
                        </div>
                        <div class="field">
                            <label for="p-email">Email</label>
                            <input id="p-email" type="email" :value="email" disabled>
                            <small>Adresa contului nu se poate schimba de aici.</small>
                        </div>
                        <div class="field">
                            <label for="p-phone">Telefon <em>(opțional)</em></label>
                            <input id="p-phone" type="tel" autocomplete="tel" inputmode="tel" x-model="form.phone">
                        </div>
                    </div>
                    <div class="alert alert--ok" x-show="msg" x-cloak x-text="msg" role="status"></div>
                    <div class="alert" x-show="err" x-cloak x-text="err" role="alert"></div>
                    <div><button type="submit" class="btn" :disabled="saving" x-text="saving ? 'Se salvează…' : 'Salvează datele'">Salvează datele</button></div>
                </div>
            </form>

            <form class="panel" @submit.prevent="changePassword()" novalidate>
                <div class="panel__head"><h2>Parolă</h2></div>
                <div class="panel__body" style="display:grid;gap:18px">
                    <div class="field">
                        <label for="p-cur">Parola curentă</label>
                        <input id="p-cur" type="password" autocomplete="current-password" x-model="pw.current_password" required>
                    </div>
                    <div class="fields fields--2">
                        <div class="field">
                            <label for="p-new">Parola nouă</label>
                            <input id="p-new" type="password" autocomplete="new-password" x-model="pw.password" minlength="8" required>
                            <small>Minimum 8 caractere.</small>
                        </div>
                        <div class="field">
                            <label for="p-new2">Repetă parola nouă</label>
                            <input id="p-new2" type="password" autocomplete="new-password" x-model="pw.password_confirmation" minlength="8" required>
                        </div>
                    </div>
                    <div class="alert alert--ok" x-show="pwMsg" x-cloak x-text="pwMsg" role="status"></div>
                    <div class="alert" x-show="pwErr" x-cloak x-text="pwErr" role="alert"></div>
                    <div><button type="submit" class="btn" :disabled="pwSaving" x-text="pwSaving ? 'Se schimbă…' : 'Schimbă parola'">Schimbă parola</button></div>
                </div>
            </form>
        </section>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
