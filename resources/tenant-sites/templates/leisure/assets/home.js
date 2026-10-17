/* Animațiile paginilor din secțiuni (leisure / Nordvale): Motion + Anime.js.
   Fiecare efect verifică dacă secțiunea lui există: secțiunile pot fi ascunse din panou. */
import { animate, scroll, inView, stagger } from 'https://cdn.jsdelivr.net/npm/motion@12.42.1/+esm';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const intro = document.querySelector('[data-intro]');
window.clearTimeout(window.__introSafety);

const revealEverything = () => {
    document.documentElement.classList.remove('reveal-ready');
    document.querySelectorAll('[data-reveal], [data-hero-kicker], [data-hero-title], [data-hero-copy], [data-hero-cta], [data-hero-visual]').forEach(el => {
        el.style.opacity = '1';
        el.style.transform = 'none';
    });
};

if (!reduceMotion) {
    // Intro sequence with Anime.js, then Motion takes over.
    if (window.anime && intro) {
        anime.timeline({ easing: 'easeOutExpo' })
            .add({ targets: '[data-intro-word]', translateY: ['110%', '0%'], duration: 900, delay: 120 })
            .add({ targets: '.intro-line', scaleX: [0, 1], duration: 650 }, '-=500')
            .add({ targets: '[data-intro-copy]', opacity: [0, 1], translateY: [12, 0], duration: 520 }, '-=380')
            .add({ targets: intro, opacity: [1, 0], duration: 520, delay: 360, complete: () => intro.classList.add('is-hidden') });
    } else {
        intro?.classList.add('is-hidden');
    }

    animate('[data-hero-kicker]', { opacity: [0, 1], y: [22, 0] }, { duration: .72, delay: .78, ease: [0.22, 1, 0.36, 1] });
    animate('[data-hero-title]', { opacity: [0, 1], y: [54, 0] }, { duration: 1.05, delay: stagger(.11, { startDelay: .88 }), ease: [0.16, 1, 0.3, 1] });
    animate('[data-hero-copy]', { opacity: [0, 1], y: [22, 0] }, { duration: .82, delay: stagger(.08, { startDelay: 1.28 }), ease: [0.22, 1, 0.36, 1] });
    animate('[data-hero-cta]', { opacity: [0, 1], y: [22, 0] }, { duration: .82, delay: 1.42, ease: [0.22, 1, 0.36, 1] });
    animate('[data-hero-visual]', { opacity: [0, 1], x: [62, 0], rotate: [1.5, 0] }, { duration: 1.18, delay: 1.02, ease: [0.16, 1, 0.3, 1] });

    document.querySelectorAll('[data-reveal]').forEach((element) => {
        inView(element, () => {
            animate(element, { opacity: [0, 1], y: [34, 0] }, { duration: .82, ease: [0.22, 1, 0.36, 1] });
        }, { margin: '0px 0px -9% 0px', amount: .12 });
    });

    const heroSection = document.querySelector('#hero-bg')?.closest('section');
    if (heroSection) scroll(animate('#hero-bg', { y: ['0%', '12%'], scale: [1.02, 1.11] }), { target: heroSection, offset: ['start start', 'end start'] });
    const reserve = document.querySelector('#reserve-image-a')?.closest('section');
    if (reserve) {
        scroll(animate('#reserve-image-a', { y: ['-3%', '8%'] }), { target: reserve, offset: ['start end', 'end start'] });
        scroll(animate('#reserve-image-b', { y: ['5%', '-7%'] }), { target: reserve, offset: ['start end', 'end start'] });
    }

    // Anime.js route marker and dash animation.
    const route = document.querySelector('#hero-route');
    const marker = document.querySelector('#route-marker');
    if (window.anime && route && marker) {
        const path = anime.path('#hero-route');
        anime({ targets: '#hero-route', strokeDashoffset: [anime.setDashoffset, -260], easing: 'linear', duration: 18000, loop: true });
        anime({ targets: marker, translateX: path('x'), translateY: path('y'), rotate: path('angle'), easing: 'easeInOutSine', duration: 7600, loop: true, direction: 'alternate' });
    }

    // Count up when stats become visible.
    const statsBlock = document.querySelector('.js-counter')?.closest('div[class*="grid"]');
    if (statsBlock && window.anime) {
        inView(statsBlock, () => {
            document.querySelectorAll('.js-counter').forEach(el => {
                const target = Number(el.dataset.value || 0);
                const text = el.textContent;
                const prefix = text.match(/^\D*/)?.[0] || '';
                const suffix = text.match(/\D*$/)?.[0] || '';
                const counter = { value: 0 };
                anime({ targets: counter, value: target, round: 1, duration: 1600, easing: 'easeOutExpo', update: () => { el.textContent = `${prefix}${counter.value}${suffix}`; } });
            });
        }, { amount: .4 });
    }

    // Desktop-only subtle tilt.
    if (window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
        document.querySelectorAll('.experience-card').forEach(card => {
            const inner = card.querySelector('.experience-card__inner');
            card.addEventListener('pointermove', event => {
                const rect = card.getBoundingClientRect();
                const x = (event.clientX - rect.left) / rect.width - .5;
                const y = (event.clientY - rect.top) / rect.height - .5;
                inner.style.transform = `rotateY(${x * 5}deg) rotateX(${-y * 5}deg) translateY(-4px)`;
            });
            card.addEventListener('pointerleave', () => { inner.style.transform = ''; });
        });
    }
} else {
    intro?.classList.add('is-hidden');
    revealEverything();
}

scroll(progress => {
    const line = document.querySelector('#page-progress');
    if (line) line.style.transform = `scaleX(${progress})`;
});

// Failsafe if a CDN script is blocked.
window.setTimeout(() => {
    intro?.classList.add('is-hidden');
    revealEverything();
}, 3500);
