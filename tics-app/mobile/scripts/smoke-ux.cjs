/* =========================================================
   Smoke test pentru comportamentele cerute dupa testul pe telefon:
     1. selectorul de oras din antetul de pe Acasa
     2. "Alege un vibe" -> lista Radar filtrata pe categorie
     3. chip-urile de filtru din Radar chiar filtreaza
     4. Profil -> Portofel -> back se intoarce in Profil
     5. back-ul pastreaza pozitia de scroll

   Rulare:
     npx vite preview --port 4190
     node scripts/smoke-ux.cjs
   ========================================================= */
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const APP = process.env.APP_URL || 'http://localhost:4190/';

const wait = (ms) => new Promise((r) => setTimeout(r, ms));
let failures = 0;
const check = (name, ok, extra = '') => {
  console.log(`${ok ? 'OK  ' : 'FAIL'} ${name}${extra ? ' — ' + extra : ''}`);
  if (!ok) failures++;
};

(async () => {
  const mod = await import('puppeteer-core');
  const puppeteer = mod.default || mod;
  const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 390, height: 844, isMobile: true, deviceScaleFactor: 2 });

  const errors = [];
  page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message));

  await page.goto(APP, { waitUntil: 'networkidle2' });

  const clickText = async (t) => {
    const ok = await page.evaluate((x) => {
      const els = [...document.querySelectorAll('button,div,span,b,a')].reverse();
      const el = els.find((e) => e.textContent.trim() === x) || els.find((e) => e.textContent.trim().startsWith(x));
      if (el) el.click();
      return !!el;
    }, t);
    await wait(800);
    return ok;
  };
  const h2 = () => page.evaluate(() => document.querySelector('.h2')?.textContent ?? '');
  const h1 = () => page.evaluate(() => document.querySelector('.h1')?.textContent ?? '');

  await wait(3200);
  await clickText('Sari peste');
  await clickText('Autentificare');
  await wait(1000);
  await clickText('Andrei Popescu');
  await wait(1500);

  /* ---------- 1. selectorul de oras ---------- */
  await page.evaluate(() => document.querySelector('.hdr .loc-v')?.parentElement?.click());
  await wait(900);
  const cityOpts = await page.evaluate(() =>
    [...document.querySelectorAll('.selrow')].map((r) => r.textContent.trim()).slice(0, 6),
  );
  check('selector oras deschis', cityOpts.length > 3, cityOpts.slice(0, 4).join(', '));

  // randul selectat include bifa ("Toată România✓"), deci luam pur si simplu al doilea
  const pick = cityOpts[1];
  await page.evaluate((c) => {
    const r = [...document.querySelectorAll('.selrow')].find((x) => x.textContent.trim() === c);
    r?.click();
  }, pick);
  await wait(1200);
  const locv = await page.evaluate(() => document.querySelector('.hdr .loc-v')?.textContent.trim());
  check('orasul ales apare in antet', locv?.startsWith(pick), `${locv}`);

  // revenim la toata tara, ca restul testelor sa aiba din ce alege
  await page.evaluate(() => document.querySelector('.hdr .loc-v')?.parentElement?.click());
  await wait(700);
  await clickText('Toată România');
  await wait(1000);

  /* ---------- 2. "Alege un vibe" -> Radar pe categorie ---------- */
  await page.evaluate(() => document.querySelectorAll('.bnav .nav')[1]?.click()); // Exploreaza
  await wait(1200);
  const catName = await page.evaluate(() => {
    const c = document.querySelector('.catcard');
    const name = c?.querySelector('.catname')?.textContent?.trim();
    c?.click();
    return name;
  });
  await wait(1500);
  check('vibe deschide Radarul pe categorie', (await h2()) === catName, `titlu="${await h2()}" vibe="${catName}"`);

  /* ---------- 3. filtrele Radar ---------- */
  const titlesNow = () =>
    page.evaluate(() => [...document.querySelectorAll('.mcard.radar .ctitle')].map((e) => e.textContent));
  await page.evaluate(() => document.querySelectorAll('.bnav .nav')[0]?.click());
  await wait(1000);
  await clickText('Vezi tot');
  await wait(2500);
  const before = await titlesNow();

  await page.evaluate(() => {
    const f = [...document.querySelectorAll('.filterbar .flt')].find((x) => x.textContent.includes('Sub 100'));
    f?.click();
  });
  await wait(6000);
  const after = await titlesNow();
  const chipOn = await page.evaluate(() =>
    [...document.querySelectorAll('.filterbar .flt')].some((x) => x.textContent.includes('Sub 100') && x.classList.contains('on')),
  );
  check('chip "Sub 100 lei" se activeaza', chipOn);
  check(
    'filtrul schimba lista',
    JSON.stringify(before) !== JSON.stringify(after) || after.length === 0,
    `${before.length} -> ${after.length}`,
  );

  /* ---------- 4 + 5. Profil -> Portofel -> back, cu scroll ---------- */
  await page.evaluate(() => document.querySelectorAll('.bnav .nav')[3]?.click()); // Profil
  await wait(1200);
  await page.evaluate(() => {
    const el = document.querySelectorAll('.screen');
    el[el.length - 1].scrollTop = 320;
  });
  await wait(500);
  const scrolled = await page.evaluate(() => {
    const el = document.querySelectorAll('.screen');
    return el[el.length - 1].scrollTop;
  });

  await clickText('Portofel & carduri');
  await wait(1200);
  const onWallet = (await h1()).includes('Portofel') || (await h2()).includes('Portofel');

  await page.evaluate(() => document.querySelector('.stickytop .icon-btn, .dbar .icon-btn')?.click());
  await wait(1400);
  const onProfile = await page.evaluate(() => document.body.textContent.includes('Portofel & carduri'));
  const restored = await page.evaluate(() => {
    const el = document.querySelectorAll('.screen');
    return el[el.length - 1].scrollTop;
  });

  check('Portofel se deschide din Profil', onWallet);
  check('back din Portofel duce in Profil', onProfile);
  check('scroll-ul e pastrat la back', Math.abs(restored - scrolled) < 40, `${scrolled} -> ${restored}`);

  console.log(errors.length ? '\nERORI:\n' + errors.join('\n') : '\nNicio eroare de pagina.');
  console.log(failures ? `\n${failures} verificari picate.` : '\nToate verificarile au trecut.');
  await browser.close();
  process.exit(failures ? 1 : 0);
})();
