# Prompt de start — Implementare Shorts (autonom, peste noapte)

> Acest document e prompt-ul pe care îl dai sesiunii de dezvoltare. Prima secțiune e pentru **tine (owner)** — cum pornești sesiunea ca să lucreze fără să te prompteze. Restul e pentru **sesiune**.

---

## ⚙️ PENTRU TINE (owner) — cum pornești rularea autonomă peste noapte

Ca să nu fii promptuit la fiecare comandă, ai două opțiuni:

**Opțiunea A (recomandată, cu garduri) — allowlist deja pregătit.**
Am adăugat în repo `/.claude/settings.json` cu:
- `defaultMode: acceptEdits` (nu te mai întreabă la editări de fișiere);
- un **allowlist** pentru comenzile de dev (git, composer, php artisan, npm/pnpm/yarn, node, utilitare shell);
- un **denylist** de siguranță (`migrate:fresh`/`db:wipe`, `push --force`, `rm -rf /`, citirea/editarea `.env`).
Deschide sesiunea în acest repo și pornește — cât timp comenzile sunt în allowlist, nu te întreabă. Dacă lovește o comandă neacoperită, s-ar putea opri până dimineața → pentru zero opriri vezi Opțiunea B.

**Opțiunea B (zero prompturi garantat) — bypass.**
Pornește CLI-ul cu:
```
claude --dangerously-skip-permissions
```
(sau, în extensia VS Code, alege modul de permisiuni „bypass"). Nu mai apare niciun prompt. Compromis: nu mai există garduri de permisiuni — de aceea prompt-ul de mai jos conține **garduri de comportament** stricte (DB de dev, fără force-push, fără deploy).

**Recomandare:** rulează pe branch-ul dedicat, pe o **bază de date locală/dev** (nu producție), cu Opțiunea A. Dimineața citești `docs/plans/PROGRESS.md` + `DECISIONS.md` și diff-ul commit-urilor.

---

## 🤖 PENTRU SESIUNE — mandat & mod de lucru

Ești o sesiune de dezvoltare cu acces complet la repo-ul **EPAS/Tixello** (Laravel 12 + Filament 4.1) și la aplicația mobilă. Implementezi funcționalitatea **Shorts** conform specificației complete din **`docs/plans/shorts.md`** (Partea A fundație + B 10 îmbogățiri + C Bunny Stream + D 12 îmbogățiri). Planurile conexe: `docs/plans/social-video-ingestion.md`, `docs/plans/friends-social.md`.

### Mandat de autonomie (CITEȘTE CU ATENȚIE)
Lucrezi **singur, toată noaptea, fără supraveghere**. Owner-ul NU e la calculator și NU poate aproba nimic.
- **NU cere aprobare** și **NU pune întrebări** care așteaptă răspuns. Nu te opri cu „să continui?", „e ok?", „confirmi?".
- Când ai o alegere de făcut, **ia decizia rezonabilă**, notează-o în `DECISIONS.md` (ce + de ce + alternative), și **continuă**.
- Dacă lovești un blocaj real (ceva ambiguu sau ireversibil), **nu te opri să întrebi**: alege varianta cea mai sigură și reversibilă, notează asumpția în `DECISIONS.md`, marchează un `TODO(owner)` în cod/PROGRESS și **treci la următoarea sarcină independentă**. Revii la ea doar dacă se deblochează.
- **Nu te opri din lucru** până nu atingi „Definition of Done" pentru valul curent sau până nu epuizezi lista de sarcini. Lucrează continuu, pas cu pas.

### Garduri de siguranță (absolute — nu le încălca)
- **Bază de date**: rulează migrațiile DOAR pe o DB **locală/dev**. Verifică `.env` (fără să-l editezi) că nu e producție; dacă nu ești sigur, folosește o DB SQLite de dev separată. **Niciodată** `migrate:fresh`/`migrate:reset`/`db:wipe` pe date reale.
- **Git**: commit + push pe branch-ul curent de lucru după fiecare pas. **Niciodată** `push --force`. Nu atinge `main`/`core`. Nu deschide PR fără să ți se ceară.
- **Fără deploy**, fără atins secrete/`.env`, fără chei reale (Bunny/Stripe) — folosește placeholdere din config + `.env.example`.
- Migrațiile trebuie să fie **aditive** (tabele noi / coloane nullable) — niciodată distructive pe coloane existente.
- Nu șterge fișiere pe care nu le-ai creat tu fără a nota motivul.
- Respectă convențiile EPAS: **Eloquent, nu SQL brut**; fonturile/asset-urile locale, nu CDN; stilul de cod existent (rulează `./vendor/bin/pint` dacă există).

### Bucla de lucru (repetă pentru fiecare pas)
1. **Plan scurt** al pasului (în `PROGRESS.md`).
2. **Implementează** (migrație → model → serviciu/job → controller/rute → resursă Filament → wiring, conform `shorts.md`).
3. **Migrează** pe DB de dev + verifică că pornește.
4. **Test**: scrie/rulează un test minimal (`php artisan test --filter=...`) pentru pasul respectiv.
5. **Format**: `./vendor/bin/pint` (dacă există).
6. **Commit** cu mesaj descriptiv + **push**.
7. **Actualizează** `PROGRESS.md` (bifat) și `DECISIONS.md` (dacă ai decis ceva).
8. Treci la pasul următor. **Fără pauze de confirmare.**

### Definition of Done (per pas)
- fișierele create/mod conform specului; migrația rulează curat pe dev; testul minimal trece; `pint` aplicat; commit-uit + push-uit; `PROGRESS.md` actualizat.

### Ordinea de implementare (urmează `shorts.md`)
Mergi în ordinea din **`shorts.md` §16** (fundație), apoi valurile din **§B12** și **§D13**. Concret, pe fază — fiecare complet (DoD) înainte de următoarea:

1. **Fundație (A)**: migrații `shorts` + `short_events` + `short_likes/saves`; model `Short` (polimorf, fără tenant scope global); resursa Filament centrală „Core" (upload native prin `VideoProvider`); API feed `tenant-client/shorts` (cursor) + telemetrie. → **abstracția `VideoProvider` + `BunnyStreamProvider` (Partea C)** cu chei placeholder din config.
2. **Redare mobil (A)**: componenta de feed vertical (HLS + autoplay + preload + overlay + like/save/share + telemetrie). Dacă nu ai încă app-ul mobil în acest repo, livrează componentele/serviciile client ca modul separat + documentează integrarea.
3. **Val 1 (creștere ieftină)**: D1 (share+referral+landing), D2 (remind/drop), D9 (UX player: blurhash/prefetch/data-saver), D11 (gamification), D10 (accesibilitate).
4. **Shoppable (B1)** + atribuire (`orders.source_short_id`).
5. **Following + ranker For You (B2)** + geamăn tenant Filament.
6. **Ingestie externă** (`social-video-ingestion.md`): YouTube → TikTok → Meta; **seed YouTube (B4)**.
7. **Val 2 (măsurare & scalare)**: D4 (retenție/atribuire feed/trending), D6 (telemetrie: partiționare/rollup/prune/sampling), D5 (ranker evolution), D12 (notificări comportamentale via `AutomationWorkflow`).
8. **Auto-gen din media (B3)** + **captions (B6)** + **analytics organizator (B5)**.
9. **Collections (B7)** + **Stories (B8)**.
10. **Val 3 (bani & compliance)**: D3 (promovate), D7 (drepturi/licențiere), D8 (guardrails cost Bunny), **UGC (B9)** + moderare + **A/B cover (B10)**.

> Dependențe pe care le tratezi ca stub-uri dacă lipsesc (notează `TODO(owner)`): **layer de push** (FCM/APNs) pt D2/D12; **puntea de identitate `MarketplaceCustomer↔Customer`** din `friends-social.md` pt D11/D12; **provider video real** (chei Bunny) — pune abstracția + config, marchează cheile ca placeholder.

### Jurnale de ținut (creează-le la început)
- **`docs/plans/PROGRESS.md`** — checklist pe faze/pași, bifat pe măsură ce avansezi; sus, un „rezumat pentru owner" (ce e gata, ce a rămas, ce blocaje).
- **`DECISIONS.md`** (rădăcină) — fiecare decizie luată singur: context, alegere, alternative, impact.
- La blocaje: `TODO(owner): ...` în cod + o linie în PROGRESS.

### La final (dimineață)
`PROGRESS.md` trebuie să reflecte exact starea: faze complete (verzi), în lucru, blocate (cu motiv). Toate commit-urile push-uite pe branch. Un rezumat scurt sus în PROGRESS: „am făcut X, urmează Y, blocaje Z".

**Începe acum cu Faza 1. Lucrează continuu, pas cu pas, fără să ceri aprobare.**
