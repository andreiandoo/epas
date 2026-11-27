<nav class="ep-vf-nav" aria-label="Secțiuni" data-ep-venue-nav>
    @foreach ($sections as $s)
        <a href="#{{ $s['id'] }}" data-target="{{ $s['id'] }}" @if ($loop->first) class="on" @endif>
            <span class="{{ $s['done'] ? 'ep-ok' : 'ep-todo' }}" title="{{ $s['done'] ? 'Completat' : 'De completat' }}"></span>{{ $s['label'] }}<span class="sr-only"> ({{ $s['done'] ? 'completat' : 'de completat' }})</span>
        </a>
    @endforeach
</nav>
<script>
(function () {
    var nav = document.currentScript.previousElementSibling;
    if (!nav || nav.__epSpy || !('IntersectionObserver' in window)) return;
    nav.__epSpy = true;
    var links = {};
    nav.querySelectorAll('a[data-target]').forEach(function (a) { links[a.dataset.target] = a; });
    function mark(id) {
        Object.keys(links).forEach(function (k) { links[k].classList.toggle('on', k === id); });
    }
    nav.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-target]');
        if (a) mark(a.dataset.target);
    });
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) mark(en.target.id); });
    }, { rootMargin: '-80px 0px -65% 0px' });
    Object.keys(links).forEach(function (id) { var el = document.getElementById(id); if (el) io.observe(el); });
})();
</script>
