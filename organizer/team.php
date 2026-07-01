<?php
/**
 * Organizer Team Management Page
 *
 * Team members with roles and permissions management
 */

require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Gestionare echipă';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'team';

$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

<!-- Main Content -->
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-6">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Gestionare echipă</h1>
                <p class="mt-1 text-sm text-muted">Administrează membrii echipei și permisiunile acestora</p>
            </div>
            <button onclick="TeamManager.showInviteModal()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white transition-all rounded-lg bg-gradient-to-r from-primary to-primary-light hover:shadow-lg hover:shadow-primary/30 hover:-translate-y-0.5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Invită membru
            </button>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-4">
            <div class="p-4 bg-white border rounded-xl border-border">
                <div class="flex items-center justify-center w-10 h-10 mb-3 rounded-lg bg-blue-50">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <p id="stat-total" class="text-2xl font-bold text-secondary">0</p>
                <p class="text-sm text-muted">Total membri</p>
            </div>
            <div class="p-4 bg-white border rounded-xl border-border">
                <div class="flex items-center justify-center w-10 h-10 mb-3 rounded-lg bg-green-50">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p id="stat-active" class="text-2xl font-bold text-secondary">0</p>
                <p class="text-sm text-muted">Activi</p>
            </div>
            <div class="p-4 bg-white border rounded-xl border-border">
                <div class="flex items-center justify-center w-10 h-10 mb-3 rounded-lg bg-yellow-50">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p id="stat-pending" class="text-2xl font-bold text-secondary">0</p>
                <p class="text-sm text-muted">Invitații în așteptare</p>
            </div>
            <div class="p-4 bg-white border rounded-xl border-border">
                <div class="flex items-center justify-center w-10 h-10 mb-3 rounded-lg bg-red-50">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <p id="stat-admins" class="text-2xl font-bold text-secondary">0</p>
                <p class="text-sm text-muted">Administratori</p>
            </div>
        </div>

        <!-- Pending Invites Alert -->
        <div id="pending-invites-alert" class="hidden items-center justify-between p-4 mb-6 border rounded-xl bg-yellow-50 border-yellow-400">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 bg-white rounded-lg">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p id="pending-count-text" class="text-sm font-semibold text-yellow-800">0 invitații în așteptare</p>
                    <p class="text-sm text-yellow-700">Invitațiile expiră în 7 zile dacă nu sunt acceptate</p>
                </div>
            </div>
            <button onclick="TeamManager.resendAllInvites()" class="px-4 py-2 text-sm font-semibold text-yellow-800 transition-colors bg-white rounded-lg hover:bg-yellow-100">
                Retrimite invitațiile
            </button>
        </div>

        <!-- Team Table -->
        <div class="overflow-hidden bg-white border rounded-xl border-border">
            <div class="flex flex-col gap-4 p-4 border-b sm:flex-row sm:items-center sm:justify-between border-border">
                <h2 class="text-lg font-semibold text-secondary">Membri echipă</h2>
                <div class="flex items-center gap-2 px-3 py-2 border rounded-lg bg-slate-50 border-border">
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="search-members" placeholder="Caută membri..." class="text-sm bg-transparent border-none outline-none w-44 text-secondary placeholder:text-muted" oninput="TeamManager.filterMembers(this.value)">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Membru</th>
                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Rol</th>
                            <th class="hidden px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase lg:table-cell text-muted">Permisiuni</th>
                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Status</th>
                            <th class="px-6 py-3 text-xs font-semibold tracking-wider text-left uppercase text-muted">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody id="team-table-body">
                        <!-- Loaded via JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div id="empty-state" class="hidden py-12 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <h3 class="mb-2 text-lg font-semibold text-secondary">Niciun membru în echipă</h3>
                <p class="mb-4 text-sm text-muted">Invită primul membru al echipei tale</p>
                <button onclick="TeamManager.showInviteModal()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg bg-primary hover:bg-primary-dark">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Invită membru
                </button>
            </div>

            <!-- Loading State -->
            <div id="loading-state" class="py-12 text-center">
                <div class="inline-flex items-center gap-2 text-muted">
                    <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Se încarcă...
                </div>
            </div>
        </div>

        <!-- Roles Info -->
        <div class="p-6 mt-6 bg-white border rounded-xl border-border">
            <h3 class="mb-4 text-base font-semibold text-secondary">Despre roluri și permisiuni</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="p-4 transition-colors border rounded-lg border-border hover:border-primary">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-3 h-3 rounded-full bg-primary"></span>
                        <span class="text-sm font-semibold text-secondary">Proprietar</span>
                    </div>
                    <p class="text-xs text-muted">Acces complet la toate funcționalitățile, inclusiv ștergerea contului</p>
                </div>
                <div class="p-4 transition-colors border rounded-lg border-border hover:border-primary">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-3 h-3 bg-purple-500 rounded-full"></span>
                        <span class="text-sm font-semibold text-secondary">Administrator</span>
                    </div>
                    <p class="text-xs text-muted">Gestionează evenimente, comenzi, rapoarte și membrii echipei</p>
                </div>
                <div class="p-4 transition-colors border rounded-lg border-border hover:border-primary">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                        <span class="text-sm font-semibold text-secondary">Manager</span>
                    </div>
                    <p class="text-xs text-muted">Gestionează evenimente specifice și procesează comenzi</p>
                </div>
                <div class="p-4 transition-colors border rounded-lg border-border hover:border-primary">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-3 h-3 rounded-full bg-slate-500"></span>
                        <span class="text-sm font-semibold text-secondary">Staff</span>
                    </div>
                    <p class="text-xs text-muted">Doar acces la funcția de check-in pentru evenimente</p>
                </div>
            </div>
        </div>

        <!-- ============ ANGAJATI PERMANENTI (leisure) ============ -->
        <!-- Sectiune dedicata pentru organizatorii leisure (Sf. Ana etc.) care
             au angajati pe ture cu coduri QR fixe individuale, distinct de
             "Echipa" de mai sus (admin/manager/staff cu access la panou). -->
        <div id="permanent-staff-section" class="hidden mt-8 pt-6 border-t-2 border-border" data-permanent-staff-section>
            <div class="flex flex-col gap-4 mb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-secondary">👥 Angajați permanenți</h2>
                    <p class="mt-1 text-sm text-muted">
                        Coduri QR fixe pentru personalul care vine pe ture la locațiile de agrement.
                        Fiecare angajat primește un cod individual care nu expiră — îl folosește la
                        fiecare intrare. Accesările sunt înregistrate separat de biletele clienților.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 flex-shrink-0">
                    <a href="/organizator/staff-raport" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold border border-secondary text-secondary rounded-lg hover:bg-secondary hover:text-white transition-colors">
                        📊 Raport activitate
                    </a>
                    <button id="staff-add-btn" type="button" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-secondary hover:bg-secondary/90">
                        + Adaugă angajat
                    </button>
                </div>
            </div>
            <div class="p-5 bg-white border rounded-xl border-border">
                <div id="staff-loading" class="p-6 text-center text-muted text-sm">Se încarcă...</div>
                <div id="staff-empty" class="hidden p-8 text-center text-muted bg-slate-50 rounded-xl text-sm">
                    Niciun angajat înregistrat. Apasă „+ Adaugă angajat" pentru a începe.
                </div>
                <div id="staff-list" class="hidden overflow-x-auto"></div>
            </div>
        </div>

        <!-- Modal: Adaugă / Editează angajat permanent -->
        <div id="staff-modal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-6 overflow-y-auto">
            <div class="bg-white rounded-2xl max-w-md w-full p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 id="staff-modal-title" class="font-bold text-lg text-secondary">Adaugă angajat</h3>
                    <button id="staff-modal-close" type="button" class="text-muted hover:text-secondary text-xl">✕</button>
                </div>
                <div class="space-y-3">
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase">Prenume *</span>
                        <input id="staff-f-first" type="text" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="ex: Ion">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase">Nume *</span>
                        <input id="staff-f-last" type="text" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="ex: Popescu">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase">Telefon</span>
                        <input id="staff-f-phone" type="tel" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="ex: 07XX XXX XXX">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase">Poziție în firmă</span>
                        <input id="staff-f-position" type="text" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="ex: Casier, Salvamar, Manager">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase">Note (opțional)</span>
                        <textarea id="staff-f-notes" rows="2" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="Observații interne"></textarea>
                    </label>
                    <label id="staff-f-active-wrap" class="hidden items-center gap-2 text-sm">
                        <input id="staff-f-active" type="checkbox" class="w-4 h-4 accent-primary">
                        <span class="text-secondary">Activ (poate trece check-in)</span>
                    </label>
                </div>
                <div class="flex items-center justify-between mt-5 pt-3 border-t border-border">
                    <button id="staff-f-delete" type="button" class="hidden px-3 py-2 text-xs font-semibold text-rose-700 border border-rose-200 rounded hover:bg-rose-50">Șterge angajat</button>
                    <div class="flex items-center gap-2 ml-auto">
                        <button id="staff-f-cancel" type="button" class="px-4 py-2 text-sm border border-border rounded hover:bg-slate-50">Anulează</button>
                        <button id="staff-f-save" type="button" class="px-5 py-2 text-sm bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark">Salvează</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Cod QR angajat permanent -->
        <div id="staff-qr-modal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-6">
            <div class="bg-white rounded-2xl max-w-sm w-full p-6 text-center">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-secondary">Cod QR personal</h3>
                    <button id="staff-qr-close" type="button" class="text-muted hover:text-secondary text-xl">✕</button>
                </div>
                <p id="staff-qr-name" class="text-lg font-bold text-secondary mb-1"></p>
                <p id="staff-qr-position" class="text-xs text-muted mb-4"></p>
                <img id="staff-qr-img" src="" alt="" class="mx-auto rounded border border-border" style="width:240px;height:240px;">
                <p id="staff-qr-code" class="mt-3 font-mono text-sm text-secondary tracking-wider"></p>
                <p class="mt-2 text-[10px] text-muted">Acest cod e fix pentru acest angajat. Tipărește-l și înmânează-l personalului. Scanare prin app-ul ScanIn de la fiecare poartă.</p>
                <div class="mt-4 flex items-center justify-center gap-2">
                    <button id="staff-qr-print" type="button" class="px-3 py-2 text-xs font-semibold bg-secondary text-white rounded hover:bg-secondary/90">🖨️ Tipărește</button>
                    <a id="staff-qr-download" download="" class="px-3 py-2 text-xs font-semibold border border-border rounded hover:bg-slate-50">⬇️ Descarcă PNG</a>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Invite Member Modal -->
<div id="invite-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="TeamManager.hideInviteModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-md bg-white shadow-xl rounded-2xl">
            <div class="flex items-center justify-between p-6 border-b border-border">
                <h3 class="text-lg font-semibold text-secondary">Invită membru nou</h3>
                <button onclick="TeamManager.hideInviteModal()" class="p-2 transition-colors rounded-lg text-muted hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="invite-form" onsubmit="TeamManager.submitInvite(event)" class="p-6 space-y-4">
                <div>
                    <label class="block mb-1.5 text-sm font-medium text-secondary">Nume complet</label>
                    <input type="text" name="name" required class="w-full px-4 py-2.5 text-sm border rounded-lg border-border focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none" placeholder="ex: Ion Popescu">
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-medium text-secondary">Email</label>
                    <input type="email" name="email" required class="w-full px-4 py-2.5 text-sm border rounded-lg border-border focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none" placeholder="ex: ion@companie.ro">
                </div>
                <div>
                    <label class="block mb-1.5 text-sm font-medium text-secondary">Rol</label>
                    <select name="role" required class="w-full px-4 py-2.5 text-sm border rounded-lg border-border focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                        <option value="">Selectează rol</option>
                        <option value="admin">Administrator</option>
                        <option value="manager">Manager</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div id="leisure-role-section" class="hidden">
                    <label class="block mb-1.5 text-sm font-medium text-secondary">🌊 Rol operator (leisure)</label>
                    <select name="leisure_role" class="w-full px-4 py-2.5 text-sm border rounded-lg border-border focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                        <option value="">— Fără rol leisure —</option>
                        <option value="check_in">Operator check-in</option>
                        <option value="rental_boats">Operator închiriere bărci</option>
                        <option value="rental_pontoon">Operator închiriere vaporaș</option>
                        <option value="validation_pontoon">Operator validare bilete vaporaș</option>
                        <option value="rental_sled">Operator închiriere sănii</option>
                        <option value="validation_tow">Operator validare tractări</option>
                        <option value="pos_cashier">Operator fix casă POS</option>
                        <option value="admin_mobile">Membru admin (scan + vânzare mobilă)</option>
                        <option value="kiosk_selfcheckin">🖥 Tabletă auto-check-in (kiosk clienți)</option>
                    </select>
                    <p class="mt-1 text-[11px] text-muted">Determină ce ecran vede operatorul în aplicația mobilă când nu are turnetă activă.</p>
                </div>
                <div id="permissions-section" class="hidden">
                    <label class="block mb-2 text-sm font-medium text-secondary">Permisiuni</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="events" class="w-4 h-4 border-2 rounded text-primary focus:ring-primary">
                            <span class="text-muted">Evenimente</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="orders" class="w-4 h-4 border-2 rounded text-primary focus:ring-primary">
                            <span class="text-muted">Comenzi</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="reports" class="w-4 h-4 border-2 rounded text-primary focus:ring-primary">
                            <span class="text-muted">Rapoarte</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="team" class="w-4 h-4 border-2 rounded text-primary focus:ring-primary">
                            <span class="text-muted">Echipă</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="checkin" class="w-4 h-4 border-2 rounded text-primary focus:ring-primary">
                            <span class="text-muted">Check-in (Aplicația mobilă)</span>
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="TeamManager.hideInviteModal()" class="flex-1 px-4 py-2.5 text-sm font-semibold border rounded-lg border-border text-muted hover:bg-slate-50">
                        Anulează
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-primary hover:bg-primary-dark">
                        Trimite invitație
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Member Modal -->
<div id="edit-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="TeamManager.hideEditModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-md bg-white shadow-xl rounded-2xl">
            <div class="flex items-center justify-between p-6 border-b border-border">
                <h3 class="text-lg font-semibold text-secondary">Editează membru</h3>
                <button onclick="TeamManager.hideEditModal()" class="p-2 transition-colors rounded-lg text-muted hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="edit-form" onsubmit="TeamManager.submitEdit(event)" class="p-6 space-y-4">
                <input type="hidden" name="member_id" id="edit-member-id">
                <div>
                    <label class="block mb-1.5 text-sm font-medium text-secondary">Rol</label>
                    <select name="role" id="edit-role" required class="w-full px-4 py-2.5 text-sm border rounded-lg border-border focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                        <option value="admin">Administrator</option>
                        <option value="manager">Manager</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div id="edit-leisure-role-section" class="hidden">
                    <label class="block mb-1.5 text-sm font-medium text-secondary">🌊 Rol operator (leisure)</label>
                    <select name="leisure_role" id="edit-leisure-role" class="w-full px-4 py-2.5 text-sm border rounded-lg border-border focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                        <option value="">— Fără rol leisure —</option>
                        <option value="check_in">Operator check-in</option>
                        <option value="rental_boats">Operator închiriere bărci</option>
                        <option value="rental_pontoon">Operator închiriere vaporaș</option>
                        <option value="validation_pontoon">Operator validare bilete vaporaș</option>
                        <option value="rental_sled">Operator închiriere sănii</option>
                        <option value="validation_tow">Operator validare tractări</option>
                        <option value="pos_cashier">Operator fix casă POS</option>
                        <option value="admin_mobile">Membru admin (scan + vânzare mobilă)</option>
                        <option value="kiosk_selfcheckin">🖥 Tabletă auto-check-in (kiosk clienți)</option>
                    </select>
                    <p class="mt-1 text-[11px] text-muted">Determină ce ecran vede operatorul în aplicația mobilă când nu are turnetă activă.</p>
                </div>
                <div id="edit-permissions-section">
                    <label class="block mb-2 text-sm font-medium text-secondary">Permisiuni</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="events" id="edit-perm-events" class="w-4 h-4 border-2 rounded text-primary focus:ring-primary">
                            <span class="text-muted">Evenimente</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="orders" id="edit-perm-orders" class="w-4 h-4 border-2 rounded text-primary focus:ring-primary">
                            <span class="text-muted">Comenzi</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="reports" id="edit-perm-reports" class="w-4 h-4 border-2 rounded text-primary focus:ring-primary">
                            <span class="text-muted">Rapoarte</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="team" id="edit-perm-team" class="w-4 h-4 border-2 rounded text-primary focus:ring-primary">
                            <span class="text-muted">Echipă</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="checkin" id="edit-perm-checkin" class="w-4 h-4 border-2 rounded text-primary focus:ring-primary">
                            <span class="text-muted">Check-in (Aplicația mobilă)</span>
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="TeamManager.hideEditModal()" class="flex-1 px-4 py-2.5 text-sm font-semibold border rounded-lg border-border text-muted hover:bg-slate-50">
                        Anulează
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-primary hover:bg-primary-dark">
                        Salvează
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="TeamManager.hideDeleteModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-sm bg-white shadow-xl rounded-2xl">
            <div class="p-6 text-center">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </div>
                <h3 class="mb-2 text-lg font-semibold text-secondary">Elimină membru</h3>
                <p class="mb-6 text-sm text-muted">Ești sigur că vrei să elimini acest membru din echipă? Această acțiune nu poate fi anulată.</p>
                <input type="hidden" id="delete-member-id">
                <div class="flex gap-3">
                    <button onclick="TeamManager.hideDeleteModal()" class="flex-1 px-4 py-2.5 text-sm font-semibold border rounded-lg border-border text-muted hover:bg-slate-50">
                        Anulează
                    </button>
                    <button onclick="TeamManager.confirmDelete()" class="flex-1 px-4 py-2.5 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">
                        Elimină
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>

<script>
/**
 * Team Management Module
 */
const TeamManager = {
    members: [],
    currentOrganizer: null,

    /**
     * Initialize the team manager
     */
    async init() {
        // Setup role change handlers
        document.querySelector('select[name="role"]').addEventListener('change', this.handleRoleChange.bind(this));
        document.querySelector('#edit-role').addEventListener('change', this.handleEditRoleChange.bind(this));

        // Detect daca organizatorul are evenimente leisure → arata leisure_role dropdown
        try {
            const ev = await AmbiletAPI.get('/organizer/events');
            const events = ev.data || ev || [];
            const hasLeisure = (Array.isArray(events) ? events : []).some(e => (e.display_template || 'standard') === 'leisure_venue');
            if (hasLeisure) {
                document.getElementById('leisure-role-section').classList.remove('hidden');
                document.getElementById('edit-leisure-role-section').classList.remove('hidden');
            }
        } catch (e) { /* ignore — show defaults */ }

        // Load team data
        await this.loadTeam();
    },

    /**
     * Load team members
     */
    async loadTeam() {
        try {
            const response = await AmbiletAPI.request('/organizer/team');
            if (response.success) {
                this.members = response.data.members || [];
                this.currentOrganizer = response.data.organizer;
                this.renderTeam();
                this.updateStats();
            }
        } catch (error) {
            console.error('Error loading team:', error);
            this.showError('Nu s-a putut încărca echipa');
        } finally {
            document.getElementById('loading-state').classList.add('hidden');
        }
    },

    /**
     * Render team table
     */
    renderTeam() {
        const tbody = document.getElementById('team-table-body');
        const emptyState = document.getElementById('empty-state');

        if (this.members.length === 0) {
            tbody.innerHTML = '';
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        tbody.innerHTML = this.members.map(member => this.renderMemberRow(member)).join('');
    },

    /**
     * Render a single member row
     */
    renderMemberRow(member) {
        const initials = this.getInitials(member.name);
        const avatarClass = this.getAvatarClass(member.role);
        const roleLabel = this.getRoleLabel(member.role);
        const roleBadgeClass = this.getRoleBadgeClass(member.role);
        const statusBadge = this.getStatusBadge(member.status);
        const permissions = this.renderPermissions(member);
        const isCurrentUser = member.is_current_user;
        const isOwner = member.role === 'owner';

        return `
            <tr class="hover:bg-slate-50 member-row" data-name="${member.name.toLowerCase()}" data-email="${member.email.toLowerCase()}">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 text-sm font-semibold text-white rounded-full ${avatarClass}" ${member.status === 'pending' ? 'style="opacity: 0.6"' : ''}>
                            ${initials}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-secondary">${this.escapeHtml(member.name)}</p>
                            <p class="text-xs text-muted">${this.escapeHtml(member.email)}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-full ${roleBadgeClass}">
                        ${isOwner ? '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>' : ''}
                        ${roleLabel}
                    </span>
                </td>
                <td class="hidden px-6 py-4 lg:table-cell">
                    ${permissions}
                </td>
                <td class="px-6 py-4">
                    ${statusBadge}
                </td>
                <td class="px-6 py-4">
                    ${this.renderActions(member, isCurrentUser, isOwner)}
                </td>
            </tr>
        `;
    },

    /**
     * Render member actions
     */
    renderActions(member, isCurrentUser, isOwner) {
        if (isCurrentUser) {
            return '<span class="text-xs text-muted">Tu</span>';
        }

        if (isOwner) {
            return '<span class="text-xs text-muted">-</span>';
        }

        if (member.status === 'pending') {
            return `
                <div class="flex gap-2">
                    <button onclick="TeamManager.resendInvite('${member.id}')" class="flex items-center justify-center w-8 h-8 text-gray-500 transition-colors border rounded-lg border-border hover:border-primary hover:text-primary" title="Retrimite invitația">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                    <button onclick="TeamManager.showDeleteModal('${member.id}')" class="flex items-center justify-center w-8 h-8 text-gray-500 transition-colors border rounded-lg border-border hover:border-red-500 hover:text-red-500" title="Anulează invitația">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            `;
        }

        return `
            <div class="flex gap-2">
                <button onclick="TeamManager.showEditModal('${member.id}')" class="flex items-center justify-center w-8 h-8 text-gray-500 transition-colors border rounded-lg border-border hover:border-primary hover:text-primary" title="Editează">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <button onclick="TeamManager.showDeleteModal('${member.id}')" class="flex items-center justify-center w-8 h-8 text-gray-500 transition-colors border rounded-lg border-border hover:border-red-500 hover:text-red-500" title="Elimină">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        `;
    },

    /**
     * Render permissions tags
     */
    renderPermissions(member) {
        if (member.role === 'owner') {
            return '<span class="px-2 py-1 text-xs font-medium rounded bg-red-50 text-red-700">Acces complet</span>';
        }

        const permissionLabels = {
            events: 'Evenimente',
            orders: 'Comenzi',
            reports: 'Rapoarte',
            team: 'Echipă',
            checkin: 'Check-in'
        };

        const permissions = member.permissions || [];
        if (permissions.length === 0) {
            return '<span class="text-xs text-muted">-</span>';
        }

        return `<div class="flex flex-wrap gap-1">
            ${permissions.map(p => `<span class="px-2 py-0.5 text-xs font-medium rounded bg-slate-100 text-slate-600">${permissionLabels[p] || p}</span>`).join('')}
        </div>`;
    },

    /**
     * Update stats
     */
    updateStats() {
        const total = this.members.length;
        const active = this.members.filter(m => m.status === 'active').length;
        const pending = this.members.filter(m => m.status === 'pending').length;
        const admins = this.members.filter(m => m.role === 'admin' || m.role === 'owner').length;

        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-active').textContent = active;
        document.getElementById('stat-pending').textContent = pending;
        document.getElementById('stat-admins').textContent = admins;

        // Show/hide pending invites alert
        const alert = document.getElementById('pending-invites-alert');
        if (pending > 0) {
            alert.classList.remove('hidden');
            alert.classList.add('flex');
            document.getElementById('pending-count-text').textContent = `${pending} invitații în așteptare`;
        } else {
            alert.classList.add('hidden');
            alert.classList.remove('flex');
        }
    },

    /**
     * Filter members by search query
     */
    filterMembers(query) {
        const rows = document.querySelectorAll('.member-row');
        const lowerQuery = query.toLowerCase();

        rows.forEach(row => {
            const name = row.dataset.name;
            const email = row.dataset.email;
            const matches = name.includes(lowerQuery) || email.includes(lowerQuery);
            row.style.display = matches ? '' : 'none';
        });
    },

    /**
     * Show invite modal
     */
    showInviteModal() {
        document.getElementById('invite-form').reset();
        document.getElementById('permissions-section').classList.add('hidden');
        document.getElementById('invite-modal').classList.remove('hidden');
    },

    /**
     * Hide invite modal
     */
    hideInviteModal() {
        document.getElementById('invite-modal').classList.add('hidden');
    },

    /**
     * Handle role change in invite form
     */
    handleRoleChange(e) {
        const permissionsSection = document.getElementById('permissions-section');
        const role = e.target.value;

        if (role === 'admin') {
            // Admin has all permissions by default
            permissionsSection.classList.add('hidden');
            permissionsSection.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
        } else if (role === 'manager' || role === 'staff') {
            permissionsSection.classList.remove('hidden');
            // Reset checkboxes
            permissionsSection.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            // Staff defaults to check-in only
            if (role === 'staff') {
                document.querySelector('input[value="checkin"]').checked = true;
            }
        } else {
            permissionsSection.classList.add('hidden');
        }
    },

    /**
     * Submit invite form
     */
    async submitInvite(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);

        const data = {
            name: formData.get('name'),
            email: formData.get('email'),
            role: formData.get('role'),
            leisure_role: formData.get('leisure_role') || null,
            permissions: formData.getAll('permissions[]')
        };

        try {
            const response = await AmbiletAPI.request('/organizer/team/invite', {
                method: 'POST',
                body: JSON.stringify(data)
            });

            if (response.success) {
                this.hideInviteModal();
                this.showSuccess('Invitație trimisă cu succes');
                await this.loadTeam();
            } else {
                this.showError(response.message || 'Eroare la trimiterea invitației');
            }
        } catch (error) {
            console.error('Error sending invite:', error);
            this.showError('Eroare la trimiterea invitației');
        }
    },

    /**
     * Show edit modal
     */
    showEditModal(memberId) {
        const member = this.members.find(m => m.id === memberId);
        if (!member) return;

        document.getElementById('edit-member-id').value = memberId;
        document.getElementById('edit-role').value = member.role;

        // Set leisure_role (poate fi null)
        const leisureRoleSel = document.getElementById('edit-leisure-role');
        if (leisureRoleSel) leisureRoleSel.value = member.leisure_role || '';

        // Set permissions
        const permissions = member.permissions || [];
        ['events', 'orders', 'reports', 'team', 'checkin'].forEach(perm => {
            document.getElementById(`edit-perm-${perm}`).checked = permissions.includes(perm);
        });

        this.handleEditRoleChange({ target: document.getElementById('edit-role') });
        document.getElementById('edit-modal').classList.remove('hidden');
    },

    /**
     * Hide edit modal
     */
    hideEditModal() {
        document.getElementById('edit-modal').classList.add('hidden');
    },

    /**
     * Handle role change in edit form
     */
    handleEditRoleChange(e) {
        const permissionsSection = document.getElementById('edit-permissions-section');
        const role = e.target.value;

        if (role === 'admin') {
            permissionsSection.classList.add('hidden');
        } else {
            permissionsSection.classList.remove('hidden');
        }
    },

    /**
     * Submit edit form
     */
    async submitEdit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);

        const data = {
            member_id: formData.get('member_id'),
            role: formData.get('role'),
            leisure_role: formData.get('leisure_role') || null,
            permissions: formData.getAll('permissions[]')
        };

        try {
            const response = await AmbiletAPI.request('/organizer/team/update', {
                method: 'POST',
                body: JSON.stringify(data)
            });

            if (response.success) {
                this.hideEditModal();
                this.showSuccess('Membru actualizat cu succes');
                await this.loadTeam();
            } else {
                this.showError(response.message || 'Eroare la actualizarea membrului');
            }
        } catch (error) {
            console.error('Error updating member:', error);
            this.showError('Eroare la actualizarea membrului');
        }
    },

    /**
     * Show delete modal
     */
    showDeleteModal(memberId) {
        document.getElementById('delete-member-id').value = memberId;
        document.getElementById('delete-modal').classList.remove('hidden');
    },

    /**
     * Hide delete modal
     */
    hideDeleteModal() {
        document.getElementById('delete-modal').classList.add('hidden');
    },

    /**
     * Confirm delete
     */
    async confirmDelete() {
        const memberId = document.getElementById('delete-member-id').value;

        try {
            const response = await AmbiletAPI.request('/organizer/team/remove', {
                method: 'POST',
                body: JSON.stringify({ member_id: memberId })
            });

            if (response.success) {
                this.hideDeleteModal();
                this.showSuccess('Membru eliminat cu succes');
                await this.loadTeam();
            } else {
                this.showError(response.message || 'Eroare la eliminarea membrului');
            }
        } catch (error) {
            console.error('Error removing member:', error);
            this.showError('Eroare la eliminarea membrului');
        }
    },

    /**
     * Resend invite to a member
     */
    async resendInvite(memberId) {
        try {
            const response = await AmbiletAPI.request('/organizer/team/resend-invite', {
                method: 'POST',
                body: JSON.stringify({ member_id: memberId })
            });

            if (response.success) {
                this.showSuccess('Invitație retrimisă cu succes');
            } else {
                this.showError(response.message || 'Eroare la retrimiterea invitației');
            }
        } catch (error) {
            console.error('Error resending invite:', error);
            this.showError('Eroare la retrimiterea invitației');
        }
    },

    /**
     * Resend all pending invites
     */
    async resendAllInvites() {
        try {
            const response = await AmbiletAPI.request('/organizer/team/resend-all-invites', {
                method: 'POST'
            });

            if (response.success) {
                this.showSuccess('Toate invitațiile au fost retrimise');
            } else {
                this.showError(response.message || 'Eroare la retrimiterea invitațiilor');
            }
        } catch (error) {
            console.error('Error resending invites:', error);
            this.showError('Eroare la retrimiterea invitațiilor');
        }
    },

    /**
     * Helper functions
     */
    getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    },

    getAvatarClass(role) {
        const classes = {
            owner: 'bg-gradient-to-br from-primary to-primary-light',
            admin: 'bg-gradient-to-br from-purple-500 to-purple-600',
            manager: 'bg-gradient-to-br from-green-500 to-green-600',
            staff: 'bg-gradient-to-br from-slate-500 to-slate-600'
        };
        return classes[role] || classes.staff;
    },

    getRoleLabel(role) {
        const labels = {
            owner: 'Proprietar',
            admin: 'Administrator',
            manager: 'Manager',
            staff: 'Staff'
        };
        return labels[role] || role;
    },

    getRoleBadgeClass(role) {
        const classes = {
            owner: 'bg-red-50 text-red-700',
            admin: 'bg-purple-50 text-purple-700',
            manager: 'bg-green-50 text-green-700',
            staff: 'bg-slate-100 text-slate-600'
        };
        return classes[role] || classes.staff;
    },

    getStatusBadge(status) {
        if (status === 'active') {
            return '<span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-green-50 text-green-700"><span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>Activ</span>';
        }
        if (status === 'pending') {
            return '<span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-yellow-50 text-yellow-700"><span class="w-1.5 h-1.5 bg-yellow-500 rounded-full"></span>Invitație trimisă</span>';
        }
        return '<span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-600"><span class="w-1.5 h-1.5 bg-slate-400 rounded-full"></span>Inactiv</span>';
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    showSuccess(message) {
        if (window.AmbiletToast) {
            window.AmbiletToast.success(message);
        } else {
            alert(message);
        }
    },

    showError(message) {
        if (window.AmbiletToast) {
            window.AmbiletToast.error(message);
        } else {
            alert(message);
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => TeamManager.init());

// ============================================================
// PERMANENT STAFF MANAGEMENT (leisure organizers — Sf. Ana etc.)
// Auto-init la DOMContentLoaded daca secțiunea există în DOM.
// CRUD + raport + export într-un IIFE separat ca să nu conflicteze cu TeamManager.
// ============================================================
(function () {
    const root = document.getElementById('permanent-staff-section');
    if (!root) return;
    const $ = (id) => document.getElementById(id);

    let staffCache = [];
    let editingStaffId = null;

    function escAttr(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function escHtml(s) { return escAttr(s); }
    function staffQrUrl(code) {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&margin=2&data=' + encodeURIComponent(code);
    }
    function fmtDateTime(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        if (isNaN(d.getTime())) return iso;
        const pad = n => String(n).padStart(2, '0');
        return pad(d.getDate()) + '.' + pad(d.getMonth()+1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    async function loadStaff() {
        $('staff-loading').classList.remove('hidden');
        $('staff-list').classList.add('hidden');
        $('staff-empty').classList.add('hidden');
        try {
            const res = await AmbiletAPI.get('/organizer/leisure/staff');
            staffCache = res.data?.staff || [];
            root.classList.remove('hidden');
        } catch (e) {
            console.error('[staff] load failed', e);
            staffCache = [];
            // Ascund secțiunea dacă endpoint-ul nu există (deployment incomplet)
            // sau organizatorul nu are acces leisure.
            if (e?.status === 404 || e?.status === 403) {
                root.classList.add('hidden');
                return;
            }
            root.classList.remove('hidden');
        }
        $('staff-loading').classList.add('hidden');
        renderStaffList();
    }

    function renderStaffList() {
        const list = $('staff-list');
        if (!staffCache.length) { $('staff-empty').classList.remove('hidden'); list.classList.add('hidden'); return; }
        $('staff-empty').classList.add('hidden');
        list.classList.remove('hidden');
        list.innerHTML = `
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-border">
                    <tr class="text-left text-xs text-muted uppercase">
                        <th class="px-3 py-2">Angajat</th>
                        <th class="px-3 py-2">Poziție</th>
                        <th class="px-3 py-2">Telefon</th>
                        <th class="px-3 py-2">Cod QR</th>
                        <th class="px-3 py-2">Check-in-uri</th>
                        <th class="px-3 py-2">Ultimul</th>
                        <th class="px-3 py-2 text-right">Acțiuni</th>
                    </tr>
                </thead>
                <tbody>${staffCache.map(s => `
                    <tr class="border-b border-border hover:bg-slate-50">
                        <td class="px-3 py-2">
                            <div class="font-semibold text-secondary">${escHtml(s.full_name)}</div>
                            ${!s.active ? '<span class="text-[10px] text-rose-600 font-semibold">DEZACTIVAT</span>' : ''}
                        </td>
                        <td class="px-3 py-2 text-muted">${escHtml(s.position || '—')}</td>
                        <td class="px-3 py-2 text-muted">${escHtml(s.phone || '—')}</td>
                        <td class="px-3 py-2 font-mono text-xs text-secondary">${escHtml(s.qr_code)}</td>
                        <td class="px-3 py-2 text-center font-semibold">${s.checkins_count || 0}</td>
                        <td class="px-3 py-2 text-xs text-muted">${fmtDateTime(s.last_checkin_at)}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <button data-staff-qr="${s.id}" class="px-2 py-1 text-xs font-semibold bg-secondary text-white rounded hover:bg-secondary/90">📱 QR</button>
                            <button data-staff-edit="${s.id}" class="px-2 py-1 text-xs font-semibold border border-border rounded hover:bg-slate-100">✏️ Edit</button>
                        </td>
                    </tr>
                `).join('')}</tbody>
            </table>`;
        list.querySelectorAll('[data-staff-qr]').forEach(b => b.addEventListener('click', () => openStaffQr(parseInt(b.dataset.staffQr, 10))));
        list.querySelectorAll('[data-staff-edit]').forEach(b => b.addEventListener('click', () => openStaffModal(parseInt(b.dataset.staffEdit, 10))));
    }

    function openStaffModal(id) {
        editingStaffId = id;
        const s = id ? staffCache.find(x => x.id === id) : null;
        $('staff-modal-title').textContent = s ? 'Editează angajat' : 'Adaugă angajat';
        $('staff-f-first').value = s?.first_name || '';
        $('staff-f-last').value = s?.last_name || '';
        $('staff-f-phone').value = s?.phone || '';
        $('staff-f-position').value = s?.position || '';
        $('staff-f-notes').value = s?.notes || '';
        $('staff-f-active').checked = s ? !!s.active : true;
        $('staff-f-active-wrap').classList.toggle('flex', !!s);
        $('staff-f-active-wrap').classList.toggle('hidden', !s);
        $('staff-f-delete').classList.toggle('hidden', !s);
        $('staff-modal').classList.remove('hidden');
        $('staff-modal').classList.add('flex');
    }
    function closeStaffModal() {
        $('staff-modal').classList.add('hidden');
        $('staff-modal').classList.remove('flex');
        editingStaffId = null;
    }

    async function saveStaff() {
        const body = {
            first_name: $('staff-f-first').value.trim(),
            last_name: $('staff-f-last').value.trim(),
            phone: $('staff-f-phone').value.trim() || null,
            position: $('staff-f-position').value.trim() || null,
            notes: $('staff-f-notes').value.trim() || null,
        };
        if (editingStaffId) body.active = $('staff-f-active').checked;
        if (!body.first_name || !body.last_name) { alert('Prenume + Nume sunt obligatorii.'); return; }
        try {
            if (editingStaffId) await AmbiletAPI.put('/organizer/leisure/staff/' + editingStaffId, body);
            else await AmbiletAPI.post('/organizer/leisure/staff', body);
            closeStaffModal();
            await loadStaff();
        } catch (e) {
            alert('Eroare salvare: ' + (e?.message || ''));
        }
    }
    async function deleteStaff() {
        if (!editingStaffId) return;
        if (!confirm('Dezactivezi acest angajat? Codul QR nu va mai fi acceptat la check-in (istoricul rămâne pentru raport).')) return;
        try {
            await AmbiletAPI.delete('/organizer/leisure/staff/' + editingStaffId);
            closeStaffModal();
            await loadStaff();
        } catch (e) {
            alert('Eroare ștergere: ' + (e?.message || ''));
        }
    }

    function openStaffQr(id) {
        const s = staffCache.find(x => x.id === id);
        if (!s) return;
        const url = staffQrUrl(s.qr_code);
        $('staff-qr-name').textContent = s.full_name;
        $('staff-qr-position').textContent = s.position || '';
        $('staff-qr-code').textContent = s.qr_code;
        $('staff-qr-img').src = url;
        $('staff-qr-img').alt = 'QR ' + s.full_name;
        $('staff-qr-download').href = url;
        $('staff-qr-download').download = 'staff-' + s.qr_code + '.png';
        $('staff-qr-modal').classList.remove('hidden');
        $('staff-qr-modal').classList.add('flex');
    }
    function closeStaffQr() {
        $('staff-qr-modal').classList.add('hidden');
        $('staff-qr-modal').classList.remove('flex');
    }
    function printStaffQr() {
        const name = $('staff-qr-name').textContent;
        const pos = $('staff-qr-position').textContent;
        const code = $('staff-qr-code').textContent;
        const imgSrc = $('staff-qr-img').src;
        const w = window.open('', '_blank', 'width=480,height=640');
        w.document.write('<!doctype html><html><head><title>QR ' + escHtml(name) + '</title>'
            + '<style>body{font-family:sans-serif;text-align:center;padding:24px;margin:0}'
            + 'h2{margin:0 0 4px 0;font-size:18px}p{margin:0 0 18px 0;font-size:12px;color:#666}'
            + 'img{width:280px;height:280px;border:1px solid #ddd}.code{font-family:monospace;letter-spacing:0.15em;margin-top:10px;font-size:13px}</style>'
            + '</head><body><h2>' + escHtml(name) + '</h2><p>' + escHtml(pos) + '</p>'
            + '<img src="' + escAttr(imgSrc) + '"/><div class="code">' + escHtml(code) + '</div>'
            + '<scr' + 'ipt>setTimeout(function(){window.print();window.close();},300);</scr' + 'ipt>'
            + '</body></html>');
        w.document.close();
    }

    // Raportul check-in a fost mutat in pagina dedicata /organizator/staff-raport.
    // Pe team.php pastram doar CRUD + QR.

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        // Așteaptă AmbiletAPI + AmbiletAuth să fie ready
        let retries = 0;
        const waitForApi = setInterval(() => {
            retries++;
            if (typeof AmbiletAPI !== 'undefined' && typeof AmbiletAuth !== 'undefined' && AmbiletAuth.isOrganizer && AmbiletAuth.isOrganizer()) {
                clearInterval(waitForApi);
                loadStaff();
            }
            if (retries > 20) clearInterval(waitForApi);
        }, 200);

        // Handlers
        $('staff-add-btn')?.addEventListener('click', () => openStaffModal(null));
        $('staff-modal-close')?.addEventListener('click', closeStaffModal);
        $('staff-f-cancel')?.addEventListener('click', closeStaffModal);
        $('staff-f-save')?.addEventListener('click', saveStaff);
        $('staff-f-delete')?.addEventListener('click', deleteStaff);
        $('staff-qr-close')?.addEventListener('click', closeStaffQr);
        $('staff-qr-print')?.addEventListener('click', printStaffQr);
    });
})();
</script>
