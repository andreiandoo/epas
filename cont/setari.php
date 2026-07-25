<?php
$navActive = 'settings';
$pageTitle = 'Setările contului — ' . (defined('SITE_NAME') ? SITE_NAME : 'Contul meu');
require __DIR__ . '/_inc/head.php';
require __DIR__ . '/_inc/top.php';
?>
<div class="mb-9"><p class="text-[11px] font-bold tracking-[.22em] text-brass-light">SETĂRI</p><h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">Setările contului</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-paper/48 sm:text-base">Date personale, accesibilitate, securitate, notificări, confidențialitate și gestionarea contului.</p></div>
<div x-data="settingsPage()" x-init="init()" class="space-y-6"><section class="card p-5 sm:p-6"><h2 class="font-display text-2xl">Fotografie de profil</h2><div class="mt-5 flex items-center gap-5"><div class="group relative grid h-20 w-20 place-items-center rounded-full bg-wine font-display text-2xl"><span x-text="initials">AP</span><label class="absolute inset-0 grid cursor-pointer place-items-center rounded-full bg-black/55 opacity-0 transition group-hover:opacity-100"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 5v14M5 12h14"/></svg><input type="file" class="hidden" accept="image/*"></label></div><div><strong>Schimbă fotografia</strong><p class="mt-1 text-xs text-paper/35">JPG, PNG sau WebP · maximum 2 MB.</p></div></div></section><form @submit.prevent="saveProfile()" class="card p-5 sm:p-6"><h2 class="font-display text-2xl">Informații personale</h2><div class="mt-5 grid gap-4 sm:grid-cols-2"><label><span class="label">Nume</span><input class="input" x-model="form.last_name"></label><label><span class="label">Prenume</span><input class="input" x-model="form.first_name"></label><label class="sm:col-span-2"><span class="label">Email</span><input class="input opacity-60" x-bind:value="userEmail" disabled><small class="mt-1 block text-paper/30">Adresa de email se modifică prin suport.</small></label><label><span class="label">Telefon</span><input class="input" x-model="form.phone"></label><label><span class="label">Data nașterii</span><input class="input" type="date" x-model="form.birth_date"></label><label><span class="label">Județ</span><select class="input"><option>Prahova</option><option>București</option></select></label><label><span class="label">Oraș</span><input class="input" x-model="form.city"></label></div><button :disabled="busy" class="btn-primary mt-5 rounded-full px-6 py-3 font-bold" x-text="busy?'Se salvează...':'Salvează datele'"></button></form><section class="card p-5 sm:p-6"><h2 class="font-display text-2xl">Preferințe de accesibilitate</h2><p class="mt-2 text-sm text-paper/40">Aceste informații ajută echipa să pregătească accesul. Nu înlocuiesc confirmarea directă pentru fiecare vizită.</p><div class="mt-5 grid gap-3 md:grid-cols-2"><label class="flex items-start gap-3 rounded-xl bg-white/[.025] p-4"><input type="checkbox" class="mt-1"><span><strong class="text-sm">Acces cu scaun rulant</strong><small class="mt-1 block text-paper/35">Propune automat locurile accesibile.</small></span></label><label class="flex items-start gap-3 rounded-xl bg-white/[.025] p-4"><input type="checkbox" class="mt-1"><span><strong class="text-sm">Asistență auditivă</strong><small class="mt-1 block text-paper/35">Solicită informații despre facilitățile disponibile.</small></span></label><label class="flex items-start gap-3 rounded-xl bg-white/[.025] p-4"><input type="checkbox" class="mt-1"><span><strong class="text-sm">Acces prioritar</strong><small class="mt-1 block text-paper/35">Mobilitate redusă sau timp suplimentar.</small></span></label><label class="flex items-start gap-3 rounded-xl bg-white/[.025] p-4"><input type="checkbox" class="mt-1"><span><strong class="text-sm">Avertismente senzoriale</strong><small class="mt-1 block text-paper/35">Fum, stroboscop, sunete puternice.</small></span></label></div></section><section class="card p-5 sm:p-6"><h2 class="font-display text-2xl">Parolă și securitate</h2><div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="sm:col-span-2"><span class="label">Parola curentă</span><input class="input" type="password" x-model="pw.current"></label><label><span class="label">Parola nouă</span><input class="input" type="password" x-model="pw.next"></label><label><span class="label">Confirmă parola</span><input class="input" type="password" x-model="pw.confirm"></label></div><p x-show="pwErr" x-text="pwErr" class="mt-3 text-sm text-rose-300"></p><div class="mt-5 flex flex-wrap gap-3"><button @click="changePassword()" :disabled="busy" class="btn-primary rounded-full px-5 py-2.5 text-sm font-bold">Schimbă parola</button><button @click="showToast('Toate celelalte sesiuni au fost închise')" class="btn-secondary rounded-full px-5 py-2.5 text-sm">Închide celelalte sesiuni</button></div></section><section id="notifications" class="card p-5 sm:p-6"><h2 class="font-display text-2xl">Notificări și comunicări</h2><div class="mt-5 divide-y divide-white/8"><template x-for="item in [['Confirmări și bilete','Obligatorii pentru comenzi și acces',true],['Remindere de spectacol','Cu 24 de ore înainte',true],['Schimbări de program','Anulări și reprogramări',true],['Favorite și locuri eliberate','Date noi și disponibilitate',true],['Newsletter editorial','Premiere, festivaluri și noutăți',false],['Beneficii și recompense','Puncte, carduri și oferte',false]]"><div class="flex items-center justify-between gap-4 py-4"><div><strong class="text-sm" x-text="item[0]"></strong><p class="mt-1 text-xs text-paper/35" x-text="item[1]"></p></div><button @click="item[2]=!item[2]" :class="item[2]?'on':''" class="switch shrink-0"></button></div></template></div></section><section class="card p-5 sm:p-6"><h2 class="font-display text-2xl">Confidențialitate și date</h2><div class="mt-5 grid gap-3 sm:grid-cols-2"><button @click="showToast('Arhiva datelor va fi trimisă prin email')" class="btn-secondary rounded-xl p-4 text-left"><strong class="text-sm">Descarcă datele</strong><p class="mt-1 text-xs text-paper/35">Comenzi, bilete, preferințe și recenzii.</p></button><a href="/confidentialitate" class="btn-secondary rounded-xl p-4 text-left"><strong class="text-sm">Preferințe cookies</strong><p class="mt-1 text-xs text-paper/35">Analitice și marketing.</p></a></div></section><section class="card border-rose-500/20 bg-rose-500/[.025] p-5 sm:p-6"><h2 class="font-display text-2xl text-rose-200">Zona sensibilă</h2><p class="mt-2 text-sm text-paper/40">Poți dezactiva temporar profilul sau solicita ștergerea definitivă.</p><a href="/cont/sterge-cont" class="mt-4 inline-flex text-sm font-semibold text-rose-300">Șterge contul →</a></section></div>
<script>
function settingsPage(){
    return {
        busy:false, pwErr:'',
        form:{ first_name:'', last_name:'', phone:'', birth_date:'', city:'' },
        pw:{ current:'', next:'', confirm:'' },
        auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
        init(){
            const a=this.auth();
            const parts=((a&&a.user&&a.user.name)||'').trim().split(/\s+/);
            this.form.last_name = parts[0]||''; this.form.first_name = parts.slice(1).join(' ')||'';
        },
        async saveProfile(){
            if(this.busy) return; this.busy=true;
            const a=this.auth();
            try {
                const r=await fetch('/api/proxy.php?action=acc-profile',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+a.token},body:JSON.stringify(this.form)});
                const d=await r.json().catch(()=>({}));
                if(r.ok&&d.success){ if(d.data&&d.data.name){ const stored=this.auth(); stored.user.name=d.data.name; localStorage.setItem('teatru_auth',JSON.stringify(stored)); } this.showToast('Datele au fost salvate'); }
                else this.showToast(d.error||'Nu am putut salva.');
            } catch(e){ this.showToast('Eroare de conexiune.'); }
            this.busy=false;
        },
        async changePassword(){
            if(this.busy) return; this.pwErr='';
            if(!this.pw.current||!this.pw.next){ this.pwErr='Completează parola curentă și cea nouă.'; return; }
            if(this.pw.next!==this.pw.confirm){ this.pwErr='Parolele noi nu coincid.'; return; }
            if(this.pw.next.length<8){ this.pwErr='Parola nouă trebuie să aibă minim 8 caractere.'; return; }
            this.busy=true; const a=this.auth();
            try {
                const r=await fetch('/api/proxy.php?action=acc-password',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+a.token},body:JSON.stringify({current_password:this.pw.current,password:this.pw.next,password_confirmation:this.pw.confirm})});
                const d=await r.json().catch(()=>({}));
                if(r.ok&&d.success){ this.pw={current:'',next:'',confirm:''}; this.showToast('Parola a fost actualizată'); }
                else this.pwErr = d.error || 'Nu am putut schimba parola.';
            } catch(e){ this.pwErr='Eroare de conexiune.'; }
            this.busy=false;
        }
    };
}
</script>
<?php require __DIR__ . '/_inc/bottom.php'; ?>
