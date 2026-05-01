<?php
/**
 * Artist Account — Settings
 * Same dark-sidebar layout as the dashboard/detalii/evenimente pages.
 * Three sections: profile fields, password change, danger zone (delete).
 * Email is read-only; updating it is admin-mediated for now.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$pageTitle = 'Cont Artist — Setări';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 2) . '/includes/head.php';
?>

<?php require __DIR__ . '/_partials/sidebar.php'; ?>

<main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen">
    <div class="p-4 lg:p-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Setări cont</h1>
            <p class="mt-1 text-muted">Gestionează informațiile contului tău și securitatea.</p>
        </div>

        <div class="mx-auto max-w-3xl space-y-6">
            <!-- Account fields -->
            <section class="rounded-2xl border border-border bg-white p-6">
                <div class="mb-6 flex items-start gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                        <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Informații cont</h2>
                        <p class="text-sm text-muted">Numele și datele de contact ale persoanei care administrează profilul.</p>
                    </div>
                </div>

                <form id="account-form" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="first_name" class="mb-2 block text-sm font-medium text-secondary">Prenume</label>
                            <input type="text" id="first_name" name="first_name" required maxlength="100" class="input">
                        </div>
                        <div>
                            <label for="last_name" class="mb-2 block text-sm font-medium text-secondary">Nume</label>
                            <input type="text" id="last_name" name="last_name" required maxlength="100" class="input">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-secondary">Email</label>
                        <input type="email" id="email" name="email" disabled class="input cursor-not-allowed bg-surface text-muted">
                        <p class="mt-1 text-xs text-muted">Pentru a schimba adresa de email, contactează echipa <?= SITE_NAME ?> la <a href="mailto:contact@ambilet.ro" class="underline">contact@ambilet.ro</a>.</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="phone" class="mb-2 block text-sm font-medium text-secondary">Telefon (opțional)</label>
                            <input type="tel" id="phone" name="phone" maxlength="50" class="input">
                        </div>
                        <div>
                            <label for="locale" class="mb-2 block text-sm font-medium text-secondary">Limbă</label>
                            <select id="locale" name="locale" class="input pr-10">
                                <option value="ro">Română</option>
                                <option value="en">English</option>
                                <option value="de">Deutsch</option>
                                <option value="fr">Français</option>
                                <option value="es">Español</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-border pt-4">
                        <button type="submit" class="btn btn-primary inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-white shadow-md transition-all hover:bg-primary-dark hover:shadow-lg">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Salvează modificările
                        </button>
                    </div>
                </form>
            </section>

            <!-- Password change -->
            <section class="rounded-2xl border border-border bg-white p-6">
                <div class="mb-6 flex items-start gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-warning/10">
                        <svg class="h-5 w-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Schimbă parola</h2>
                        <p class="text-sm text-muted">Sesiunile active pe alte device-uri vor fi deconectate automat.</p>
                    </div>
                </div>

                <form id="password-form" class="space-y-4">
                    <div>
                        <label for="current_password" class="mb-2 block text-sm font-medium text-secondary">Parola actuală</label>
                        <input type="password" id="current_password" name="current_password" required class="input" autocomplete="current-password">
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="new_password" class="mb-2 block text-sm font-medium text-secondary">Parola nouă</label>
                            <input type="password" id="new_password" name="password" required minlength="8" class="input" autocomplete="new-password">
                        </div>
                        <div>
                            <label for="new_password_confirmation" class="mb-2 block text-sm font-medium text-secondary">Confirmă parola nouă</label>
                            <input type="password" id="new_password_confirmation" name="password_confirmation" required minlength="8" class="input" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="border-t border-border pt-4">
                        <button type="submit" class="btn btn-primary inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-white shadow-md transition-all hover:bg-primary-dark hover:shadow-lg">Schimbă parola</button>
                    </div>
                </form>
            </section>

            <!-- Danger zone -->
            <section class="rounded-2xl border-2 border-error/20 bg-error/5 p-6">
                <div class="mb-4 flex items-start gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-error/10">
                        <svg class="h-5 w-5 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-error">Zonă periculoasă</h2>
                        <p class="text-sm text-error/80">Ștergerea contului este permanentă. Profilul public de artist NU se șterge — doar contul tău este eliminat.</p>
                    </div>
                </div>
                <button type="button" id="delete-account-btn" class="rounded-xl border border-error bg-white px-4 py-2 text-sm font-semibold text-error transition-colors hover:bg-error hover:text-white">
                    Șterge contul
                </button>
            </section>
        </div>
    </div>

    <!-- Delete confirmation modal -->
    <div id="delete-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6">
            <h3 class="mb-2 text-lg font-bold text-secondary">Confirmă ștergerea</h3>
            <p class="mb-4 text-sm text-muted">Această acțiune este permanentă. Introdu parola pentru a confirma.</p>
            <input type="password" id="delete-password" placeholder="Parola" class="input mb-4" autocomplete="off">
            <div class="flex justify-end gap-2">
                <button type="button" id="delete-cancel" class="btn btn-secondary inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-white px-4 py-2 text-sm font-semibold text-secondary transition-colors hover:bg-surface">Renunță</button>
                <button type="button" id="delete-confirm" class="btn rounded-xl bg-error px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Șterge definitiv</button>
            </div>
        </div>
    </div>
</main>

<?php
$scriptsExtra = ''
    . '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>'
    . '<script defer src="' . asset('assets/js/pages/artist-cont-setari.js') . '"></script>';
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>
