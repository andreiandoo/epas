# Grafice necesare pentru Play Console

Toate obligatorii înainte de a putea submit review. Fără ele, "Main store listing" rămâne incomplet și release-ul nu poate fi trimis.

---

## 1. App icon (OBLIGATORIU)

- **Format**: PNG cu transparență (32-bit RGBA)
- **Dimensiune**: exact **512 × 512 px**
- **Max**: 1 MB
- **Design tips**:
  - Fără colțuri rotunde — Play le adaugă automat
  - Rămâne clar la 48×48 (dimensiunea afișată în app drawer)
  - Contrast bun pe fundal alb ȘI negru (system light/dark)
  - Fără text (Play recomandă asta — greu de citit la dimensiuni mici)

**Recomandare**: pornește de la iconul curent din `ambilet-app2/assets/icon.png` (probabil deja 1024×1024). Doar redimensionează la 512×512.

Comandă rapidă cu ImageMagick (dacă îl ai):
```bash
magick i:/WORK/eventpilot/ambilet-app2/assets/icon.png -resize 512x512 i:/WORK/eventpilot/ambilet-app2/store-config/graphics/icon-512.png
```

Sau online: https://icon.kitchen (paste iconul + descarci variante).

---

## 2. Feature Graphic (OBLIGATORIU)

- **Format**: JPG sau PNG **fără canal alpha**
- **Dimensiune**: exact **1024 × 500 px**
- **Max**: 15 MB
- **Design**:
  - Este banner-ul mare afișat sus în pagina store a app-ului
  - Include logo/icon în stânga + tagline scurt centrat/dreapta
  - NU pune text important în ultimii 20% margini stânga/dreapta (Play le crop-uiește pe unele device-uri)
  - Testează pe fundal deschis ȘI închis (Play afișează in both themes)

**Idee text tagline**:
- „Vânzare & scanare bilete pentru organizatori" (RO)
- „Vinde, scanează, raportează — la eveniment" (RO alternativ)

Design rapid: **Canva** → template "Google Play feature graphic" → paste logo + tagline → export PNG.

---

## 3. Phone Screenshots (OBLIGATORIU — minim 2, maxim 8)

- **Format**: JPG sau PNG 24-bit (fără alpha)
- **Aspect ratio**: 16:9 sau 9:16 (portrait recomandat pentru phone app)
- **Dimensiune**: min 320 × 480 px, max 3840 × 3840 px, longest side max 3840
- **Recomandat**: 1080 × 1920 px (fullHD portrait)

### Screenshot-uri sugerate (ordonate pentru marketing impact)

1. **Panou de control** — dashboard cu KPI-uri live + grafic ritm vânzări
2. **Scanare QR** — camera cu chenar activ + ticket confirmat verde
3. **Vânzare POS** — coș bilete + modal plată
4. **Rapoarte** — tab Rapoarte cu grafice + tabel export
5. **Seating map** (dacă ai eveniment cu locuri) — hartă interactivă
6. **Setări** — pagina de setări + link către Manual

### Cum să le faci

**Opțiunea A — de pe telefon real** (recomandat pentru autentic):
1. Instalează versiunea sideload actuală (`ambilet.ro/android-nou-2`)
2. Log in cu contul de test
3. Navighează la fiecare ecran vrei să capturezi
4. Volume Down + Power = screenshot Android (salvat în `DCIM/Screenshots/`)
5. Transferă pe PC prin USB / Google Photos / Drive

**Opțiunea B — din emulator Android Studio** (mai controlabil dar necesită setup):
1. Deschide Android Studio → AVD Manager
2. Create Virtual Device: Pixel 7 / Android 14 (720p sau 1080p portrait)
3. Install AAB debug prin `adb install app.apk`
4. Ecran clean, fără notch fals, ori icon-uri de sistem confuze
5. Screenshot din AVD toolbar (icon cameră)

### Marketing tips pentru screenshot-uri

- **Adaugă text overlay** pe fiecare screenshot cu 3-6 cuvinte descriere: "Panou live", "Scanare < 1 sec", "Rapoarte cu export CSV"
- **Fundal colorat** contrastant în jurul screenshot-ului real (nu screenshot pur)
- Vezi cum arată top-uri Play Store în categoria Business pentru inspirație

---

## 4. Screenshots tabletă (OPȚIONAL, dar recomandat)

Dacă app-ul e folosit și pe tabletă (poate cazul viitor pentru staff cu tablete la eveniment):

- **7-inch tablet**: min 320×480, max 3840×3840
- **10-inch tablet**: same specs

Poți face din Android Studio AVD Manager → device Nexus 7 sau Nexus 10.

Play Store dă vizibilitate MULT mai bună app-urilor cu screenshot-uri tablet dacă utilizatorul caută de pe tabletă.

---

## Folder structură recomandată

Creează folder `store-config/graphics/` și pune totul acolo:

```
store-config/
├── graphics/
│   ├── icon-512.png              (obligatoriu)
│   ├── feature-graphic-1024x500.png  (obligatoriu)
│   ├── phone-screenshots/
│   │   ├── 01-panou.png
│   │   ├── 02-scanare.png
│   │   ├── 03-vanzare.png
│   │   ├── 04-rapoarte.png
│   │   └── ...
│   └── tablet-screenshots/       (optional)
│       ├── 01-panou.png
│       └── ...
```

Le upload-ezi ulterior din Play Console → Main store listing (drag & drop).

---

## Ce fac EU cât timp tu pregătești graficele

- Când ești pregătit, îmi spui unde sunt fișierele + facem AAB-ul
- Îți verific structura + optimizează Play Store listing textele dacă e cazul
- Rulez `bundleStoreRelease` să generez AAB-ul (~40 MB) — verific integrity cu `bundletool validate`
- Îți dau AAB-ul de upload

---

## Alternativă rapidă dacă vrei să publici FĂRĂ să faci grafice acum

Play Console **NU permite publish** fără toate cele 3 grafice. Deci nu putem urca AAB-ul până nu ai:
1. Icon 512×512
2. Feature graphic 1024×500
3. Minim 2 phone screenshots

**Recomandare rapidă**:
- Icon: 5 min cu online resize dintr-un existing asset
- Feature graphic: 15 min în Canva cu template gata
- 2 screenshots: 5 min de pe telefon după install sideload

Total realistic: **~30 min pentru toate**. Poți lansa în aceeași zi.
