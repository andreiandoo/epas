<?php
/**
 * Artist Account — Settings
 * Three forms: profile fields (name/phone/locale), password change,
 * and account deletion (with password confirmation).
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$pageTitle = 'Cont Artist — Setări';
$bodyClass = 'min-h-screen bg-surface';
$cssBundle = 'account';
require_once dirname(__DIR__, 2) . '/includes/head.php';
?>

<div class="flex min-h-screen">
    <?php require __DIR__ . '/_partials/sidebar.php'; ?>
    <div class="flex flex-col flex-1 min-w-0">
        <?php require __DIR__ . '/_partials/header.php'; ?>

        <main class="flex-1 p-6 lg:p-10">
            <div class="max-w-3xl mx-auto">
                <h1 class="mb-2 text-3xl font-bold text-secondary">Setări cont</h1>
                <p class="mb-8 text-muted">Gestionează informațiile contului tău.</p>

                <!-- Profile fields -->
                <section class="p-6 mb-6 bg-white border rounded-2xl border-border">
                    <h2 class="mb-4 text-lg font-semibold text-secondary">Informații cont</h2>
                    <form id="account-form" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="first_name" class="block mb-2 text-sm font-medium text-secondary">Prenume</label>
                                <input type="text" id="first_name" name="first_name" required maxlength="100" class="w-full input">
                            </div>
                            <div>
                                <label for="last_name" class="block mb-2 text-sm font-medium text-secondary">Nume</label>
                                <input type="text" id="last_name" name="last_name" required maxlength="100" class="w-full input">
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block mb-2 text-sm font-medium text-secondary">Email</label>
                            <input type="email" id="email" name="email" disabled class="w-full input bg-surface text-muted">
                            <p class="mt-1 text-xs text-muted">Pentru a schimba adresa de email, contactează echipa <?= SITE_NAME ?>.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="phone" class="block mb-2 text-sm font-medium text-secondary">Telefon (opțional)</label>
                                <input type="tel" id="phone" name="phone" maxlength="50" class="w-full input">
                            </div>
                            <div>
                                <label for="locale" class="block mb-2 text-sm font-medium text-secondary">Limbă</label>
                                <select id="locale" name="locale" class="w-full input">
                                    <option value="ro">Română</option>
                                    <option value="en">English</option>
                                    <option value="de">Deutsch</option>
                                    <option value="fr">Français</option>
                                    <option value="es">Español</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="text-white btn bg-primary btn-primary">
                            Salvează modificările
                        </button>
                    </form>
                </section>

                <!-- Password change -->
                <section class="p-6 mb-6 bg-white border rounded-2xl border-border">
                    <h2 class="mb-4 text-lg font-semibold text-secondary">Schimbă parola</h2>
                    <form id="password-form" class="space-y-4">
                        <div>
                            <label for="current_password" class="block mb-2 text-sm font-medium text-secondary">Parola actuală</label>
                            <input type="password" id="current_password" name="current_password" required class="w-full input" autocomplete="current-password">
                        </div>
                        <div>
                            <label for="new_password" class="block mb-2 text-sm font-medium text-secondary">Parola nouă</label>
                            <input type="password" id="new_password" name="password" required minlength="8" class="w-full input" autocomplete="new-password">
                        </div>
                        <div>
                            <label for="new_password_confirmation" class="block mb-2 text-sm font-medium text-secondary">Confirmă parola nouă</label>
                            <input type="password" id="new_password_confirmation" name="password_confirmation" required minlength="8" class="w-full input" autocomplete="new-password">
                        </div>
                        <button type="submit" class="text-white btn bg-primary btn-primary">
                            Schimbă parola
                        </button>
                    </form>
                </section>

                <!-- Danger zone -->
                <section class="p-6 border-2 border-red-200 rounded-2xl bg-red-50">
                    <h2 class="mb-2 text-lg font-semibold text-red-900">Zonă periculoasă</h2>
                    <p class="mb-4 text-sm text-red-800">
                        Ștergerea contului este permanentă. Profilul public de artist NU se șterge — doar contul tău este eliminat.
                    </p>
                    <button id="delete-account-btn" class="text-red-700 bg-white border border-red-300 btn hover:bg-red-100">
                        Șterge contul
                    </button>
                </section>

                <!-- Delete confirmation modal -->
                <div id="delete-modal" class="hidden fixed inset-0 z-50 items-center justify-center p-4 bg-black/50">
                    <div class="w-full max-w-md p-6 bg-white rounded-2xl">
                        <h3 class="mb-2 text-lg font-bold text-secondary">Confirmă ștergerea</h3>
                        <p class="mb-4 text-sm text-muted">Această acțiune este permanentă. Introdu parola pentru a confirma.</p>
                        <input type="password" id="delete-password" placeholder="Parola" class="w-full mb-4 input" autocomplete="off">
                        <div class="flex justify-end gap-2">
                            <button id="delete-cancel" class="btn">Renunță</button>
                            <button id="delete-confirm" class="text-white bg-red-600 btn hover:bg-red-700">Șterge definitiv</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
$scriptsExtra = ''
    . '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>'
    . '<script defer src="' . asset('assets/js/pages/artist-cont-setari.js') . '"></script>';
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>
