<?php
/**
 * Artist Account — Register
 * Artists self-register here to claim a profile or apply for one. Account
 * starts as `pending` server-side: requires email verification AND admin
 * approval before login is allowed.
 *
 * URL params:
 *   ?claim=<artist-slug>  — pre-fill the claim slug (set by the
 *                            "Revendică profilul" button on /artist/{slug})
 */
require_once dirname(__DIR__) . '/includes/config.php';

// Sanitize the optional claim slug (pre-filled into a hidden input).
$claimSlug = isset($_GET['claim']) ? preg_replace('/[^a-z0-9-]/i', '', strtolower($_GET['claim'])) : '';

$pageTitle = 'Cont artist — înregistrare';
$bodyClass = 'min-h-screen flex bg-surface';
$authTitle = $claimSlug ? 'Revendică-ți profilul' : 'Cont artist';
$authSubtitle = $claimSlug
    ? 'Completează datele pentru a revendica profilul. După verificare, vei putea edita informațiile publice.'
    : 'Creează-ți cont de artist pe ' . SITE_NAME . ' pentru a-ți gestiona profilul, evenimentele și informațiile publice.';
$authFeatures = [
    ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Cerere revizuită de echipa ' . SITE_NAME],
    ['icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'text' => 'Editezi profilul tău public'],
    ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'text' => 'Vezi evenimentele care te promovează'],
];

$cssBundle = 'auth';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/auth-branding.php';
?>

    <div class="flex items-center justify-center flex-1 p-8">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center lg:hidden">
                <a href="/" class="inline-flex items-center gap-3">
                    <div class="flex items-center justify-center w-12 h-12 bg-primary rounded-xl">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-extrabold text-secondary"><?= strtoupper(SITE_NAME) ?></span>
                </a>
            </div>

            <div class="p-8 bg-white border rounded-2xl border-border">
                <div class="mb-8 text-center">
                    <h2 class="text-2xl font-bold text-secondary"><?= $claimSlug ? 'Revendică profilul' : 'Cont artist' ?></h2>
                    <p class="mt-2 text-muted">
                        <?= $claimSlug
                            ? 'Profil revendicat: <span class="font-semibold text-primary">' . htmlspecialchars($claimSlug, ENT_QUOTES, 'UTF-8') . '</span>'
                            : 'Completează datele pentru a aplica' ?>
                    </p>
                </div>

                <?php if ($claimSlug): ?>
                <div id="claim-status" class="hidden p-3 mb-5 text-sm text-center rounded-lg"></div>
                <?php endif; ?>

                <form id="artist-register-form" class="space-y-5">
                    <input type="hidden" id="artist_slug" name="artist_slug" value="<?= htmlspecialchars($claimSlug, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block mb-2 text-sm font-medium text-secondary">Prenume</label>
                            <input type="text" id="first_name" name="first_name" required class="w-full input" placeholder="Ion">
                        </div>
                        <div>
                            <label for="last_name" class="block mb-2 text-sm font-medium text-secondary">Nume</label>
                            <input type="text" id="last_name" name="last_name" required class="w-full input" placeholder="Popescu">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block mb-2 text-sm font-medium text-secondary">Email</label>
                        <input type="email" id="email" name="email" required class="w-full input" placeholder="email@exemplu.ro" autocapitalize="none" autocorrect="off" spellcheck="false">
                    </div>

                    <div>
                        <label for="phone" class="block mb-2 text-sm font-medium text-secondary">Telefon (opțional)</label>
                        <input type="tel" id="phone" name="phone" class="w-full input" placeholder="0722 123 456">
                    </div>

                    <div>
                        <label for="password" class="block mb-2 text-sm font-medium text-secondary">Parolă</label>
                        <input type="password" id="password" name="password" required minlength="8" class="w-full input" placeholder="Minim 8 caractere">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block mb-2 text-sm font-medium text-secondary">Confirmă parola</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required class="w-full input" placeholder="Repetă parola">
                    </div>

                    <div>
                        <label for="claim_message" class="block mb-2 text-sm font-medium text-secondary">
                            <?= $claimSlug ? 'De ce ești tu titularul profilului?' : 'Mesaj (opțional)' ?>
                        </label>
                        <textarea id="claim_message" name="claim_message" rows="4" class="w-full input"
                            placeholder="<?= $claimSlug
                                ? 'Ex: Sunt managerul oficial al artistului, contractul de booking este înregistrat la...'
                                : 'Spune-ne câteva cuvinte despre tine sau echipa ta' ?>"></textarea>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium text-secondary">Linkuri de dovadă (opțional, max 5)</label>
                        <div id="proof-links" class="space-y-2">
                            <input type="url" name="claim_proof[]" class="w-full input" placeholder="https://instagram.com/contul-tau-oficial">
                        </div>
                        <button type="button" id="add-proof-link" class="mt-2 text-sm font-medium text-primary hover:underline">+ Adaugă încă un link</button>
                        <p class="mt-2 text-xs text-muted">Profil oficial pe rețele sociale, site, contracte de booking, etc.</p>
                    </div>

                    <div class="flex items-start">
                        <input type="checkbox" id="terms" name="terms" required class="w-4 h-4 mt-1 rounded text-primary border-border focus:ring-primary">
                        <label for="terms" class="ml-2 text-sm text-muted">
                            Sunt de acord cu <a href="/termeni" class="text-primary">Termenii și condițiile</a>
                            și <a href="/confidentialitate" class="text-primary">Politica de confidențialitate</a>
                        </label>
                    </div>

                    <button type="submit" class="w-full btn btn-primary bg-primary btn-lg" aria-label="Trimite cererea">
                        Trimite cererea
                    </button>
                </form>

                <p class="mt-6 text-xs text-center text-muted">
                    După înregistrare îți vom trimite un email de verificare. Cererea ta intră în review după ce confirmi adresa.
                </p>

                <p class="mt-6 text-center text-muted">
                    Ai deja cont? <a href="/artist/login" class="font-medium text-primary">Conectează-te</a>
                </p>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-register.js') . '"></script>';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
