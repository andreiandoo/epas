{{-- Styles + behaviour shared by the manual drawer and the manual hub page. --}}
<style>
.epm-root, .epm-page {
    --epm-bg: #ffffff;
    --epm-bg-soft: #f8fafc;
    --epm-bg-strong: #eef2f6;
    --epm-border: #e2e8f0;
    --epm-text: #334155;
    --epm-muted: #64748b;
    --epm-heading: #0f172a;
    --epm-accent: var(--primary-600, #2563eb);
    --epm-accent-soft: color-mix(in srgb, var(--epm-accent) 10%, transparent);
    --epm-warn-bg: #fffbeb;
    --epm-warn-border: #fcd34d;
    --epm-warn-text: #92400e;
}
.dark .epm-root, .dark .epm-page {
    --epm-bg: #111827;
    --epm-bg-soft: #1a2333;
    --epm-bg-strong: #243044;
    --epm-border: rgba(148, 163, 184, 0.18);
    --epm-text: #cbd5e1;
    --epm-muted: #94a3b8;
    --epm-heading: #f8fafc;
    --epm-accent: var(--primary-400, #60a5fa);
    --epm-warn-bg: rgba(245, 158, 11, 0.1);
    --epm-warn-border: rgba(245, 158, 11, 0.35);
    --epm-warn-text: #fcd34d;
}
html.epm-lock, html.epm-lock body { overflow: hidden !important; }

/* Drawer */
.epm-root { position: fixed; inset: 0; z-index: 10050; display: none; }
.epm-root.is-open { display: block; }
.epm-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); opacity: 0; transition: opacity 0.2s ease; }
.epm-root.is-visible .epm-backdrop { opacity: 1; }
.epm-panel { position: absolute; top: 0; right: 0; bottom: 0; width: min(820px, 100vw); display: flex; flex-direction: column; background: var(--epm-bg); color: var(--epm-text); box-shadow: -12px 0 40px rgba(0, 0, 0, 0.25); transform: translateX(100%); transition: transform 0.22s ease; }
.epm-root.is-visible .epm-panel { transform: none; }
.epm-head { flex-shrink: 0; padding: 16px 20px 12px; border-bottom: 1px solid var(--epm-border); }
.epm-head-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.epm-kicker { margin: 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--epm-accent); }
.epm-title { margin: 2px 0 0; font-size: 18px; font-weight: 700; line-height: 1.3; color: var(--epm-heading); }
.epm-head-actions { display: flex; align-items: center; gap: 6px; }
.epm-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; font-size: 12px; font-weight: 500; color: var(--epm-text); text-decoration: none; border: 1px solid var(--epm-border); border-radius: 8px; background: transparent; }
.epm-btn:hover { background: var(--epm-bg-soft); color: var(--epm-heading); }
.epm-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; color: var(--epm-muted); background: transparent; border: 0; border-radius: 8px; cursor: pointer; }
.epm-icon-btn:hover { background: var(--epm-bg-soft); color: var(--epm-heading); }
.epm-search { display: block; width: 100%; padding: 9px 12px 9px 36px; font-size: 14px; color: var(--epm-heading); background: var(--epm-bg-soft) url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2' stroke='%2394a3b8'><path stroke-linecap='round' stroke-linejoin='round' d='m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z'/></svg>") no-repeat 11px center / 16px; border: 1px solid var(--epm-border); border-radius: 10px; outline: none; }
.epm-search::placeholder { color: var(--epm-muted); }
.epm-search:focus { border-color: var(--epm-accent); box-shadow: 0 0 0 3px var(--epm-accent-soft); }
.epm-notice { margin: 10px 0 0; padding: 8px 12px; font-size: 13px; line-height: 1.45; color: var(--epm-warn-text); background: var(--epm-warn-bg); border: 1px solid var(--epm-warn-border); border-radius: 8px; }
.epm-notice[hidden], .epm-empty[hidden] { display: none; }
.epm-body { flex: 1; min-height: 0; display: flex; }
.epm-toc { flex-shrink: 0; width: 220px; overflow-y: auto; padding: 14px 10px 24px 12px; border-right: 1px solid var(--epm-border); }
.epm-content { flex: 1; min-width: 0; overflow-y: auto; padding: 4px 26px 90px; }
.epm-loading, .epm-empty { padding: 24px 0; font-size: 14px; color: var(--epm-muted); }
@media (max-width: 760px) {
    .epm-root .epm-toc { display: none; }
    .epm-root .epm-content { padding: 4px 16px 90px; }
}

/* Table of contents */
.epm-toc-group { margin-bottom: 8px; }
.epm-toc-heading { margin: 10px 8px 4px; font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--epm-muted); }
.epm-toc-link { display: block; padding: 5px 8px; font-size: 13px; line-height: 1.35; color: var(--epm-text); text-decoration: none; border-radius: 6px; }
.epm-toc-link--sub { padding-left: 14px; font-size: 12.5px; }
.epm-toc-link:hover { background: var(--epm-bg-soft); color: var(--epm-heading); }
.epm-toc-link.is-active { background: var(--epm-accent-soft); color: var(--epm-accent); font-weight: 600; }

/* Chapters */
.epm-ch { margin-top: 12px; padding-top: 22px; border-top: 1px solid var(--epm-border); scroll-margin-top: 90px; }
.epm-content > .epm-ch:first-child { margin-top: 0; border-top: 0; }
.epm-ch-kicker { margin: 0; font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--epm-accent); }
.epm-ch-title { margin: 2px 0 8px; font-size: 21px; font-weight: 700; line-height: 1.25; color: var(--epm-heading); }
.epm-ch-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 0 0 6px; }
.epm-ch-meta:empty { display: none; }
.epm-badge { padding: 2px 8px; font-size: 11px; font-weight: 500; color: var(--epm-muted); background: var(--epm-bg-strong); border-radius: 999px; }
.epm-badge--warn { color: var(--epm-warn-text); background: var(--epm-warn-bg); border: 1px solid var(--epm-warn-border); }
.epm-updated { font-size: 11px; color: var(--epm-muted); }
.epm-hide { display: none !important; }

/* Chapter body (rendered markdown) */
.epm-prose { font-size: 14px; line-height: 1.65; color: var(--epm-text); }
.epm-prose .epm-sec { scroll-margin-top: 90px; }
.epm-prose h2 { margin: 26px 0 8px; font-size: 16.5px; font-weight: 700; line-height: 1.3; color: var(--epm-heading); }
.epm-prose h3 { margin: 18px 0 6px; font-size: 14.5px; font-weight: 600; color: var(--epm-heading); }
.epm-prose p { margin: 0 0 10px; }
.epm-prose ul, .epm-prose ol { margin: 0 0 12px; padding-left: 22px; }
.epm-prose ul { list-style: disc; }
.epm-prose ol { list-style: decimal; }
.epm-prose li { margin: 3px 0; }
.epm-prose li > p { margin: 0; }
.epm-prose strong { font-weight: 600; color: var(--epm-heading); }
.epm-prose a { color: var(--epm-accent); text-decoration: underline; text-underline-offset: 2px; }
.epm-prose code { padding: 1px 5px; font-size: 12.5px; color: var(--epm-heading); background: var(--epm-bg-strong); border-radius: 5px; }
.epm-prose hr { margin: 22px 0; border: 0; border-top: 1px solid var(--epm-border); }
.epm-prose .epm-table { margin: 6px 0 14px; overflow-x: auto; }
.epm-prose table { width: 100%; font-size: 13px; border-collapse: collapse; }
.epm-prose th, .epm-prose td { padding: 7px 10px; text-align: left; vertical-align: top; border: 1px solid var(--epm-border); }
.epm-prose th { font-weight: 600; color: var(--epm-heading); background: var(--epm-bg-soft); }
.epm-prose blockquote { margin: 10px 0 14px; padding: 10px 14px; background: var(--epm-accent-soft); border-left: 3px solid var(--epm-accent); border-radius: 0 8px 8px 0; }
.epm-prose blockquote p:last-child { margin-bottom: 0; }
.epm-prose details { margin: 8px 0; background: var(--epm-bg-soft); border: 1px solid var(--epm-border); border-radius: 10px; }
.epm-prose details[open] { background: var(--epm-bg); }
.epm-prose summary { position: relative; padding: 10px 14px 10px 36px; font-weight: 600; color: var(--epm-heading); cursor: pointer; list-style: none; }
.epm-prose summary::-webkit-details-marker { display: none; }
.epm-prose summary::before { content: ''; position: absolute; top: 50%; left: 15px; width: 7px; height: 7px; border-right: 2px solid var(--epm-muted); border-bottom: 2px solid var(--epm-muted); transform: translateY(-70%) rotate(-45deg); transition: transform 0.15s ease; }
.epm-prose details[open] > summary::before { transform: translateY(-60%) rotate(45deg); }
.epm-prose details > :not(summary) { margin-left: 14px; margin-right: 14px; }
.epm-prose details > :last-child { margin-bottom: 12px; }

/* Hub page */
.epm-page { color: var(--epm-text); }
.epm-page-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.epm-lead { max-width: 640px; margin: 0; font-size: 14px; color: var(--epm-muted); }
.epm-page .epm-search { max-width: 380px; }
.epm-page-grid { display: flex; align-items: flex-start; gap: 24px; }
.epm-page .epm-toc { position: sticky; top: 80px; width: 250px; max-height: calc(100vh - 100px); background: var(--epm-bg); border: 1px solid var(--epm-border); border-radius: 12px; }
.epm-page .epm-content { overflow: visible; padding: 8px 30px 36px; background: var(--epm-bg); border: 1px solid var(--epm-border); border-radius: 12px; }
@media (max-width: 1023px) {
    .epm-page-grid { display: block; }
    .epm-page .epm-toc { display: none; }
    .epm-page .epm-content { padding: 8px 18px 28px; }
}
</style>
<script>
(function () {
    if (window.EpManual) return;

    function norm(value) {
        return String(value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function containsAll(text, terms) {
        return terms.every(function (term) { return text.indexOf(term) !== -1; });
    }

    // Shows only the sections matching every word of the query; opens matching Q&A.
    function search(scope, query) {
        var terms = norm(query).split(/\s+/).filter(Boolean);
        var hits = 0;

        scope.querySelectorAll('.epm-ch').forEach(function (chapter) {
            var head = chapter.querySelector('.epm-ch-head');
            var headHit = terms.length > 0 && containsAll(norm(head ? head.textContent : ''), terms);
            var chapterHit = terms.length === 0 || headHit;

            chapter.querySelectorAll('.epm-sec').forEach(function (section) {
                if (section.epmText === undefined) section.epmText = norm(section.textContent);
                var match = terms.length === 0 || headHit || containsAll(section.epmText, terms);
                section.classList.toggle('epm-hide', !match);
                if (match) chapterHit = true;
                if (terms.length && !headHit) {
                    section.querySelectorAll('details').forEach(function (details) {
                        details.open = containsAll(norm(details.textContent), terms);
                    });
                }
            });

            chapter.classList.toggle('epm-hide', !chapterHit);
            if (chapterHit) hits++;

            var tocItem = scope.querySelector('[data-epm-toc-item="' + chapter.getAttribute('data-epm-chapter') + '"]');
            if (tocItem) tocItem.classList.toggle('epm-hide', !chapterHit);
        });

        scope.querySelectorAll('[data-epm-toc-group]').forEach(function (group) {
            group.classList.toggle('epm-hide', !group.querySelector('[data-epm-toc-item]:not(.epm-hide)'));
        });

        var empty = scope.querySelector('[data-epm-empty]');
        if (empty) empty.hidden = hits > 0;

        return hits;
    }

    function markActive(scope, element) {
        var chapter = element.closest('.epm-ch') || element;
        var id = chapter.getAttribute('data-epm-chapter');
        scope.querySelectorAll('[data-epm-toc-item]').forEach(function (link) {
            link.classList.toggle('is-active', link.getAttribute('data-epm-toc-item') === id);
        });
    }

    function scrollToTarget(container, target, smooth) {
        if (!target) return;
        if (target.tagName === 'DETAILS') target.open = true;
        if (container) {
            var top = target.getBoundingClientRect().top - container.getBoundingClientRect().top + container.scrollTop - 8;
            container.scrollTo({ top: top, behavior: smooth ? 'smooth' : 'auto' });
        } else {
            target.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
        }
    }

    // ---- Drawer ----
    var state = { data: null, loading: null, lastFocus: null };

    function drawer() {
        return document.getElementById('ep-manual');
    }

    // Filament keeps the active tab in ?tab=...; the value may carry "::" parts.
    function activeTab(tabs) {
        var keys = Object.keys(tabs || {});
        var value = new URLSearchParams(window.location.search).get('tab');
        if (!value) return keys[0] || null;
        var parts = value.split('::');
        for (var i = 0; i < parts.length; i++) {
            if (keys.indexOf(parts[i]) !== -1) return parts[i];
        }
        return null;
    }

    function load(el) {
        if (state.data) return Promise.resolve(state.data);
        if (state.loading) return state.loading;

        state.loading = fetch(el.getAttribute('data-src'), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (data) {
                state.data = data;
                el.querySelector('[data-epm-toc]').innerHTML = data.toc;
                el.querySelector('[data-epm-content]').innerHTML = data.content;
                return data;
            })
            .catch(function (error) {
                state.loading = null;
                el.querySelector('[data-epm-content]').innerHTML = '<p class="epm-loading">Manualul nu s-a putut încărca. Închide și încearcă din nou peste câteva secunde.</p>';
                throw error;
            });

        return state.loading;
    }

    function open(detail) {
        var el = drawer();
        if (!el) return;
        detail = detail || {};

        state.lastFocus = document.activeElement;
        el.classList.add('is-open');
        document.documentElement.classList.add('epm-lock');
        requestAnimationFrame(function () { el.classList.add('is-visible'); });

        var input = el.querySelector('[data-epm-search]');
        if (input && window.matchMedia('(pointer: fine)').matches) {
            setTimeout(function () { input.focus({ preventScroll: true }); }, 80);
        }

        load(el).then(function (data) {
            var content = el.querySelector('[data-epm-content]');
            var notice = el.querySelector('[data-epm-notice]');
            var target = null;
            notice.hidden = true;

            if (input && input.value) search(el, input.value);

            if (detail.anchor) {
                target = content.querySelector('#' + CSS.escape(detail.anchor));
            }

            if (!target) {
                var tab = activeTab(data.tabs);
                if (tab) {
                    target = content.querySelector('[data-epm-tab="' + tab + '"]');
                    if (!target && data.tabs[tab]) {
                        notice.textContent = 'Capitolul pentru tab-ul „' + data.tabs[tab] + '” este în lucru. Până atunci ai aici privirea de ansamblu și capitolele deja scrise.';
                        notice.hidden = false;
                    }
                }
            }

            requestAnimationFrame(function () {
                if (target) {
                    scrollToTarget(content, target, false);
                    markActive(el, target);
                } else {
                    content.scrollTop = 0;
                    var first = content.querySelector('.epm-ch');
                    if (first) markActive(el, first);
                }
            });
        }).catch(function () {});
    }

    function close() {
        var el = drawer();
        if (!el || !el.classList.contains('is-open')) return;
        el.classList.remove('is-visible');
        document.documentElement.classList.remove('epm-lock');
        setTimeout(function () { el.classList.remove('is-open'); }, 220);
        if (state.lastFocus && typeof state.lastFocus.focus === 'function') {
            state.lastFocus.focus({ preventScroll: true });
        }
    }

    // Highlights the chapter currently at the top of the reading area.
    function spy(scope, container) {
        var offset = container ? container.getBoundingClientRect().top : 0;
        var current = null;
        scope.querySelectorAll('.epm-ch:not(.epm-hide)').forEach(function (chapter) {
            if (chapter.getBoundingClientRect().top - offset <= 90) current = chapter;
        });
        if (!current) current = scope.querySelector('.epm-ch:not(.epm-hide)');
        if (current) markActive(scope, current);
    }

    window.addEventListener('ep-manual:open', function (event) { open(event.detail); });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') close();
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-epm-close]')) {
            event.preventDefault();
            close();
            return;
        }

        var trigger = event.target.closest('[data-epm-open]');
        if (trigger) {
            event.preventDefault();
            open({ anchor: trigger.getAttribute('data-epm-open') || null });
            return;
        }

        var link = event.target.closest('[data-epm-goto]');
        if (!link) return;
        var scope = link.closest('.epm-root, .epm-page');
        var target = scope ? scope.querySelector('#' + CSS.escape(link.getAttribute('data-epm-goto'))) : null;
        if (!target) return;

        event.preventDefault();
        var inDrawer = scope.classList.contains('epm-root');
        scrollToTarget(inDrawer ? scope.querySelector('[data-epm-content]') : null, target, true);
        markActive(scope, target);
        if (!inDrawer && window.history.replaceState) window.history.replaceState(null, '', '#' + target.id);
    });

    document.addEventListener('input', function (event) {
        var input = event.target.closest ? event.target.closest('[data-epm-search]') : null;
        var scope = input ? input.closest('.epm-root, .epm-page') : null;
        if (scope) search(scope, input.value);
    });

    var spyQueued = false;
    document.addEventListener('scroll', function () {
        if (spyQueued) return;
        spyQueued = true;
        requestAnimationFrame(function () {
            spyQueued = false;
            var el = drawer();
            if (el && el.classList.contains('is-open')) {
                spy(el, el.querySelector('[data-epm-content]'));
                return;
            }
            var page = document.querySelector('.epm-page');
            if (page) spy(page, null);
        });
    }, { capture: true, passive: true });

    window.EpManual = { open: open, close: close, search: search };
})();
</script>
