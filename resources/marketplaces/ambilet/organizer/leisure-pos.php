<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'POS — Emitere bilete';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_pos';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">🎫 Emite bilete (POS)</h1>
            <p class="mt-1 text-sm text-muted">Pentru vânzare on-site cu chitanță 80mm pe imprimantă termică.</p>
        </div>

        <div class="bg-white border rounded-2xl border-border p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 mb-4 bg-emerald-100 rounded-full">
                <svg class="w-8 h-8 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <h2 class="text-lg font-bold text-secondary mb-2">POS în construcție (F5.7)</h2>
            <p class="text-muted text-sm max-w-md mx-auto mb-4">
                Vei putea selecta data + tipuri bilete + cantități, încasare cash/card și auto-printare chitanță 80mm.
            </p>
            <p class="text-xs text-muted mb-6">
                Estimare: 1.5-2 zile pentru UI POS + endpoint <code class="bg-slate-100 px-1 rounded">/leisure/pos/sale</code> + chitanță print-friendly.
            </p>
            <div class="grid sm:grid-cols-2 gap-3 max-w-md mx-auto text-left">
                <div class="p-3 bg-slate-50 rounded-lg">
                    <p class="text-xs font-bold text-secondary mb-1">✅ Ce va include:</p>
                    <ul class="text-xs text-muted space-y-1">
                        <li>• Pick date (azi/mâine)</li>
                        <li>• Grid mare bilete cu image</li>
                        <li>• Sumar live + total</li>
                        <li>• Cash / Card / email</li>
                    </ul>
                </div>
                <div class="p-3 bg-slate-50 rounded-lg">
                    <p class="text-xs font-bold text-secondary mb-1">🖨️ Print termic:</p>
                    <ul class="text-xs text-muted space-y-1">
                        <li>• Chitanță 80mm PDF</li>
                        <li>• Auto window.print()</li>
                        <li>• QR per bilet</li>
                        <li>• Configurare default printer</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</div>
<?php
require_once dirname(__DIR__) . '/includes/organizer-footer.php';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
