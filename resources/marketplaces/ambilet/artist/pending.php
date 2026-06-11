<?php
/**
 * Artist Account — Pending Approval
 * Shown after a successful registration and after a login attempt where
 * the account is still pending. Users land here when status='pending'
 * (email may or may not be verified yet — the page reflects both cases).
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Cont artist — în așteptare';
$bodyClass = 'min-h-screen flex bg-surface';
$authTitle = 'Cererea ta este în review';
$authSubtitle = 'Echipa ' . SITE_NAME . ' va analiza informațiile tale și te va anunța prin email când contul va fi aprobat.';

$cssBundle = 'auth';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/auth-branding.php';
?>

    <div class="flex items-center justify-center flex-1 p-8">
        <div class="w-full max-w-md">
            <div class="p-8 bg-white border rounded-2xl border-border">
                <div class="mb-6 text-center">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-amber-100">
                        <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-secondary">Cerere primită!</h2>
                    <p class="mt-2 text-muted" id="pending-email-text">
                        Cererea ta a fost trimisă cu succes.
                    </p>
                </div>

                <div class="p-4 mb-6 rounded-xl bg-surface">
                    <h3 class="mb-3 text-sm font-semibold text-secondary">Ce urmează?</h3>
                    <ol class="space-y-3 text-sm text-muted">
                        <li class="flex items-start gap-3">
                            <span class="flex items-center justify-center flex-shrink-0 w-6 h-6 text-xs font-bold text-white rounded-full bg-primary">1</span>
                            <span><strong>Verifică-ți emailul.</strong> Confirmă adresa folosind linkul primit.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex items-center justify-center flex-shrink-0 w-6 h-6 text-xs font-bold text-white rounded-full bg-primary">2</span>
                            <span><strong>Echipa noastră analizează cererea.</strong> Verificăm informațiile tale și linkurile de dovadă.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex items-center justify-center flex-shrink-0 w-6 h-6 text-xs font-bold text-white rounded-full bg-primary">3</span>
                            <span><strong>Primești emailul de aprobare.</strong> Apoi te poți conecta și începe să-ți gestionezi profilul.</span>
                        </li>
                    </ol>
                </div>

                <div class="p-4 mb-6 border rounded-xl bg-blue-50 border-blue-200">
                    <p class="text-xs text-blue-700">
                        <strong>Reviewul durează de obicei 1-2 zile lucrătoare.</strong>
                        Pentru întrebări, ne poți contacta la <a href="mailto:contact@ambilet.ro" class="underline">contact@ambilet.ro</a>.
                    </p>
                </div>

                <a href="/" class="block w-full py-3 text-center text-sm font-medium text-secondary border rounded-xl border-border hover:bg-primary/10 hover:text-primary hover:border-primary">
                    Înapoi la <?= SITE_NAME ?>
                </a>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = '<script>
    (function() {
        var params = new URLSearchParams(window.location.search);
        var email = params.get("email");
        if (email) {
            var el = document.getElementById("pending-email-text");
            if (el) {
                el.innerHTML = "Cererea ta a fost trimisă cu succes pentru <strong>" + email.replace(/[<>]/g, "") + "</strong>.";
            }
        }
    })();
</script>';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
