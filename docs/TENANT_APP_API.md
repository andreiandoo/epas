# Tenant Mobile-App API (`/api/tenant-app/*`)

Strat de API **nou, autentificat, tenant-scoped**, care permite staff-ului unui tenant white-label să-și gestioneze evenimentele, vânzările și check-in-ul de pe mobil (aplicația Tixello Mobile). Este **aditiv** — nu modifică fluxurile existente; refolosește modelele partajate `Event` / `Order` / `Ticket` (toate au `tenant_id`).

## Identitate & autentificare

Staff-ul tenantului = un `User` (email + parolă, Sanctum) legat de un `Tenant`:
- **owner** — `User(role='tenant')` cu `ownedTenant`;
- **editor/admin** — `User` cu `tenant_id`;
- **staff** — `User` legat de un `App\Models\Leisure\TenantTeamMember` activ (rol + permisiuni).

Login-ul emite un token Sanctum al cărui **nume** codează apartenența:
- owner/admin → `tenant-app`
- staff → `tenant-staff-{teamMemberId}`

Middleware-ul **`tenant.app.auth`** (`App\Http\Middleware\TenantAppAuth`) rulează după `auth:sanctum`, rezolvă `Tenant`-ul și publică pe request `tenant`, `tenant_id`, `tenant_team_member`. Fiecare controller filtrează pe `tenant_id`.

**Permisiuni** (gate în `BaseController::requirePermission`): owner/admin au acces complet; staff-ul e gated de `TenantTeamMember::hasPermission()` — `tickets.scan`, `orders.view`, `orders.refund`, `pos.checkout`, `reports.view` etc. (adminii trec peste listă).

## Endpoint-uri

| Metodă | Cale | Descriere | Permisiune |
|---|---|---|---|
| POST | `/api/tenant-app/login` | Login staff tenant → `{user, token, available_tenants}` | public (throttle 10/min) |
| GET | `/api/tenant-app/me` | Profil + tenant + team_member | — |
| POST | `/api/tenant-app/logout` | Revocă token-ul curent | — |
| GET | `/api/tenant-app/dashboard` | KPIs (evenimente, vândute, intrați, venituri) + comenzi recente | `reports.view` |
| GET | `/api/tenant-app/events` | Listă evenimente ale tenantului (paginat, cu sold/entered/revenue) | — |
| GET | `/api/tenant-app/events/{id}` | Detaliu eveniment + tipuri de bilete | — |
| GET | `/api/tenant-app/events/{id}/statistics` | Rată check-in, vândute, intrați, venituri, la ușă | — |
| GET | `/api/tenant-app/orders` | Listă comenzi (filtre `event_id`, `status`) | `orders.view` |
| GET | `/api/tenant-app/orders/{id}` | Detaliu comandă + bilete | `orders.view` |
| POST | `/api/tenant-app/participants/checkin` | Check-in după cod (`ticket_code`, normalizează `/t//v//verify/`) | `tickets.scan` |
| POST | `/api/tenant-app/events/{id}/check-in/{barcode}` | Check-in scoped pe eveniment | `tickets.scan` |
| DELETE | `/api/tenant-app/events/{id}/check-in/{barcode}` | Anulează check-in | `tickets.scan` |

Răspuns standard: `{ "success": bool, "message"?, "data"? }` (envelope identic cu API-ul marketplace).

## Fișiere adăugate

- `app/Http/Middleware/TenantAppAuth.php` — alias `tenant.app.auth` (înregistrat în `bootstrap/app.php`)
- `app/Http/Controllers/Api/TenantApp/{BaseController,AuthController,DashboardController,EventsController,OrdersController,CheckInController}.php`
- Grup de rute nou în `routes/api.php` (prefix `tenant-app`)

## De construit în continuare

- **POS door-sale** (`POST /orders`) — refolosind `App\Services\DoorSales\DoorSalesService` (deja keyed pe `tenant_id`): `getTicketTypes`, `calculate`, `process`. De validat pe mediu real întâi (semnătura `$data`).
- **Hartă locuri** — versiune tenant a `seating-map` + token semnat.
- **Echipă** — CRUD `TenantTeamMember` + invitații (login staff prin invite).
- **Finanțe / facturi / payout**, **setări/branding/mobile-settings**, **export CSV**.
- **Auth**: forgot/reset password, verificare, mobile-settings.

## Calibrare pe date reale (tenant demo 17 teatru & 36 leisure)

Ajustări făcute după inspecția conturilor demo:

- **Venituri / „plătit" se calculează pe `Order.status`, NU pe `payment_status`.** În date reale o comandă plătită are `status='paid'` dar `payment_status='pending'`. Bucket folosit: `status ∈ {paid, completed, confirmed, free}`.
- **Permisiuni derivate din `leisure_role`.** Membrii de echipă reali au `permissions=null` și doar `leisure_role` setat. `BaseController::can()` mapează rolul la permisiuni (ex. `check_in → tickets.scan`, `pos_cashier → pos.checkout,orders.view,tickets.scan`), în plus față de `admin/manager` (acces complet) și lista explicită de permisiuni dacă există.
- **Identitate confirmată**: teatru 17 = owner `User(role='tenant')` fără team members; leisure 36 = 1 editor + 5 `tenant_team_members` (manager/admin, staff pos_cashier/check_in/rental_operator/inventory_manager). Login-ul acoperă owner, editor și staff.
- **KPIs de Home** nu mai cer `reports.view` (un scanner vede numerele de bază); rapoartele detaliate/export rămân gated.

## Note

Codul e aliniat la modelele existente (`User`, `Leisure\TenantTeamMember`, `Event`, `Order`, `Ticket`), lint-uit (`php -l`) și calibrat pe datele demo, dar **nu a fost rulat runtime** (repo-ul din sesiune nu are `vendor/`). Rămâne de verificat cu tickete reale (demo n-avea `tickets`): fluxul de check-in și numărul „vândute" bazat pe tickete.
