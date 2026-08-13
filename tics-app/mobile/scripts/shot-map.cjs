/* =========================================================
   Captura hartii cu dale REALE, fara interceptare de retea.

   Tonul hartii se face din filtre CSS peste dale OSM, iar filtrele nu pot fi
   judecate din cod — trebuie vazute. Testele de interfata servesc dale de 1x1
   transparent (ca sa nu depinda de retea), deci nu pot arata culoarea.

   Folosire:
     npx vite preview --port 4190
     OUT=harta.png node scripts/shot-map.cjs
   ========================================================= */
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const APP = process.env.APP_URL || 'http://localhost:4190/';
const OUT = process.env.OUT || 'map.png';
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const mod = await import('puppeteer-core');
  const puppeteer = mod.default || mod;
  const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
  await browser.defaultBrowserContext().overridePermissions(new URL(APP).origin, ['geolocation']);
  await page.setGeolocation({ latitude: 44.4268, longitude: 26.1025 });

  await page.goto(APP, { waitUntil: 'networkidle2' });
  await wait(3500);

  const clickText = async (t) => {
    await page.evaluate((x) => {
      const els = [...document.querySelectorAll('button,div,span,b,a')].reverse();
      const el = els.find((e) => e.textContent.trim() === x) || els.find((e) => e.textContent.trim().startsWith(x));
      if (el) el.click();
    }, t);
    await wait(900);
  };

  await clickText('Sari peste');
  await clickText('Autentificare');
  await wait(900);
  await clickText('Andrei Popescu');
  await wait(2500);

  await page.waitForFunction(() => !!document.querySelector('.leaflet-container'), { timeout: 20000 }).catch(() => {});
  await wait(6000);

  const info = await page.evaluate(() => {
    const c = document.querySelector('.leaflet-container');
    if (!c) return null;
    c.scrollIntoView({ block: 'center' });
    const nav = document.querySelector('.bnav');

    return {
      tiles: c.querySelectorAll('.leaflet-tile-loaded').length,
      pins: c.querySelectorAll('.leaflet-marker-icon').length,
      mapZ: getComputedStyle(c.parentElement).zIndex,
      navZ: nav ? getComputedStyle(nav).zIndex : 'fara bara',
    };
  });
  console.log('harta:', JSON.stringify(info));

  await wait(1500);
  await page.screenshot({ path: OUT });
  console.log('captura:', OUT);

  await browser.close();
})();
