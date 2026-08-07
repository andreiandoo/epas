/* =========================================================
   Smoke test pentru ecranele Radar pe DATE REALE (app.tics.ro).

   Nu compara pixeli — pentru Radar n-are sens, continutul nu mai e cel din
   prototip. Verifica ce conteaza dupa cablare: ca ecranele randeaza fara
   erori, ca datele chiar ajung in DOM si cat dureaza pana apar.

   Rulare:
     npx vite preview --port 4190
     node scripts/smoke-radar.cjs
   ========================================================= */
const CHROME =
  process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const APP = process.env.APP_URL || 'http://localhost:4190/';
const OUT = 'compare-out';

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const fs = require('fs');
  fs.mkdirSync(OUT, { recursive: true });

  const mod = await import('puppeteer-core');
  const puppeteer = mod.default || mod;
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox'],
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 390, height: 844, isMobile: true, deviceScaleFactor: 2 });

  const errors = [];
  page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message));
  page.on('console', (m) => m.type() === 'error' && errors.push('CONSOLE: ' + m.text()));

  await page.goto(APP, { waitUntil: 'networkidle2' });

  // login demo -> shell de client
  const clickText = async (t) => {
    const ok = await page.evaluate((x) => {
      const els = [...document.querySelectorAll('button,div,span,b,a')].reverse();
      const el =
        els.find((e) => e.textContent.trim() === x) || els.find((e) => e.textContent.trim().startsWith(x));
      if (el) el.click();
      return !!el;
    }, t);
    await wait(900);
    return ok;
  };

  await wait(3200); // splash
  await clickText('Sari peste');
  await clickText('Autentificare');
  await wait(1200);
  await clickText('Andrei Popescu'); // pasul "Alege contul"
  await wait(1200);
  console.log('shell:', await page.evaluate(() => document.querySelector('.app')?.className));

  const goHash = async (screen) => {
    await page.evaluate((s) => {
      const el = [...document.querySelectorAll('*')].find((e) => e.textContent.trim() === s);
      if (el) el.click();
    }, screen);
    await wait(800);
  };

  /* ---------- Radar ---------- */
  // "Vezi tot" din sectiunea Radar de pe Acasa -> ecranul ticslist
  await page.evaluate(() => {
    const h = [...document.querySelectorAll('.sec')].find((s) => s.textContent.includes('Cel mai bun pret din toata piata') || s.textContent.includes('Cel mai bun pre'));
    h?.querySelector('.more, [class*=more]')?.click();
  });
  await wait(1000);
  await clickText('Vezi tot');
  console.log('ecran:', await page.evaluate(() => document.querySelector('.h2')?.textContent));

  const t0 = Date.now();
  // asteptam DATELE REALE: titluri care nu sunt in datasetul prototipului
  const PROTO = ['Smiley Live', 'Untold 2026', 'Delia — Deliria'];
  const radar = await page
    .waitForFunction(
      (proto) => {
        const cards = [...document.querySelectorAll('.mcard.radar')];
        if (cards.length < 3) return false;
        const titles = cards.map((c) => c.querySelector('.ctitle')?.textContent ?? '');
        return titles.some((t) => !proto.includes(t)) ? titles.join('|') : false;
      },
      { timeout: 45000 },
      PROTO,
    )
    .then((h) => h.jsonValue())
    .catch(() => null);
  console.log(`Radar: ${radar ? 'OK' : 'TIMEOUT'} in ${Date.now() - t0}ms`);
  if (radar) console.log('  ', radar.split('|').slice(0, 4).join(' / '));
  await page.screenshot({ path: `${OUT}/smoke-radar.png` });

  /* ---------- Oferte (primul card) ---------- */
  await page.evaluate(() => document.querySelector('.mcard.radar')?.click());
  await wait(2500);
  const offers = await page.evaluate(() => {
    const rows = [...document.querySelectorAll('.selrow')];
    return rows.map((r) => r.textContent.replace(/\s+/g, ' ').trim().slice(0, 60));
  });
  console.log(`Oferte: ${offers.length} platforme`);
  offers.slice(0, 4).forEach((o) => console.log('   ', o));
  await page.screenshot({ path: `${OUT}/smoke-ticsoffers.png` });

  /* ---------- Calendar ---------- */
  // inapoi in Radar (sageata din dbar), apoi iconita de calendar din topbar
  await page.evaluate(() => document.querySelector('.dbar .icon-btn')?.click());
  await wait(1200);
  await page.evaluate(() => {
    const btns = [...document.querySelectorAll('.stickytop .hrow .icon-btn')];
    btns[btns.length - 1]?.click();
  });
  await wait(1500);
  console.log('ecran calendar:', await page.evaluate(() => document.querySelector('.h2')?.textContent));
  const t1 = Date.now();
  const cal = await page
    .waitForFunction(
      () => {
        const t = document.body.textContent;
        return /\d[\d.]* ev\./.test(t) ? t.match(/\+?[\d.]+ ev\./)[0] : false;
      },
      { timeout: 60000 },
    )
    .then((h) => h.jsonValue())
    .catch(() => null);
  console.log(`Calendar: ${cal ?? 'TIMEOUT'} in ${Date.now() - t1}ms`);
  await page.screenshot({ path: `${OUT}/smoke-calendar.png` });

  console.log(errors.length ? '\nERORI:\n' + errors.join('\n') : '\nNicio eroare in consola.');
  await browser.close();
})();
