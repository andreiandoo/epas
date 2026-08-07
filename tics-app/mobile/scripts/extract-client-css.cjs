/**
 * Extrage CSS-ul de CLIENT din tics-app/client-app.html, VERBATIM.
 *
 * Doua transformari, ambele strict de integrare (nu de design):
 *   1. Scoate @font-face (fontul Outfit vine din fonts.css, acelasi base64)
 *      si carcasa de "studio" (rama de telefon), pentru ca in aplicatia reala
 *      ecranul telefonului ESTE fereastra.
 *   2. Scopeaza tot sub `.app-client`. Necesar: prototipul de client si cel de
 *      organizator folosesc acelasi nume pentru clase diferite (.screen, .card,
 *      .row, .avatar, ...) si isi redefinesc tokenurile in :root. Fara scoping,
 *      ultimul stylesheet incarcat l-ar strica pe celalalt.
 *
 * Rulare:  node scripts/extract-client-css.cjs
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const SRC = path.resolve(ROOT, '../client-app.html');
const OUT = path.resolve(ROOT, 'src/design/client.css');
const SCOPE = '.app-client';

/* ---------- 1. extragere ---------- */
const raw = fs.readFileSync(SRC, 'utf8').match(/<style>([\s\S]*?)<\/style>/)[1];
const lines = raw.split('\n').filter((l) => !/^@font-face/.test(l.trim()));

// Linii (1-based, dupa filtrarea @font-face) care tin de carcasa de studiu.
const DROP = new Set([20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 93]);
const css = lines.filter((_, i) => !DROP.has(i + 1)).join('\n');

/* ---------- 2. scoping ---------- */
/** Prefixeaza o lista de selectori separati prin virgula. */
function scopeSelector(sel) {
  return sel
    .split(',')
    .map((s) => {
      const t = s.trim();
      if (!t) return t;
      // :root tine tokenurile — devine chiar containerul, ca sa se meosteneasca.
      if (t === ':root') return SCOPE;
      // `*` si `html`/`body` raman relative la container.
      if (t === '*') return `${SCOPE} *`;
      if (t === 'html' || t === 'body') return SCOPE;
      // Selector care incepe cu pseudo-element/clasa globala (::-webkit-...)
      if (t.startsWith('::')) return `${SCOPE} ${t}`;
      return `${SCOPE} ${t}`;
    })
    .join(', ');
}

/**
 * Parcurge CSS-ul urmarind acoladele si rescrie doar preludiile de regula.
 * @keyframes / @font-face raman neatinse inauntru; @media se descompune.
 */
function scopeCss(input) {
  let out = '';
  let buf = '';
  let depth = 0;
  // stiva de tipuri de bloc: 'rule' | 'media' | 'keyframes'
  const stack = [];

  for (let i = 0; i < input.length; i++) {
    const ch = input[i];

    if (ch === '{') {
      const prelude = buf;
      const trimmed = prelude.trim();
      buf = '';

      const insideKeyframes = stack.includes('keyframes');

      if (trimmed.startsWith('@')) {
        const isKeyframes = /^@(-\w+-)?keyframes\b/.test(trimmed);
        const isFontFace = /^@font-face\b/.test(trimmed);
        stack.push(isKeyframes || isFontFace ? 'keyframes' : 'media');
        out += prelude + '{';
      } else if (insideKeyframes) {
        // 0%, 50%, from, to — nu se scopeaza
        stack.push('rule');
        out += prelude + '{';
      } else if (depth === 0 || stack[stack.length - 1] === 'media') {
        stack.push('rule');
        const lead = prelude.match(/^\s*/)[0];
        const comments = [];
        // pastram comentariile de dinaintea selectorului
        const cleaned = trimmed.replace(/\/\*[\s\S]*?\*\//g, (m) => {
          comments.push(m);
          return '';
        });
        out += lead + comments.join('') + (comments.length ? '\n' : '') + scopeSelector(cleaned) + '{';
      } else {
        stack.push('rule');
        out += prelude + '{';
      }
      depth++;
      continue;
    }

    if (ch === '}') {
      out += buf + '}';
      buf = '';
      stack.pop();
      depth--;
      continue;
    }

    buf += ch;
  }
  return out + buf;
}

const scoped = scopeCss(css);

/* ---------- 3. scriere ---------- */
const header = `/* =========================================================
   CLIENT — CSS PORTAT VERBATIM din tics-app/client-app.html.

   FISIER GENERAT. Nu edita la mana:
       node scripts/extract-client-css.cjs

   Fata de prototip s-au facut DOAR doua transformari de integrare:
     1. scos @font-face (fontul vine din fonts.css) si carcasa de "studio"
        (.device/.screenport/.island/.vp/.stage/.hint/.stagebar + regula body
        care centra macheta) — in aplicatia reala ecranul E fereastra;
     2. totul scopat sub \`${SCOPE}\`, pentru ca prototipul de client si cel de
        organizator dau acelasi nume unor clase diferite (.screen, .card, .row,
        .avatar, ...) si isi redefinesc tokenurile in :root. \`:root\` a devenit
        \`${SCOPE}\` — tokenurile se mostenesc la fel.

   REGULA DE AUR (§12.4): nu inventa UX si nu scrie CSS nou pentru ecranele de
   client. Daca un ecran are nevoie de un stil, el exista deja aici — cauta
   clasa in prototip.
   ========================================================= */

/* Radacina shell-ului de client: preia rolul lui .screenport/.vp din prototip
   (container relativ peste care .screen face position:absolute). */
${SCOPE} {
  position: relative;
  flex: 1;
  min-height: 0;
  overflow: hidden;
  background: var(--bg);
}

`;

const footer = `

/* Integrare cu telefonul real (nu design): prototipul rula intr-o rama fara
   notch software, aplicatia are safe-area. */
${SCOPE} .stickytop,
${SCOPE} .dbar {
  padding-top: env(safe-area-inset-top, 0px);
}
${SCOPE} .bnav {
  padding-bottom: calc(10px + env(safe-area-inset-bottom, 0px));
}
`;

fs.writeFileSync(OUT, header + scoped.trim() + footer, 'utf8');

/* ---------- 4. verificari ---------- */
const out = fs.readFileSync(OUT, 'utf8');
const checks = [
  ['fara base64 de font', !/data:font\/woff2/.test(out)],
  ['fara @font-face', !/^@font-face/m.test(out)],
  ['fara rama .device', !/\.device\s*\{/.test(out)],
  ['are .mcard scopat', out.includes(`${SCOPE} .mcard`)],
  ['are .bnav scopat', out.includes(`${SCOPE} .bnav`)],
  ['are .screen scopat', out.includes(`${SCOPE} .screen`)],
  ['tokenurile pe container', out.includes(`${SCOPE} {`)],
  ['fara :root ramas', !/:root\s*\{/.test(out)],
  ['keyframes neatinse', /@keyframes pop\{/.test(out) || /@keyframes pop \{/.test(out)],
  ['acolade echilibrate', (out.match(/\{/g) || []).length === (out.match(/\}/g) || []).length],
];

console.log('scris:', OUT);
console.log('  linii:', out.split('\n').length, '| KB:', (out.length / 1024).toFixed(1));
let ok = true;
checks.forEach(([name, pass]) => {
  if (!pass) ok = false;
  console.log(`  ${pass ? 'OK  ' : 'FAIL'} ${name}`);
});
process.exit(ok ? 0 : 1);
