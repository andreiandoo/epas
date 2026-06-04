<?php
/**
 * bilete.online — Organizator › Echipă (v3).
 * Route: /organizator/echipa (and /organizator/team)
 *
 * Team members + roles/permissions: stats, searchable table, invite/edit/remove
 * modals, resend invites. Ported from ambilet to v3 + shell, wired to
 * BileteOnlineAPI organizer team endpoints. Leisure-operator role dropdowns are
 * intentionally omitted (leisure is Ambilet-only).
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Gestionare echipă';
$currentPage = 'team';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-3xl font-bold leading-none">Gestionare echipă</h1>
                <p class="mt-1.5 text-sm text-ink-soft">Administrează membrii echipei și permisiunile acestora.</p>
            </div>
            <button onclick="TeamManager.showInviteModal()" class="inline-flex items-center justify-center gap-2 self-start rounded-full bg-vermilion px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d sm:self-auto">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Invită membru
            </button>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border-2 border-ink bg-paper p-4"><span class="mb-3 grid h-10 w-10 place-items-center rounded-lg bg-sky/10 text-sky"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></span><p id="stat-total" class="font-display text-2xl font-bold">0</p><p class="text-sm text-ink-soft">Total membri</p></div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-4"><span class="mb-3 grid h-10 w-10 place-items-center rounded-lg bg-forest/10 text-forest"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span><p id="stat-active" class="font-display text-2xl font-bold">0</p><p class="text-sm text-ink-soft">Activi</p></div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-4"><span class="mb-3 grid h-10 w-10 place-items-center rounded-lg bg-ochre/10 text-ochre"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span><p id="stat-pending" class="font-display text-2xl font-bold">0</p><p class="text-sm text-ink-soft">Invitații în așteptare</p></div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-4"><span class="mb-3 grid h-10 w-10 place-items-center rounded-lg bg-vermilion/10 text-vermilion"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></span><p id="stat-admins" class="font-display text-2xl font-bold">0</p><p class="text-sm text-ink-soft">Administratori</p></div>
        </div>

        <div id="pending-invites-alert" class="mb-6 hidden items-center justify-between rounded-2xl border-2 border-ochre/40 bg-ochre/10 p-4">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-paper text-ochre"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></span>
                <div><p id="pending-count-text" class="text-sm font-bold text-ink">0 invitații în așteptare</p><p class="text-sm text-ink-soft">Invitațiile expiră în 7 zile dacă nu sunt acceptate.</p></div>
            </div>
            <button onclick="TeamManager.resendAllInvites()" class="rounded-full border-2 border-ink px-4 py-2 text-sm font-bold transition hover:bg-ink hover:text-paper">Retrimite invitațiile</button>
        </div>

        <div class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
            <div class="flex flex-col gap-4 border-b-2 border-ink/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="font-display text-lg font-bold">Membri echipă</h2>
                <div class="flex items-center gap-2 rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2">
                    <svg class="h-4 w-4 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="search-members" placeholder="Caută membri…" class="w-44 border-none bg-transparent text-sm outline-none placeholder:text-ink-soft" oninput="TeamManager.filterMembers(this.value)">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-paper-2 text-left">
                        <tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">
                            <th class="px-6 py-3">Membru</th><th class="px-6 py-3">Rol</th><th class="hidden px-6 py-3 lg:table-cell">Permisiuni</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody id="team-table-body" class="divide-y divide-ink/10 text-sm"></tbody>
                </table>
            </div>
            <div id="empty-state" class="hidden py-12 text-center">
                <svg class="mx-auto mb-4 h-16 w-16 text-ink/15" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <h3 class="mb-1 font-display text-lg font-bold">Niciun membru în echipă</h3>
                <p class="mb-4 text-sm text-ink-soft">Invită primul membru al echipei tale.</p>
                <button onclick="TeamManager.showInviteModal()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-4 py-2 text-sm font-bold text-paper transition hover:bg-vermilion-d"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>Invită membru</button>
            </div>
            <div id="loading-state" class="py-12 text-center text-ink-soft">Se încarcă…</div>
        </div>

        <div class="mt-6 rounded-2xl border-2 border-ink bg-paper p-6">
            <h3 class="mb-4 font-display text-base font-bold">Despre roluri și permisiuni</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border-2 border-ink/15 p-4 transition hover:border-vermilion"><div class="mb-2 flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-vermilion"></span><span class="text-sm font-bold">Proprietar</span></div><p class="text-xs text-ink-soft">Acces complet la toate funcționalitățile, inclusiv ștergerea contului.</p></div>
                <div class="rounded-xl border-2 border-ink/15 p-4 transition hover:border-vermilion"><div class="mb-2 flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-sky"></span><span class="text-sm font-bold">Administrator</span></div><p class="text-xs text-ink-soft">Gestionează activități, comenzi, rapoarte și membrii echipei.</p></div>
                <div class="rounded-xl border-2 border-ink/15 p-4 transition hover:border-vermilion"><div class="mb-2 flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-forest"></span><span class="text-sm font-bold">Manager</span></div><p class="text-xs text-ink-soft">Gestionează activități specifice și procesează comenzi.</p></div>
                <div class="rounded-xl border-2 border-ink/15 p-4 transition hover:border-vermilion"><div class="mb-2 flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-ink-soft"></span><span class="text-sm font-bold">Staff</span></div><p class="text-xs text-ink-soft">Doar acces la funcția de check-in pentru activități.</p></div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<!-- Invite modal -->
<div id="invite-modal" class="fixed inset-0 z-[80] hidden">
    <div class="absolute inset-0 bg-ink/60 backdrop-blur-sm" onclick="TeamManager.hideInviteModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-md rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
            <div class="flex items-center justify-between border-b-2 border-ink/10 p-6"><h3 class="font-display text-lg font-bold">Invită membru nou</h3><button onclick="TeamManager.hideInviteModal()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button></div>
            <form id="invite-form" onsubmit="TeamManager.submitInvite(event)" class="space-y-4 p-6">
                <div><label class="mb-1.5 block text-sm font-bold">Nume complet</label><input type="text" name="name" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: Ion Popescu"></div>
                <div><label class="mb-1.5 block text-sm font-bold">Email</label><input type="email" name="email" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" placeholder="ex: ion@companie.ro"></div>
                <div><label class="mb-1.5 block text-sm font-bold">Rol</label><select name="role" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink"><option value="">Selectează rol</option><option value="admin">Administrator</option><option value="manager">Manager</option><option value="staff">Staff</option></select></div>
                <div id="permissions-section" class="hidden">
                    <label class="mb-2 block text-sm font-bold">Permisiuni</label>
                    <div class="space-y-2 text-sm">
                        <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="permissions[]" value="events" class="h-4 w-4 rounded text-vermilion"><span class="text-ink-soft">Activități</span></label>
                        <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="permissions[]" value="orders" class="h-4 w-4 rounded text-vermilion"><span class="text-ink-soft">Comenzi</span></label>
                        <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="permissions[]" value="reports" class="h-4 w-4 rounded text-vermilion"><span class="text-ink-soft">Rapoarte</span></label>
                        <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="permissions[]" value="team" class="h-4 w-4 rounded text-vermilion"><span class="text-ink-soft">Echipă</span></label>
                        <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="permissions[]" value="checkin" class="h-4 w-4 rounded text-vermilion"><span class="text-ink-soft">Check-in (aplicația mobilă)</span></label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4"><button type="button" onclick="TeamManager.hideInviteModal()" class="flex-1 rounded-full border-2 border-ink py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Anulează</button><button type="submit" class="flex-1 rounded-full bg-vermilion py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Trimite invitație</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit modal -->
<div id="edit-modal" class="fixed inset-0 z-[80] hidden">
    <div class="absolute inset-0 bg-ink/60 backdrop-blur-sm" onclick="TeamManager.hideEditModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-md rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
            <div class="flex items-center justify-between border-b-2 border-ink/10 p-6"><h3 class="font-display text-lg font-bold">Editează membru</h3><button onclick="TeamManager.hideEditModal()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button></div>
            <form id="edit-form" onsubmit="TeamManager.submitEdit(event)" class="space-y-4 p-6">
                <input type="hidden" name="member_id" id="edit-member-id">
                <div><label class="mb-1.5 block text-sm font-bold">Rol</label><select name="role" id="edit-role" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink"><option value="admin">Administrator</option><option value="manager">Manager</option><option value="staff">Staff</option></select></div>
                <div id="edit-permissions-section">
                    <label class="mb-2 block text-sm font-bold">Permisiuni</label>
                    <div class="space-y-2 text-sm">
                        <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="permissions[]" value="events" id="edit-perm-events" class="h-4 w-4 rounded text-vermilion"><span class="text-ink-soft">Activități</span></label>
                        <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="permissions[]" value="orders" id="edit-perm-orders" class="h-4 w-4 rounded text-vermilion"><span class="text-ink-soft">Comenzi</span></label>
                        <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="permissions[]" value="reports" id="edit-perm-reports" class="h-4 w-4 rounded text-vermilion"><span class="text-ink-soft">Rapoarte</span></label>
                        <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="permissions[]" value="team" id="edit-perm-team" class="h-4 w-4 rounded text-vermilion"><span class="text-ink-soft">Echipă</span></label>
                        <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="permissions[]" value="checkin" id="edit-perm-checkin" class="h-4 w-4 rounded text-vermilion"><span class="text-ink-soft">Check-in (aplicația mobilă)</span></label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4"><button type="button" onclick="TeamManager.hideEditModal()" class="flex-1 rounded-full border-2 border-ink py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Anulează</button><button type="submit" class="flex-1 rounded-full bg-vermilion py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Salvează</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Delete modal -->
<div id="delete-modal" class="fixed inset-0 z-[80] hidden">
    <div class="absolute inset-0 bg-ink/60 backdrop-blur-sm" onclick="TeamManager.hideDeleteModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-sm rounded-[2rem] border-2 border-ink bg-paper p-6 text-center shadow-deep">
            <span class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-full bg-vermilion/10 text-vermilion"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></span>
            <h3 class="mb-2 font-display text-lg font-bold">Elimină membru</h3>
            <p class="mb-6 text-sm text-ink-soft">Ești sigur că vrei să elimini acest membru din echipă? Această acțiune nu poate fi anulată.</p>
            <input type="hidden" id="delete-member-id">
            <div class="flex gap-3"><button onclick="TeamManager.hideDeleteModal()" class="flex-1 rounded-full border-2 border-ink py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Anulează</button><button onclick="TeamManager.confirmDelete()" class="flex-1 rounded-full bg-vermilion py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Elimină</button></div>
        </div>
    </div>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error') alert(msg);
}

const TeamManager = {
    members: [],

    async init() {
        if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
        const roleSel = document.querySelector('select[name="role"]');
        if (roleSel) roleSel.addEventListener('change', this.handleRoleChange.bind(this));
        const editRole = document.getElementById('edit-role');
        if (editRole) editRole.addEventListener('change', this.handleEditRoleChange.bind(this));
        await this.loadTeam();
    },

    async loadTeam() {
        try {
            const r = await BileteOnlineAPI.get('/organizer/team');
            if (r && r.success) { this.members = (r.data && r.data.members) || []; this.renderTeam(); this.updateStats(); }
        } catch (e) { orgNotify('Nu s-a putut încărca echipa.', 'error'); }
        finally { document.getElementById('loading-state').classList.add('hidden'); }
    },

    renderTeam() {
        const tbody = document.getElementById('team-table-body');
        const empty = document.getElementById('empty-state');
        if (!this.members.length) { tbody.innerHTML = ''; empty.classList.remove('hidden'); return; }
        empty.classList.add('hidden');
        tbody.innerHTML = this.members.map(m => this.renderMemberRow(m)).join('');
    },

    renderMemberRow(member) {
        const initials = this.getInitials(member.name);
        const isOwner = member.role === 'owner';
        return `
            <tr class="member-row hover:bg-paper-2/60" data-name="${(member.name || '').toLowerCase()}" data-email="${(member.email || '').toLowerCase()}">
                <td class="px-6 py-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full text-sm font-bold text-paper ${this.avatarClass(member.role)}" ${member.status === 'pending' ? 'style="opacity:.6"' : ''}>${initials}</span><div><p class="font-bold">${this.esc(member.name)}</p><p class="text-xs text-ink-soft">${this.esc(member.email)}</p></div></div></td>
                <td class="px-6 py-4"><span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold ${this.roleBadge(member.role)}">${isOwner ? '<svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>' : ''}${this.roleLabel(member.role)}</span></td>
                <td class="hidden px-6 py-4 lg:table-cell">${this.renderPermissions(member)}</td>
                <td class="px-6 py-4">${this.statusBadge(member.status)}</td>
                <td class="px-6 py-4">${this.renderActions(member, member.is_current_user, isOwner)}</td>
            </tr>`;
    },

    renderActions(member, isCurrentUser, isOwner) {
        if (isCurrentUser) return '<span class="text-xs text-ink-soft">Tu</span>';
        if (isOwner) return '<span class="text-xs text-ink-soft">—</span>';
        const btn = (fn, title, cls, path) => `<button onclick="${fn}" title="${title}" class="grid h-8 w-8 place-items-center rounded-lg border-2 border-ink/15 text-ink-soft transition ${cls}"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">${path}</svg></button>`;
        if (member.status === 'pending') {
            return `<div class="flex gap-2">${btn("TeamManager.resendInvite('" + member.id + "')", 'Retrimite invitația', 'hover:border-ink hover:text-ink', '<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>')}${btn("TeamManager.showDeleteModal('" + member.id + "')", 'Anulează invitația', 'hover:border-vermilion hover:text-vermilion', '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>')}</div>`;
        }
        return `<div class="flex gap-2">${btn("TeamManager.showEditModal('" + member.id + "')", 'Editează', 'hover:border-ink hover:text-ink', '<path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>')}${btn("TeamManager.showDeleteModal('" + member.id + "')", 'Elimină', 'hover:border-vermilion hover:text-vermilion', '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>')}</div>`;
    },

    renderPermissions(member) {
        if (member.role === 'owner') return '<span class="rounded bg-vermilion/10 px-2 py-1 text-xs font-bold text-vermilion">Acces complet</span>';
        const labels = { events: 'Activități', orders: 'Comenzi', reports: 'Rapoarte', team: 'Echipă', checkin: 'Check-in' };
        const perms = member.permissions || [];
        if (!perms.length) return '<span class="text-xs text-ink-soft">—</span>';
        return `<div class="flex flex-wrap gap-1">${perms.map(p => `<span class="rounded bg-paper-2 px-2 py-0.5 text-xs font-medium text-ink-soft">${labels[p] || p}</span>`).join('')}</div>`;
    },

    updateStats() {
        const total = this.members.length;
        const active = this.members.filter(m => m.status === 'active').length;
        const pending = this.members.filter(m => m.status === 'pending').length;
        const admins = this.members.filter(m => m.role === 'admin' || m.role === 'owner').length;
        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-active').textContent = active;
        document.getElementById('stat-pending').textContent = pending;
        document.getElementById('stat-admins').textContent = admins;
        const alert = document.getElementById('pending-invites-alert');
        if (pending > 0) { alert.classList.remove('hidden'); alert.classList.add('flex'); document.getElementById('pending-count-text').textContent = `${pending} invitații în așteptare`; }
        else { alert.classList.add('hidden'); alert.classList.remove('flex'); }
    },

    filterMembers(query) {
        const q = query.toLowerCase();
        document.querySelectorAll('.member-row').forEach(row => {
            row.style.display = (row.dataset.name.includes(q) || row.dataset.email.includes(q)) ? '' : 'none';
        });
    },

    showInviteModal() { document.getElementById('invite-form').reset(); document.getElementById('permissions-section').classList.add('hidden'); document.getElementById('invite-modal').classList.remove('hidden'); },
    hideInviteModal() { document.getElementById('invite-modal').classList.add('hidden'); },

    handleRoleChange(e) {
        const sec = document.getElementById('permissions-section');
        const role = e.target.value;
        if (role === 'admin') { sec.classList.add('hidden'); sec.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = true); }
        else if (role === 'manager' || role === 'staff') {
            sec.classList.remove('hidden');
            sec.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
            if (role === 'staff') { const c = document.querySelector('#permissions-section input[value="checkin"]'); if (c) c.checked = true; }
        } else sec.classList.add('hidden');
    },

    async submitInvite(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        const data = { name: fd.get('name'), email: fd.get('email'), role: fd.get('role'), permissions: fd.getAll('permissions[]') };
        try {
            const r = await BileteOnlineAPI.post('/organizer/team/invite', data);
            if (r && r.success) { this.hideInviteModal(); orgNotify('Invitație trimisă cu succes.', 'success'); await this.loadTeam(); }
            else orgNotify((r && r.message) || 'Eroare la trimiterea invitației.', 'error');
        } catch (e) { orgNotify('Eroare la trimiterea invitației.', 'error'); }
    },

    showEditModal(memberId) {
        const member = this.members.find(m => String(m.id) === String(memberId));
        if (!member) return;
        document.getElementById('edit-member-id').value = memberId;
        document.getElementById('edit-role').value = member.role;
        const perms = member.permissions || [];
        ['events', 'orders', 'reports', 'team', 'checkin'].forEach(p => { document.getElementById('edit-perm-' + p).checked = perms.includes(p); });
        this.handleEditRoleChange({ target: document.getElementById('edit-role') });
        document.getElementById('edit-modal').classList.remove('hidden');
    },
    hideEditModal() { document.getElementById('edit-modal').classList.add('hidden'); },
    handleEditRoleChange(e) {
        const sec = document.getElementById('edit-permissions-section');
        if (e.target.value === 'admin') sec.classList.add('hidden'); else sec.classList.remove('hidden');
    },

    async submitEdit(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        const data = { member_id: fd.get('member_id'), role: fd.get('role'), permissions: fd.getAll('permissions[]') };
        try {
            const r = await BileteOnlineAPI.post('/organizer/team/update', data);
            if (r && r.success) { this.hideEditModal(); orgNotify('Membru actualizat cu succes.', 'success'); await this.loadTeam(); }
            else orgNotify((r && r.message) || 'Eroare la actualizarea membrului.', 'error');
        } catch (e) { orgNotify('Eroare la actualizarea membrului.', 'error'); }
    },

    showDeleteModal(memberId) { document.getElementById('delete-member-id').value = memberId; document.getElementById('delete-modal').classList.remove('hidden'); },
    hideDeleteModal() { document.getElementById('delete-modal').classList.add('hidden'); },
    async confirmDelete() {
        const id = document.getElementById('delete-member-id').value;
        try {
            const r = await BileteOnlineAPI.post('/organizer/team/remove', { member_id: id });
            if (r && r.success) { this.hideDeleteModal(); orgNotify('Membru eliminat cu succes.', 'success'); await this.loadTeam(); }
            else orgNotify((r && r.message) || 'Eroare la eliminarea membrului.', 'error');
        } catch (e) { orgNotify('Eroare la eliminarea membrului.', 'error'); }
    },

    async resendInvite(memberId) {
        try {
            const r = await BileteOnlineAPI.post('/organizer/team/resend-invite', { member_id: memberId });
            orgNotify((r && r.success) ? 'Invitație retrimisă cu succes.' : ((r && r.message) || 'Eroare la retrimitere.'), (r && r.success) ? 'success' : 'error');
        } catch (e) { orgNotify('Eroare la retrimiterea invitației.', 'error'); }
    },
    async resendAllInvites() {
        try {
            const r = await BileteOnlineAPI.post('/organizer/team/resend-all-invites');
            orgNotify((r && r.success) ? 'Toate invitațiile au fost retrimise.' : ((r && r.message) || 'Eroare la retrimitere.'), (r && r.success) ? 'success' : 'error');
        } catch (e) { orgNotify('Eroare la retrimiterea invitațiilor.', 'error'); }
    },

    getInitials(name) { return (name || '').split(' ').map(n => n[0] || '').join('').substring(0, 2).toUpperCase() || '?'; },
    avatarClass(role) { return ({ owner: 'bg-vermilion', admin: 'bg-sky', manager: 'bg-forest', staff: 'bg-ink-soft' })[role] || 'bg-ink-soft'; },
    roleLabel(role) { return ({ owner: 'Proprietar', admin: 'Administrator', manager: 'Manager', staff: 'Staff' })[role] || role; },
    roleBadge(role) { return ({ owner: 'bg-vermilion/10 text-vermilion', admin: 'bg-sky/10 text-sky', manager: 'bg-forest/10 text-forest', staff: 'bg-paper-2 text-ink-soft' })[role] || 'bg-paper-2 text-ink-soft'; },
    statusBadge(status) {
        if (status === 'active') return '<span class="inline-flex items-center gap-1.5 rounded-full bg-forest/15 px-2 py-1 text-xs font-bold text-forest"><span class="h-1.5 w-1.5 rounded-full bg-forest"></span>Activ</span>';
        if (status === 'pending') return '<span class="inline-flex items-center gap-1.5 rounded-full bg-ochre/15 px-2 py-1 text-xs font-bold text-ochre"><span class="h-1.5 w-1.5 rounded-full bg-ochre"></span>Invitație trimisă</span>';
        return '<span class="inline-flex items-center gap-1.5 rounded-full bg-ink/10 px-2 py-1 text-xs font-bold text-ink-soft"><span class="h-1.5 w-1.5 rounded-full bg-ink-soft"></span>Inactiv</span>';
    },
    esc(t) { const d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => TeamManager.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
