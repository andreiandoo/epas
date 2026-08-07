/* Comparatie 1:1 prototip vs port.
   Prototipul ruleaza intr-o rama de telefon desenata, deci decupam exact aria
   .screenport. Aplicatia ocupa tot viewport-ul. Capturam ambele la aceleasi
   pozitii de scroll => imagini direct comparabile, 390x844. */
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = process.env.OUT_DIR || 'C:/Users/PC/AppData/Local/Temp/tixello-compare';
const PROTO = 'file:///I:/WORK/eventpilot/epas/tics-app/client-app.html';
const APP = 'http://localhost:4190/';
const OFFSETS = [0, 700, 1400];

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const mod = await import('puppeteer-core');
  const puppeteer = mod.default || mod;
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--allow-file-access-from-files'],
  });

  const newPage = async (w = 390, h = 844) => {
    const page = await browser.newPage();
    await page.setViewport({ width: w, height: h, isMobile: true, deviceScaleFactor: 2 });
    page.on('pageerror', (e) => console.log('   PAGEERROR:', e.message));
    return page;
  };

  const clickAttr = (page, a) =>
    page.evaluate((x) => {
      const el = document.querySelector(`[data-a="${x}"]`);
      if (el) el.click();
      return !!el;
    }, a);

  const clickText = (page, t) =>
    page.evaluate((x) => {
      const els = [...document.querySelectorAll('button,div,span,b')].reverse();
      const el = els.find((e) => e.textContent.trim() === x) || els.find((e) => e.textContent.trim().startsWith(x));
      if (el) el.click();
      return !!el;
    }, x => x);

  const clickTextArg = async (page, t) => {
    const ok = await page.evaluate((x) => {
      const els = [...document.querySelectorAll('button,div,span,b')].reverse();
      const el = els.find((e) => e.textContent.trim() === x) || els.find((e) => e.textContent.trim().startsWith(x));
      if (el) el.click();
      return !!el;
    }, t);
    await wait(600);
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
      await page.evaluate(
        (s, o) => {
          const el = document.querySelector(s);
          if (el) el.scrollTop = o;
        },
        scrollSel,
        off,
      );
      await wait(500);
      await page.screenshot({ path: `${OUT}/${prefix}-${off}.png`, ...(clip ? { clip } : {}) });
    }
    const h = await page.evaluate((s) => document.querySelector(s)?.scrollHeight ?? 0, scrollSel);
    console.log(`  ${prefix}: continut ${h}px`);
    return h;
  };

  /* ---------- PROTOTIP ---------- */
  console.log('prototip:');
  const p = await newPage(430, 900);
  await p.goto(PROTO, { waitUntil: 'networkidle0' });
  await wait(2700);
  await clickAttr(p, 'go:login');
  await wait(500);
  await clickTextArg(p, 'Autentificare');
  await wait(1000);
  const ph = await shots(p, 'proto', '.vp .screen', '.screenport');

  /* ---------- APLICATIE ---------- */
  console.log('aplicatie:');
  const a = await newPage(390, 844);
  await a.goto(APP, { waitUntil: 'networkidle0' });
  await wait(700);
  await clickTextArg(a, 'Conectare');
  await clickTextArg(a, 'Andrei Popescu');
  await wait(800);
  const ah = await shots(a, 'app', '.app-client .screen', null);

  console.log('');
  console.log(`diferenta de inaltime: ${Math.abs(ph - ah)}px  (proto ${ph} / app ${ah})`);

  await browser.close();
})().catch((e) => {
  console.error('FAIL:', e.message);
  process.exit(1);
});
