/* =========================================================
   Comportamentele de chrome din bucla de randare a prototipului.

   In client-app.html ele NU stau in functiile de ecran, ci in paint() —
   se aplica deci TUTUROR ecranelor:

     // posterul urca sub bara flotanta, ca ea sa pluteasca peste hero-image
     const d = el.querySelector('[data-dbar]');
     if (d) { const ps = d.nextElementSibling;
              if (ps && ps.classList.contains('poster'))
                ps.style.marginTop = (-d.offsetHeight) + 'px'; }

     // stari de scroll pe headerele sticky
     el.addEventListener('scroll', () => {
       const s = el.querySelector('.stickytop');
       if (s) s.classList.toggle('scrolled', el.scrollTop > 4);
       const d = el.querySelector('[data-dbar]');
       if (d) d.classList.toggle('scrolled', el.scrollTop > 190);
     });

   Fara prima regula, bara isi ocupa propriul spatiu deasupra posterului si
   ecranul de eveniment iese cu ~82px mai inalt decat prototipul.
   ========================================================= */
import { useEffect, type RefObject } from 'react';

export function useScreenChrome(ref: RefObject<HTMLDivElement>) {
  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    /* 1. posterul urca sub bara flotanta */
    const applyDbarOffset = () => {
      const d = el.querySelector<HTMLElement>('[data-dbar]');
      if (!d) return;
      const ps = d.nextElementSibling as HTMLElement | null;
      if (ps && ps.classList.contains('poster')) ps.style.marginTop = `${-d.offsetHeight}px`;
    };
    const raf = requestAnimationFrame(applyDbarOffset);

    /* 2. starile de scroll ale headerelor */
    const onScroll = () => {
      const s = el.querySelector('.stickytop');
      if (s) s.classList.toggle('scrolled', el.scrollTop > 4);
      const d = el.querySelector('[data-dbar]');
      if (d) d.classList.toggle('scrolled', el.scrollTop > 190);
    };
    el.addEventListener('scroll', onScroll, { passive: true });

    return () => {
      cancelAnimationFrame(raf);
      el.removeEventListener('scroll', onScroll);
    };
  }, [ref]);
}
