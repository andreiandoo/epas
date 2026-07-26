<?php
/** PAGESET: register.  POST proxy 'register' {name,email,password} → {data:{token,user}}. */
layout('public', ['title' => t('auth.register') . ' — ' . kit_cfg('site_name'), 'nav' => ''],
function () { ?>
  <section class="kit-section"><div class="kit-container" style="max-width:26rem" x-data="kitRegister()">
    <h1 class="kit-display" style="font-size:1.8rem;margin-bottom:1.25rem"><?= e(t('auth.register')) ?></h1>
    <form @submit.prevent="submit()" style="display:flex;flex-direction:column;gap:.75rem">
      <input class="kit-search" style="max-width:none" x-model="name" placeholder="<?= e(t('auth.name')) ?>" required>
      <input class="kit-search" style="max-width:none" type="email" x-model="email" placeholder="<?= e(t('checkout.email')) ?>" required>
      <input class="kit-search" style="max-width:none" type="password" x-model="password" placeholder="<?= e(t('auth.password')) ?>" required minlength="8">
      <p x-show="error" x-text="error" style="color:var(--kit-error);font-size:.9rem"></p>
      <button class="kit-btn kit-btn--primary" :disabled="busy"><span x-text="busy?'…':L.cta"></span></button>
    </form>
    <p class="kit-muted" style="font-size:.9rem;margin-top:1rem"><?= e(t('auth.have_account')) ?> <a href="/autentificare" style="color:var(--kit-primary)"><?= e(t('auth.signin')) ?></a></p>
  </div></section>
  <script>
  function kitRegister(){ return { name:'', email:'', password:'', error:'', busy:false,
    L: <?= json_encode(['cta' => t('auth.register'), 'fail' => t('auth.register_fail'), 'net' => t('common.network_error')], JSON_UNESCAPED_UNICODE) ?>,
    async submit(){ this.error=''; this.busy=true;
      try{ const r=await KitProxy('register',{},{method:'POST',body:{name:this.name,email:this.email,password:this.password}});
        const d=(r&&r.data)||{};
        if(d.token){ KitAuth.set({token:d.token,user:d.user||{}}); window.location.href='/cont'; return; }
        this.error=(r&&r.error)||this.L.fail;
      }catch(e){ this.error=this.L.net; } finally{ this.busy=false; }
    } }; }
  </script>
<?php });
