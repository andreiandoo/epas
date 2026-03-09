# Plan: Cashless Mode Selection (QR vs NFC) per Festival Edition

## Context
Organizatorul alege per editie de festival modul cashless: **NFC** (bratara cu cip), **QR** (cod QR), sau **hybrid** (ambele).

- **NFC**: sold stocat pe chip, offline-first, terminalul citeste/scrie direct, sync ulterior
- **QR**: sold pe server, scanezi QR → lookup server → debitare server-side (necesita conexiune)
- **Hybrid**: ambele tipuri de bratari acceptate simultan

## Modificari

### 1. Migration: adaug `cashless_mode` pe `festival_editions`
**File:** `database/migrations/2026_03_09_210000_add_cashless_mode_to_festival_editions.php`

```php
$table->string('cashless_mode', 10)->default('nfc'); // nfc, qr, hybrid
```

### 2. Enum: `CashlessMode`
**File:** `app/Enums/CashlessMode.php`

```php
enum CashlessMode: string {
    case Nfc = 'nfc';
    case Qr = 'qr';
    case Hybrid = 'hybrid';
}
```
Cu metoda `label()` pentru UI.

### 3. Model: `FestivalEdition` — adaug campul + helpers
**File:** `app/Models/FestivalEdition.php`

- Adaug `cashless_mode` in `$fillable` si `$casts` (cast la CashlessMode enum)
- Adaug helpere: `isNfcMode()`, `isQrMode()`, `isHybridMode()`, `supportsNfc()`, `supportsQr()`

### 4. Controller: `EditionController` — accept `cashless_mode` la store/update
**File:** `app/Http/Controllers/Api/Festival/EditionController.php`

- Adaug `'cashless_mode' => 'in:nfc,qr,hybrid'` la validare in `store()` si `update()`

### 5. Migration: adaug campuri offline sync pe `wristband_transactions`
**File:** `database/migrations/2026_03_09_210001_add_offline_sync_fields_to_wristband_transactions.php`

```php
$table->string('sync_source', 15)->default('online');   // online, offline_sync
$table->string('offline_ref')->nullable()->unique();     // terminal-generated unique ref (idempotency key)
$table->timestamp('offline_transacted_at')->nullable();  // cand s-a intamplat efectiv pe terminal
$table->boolean('is_reconciled')->default(true);         // false daca balance mismatch intre chip si server
```

### 6. Model: `WristbandTransaction` — adaug campuri noi
**File:** `app/Models/WristbandTransaction.php`

- Adaug campurile noi in `$fillable` si `$casts`
- Adaug scope `scopeOffline($query)` si `scopeUnreconciled($query)`

### 7. Service: `NfcSyncService`
**File:** `app/Services/NfcSyncService.php`

Logica de reconciliere pentru NFC offline sync:
- `syncBatch(array $transactions, int $editionId, int $tenantId): array`
- Proceseaza fiecare tranzactie din batch:
  - Skip duplicate (pe baza `offline_ref`)
  - Creaza WristbandTransaction cu `sync_source = 'offline_sync'`
  - Creaza VendorSaleItems pentru fiecare linie
  - Actualizeaza `balance_cents` pe server (reconciliere)
  - Daca balance pe chip != balance pe server → `is_reconciled = false` + log warning
- Returneaza status per tranzactie: `synced`, `duplicate`, `wristband_not_found`, `error`

### 8. Controller: `WristbandController` — adaptez + adaug endpoint sync

**File:** `app/Http/Controllers/Api/Festival/WristbandController.php`

#### `import()`:
- Daca editia e `qr` → forteaza `wristband_type = 'qr'`
- Daca editia e `nfc` → forteaza `wristband_type = 'nfc'`
- Daca editia e `hybrid` → accepta orice tip din request
- Auto-genereaza QR payload la import daca tipul e `qr`

#### `show()` si `resolveQr()`:
- Includ `cashless_mode` din editie in raspuns, ca POS app-ul sa stie ce mod sa foloseasca

#### Nou: `syncOfflineTransactions()`:
- Endpoint pentru terminale NFC care trimit batch de tranzactii offline
- Valideaza ca editia suporta NFC (`nfc` sau `hybrid`)
- Delega la `NfcSyncService::syncBatch()`
- Returneaza status per tranzactie + balance reconciliat

### 9. Routes: adaug endpoint-ul de sync
**File:** `routes/api.php`

```php
Route::post('/wristbands/sync-offline', [WristbandController::class, 'syncOfflineTransactions'])
    ->name('api.festival.wristbands.sync-offline');
```

## Flow-uri rezultante

### QR Mode:
1. Organizatorul seteaza `cashless_mode = 'qr'` pe editie
2. Import bratari QR → `wristband_type = 'qr'`, auto-genereaza QR payload
3. Top-up: server-side (balance pe DB)
4. Charge: POS scaneaza QR → `resolveQr()` → `charge()` → server debiteaza instant
5. **Necesita conexiune permanenta la charge**

### NFC Mode:
1. Organizatorul seteaza `cashless_mode = 'nfc'` pe editie
2. Import bratari NFC → `wristband_type = 'nfc'`
3. Top-up: la terminal (scrie pe chip) + sync la server via `syncOfflineTransactions()`
4. Charge: terminal citeste chip → scade sold → scrie pe chip → sync batch ulterior
5. **Functioneaza offline, sync periodic**
6. Server-ul primeste batch via `syncOfflineTransactions()` → reconciliere

### Hybrid Mode:
1. Ambele flow-uri coexista
2. Fiecare bratara individuala are tipul ei (`nfc` sau `qr`)
3. POS-ul verifica tipul bratarii si aplica flow-ul corespunzator

## Ordine de implementare

1. Enum `CashlessMode`
2. Migration `cashless_mode` pe `festival_editions`
3. Update `FestivalEdition` model
4. Migration campuri offline sync pe `wristband_transactions`
5. Update `WristbandTransaction` model
6. Service `NfcSyncService`
7. Update `EditionController` (validare cashless_mode)
8. Update `WristbandController` (import logic + sync endpoint + show/resolveQr)
9. Routes
