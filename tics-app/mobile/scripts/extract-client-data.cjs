/**
 * Extrage DATELE si HELPERELE VIZUALE de client din tics-app/client-app.html,
 * VERBATIM, intr-un modul TS.
 *
 * De ce verbatim: portul precedent a inlocuit scenele SVG procedurale ale
 * prototipului (SCENES / bgv / poster / galFor) cu gradiente + emoji, ceea ce
 * a schimbat complet aspectul cardurilor. Datele si generatoarele de imagine
 * trebuie sa fie BIT-IDENTICE cu prototipul.
 *
 * Se extrag liniile dintre `const VEN={` si `const S={},INIT={};`, MAI PUTIN
 * helperele cuplate la DOM (lightbox, toast, timere) — acelea se porteaza ca
 * si componente React, nu ca date.
 *
 * Rulare:  node scripts/extract-client-data.cjs
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const SRC = path.resolve(ROOT, '../client-app.html');
const OUT = path.resolve(ROOT, 'src/mock/prototype.ts');

const html = fs.readFileSync(SRC, 'utf8');
const lines = html.split('\n');

const start = lines.findIndex((l) => /^\s*const VEN=\{/.test(l));
const end = lines.findIndex((l) => /^\s*const S=\{\},INIT=\{\};/.test(l));
if (start < 0 || end < 0) throw new Error('Nu gasesc granitele blocului de date.');

const block = lines.slice(start, end);

/* Helpere cuplate la DOM sau la runtime-ul prototipului — nu intra in modul.
   Se recunosc dupa numele declarat pe linia respectiva. */
const DOM_COUPLED = [
  /^\s*const toast=/,
  /^\s*const lbEl=/,
  /^\s*function renderLB\(/,
  /^\s*function openLB\(/,
  /^\s*function closeLB\(/,
  /^\s*function stepLB\(/,
  /^\s*function clearTimers\(/,
  /^\s*function tick\(/,
  /^\s*\/\/ ===== gallery lightbox =====/,
  /^\s*\/\/ ===== timers/,
];

/* Declaratiile excluse pot fi multi-linie (renderLB are un template literal pe
   3 randuri). Sarim de la linia care declanseaza pana cand acoladele si
   backtick-urile acumulate revin in echilibru. */
const balance = (s) => {
  let braces = 0;
  let ticks = 0;
  let inTick = false;
  for (const ch of s) {
    if (ch === '`') {
      inTick = !inTick;
      ticks++;
    } else if (!inTick) {
      if (ch === '{') braces++;
      else if (ch === '}') braces--;
    }
  }
  return { braces, balanced: braces === 0 && ticks % 2 === 0 };
};

const kept = [];
for (let i = 0; i < block.length; i++) {
  const line = block[i];
  if (!DOM_COUPLED.some((re) => re.test(line))) {
    kept.push(line);
    continue;
  }
  // linie exclusa: consumam si continuarile ei
  let acc = line;
  while (!balance(acc).balanced && i + 1 < block.length) {
    i++;
    acc += '\n' + block[i];
  }
}

/* Exportam DOAR declaratiile de nivel superior. In prototip acelea sunt
   indentate cu exact 2 spatii (corpul IIFE-ului principal); orice indentare mai
   mare inseamna o declaratie dintr-un bloc interior, unde `export` ar fi
   ilegal si ar rupe build-ul.
   `const A=..,B=..` primeste un singur `export` — valid pentru declaratii
   multiple pe aceeasi linie. */
const exported = kept.map((l) => l.replace(/^ {2}(const|let|function)\s/, '  export $1 '));

const header = `/* =========================================================
   CLIENT — DATE SI HELPERE VIZUALE, PORTATE VERBATIM din
   tics-app/client-app.html.

   FISIER GENERAT. Nu edita la mana:
       node scripts/extract-client-data.cjs

   Contine, identic cu prototipul:
     - datasets: VEN, ART, ARTX, EV, TICS, FEST, STAY, ADDONS, EXPDAYS,
       ATTENDED, CATPOOLS, PREFGROUPS, REWARDS, AFF, CAL_*, EVREVIEWS, ...
     - iconografia \`I\` (SVG inline)
     - SCENES: cele 9 "fotografii" SVG procedurale + sceneURI / bgv / galFor /
       poster / scByCat  <- generatoarele de imagine ale cardurilor.
       Portul precedent le-a inlocuit cu gradiente + emoji; de aici venea
       diferenta mare de aspect.

   Nu s-au portat helperele cuplate la DOM (lightbox, toast, timere) — acelea
   devin componente React.
   ========================================================= */
// @ts-nocheck — dump verbatim din prototip; tipurile se adauga la consum, nu aici.
/* eslint-disable */

`;

fs.writeFileSync(OUT, header + exported.join('\n').trim() + '\n', 'utf8');

const out = fs.readFileSync(OUT, 'utf8');
const checks = [
  ['are SCENES', /export const SCENES=/.test(out)],
  ['are bgv', /export const bgv=/.test(out)],
  ['are poster', /export const poster=/.test(out)],
  ['are galFor', /export const galFor=/.test(out)],
  ['are I (iconite)', /export const I=/.test(out)],
  ['are EV', /export const EV=/.test(out)],
  ['are CATPOOLS', /export const CATPOOLS=/.test(out)],
  ['fara toast DOM', !/const toast=m=>\{toastEl/.test(out)],
  ['fara lbEl', !/const lbEl=/.test(out)],
];

console.log('scris:', OUT);
console.log('  linii:', out.split('\n').length, '| KB:', (out.length / 1024).toFixed(1));
let ok = true;
checks.forEach(([n, p]) => {
  if (!p) ok = false;
  console.log(`  ${p ? 'OK  ' : 'FAIL'} ${n}`);
});
process.exit(ok ? 0 : 1);
