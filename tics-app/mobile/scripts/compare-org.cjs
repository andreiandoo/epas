/* =========================================================
   AUDIT DE FIDELITATE — shell-ul de ORGANIZATOR, prototip vs port.

   Echivalentul lui compare-with-prototype.cjs, dar pentru organizer-app.html.
   Diferenta de metoda: prototipul clientului avea atribute `data-a` pe care
   se putea da click; cel de organizator nu are. In schimb `S`, `go()` si
   `render()` sunt globale, deci punem starea direct si redesenam — mai
   robust decat sa cautam butoane dupa text.

   Rulare:
     npx vite preview --port 4190
     node scripts/compare-org.cjs            # toate ecranele
     node scripts/compare-org.cjs Sales      # unul singur

   Ca si la client, fortam rama telefonului la 896px inaltime, ca .screenport
   sa fie exact 390px — aceeasi latime cu viewport-ul aplicatiei. Altfel
   textele se rup diferit si pare eroare de port acolo unde nu e.
   ========================================================= */
const CHROME = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = process.env.OUT_DIR || 'C:/Users/PC/AppData/Local/Temp/tixello-compare-org';
const PROTO = 'file:///I:/WORK/eventpilot/epas/tics-app/organizer-app.html';
const APP = 'http://localhost:4190/';
const OFFSETS = [0, 700, 1400];

/** Ecranele shell-ului standard de organizator, in ordinea din tabbar. */
const TABS = [
  { key: 'Dashboard', label: 'Panou' },
  { key: 'CheckIn', label: 'Scanare' },
  { key: 'Sales', label: 'Vânzare' },
  { key: 'Reports', label: 'Rapoarte' },
  { key: 'Settings', label: 'Setări' },
];

const only = process.argv[2];
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const fs = require('fs');
  fs.mkdirSync(OUT, { recursive: true });

  const mod = await import('puppeteer-core');
  const puppeteer = mod.default || mod;
  const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });

  const newPage = async (w, h) => {
    const page = await browser.newPage();
    await page.setViewport({ width: w, height: h, isMobile: true, deviceScaleFactor: 2 });
    page.on('pageerror', (e) => console.log('   PAGEERROR:', e.message));
    return page;
  };

  const shots = async (page, prefix, scrollSel, clipSel) => {
    const clip = clipSel
      ? await page.evaluate((s) => {
          const el = document.querySelector(s);
          if (!el) return null;
          const r = el.getBoundingClientRect();
          return { x: r.x, y: r.y, width: r.width, height: r.height };
        }, clipSel)
      : null;

    let h = 0;
    for (const off of OFFSETS) {
      h = await page.evaluate(
        (s, o) => {
          const el = document.querySelector(s);
          if (!el) return 0;
          el.scrollTop = o;
          return el.scrollHeight;
        },
        scrollSel,
        off,
      );
      if (off > 0 && off > h) continue;
      await wait(450);
      await page.screenshot({ path: `${OUT}/${prefix}-${off}.png`, ...(clip ? { clip } : {}) });
    }
    return h;
  };

  /* ---------- PROTOTIP ---------- */
  const p = await newPage(470, 1000);
  await p.goto(PROTO, { waitUntil: 'networkidle0' });
  // rama .phone are deja exact 390px latime, cat viewport-ul aplicatiei;
  // ascundem doar panoul lateral "studio", care e unealta de test
  await p.addStyleTag({ content: '.rail{display:none !important}' });
  await wait(2600);

  /** Punem starea direct: `S`, `go()` si `render()` sunt globale in prototip. */
  const protoTab = async (tab) => {
    await p.evaluate((t) => {
      // eslint-disable-next-line no-undef
      S.authed = true;
      S.mode = 'organizer';
      S.account = 'organizer';
      S.ctx = 'organizer';
      S.role = 'admin';
      S.modal = null;
      S.tab = t;
      // eslint-disable-next-line no-undef
      render();
    }, tab);
    await wait(700);
  };

  /* ---------- APLICATIE ---------- */
  const a = await newPage(390, 844);
  await a.goto(APP, { waitUntil: 'networkidle0' });
  await wait(2900);
  const clickText = async (page, t) => {
    const ok = await page.evaluate((x) => {
      const els = [...document.querySelectorAll('button,div,span,b,a')].reverse();
      const el = els.find((e) => e.textContent.trim() === x) || els.find((e) => e.textContent.trim().startsWith(x));
      if (el) el.click();
      return !!el;
    }, t);
    await wait(700);
    return ok;
  };
  await clickText(a, 'Sari peste');
  await clickText(a, 'Autentificare');
  await wait(900);
  await clickText(a, 'Nord Events'); // proprietatea de organizator standard
  await wait(1000);

  const appTab = async (label) => {
    await a.evaluate((l) => {
      const tabs = [...document.querySelectorAll('.tabbar .tab')];
      const el = tabs.find((t) => t.textContent.trim() === l);
      if (el) el.click();
    }, label);
    await wait(700);
  };

  const shell = await a.evaluate(() => document.querySelector('.app')?.className);
  if (!shell?.includes('app-org')) {
    console.log(`ATENTIE: nu am ajuns in shell-ul de organizator (class="${shell}")`);
  }

  /* ---------- comparatie ---------- */
  const rows = [];
  for (const t of TABS) {
    if (only && only !== t.key) continue;
    await protoTab(t.key);
    const ph = await shots(p, `proto-${t.key}`, '#app .screen', '.phone');
    await appTab(t.label);
    const ah = await shots(a, `app-${t.key}`, '.app-org .screen', null);
    rows.push({ tab: t.key, proto: ph, app: ah, diff: Math.abs(ph - ah) });
  }

  console.log('');
  console.log('ecran         prototip     port    diferenta');
  for (const r of rows) {
    console.log(
      `${r.tab.padEnd(12)} ${String(r.proto).padStart(7)}px ${String(r.app).padStart(7)}px ${String(r.diff).padStart(8)}px`,
    );
  }
  console.log('');
  console.log(`capturi in: ${OUT}`);

  await browser.close();
})().catch((e) => {
  console.error('FAIL:', e.message);
  process.exit(1);
});
