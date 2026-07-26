<?php
/**
 * PAGESET: login.  POST proxy 'login' {email,password} → {data:{token,user}}.
 * Stores KitAuth and redirects to ?next (or /cont).
 */
layout('public', ['title' => 'Autentificare — ' . kit_cfg('site_name'), 'nav' => ''],
function () { ?>
  <section class="kit-section"><div class="kit-container" style="max-width:26rem" x-data="kitLogin()">
    <h1 class="kit-display" style="font-size:1.8rem;margin-bottom:1.25rem">Autentificare</h1>
    <form @submit.prevent="submit()" style="display:flex;flex-direction:column;gap:.75rem">
      <input class="kit-search" style="max-width:none" type="email" x-model="email" placeholder="Email" required>
      <input class="kit-search" style="max-width:none" type="password" x-model="password" placeholder="Parolă" required>
      <p x-show="error" x-text="error" style="color:var(--kit-error);font-size:.9rem"></p>
      <button class="kit-btn kit-btn--primary" :disabled="busy"><span x-text="busy?'…':'Intră în cont'"></span></button>
    </form>
    <p class="kit-muted" style="font-size:.9rem;margin-top:1rem">Nu ai cont? <a href="/inregistrare" style="color:var(--kit-primary)">Creează unul</a></p>
  </div></section>
  <script>
  function kitLogin(){ return { email:'', password:'', error:'', busy:false,
    async submit(){ this.error=''; this.busy=true;
      try{ const r=await KitProxy('login',{},{method:'POST',body:{email:this.email,password:this.password}});
        const d=(r&&r.data)||{};
        if(d.token){ KitAuth.set({token:d.token,user:d.user||{}}); const n=new URLSearchParams(location.search).get('next'); window.location.href=n||'/cont'; return; }
        this.error=(r&&r.error)||'Email sau parolă greșite.';
      }catch(e){ this.error='Eroare de rețea.'; } finally{ this.busy=false; }
    } }; }
  </script>
<?php });
