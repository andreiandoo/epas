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
  await page.waitForFunction(() => document.querySelectorAll('.catcard').length > 12, { timeout: 40000 }).catch(() => {});
  await wait(800);
  const nCats = await page.evaluate(() => document.querySelectorAll('.catcard').length);
  check('categorii reale in "Alege un vibe"', nCats > 6, `${nCats} categorii`);
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
  // lista porneste goala (fara date demo), deci asteptam cardurile reale
  await page.waitForFunction(() => document.querySelectorAll('.mcard.radar').length > 0, { timeout: 40000 }).catch(() => {});
  await wait(1200);
  const before = await titlesNow();
  check('Radarul incarca evenimente reale', before.length >= 6, `${before.length} carduri`);

  /* "Incarca mai multe" */
  const more = await page.evaluate(() => {
    const b = [...document.querySelectorAll('button')].find((x) => x.textContent.includes('Incarc') || x.textContent.includes('Încarc'));
    if (b) b.click();
    return !!b;
  });
  if (more) {
    await page.waitForFunction((n) => document.querySelectorAll('.mcard.radar').length > n, { timeout: 45000 }, before.length).catch(() => {});
    const grown = (await titlesNow()).length;
    check('"Incarca mai multe" aduce evenimente', grown > before.length, `${before.length} -> ${grown}`);
  } else {
    check('"Incarca mai multe" exista', false);
  }

  await page.evaluate(() => {
    const f = [...document.querySelectorAll('.filterbar .flt')].find((x) => x.textContent.includes('Sub 100'));
    f?.click();
  });
  // dupa "Incarca mai multe" lista e mai mare, deci refetch-ul dureaza mai mult
  await page
    .waitForFunction(
      () => {
        const p = [...document.querySelectorAll('.mcard.radar .amt')].map((e) => parseInt(e.textContent.replace(/\D/g, ''), 10));
        return p.length > 0 && p.every((x) => x <= 100);
      },
      { timeout: 40000 },
    )
    .catch(() => {});
  const after = await titlesNow();
  const chipOn = await page.evaluate(() =>
    [...document.querySelectorAll('.filterbar .flt')].some((x) => x.textContent.includes('Sub 100') && x.classList.contains('on')),
  );
  check('chip "Sub 100 lei" se activeaza', chipOn);
  // verificam EFECTUL, nu ca lista s-a schimbat: daca toate erau deja ieftine,
  // e corect sa ramana aceleasi
  const prices = await page.evaluate(() =>
    [...document.querySelectorAll('.mcard.radar .amt')].map((e) => parseInt(e.textContent.replace(/\D/g, ''), 10)),
  );
  check(
    'filtrul lasa doar preturi sub 100',
    prices.length > 0 && prices.every((p) => p <= 100),
    `${after.length} carduri, preturi ${prices.join(', ')}`,
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

  /* ---------- preferintele se retin intre porniri ---------- */
  /* Profil -> Setări cont -> Preferințele mele. `.bnav .nav` are patru intrari
     (butonul din mijloc e `.fab`, nu `.nav`), deci profilul e ultima. */
  await page.evaluate(() => {
    const navs = document.querySelectorAll('.bnav .nav');
    navs[navs.length - 1]?.click();
  });
  await wait(1000);
  const prefBefore = await page.evaluate(() => localStorage.getItem('tixello.prefs'));
  await clickText('Setări cont');
  await wait(900);
  await page.evaluate(() => {
    const el = [...document.querySelectorAll('.listitem')].find((e) => /Preferințele mele/.test(e.textContent ?? ''));
    el?.click();
  });
  await wait(1200);
  const toggled = await page.evaluate(() => {
    const b = [...document.querySelectorAll('button.pref')].find((x) => !x.classList.contains('on'));
    const label = b?.textContent?.trim();
    b?.click();

    return label ?? '';
  });
  await wait(500);
  const prefAfter = await page.evaluate(() => localStorage.getItem('tixello.prefs'));
  check(
    'preferintele se scriu in localStorage',
    prefAfter !== null && prefAfter !== prefBefore,
    `a bifat „${toggled}"`,
  );

  /* ---------- orasul ales se retine ---------- */
  const savedCity = await page.evaluate(() => localStorage.getItem('tixello.city'));
  check('orasul ales e pastrat intre porniri', savedCity !== null, `„${savedCity}"`);

  /* ---------- ecranul de prieteni ---------- */
  await page.evaluate(() => {
    const navs = document.querySelectorAll('.bnav .nav');
    navs[navs.length - 1]?.click();
  });
  await wait(900);
  const opened = await page.evaluate(() => {
    const el = [...document.querySelectorAll('.listitem')].find((e) => /Prietenii mei/.test(e.textContent ?? ''));
    el?.click();

    return !!el;
  });
  await wait(1200);
  const friendsScreen = await page.evaluate(() => ({
    title: document.querySelector('.h2')?.textContent ?? '',
    body: document.body.textContent.replace(/\s+/g, ' ').slice(0, 300),
  }));
  check('ecranul de prieteni se deschide din Profil', opened && /Prietenii mei/.test(friendsScreen.title), friendsScreen.title);
  /* Fara cont Tixello, ecranul TREBUIE sa spuna asta, nu sa ramana gol: aici
     nu exista date demo, iar un ecran alb ar parea o eroare. */
  check(
    'fara cont, ecranul explica de ce e gol',
    /Intră în contul Tixello/.test(friendsScreen.body),
    friendsScreen.body.slice(0, 90),
  );

  console.log(errors.length ? '\nERORI:\n' + errors.join('\n') : '\nNicio eroare de pagina.');
  console.log(failures ? `\n${failures} verificari picate.` : '\nToate verificarile au trecut.');
  await browser.close();
  process.exit(failures ? 1 : 0);
})();
