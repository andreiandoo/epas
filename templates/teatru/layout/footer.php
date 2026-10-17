<?php
/**
 * Subsolul template-ului teatru + închiderea documentului.
 */
$footer  = (array) site('footer', []);
$socials = array_filter([
    'Facebook' => $footer['facebook'] ?? '',
    'Instagram' => $footer['instagram'] ?? '',
    'TikTok' => $footer['tiktok'] ?? '',
    'YouTube' => $footer['youtube'] ?? '',
]);
$logoUrl = site('branding.logo');
?>
    <!-- Footer -->
    <footer class="py-12 px-4 lg:px-8 border-t border-primary/10">
        <div class="max-w-7xl mx-auto grid gap-8 md:grid-cols-[1.4fr_1fr_1fr] text-sm text-muted">
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <?php if ($logoUrl): ?>
                        <img src="<?= e($logoUrl) ?>" alt="<?= e(SITE_NAME) ?>" class="h-10 w-auto max-w-[160px] object-contain">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full border-2 border-primary flex items-center justify-center"><span class="font-display text-primary"><?= e(SITE_LOGO_TEXT) ?></span></div>
                    <?php endif; ?>
                    <span class="font-display text-lg text-fg"><?= e(SITE_NAME) ?></span>
                </div>
                <?php if (!empty($footer['text'])): ?><p class="max-w-md leading-relaxed"><?= ts_md($footer['text']) ?></p><?php endif; ?>
            </div>
            <div class="space-y-2">
                <?php if (!empty($footer['email'])): ?><p><a href="mailto:<?= e($footer['email']) ?>" class="hover:text-primary transition-colors"><?= e($footer['email']) ?></a></p><?php endif; ?>
                <?php if (!empty($footer['phone'])): ?><p><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $footer['phone'])) ?>" class="hover:text-primary transition-colors"><?= e($footer['phone']) ?></a></p><?php endif; ?>
                <?php if ($socials): ?>
                <p class="flex flex-wrap gap-x-4 gap-y-1 pt-2">
                    <?php foreach ($socials as $label => $url): ?><a href="<?= e(ts_href($url)) ?>" target="_blank" rel="noopener" class="hover:text-primary transition-colors"><?= e($label) ?></a><?php endforeach; ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="space-y-2 md:text-right">
                <p><a href="/confidentialitate" class="hover:text-primary transition-colors">Confidențialitate</a></p>
                <p><a href="/termeni" class="hover:text-primary transition-colors">Termeni</a></p>
                <p><a href="/cookies" class="hover:text-primary transition-colors">Cookies</a> · <a href="#" onclick="window.dispatchEvent(new CustomEvent('site:cookie-settings'));return false" class="hover:text-primary transition-colors">Setări cookies</a></p>
                <p class="pt-2">© <?= date('Y') ?> <?= e(SITE_NAME) ?> · Ticketing by <a href="https://tixello.ro" class="text-primary hover:text-primary-light font-semibold">tixello</a></p>
            </div>
        </div>
    </footer>

    <?= ts_cookie_bar() ?>

    <!-- Toast global + componentă Alpine „favorite/follow” pentru pagini publice -->
    <div x-data="{msg:'',show:false}" x-init="window.siteToast=(m)=>{msg=m;show=true;setTimeout(()=>show=false,2600)}" x-show="show" x-cloak style="position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:120" class="rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-on-primary shadow-xl" x-text="msg"></div>
    <script>
    function favBtn(type, id, meta){
        return {
            on:false, busy:false, ready:false,
            auth(){ try { return JSON.parse(localStorage.getItem('site_auth')||'null'); } catch(e){ return null; } },
            async init(){
                const a=this.auth(); if(!a||!a.token){ this.ready=true; return; }
                try {
                    const r=await fetch('/api/proxy.php?action=acc-favorites',{headers:{'Authorization':'Bearer '+a.token}});
                    const d=await r.json().catch(()=>({}));
                    if(d&&d.success){ const list=(type==='artist'?d.data.artists:d.data.events)||[]; this.on=list.some(x=>String(x.item_id)===String(id)); }
                } catch(e){}
                this.ready=true;
            },
            async toggle(){
                const a=this.auth();
                if(!a||!a.token){ window.location.href='/autentificare?next='+encodeURIComponent(location.pathname+location.search); return; }
                if(this.busy) return; this.busy=true;
                const prev=this.on; this.on=!prev;
                try {
                    await fetch('/api/proxy.php?action=acc-fav-toggle',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+a.token},body:JSON.stringify({item_type:type,item_id:id,meta:meta||{}})});
                    if(window.siteToast) window.siteToast(this.on?(type==='artist'?'Artist urmărit':'Adăugat la favorite'):'Eliminat din favorite');
                } catch(e){ this.on=prev; }
                this.busy=false;
            }
        };
    }
    </script>
</body>
</html>
