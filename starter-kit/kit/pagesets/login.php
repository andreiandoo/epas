<?php
/**
 * PAGESET: login.  POST proxy 'login' {email,password} → {data:{token,user}}.
 * Stores KitAuth and redirects to ?next (or /cont). Fully localized via t().
 */
layout('public', ['title' => t('auth.login') . ' — ' . kit_cfg('site_name'), 'nav' => ''],
function () { ?>
  <section class="kit-section"><div class="kit-container" style="max-width:26rem" x-data="kitLogin()">
    <h1 class="kit-display" style="font-size:1.8rem;margin-bottom:1.25rem"><?= e(t('auth.login')) ?></h1>
    <form @submit.prevent="submit()" style="display:flex;flex-direction:column;gap:.75rem">
      <input class="kit-search" style="max-width:none" type="email" x-model="email" placeholder="<?= e(t('checkout.email')) ?>" required>
      <input class="kit-search" style="max-width:none" type="password" x-model="password" placeholder="<?= e(t('auth.password')) ?>" required>
      <p x-show="error" x-text="error" style="color:var(--kit-error);font-size:.9rem"></p>
      <button class="kit-btn kit-btn--primary" :disabled="busy"><span x-text="busy?'…':L.cta"></span></button>
    </form>
    <p class="kit-muted" style="font-size:.9rem;margin-top:1rem"><?= e(t('auth.no_account')) ?> <a href="/inregistrare" style="color:var(--kit-primary)"><?= e(t('auth.create_one')) ?></a></p>
  </div></section>
  <script>
  function kitLogin(){ return { email:'', password:'', error:'', busy:false,
    L: <?= json_encode(['cta' => t('auth.login_cta'), 'bad' => t('auth.invalid'), 'net' => t('common.network_error')], JSON_UNESCAPED_UNICODE) ?>,
    async submit(){ this.error=''; this.busy=true;
      try{ const r=await KitProxy('login',{},{method:'POST',body:{email:this.email,password:this.password}});
        const d=(r&&r.data)||{};
        if(d.token){ KitAuth.set({token:d.token,user:d.user||{}}); const n=new URLSearchParams(location.search).get('next'); window.location.href=n||'/cont'; return; }
        this.error=(r&&r.error)||this.L.bad;
      }catch(e){ this.error=this.L.net; } finally{ this.busy=false; }
    } }; }
  </script>
<?php });
