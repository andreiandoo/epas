# Coduri Cupon

## Prezentare Scurtă

Supraalimentează-ți vânzările cu Coduri Cupon. Reducerile strategice conduc vânzările de bilete, răsplătesc clienții loiali și ocupă locurile pentru spectacolele subvândute. Sistemul nostru comprehensiv de cupoane îți oferă puterea și flexibilitatea de a rula promoții care livrează rezultate.

Creează campanii cu precizie. Generează mii de coduri unice în secunde, fiecare urmăribil individual. Sau creează coduri universale simple pe care toată lumea le poate folosi. Setează reduceri procentuale, sume fixe, livrare gratuită sau oferte cumpără-X-primești-Y care se potrivesc strategiei tale de promovare.

Controlează fiecare aspect al promoțiilor tale. Limitează de câte ori poate fi folosit fiecare cod - o dată per client, zece ori în total sau nelimitat. Setează cerințe minime de achiziție și plafoane maxime de reducere. Restricționează codurile la evenimente specifice, tipuri de bilete sau segmente de clienți.

Programarea este integrată. Setează date de început și sfârșit pentru campanii limitate în timp. Rulează vânzări flash, promoții early-bird sau speciale de sărbători fără intervenție manuală.

Validarea în timp real asigură că codurile sunt verificate instant la checkout. Codurile invalide, expirate sau deja folosite sunt respinse imediat cu mesaje clare. Clienții știu exact de ce un cod nu a funcționat.

Urmărește totul. Vezi care coduri sunt folosite, de cine și pentru ce achiziții. Analizează performanța campaniilor cu rapoarte detaliate. Exportă datele pentru analiză aprofundată sau scopuri contabile.

Codurile cupon transformă ideile de marketing în rezultate măsurabile. Rulează promoții mai inteligente care îți cresc afacerea.

---

## Descriere Detaliată

Microserviciul Coduri Cupon oferă un sistem complet de gestionare a codurilor promoționale pentru ticketing-ul de evenimente. Gestionează crearea campaniilor, generarea codurilor, validarea, urmărirea utilizărilor și analizele de performanță.

### Tipuri de Campanii

- **Reducere Procentuală**: Reduce prețurile cu un procent (ex: 20% reducere)
- **Sumă Fixă**: Scade o valoare fixă (ex: 10€ reducere)
- **Livrare Gratuită**: Renunță la taxele de livrare
- **Cumpără-X-Primești-Y**: Reduceri pachet (ex: cumpără 3 primești 1 gratis)

### Generare Coduri

Sistemul suportă formate multiple de coduri:
- **Alfanumeric**: Litere și numere mixte (ABC123)
- **Numeric**: Doar numere (123456)
- **Alfabetic**: Doar litere (VARA)
- **Personalizat**: Pattern-uri definite de utilizator cu prefix/sufix

Generarea în masă creează mii de coduri unice în secunde, fiecare cu propriul istoric de urmărire.

### Reguli de Validare

Codurile pot fi restricționate prin:
- Limite de utilizare (per utilizator, utilizări totale)
- Valoare minimă/maximă comandă
- Produse sau categorii specifice
- Segmente de clienți sau cumpărători pentru prima dată
- Intervale de date și ore
- Restricții geografice

### Urmărire Utilizări

Fiecare utilizare este înregistrată cu:
- Informații client
- Detalii comandă
- Sumă reducere aplicată
- Timestamp și metadate

Aceasta permite atribuire detaliată și calcul ROI pentru fiecare campanie.

---

## Funcționalități

### Gestionare Campanii
- Gestionare campanii cu programare
- Generare coduri în masă (mii de coduri)
- Formate cod personalizate (alfanumeric, numeric, personalizat)
- Prefixe și sufixe cod
- Activare/dezactivare campanii

### Tipuri de Reduceri
- Reduceri procentuale
- Reduceri sumă fixă
- Promoții livrare gratuită
- Oferte cumpără-X-primești-Y
- Reguli reduceri combinabile

### Controale Utilizare
- Limite utilizare per utilizator
- Limite utilizare totale
- Opțiune doar prima achiziție
- Cerințe minime de achiziție
- Plafoane maxime reducere

### Targetare
- Targetare produse/categorii
- Excluderi produse/categorii
- Coduri specifice eveniment
- Restricții segment clienți
- Targetare geografică

### Validare și Utilizare
- Validare cod în timp real
- Urmărire utilizări
- Jurnal încercări validare
- Suport reversare utilizare
- Prevenire utilizare duplicată

### Analize și Export
- Analize și raportare campanii
- Export coduri (CSV/JSON)
- Atribuire coduri utilizatorilor specifici
- Dashboard-uri performanță

---

## Cazuri de Utilizare

### Reduceri Early Bird
Răsplătește clienții care rezervă devreme cu reduceri procentuale. Conduce impulsul de vânzări timpuriu și prezice mai bine participarea.

### Vânzări de Ultim Moment
Ocupă locurile goale cu coduri de vânzare flash distribuite prin email sau social media. Urgența limitată în timp conduce decizii rapide.

### Coduri Parteneri și Afiliați
Creează coduri unice pentru parteneri, influenceri sau afiliați. Urmărește exact care parteneriate conduc vânzări.

### Loialitate Clienți
Răsplătește clienții repetiți cu coduri exclusive. Trimite reduceri personalizate bazate pe istoricul de achiziții.

### Vânzări Corporate
Oferă coduri specifice companiei pentru clienți B2B. Urmărește achizițiile corporate și oferă reduceri de volum.

### Campanii Social Media
Creează coduri partajabile pentru promoții sociale. Urmărește care platforme conduc cele mai multe conversii.

---

## Documentație Tehnică

### Prezentare Generală

Microserviciul Coduri Cupon gestionează campaniile de coduri promoționale incluzând crearea, generarea, validarea, utilizarea și analizele.

### Schema Bazei de Date

| Tabel | Descriere |
|-------|-----------|
| `coupon_campaigns` | Definiții campanii |
| `coupon_codes` | Coduri individuale |
| `coupon_redemptions` | Înregistrări utilizare |
| `coupon_generation_jobs` | Taskuri generare în masă |
| `coupon_validation_attempts` | Jurnal validare |

### Endpoint-uri API

#### Gestionare Campanii

```
GET /api/coupons/campaigns
```
Listează toate campaniile.

```
POST /api/coupons/campaigns
```
Creează campanie nouă.

```
GET /api/coupons/campaigns/{id}
```
Obține detalii campanie.

```
PUT /api/coupons/campaigns/{id}
```
Actualizează campanie.

```
DELETE /api/coupons/campaigns/{id}
```
Șterge campanie.

```
POST /api/coupons/campaigns/{id}/activate
```
Activează campanie.

#### Generare Coduri

```
POST /api/coupons/campaigns/{id}/generate
```
Generează coduri pentru campanie.

**Cerere:**
```json
{
  "quantity": 1000,
  "format": "alphanumeric",
  "prefix": "VARA",
  "length": 6
}
```

```
GET /api/coupons/campaigns/{id}/codes
```
Listează codurile din campanie.

#### Validare și Utilizare

```
POST /api/coupons/validate
```
Validează codul la checkout.

**Cerere:**
```json
{
  "code": "VARA20ABC",
  "cart": {
    "items": [...],
    "total": 150.00
  },
  "customer_id": "cust_123"
}
```

```
POST /api/coupons/redeem
```
Înregistrează utilizarea codului.

```
POST /api/coupons/reverse
```
Reversează utilizarea (returnare).

#### Analize

```
GET /api/coupons/campaigns/{id}/stats
```
Statistici performanță campanie.

```
GET /api/coupons/redemptions
```
Listează toate utilizările.

```
GET /api/coupons/export/{campaignId}
```
Exportă coduri în CSV/JSON.

### Structură Campanie

```json
{
  "id": "camp_123",
  "name": "Reduceri de Vară 2025",
  "type": "percentage",
  "value": 20,
  "rules": {
    "min_purchase": 50.00,
    "max_discount": 100.00,
    "usage_limit": 1000,
    "per_user_limit": 1,
    "first_purchase_only": false,
    "valid_from": "2025-06-01T00:00:00Z",
    "valid_until": "2025-08-31T23:59:59Z",
    "applicable_events": [1, 2, 3],
    "excluded_categories": ["vip"]
  },
  "code_format": {
    "type": "alphanumeric",
    "prefix": "VARA",
    "length": 6
  },
  "status": "active",
  "stats": {
    "codes_generated": 1000,
    "codes_used": 245,
    "total_discount": 4900.00,
    "revenue_generated": 24500.00
  }
}
```

### Răspuns Validare

```json
{
  "valid": true,
  "code": "VARA20ABC",
  "discount": {
    "type": "percentage",
    "value": 20,
    "amount": 30.00
  },
  "message": "Cod aplicat cu succes",
  "restrictions": {
    "remaining_uses": 755,
    "expires_at": "2025-08-31T23:59:59Z"
  }
}
```

### Configurare

```php
'coupons' => [
    'discount_types' => ['percentage', 'fixed', 'free_shipping', 'buy_x_get_y'],
    'code_formats' => ['alphanumeric', 'numeric', 'alphabetic', 'custom'],
    'max_codes_per_batch' => 10000,
    'validation' => [
        'log_attempts' => true,
        'rate_limit' => 100, // per minut
    ],
]
```

### Exemplu de Integrare

```php
use App\Services\PromoCodes\PromoCodeService;
use App\Services\PromoCodes\PromoCodeValidator;

$service = app(PromoCodeService::class);
$validator = app(PromoCodeValidator::class);

// Validează cod
$result = $validator->validate($code, $cart, $customer);

if ($result->isValid()) {
    // Aplică reducerea la coș
    $cart->applyDiscount($result->getDiscount());

    // Înregistrează utilizarea după plată
    $service->redeem($code, $order);
}
```
