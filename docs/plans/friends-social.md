# Plan — Prieteni & social (friends, cumpără la comun, cumpără pentru ei, invită) în core.tixello.com

> Feature: funcționalitățile „prieteni" din prototipul de client — listă de prieteni, cumpără la comun cu prietenii (split), cumpără pentru ei (gift), invită prieteni.
> Branch de referință: `core`.

---

## 0. DECIZIE ARHITECTURALĂ #1 (blocantă) — care identitate de cumpărător?

Există **două** modele de cumpărător:

| | `Customer` (`app/Models/Customer.php`) | `MarketplaceCustomer` (`app/Models/MarketplaceCustomer.php`) |
|---|---|---|
| Scope | tenant (`tenant_id`, pivot `customer_tenant`) | marketplace (`marketplace_client_id`) |
| Auth | Sanctum + `CustomerToken` | Sanctum + 2FA |
| Rute | `tenant-client/*` | `marketplace-client/customer/*` (**app-ul mobil**) |
| Are: bilete, transferuri, gift cards, beneficiari, favorite | ❌ (parțial) | ✅ |
| Are: puncte loialitate, `Gamification\Referral`, cashless | ✅ | ❌ |

**Cumpărătorul din aplicația mobilă = `MarketplaceCustomer`.** Standardizează prietenii pe el. Loialitatea/referral/cashless stau pe `Customer` → e nevoie de **o punte**:
- opțiunea A (recomandată): coloană de legătură `marketplace_customers.customer_id` (nullable) + un `IdentityBridgeService` care citește/scrie latura corectă;
- opțiunea B: echivalente marketplace-side pentru puncte/referral.

Rezolvă asta **înainte** de restul. Toate rutele noi merg sub `marketplace-client/customer/*` (guard `auth:sanctum` + middleware `marketplace.auth`, vezi `routes/api.php:2309`).

---

## 1. Listă de prieteni (add/accept) — **DE CONSTRUIT DE LA ZERO**

Nu există niciun model friend/follow între cumpărători. (`MarketplaceCustomerBeneficiary` = „persoane salvate", nu conturi, unidirecțional — utile doar ca sugestii.)

### Migrație `create_marketplace_friendships_table`
```php
Schema::create('marketplace_friendships', function (Blueprint $t) {
    $t->id();
    $t->foreignId('requester_id')->constrained('marketplace_customers')->cascadeOnDelete();
    $t->foreignId('addressee_id')->constrained('marketplace_customers')->cascadeOnDelete();
    $t->enum('status', ['pending','accepted','blocked'])->default('pending');
    $t->timestamp('accepted_at')->nullable();
    $t->timestamps();
    $t->unique(['requester_id','addressee_id']);
    $t->index(['addressee_id','status']);
});
```

### Model `app/Models/MarketplaceFriendship.php` + helpers pe `MarketplaceCustomer`
```php
// MarketplaceCustomer
public function friends(): // acceptate, în ambele direcții (union requester+addressee)
public function pendingReceived(): hasMany(Friendship, addressee_id)->pending
public function pendingSent(): hasMany(Friendship, requester_id)->pending
public function sendFriendRequest(MarketplaceCustomer $to): Friendship
public function acceptFriend(Friendship $r): void
public function isFriendWith(MarketplaceCustomer $o): bool
public function block(MarketplaceCustomer $o): void
```

### Descoperire prieteni
- link/QR de invitație (reutilizează referral — §4);
- **potrivire contacte** (hash SHA-256 pe email/telefon normalizat, niciodată plain) → match pe `MarketplaceCustomer`;
- căutare după username/handle (adaugă `username` unic pe `marketplace_customers` dacă nu există);
- sugestii din `MarketplaceCustomerBeneficiary` + co-participanți la evenimente trecute (din `orders`/`tickets`).

### API — `marketplace-client/customer/friends/*`
```
GET    friends                      // lista prietenilor
GET    friends/requests             // {incoming[], outgoing[]}
POST   friends/request              // {addressee_id | email | phone_hash}
POST   friends/{id}/accept
POST   friends/{id}/reject
DELETE friends/{id}                 // remove
POST   friends/{id}/block
GET    friends/search?q=            // username/nume
POST   friends/match-contacts       // {hashes:[]} → conturi găsite
```

### Privacy / GDPR
- potrivirea contactelor doar pe hash; vizibilitate „merg la evenimentul X" **opt-in**; folosește infra de cookie/consimțământ existentă.

---

## 2. Cumpără la comun / split — **EXTINDE `GroupBooking` către API-ul de client**

**Există deja** (organizer/Filament-only, fără API client):
- `app/Models/GroupBooking.php` — `organizer_customer_id`, `payment_type` = `PAYMENT_FULL|PAYMENT_SPLIT|PAYMENT_INVOICE`, `total_tickets`, status flow, `getPaymentProgress()`.
- `app/Models/GroupBookingMember.php` — `ticket_id`, `name/email/phone`, `amount_due`, `amount_paid`, `payment_status`, `payment_link`, `paid_at`.
- `app/Services/GroupBooking/GroupBookingService.php`; resurse Filament tenant + marketplace.
- Lipsă: **rută în `routes/api.php`** (grep group/split = 0) și **membrii nu sunt legați de conturi** (doar email/telefon).

### De adăugat
- Migrație: `group_booking_members.marketplace_customer_id` (nullable FK) → leagă membrul de un cont/prieten.
- API — `marketplace-client/customer/group-bookings/*`:
```
POST group-bookings                 // creează grup dintr-un coș; initiator = organizer_customer
POST group-bookings/{id}/invite     // {friend_ids[] | emails[]} → GroupBookingMember + payment_link
GET  group-bookings/{id}            // progres (getPaymentProgress), membri, status
POST group-bookings/{id}/members/{m}/pay   // → Stripe PaymentIntent pt partea membrului
POST group-bookings/{id}/cancel
GET  group-bookings                 // grupurile mele (inițiate + în care sunt membru)
```
- **Plată:** fiecare `payment_link` → un Stripe **PaymentIntent** (PaymentSheet în mobil). `PAYMENT_SPLIT` = fiecare plătește partea lui; `PAYMENT_FULL` = inițiatorul plătește tot. Biletele se emit când toți au plătit (sau per-membru la plata lui). **Real-world → fără IAP.**
- Notificări push: „X te-a invitat să cumpărați împreună", „membru a plătit", „grup complet".

> `PaymentSplit` (`app/Models/PaymentSplit.php`) NU e pentru asta — e split de fee Stripe-Connect platformă/tenant. A nu se confunda.

---

## 3. Cumpără pentru ei / gift — **REUTILIZEAZĂ, adaugă flux cu selector de prieten**

**Fundație existentă (puternică):**
- `app/Models/Ticket.php` — `current_owner_customer_id` decuplat de cumpărător (`currentOwnerCustomer()`), `attendee_name/email`, `holder_name/email`. **Un bilet poate fi deja alocat altei persoane decât cumpărătorul.**
- `app/Models/MarketplaceTicketTransfer.php` — `from_customer_id`/`to_customer_id`/`to_email`, `token`, status pending/accepted/rejected/expired, expirare 7 zile, `accept()` mută proprietatea. API: `routes/api.php:2484-2501` (`/transfers/direct`, `/transfers`, `/incoming|outgoing`, `/accept|reject|cancel`, public `/accept-by-token`).
- `app/Models/MarketplaceGiftCard.php` — flux complet gift (recipient, mesaj, ocazie, delivery programat).

### De adăugat: `GiftPurchaseService` + opțiune la checkout
- La checkout: „Acest bilet e pentru un prieten" → alege prieten (din §1) sau email.
- Orchestrare: cumpără → setează `attendee_name/email` → creează `MarketplaceTicketTransfer` **pending** către contul prietenului la finalizarea comenzii → notifică.
- Fallback: dacă prietenul n-are cont → transfer by-email (există deja).
- API: reutilizează rutele de transfer; adaugă doar `POST marketplace-client/customer/orders/{id}/gift {ticket_id, friend_id|email}` ca shortcut.

---

## 4. Invită prieteni — **REUTILIZEAZĂ referral**

**Există:**
- `app/Models/Gamification/Referral.php` — `referrer_customer_id`/`referred_customer_id`/`referred_email`, `referral_code`, flow pending→signed_up→converted, `processPoints()` (puncte ambelor părți).
- Marketplace-side: tabele `marketplace_referrals` + `marketplace_referral_codes` + `App\Http\Controllers\Api\MarketplaceClient\Customer\ReferralsController` (query prin `DB::table`). Rute: `routes/api.php:2474-2482` (`/referrals`, `/regenerate`, `/leaderboard`, `/claim`, public `/track-click`, `/validate`).

### De făcut
- Conectează ecranul „Invită prieteni" la rutele `marketplace-client/customer/referrals/*` (există).
- **Punte referral → friendship:** când un invitat se înregistrează prin codul tău, creează automat un `MarketplaceFriendship` (pending sau accepted) între cei doi.
- `Affiliate*` = program monetizat separat (comisioane/withdrawal) — **nu** îl folosi pentru invitația simplă între prieteni.

---

## 5. Transversal (necesare ca să fie „reale")

- **Wallet general** pentru `MarketplaceCustomer`: azi există doar cashless (festival/wristband, pe `Customer`, `app/Models/Cashless/CashlessAccount.php`) + sold gift-card. Dacă vrei portofel general (ca în prototip), model nou `MarketplaceWallet` (balance, topup Stripe, ledger) — plan separat.
- **Push & notificări:** cereri prietenie, invitații group-buy, gift/transfer primit → layer FCM/APNs + tabel notificări (nu există push customer-facing încă).
- **Loialitate:** `Gamification\CustomerPoints`/`Reward`/`Badge` + `/rewards` API există, dar pe `Customer` → rezolvă prin puntea de identitate (§0).

---

## 6. Ordinea recomandată de implementare

1. **Punte de identitate** `MarketplaceCustomer` ↔ `Customer` (§0) — precondiție.
2. **Friends graph** (§1): migrație + model + API + descoperire (contacte/username/QR).
3. **Invită** (§4): wire referral existent + auto-friend la signup.
4. **Gift-at-purchase** (§3): `GiftPurchaseService` peste transfer/gift-card existent.
5. **Group-buy** (§2): expune `GroupBooking` prin API client + membri legați de conturi + Stripe split.
6. **Push + wallet general** (§5) dacă sunt necesare dincolo de cashless.

## 7. Rezumat EXISTĂ vs. DE CONSTRUIT

| Funcție | Status | Ce faci |
|---|---|---|
| Listă prieteni (add/accept) | **LIPSEȘTE** | construiește `marketplace_friendships` + API |
| Cumpără la comun / split | **PARȚIAL** | expune `GroupBooking` prin API client + leagă membri de conturi |
| Cumpără pentru ei / gift | **EXISTĂ (mare parte)** | flux gift-at-purchase peste `MarketplaceTicketTransfer`/`GiftCard` |
| Invită / referral | **EXISTĂ** | wire `referrals` API + punte spre friendship |
| Wallet | **PARȚIAL** | cashless există; wallet general = plan separat |
| Loialitate puncte | **EXISTĂ** | rezolvă prin puntea de identitate |
