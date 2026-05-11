<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Echipă & schimburi';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_team';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">👨‍💼 Echipă & schimburi</h1>
            <p class="mt-1 text-sm text-muted">Alocă membrii pe zile/săptămâni: cine scanează, cine vinde, pe ce poartă.</p>
        </div>

        <div class="bg-white border rounded-2xl border-border p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 mb-4 bg-blue-100 rounded-full">
                <svg class="w-8 h-8 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <h2 class="text-lg font-bold text-secondary mb-2">Planificator echipă (F5.8)</h2>
            <p class="text-muted text-sm max-w-md mx-auto mb-4">
                Calendar săptămânal cu drag&drop pentru alocare shifts pe membri. Roluri: gate_scanner, sales_operator, shift_manager, accountant.
            </p>
            <p class="text-xs text-muted mb-6">
                Estimare: 1.5-2 zile. UI vanilla JS + Tailwind grid + Sortable.js (CDN).
            </p>
            <div class="grid sm:grid-cols-2 gap-3 max-w-md mx-auto text-left">
                <div class="p-3 bg-slate-50 rounded-lg">
                    <p class="text-xs font-bold text-secondary mb-1">📅 Funcționalități:</p>
                    <ul class="text-xs text-muted space-y-1">
                        <li>• Calendar week/day toggle</li>
                        <li>• Coloane membri × zile</li>
                        <li>• Drag&drop shifts colorate</li>
                        <li>• Resize start/end</li>
                    </ul>
                </div>
                <div class="p-3 bg-slate-50 rounded-lg">
                    <p class="text-xs font-bold text-secondary mb-1">🎯 Per shift:</p>
                    <ul class="text-xs text-muted space-y-1">
                        <li>• Start / End</li>
                        <li>• Rol (scanner/sales/manager)</li>
                        <li>• Poartă (A, B, C, ...)</li>
                        <li>• Notițe</li>
                    </ul>
                </div>
            </div>
            <div class="mt-6">
                <a href="/organizator/echipa" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition-colors">→ Vezi membrii curenți (vechiul ecran)</a>
            </div>
        </div>
    </main>
</div>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
