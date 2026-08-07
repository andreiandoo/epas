/* Capturi din shell-ul de ORGANIZATOR, ca referinta inainte/dupa refactorizari
   de CSS. Rulare: node scripts/capture-org.cjs <sufix>  (ex. "inainte") */
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = process.env.OUT_DIR || 'C:/Users/PC/AppData/Local/Temp/tixello-compare';
const APP = 'http://localhost:4190/';
const TAG = process.argv[2] || 'now';

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  require('fs').mkdirSync(OUT, { recursive: true });
  const mod = await import('puppeteer-core');
  const puppeteer = mod.default || mod;
  const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 390, height: 844, isMobile: true, deviceScaleFactor: 2 });
  page.on('pageerror', (e) => console.log('   PAGEERROR:', e.message));

  const clickText = async (t) => {
    const ok = await page.evaluate((x) => {
      const els = [...document.querySelectorAll('button,div,span,b')].reverse();
      const el = els.find((e) => e.textContent.trim() === x) || els.find((e) => e.textContent.trim().startsWith(x));
      if (el) el.click();
      return !!el;
    }, t);
    await wait(700);
    return ok;
  };

  await page.goto(APP, { waitUntil: 'networkidle0' });
  await wait(700);
  await clickText('Conectare');
  await clickText('Nord Events');
  await wait(800);
  await page.screenshot({ path: `${OUT}/org-panou-${TAG}.png` });

  await clickText('Scanare');
  await page.evaluate(() => {
    const hint = [...document.querySelectorAll('div')].find(
      (e) => e.textContent.trim().startsWith('Apas') && e.children.length === 0,
    );
    if (hint) hint.parentElement.click();
  });
  await wait(600);
  await page.screenshot({ path: `${OUT}/org-scanare-${TAG}.png` });

  await clickText('Setări');
  await wait(600);
  await page.screenshot({ path: `${OUT}/org-setari-${TAG}.png` });

  console.log(`capturi org (${TAG}) in ${OUT}`);
  await browser.close();
})().catch((e) => {
  console.error('FAIL:', e.message);
  process.exit(1);
});
