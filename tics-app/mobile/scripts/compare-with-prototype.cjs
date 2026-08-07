/* =========================================================
   Comparatie 1:1 prototip vs port, pentru un ecran anume.

   Prototipul ruleaza intr-o rama de telefon desenata, deci decupam exact aria
   .screenport. Aplicatia ocupa tot viewport-ul. Capturam ambele la aceleasi
   pozitii de scroll => imagini direct comparabile.

   Rulare:
     node scripts/compare-with-prototype.cjs home
     node scripts/compare-with-prototype.cjs explore
     OUT_DIR=... node scripts/compare-with-prototype.cjs tickets

   Necesita: `npm i --no-save puppeteer-core` si `vite preview --port 4190`.
   ========================================================= */
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = process.env.OUT_DIR || 'C:/Users/PC/AppData/Local/Temp/tixello-compare';
const PROTO = 'file:///I:/WORK/eventpilot/epas/tics-app/client-app.html';
const APP = 'http://localhost:4190/';
const OFFSETS = [0, 700, 1400, 2100];

const SCREEN = process.argv[2] || 'home';

/** Cum se ajunge la fiecare ecran, in fiecare dintre cele doua medii. */
const ROUTES = {
  home: { proto: [], app: [] },
  explore: { proto: ['tab:explore'], app: [{ nav: 'explore' }] },
  tickets: { proto: ['tab:tickets'], app: [{ nav: 'tickets' }] },
  wallet: { proto: ['tab:wallet'], app: [{ nav: 'wallet' }] },
  profile: { proto: ['tab:profile'], app: [{ nav: 'profile' }] },
  ticket: {
    proto: ['tab:tickets', 'go:ticket:coldplay:0'],
    app: [{ nav: 'tickets' }, { text: 'Coldplay' }],
  },
  transfer: {
    proto: ['tab:tickets', 'go:ticket:coldplay:0', 'go:transfer:coldplay:0'],
    app: [{ nav: 'tickets' }, { text: 'Coldplay' }, { text: 'Transferă biletul' }],
  },
  event: { proto: ['go:event:coldplay'], app: [{ text: 'Coldplay' }] },
  tickettypes: {
    proto: ['go:event:coldplay', 'go:tickettypes'],
    app: [{ text: 'Coldplay' }, { text: 'Alege bilete' }],
  },
  seatmap: {
    proto: ['go:event:coldplay', 'go:tickettypes', 'go:seatmap'],
    app: [{ text: 'Coldplay' }, { text: 'Alege bilete' }, { text: 'Continuă ·' }],
  },
  expdate: { proto: ['go:event:salina', 'go:expdate'], app: [{ text: 'Salina Turda' }, { text: 'Alege data' }] },
  topup: { proto: ['tab:wallet', 'go:topup'], app: [{ nav: 'wallet' }, { text: 'Încarcă' }] },
  payqr: { proto: ['tab:wallet', 'go:payqr'], app: [{ nav: 'wallet' }, { text: 'Plătește' }] },
};

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const fs = require('fs');
  fs.mkdirSync(OUT, { recursive: true });

  const mod = await import('puppeteer-core');
  const puppeteer = mod.default || mod;
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--allow-file-access-from-files'],
  });

  const newPage = async (w, h) => {
    const page = await browser.newPage();
    await page.setViewport({ width: w, height: h, isMobile: true, deviceScaleFactor: 2 });
    page.on('pageerror', (e) => console.log('   PAGEERROR:', e.message));
    return page;
  };

  const clickAttr = async (page, a) => {
    const ok = await page.evaluate((x) => {
      const el = document.querySelector(`[data-a="${x}"]`);
      if (el) el.click();
      return !!el;
    }, a);
    await wait(700);
    return ok;
  };

  const clickText = async (page, t) => {
    const ok = await page.evaluate((x) => {
      const els = [...document.querySelectorAll('button,div,span,b')].reverse();
      const el = els.find((e) => e.textContent.trim() === x) || els.find((e) => e.textContent.trim().startsWith(x));
      if (el) el.click();
      return !!el;
    }, t);
    await wait(700);
    return ok;
  };

  /** In aplicatie, tab-urile se apasa dupa iconita din .bnav.
      ATENTIE: .bnav .nav are DOAR 4 elemente — FAB-ul (wallet) e .fab, separat. */
  const clickNav = async (page, id) => {
    const order = ['home', 'explore', 'tickets', 'profile'];
    const idx = order.indexOf(id);
    const ok = await page.evaluate(
      (i, isWallet) => {
        if (isWallet) {
          const fab = document.querySelector('.bnav .fab');
          if (fab) fab.click();
          return !!fab;
        }
        const navs = document.querySelectorAll('.bnav .nav');
        if (!navs[i]) return false;
        navs[i].click();
        return true;
      },
      idx < 0 ? 0 : idx,
      id === 'wallet',
    );
    await wait(800);
    return ok;
  };

  const shots = async (page, prefix, scrollSel, clipSel) => {
    const clip = clipSel
      ? await page.evaluate((s) => {
          const r = document.querySelector(s).getBoundingClientRect();
          return { x: r.x, y: r.y, width: r.width, height: r.height };
        }, clipSel)
      : null;

    for (const off of OFFSETS) {
      const h = await page.evaluate(
        (s, o) => {
          const el = document.querySelector(s);
          if (!el) return 0;
          el.scrollTop = o;
          return el.scrollHeight;
        },
        scrollSel,
        off,
      );
      if (off > 0 && off > h) continue; // nu capturam sub finalul continutului
      await wait(600);
      await page.screenshot({ path: `${OUT}/${prefix}-${off}.png`, ...(clip ? { clip } : {}) });
    }
    const h = await page.evaluate((s) => document.querySelector(s)?.scrollHeight ?? 0, scrollSel);
    console.log(`  ${prefix}: continut ${h}px`);
    return h;
  };

  const route = ROUTES[SCREEN] || ROUTES.home;

  /* ---------- PROTOTIP ----------
     Rama .device are `height:min(844px, 100vh-64px)` si `aspect-ratio:390/844`,
     deci la dimensiunea ei implicita ecranul interior are DOAR ~362px latime —
     mai putin decat un telefon real (390px). Comparat asa, textele se rup
     diferit si pare o eroare de port, desi nu e.
     Fortam inaltimea ramei la 896px => latime 414px => screenport exact 390px,
     identic cu viewport-ul aplicatiei. */
  console.log(`ecran: ${SCREEN}`);
  console.log('prototip:');
  const p = await newPage(470, 1000);
  await p.goto(PROTO, { waitUntil: 'networkidle0' });
  await p.addStyleTag({ content: '.device{height:896px !important}' });
  await wait(2700); // splash-ul se auto-avanseaza la 2200ms
  await clickAttr(p, 'go:login'); // "Sari peste" din onboarding
  await clickText(p, 'Autentificare');
  await wait(800);
  for (const a of route.proto) await clickAttr(p, a);
  const ph = await shots(p, `proto-${SCREEN}`, '.vp .screen', '.screenport');

  /* ---------- APLICATIE ---------- */
  console.log('aplicatie:');
  const a = await newPage(390, 844);
  await a.goto(APP, { waitUntil: 'networkidle0' });
  await wait(700);
  await clickText(a, 'Conectare');
  await clickText(a, 'Andrei Popescu');
  await wait(700);
  for (const step of route.app) {
    if (step.nav) await clickNav(a, step.nav);
    else if (step.text) {
      const ok = await clickText(a, step.text);
      if (!ok) console.log(`   nu am gasit in aplicatie: "${step.text}"`);
    }
  }
  const ah = await shots(a, `app-${SCREEN}`, '.app-client .screen', null);

  console.log('');
  console.log(`diferenta de inaltime: ${Math.abs(ph - ah)}px  (proto ${ph} / app ${ah})`);
  console.log(`capturi in: ${OUT}`);

  await browser.close();
})().catch((e) => {
  console.error('FAIL:', e.message);
  process.exit(1);
});
