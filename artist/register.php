<?php
/**
 * Artist Account — Register
 * Two flows handled by the same page, branched on `?claim=<slug>`:
 *
 *   1. WITH claim (deep-linked from "Revendică profilul" on /artist/{slug}):
 *      slug is locked, applicant must explain why they're the rightful owner
 *      (claim_message — required).
 *
 *   2. WITHOUT claim (direct visit to /artist/inregistrare): the applicant
 *      picks the artist they represent from a searchable dropdown. No
 *      explanation message — admin reviews based on the picker selection
 *      and account email.
 *
 * In both flows the linked artist_id is REQUIRED — there's no "register
 * first, link later" path.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$claimSlug = isset($_GET['claim']) ? preg_replace('/[^a-z0-9-]/i', '', strtolower($_GET['claim'])) : '';
$hasClaim = $claimSlug !== '';

$pageTitle = 'Cont artist — înregistrare';
$bodyClass = 'min-h-screen flex bg-surface';
$authTitle = $hasClaim ? 'Revendică-ți profilul' : 'Cont artist';
$authSubtitle = $hasClaim
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
                    <h2 class="text-2xl font-bold text-secondary"><?= $hasClaim ? 'Revendică profilul' : 'Cont artist' ?></h2>
                    <p class="mt-2 text-muted">
                        <?= $hasClaim
                            ? 'Profil revendicat: <span class="font-semibold text-primary">' . htmlspecialchars($claimSlug, ENT_QUOTES, 'UTF-8') . '</span>'
                            : 'Completează datele pentru a aplica' ?>
                    </p>
                </div>

                <?php if ($hasClaim): ?>
                <div id="claim-status" class="hidden p-3 mb-5 text-sm text-center rounded-lg"></div>
                <?php endif; ?>

                <form id="artist-register-form" class="space-y-5">
                    <input type="hidden" id="artist_slug" name="artist_slug" value="<?= htmlspecialchars($claimSlug, ENT_QUOTES, 'UTF-8') ?>">
                    <!-- Filled by the picker below when no slug was supplied. -->
                    <input type="hidden" id="artist_id" name="artist_id" value="">

                    <?php if (!$hasClaim): ?>
                    <!-- ARTIST PICKER (only when no slug is pre-filled) -->
                    <div>
                        <label for="artist_search" class="block mb-2 text-sm font-medium text-secondary">
                            Pe ce artist îl reprezinți?
                        </label>
                        <div class="relative">
                            <input type="text" id="artist_search" autocomplete="off"
                                placeholder="Caută artistul..."
                                class="w-full input">
                            <input type="hidden" id="artist_picker_value" value="">

                            <!-- Selected artist preview (shown after a pick).
                                 JS toggles `hidden` and adds `flex` when populating. -->
                            <div id="artist_selected" class="items-center hidden gap-3 p-3 mt-2 border rounded-lg border-primary/30 bg-primary/5">
                                <img id="artist_selected_logo" src="" class="object-cover w-10 h-10 rounded-full bg-gray-200" alt="">
                                <div class="flex-1 min-w-0">
                                    <p id="artist_selected_name" class="text-sm font-semibold truncate text-secondary"></p>
                                    <p id="artist_selected_slug" class="text-xs truncate text-muted"></p>
                                </div>
                                <button type="button" id="artist_clear_btn" class="px-2 text-sm text-muted hover:text-red-600" aria-label="Șterge selecția">×</button>
                            </div>

                            <!-- Search results dropdown -->
                            <div id="artist_results" class="hidden absolute left-0 right-0 z-20 mt-1 overflow-hidden bg-white border rounded-lg shadow-lg border-border max-h-72 overflow-y-auto"></div>
                        </div>
                        <p class="mt-1 text-xs text-muted">
                            Nu găsești artistul? <a href="mailto:contact@ambilet.ro" class="underline">Contactează-ne</a>.
                        </p>
                    </div>
                    <?php endif; ?>

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

                    <?php if ($hasClaim): ?>
                    <!-- Justification — required ONLY in the claim flow. -->
                    <div>
                        <label for="claim_message" class="block mb-2 text-sm font-medium text-secondary">
                            De ce ești tu titularul profilului?
                        </label>
                        <textarea id="claim_message" name="claim_message" rows="4" required class="w-full input"
                            placeholder="Ex: Sunt managerul oficial al artistului, contractul de booking este înregistrat la..."></textarea>
                    </div>
                    <?php endif; ?>

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
// Pass claim flag so JS knows which flow to wire.
echo '<script>window.ARTIST_CLAIM_SLUG = ' . json_encode($claimSlug) . ';</script>';
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-register.js') . '"></script>';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
