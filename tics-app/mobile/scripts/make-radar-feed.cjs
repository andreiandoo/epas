/* Genereaza acelasi JSON ca public/tics-app/radar.php, ca sa putem testa
   aplicatia local inainte ca proxy-ul sa fie pe server. */
const fs = require('fs');
const BS = String.fromCharCode(92); // backslash

function extract(html, key) {
  const needle = `"${key}":[`;
  const at = html.indexOf(needle);
  if (at < 0) return null;
  const start = at + needle.length - 1;
  let depth = 0;
  let inStr = false;
  let esc = false;
  for (let i = start; i < html.length; i++) {
    const ch = html[i];
    if (inStr) {
      if (esc) esc = false;
      else if (ch === BS) esc = true;
      else if (ch === '"') inStr = false;
      continue;
    }
    if (ch === '"') {
      inStr = true;
      continue;
    }
    if (ch === '[') depth++;
    else if (ch === ']') {
      depth--;
      if (depth === 0) return JSON.parse(html.slice(start, i + 1));
    }
  }
  return null;
}

(async () => {
  const r = await fetch('https://app.tics.ro/events', {
    headers: { Accept: 'text/html', 'User-Agent': 'TixelloApp/1.0' },
  });
  const html = await r.text();
  const ev = extract(html, 'events');
  const cats = extract(html, 'subnav');
  const cities = extract(html, 'cities');

  const payload = {
    fetched: new Date().toISOString(),
    events: ev.map((e) => ({
      id: e.id, cat: e.cat, label: e.catLabel, color: e.color, t: e.title,
      city: e.city, ven: e.venue, genre: e.genre, plat: e.platform,
      price: e.priceNum ?? null, save: e.save | 0, sold: !!e.soldout,
      days: e.days | 0, date: e.dateLabel, wknd: !!e.weekend, img: e.poster ?? null,
    })),
    cats: cats.map((c) => ({ key: c.k || c.key, label: c.l || c.label, color: c.c || c.color })),
    cities: cities.map((c) => ({ name: c.name, n: c.n | 0 })),
  };

  fs.mkdirSync('public', { recursive: true });
  fs.writeFileSync('public/radar.json', JSON.stringify(payload));
  console.log('events', payload.events.length, 'cats', payload.cats.length, 'cities', payload.cities.length, 'bytes', JSON.stringify(payload).length);
  const byCat = {};
  payload.events.forEach((e) => (byCat[e.cat] = (byCat[e.cat] || 0) + 1));
  console.log('teatru:', byCat.teatru, 'concerte:', byCat.concerte, 'cu pret:', payload.events.filter((e) => e.price).length);
})();
