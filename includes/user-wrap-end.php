<?php

?>
        </main>
    </div>
</div>
<!-- Mobile Menu Toggle -->
<button id="mobile-sidebar-toggle" class="sticky left-0 right-0 z-40 items-center justify-center hidden mx-auto text-white rounded-full shadow-lg mobile:flex gap-x-2 lg:hidden h-14 bg-primary bottom-4" style="width: 90%;" aria-label="Toggle user menu">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    <span class="">Meniul meu</span>
</button>
<div id="mobile-toggle-sentinel" class="hidden mobile:block" style="height:1px;margin-top:-1px;"></div>
<script>
(function(){
    var btn = document.getElementById('mobile-sidebar-toggle');
    var sentinel = document.getElementById('mobile-toggle-sentinel');
    if (!btn || !sentinel) return;
    var ready = false;
    var obs = new IntersectionObserver(function(entries) {
        var atBottom = entries[0].isIntersecting;
        btn.style.width = atBottom ? '100%' : '90%';
        btn.style.borderRadius = atBottom ? '0' : '9999px';
        btn.style.bottom = atBottom ? '0' : '1rem';
        btn.style.boxShadow = atBottom
            ? 'none'
            : '0 10px 25px -5px rgba(0,0,0,.25), 0 8px 10px -6px rgba(0,0,0,.15)';
        if (!ready) {
            ready = true;
            requestAnimationFrame(function(){
                requestAnimationFrame(function(){
                    btn.style.transition = 'width .3s ease, border-radius .3s ease, bottom .3s ease, box-shadow .3s ease';
                });
            });
        }
    }, { threshold: 0 });
    obs.observe(sentinel);
})();
</script>