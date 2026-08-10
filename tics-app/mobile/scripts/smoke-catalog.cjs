/* =========================================================
   Smoke test pentru fisele reale de eveniment / artist / locatie.

   Verifica exact ce s-a schimbat: un id care NU exista in datasetul
   prototipului nu mai duce la „nu e disponibil", ci la ecranul real, cu datele
   din catalog — si ca sectiunile fara corespondent real (recenzii de eveniment,
   melodii, clipuri) chiar dispar in loc sa arate continut demo.

   Catalogul e servit local, deci testul nu depinde de retea sau de baza.

   Rulare:
     npx vite build && npx vite preview --port 4191
     node scripts/smoke-catalog.cjs
   ========================================================= */
const CHROME = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const APP = process.env.APP_URL || 'http://localhost:4191/';

const wait = (ms) => new Promise((r) => setTimeout(r, ms));
let failures = 0;
const check = (name, ok, extra = '') => {
  console.log(`${ok ? 'OK  ' : 'FAIL'} ${name}${extra ? ' — ' + extra : ''}`);
  if (!ok) failures++;
};

const EVENT = {
  id: 9001,
  slug: 'concert-real',
  title: 'Concert Real 2026',
  subtitle: null,
  date: '2026-09-12',
  day: '12',
  month: 'sep',
  date_label: '12 sep',
  time: '20:00',
  city: 'Cluj-Napoca',
  venue: { id: 7001, slug: 'sala-reala', name: 'Sala Reală', city: 'Cluj-Napoca', address: 'Str. Test 10' },
  poster: null,
  price_from: 123,
  is_cancelled: false,
  is_postponed: false,
  hero: null,
  gallery: [],
  category: 'Concerte',
  organizer: 'Organizator SRL',
  short_description: null,
  description: 'Descrierea reala a evenimentului, adusa din catalog.',
  terms: null,
  pricing: { source: 'marketplace', mode: 'included', rate: 7 },
  ticket_types: [
    {
      id: 1,
      name: 'Acces general',
      description: 'In picioare',
      price: 123,
      full_price: 150,
      perks: ['Acces zona generala', 'Garderoba inclusa'],
      available: true,
    },
    { id: 2, name: 'Categoria I', description: 'Loc pe scaun', price: 199, full_price: null, perks: [], available: true },
  ],
  artists: [{ id: 6446, slug: 'sukar-nation', name: 'Sukar Nation', role: 'Live', image: null }],
};

const ARTIST = {
  id: 6446,
  slug: 'sukar-nation',
  name: 'Sukar Nation',
  role: 'Live',
  bio: 'Biografia reala a artistului.',
  city: 'București',
  country: 'RO',
  image: null,
  cover: null,
  links: { youtube: 'https://youtube.com/@sukarnation' },
  followers: { youtube: 120000 },
  events: [{ ...EVENT, venue: EVENT.venue }],
};

const VENUE = {
  id: 7001,
  slug: 'sala-reala',
  name: 'Sala Reală',
  city: 'Cluj-Napoca',
  address: 'Str. Test 10',
  country: 'RO',
  capacity: 1200,
  description: 'Descrierea reala a locatiei.',
  image: null,
  portrait: null,
  gallery: [],
  lat: null,
  lng: null,
  rating: 4.6,
  review_count: 88,
  reviews: [],
  events: [{ ...EVENT, venue: EVENT.venue }],
};

(async () => {
  const puppeteer = (await import('puppeteer-core')).default;
  const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });

  const errors = [];
  page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message));

  const json = (req, data) =>
    req.respond({
      status: 200,
      contentType: 'application/json',
      headers: { 'Access-Control-Allow-Origin': '*' },
      body: JSON.stringify({ success: true, data }),
    });

  /** Ce fel de short serveste feed-ul la urmatoarea intrare in ecran. */
  let feedMode = 'event';

  const shortFor = (kind) => ({
    id: 1,
    source: 'upload',
    feed: 'for_you',
    playback: { hls_url: null, poster_url: null, blurhash: null },
    embed_html: null,
    source_url: null,
    duration: null,
    aspect: '9:16',
    title: 'Test',
    caption: null,
    description: null,
    hashtags: [],
    language: null,
    music_credit: null,
    owner:
      kind === 'artist'
        ? { type: 'artist', id: 6446, slug: 'sukar-nation', name: 'Sukar Nation', label: 'Artist', details: [] }
        : kind === 'venue'
          ? { type: 'venue', id: 7001, slug: 'sala-reala', name: 'Sala Reală', label: 'Locație', details: [] }
          : { type: 'event', id: 9001, slug: 'concert-real', name: 'Concert Real 2026', label: 'Eveniment', details: [] },
    event: kind === 'event' ? { id: 9001, slug: 'concert-real', title: 'Concert Real 2026', date: null } : null,
    cta: {
      type: kind === 'artist' ? 'open_artist' : kind === 'venue' ? 'open_venue' : 'buy_tickets',
      label: 'Deschide',
      url: null,
      ticket_type_id: null,
      promo_code: null,
      on_sale_at: null,
      pending: false,
    },
    content_flags: [],
    stats: { likes: 0, views: 0, shares: 0 },
    viewer: { liked: false, saved: false, reminded: false },
  });

  await page.setRequestInterception(true);
  page.on('request', (req) => {
    const url = req.url();

    if (url.includes('/tenant-client/shorts') && !url.includes('/events')) {
      return json(req, { feed: 'for_you', items: [shortFor(feedMode)], playback: null, next_cursor: null });
    }

    // lista (fara id) inaintea fisei, altfel „events?" ar cadea pe fisa
    if (/\/catalog\/events(\?|$)/.test(url)) return json(req, [EVENT]);
    if (url.includes('/catalog/events/')) return json(req, EVENT);
    if (url.includes('/catalog/artists/')) return json(req, ARTIST);
    if (url.includes('/catalog/venues/')) return json(req, VENUE);

    if (/^https?:\/\/(?!localhost)/.test(url)) {
      return req.respond({
        status: 200,
        contentType: 'application/json',
        headers: { 'Access-Control-Allow-Origin': '*' },
        body: '{"success":true,"data":{}}',
      });
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

  /* Drumul real: feed de shorts -> tap pe butonul principal. Nu se injecteaza
     navigare de test, ca sa nu ramana cod de test in aplicatie si ca sa se
     verifice chiar traseul pe care il face utilizatorul. */
  const enterShorts = () =>
    page.evaluate(() => {
      const el = [...document.querySelectorAll('button.chip')].find((b) => b.textContent.trim() === 'Pe val');
      el?.click();

      return !!el;
    });

  /* Fisele de detaliu n-au bara de jos, deci intoarcerea se face cu butonul de
     inapoi din bara de sus, exact ca la utilizator. */
  const goHome = async () => {
    for (let i = 0; i < 4; i++) {
      const onHome = await page.evaluate(() => !!document.querySelector('.bnav'));
      if (onHome) break;
      await page.evaluate(() => {
        const b = document.querySelector('.dbar .icon-btn, .shchrome .shtop button, .topbar .icon-btn');
        b?.click();
      });
      await wait(600);
    }
    await page.evaluate(() => document.querySelectorAll('.bnav .nav')[0]?.click());
    await wait(700);
  };

  const openScreen = async (kind) => {
    feedMode = kind;
    await goHome();
    await enterShorts();
    await page.waitForFunction(() => !!document.querySelector('.shchrome .shcta'), { timeout: 15000 });
    await page.evaluate(() => document.querySelector('.shchrome .shcta').click());
    await wait(1800);
  };

  const bodyText = () => page.evaluate(() => document.body.textContent.replace(/\s+/g, ' '));

  /* `textContent` include si nodurile cu display:none, deci nu poate raspunde
     la „sectiunea e ascunsa?". Urcam de la titlu pana la body si verificam
     fiecare parinte — asa prindem si `hidden` pus pe containerul de deasupra. */
  const sectionVisible = (label) =>
    page.evaluate((t) => {
      const el = [...document.querySelectorAll('.h2')].find((e) => e.textContent.trim() === t);
      if (!el) return false;

      for (let n = el; n && n !== document.body; n = n.parentElement) {
        if (getComputedStyle(n).display === 'none') return false;
      }

      return true;
    }, label);

  /* ---------- eveniment ---------- */
  await openScreen('event');
  let txt = await bodyText();
  check('evenimentul real se deschide', txt.includes('Concert Real 2026'), txt.slice(0, 90));
  check('descrierea reala apare', txt.includes('Descrierea reala a evenimentului'));
  check('sala reala apare', txt.includes('Sala Reală'));
  check('pretul minim apare in dock', txt.includes('123'));
  check('recenziile demo NU apar pe eveniment real', !(await sectionVisible('Recenzii')));

  /* Lista de bilete: descriere, beneficii, reducere si comisionul corect. */
  await page.evaluate(() => {
    const b = [...document.querySelectorAll('button.cta')].find((x) => /Alege bilete/.test(x.textContent));
    b?.click();
  });
  await wait(1400);
  const tickets = await page.evaluate(() => ({
    cards: document.querySelectorAll('.ttcard').length,
    perks: [...document.querySelectorAll('.ttperks li')].map((e) => e.textContent.trim()),
    body: document.body.textContent.replace(/\s+/g, ' '),
  }));
  check('cardurile de bilet folosesc marcajul nou', tickets.cards === 2, `${tickets.cards} carduri`);
  check('beneficiile apar ca lista', tickets.perks.length === 2, tickets.perks.join(' | '));
  check('reducerea se vede ca procent', /-18%/.test(tickets.body), '150 -> 123 lei');
  check('preturile nu mai sunt 0', /123/.test(tickets.body) && /199/.test(tickets.body));
  check('galeria goala nu are antet', !(await sectionVisible('Galerie')));

  /* ---------- artist ---------- */
  await openScreen('artist');
  txt = await bodyText();
  check('artistul real se deschide', txt.includes('Sukar Nation'), txt.slice(0, 90));
  check('biografia reala apare', txt.includes('Biografia reala a artistului'));
  check('melodiile demo NU apar', !(await sectionVisible('Top 10 melodii')));
  check('evenimentul artistului apare', txt.includes('Concert Real 2026'));

  /* ---------- locatie ---------- */
  await openScreen('venue');
  txt = await bodyText();
  check('locatia reala se deschide', txt.includes('Sala Reală'), txt.slice(0, 90));
  check('descrierea locatiei apare', txt.includes('Descrierea reala a locatiei'));
  check('capacitatea reala apare', txt.includes('1.200'), txt.slice(txt.indexOf('Capacitate') - 12, txt.indexOf('Capacitate') + 10));
  check('nota reala apare, nu 4.8 fix', txt.includes('4.6') && !txt.includes('4.8'));

  if (errors.length) {
    console.log('\nErori de pagina:');
    errors.forEach((e) => console.log('  ' + e));
    failures += errors.length;
  }

  console.log(`\n${failures === 0 ? 'TOATE OK' : failures + ' esecuri'}`);
  await browser.close();
  process.exit(failures === 0 ? 0 : 1);
})();
