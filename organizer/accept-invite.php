<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Acceptă invitația — ' . SITE_NAME;
$pageDescription = 'Acceptă invitația în echipă și setează-ți parola';
$cssBundle = 'auth';
$bodyClass = 'flex min-h-screen';
require_once __DIR__ . '/../includes/head.php';

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
?>

<div class="flex items-center justify-center flex-1 p-6 lg:p-12 bg-surface">
    <div class="w-full max-w-lg">

        <!-- Loading state -->
        <div id="loadingState" class="text-center py-12">
            <div class="w-12 h-12 mx-auto mb-4 border-4 rounded-full animate-spin border-primary border-t-transparent"></div>
            <p class="text-muted">Se verifică invitația...</p>
        </div>

        <!-- Error state -->
        <div id="errorState" class="hidden text-center py-12">
            <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-red-100">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
            <h2 class="mb-2 text-xl font-bold text-secondary">Invitație invalidă</h2>
            <p id="errorMessage" class="text-muted">Invitația a expirat sau linkul este incorect.</p>
            <a href="/organizator/login" class="inline-block mt-6 px-6 py-3 text-sm font-semibold text-white bg-primary rounded-xl">Mergi la autentificare</a>
        </div>

        <!-- Accept form -->
        <div id="acceptForm" class="hidden">
            <div class="p-8 bg-white border rounded-2xl border-border">
                <!-- Header -->
                <div class="mb-6 text-center">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-xl" style="background:linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h2 class="text-2xl font-bold text-secondary">Ai fost invitat în echipă!</h2>
                </div>

                <!-- Organizer info -->
                <div class="p-4 mb-6 rounded-xl bg-primary/5 border border-primary/10">
                    <p class="text-sm text-muted">Organizator</p>
                    <p id="orgName" class="text-lg font-bold text-secondary"></p>
                    <p id="orgCompany" class="text-sm text-muted"></p>
                </div>

                <!-- Pre-filled info -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block mb-1 text-xs font-medium text-muted uppercase tracking-wide">Nume</label>
                        <p id="memberName" class="text-sm font-semibold text-secondary"></p>
                    </div>
                    <div>
                        <label class="block mb-1 text-xs font-medium text-muted uppercase tracking-wide">Email</label>
                        <p id="memberEmail" class="text-sm font-semibold text-secondary"></p>
                    </div>
                </div>

                <hr class="my-6 border-border">

                <!-- Password form -->
                <form onsubmit="AcceptInvite.submit(event)" class="space-y-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-secondary">Parola</label>
                        <input type="password" id="password" required minlength="8" placeholder="Minim 8 caractere"
                            class="w-full px-4 py-3 text-sm bg-white border border-border rounded-xl focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            oninput="AcceptInvite.checkStrength(this.value)">
                        <div class="flex gap-1 mt-2">
                            <div id="str1" class="h-1.5 flex-1 rounded-full bg-gray-200"></div>
                            <div id="str2" class="h-1.5 flex-1 rounded-full bg-gray-200"></div>
                            <div id="str3" class="h-1.5 flex-1 rounded-full bg-gray-200"></div>
                            <div id="str4" class="h-1.5 flex-1 rounded-full bg-gray-200"></div>
                        </div>
                        <p id="strLabel" class="mt-1 text-xs text-muted"></p>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium text-secondary">Confirmă parola</label>
                        <input type="password" id="password_confirmation" required minlength="8" placeholder="Repetă parola"
                            class="w-full px-4 py-3 text-sm bg-white border border-border rounded-xl focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium text-secondary">Telefon</label>
                        <input type="tel" id="phone" placeholder="+40 7xx xxx xxx"
                            class="w-full px-4 py-3 text-sm bg-white border border-border rounded-xl focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>

                    <button type="submit" id="submitBtn" class="w-full py-3.5 text-sm font-semibold text-white rounded-xl" style="background:var(--color-primary);">
                        Activează contul
                    </button>
                </form>
            </div>
        </div>

        <!-- Success state -->
        <div id="successState" class="hidden text-center py-12">
            <div class="p-8 bg-white border rounded-2xl border-border">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-green-100">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2 class="mb-2 text-2xl font-bold text-secondary">Contul a fost activat!</h2>
                <p class="mb-6 text-muted">Te poți autentifica acum în dashboard-ul de organizator.</p>

                <div class="p-5 mb-6 rounded-xl bg-gray-50 border border-border">
                    <p class="mb-3 text-sm font-semibold text-secondary">Descarcă aplicația de scanare</p>
                    <div class="flex justify-center mb-3">
                        <img id="qrCode" src="" alt="QR Code" class="w-32 h-32">
                    </div>
                    <a href="https://ambilet.ro/android" target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white rounded-xl" style="background:var(--color-primary);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descarcă pentru Android
                    </a>
                </div>

                <a href="/organizator/login" class="inline-block px-6 py-3 text-sm font-semibold text-white rounded-xl" style="background:var(--color-secondary);">
                    Autentifică-te în dashboard →
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$scriptsExtra = <<<SCRIPTS
<script>
const AcceptInvite = {
    token: '{$token}',
    email: '{$email}',

    async init() {
        if (!this.token || !this.email) {
            this.showError('Link invalid. Lipsesc parametrii necesari.');
            return;
        }
        try {
            const resp = await AmbiletAPI.get('/organizer/team/validate-invite', { token: this.token, email: this.email });
            if (!resp.success) {
                this.showError(resp.message || 'Invitația este invalidă sau a expirat.');
                return;
            }
            document.getElementById('orgName').textContent = resp.data.organizer.name || '';
            document.getElementById('orgCompany').textContent = resp.data.organizer.company_name || '';
            document.getElementById('memberName').textContent = resp.data.member.name || '';
            document.getElementById('memberEmail').textContent = resp.data.member.email || '';
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('acceptForm').classList.remove('hidden');
        } catch (e) {
            this.showError('Invitația este invalidă sau a expirat.');
        }
    },

    showError(msg) {
        document.getElementById('loadingState').classList.add('hidden');
        document.getElementById('errorMessage').textContent = msg;
        document.getElementById('errorState').classList.remove('hidden');
    },

    checkStrength(pw) {
        let score = 0;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
        const labels = ['Slabă', 'Acceptabilă', 'Bună', 'Puternică'];
        for (let i = 1; i <= 4; i++) {
            const el = document.getElementById('str' + i);
            el.className = 'h-1.5 flex-1 rounded-full ' + (i <= score ? colors[score - 1] : 'bg-gray-200');
        }
        document.getElementById('strLabel').textContent = score > 0 ? labels[score - 1] : '';
    },

    async submit(e) {
        e.preventDefault();
        const pw = document.getElementById('password').value;
        const pwc = document.getElementById('password_confirmation').value;
        const phone = document.getElementById('phone').value;
        const btn = document.getElementById('submitBtn');

        if (pw !== pwc) {
            if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error('Parolele nu coincid.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Se activează...';

        try {
            const resp = await AmbiletAPI.post('/organizer/team/accept-invite', {
                token: this.token,
                email: this.email,
                password: pw,
                password_confirmation: pwc,
                phone: phone
            });
            if (resp.success) {
                document.getElementById('acceptForm').classList.add('hidden');
                document.getElementById('qrCode').src = 'https://api.qrserver.com/v1/create-qr-code/?size=128x128&data=' + encodeURIComponent('https://ambilet.ro/android');
                document.getElementById('successState').classList.remove('hidden');
            } else {
                if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error(resp.message || 'Eroare la activare.');
                btn.disabled = false;
                btn.textContent = 'Activează contul';
            }
        } catch (err) {
            if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error('Eroare de rețea. Încearcă din nou.');
            btn.disabled = false;
            btn.textContent = 'Activează contul';
        }
    }
};
document.addEventListener('DOMContentLoaded', () => AcceptInvite.init());
</script>
SCRIPTS;

require_once __DIR__ . '/../includes/scripts.php';
