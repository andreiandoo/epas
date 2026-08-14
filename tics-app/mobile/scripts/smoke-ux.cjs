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
  /* „Descoperă" nu e in bara: se intra din BANNERUL de pe Acasa. Era un chip
     in randul de categorii, ceea ce il facea sa arate a filtru, desi e un
     ecran intreg. */
  await page.evaluate(() => {
    const b = [...document.querySelectorAll('.wave.discover')][0];
    b?.click();
  });
  await page.waitForFunction(() => document.querySelectorAll('.catcard').length > 12, { timeout: 40000 }).catch(() => {});
  await wait(800);
  /* Banda „Recomandări AI" si intrarea in Asistent, dupa macheta. */
  const ai = await page.evaluate(() => {
    const band = document.querySelector('.airec');
    const cta = document.querySelector('.airec-cta');
    cta?.click();

    return { band: !!band, cta: cta?.textContent.replace(/\s+/g, ' ').trim() ?? '' };
  });
  await wait(1600);
  const assistant = await page.evaluate(() => ({
    title: document.querySelector('.h2')?.textContent ?? '',
    bubbles: document.querySelectorAll('.aibubble').length,
    body: document.body.textContent.replace(/\s+/g, ' ').slice(0, 200),
  }));
  check('banda „Recomandări AI" exista', ai.band, ai.cta);
  check('butonul duce in „tics AI"', /tics AI/.test(assistant.title), assistant.title);
  check('asistentul compune un plan', /Planul tău/.test(assistant.body) || assistant.bubbles >= 2, `${assistant.bubbles} bule`);

  /* Selectoarele cerute: ziua (planurile trebuie sa fie in ACEEASI seara) si
     orasul cautabil (orice localitate, nu doar cele din feed). */
  const controls = await page.evaluate(() => {
    const t = document.body.textContent.replace(/\s+/g, ' ');

    return {
      day: /Când/.test(t) && /Azi/.test(t) && /Mâine/.test(t),
      cityBtn: /Alege orașul|Oriunde/.test(t),
      reroll: /Dă-mi alte sugestii/.test(t),
    };
  });
  check('exista selector de zi', controls.day);
  check('orasul se alege dintr-o cautare', controls.cityBtn);
  check('exista butonul de alte sugestii', controls.reroll);
  /* Back-ul din Asistent: TopBar randeaza `.stickytop > .hrow`, nu `.topbar`. */
  await page.evaluate(() => document.querySelector('.stickytop .hrow .icon-btn')?.click());
  await wait(1200);

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
  /* Radarul are acum intrare proprie in bara de jos; „Vezi tot" de pe Acasa
     duce in alta parte de cand ecranul a fost restructurat. */
  await page.evaluate(() => document.querySelector('.bnav .nav[aria-label="Radar"]')?.click());
  await wait(1200);
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
  /* Dupa eticheta, nu dupa index: bara are acum cinci intrari si alta ordine,
     iar un index scris fix ar fi trimis testul in alt ecran la fiecare
     rearanjare. */
  await page.evaluate(() => document.querySelector('.bnav .nav[aria-label="Contul meu"]')?.click());
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
  await page.evaluate(() => document.querySelector('.bnav .nav[aria-label="Contul meu"]')?.click());
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
  await page.evaluate(() => document.querySelector('.bnav .nav[aria-label="Contul meu"]')?.click());
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
    /* Citit ACUM, cat suntem pe ecran: verificarile de mai jos ruleaza dupa ce
       testul a mai umblat prin aplicatie, iar DOM-ul nu mai e cel de aici. */
    canConnect: document.querySelectorAll('input[type="password"]').length > 0,
  }));
  /* ---------- Radar: mai multe orase deodata ---------- */
  await page.evaluate(() => {
    const nav = [...document.querySelectorAll('.bnav .nav')].find((n) => /Radar/.test(n.getAttribute('aria-label') || ''));
    nav?.click();
  });
  await wait(2500);

  const cityLineOpened = await page.evaluate(() => {
    const b = document.querySelector('.citypick');
    b?.click();

    return !!b;
  });
  await wait(700);
  /* Selectorul sta langa titlu (nu pe un rand propriu, cum era o versiune) si
     isi scurteaza textul — numele intregi sunt oricum in titlul de deasupra. */
  check('selectorul de orase sta langa titlu', cityLineOpened);

  /* Doua orase, fara ca foaia sa se inchida intre ele: o foaie care se inchide
     la prima bifa face imposibila alegerea a doua. */
  await page.evaluate(() => {
    const rows = [...document.querySelectorAll('.selrow')].filter((r) => !/Toată România/.test(r.textContent));
    rows[0]?.click();
  });
  await wait(400);
  await page.evaluate(() => {
    const rows = [...document.querySelectorAll('.selrow')].filter((r) => !/Toată România/.test(r.textContent));
    rows[1]?.click();
  });
  /* Asteptam randarea: bifele sunt stare React, nu un atribut pus de click. */
  await wait(500);
  const multi = await page.evaluate(() => ({
    stillOpen: !!document.querySelector('.selrow'),
    checked: document.querySelectorAll('.selrow .cbx.on').length,
  }));
  check('foaia ramane deschisa intre bife', multi.stillOpen);
  check('doua orase raman bifate', multi.checked === 2, `${multi.checked} bifate`);

  await page.evaluate(() => {
    const b = [...document.querySelectorAll('button.chip')].find((x) => x.textContent.trim() === 'Gata');
    b?.click();
  });
  await wait(1500);

  const header = await page.evaluate(() => ({
    eyebrow: document.querySelector('.eyebrow')?.textContent ?? '',
    line: document.querySelector('.citypick-txt')?.textContent ?? '',
    badge: document.querySelector('.citypick-n')?.textContent ?? '',
    live: /prețuri comparate live/.test(document.body.textContent),
  }));
  check('titlul poarta orasele alese', header.eyebrow.includes(' și ') || /\+\d/.test(header.eyebrow), header.eyebrow);
  check('selectorul arata cate orase sunt', header.badge === '2', `badge="${header.badge}"`);
  check('textul „prețuri comparate live" a disparut', !header.live);

  /* Voalul din spatele barei de jos, pe orice ecran cu bara. */
  const veil = await page.evaluate(() => {
    const v = document.querySelector('.navveil');
    if (!v) return null;
    const nav = document.querySelector('.bnav').getBoundingClientRect();
    const r = v.getBoundingClientRect();

    return { below: Math.round(r.bottom), coversNav: r.top <= nav.top, h: Math.round(r.height) };
  });
  check('voalul acopera zona de sub bara', !!veil && veil.coversNav, veil ? `${veil.h}px inaltime` : 'lipseste');

  check('ecranul de prieteni se deschide din Profil', opened && /Prietenii mei/.test(friendsScreen.title), friendsScreen.title);
  /* Fara cont tics, ecranul TREBUIE sa ofere legarea contului, nu doar sa
     explice de ce e gol: mesajul de dinainte era un capat de drum — spunea
     „intra in contul tics" fara nicio cale spre acel cont. */
  check(
    'fara cont, ecranul ofera legarea contului tics',
    /Conectează contul tics/.test(friendsScreen.body) && friendsScreen.canConnect,
    friendsScreen.body.slice(0, 90),
  );

  /* ---------- categoria nu mai cade pe evenimente demo ---------- */
  await page.evaluate(() => document.querySelector('.bnav .nav[aria-label="Acasă"]')?.click());
  await wait(900);
  const wentToCategory = await page.evaluate(() => {
    const b = [...document.querySelectorAll('button.chip')].find((x) => /Teatru/.test(x.textContent ?? ''));
    b?.click();

    return !!b;
  });
  await wait(2500);
  const category = await page.evaluate(() => ({
    body: document.body.textContent.replace(/\s+/g, ' '),
    cards: document.querySelectorAll('.mcard').length,
  }));
  /* „Coldplay" e din datasetul prototipului. Daca apare pe un ecran de
     categorie, inseamna ca fallback-ul demo s-a intors. */
  check(
    'categoria NU arata evenimente demo',
    wentToCategory && !/Coldplay/.test(category.body),
    `${category.cards} carduri`,
  );

  /* ---------- bifa „Ține-mă minte" exista si comuta ---------- */
  await page.evaluate(() => localStorage.clear());
  await page.reload({ waitUntil: 'networkidle2' });
  await wait(3400);
  await clickText('Sari peste');
  await wait(700);
  const cbxBefore = await page.evaluate(() => {
    const el = document.querySelector('.cbx');
    if (!el) return null;
    el.parentElement?.click();

    return el.classList.contains('on');
  });
  /* React randeaza dupa click; citirea imediata prinde starea veche. */
  await wait(400);
  const cbxAfter = await page.evaluate(() => document.querySelector('.cbx')?.classList.contains('on') ?? null);
  check(
    'bifa „Ține-mă minte" exista si comuta',
    cbxBefore !== null && cbxBefore !== cbxAfter,
    `${cbxBefore} -> ${cbxAfter}`,
  );

  console.log(errors.length ? '\nERORI:\n' + errors.join('\n') : '\nNicio eroare de pagina.');
  console.log(failures ? `\n${failures} verificari picate.` : '\nToate verificarile au trecut.');
  await browser.close();
  process.exit(failures ? 1 : 0);
})();
