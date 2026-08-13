/* =========================================================
   Smoke test pentru comportamentul cerut in ecranul „Pe val":
     1. bara de sus, rail-ul lateral si blocul de text NU stau in scroller
     2. la schimbarea short-ului nodurile raman ACELEASI (nu se remonteaza),
        se schimba doar textul din ele
     3. zona de jos e mai sus decat era (info 34px / rail 32px in prototip)
     4. swipe stanga->dreapta = inapoi; dreapta->stanga = detaliu

   Feed-ul e servit local (request interception): testul nu depinde de retea,
   de starea serverului sau de un cont.

   Rulare:
     npx vite build && npx vite preview --port 4191
     node scripts/smoke-shorts.cjs
   ========================================================= */
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const APP = process.env.APP_URL || 'http://localhost:4191/';

const wait = (ms) => new Promise((r) => setTimeout(r, ms));
let failures = 0;
const check = (name, ok, extra = '') => {
  console.log(`${ok ? 'OK  ' : 'FAIL'} ${name}${extra ? ' — ' + extra : ''}`);
  if (!ok) failures++;
};

const short = (id, title, likes) => ({
  id,
  source: 'upload',
  feed: 'for_you',
  playback: { hls_url: null, poster_url: null, blurhash: null },
  embed_html: null,
  source_url: null,
  duration: null,
  aspect: '9:16',
  title,
  caption: `descriere ${id}`,
  hashtags: [],
  language: null,
  music_credit: null,
  owner: {
    type: 'venue',
    id: 10 + id,
    slug: `v${id}`,
    name: `Loc ${id}`,
    label: 'Locație',
    details: [
      { icon: 'pin', text: `Str. Exemplu ${id}, Oras` },
      { icon: 'star', text: `4.${id} · 120 recenzii` },
    ],
  },
  event: { id: 100 + id, slug: `e${id}`, title, date: null },
  cta: {
    type: 'buy_tickets',
    label: `de la ${80 + id} lei`,
    url: null,
    ticket_type_id: null,
    promo_code: null,
    on_sale_at: null,
    pending: false,
  },
  content_flags: [],
  stats: { likes, views: 0, shares: 0 },
  viewer: { liked: false, saved: false, reminded: false },
});

(async () => {
  const mod = await import('puppeteer-core');
  const puppeteer = mod.default || mod;
  const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });

  const errors = [];
  page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message));

  await page.setRequestInterception(true);
  page.on('request', (req) => {
    const url = req.url();

    if (url.includes('/tenant-client/shorts') && !url.includes('/events')) {
      return req.respond({
        status: 200,
        contentType: 'application/json',
        headers: { 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({
          success: true,
          data: {
            feed: 'for_you',
            items: [short(1, 'Primul short', 1200), short(2, 'Al doilea short', 34)],
            playback: null,
            next_cursor: null,
          },
        }),
      });
    }

    // orice alt apel extern (Radar, telemetrie) primeste un raspuns gol:
    // testul e despre randare, nu despre retea
    if (/^https?:\/\/(?!localhost)/.test(url)) {
      return req.respond({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{}}' });
    }

    return req.continue();
  });

  await page.goto(APP, { waitUntil: 'networkidle2' });

  const clickText = async (t) => {
    const ok = await page.evaluate((x) => {
      const els = [...document.querySelectorAll('button,div,span,b,a')].reverse();
      const el = els.find((e) => e.textContent.trim() === x) || els.find((e) => e.textContent.trim().startsWith(x));
      if (el) el.click();
      return !!el;
    }, t);
    await wait(700);
    return ok;
  };

  await wait(3200);
  await clickText('Sari peste');
  await clickText('Autentificare');
  await wait(900);
  await clickText('Andrei Popescu');
  await wait(1400);

  /* ---------- intram in „Pe val" (chip-ul din antetul de pe Acasa) ---------- */
  const enterShorts = () =>
    page.evaluate(() => {
      const el = [...document.querySelectorAll('button.chip')].find((b) => b.textContent.trim() === 'Pe val');
      el?.click();

      return !!el;
    });

  const opened = await enterShorts();
  await wait(1500);

  const hasChrome = await page.evaluate(() => !!document.querySelector('.shchrome'));
  check('ecranul „Pe val" cu feed live', hasChrome, opened ? '' : 'nu am gasit intrarea');

  if (!hasChrome) {
    console.log('\nNu pot continua fara feed-ul live.');
    await browser.close();
    process.exit(1);
  }

  /* ---------- 1. comenzile sunt in afara scroller-ului ---------- */
  const outside = await page.evaluate(() => {
    const scroller = document.querySelector('.shorts');
    const names = ['.shtop', '.info', '.rail'];

    return names.map((n) => {
      const el = document.querySelector('.shchrome ' + n);

      return { n, found: !!el, inScroller: el ? scroller.contains(el) : true };
    });
  });
  for (const r of outside) {
    check(`${r.n} exista si e in afara scroller-ului`, r.found && !r.inScroller);
  }

  /* ---------- 2. nodurile persista la schimbarea short-ului ---------- */
  await page.evaluate(() => {
    // marcam nodurile ca sa vedem daca supravietuiesc
    document.querySelector('.shchrome .shtop button').dataset.smoke = 'back';
    document.querySelector('.shchrome .rail button').dataset.smoke = 'like';
    document.querySelector('.shchrome .shcta').dataset.smoke = 'cta';
  });

  const before = await page.evaluate(() => ({
    title: document.querySelector('.shchrome .shtitle span').textContent,
    likes: document.querySelector('.shchrome .rail button > span')?.textContent ?? '',
    cta: document.querySelector('.shchrome .shcta').textContent.trim(),
    infoBottom: document.querySelector('.shchrome .info').getBoundingClientRect().bottom,
    railBottom: document.querySelector('.shchrome .rail').getBoundingClientRect().bottom,
  }));

  await page.evaluate(() => {
    const s = document.querySelector('.shorts');
    s.scrollTo({ top: s.clientHeight, behavior: 'auto' });
  });
  await wait(1200);

  const after = await page.evaluate(() => ({
    survived: {
      back: document.querySelector('.shchrome .shtop button')?.dataset.smoke === 'back',
      like: document.querySelector('.shchrome .rail button')?.dataset.smoke === 'like',
      cta: document.querySelector('.shchrome .shcta')?.dataset.smoke === 'cta',
    },
    likes: document.querySelector('.shchrome .rail button > span')?.textContent ?? '',
    cta: document.querySelector('.shchrome .shcta').textContent.trim(),
    infoBottom: document.querySelector('.shchrome .info').getBoundingClientRect().bottom,
    railBottom: document.querySelector('.shchrome .rail').getBoundingClientRect().bottom,
  }));

  check('butonul de inapoi e acelasi nod dupa schimbarea short-ului', after.survived.back);
  check('butonul lateral e acelasi nod', after.survived.like);
  check('butonul de bilete e acelasi nod', after.survived.cta);
  check('cifra de la like s-a schimbat', before.likes !== after.likes, `${before.likes} -> ${after.likes}`);
  check('textul butonului de bilete s-a schimbat', before.cta !== after.cta, `"${before.cta}" -> "${after.cta}"`);
  check(
    'rail-ul a ramas la aceeasi inaltime',
    Math.abs(before.railBottom - after.railBottom) < 1,
    `${before.railBottom} -> ${after.railBottom}`,
  );

  /* ---------- 3. zona de jos a urcat ---------- */
  const geom = await page.evaluate(() => {
    const h = window.innerHeight;
    const info = document.querySelector('.shchrome .info').getBoundingClientRect();
    const rail = document.querySelector('.shchrome .rail').getBoundingClientRect();

    /* Bara de jos e ascunsa in Shorts, deci o masuram prin stilul ei calculat,
       nu prin pozitia din pagina (translatata in afara ecranului). */
    const nav = document.querySelector('.bnav');
    const navGap = nav ? parseFloat(getComputedStyle(nav).bottom) : -1;

    return { infoGap: h - info.bottom, railGap: h - rail.bottom, navGap };
  });
  const meta = await page.evaluate(() => ({
    pill: document.querySelector('.shchrome .gpill')?.textContent.trim(),
    title: document.querySelector('.shchrome .shtitle span')?.textContent.trim(),
    details: [...document.querySelectorAll('.shchrome .cmeta .i')].map((e) => e.textContent.trim()),
  }));
  check('pastila arata TIPUL, nu numele', meta.pill === 'Locație', `pastila="${meta.pill}" titlu="${meta.title}"`);
  check('detaliile locatiei apar sub titlu', meta.details.length === 2, meta.details.join(' | '));

  /* Linia comuna e `--ep-bottom-line` (45px + safe-bottom); prototipul avea
     34px pentru text si 32 pentru rail. Verificam ca ambele stau pe ea. */
  check('blocul de text sta pe linia comuna', geom.infoGap >= 45, `${Math.round(geom.infoGap)}px de jos`);
  check('rail-ul sta pe linia comuna', geom.railGap >= 43, `${Math.round(geom.railGap)}px de jos`);
  /* „Pe val" e acum tab, deci bara ramane vizibila peste feed, iar comenzile
     short-ului trebuie sa stea DEASUPRA ei — nu aliniate cu ea, cum era cand
     bara se ascundea aici. */
  check(
    'comenzile din shorts stau deasupra barei de jos',
    geom.railGap > geom.navGap + 40,
    `rail ${Math.round(geom.railGap)}px vs nav ${Math.round(geom.navGap)}px`,
  );

  /* ---------- 3b. titlul lung se micsoreaza, descrierea se plieaza ---------- */
  const titleInfo = await page.evaluate(() => {
    const el = document.querySelector('.shchrome .shtitle');
    const wrap = document.querySelector('.shchrome .shdescwrap');

    return {
      size: parseFloat(getComputedStyle(el).fontSize),
      openHeight: wrap ? wrap.getBoundingClientRect().height : -1,
    };
  });
  check('descrierea porneste pliata', titleInfo.openHeight < 6, `${Math.round(titleInfo.openHeight)}px`);

  await page.evaluate(() => document.querySelector('.shchrome .shtitle').click());
  await wait(600);
  const descOpened = await page.evaluate(() => document.querySelector('.shchrome .shdescwrap').getBoundingClientRect().height);
  check('atingerea titlului desface descrierea', descOpened > 10, `${Math.round(descOpened)}px`);

  await page.evaluate(() => document.querySelector('.shchrome .shtitle').click());
  await wait(600);
  const closed = await page.evaluate(() => document.querySelector('.shchrome .shdescwrap').getBoundingClientRect().height);
  check('a doua atingere o plieaza la loc', closed < 6, `${Math.round(closed)}px`);

  /* ---------- 4. gesturile orizontale ---------- */
  const swipe = async (fromX, toX) => {
    await page.touchscreen.touchStart(fromX, 500);
    await page.touchscreen.touchMove((fromX + toX) / 2, 505);
    await page.touchscreen.touchMove(toX, 508);
    await page.touchscreen.touchEnd();
    // daca gestul a scos pagina din aplicatie, testul ar trece degeaba
    if ((await page.evaluate(() => location.href)) === 'about:blank') {
      throw new Error('pagina a navigat in istoric — gestul a fost interceptat de browser');
    }
    await wait(900);
  };

  /* Pornim de la 150px, nu de la margine: Chrome trateaza un swipe pornit de
     langa marginea stanga ca navigare in istoric si pleaca de pe pagina — ceea
     ce ar trece testul din motivul gresit. Pe Android, marginea e la fel de
     ocupata, de gestul de sistem „inapoi". */
  await swipe(150, 340); // stanga -> dreapta
  const leftAfter = await page.evaluate(() => !!document.querySelector('.shchrome'));
  check('swipe stanga->dreapta iese din shorts (inapoi)', !leftAfter);

  // ne intoarcem si incercam celalalt sens
  await page.evaluate(() => document.querySelectorAll('.bnav .nav')[0]?.click());
  await wait(900);
  const reEntered = await enterShorts();
  await wait(1800);

  if (await page.evaluate(() => !!document.querySelector('.shchrome'))) {
    await swipe(340, 150); // dreapta -> stanga
    const rightAfter = await page.evaluate(() => ({
      gone: !document.querySelector('.shchrome'),
      body: document.body.textContent.slice(0, 400),
    }));
    check('swipe dreapta->stanga deschide detaliul', rightAfter.gone);
  } else {
    const where = await page.evaluate(() => ({
      chrome: !!document.querySelector('.shchrome'),
      shorts: !!document.querySelector('.shorts'),
      h1: document.querySelector('.h1')?.textContent ?? '',
      h2: document.querySelector('.h2')?.textContent ?? '',
      body: document.body.innerText.replace(/\s+/g, ' ').slice(0, 200),
      chips: [...document.querySelectorAll('button.chip')].slice(0, 6).map((b) => b.textContent.trim()),
    }));
    check('am reintrat in shorts pentru al doilea gest', false, `click=${reEntered} ${JSON.stringify(where)}`);
  }

  if (errors.length) {
    console.log('\nErori de pagina:');
    errors.forEach((e) => console.log('  ' + e));
    failures += errors.length;
  }

  console.log(`\n${failures === 0 ? 'TOATE OK' : failures + ' esecuri'}`);
  await browser.close();
  process.exit(failures === 0 ? 0 : 1);
})();
