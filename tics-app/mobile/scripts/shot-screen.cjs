/* =========================================================
   Captura unui ecran anume, cu date reale.

   Testele de interfata intercepteaza reteaua (ca sa fie stabile), deci nu pot
   arata cum arata CHIAR ecranul. Asta il deschide pe date reale si il
   fotografiaza — singurul mod de a judeca o banda, o culoare sau un spatiu.

   Folosire:
     npx vite preview --port 4190
     SCREEN=explore OUT=explore.png node scripts/shot-screen.cjs

   SCREEN: explore | radar | home | assistant
   ========================================================= */
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const APP = process.env.APP_URL || 'http://localhost:4190/';
const OUT = process.env.OUT || 'screen.png';
const SCREEN = process.env.SCREEN || 'explore';
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
  await wait(800);
  await clickText('Andrei Popescu');
  await wait(2200);

  if (SCREEN === 'explore' || SCREEN === 'assistant') {
    await page.evaluate(() => document.querySelector('.wave.discover')?.click());
    await wait(4000);
  }

  if (SCREEN === 'assistant') {
    await page.evaluate(() => document.querySelector('.airec-cta')?.click());
    await wait(3000);
  }

  if (SCREEN === 'radar') {
    await page.evaluate(() => {
      const n = [...document.querySelectorAll('.bnav .nav')].find(
        (x) => (x.getAttribute('aria-label') || '') === 'Radar',
      );
      n?.click();
    });
    await wait(4000);
  }

  await page.screenshot({ path: OUT });
  console.log('captura:', OUT);

  await browser.close();
})();
