# Data Safety — răspunsuri pentru Play Console

Play Console → App content → Data safety. Pentru fiecare secțiune, răspunsurile de mai jos.

## 1. Data collection and security

**Does your app collect or share any of the required user data types?**
→ **YES**

**Is all of the user data collected by your app encrypted in transit?**
→ **YES** (comunicarea cu core.tixello.com e exclusiv HTTPS/TLS 1.2+)

**Do you provide a way for users to request that their data is deleted?**
→ **YES** (contact suport@ambilet.ro pentru ștergere cont; sau prin org care i-a invitat)

## 2. Data types — ce tipuri de date colectăm

### Personal info

**Email addresses**
- Collected: **YES**
- Shared with 3rd parties: **NO**
- Processing: **Required for app functionality** (login)
- Optional: NO
- Purpose:
  - App functionality: ✓
  - Account management: ✓

**Name**
- Collected: **YES** (numele complet al staff-ului)
- Shared: **NO**
- Required: YES
- Purpose: App functionality, Account management

**User IDs**
- Collected: **YES** (ID intern user, ID tenant)
- Shared: **NO**
- Required: YES
- Purpose: App functionality, Analytics

### Financial info

**Purchase history**
- Collected: **YES** (istoricul vânzărilor efectuate la POS de staff)
- Shared: **NO**
- Required: YES
- Purpose: App functionality (audit, rapoarte pentru organizator)

### App activity

**App interactions**
- Collected: **YES** (scanări, vânzări, check-in-uri, timp activitate)
- Shared: **NO**
- Required: YES
- Purpose: App functionality, Analytics

**In-app search history**
- Collected: **NO**

**Installed apps**
- Collected: **NO**

**Other user-generated content**
- Collected: **NO** (nu upload de content de la utilizatori)

### Device or other IDs

**Device or other IDs**
- Collected: **YES** (device ID pentru anti-fraud + debugging crash-uri)
- Shared: **NO**
- Required: YES
- Purpose: App functionality, Security & fraud prevention

### Photos and videos

**Photos**
- Collected: **NO** (camera folosită DOAR pentru scanare QR — imaginea nu se salvează niciodată)

### Location

**Approximate location**
- Collected: **NO**

**Precise location**
- Collected: **NO**

### Messages, Audio files, Files & docs
→ **NO** pentru toate

### Diagnostics

**Crash logs**
- Collected: **YES** (via Sentry — stack trace-uri pentru diagnosticare erori)
- Shared: **YES** (cu Sentry.io ca vendor)
- Required: NO (optional — user poate opta out via Setări)
- Purpose: Analytics, App functionality

**Performance data**
- Collected: **YES** (via Sentry — timp răspuns, ratio erori)
- Shared: YES (Sentry.io)
- Required: NO
- Purpose: Analytics

## 3. Security practices

☑ Data is encrypted in transit
☐ You can request that data be deleted (via email suport@ambilet.ro)
☑ Follows Families Policy (n/a — app pentru staff adulți, nu copii)
☑ Committed to Play's Families Policy (n/a)
☑ Independent security review (nu încă)

## Notă importantă

**Nu colectăm date de la clienții care cumpără bilete** — doar de la staff-ul care operează aplicația. Datele cumpărătorilor de bilete sunt gestionate separat prin platforma web AmBilet.ro (nu prin această aplicație).
