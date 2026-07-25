<?php
/**
 * Unified footer + document close for the teatru skin.
 */
$siteName = defined('SITE_NAME') ? SITE_NAME : 'Teatrul Național';
$logoText = defined('SITE_LOGO_TEXT') ? SITE_LOGO_TEXT : 'TN';
$year = date('Y');
?>
    <!-- Footer -->
    <footer class="py-12 px-4 lg:px-8 border-t border-gold/10">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-warm-gray">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full border-2 border-gold flex items-center justify-center">
                    <span class="font-display text-gold"><?= htmlspecialchars($logoText) ?></span>
                </div>
                <span>© <?= $year ?> <?= htmlspecialchars($siteName) ?></span>
            </div>
            <div class="flex items-center gap-6">
                <a href="/confidentialitate" class="hover:text-gold transition-colors">Confidențialitate</a>
                <a href="/termeni" class="hover:text-gold transition-colors">Termeni</a>
                <p>Ticketing by <a href="https://tixello.ro" class="text-gold hover:text-gold-light font-semibold">tixello</a></p>
            </div>
        </div>
    </footer>

    <!-- Toast global + componentă Alpine „favorite/follow” pentru pagini publice -->
    <div x-data="{msg:'',show:false}" x-init="window.teatruToast=(m)=>{msg=m;show=true;setTimeout(()=>show=false,2600)}" x-show="show" x-cloak style="position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:120" class="rounded-full bg-gold px-5 py-2.5 text-sm font-semibold text-midnight shadow-xl" x-text="msg"></div>
    <script>
    function favBtn(type, id, meta){
        return {
            on:false, busy:false, ready:false,
            auth(){ try { return JSON.parse(localStorage.getItem('teatru_auth')||'null'); } catch(e){ return null; } },
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
                    if(window.teatruToast) window.teatruToast(this.on?(type==='artist'?'Artist urmărit':'Adăugat la favorite'):'Eliminat din favorite');
                } catch(e){ this.on=prev; }
                this.busy=false;
            }
        };
    }
    </script>
</body>
</html>
