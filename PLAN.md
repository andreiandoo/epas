# Plan: Introducere Tipuri de Clienti Tenant - Artist, Agency, Theater

## Context

Tixello opereaza actualmente cu un model de tenant generic. Campul `type` (single|small|medium|large|premium) e bazat pe dimensiune, iar `organizer_type` (agency|promoter|venue|artist|ngo|other) e folosit doar la onboarding fara impact functional. Trebuie sa introducem **tipuri de tenant cu impact functional real** care activeaza/dezactiveaza feature-uri, modele de date si flow-uri specifice.

---

## Arhitectura Propusa

### 1. Enum `TenantArtistType` (coloana noua `artist_type` pe tenants)

Valori: `tenant-artist`, `agency`, `theater`

Aceasta coloana determina **comportamentul functional** al tenant-ului. Campul existent `type` ramane pentru dimensiunea/planul de pret. Un tenant poate avea `type=medium` si `artist_type=theater`.

### 2. Sub-tipuri Theater

`theater_subtype` (coloana pe tenants, nullable): `theater`, `opera`, `philharmonic`

---

## Modificari Database (Migrari)

### Migration 1: `add_artist_type_to_tenants_table`
- Adaugare coloana `artist_type` (enum: 'tenant-artist', 'agency', 'theater', nullable, default null)
- Adaugare coloana `theater_subtype` (enum: 'theater', 'opera', 'philharmonic', nullable)
- Adaugare coloana `artist_type_settings` (json, nullable) - setari specifice tipului

### Migration 2: `create_seasons_table`
- `id`, `tenant_id` (FK), `name` (json, translatable), `slug`
- `description` (json, translatable)
- `start_date`, `end_date`
- `status` (enum: 'planning', 'active', 'completed', 'archived')
- `poster_url`, `settings` (json)
- `is_subscription_enabled` (bool, default false)
- `timestamps`

### Migration 3: `create_season_subscriptions_table`
- `id`, `season_id` (FK), `customer_id` (FK), `tenant_id` (FK)
- `name`, `type` (enum: 'full', 'partial', 'custom')
- `seat_label` (string, nullable) - locul rezervat pe toata stagiunea
- `section_id` (FK nullable) - sectiunea din seating layout
- `price_cents` (integer)
- `currency` (string, 3)
- `status` (enum: 'active', 'pending', 'expired', 'cancelled')
- `events_included` (json) - array de event_ids incluse
- `valid_from`, `valid_until`
- `auto_renew` (bool, default false)
- `meta` (json)
- `timestamps`

### Migration 4: `add_season_id_to_events_table`
- Adaugare coloana `season_id` (FK nullable) pe `events` - legatura event la stagiune
- Adaugare coloana `repertoire_id` (FK nullable) - pentru spectacole din repertoriu (reutilizabile)

### Migration 5: `create_repertoire_table`
- `id`, `tenant_id` (FK)
- `title` (json, translatable), `slug`
- `description` (json, translatable)
- `duration_minutes` (integer, nullable)
- `genre` (string, nullable)
- `director` (string, nullable), `choreographer`, `conductor` (pentru opera/filarmonica)
- `poster_url`, `meta` (json)
- `is_active` (bool, default true)
- `timestamps`

### Migration 6: `create_tenant_artists_table`
- `id`, `tenant_id` (FK)
- `artist_id` (FK nullable) - link la artistul global (optional)
- `name`, `slug`
- `role` (string) - ex: actor, solist, dirijor, coregraf, muzician
- `bio` (json, translatable)
- `photo_url`, `phone`, `email`
- `contract_start`, `contract_end`
- `is_resident` (bool) - artist permanent al institutiei
- `meta` (json)
- `status` (enum: 'active', 'inactive')
- `timestamps`

### Migration 7: `create_agency_artists_table`
- `id`, `tenant_id` (FK) - agentia
- `artist_id` (FK) - link la artistul global
- `contract_type` (enum: 'exclusive', 'non_exclusive', 'management', 'booking')
- `contract_start`, `contract_end`
- `commission_rate` (decimal)
- `territory` (json) - tari/regiuni acoperite
- `notes` (text, nullable)
- `status` (enum: 'active', 'inactive', 'pending')
- `timestamps`

### Migration 8: `create_merch_products_table` (pentru tenant-artist)
- `id`, `tenant_id` (FK)
- `name` (json, translatable), `slug`
- `description` (json, translatable)
- `sku`, `price_cents`, `currency`
- `stock_quantity`, `stock_status` (enum: 'in_stock', 'out_of_stock', 'preorder')
- `category` (string) - tricouri, viniluri, postere, etc.
- `images` (json)
- `weight_grams` (int, nullable)
- `is_digital` (bool, default false)
- `digital_file_url` (string, nullable)
- `is_active` (bool, default true)
- `meta` (json)
- `timestamps`

### Migration 9: `create_merch_order_items_table`
- `id`, `order_id` (FK), `merch_product_id` (FK)
- `quantity`, `unit_price_cents`, `total_price_cents`
- `variant` (json, nullable) - marime, culoare, etc.
- `timestamps`

---

## Modele Eloquent Noi

1. **Season** - stagiuni (theater)
2. **SeasonSubscription** - abonamente la stagiuni
3. **Repertoire** - spectacole din repertoriu (theater)
4. **TenantArtist** - artisti apartinand unui tenant (theater/agency)
5. **AgencyArtist** - relatie agentie-artist cu detalii contract
6. **MerchProduct** - produse merchandise (tenant-artist)
7. **MerchOrderItem** - items merch in comenzi

---

## Modificari pe Modele Existente

### Tenant.php
- Adaugare `artist_type` si `theater_subtype` la fillable/casts
- Metode helper: `isTheater()`, `isAgency()`, `isTenantArtist()`
- Relatii noi: `seasons()`, `repertoire()`, `tenantArtists()`, `agencyArtists()`, `merchProducts()`
- `getAvailableMicroservices()` - filtreaza microservicii relevante per tip
- `getDefaultMicroservices()` - microservicii activate implicit per tip

### Event.php
- Adaugare relatie `season()` (BelongsTo)
- Adaugare relatie `repertoire()` (BelongsTo)
- `performances()` relatie (HasMany) - deja exista ca model, dar trebuie consolidata

### Performance.php
- Extindere cu `ticket_types` (override per-performance)
- Relatie cu `season`
- Status-uri aditionale: 'scheduled', 'on_sale', 'sold_out', 'cancelled', 'completed'

### Order.php
- Adaugare relatie `merchOrderItems()` (HasMany)
- Support pentru comenzi mixte (bilete + merch)

---

## Logica per Tip de Tenant

### Tenant-Artist
- Un singur artist/formatie
- Vinde bilete la propriile evenimente
- Poate vinde merch (microserviciul `shop`)
- Profil de artist direct pe tenant
- Toate microserviciile disponibile
- **Microservicii recomandate**: shop (merch), analytics, crm, affiliate-tracking, social integrations

### Agency
- Reprezinta mai multi artisti
- Dashboard cu overview per artist
- Management contracte artisti
- Poate crea evenimente pentru oricare din artistii sai
- Rapoarte per artist
- **Microservicii recomandate**: crm, analytics, invoicing (efactura), accounting

### Theater (+ Opera, Filarmonica)
- O singura locatie fizica (venue propriu)
- Seating layout fix al salii
- Stagiuni cu spectacole grupate
- Repertoriu persistent (acelasi spectacol, multiple reprezentatii)
- Abonamente pe stagiune cu loc fix rezervat
- Artisti proprii (actori, solisti, etc.)
- **Microservicii recomandate**: analytics, crm, door-sales, ticket-customizer, efactura

---

## Fisiere de Creat/Modificat

### Noi:
1. `database/migrations/YYYY_MM_DD_000001_add_artist_type_to_tenants_table.php`
2. `database/migrations/YYYY_MM_DD_000002_create_seasons_table.php`
3. `database/migrations/YYYY_MM_DD_000003_create_season_subscriptions_table.php`
4. `database/migrations/YYYY_MM_DD_000004_add_season_id_to_events_table.php`
5. `database/migrations/YYYY_MM_DD_000005_create_repertoire_table.php`
6. `database/migrations/YYYY_MM_DD_000006_create_tenant_artists_table.php`
7. `database/migrations/YYYY_MM_DD_000007_create_agency_artists_table.php`
8. `database/migrations/YYYY_MM_DD_000008_create_merch_products_table.php`
9. `database/migrations/YYYY_MM_DD_000009_create_merch_order_items_table.php`
10. `app/Models/Season.php`
11. `app/Models/SeasonSubscription.php`
12. `app/Models/Repertoire.php`
13. `app/Models/TenantArtist.php`
14. `app/Models/AgencyArtist.php`
15. `app/Models/MerchProduct.php`
16. `app/Models/MerchOrderItem.php`
17. `app/Enums/TenantArtistType.php`
18. `app/Enums/TheaterSubtype.php`

### Modificate:
1. `app/Models/Tenant.php` - adaugare relatii, helpers, fillable/casts
2. `app/Models/Event.php` - adaugare season_id, repertoire_id
3. `app/Models/Performance.php` - extindere functionalitate
4. `app/Models/Order.php` - suport merch

---

## Ordine de Implementare

1. Enums (TenantArtistType, TheaterSubtype)
2. Migration 1 (artist_type pe tenants) + update Tenant.php
3. Migrations 2-5 (seasons, repertoire) + modele Season, Repertoire
4. Migration 6 (tenant_artists) + model TenantArtist
5. Migration 7 (agency_artists) + model AgencyArtist
6. Migrations 8-9 (merch) + modele MerchProduct, MerchOrderItem
7. Update Event.php, Performance.php, Order.php
8. Migration 4 (season_id pe events)
