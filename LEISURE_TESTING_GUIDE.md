# Leisure Tenant — Testing Guide

Pași de la zero la "click-and-test" pe tot stack-ul leisure.

## 1. Rulează seeder-ul demo

```bash
cd epas
php artisan db:seed --class=LeisureDemoSeeder
```

> Idempotent — îl poți rula de mai multe ori, nu duplică nimic.

Output-ul îți confirmă credențialele de mai jos.

## 2. Conturi create

### Admin (panou `/admin`)
- **Email:** `owner@aquasplash.demo`
- **Parolă:** `aquasplash`

Folosește acest cont să vezi tenant-ul în [/admin/tenants](/admin/tenants), să-i deschizi tab-ul **Leisure**, să modifici feature flags etc.

### Tenant owner (panou `/tenant`)
Același cont — `owner@aquasplash.demo` — accesează și `/tenant` ca administrator al tenant-ului. Aici vezi:
- **Leisure → Capacități** — calendar de stoc per zi + sloturi orare (kayak)
- **Leisure → Inventar fizic** — 4 kayak-uri + 4 biciclete cu QR-uri printabile
- **Leisure → Rentals (istoric)** — vei vedea 2 rentals active, dintre care unul **overdue** (roșu)
- **Leisure → Echipa** — 5 operatori
- **Leisure → Societăți (multi-CIF)** — 2 societăți (Aquapark + Splash Rentals & Parking)
- **Leisure → CRM Clienți** — 6 clienți cu istoric variat
- **Leisure → Rapoarte** — venituri pe zi + breakdown per canal
- **Ticket Types** — 5 tipuri cu config completă (durate, channel pricing, sezoane, reguli weekend)

### Operatori (panou `/operator`)
Toți cu parola **`operator123`**:

| Email | Rol leisure | Vede |
|---|---|---|
| `manager@aquasplash.demo` | `admin` | Tot (Dashboard + Check-in + Rentals + POS) |
| `checkin@aquasplash.demo` | `check_in` | Doar Check-in |
| `rental@aquasplash.demo` | `rental_operator` | Doar Active Rentals (start/stop) |
| `cashier@aquasplash.demo` | `pos_cashier` | Doar POS |
| `inventory@aquasplash.demo` | `inventory_manager` | Dashboard, fără acțiuni POS |

## 3. Test flows recomandate

### A. Tenant-side admin (login cu owner)

1. Mergi la **/tenant** → Leisure → **Capacități**. Vezi 30 zile pentru fiecare tip + 4 sloturi orare/zi pentru kayak.
2. Click pe o capacitate → schimbă `is_closed` → salvează. Apoi vezi-o cum apare cu badge "Închis".
3. Leisure → **Inventar fizic** → selectează câteva → **Print QR codes**. Se deschide o pagină A4 cu QR-uri (grid 4×, prin api.qrserver.com). Le poți printa fizic.
4. Leisure → **Rentals (istoric)** → vezi 2 rentals active. Unul are badge roșu "Depășire". Apasă **Forțează închidere** pe el — va calcula automat surcharge-ul.
5. Leisure → **Echipa** → vezi cei 5 operatori. Schimbă-i unuia rolul leisure și vezi cum se reflectă imediat în `/operator` (după ce face logout/login).
6. Leisure → **Rapoarte** → modifică intervalul de date. Vezi bar chart venituri/zi + breakdown per canal (online/POS fix/POS mobil/embed).
7. Ticket Types → editează **Kayak (rental)**. Vezi toate secțiunile: 3 variante durată, regulă weekend +25%, surcharge depășire, prețuri pe 4 canale, multi-society linkat.

### B. Operator panel

1. Logout din `/tenant`. Mergi la **/operator** și loghează-te cu `checkin@aquasplash.demo` / `operator123`.
2. Vezi Dashboard cu doar tile-ul **Check-in bilete**. Click → ajungi pe scanner.
3. Iei un cod de bilet din DB (de exemplu copiezi din `/tenant` → biletele unei comenzi) sau folosești ID-uri din seeder. **Tastezi** codul în input și apeși Enter (sau scanezi cu un cititor HID Bluetooth — la fel funcționează: tastează + Enter).
4. Vezi feedback verde (intrare permisă) sau galben (deja folosit).

5. Logout. Login ca `rental@aquasplash.demo`.
6. Vezi pagina **Rentals active** — lista cu kayak-urile active. Unul are border roșu (overdue).
7. Apasă **Finalizează** pe cel overdue → mesaj cu durată reală + surcharge calculat.

8. Logout. Login ca `cashier@aquasplash.demo`.
9. Mergi la **POS** — grid cu cele 5 tipuri de bilete. Schimbă canalul (POS fix vs POS mobil) și vezi cum prețurile se actualizează automat per buton.
10. Adaugă bilete în coș cu butonul "Adaugă". Selectează cash/card. Apasă "Finalizează comandă" — confirmare (integrarea completă Order+Tickets vine în următorul commit per design doc).

### C. Embed widget

Tenant-ul are flag-ul `features.leisure.embed.enabled=true`, deci poți deschide direct:

```
http://localhost/embed/leisure/aquapark-splash-demo
```

Vezi pagina brandată cu cele 5 tipuri de bilete și prețurile **canalul "embed"** aplicate. Coșul JS funcționează partial (E11 stub — full checkout vine ulterior).

Pentru a-l încorpora pe un site extern:
```html
<div id="tixello-leisure"></div>
<script src="http://tixello.local/embed/tixello-leisure-embed.js"
    data-tenant="aquapark-splash-demo"
    data-theme="light"
    data-accent-color="#10b981"></script>
```

### D. API public

```bash
# Disponibilitate luna curentă pentru "Bilet acces 1 zi"
# (înlocuiește {ticketTypeId} cu ID-ul real din DB)
curl "http://your-host/api/leisure/tenants/aquapark-splash-demo/ticket-types/{ticketTypeId}/availability?month=2026-05"

# Sloturi orare pentru kayak într-o zi
curl "http://your-host/api/leisure/tenants/aquapark-splash-demo/ticket-types/{kayakId}/slots?date=2026-05-25"
```

### E. Chitanță 80mm (preview)

Nu există încă endpoint dedicat (vine cu integrarea POS checkout), dar template-ul de chitanță [`resources/views/leisure/receipt.blade.php`](epas/resources/views/leisure/receipt.blade.php) îl poți preview-a manual cu orice Order.

## 4. Cum scapi de demo data

```bash
# Manual (din tinker):
php artisan tinker
>>> $t = \App\Models\Tenant::where('slug', 'aquapark-splash-demo')->first();
>>> $t->delete();  // cascade pe team_members, tax_registries, capacities, rentals, resources

# Pentru users (rămân după tenant delete dacă nu sunt soft-deleted):
>>> \App\Models\User::where('email', 'like', '%@aquasplash.demo')->delete();
```

## 5. Probleme cunoscute / așteptări

- **POS checkout** finalizează cu mesaj "vine în E10" — integrarea completă Order+Ticket creation prin POS e următorul work item (~1 zi).
- **Embed checkout** trimite alert() la final, nu POST real (vine cu același work item).
- **Feature DB tests** sunt scrise dar nu rulează pe host fără Docker/sqlite — rulează doar `tests/Unit/Leisure` local (42/42 pass).
- **Mobile operator app** (E12) e separat — nu există încă în repo.
