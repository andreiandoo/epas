<?php
/** PAGESET: account-settings — profile (proxy 'me') + logout. */
layout('account', ['title' => 'Setări — ' . kit_cfg('site_name'), 'nav' => 'settings'], function () { ?>
  <h1 class="kit-display" style="font-size:1.8rem;margin-bottom:1.25rem">Setări</h1>
  <div x-data="{ me:{}, saved:false, async load(){ const r=await KitProxy('me'); this.me=(r&&r.data)||{}; },
        async save(){ await KitProxy('me-update',{},{method:'POST',body:this.me}); this.saved=true; setTimeout(()=>this.saved=false,2000); } }" x-init="load()">
    <form @submit.prevent="save()" class="kit-ordersum" style="max-width:32rem">
      <div style="display:flex;flex-direction:column;gap:.75rem">
        <label style="font-size:.85rem">Nume<input class="kit-search" style="max-width:none" x-model="me.name"></label>
        <label style="font-size:.85rem">Email<input class="kit-search" style="max-width:none" type="email" x-model="me.email"></label>
        <label style="font-size:.85rem">Telefon<input class="kit-search" style="max-width:none" x-model="me.phone"></label>
      </div>
      <button class="kit-btn kit-btn--primary" style="margin-top:1rem"><span x-text="saved?'Salvat ✓':'Salvează'"></span></button>
    </form>
    <div style="margin-top:2rem">
      <button class="kit-btn kit-btn--outline" @click="logout()">Ieși din cont</button>
    </div>
  </div>
<?php });
