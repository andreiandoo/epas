# AmBilet Scan — Play Console publish guide

Pași concreți pentru prima publicare pe Google Play Console. Presupune că ai deja cont developer validat + DUNS. Deschide https://play.google.com/console/u/0/developers/7025992873693153765/app-list într-un tab.

Estimare timp: **~45-60 min prima dată** (formularele Play Console sunt lungi; a doua oară cu update va dura <10 min).

---

## PASUL 0 — Build AAB-ul (înainte de Play Console)

În terminal, din `i:/WORK/eventpilot/ambilet-app2/android`:

```bash
GRADLE_USER_HOME=i:/CACHE/GradleCache \
NODE_OPTIONS="--dns-result-order=ipv4first --max-old-space-size=2048" \
JAVA_TOOL_OPTIONS="-Djavax.net.ssl.trustStoreType=Windows-ROOT" \
./gradlew --no-daemon -Dorg.gradle.parallel=false bundleStoreRelease
```

Output: `android/app/build/outputs/bundle/storeRelease/app-store-release.aab` (~40 MB).

**IMPORTANT**: Comanda de mai sus e diferită față de sideload:
- `bundle` (nu `assemble`) — AAB nu APK
- `Store` (nu default) — flavor-ul de Play Store cu bundle ID `.store`

Verifică AAB-ul:
```bash
ls -la app/build/outputs/bundle/storeRelease/app-store-release.aab
sha256sum app/build/outputs/bundle/storeRelease/app-store-release.aab
```

---

## PASUL 1 — Create app în Play Console

1. În Play Console home → **Create app** (butonul albastru sus dreapta)
2. Completează:
   - **App name**: `AmBilet - Vânzare & Scan` (max 30 chars — vezi `listing/title.txt`)
   - **Default language**: Romanian - română
   - **App or game**: **App**
   - **Free or paid**: **Free**
3. Bifează cele 2 declarații (developer program policies + US export laws)
4. **Create app**

Ești acum în app-ul nou creat cu **Dashboard-ul de setup** — un checklist cu ~15 secțiuni. Le vom parcurge pe rând.

---

## PASUL 2 — App content (declarations)

Toate declarațiile astea trebuie complete înainte de release. Play Console le adună într-o secțiune "App content" în meniul lateral.

### 2.1 Privacy policy
- URL Privacy Policy: **`https://ambilet.ro/confidentialitate`**
- Save

**Alte URL-uri publice utile în alte secțiuni ale Play Console:**
- Terms of Service: `https://ambilet.ro/termeni`
- GDPR: `https://ambilet.ro/gdpr`

### 2.2 App access
- Alege: **"All or some functionality is restricted"** (login obligatoriu)
- Add instructions:
  - **Login credentials for testing** (Play va încerca app-ul):
    - **Username**: `org@organizator.ro`
    - **Password**: `Org@n!zat0r`
    - **Instructions (RO)**: `Contul de test are rol de organizator pe evenimente demo. Deschide aplicația, tastează email + parolă, apasă Autentificare. Vei ajunge pe Panou — vezi rapid dashboardul cu KPI-uri. Poți naviga la tab-urile Scanare / Vânzare / Rapoarte / Setări pentru a vedea funcționalitatea. Nu efectua vânzări reale — datele sunt sandbox.`
- Save

**IMPORTANT**: Contul `org@organizator.ro` e dummy pentru primul submit. Înlocuiește cu un cont real de test când ai unul dedicat.

### 2.3 Ads
- Does your app contain ads? **NO**
- Save

### 2.4 Content rating (IARC questionnaire)
- Start questionnaire
- Email: al tău
- Category: **Utility, Productivity, Communication, or Other**
- Răspunde NO la TOATE întrebările despre violență/adult content/gambling/drugs — app-ul e strict utility.
- Trimite. Vei primi rating **PEGI 3 / Everyone** în câteva secunde.

### 2.5 Target audience and content
- Target age: **18 și peste** (app pentru staff angajat, nu copii)
- App attracts children? **NO**
- Save

### 2.6 News apps
- Is your app a news app? **NO**

### 2.7 COVID-19 contact tracing and status apps
- **NO**

### 2.8 Data safety
Vezi `listing/data-safety.md` — copiază răspunsurile de acolo pas cu pas. E cel mai lung formular, ia ~15 min prima dată.

### 2.9 Government apps
- **NO**

### 2.10 Financial features
- **NO** (nu procesăm plăți în app — plățile POS trec prin backend + terminal card fizic separat)

### 2.11 Health apps
- **NO**

### 2.12 Advertising ID
- Does your app use advertising ID? **NO**

---

## PASUL 3 — Main store listing

Meniu stânga → **Grow** → **Store presence** → **Main store listing**.

- **App name**: `AmBilet - Vânzare & Scan`
- **Short description** (max 80 chars): copiază din `listing/short-description.txt`
- **Full description** (max 4000 chars): copiază din `listing/full-description.txt`
- **App icon** (512×512 PNG, 32-bit RGBA): upload
- **Feature graphic** (1024×500 JPG/PNG, no alpha): upload
- **Phone screenshots** (min 2, max 8):
  - Format: 16:9 sau 9:16, min 320×480, max 3840×3840
  - Upload cele 2-8 screenshot-uri ale tale
- **7-inch tablet screenshots**: opțional, dar recomandat dacă ai
- **10-inch tablet screenshots**: opțional
- (Opțional) Promo video (YouTube URL)
- **App category**: **Business** (recomand) — sau **Productivity** dacă preferi
- **Tags**: adaugă tag-uri relevante: `productivity`, `business tools`, `event management`, `ticketing`, `qr scanner`
- **Contact details**: email, phone, website (ambilet.ro)
- Save

---

## PASUL 4 — Create release (Internal testing întâi)

Play Store are 4 track-uri de release:
1. **Internal testing** — până la 100 testeri, live în ~5 min după submit (pentru primul release recomand)
2. **Closed testing** — până la câteva mii de testeri, aprobat de Google (~1-2 zile)
3. **Open testing** — public, dar cu badge "beta" (aprobat ~1-3 zile)
4. **Production** — public complet (review Google, prima oară 3-7 zile pentru cont nou)

**Recomand: Internal Testing pentru primul release**, apoi când e stabil promovezi la Production.

### 4.1 Setup Internal testing
- Meniu stânga → **Test and release** → **Internal testing**
- Tab **Testers**:
  - Create email list: `AmBilet Internal Testers`
  - Add email addresses (adresele Google/Gmail ale testerilor)
  - Save
- Tab **Releases** → **Create new release**

### 4.2 Upload AAB
- Section **App bundles** → **Upload**
- Selectează `app-store-release.aab` (din `android/app/build/outputs/bundle/storeRelease/`)
- Upload durează 1-2 min (~40 MB)

### 4.3 Play App Signing enrollment
La primul upload, Play îți cere să te înrolezi în **Play App Signing**:
- **YES, use Play App Signing** — recomandat
- Play va lua upload key-ul tău (din AAB) și îl va folosi ca "upload key"
- Play generează un "app signing key" separat pe care îl păstrează la ei
- Pentru orice update viitor, semnezi cu upload key (upload.keystore) și Play re-semnează cu app signing key
- **Beneficiu**: dacă pierzi upload.keystore, Google poate reset-a (nu ești blocat permanent ca înainte 2021)

Bifează opțiunea + **Continue**.

### 4.4 Release details
- **Release name**: `2.2.0` (auto-completat din versionName)
- **Release notes** (per language):
  - Romanian: copiază din `listing/whats-new.txt`
- **Save** → **Review release**
- **Start rollout to Internal testing**

App-ul e ACUM live pe track-ul intern. În ~5-10 min testerii vor primi email cu link install.

---

## PASUL 5 — Verify + escalate to Production

### 5.1 Testeaza pe telefonul tău
- Deschide email-ul de Play Store cu link
- Sau accesează direct: https://play.google.com/apps/internaltest/[APP_ID]
- Install de acolo (NOT sideload)
- Verifică că totul merge

### 5.2 Când e OK, promovează la Production
- Play Console → **Internal testing** tab
- Selectează release-ul curent → **Promote release** → **Production**
- Adaugă release notes (aceleași)
- Rollout: începe cu 10% și crește la 100% în zile
- **Start rollout to Production**

Prima trimitere la Production pentru un cont NOU trebuie **review Google** — durează 3-7 zile de obicei. Vei primi email când e aprobat.

---

## PASUL 6 — Post-launch

### 6.1 Monitor
- **Statistics** → download-uri, ratings, crash rate
- **Ratings and reviews** → răspunde la review-uri (îmbunătățește engagement)
- **Android vitals** → crash-free rate, ANR-uri (Play penalizează app-uri cu >0.5% crash rate)

### 6.2 Updates
Pentru orice update:
1. Bump în `android/app/build.gradle` `store` flavor:
   - `versionCode 220000` → `220010` (crește cu 10 pentru patch, 1000 pentru minor, 100000 pentru major)
   - `versionName "2.2.0"` → `"2.2.1"`
2. `./gradlew bundleStoreRelease`
3. Upload AAB nou în Play Console → Production → Create new release
4. Adaugă release notes
5. Rollout (poate fi 20% prima dată, apoi 100% peste o zi)

---

## Cheatsheet comenzi

```bash
# Build dev sideload (unchanged flow)
./gradlew assembleSideloadRelease
# Output: app/build/outputs/apk/sideloadRelease/app-sideload-arm64-v8a-release.apk

# Build Play Store AAB
./gradlew bundleStoreRelease
# Output: app/build/outputs/bundle/storeRelease/app-store-release.aab

# Build ambele (dev + store)
./gradlew assembleSideloadRelease bundleStoreRelease
```

---

## Files reference

- `store-config/upload.keystore` — signing key (25y validitate, PKCS12 RSA 2048)
- `store-config/signing.properties` — parole (gitignored)
- `store-config/KEYSTORE-BACKUP-CREDENTIALS.md` — backup credentials + instructions (gitignored)
- `store-config/listing/title.txt` — nume app
- `store-config/listing/short-description.txt` — descriere scurtă
- `store-config/listing/full-description.txt` — descriere lungă
- `store-config/listing/whats-new.txt` — release notes primul release
- `store-config/listing/data-safety.md` — răspunsurile pentru Data Safety form
- `store-config/PLAY-CONSOLE-GUIDE.md` — acest fișier

---

## URGENT — Backup keystore

**Fără keystore-ul de la `store-config/upload.keystore` + parola din `signing.properties`, nu poți publica update-uri la app pe Play Store.**

Deși Play App Signing (Pasul 4.3) permite reset-ul cheii dacă e pierdută, procesul e complicat + dăinuiește câteva zile în timpul cărora app-ul rămâne fără updates.

**Fă backup ACUM**:
1. Copiază `upload.keystore` + `signing.properties` + `KEYSTORE-BACKUP-CREDENTIALS.md` într-un folder zip
2. Encrypt zip-ul cu parolă memorată sau salvată în 1Password/Bitwarden
3. Upload zip-ul într-un cloud vault privat (Google Drive, iCloud, sau Dropbox privat)
4. Bonus: pune o copie și pe un USB extern la un loc safe

**Nu partaja parolele prin email / Slack / chat**. Doar tu ar trebui să le ai.
