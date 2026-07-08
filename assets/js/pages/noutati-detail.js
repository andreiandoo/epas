/**
 * Noutăți (detail) — enhancers for the SSR-rendered detail page.
 *
 * Responsibilities:
 *   - Reactions bar: session-hash-based toggle voting via
 *     AmbiletAPI.request('/system-updates/{slug}/react', POST). The
 *     session_hash is a random 32-byte hex kept in localStorage for
 *     1 year so votes survive across visits without a login.
 *   - Share menu: Facebook / WhatsApp / Email / Copy link (native
 *     navigator.share on mobile if available).
 *   - Sticky sub-nav: appears on scroll past ~300px, hides at top.
 *   - Reading progress bar: fills as the visitor scrolls the article.
 */
(function () {
    'use strict';

    // ---------- Session hash (persistent, no login required) --------
    const STORAGE_KEY = 'noutati_session';
    function getSessionHash() {
        try {
            let hash = localStorage.getItem(STORAGE_KEY);
            if (!hash || hash.length !== 64) {
                // 64-char hex ≈ 256 bits. Enough entropy to make
                // collisions between visitors negligible.
                const bytes = new Uint8Array(32);
                (window.crypto || window.msCrypto).getRandomValues(bytes);
                hash = Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
                localStorage.setItem(STORAGE_KEY, hash);
            }
            return hash;
        } catch (e) {
            // Private mode etc. — fall back to a per-tab hash so reactions
            // still work within the session.
            const bytes = new Uint8Array(32);
            (window.crypto || window.msCrypto).getRandomValues(bytes);
            return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
        }
    }

    // Which reaction types the current session has already voted for.
    function getMyReactions(slug) {
        try {
            const raw = localStorage.getItem('noutati_reactions_' + slug);
            return raw ? JSON.parse(raw) : [];
        } catch (e) { return []; }
    }
    function saveMyReactions(slug, arr) {
        try {
            localStorage.setItem('noutati_reactions_' + slug, JSON.stringify(arr));
        } catch (e) { /* ignore */ }
    }

    // ---------- Reactions --------------------------------------------
    function initReactions() {
        const container = document.getElementById('noutati-reactions');
        if (!container) return;
        const slug = container.dataset.slug;
        const sessionHash = getSessionHash();
        const myReactions = new Set(getMyReactions(slug));
        let inFlight = false;

        // Apply initial active state from localStorage.
        container.querySelectorAll('.noutati-reaction').forEach(btn => {
            if (myReactions.has(btn.dataset.type)) {
                btn.classList.add('is-active');
            }
        });

        container.addEventListener('click', async (e) => {
            const btn = e.target.closest('.noutati-reaction');
            if (!btn || inFlight) return;
            const type = btn.dataset.type;
            inFlight = true;

            // Optimistic UI — snap immediately, revert on failure.
            const wasActive = btn.classList.contains('is-active');
            const countEl = btn.querySelector('.reaction-count');
            const oldCount = parseInt(btn.dataset.count || '0', 10);
            const newCount = Math.max(0, oldCount + (wasActive ? -1 : 1));
            btn.classList.toggle('is-active', !wasActive);
            btn.dataset.count = String(newCount);
            countEl.textContent = String(newCount);

            try {
                const response = await AmbiletAPI.request(
                    '/system-updates/' + encodeURIComponent(slug) + '/react',
                    {
                        method: 'POST',
                        body: JSON.stringify({ type, session_hash: sessionHash }),
                        noCache: true,
                    }
                );
                // Trust server-side aggregate counts to reconcile any
                // drift (e.g. multiple tabs, another visitor voted).
                const counts = (response && response.data && response.data.reaction_counts) || {};
                const my = (response && response.data && response.data.my_reactions) || [];
                container.querySelectorAll('.noutati-reaction').forEach(b => {
                    const t = b.dataset.type;
                    if (counts[t] !== undefined) {
                        b.dataset.count = String(counts[t]);
                        const c = b.querySelector('.reaction-count');
                        if (c) c.textContent = String(counts[t]);
                    }
                    b.classList.toggle('is-active', my.indexOf(t) !== -1);
                });
                saveMyReactions(slug, my);
            } catch (err) {
                console.error('[noutati] reaction failed:', err);
                // Revert optimistic update.
                btn.classList.toggle('is-active', wasActive);
                btn.dataset.count = String(oldCount);
                countEl.textContent = String(oldCount);
            } finally {
                inFlight = false;
            }
        });
    }

    // ---------- Share menu -------------------------------------------
    function initShare() {
        const toggle = document.getElementById('noutati-share-toggle');
        const menu = document.getElementById('noutati-share-menu');
        const miniToggle = document.getElementById('noutati-share-toggle-mini');
        if (!toggle || !menu) return;

        const url = window.location.href;
        const title = document.title;

        function openMenu(anchor) {
            // Native share sheet on mobile — no menu needed.
            if (navigator.share && anchor === 'mini') {
                navigator.share({ title, url }).catch(() => {});
                return;
            }
            menu.classList.remove('hidden');
        }
        function closeMenu() { menu.classList.add('hidden'); }

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });
        if (miniToggle) {
            miniToggle.addEventListener('click', (e) => {
                e.preventDefault();
                openMenu('mini');
            });
        }
        document.addEventListener('click', (e) => {
            if (!menu.contains(e.target) && e.target !== toggle) closeMenu();
        });

        menu.querySelectorAll('[data-share]').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const kind = a.dataset.share;
                if (kind === 'facebook') {
                    window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url), '_blank', 'noopener,noreferrer,width=600,height=500');
                } else if (kind === 'whatsapp') {
                    window.open('https://wa.me/?text=' + encodeURIComponent(title + ' ' + url), '_blank', 'noopener,noreferrer');
                } else if (kind === 'email') {
                    window.location.href = 'mailto:?subject=' + encodeURIComponent(title) + '&body=' + encodeURIComponent(url);
                } else if (kind === 'copy') {
                    navigator.clipboard.writeText(url).then(() => {
                        const label = document.getElementById('noutati-copy-label');
                        if (label) {
                            const old = label.textContent;
                            label.textContent = 'Copiat! ✓';
                            setTimeout(() => { label.textContent = old; }, 1800);
                        }
                    }).catch(() => {});
                }
                closeMenu();
            });
        });
    }

    // ---------- Sticky sub-nav + reading progress --------------------
    function initScrollUI() {
        const subnav = document.getElementById('noutati-subnav');
        const progressFill = document.getElementById('noutati-progress-fill');
        const article = document.querySelector('article');
        if (!article) return;

        let ticking = false;
        function update() {
            const scrolled = window.scrollY;
            const rect = article.getBoundingClientRect();
            const articleTop = scrolled + rect.top;
            const articleHeight = article.offsetHeight;
            const viewportHeight = window.innerHeight;

            // Sub-nav visible after user scrolls past the header band (~400px).
            if (subnav) {
                if (scrolled > 400) {
                    subnav.classList.remove('opacity-0', '-translate-y-full', 'pointer-events-none');
                } else {
                    subnav.classList.add('opacity-0', '-translate-y-full', 'pointer-events-none');
                }
            }

            // Reading progress: 0% at article top-in-view, 100% at article
            // bottom-in-view. Clamped, no NaN.
            if (progressFill) {
                const start = articleTop - viewportHeight * 0.3;
                const end = articleTop + articleHeight - viewportHeight * 0.5;
                const raw = end > start ? (scrolled - start) / (end - start) : 0;
                const pct = Math.min(100, Math.max(0, raw * 100));
                progressFill.style.width = pct + '%';
            }

            ticking = false;
        }

        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(update);
                ticking = true;
            }
        }, { passive: true });
        update();
    }

    function init() {
        initReactions();
        initShare();
        initScrollUI();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
