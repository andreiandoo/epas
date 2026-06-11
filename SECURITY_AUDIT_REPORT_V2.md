# Raport Complet de Audit de Securitate - EPAS Platform

**Data:** 2026-02-01
**Versiune:** 2.0 (Extended Tenant & Marketplace Audit)
**Auditor:** Security Analysis Tool

---

## SUMAR EXECUTIV

Acest raport prezinta rezultatele auditului COMPLET de securitate, inclusiv analiza aprofundata a componentelor **Tenant** si **Marketplace**. Au fost identificate vulnerabilitati suplimentare critice care necesita atentie imediata.

### Statistici Actualizate

| Severitate | Prima Analiza | Analiza Extinsa | Total |
|------------|---------------|-----------------|-------|
| **Critica** | 3 | 4 | **7** |
| **Ridicata** | 5 | 8 | **13** |
| **Medie** | 7 | 4 | **11** |
| **Scazuta** | 4 | 2 | **6** |

---

# PARTEA I: VULNERABILITATI TENANT & MARKETPLACE (NOI)

## CRITICAL-004: Endpoint-uri Admin Fara Autentificare

**Severitate: CRITICA | CVSS: 9.8**
**Locatie:** `routes/api.php:1250-1290`

### Descriere
Rutele admin sub prefix-ul `/api/tenant-client/admin` NU au middleware de autentificare:

```php
Route::prefix('tenant-client')->middleware(['throttle:api', 'tenant.client.cors'])->group(function () {
    // Admin (requires admin auth) - DAR NU ARE MIDDLEWARE!
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/events', [AdminController::class, 'events']);
        Route::post('/events', [AdminController::class, 'createEvent']);
        Route::put('/events/{eventId}', [AdminController::class, 'updateEvent']);
        Route::get('/orders', [AdminController::class, 'orders']);
        Route::get('/customers', [AdminController::class, 'customers']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);
        // ... TOATE NEPROTEJATE
    });
});
```

### Impact
- Orice utilizator neautentificat poate accesa dashboard-ul admin
- Poate vedea toate evenimentele, comenzile, clientii
- Poate modifica setarile tenant-ului
- **Data breach masiv**

### Exploit
```bash
# Acces la toate datele admin fara autentificare
curl "https://api.example.com/api/tenant-client/admin/dashboard?hostname=tenant.example.com"
curl "https://api.example.com/api/tenant-client/admin/orders?hostname=tenant.example.com"
curl "https://api.example.com/api/tenant-client/admin/customers?hostname=tenant.example.com"
```

### Remediere
```php
// routes/api.php - TREBUIE ADAUGAT MIDDLEWARE
Route::prefix('admin')
    ->middleware(['tenant.admin.auth']) // <-- ADAUGATI ACEST MIDDLEWARE
    ->group(function () {
        // ... rutele admin
    });
```

**Patch creat:** `app/Http/Middleware/TenantAdminAuth.php`

---

## CRITICAL-005: IDOR pe Orders (Insecure Direct Object Reference)

**Severitate: CRITICA | CVSS: 8.1**
**Locatie:** `app/Http/Controllers/Api/TenantClient/OrderController.php:267`

### Descriere
```php
public function show(Request $request, int $orderId): JsonResponse
{
    $order = Order::with(['tickets.ticketType.event', 'customer'])->find($orderId);
    // LIPSESTE: ->where('tenant_id', $tenantId)

    if (!$order) {
        return response()->json(['error' => 'Order not found'], 404);
    }
    // Returneaza comanda ORICARUI tenant
}
```

### Impact
- Un client poate vedea comenzile altor tenanti doar schimband ID-ul
- Expune date personale (email, telefon, adresa)
- Expune informatii financiare

### Exploit
```bash
# Itereaza prin order IDs pentru a extrage date
for i in {1..10000}; do
    curl "https://api.example.com/api/tenant-client/orders/$i" >> orders_dump.json
done
```

### Remediere
```php
public function show(Request $request, int $orderId): JsonResponse
{
    $tenant = $request->attributes->get('tenant');

    $order = Order::with(['tickets.ticketType.event', 'customer'])
        ->where('tenant_id', $tenant->id)  // <-- OBLIGATORIU
        ->find($orderId);

    if (!$order) {
        return response()->json(['error' => 'Order not found'], 404);
    }
    // ...
}
```

---

## CRITICAL-006: Super Admin Login Bypass

**Severitate: CRITICA | CVSS: 9.1**
**Locatie:** `app/Http/Controllers/Api/TenantClient/AuthController.php:334-373`

### Descriere
```php
public function superAdminLogin(Request $request): JsonResponse
{
    $token = $request->input('token');

    // Verifica token din cache
    $tokenData = cache()->get("admin_login_token:{$token}");

    if (!$tokenData) {
        return response()->json(['success' => false, 'message' => 'Invalid or expired token'], 401);
    }

    // Returneaza sesiune super_admin
    return response()->json([
        'success' => true,
        'data' => [
            'token' => $sessionToken,
            'user' => ['id' => $tokenData['admin_id'], 'role' => 'super_admin'],
        ],
    ]);
}
```

### Probleme
1. Endpoint PUBLIC la `/api/tenant-client/auth/super-login`
2. Fara rate limiting - brute-forceable
3. Token-uri cache predictibile
4. Fara verificare IP sau semnatura

### Impact
- Acces super_admin daca token-ul e ghicit/furat
- Compromiterea completa a platformei

### Remediere
```php
// 1. Adaugati rate limiting strict
Route::post('/auth/super-login', [AuthController::class, 'superAdminLogin'])
    ->middleware('throttle:3,5'); // Max 3 incercari la 5 minute

// 2. Adaugati verificare IP
$tokenData = cache()->get("admin_login_token:{$token}");
if ($tokenData['allowed_ip'] !== $request->ip()) {
    return response()->json(['error' => 'IP mismatch'], 403);
}

// 3. Folositi token-uri mai lungi si semnate
$token = hash_hmac('sha256', Str::random(64), config('app.key'));
```

---

## CRITICAL-007: TenantScope Permite Bypass prin Query Parameter

**Severitate: CRITICA | CVSS: 8.5**
**Locatie:** `app/Models/Scopes/TenantScope.php:46-48`

### Descriere
```php
protected function getCurrentTenantId(): ?int
{
    // ... alte verificari ...

    // VULNERABIL: Accepta tenant_id din query params!
    if (request()->has('_tenant_id')) {
        return request()->get('_tenant_id');
    }

    return null;
}
```

### Impact
Un atacator poate accesa datele oricarui tenant adaugand `?_tenant_id=X` la orice request.

### Exploit
```bash
# Acceseaza datele tenant-ului 5 (nu al tau)
curl "https://api.example.com/api/tenant-client/events?_tenant_id=5"
```

### Remediere
```php
// ELIMINATI complet aceasta linie:
// if (request()->has('_tenant_id')) { ... }

// Folositi DOAR request attributes setate de middleware validat
if (request()->attributes->has('tenant_id')) {
    return request()->attributes->get('tenant_id');
}
```

**Patch creat:** `app/Models/Scopes/SecureTenantScope.php`

---

## HIGH-006: Lipsa Izolarii Tenant in Marketplace Events

**Severitate: RIDICATA | CVSS: 7.5**
**Locatie:** `app/Http/Controllers/Api/MarketplaceClient/EventsController.php:619`

### Descriere
```php
public function show(Request $request, $identifier): JsonResponse
{
    $event = Event::find((int) $identifier);
    // LIPSESTE: ->where('marketplace_client_id', $client->id)
}
```

Similar la liniile: 670, 703, 805

### Impact
Un marketplace client poate vedea evenimentele altor clienti.

### Remediere
```php
$client = $this->requireClient($request);
$event = Event::where('marketplace_client_id', $client->id)
    ->find((int) $identifier);
```

---

## HIGH-007: API Keys Stocate in Plaintext

**Severitate: RIDICATA | CVSS: 7.2**
**Locatie:** `app/Http/Middleware/MarketplaceClientAuth.php:38`

### Descriere
```php
$client = MarketplaceClient::where('api_key', $apiKey)->first();
```

### Probleme
1. API keys stocate plaintext in DB
2. Comparatie non-timing-safe
3. Vulnerabil la database breach

### Remediere
```php
// Stocare hash in DB
$hashedKey = hash('sha256', $apiKey);
$client = MarketplaceClient::where('api_key_hash', $hashedKey)->first();

// SAU timing-safe comparison
$client = MarketplaceClient::all()->first(function($c) use ($apiKey) {
    return $c->api_key && hash_equals($c->api_key, $apiKey);
});
```

---

## HIGH-008: Tenant Resolution Bypass

**Severitate: RIDICATA | CVSS: 7.8**
**Locatie:** `app/Http/Controllers/Api/TenantClient/AuthController.php:30-52`

### Descriere
```php
private function resolveTenant(Request $request): ?Tenant
{
    $hostname = $request->query('hostname');
    $tenantId = $request->query('tenant');

    if ($tenantId) {
        return Tenant::find($tenantId);  // ORICE tenant ID e acceptat!
    }
    return null;
}
```

### Impact
Un utilizator se poate inregistra/autentifica pe ORICE tenant pasand `?tenant=X`.

### Remediere
```php
// NU acceptati tenant_id din query params
// Folositi DOAR hostname-based resolution
if ($hostname) {
    $domain = Domain::where('domain', $hostname)
        ->where('is_active', true)
        ->first();
    return $domain?->tenant;
}
return null;
```

---

## HIGH-009: Lipsa Validare Event-Tenant in Cart

**Severitate: RIDICATA | CVSS: 7.0**
**Locatie:** `app/Http/Controllers/Api/TenantClient/OrderController.php:109-141`

### Descriere
```php
foreach ($validated['cart'] as $cartItem) {
    $ticketType = TicketType::find($cartItem['ticketTypeId']);

    // LIPSESTE: Verificare ca ticketType apartine unui event al tenant-ului curent

    // Proceseaza comanda...
}
```

### Impact
Un client poate cumpara bilete de la evenimente ale altor tenanti.

---

## HIGH-010: SQL Injection in Shop Search

**Severitate: RIDICATA | CVSS: 8.0**
**Locatie:** `app/Http/Controllers/Api/TenantClient/ShopProductController.php:113`

### Descriere
```php
$q->whereRaw("JSON_EXTRACT(title, '$.\"{$tenantLanguage}\"') LIKE ?", ["%{$search}%"])
```

`$tenantLanguage` este interpolat direct in SQL fara escape.

### Remediere
```php
$safeLang = preg_replace('/[^a-z_]/', '', strtolower($tenantLanguage));
$q->whereRaw("JSON_EXTRACT(title, '$.\"" . $safeLang . "\"') LIKE ?", ["%{$search}%"])
```

---

## HIGH-011: withoutGlobalScopes() Folosit Periculos

**Severitate: RIDICATA | CVSS: 6.5**
**Locatii multiple:**
- `TenantClient/AffiliateController.php:120, 194, 278, 345, 400, 455, 519, 623`
- `TenantClient/AccountController.php:1110`

### Descriere
```php
$affiliate = \App\Models\Affiliate::withoutGlobalScopes()
    ->where('tenant_id', $tenant->id)
    ->first();
```

Desi adauga `tenant_id` manual, este usor de uitat si permite data leakage.

### Remediere
```php
// Folositi metoda securizata din trait
$affiliate = Affiliate::findSecure($affiliateId);
```

---

## HIGH-012: Email Verification Token Slab

**Severitate: RIDICATA | CVSS: 6.8**
**Locatie:** `app/Http/Controllers/Api/TenantClient/AuthController.php:390`

### Probleme
1. Token stocat in JSON meta fara expiration check
2. Fara rate limiting pe verificare
3. 64 caractere random pot fi brute-forced

---

## HIGH-013: Lipsa Permission Checks in Admin Operations

**Severitate: RIDICATA | CVSS: 7.5**
**Locatie:** `app/Http/Controllers/Api/TenantClient/AdminController.php:456-509`

### Descriere
```php
public function createVenue(Request $request): JsonResponse
{
    $tenant = $request->attributes->get('tenant');

    // LIPSESTE: Verificare ca user-ul are rol de admin
    // Oricine cu acces la tenant poate crea venues

    Venue::create([...]);
}
```

---

# PARTEA II: PATCH-URI CREATE

## Fisiere Noi de Securitate

| Fisier | Descriere |
|--------|-----------|
| `app/Http/Middleware/TenantAdminAuth.php` | Autentificare admin pentru tenant routes |
| `app/Http/Middleware/TenantAuthenticationSecure.php` | Versiune securizata a TenantAuthentication |
| `app/Http/Middleware/DDoSProtectionMiddleware.php` | Protectie DDoS multi-layer |
| `app/Traits/SecureTenantScoping.php` | Trait pentru izolare tenant sigura |
| `app/Traits/SecureMarketplaceScoping.php` | Trait pentru izolare marketplace |
| `app/Models/Scopes/SecureTenantScope.php` | Scope global securizat pentru tenant |
| `app/Models/Scopes/MarketplaceScope.php` | Scope global pentru marketplace |
| `config/backup.php` | Configurare backup DB |
| `app/Console/Commands/DatabaseBackupCommand.php` | Comanda backup automat |

---

# PARTEA III: ACTIUNI IMEDIATE NECESARE

## Prioritate CRITICA (24 ore)

1. **Adaugati middleware la admin routes:**
```php
// routes/api.php linia 1250
Route::prefix('admin')
    ->middleware(['tenant.admin.auth'])
    ->group(function () { ... });
```

2. **Inregistrati middleware-ul:**
```php
// bootstrap/app.php sau app/Http/Kernel.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'tenant.admin.auth' => \App\Http\Middleware\TenantAdminAuth::class,
        'ddos.protection' => \App\Http\Middleware\DDoSProtectionMiddleware::class,
    ]);
})
```

3. **Inlocuiti TenantScope:**
```php
// In modelele care folosesc TenantScope
use App\Models\Scopes\SecureTenantScope;

protected static function booted()
{
    static::addGlobalScope(new SecureTenantScope());
}
```

4. **Adaugati tenant_id check in OrderController:**
```php
$order = Order::where('tenant_id', $tenant->id)->find($orderId);
```

5. **Dezactivati super-login sau adaugati protectie:**
```php
// Comentati sau stergeti ruta
// Route::post('/auth/super-login', ...);
```

## Prioritate RIDICATA (1 saptamana)

1. Hash API keys in database
2. Adaugati tenant validation in cart processing
3. Fixati SQL injection in shop search
4. Adaugati rate limiting pe toate endpoint-urile sensibile
5. Implementati permission checks in admin operations

## Prioritate MEDIE (2 saptamani)

1. Migrare la SecureTenantScope in toate modelele
2. Audit complet al folosirii withoutGlobalScopes()
3. Implementare token expiration pentru email verification
4. Adaugare logging pentru toate operatiunile admin

---

# PARTEA IV: CHECKLIST COMPLET

## Autentificare & Autorizare
- [ ] Middleware pe toate admin routes
- [ ] Validare API key efectiva
- [ ] Permission checks in admin operations
- [ ] Super admin login securizat sau dezactivat
- [ ] Rate limiting pe login/register

## Izolare Multi-Tenant
- [ ] SecureTenantScope implementat
- [ ] IDOR fix in OrderController
- [ ] IDOR fix in toate controllerele
- [ ] Tenant resolution doar prin hostname
- [ ] Eliminat _tenant_id din query params

## Marketplace
- [ ] MarketplaceScope implementat
- [ ] API keys hashed
- [ ] Izolare marketplace_client_id

## Securitate Generala
- [ ] DDoS protection activata
- [ ] Session encryption ON
- [ ] HTTPS fortat + HSTS
- [ ] Debug mode OFF
- [ ] CORS restrictionat

## Database
- [ ] Backup-uri automate
- [ ] Encryption in transit (SSL)
- [ ] Least privilege access

---

# CONCLUZII

Auditul extins a relevat **vulnerabilitati critice suplimentare** in componentele Tenant si Marketplace:

1. **Admin routes complet neprotejate** - expun toate datele fara autentificare
2. **IDOR pe multiple endpoint-uri** - permit acces cross-tenant
3. **TenantScope bypass** - permite accesarea datelor oricarui tenant
4. **Super admin login vulnerabil** - poate fi exploatat pentru acces total

**Recomandare:** Platforma NU ar trebui sa fie in productie pana cand vulnerabilitatile CRITICE nu sunt remediate.

---

*Raport generat: 2026-02-01 | Versiune: 2.0*
