<?php
/**
 * PAGESET: register.  POST proxy 'register' {name,email,password} → {data:{token,user}}.
 */
layout('public', ['title' => 'Cont nou — ' . kit_cfg('site_name'), 'nav' => ''],
function () { ?>
  <section class="kit-section"><div class="kit-container" style="max-width:26rem" x-data="kitRegister()">
    <h1 class="kit-display" style="font-size:1.8rem;margin-bottom:1.25rem">Creează cont</h1>
    <form @submit.prevent="submit()" style="display:flex;flex-direction:column;gap:.75rem">
      <input class="kit-search" style="max-width:none" x-model="name" placeholder="Nume complet" required>
      <input class="kit-search" style="max-width:none" type="email" x-model="email" placeholder="Email" required>
      <input class="kit-search" style="max-width:none" type="password" x-model="password" placeholder="Parolă (min. 8)" required minlength="8">
      <p x-show="error" x-text="error" style="color:var(--kit-error);font-size:.9rem"></p>
      <button class="kit-btn kit-btn--primary" :disabled="busy"><span x-text="busy?'…':'Creează cont'"></span></button>
    </form>
    <p class="kit-muted" style="font-size:.9rem;margin-top:1rem">Ai deja cont? <a href="/autentificare" style="color:var(--kit-primary)">Autentifică-te</a></p>
  </div></section>
  <script>
  function kitRegister(){ return { name:'', email:'', password:'', error:'', busy:false,
    async submit(){ this.error=''; this.busy=true;
      try{ const r=await KitProxy('register',{},{method:'POST',body:{name:this.name,email:this.email,password:this.password}});
        const d=(r&&r.data)||{};
        if(d.token){ KitAuth.set({token:d.token,user:d.user||{}}); window.location.href='/cont'; return; }
        this.error=(r&&r.error)||'Nu am putut crea contul.';
      }catch(e){ this.error='Eroare de rețea.'; } finally{ this.busy=false; }
    } }; }
  </script>
<?php });
