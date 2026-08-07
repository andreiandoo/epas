/**
 * Transformare unica: scopeaza clasele de componenta din base.css sub `.app-org`.
 *
 * DE CE:
 * client.css (portat din prototipul de client) e deja scopat sub .app-client,
 * ceea ce impiedica clientul sa strice organizatorul. Dar invers nu era
 * adevarat: base.css declara global `.row`, `.card`, `.avatar` etc., iar
 * clientul redefineste ACELEASI nume cu alt inteles. Selectorul `.app-client
 * .row` castiga la specificitate, dar DOAR pentru proprietatile pe care le
 * declara — `background`, `border`, `border-radius`, `box-shadow` din `.row`-ul
 * organizatorului se scurgeau in randurile de bilet ale clientului si le dadeau
 * chenar. (Vizibil in comparatia ecranului Bilete.)
 *
 * Raman GLOBALE: reset-ul, html/body/#root, .app, :focus-visible, @keyframes
 * si @media de reduced-motion — nu sunt componente si nu se ciocnesc.
 *
 * Rulare (o singura data, rezultatul se comite):
 *     node scripts/scope-base-css.cjs
 */
const fs = require('fs');
const path = require('path');

const FILE = path.resolve(__dirname, '../src/design/base.css');
const SCOPE = '.app-org';

/** Selectori care raman globali. */
const GLOBAL = [/^\*$/, /^html$/, /^body$/, /^#root$/, /^\.app$/, /^:focus-visible$/, /^button$/];

const isGlobal = (sel) => GLOBAL.some((re) => re.test(sel.trim()));

function scopeSelector(sel) {
  return sel
    .split(',')
    .map((s) => {
      const t = s.trim();
      if (!t) return t;
      if (isGlobal(t)) return t;
      // `.app` e containerul comun; `.app .x` devine `.app-org .x`
      if (t.startsWith('.app ')) return `${SCOPE} ${t.slice(5)}`;
      return `${SCOPE} ${t}`;
    })
    .join(',\n');
}

function scopeCss(input) {
  let out = '';
  let buf = '';
  let depth = 0;
  const stack = [];

  for (const ch of input) {
    if (ch === '{') {
      const prelude = buf;
      const trimmed = prelude.trim();
      buf = '';
      const insideKeyframes = stack.includes('keyframes');

      if (trimmed.startsWith('@')) {
        const isKf = /^@(-\w+-)?keyframes\b/.test(trimmed) || /^@font-face\b/.test(trimmed);
        stack.push(isKf ? 'keyframes' : 'media');
        out += prelude + '{';
      } else if (insideKeyframes) {
        stack.push('rule');
        out += prelude + '{';
      } else if (depth === 0 || stack[stack.length - 1] === 'media') {
        stack.push('rule');
        const lead = prelude.match(/^\s*/)[0];
        const comments = [];
        const cleaned = trimmed.replace(/\/\*[\s\S]*?\*\//g, (m) => {
          comments.push(m);
          return '';
        });
        out += lead + comments.join('\n') + (comments.length ? '\n' : '') + scopeSelector(cleaned) + ' {';
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

const src = fs.readFileSync(FILE, 'utf8');
if (src.includes(`${SCOPE} .card`)) {
  console.log('base.css pare deja scopat — nu fac nimic.');
  process.exit(0);
}

const scoped = scopeCss(src);
const note = `/* NOTA DE ARHITECTURA
   Clasele de componenta de mai jos sunt scopate sub \`${SCOPE}\`.
   Motivul: prototipul de client si cel de organizator dau ACELASI nume unor
   clase diferite (.row, .card, .avatar, .h1, .pad, .badge, .tag, .seat, ...).
   Fara scoping in ambele sensuri, proprietatile nedeclarate de una se scurg
   din cealalta — de ex. randurile de bilet ale clientului primeau chenarul
   lui .row de la organizator.
   Shell-urile de organizator randeaza intr-un container cu clasa \`app-org\`;
   cel de client, in \`app-client\` (vezi src/design/client.css).
   Raman globale doar reset-ul, html/body/#root/.app si :focus-visible. */

`;

fs.writeFileSync(FILE, note + scoped.trim() + '\n', 'utf8');

const out = fs.readFileSync(FILE, 'utf8');
const checks = [
  ['card scopat', out.includes(`${SCOPE} .card`)],
  ['row scopat', out.includes(`${SCOPE} .row`)],
  ['tabbar scopat', out.includes(`${SCOPE} .tabbar`)],
  ['.app ramas global', /\n\.app \{/.test(out) || /^\.app \{/m.test(out)],
  ['reset ramas global', /^\* \{/m.test(out)],
  ['acolade echilibrate', (out.match(/\{/g) || []).length === (out.match(/\}/g) || []).length],
];
let ok = true;
checks.forEach(([n, p]) => {
  if (!p) ok = false;
  console.log(`  ${p ? 'OK  ' : 'FAIL'} ${n}`);
});
console.log(`scris: ${FILE} (${(out.length / 1024).toFixed(1)} KB)`);
process.exit(ok ? 0 : 1);
