# Nordvale — skin tenant LEISURE (nordvale.tixello.ro)

Site public complet, funcțional, pentru un tenant Tixello de tip **leisure** (parc de aventură / rezervație). Construit după modelul `tenant-demos/teatru`, cu design-ul preluat 1:1 din template-urile HTML `verticals/demo-leisure`.

## Arhitectură

Site PHP standalone (fără build). Se conectează la `core.tixello.com` prin:

- **Server-side** (`includes/api.php` → `api_get()`): citiri publice cache-uite (experiențe/evenimente, abonamente, metode de plată). Scopate automat prin `?tenant=TENANT_ID`.
- **Browser → core** (`api/proxy.php`): auth, cont client, coș/checkout, voucher, seating/hold-uri. Evită CORS și păstrează o sesiune stabilă prin cookie `nordvale_seat_sid` (header `X-Session-Id`).

Autentificarea trăiește în browser în `localStorage`:
- `nordvale_auth` = `{ token, user }` — token Bearer trimis către core prin proxy.
- `nordvale_cart` = coșul (general admission: `{ event, items:[{ticket_type_id,title,date,slot,qty,unit_price}], subtotal }`).

## Configurare

1. Copiază `includes/config.local.php.example` → `includes/config.local.php`.
2. Setează `TENANT_ID` la id-ul real al tenantului leisure „Nordvale”.
3. (Opțional) `DEBUG=true` pe dezvoltare.

Fișierele `includes/config.local.php`, `cache/`, `deploy.log` NU se versionează și sunt păstrate la deploy.

## Rute (clean URL → fișier)

| Rută | Fișier | |
|---|---|---|
| `/` | index.php | homepage |
| `/experiente` | experiences.php | listă experiențe |
| `/experienta?slug=` | experience.php | detaliu experiență |
| `/calendar` | calendar.php | calendar sloturi |
| `/planifica` | planifica.php | planificator vizită |
| `/bilete` | bilete.php | bilete & pachete (CTA principal) |
| `/abonamente` | abonamente.php | membership / abonamente |
| `/grupuri` | grupuri.php | grupuri & corporate |
| `/card-cadou` · `/card-cadou-confirmare` | gift-card.php · gift-card-confirmation.php | carduri cadou |
| `/cos` → `/finalizare` → `/confirmare` | cart.php · checkout.php · confirmare.php | flux comandă |
| `/voucher` | voucher-verify.php | verificare voucher |
| `/autentificare` `/inregistrare` `/recuperare-parola` `/reseteaza-parola` `/verifica-email` | login/register/forgot-password/reset-password/verify-email.php | auth |
| `/cont`, `/cont/{pagina}` | cont/*.php | cont client (dashboard, comenzi, comanda, bilete, bilet, favorite, abonamente, notificari, profil, rambursare, suport) |
| `/despre` `/contact` `/faq` `/noutati` `/termeni` `/confidentialitate` | about/contact/faq/news/terms/privacy.php | conținut |

## Deploy (nordvale.tixello.ro)

Ca la teatru: branch dedicat `nordvale` care conține site-ul la rădăcină + webhook FTP.

1. Sincronizează conținutul acestui folder în branch-ul `nordvale` (la rădăcina branch-ului).
2. `_webhook-deploy.php` (setează `DEPLOY_SECRET`) descarcă ZIP-ul branch-ului `nordvale` și îl extrage în web root la fiecare push. Test manual: `https://nordvale.tixello.ro/_webhook-deploy.php?test=1`.
3. Pe server, pune `includes/config.local.php` cu `TENANT_ID` real (nu se suprascrie la deploy).

## Note

- Design-ul e preluat exact din template-urile HTML; wiring-ul funcțional e grefat peste (după modelul teatru).
- Paginile publice au fallback pe conținutul demo din template dacă API-ul nu returnează date — astfel site-ul se randează mereu, chiar înainte de a conecta tenantul.
