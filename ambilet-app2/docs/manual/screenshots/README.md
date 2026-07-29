# Manual screenshots — naming convention

**Format**: `{cap}-{slug}.png` (kebab-case, lowercase, `.png`)

- `cap` = capitolul din manual (`01`, `02`, ..., `28`)
- `slug` = descriere scurtă în engleză (kebab-case), aliniată cu titlul referinței din capitol

**Locație**: pune fișierul direct aici, în
`ambilet-app2/docs/manual/screenshots/`. Se referențiază în `.md`-uri
ca `![alt](./screenshots/NUME.png)` — deja setat, nu trebuie să
modifici manualele când urci o poză cu numele exact de mai jos.

**Rezoluție**: portret 1080×2400 (Android device standard) sau
aproximativ. Compresie normală (nu ultra-comprimat — să se vadă textul).

**Dacă urci cu alt nume**: pune în `ambilet-app2/screenshots/` (folder-ul
de la rădăcina app-ului, cu nume liber) și cere-mi să le map + să le
mut. Sau redenumește după tabelul de mai jos și pune direct aici.

---

## Screenshot-uri deja urcate (v2.2.0-dev)

- `01-splash.png` — splash animat
- `01-login.png` — ecran de login
- `01-dashboard-tour.png` — Panou cu antet + bară eveniment + meniu jos

---

## Toate screenshot-urile așteptate (per capitol)

### Cap 01 — Bine ai venit
| Filename | Ce arată |
|---|---|
| `01-splash.png` | splash screen animat (QR-scan reveal → logo AmBilet) |
| `01-login.png` | ecranul de login cu email + parolă + buton Autentificare |
| `01-dashboard-tour.png` | Panoul complet cu antet + bară eveniment roșu + meniul de jos evidențiate |
| `01-header-offline.png` | header cu indicator Offline (folosit și în cap 06) |
| `01-cart-offline.png` | coș cu 3 bilete în modul offline (folosit și în cap 06) |
| `01-success-offline.png` | ecran de confirmare vânzare offline (folosit și în cap 06) |
| `01-pending-badge.png` | badge "3 în așteptare" din header (folosit și în cap 06) |
| `01-syncing.png` | sincronizare în progres (folosit și în cap 06) |

### Cap 02 — Tur rapid
`02-panou.png`, `02-scanare.png`, `02-vanzare.png`, `02-rapoarte.png`, `02-setari.png`

### Cap 03 — Selecție eveniment
`03-event-bar.png`, `03-events-modal.png`, `03-past-selector.png`

### Cap 04 — Vânzare bilete
`04-sales-empty.png`, `04-cart.png`, `04-payment-modal.png`, `04-cash-confirm.png`, `04-card-confirm.png`, `04-success.png`, `04-sales-today.png`

### Cap 05 — Vânzare cu locuri
`05-seated-card.png`, `05-seating-map.png`, `05-selected-seats.png`, `05-seat-cart.png`

### Cap 07 — Bilete eveniment
`07-bar.png`, `07-list.png`, `07-header-stats.png`

### Cap 08 — Bilete test
`08-test-card.png`

### Cap 09 — Scanare cameră
`09-scanner-frame.png`, `09-result-valid.png`, `09-result-duplicate.png`, `09-result-invalid.png`

### Cap 10 — Scanare manuală (după cod / email)
`10-manual-modal.png`, `10-name-search.png`

### Cap 11 — Scanere Bluetooth
`11-bluetooth-paired.png`

### Cap 13 — Panou control
`13-panou-full.png`, `13-4-cards.png`, `13-capacity.png`, `13-online-door.png`, `13-quick-actions.png`, `13-activity.png`, `13-panou-scanner.png`

### Cap 14 — Ritm vânzare
`14-rate-card.png`

### Cap 15 — Rapoarte
`15-rapoarte-full.png`, `15-checkin-rate.png`, `15-gates.png`, `15-revenue.png`, `15-hourly.png`, `15-past-selector.png`, `15-export.png`

### Cap 16 — Personal
`16-modal.png`, `16-add-form.png`, `16-expand.png`, `16-counter.png`

### Cap 17 — Porți
`17-modal.png`, `17-add.png`, `17-edit.png`

### Cap 18 — Raportare urgențe
`18-notif-panel.png`, `18-shift-bar.png`, `18-sheet.png`, `18-photo-attached.png`, `18-audio-attached.png`

### Cap 19 — Contact urgențe
`19-settings.png`, `19-buttons.png`

### Cap 20 — Notificări
`20-bell.png`, `20-panel.png`

### Cap 21 — Tură
`21-shift-bar.png`, `21-summary.png`

### Cap 22 — Aspect (light / dark)
`22-settings.png`

### Cap 23 — Securitate
`23-settings.png`

### Cap 24 — Setări Scanner
`24-settings.png`, `24-prompt.png`

### Cap 25 — Mod Offline
`25-settings.png`

### Cap 26 — Widget Android
`26-widget.png`, `26-install.png`

### Cap 27 — Landscape (tablete)
`27-landscape.png`

### Cap 28 — Comutare organizatori
`28-header.png`, `28-modal.png`
