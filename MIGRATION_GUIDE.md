# AmBilet → Tixello Migration Guide

## Overview

Migrare incrementală de date din AmBilet (WordPress/WooCommerce/Tickera) în Tixello (Laravel).

- **Marketplace client ID**: 1
- **Fallback organizer ID**: 54 (ambilet.ro@gmail.com)
- **CSV storage**: `epas/resources/marketplaces/ambilet/old_database/2026/incremental_MMDD/`
- **Map files**: `storage/app/import_maps/` (events_map.json, orders_map.json, etc.)

---

## WordPress Terminology & Meta Keys

### Post Types
| WP Post Type | Tixello Entity | Notes |
|---|---|---|
| `tc_events` | events | Evenimente |
| `product` | ticket_types | Tipuri de bilete (WooCommerce products) |
| `shop_order` | orders | Comenzi |
| `tc_tickets_instances` | tickets | Bilete individuale. **NU** `tc_tickets`! |

### Meta Keys per Post Type

**tc_events (evenimente):**
| Meta Key | Descriere | Notes |
|---|---|---|
| `despre_eveniment` | Descriere eveniment (HTML) | **NU** `post_content`! |
| `imagine_principala` | Hero image (ID attachment WP) | Prima opțiune pentru hero |
| `poster` | Poster image (ID attachment WP) | |
| `_thumbnail_id` | Featured image WP (fallback hero) | Standard WP |
| `event_date_time` | Data+ora start: "2026-08-29 20:00" | |
| `event_end_date_time` | Data+ora final: "2026-08-29 22:00" | |
| `data_eveniment_start` | Data start range: "20260821" (YYYYMMDD) | |
| `data_eveniment_end` | Data end range: "20260829" (YYYYMMDD) | |
| `expirare_eveniment` | Data expirare: "2026-08-29 20:00" | Past = archived |
| `organizer_wp_user_id` | WP user ID al organizatorului | |
| `event_location` | "Venue Name, City" | |
| `sold_out` | 0/1 | |
| `amanat` | 0/1 (postponed) | |
| `anulat` | 0/1 (cancelled) | |
| `bilete_doar_la_intrare` | 0/1 (door sales only) | |
| `website` | URL website | |
| `facebook` | URL Facebook | |
| `ticket_terms` | Termeni bilete (text) | |
| `duration_type` | single_day/range | |

**product (ticket types):**
| Meta Key | Descriere | Notes |
|---|---|---|
| `_event_name` | wp_event_id (link la eveniment) | **NU** `event_name`! (cu underscore) |
| `_price` | Preț (float, RON) | |
| `_stock` | Stoc | NULL/empty = unlimited |
| `_manage_stock` | "no" la toate | Nu contează |
| `_ticket_availability` | "open_ended" / "range" | Fereastra de VALIDITATE/SCANARE, **NU** vânzare |
| `subtitlu_bilet` | Descriere tip bilet | → ticket_types.description |
| `product-fee-amount` | Comision: "6%" sau "2,5" | → commission_type/rate/fixed |
| `minimum_allowed_quantity` | Min per tranzacție | → min_per_order |
| `maximum_allowed_quantity` | Max per tranzacție | → max_per_order |
| `bilete_eveniment` (pe eveniment) | Array serializat PHP cu wp_product_ids active | Controlează asocierea, NU disponibilitatea |

**tc_tickets_instances (bilete):**
| Meta Key | Descriere | Notes |
|---|---|---|
| `event_id` | wp_event_id | |
| `ticket_type_id` | wp_product_id | |
| `ticket_code` | Cod bilet unic | |
| `item_id` | wc_order_item_id | **NU** `wc_order_item_id`! Linking to orders |
| `tc_checkins` | PHP serialized checkin data | |
| `seat_label` | Label loc (ex: "A-12") | |

**shop_order (comenzi):**
| Meta Key | Descriere |
|---|---|
| `_billing_email` | Email client |
| `_billing_first_name` | Prenume |
| `_billing_last_name` | Nume |
| `_billing_phone` | Telefon |
| `_order_total` | Total (float, RON) |
| `_payment_method` | Metodă plată |
| `_date_paid` / `_paid_date` | Data plății (poate lipsi) |
| `_customer_ip_address` | IP client |
| `_wc_order_attribution_device_type` | Mobile/Desktop |
| `_wc_order_attribution_source_type` | referral/typein/organic |
| `_wc_order_attribution_utm_source` | UTM source |
| `_wc_order_attribution_utm_medium` | UTM medium |
| `_wc_order_attribution_utm_content` | UTM content |
| `_wc_order_attribution_referrer` | Referrer URL |
| `_wc_order_attribution_session_entry` | Landing page URL |
| `_wc_order_attribution_session_count` | Nr sesiuni |
| `_wc_order_attribution_session_pages` | Nr pagini |
| `_wc_order_attribution_user_agent` | User agent |

---

## CSV Formats per Command

### `import:ambilet-events` → inc_events_new.csv
```
wp_event_id, name, wp_slug, post_status, created_at, organizer_wp_user_id, starts_at, ends_at, location, image_url, ticket_terms, description, duration_type, organizer_email
```

### `import:ambilet-ticket-types` → inc_ticket_types.csv
```
wp_product_id, name, wp_event_id, price, stock_qty, sold_count
```
> `stock_qty` = stoc CURENT rămas (WP `_stock`), `sold_count` = SUM(_qty) din comenzi plătite/procesate (`wc-completed/processing/on-hold`). `quota_total = stock_qty + sold_count`, `quota_sold = sold_count`.

### `import:ambilet-orders` → inc_orders.csv
```
wp_order_id, order_status, created_at, order_total, payment_method, customer_email, billing_first_name, billing_last_name, billing_phone
```
> `paid_at` e opțional — comanda folosește `created_at` ca fallback.

### `import:ambilet-tickets` → inc_ticket_instances.csv
```
wp_ticket_id, created_at, post_status, wp_event_id, wp_product_id, ticket_code, wc_order_item_id, checkin_data
```
> Necesită `order_item_map.csv` în același folder (format: `order_item_id, wp_order_id`)

### `fix:ambilet-event-fields` → inc_event_fields_fix.csv
```
wp_event_id, post_status, description, hero_image_url, poster_url, featured_image_url, sold_out, website, facebook, amanat, anulat, bilete_doar_la_intrare
```

### `fix:ambilet-event-dates` → inc_event_dates_fix.csv
```
wp_event_id, event_date_time, event_end_date_time, data_eveniment_start, data_eveniment_end, expirare_eveniment
```

### `fix:ambilet-orphan-tickets` → inc_ticket_order_map.csv
```
wp_ticket_id, wp_order_id
```

### `import:ambilet-order-attribution` → inc_order_attribution.csv
```
wp_order_id, device_type, source_type, utm_source, utm_medium, utm_content, referrer, landing_page, session_count, session_pages, ip_address, user_agent
```

---

## Status Mappings

### WP post_status → Tixello Event
| WP post_status | Tixello status | is_published |
|---|---|---|
| publish | published | true |
| draft | draft | false |
| future | draft | false |
| private | draft | false |
| pending | draft | false |

> **Archived**: Setat de `fix:ambilet-event-dates` când `expirare_eveniment < now()`. Păstrează `is_published = 1`.

### WC Order Status → Tixello
| WC Status | order status | payment_status | ticket status |
|---|---|---|---|
| wc-completed | completed | paid | valid |
| wc-processing | pending | pending | cancelled |
| wc-on-hold | pending | pending | cancelled |
| wc-failed | failed | failed | cancelled |
| wc-cancelled | cancelled | failed | cancelled |
| wc-refunded | refunded | refunded | void |
| wc-pending | pending | pending | cancelled |

---

## Tixello Field Conventions

- **Descriptions**: JSON `{"ro": "<p>HTML content</p>"}` (Translatable trait)
- **Ticket terms**: JSON `{"ro": "text"}` sau NULL
- **Images**: Path relativ pe disk public: `events/hero/{md5}.webp`, `events/posters/{md5}.webp`
- **quota_total**: `-1` = nelimitat (WP `_stock` gol/NULL), `0` = epuizat/sold out (stoc zero), `>0` = cantitate fixă (stoc inițial = stock + sold)
- **Order number**: `AMB-{wp_order_id}`
- **Event series**: `AMB-{wp_event_id}`

---

## SQL Queries pentru Export din phpMyAdmin

Înlocuiește `DATE_START` și `DATE_END` cu intervalul dorit (ex: `'2026-03-23 23:59:59'`).

### 1. Evenimente NOI
```sql
SELECT
    p.ID AS wp_event_id,
    p.post_title AS name,
    p.post_name AS wp_slug,
    p.post_status,
    p.post_date AS created_at,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'organizer_wp_user_id' LIMIT 1), '0') AS organizer_wp_user_id,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'event_date_time' LIMIT 1), '') AS starts_at,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'event_end_date_time' LIMIT 1), '') AS ends_at,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'event_location' LIMIT 1), '') AS location,
    '' AS image_url, '' AS ticket_terms, '' AS description, 'single_day' AS duration_type,
    COALESCE((SELECT u.user_email FROM wpyt_users u WHERE u.ID = (SELECT pm2.meta_value FROM wpyt_postmeta pm2 WHERE pm2.post_id = p.ID AND pm2.meta_key = 'organizer_wp_user_id' LIMIT 1)), '') AS organizer_email
FROM wpyt_posts p
WHERE p.post_type = 'tc_events'
  AND p.post_date > 'DATE_START' AND p.post_date <= 'DATE_END'
ORDER BY p.ID
```

### 2. Event Fields Fix (NEW)
```sql
SELECT
    p.ID AS wp_event_id, p.post_status,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'despre_eveniment' LIMIT 1), '') AS description,
    COALESCE((SELECT wp2.guid FROM wpyt_posts wp2 WHERE wp2.ID = (SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'imagine_principala' LIMIT 1)), '') AS hero_image_url,
    COALESCE((SELECT wp2.guid FROM wpyt_posts wp2 WHERE wp2.ID = (SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'poster' LIMIT 1)), '') AS poster_url,
    COALESCE((SELECT wp2.guid FROM wpyt_posts wp2 WHERE wp2.ID = (SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id' LIMIT 1)), '') AS featured_image_url,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'sold_out' LIMIT 1), '0') AS sold_out,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'website' LIMIT 1), '') AS website,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'facebook' LIMIT 1), '') AS facebook,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'amanat' LIMIT 1), '0') AS amanat,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'anulat' LIMIT 1), '0') AS anulat,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'bilete_doar_la_intrare' LIMIT 1), '0') AS bilete_doar_la_intrare
FROM wpyt_posts p
WHERE p.post_type = 'tc_events'
  AND p.post_date > 'DATE_START' AND p.post_date <= 'DATE_END'
ORDER BY p.ID
```

### 3. Event Fields Fix (MODIFIED — evenimente existente modificate)
Aceeași structură ca #2, dar:
```sql
WHERE p.post_type = 'tc_events'
  AND p.post_date <= 'DATE_START'
  AND p.post_modified > 'DATE_START' AND p.post_modified <= 'DATE_END'
```

### 4. Event Dates Fix (NEW)
```sql
SELECT
    p.ID AS wp_event_id,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'event_date_time' LIMIT 1), '') AS event_date_time,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'event_end_date_time' LIMIT 1), '') AS event_end_date_time,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'data_eveniment_start' LIMIT 1), '') AS data_eveniment_start,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'data_eveniment_end' LIMIT 1), '') AS data_eveniment_end,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'expirare_eveniment' LIMIT 1), '') AS expirare_eveniment
FROM wpyt_posts p
WHERE p.post_type = 'tc_events'
  AND p.post_date > 'DATE_START' AND p.post_date <= 'DATE_END'
ORDER BY p.ID
```

### 5. Event Dates Fix (MODIFIED)
Aceeași structură ca #4, dar cu WHERE din #3.

### 6. Ticket Types (NEW + MODIFIED)
```sql
SELECT
    p.ID AS wp_product_id,
    p.post_title AS name,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = '_event_name' LIMIT 1), '') AS wp_event_id,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = '_price' LIMIT 1), '0') AS price,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = '_stock' LIMIT 1), '') AS stock_qty,
    (SELECT COALESCE(SUM(
         CAST((SELECT oim2.meta_value FROM wpyt_woocommerce_order_itemmeta oim2
               WHERE oim2.order_item_id = oim.order_item_id AND oim2.meta_key = '_qty' LIMIT 1)
              AS UNSIGNED)), 0)
     FROM wpyt_woocommerce_order_itemmeta oim
     JOIN wpyt_woocommerce_order_items oi ON oi.order_item_id = oim.order_item_id
     JOIN wpyt_posts o ON o.ID = oi.order_id
     WHERE oim.meta_key = '_product_id' AND oim.meta_value = p.ID
       AND o.post_type = 'shop_order' AND o.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
    ) AS sold_count
FROM wpyt_posts p
WHERE p.post_type = 'product'
  AND p.post_status IN ('publish', 'draft', 'private', 'future')
  AND GREATEST(p.post_date, p.post_modified) > 'DATE_START'
  AND GREATEST(p.post_date, p.post_modified) <= 'DATE_END'
ORDER BY p.ID
```
> `stock_qty` = stoc curent rămas din WP. `sold_count` = SUM(_qty) din comenzi `wc-completed/processing/on-hold` (nu COUNT — un order item poate avea qty>1). La import: `quota_total = stock_qty + sold_count`, `quota_sold = sold_count`.

### 7. Comenzi NOI
```sql
SELECT
    p.ID AS wp_order_id,
    p.post_status AS order_status,
    p.post_date AS created_at,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = '_order_total' LIMIT 1), '0') AS order_total,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = '_payment_method' LIMIT 1), '') AS payment_method,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = '_billing_email' LIMIT 1), '') AS customer_email,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = '_billing_first_name' LIMIT 1), '') AS billing_first_name,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = '_billing_last_name' LIMIT 1), '') AS billing_last_name,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = '_billing_phone' LIMIT 1), '') AS billing_phone
FROM wpyt_posts p
WHERE p.post_type = 'shop_order'
  AND p.post_date > 'DATE_START' AND p.post_date <= 'DATE_END'
ORDER BY p.ID
```

### 8. Order Item Map
```sql
SELECT
    oi.order_item_id,
    oi.order_id AS wp_order_id
FROM wpyt_woocommerce_order_items oi
JOIN wpyt_posts p ON p.ID = oi.order_id
WHERE p.post_type = 'shop_order'
  AND p.post_date > 'DATE_START' AND p.post_date <= 'DATE_END'
  AND oi.order_item_type = 'line_item'
ORDER BY oi.order_item_id
```

### 9. Bilete NOI
```sql
SELECT
    p.ID AS wp_ticket_id,
    p.post_date AS created_at,
    p.post_status,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'event_id' LIMIT 1), '') AS wp_event_id,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'ticket_type_id' LIMIT 1), '') AS wp_product_id,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'ticket_code' LIMIT 1), '') AS ticket_code,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'item_id' LIMIT 1), '') AS wc_order_item_id,
    COALESCE((SELECT pm.meta_value FROM wpyt_postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = 'tc_checkins' LIMIT 1), '') AS checkin_data
FROM wpyt_posts p
WHERE p.post_type = 'tc_tickets_instances'
  AND p.post_date > 'DATE_START' AND p.post_date <= 'DATE_END'
ORDER BY p.ID
```

### 10. Order Attribution
```sql
SELECT
    p.ID AS wp_order_id,
    MAX(CASE WHEN pm.meta_key = '_wc_order_attribution_device_type' THEN pm.meta_value END) AS device_type,
    MAX(CASE WHEN pm.meta_key = '_wc_order_attribution_source_type' THEN pm.meta_value END) AS source_type,
    MAX(CASE WHEN pm.meta_key = '_wc_order_attribution_utm_source' THEN pm.meta_value END) AS utm_source,
    MAX(CASE WHEN pm.meta_key = '_wc_order_attribution_utm_medium' THEN pm.meta_value END) AS utm_medium,
    MAX(CASE WHEN pm.meta_key = '_wc_order_attribution_utm_content' THEN pm.meta_value END) AS utm_content,
    MAX(CASE WHEN pm.meta_key = '_wc_order_attribution_referrer' THEN pm.meta_value END) AS referrer,
    MAX(CASE WHEN pm.meta_key = '_wc_order_attribution_session_entry' THEN pm.meta_value END) AS landing_page,
    MAX(CASE WHEN pm.meta_key = '_wc_order_attribution_session_count' THEN pm.meta_value END) AS session_count,
    MAX(CASE WHEN pm.meta_key = '_wc_order_attribution_session_pages' THEN pm.meta_value END) AS session_pages,
    MAX(CASE WHEN pm.meta_key = '_customer_ip_address' THEN pm.meta_value END) AS ip_address,
    MAX(CASE WHEN pm.meta_key = '_wc_order_attribution_user_agent' THEN pm.meta_value END) AS user_agent
FROM wpyt_posts p
JOIN wpyt_postmeta pm ON pm.post_id = p.ID
WHERE p.post_type = 'shop_order'
  AND p.post_date > 'DATE_START' AND p.post_date <= 'DATE_END'
  AND pm.meta_key IN ('_wc_order_attribution_device_type','_wc_order_attribution_source_type','_wc_order_attribution_utm_source','_wc_order_attribution_utm_medium','_wc_order_attribution_utm_content','_wc_order_attribution_referrer','_wc_order_attribution_session_entry','_wc_order_attribution_session_count','_wc_order_attribution_session_pages','_customer_ip_address','_wc_order_attribution_user_agent')
GROUP BY p.ID ORDER BY p.ID
```

### 11. Ticket-Order Map
```sql
SELECT
    t.ID AS wp_ticket_id,
    oi.order_id AS wp_order_id
FROM wpyt_posts t
JOIN wpyt_postmeta pm ON pm.post_id = t.ID AND pm.meta_key = 'item_id'
JOIN wpyt_woocommerce_order_items oi ON oi.order_item_id = pm.meta_value
WHERE t.post_type = 'tc_tickets_instances'
  AND t.post_date > 'DATE_START' AND t.post_date <= 'DATE_END'
ORDER BY t.ID
```

---

## Ordinea de Execuție pe Server

```bash
# Variabile
INC="resources/marketplaces/ambilet/old_database/2026/incremental_MMDD"

# 0. Backup map files
cp storage/app/import_maps/events_map.json storage/app/import_maps/events_map_backup.json
cp storage/app/import_maps/orders_map.json storage/app/import_maps/orders_map_backup.json
cp storage/app/import_maps/ticket_types_map.json storage/app/import_maps/ticket_types_map_backup.json
cp storage/app/import_maps/tickets_map.json storage/app/import_maps/tickets_map_backup.json

# 1. Import evenimente noi
php artisan import:ambilet-events $INC/inc_events_new.csv --fallback-organizer=54

# 2. Import ticket types (noi — cele modificate sunt skip-uite automat)
php artisan import:ambilet-ticket-types $INC/inc_ticket_types.csv

# 3. Import comenzi noi
php artisan import:ambilet-orders $INC/inc_orders.csv

# 4. Import bilete noi (copiază order_item_map cu numele corect)
cp $INC/inc_order_item_map.csv $INC/order_item_map.csv
php artisan import:ambilet-tickets $INC/inc_ticket_instances.csv

# 5. Fix order statuses (OBLIGATORIU — fără asta toate comenzile sunt completed/paid)
php artisan fix:ambilet-order-statuses $INC/inc_orders.csv
# Remapează: wc-cancelled→cancelled, wc-failed→failed, wc-processing→pending
# Actualizează și statusul biletelor asociate (cancelled/void)

# 6. Fix event fields — ATENȚIE la ordinea --skip-images:
#    NEW events: FĂRĂ --skip-images (au nevoie de URL-uri setate pentru download)
#    MODIFIED events: CU --skip-images (să nu suprascrie imaginile locale existente)
php artisan fix:ambilet-event-fields $INC/inc_event_fields_fix.csv
php artisan fix:ambilet-event-fields $INC/inc_event_fields_fix_modified.csv --skip-images

# 7. Fix event dates (NEW apoi MODIFIED)
php artisan fix:ambilet-event-dates $INC/inc_event_dates_fix.csv
php artisan fix:ambilet-event-dates $INC/inc_event_dates_fix_modified.csv

# 8. Download imagini + fix paths
#    TREBUIE rulat DUPĂ fix:ambilet-event-fields (care setează URL-urile externe)
php artisan fix:ambilet-event-images
php artisan fix:ambilet-event-images --fix-paths

# 9. Activare ticket types (quota se setează corect la import: 0=epuizat, >0=fix, -1=nelimitat)
php artisan tinker --execute='$ids=DB::table("events")->where("marketplace_client_id",1)->pluck("id");echo "Activated: ".DB::table("ticket_types")->whereIn("event_id",$ids)->where("status","hidden")->update(["status"=>"active","updated_at"=>now()]).PHP_EOL;'

# 10. Link orphan tickets
php artisan fix:ambilet-orphan-tickets $INC/inc_ticket_order_map.csv

# 11. Import comisioane și limite cantitate pe ticket types
php artisan fix:ambilet-ticket-type-commissions resources/marketplaces/ambilet/old_database/2026/product_comissions_data.csv

# 12. Generate SKU și serie start/end pe ticket types (lipsesc la import)
php artisan fix:ambilet-ticket-type-series

# 13. Fix availability: dezactivare produse scoase + sale prices
# Necesită CSV-uri în old_database/product_stock/:
# - event_active_products.csv (bilete_eveniment serialized per event)
# - product_availability.csv (availability dates, sale prices, stock status)
php artisan fix:ambilet-ticket-type-availability

# 14. Reactivare ticket types care sunt în bilete_eveniment dar hidden
# (comanda de availability dezactivează pe baza bilete_eveniment, dar
#  unele pot fi hidden din alte rulări anterioare)
# Rulează tinker cu scriptul de reactivare IN LIST (vezi conversație)

# 15. Fix future (scheduled) products — WP post_status=future
# Exportează produse cu post_status=future din WP
# Setează status=hidden + scheduled_at=post_date pe ticket types
# Rulează tinker cu scriptul de scheduled (vezi conversație)
# IMPORTANT: _ticket_availability_from/to_date NU controlează vânzarea!
# Acestea controlează fereastra de VALIDITATE/SCANARE a biletului.
# Disponibilitatea la vânzare e controlată de WP post_status (publish vs future).

# 16. Mark scanned tickets as used (bilete cu checked_in_at dar status=valid)
php artisan tinker --execute='$fixed=DB::table("tickets")->where("marketplace_client_id",1)->where("status","valid")->whereNotNull("checked_in_at")->update(["status"=>"used","updated_at"=>now()]);echo "Marked used: $fixed".PHP_EOL;'

# 17. Import order attribution (UTM, device, referrer, IP)
php artisan import:ambilet-order-attribution $INC/inc_order_attribution.csv

# 18. Clear bilete.online cache (pe serverul bilete.online)
# Accesează clear-cache.php sau procedura specifică

# 19. Verificare
php artisan tinker --execute='echo "Events: ".DB::table("events")->where("marketplace_client_id",1)->count().PHP_EOL;echo "Ticket Types: ".DB::table("ticket_types")->whereIn("event_id",DB::table("events")->where("marketplace_client_id",1)->pluck("id"))->count().PHP_EOL;echo "Orders: ".DB::table("orders")->where("marketplace_client_id",1)->count().PHP_EOL;echo "Tickets: ".DB::table("tickets")->where("marketplace_client_id",1)->count().PHP_EOL;echo "Customers: ".DB::table("marketplace_customers")->where("marketplace_client_id",1)->count().PHP_EOL;'
```

---

## Known Gotchas & Fixes

### CRITICE (cauzează erori)

1. **Post type bilete = `tc_tickets_instances`** — NU `tc_tickets`
2. **Meta key ticket type = `_event_name`** — cu underscore prefix
3. **Meta key ticket→order = `item_id`** — NU `wc_order_item_id`
4. **Meta key descriere = `despre_eveniment`** — NU `post_content`
5. **`paid_at` poate lipsi din CSV** — comanda folosește `$data['paid_at'] ?? null`
6. **`order_item_map.csv` trebuie să fie în ACELAȘI folder** cu `inc_ticket_instances.csv` și să se numească exact `order_item_map.csv`

### ORDINEA CONTEAZĂ

7. **`fix:ambilet-order-statuses` e OBLIGATORIU după `import:ambilet-orders`** — fără el, TOATE comenzile sunt `completed/paid` indiferent de statusul real WC. Actualizează și biletele asociate (cancelled/void).
8. **fix:ambilet-event-fields SUPRASCRIE imaginile locale** cu URL-uri externe din CSV:
   - Pentru evenimente **NOI**: rulează FĂRĂ `--skip-images` (au nevoie de URL-uri pentru download)
   - Pentru evenimente **MODIFICATE**: rulează CU `--skip-images` (păstrează imaginile locale)
   - APOI rulează `fix:ambilet-event-images` + `--fix-paths`
9. **Import order: Events → Ticket Types → Orders → Tickets → Order Statuses** — strict, altfel foreign key errors sau statusuri greșite

### VALORI SPECIALE

9. **`quota_total = -1`** = nelimitat (WP `_stock` gol/NULL); `0` = epuizat/sold out (WP `_stock=0`); `>0` = cantitate fixă
10. **Archived events păstrează `is_published = 1`** — nu pune draft la evenimentele expirate
11. **Ticket types se importă cu `status = hidden`** — trebuie activate manual după import
12. **`_ticket_availability_from/to_date` NU controlează vânzarea** — controlează fereastra de VALIDITATE/SCANARE. Disponibilitatea la vânzare e WP `post_status` (publish=activ, future=programat)
13. **WP `post_status = future`** pe produse = ticket type programat → `status=hidden` + `scheduled_at=post_date`
14. **`bilete_eveniment`** conține TOATE produsele asociate, inclusiv cele `future` — nu e suficient pentru a determina dacă e activ la vânzare

### IMAGINI

12. **301 redirect**: `ambilet.ro` → `www.ambilet.ro` — download-ul trebuie să urmeze redirects
13. **Unele 404** returnează HTML body (37KB) — verifică Content-Type, nu doar status code
14. **PNG palette** → `imagepalettetotruecolor()` înainte de `imagewebp()`
15. **`Storage::put()` creează directoare automat** — nu folosi `makeDirectory()`
16. **Filename = `md5(URL).webp`** — idempotent, nu descarcă de 2 ori

### ORGANIZATORI

17. **Email redirects**: unele emailuri sunt mapate la alt organizator (ex: `mihnea.grecu@xlab.ro` → `contact@grimus.ro`)
18. **Fallback organizer ID = 54** (ambilet.ro@gmail.com) — pentru evenimente fără organizator
19. **Organizatorii noi trebuie creați manual** în Tixello înainte de import

---

## Organizatori cu Email Redirects

| Email original | Redirect la | Motiv |
|---|---|---|
| mihnea.grecu@xlab.ro | contact@grimus.ro | Același organizator |
| mariusstoicea@gmail.com | contact@grimus.ro | Același organizator |

---

## Verificare Post-Import

```sql
-- Pe Tixello DB (tinker)

-- Bilete orfane (fără order_id)
SELECT COUNT(*) FROM tickets WHERE marketplace_client_id=1 AND order_id IS NULL;

-- Comenzi fără event_id
SELECT COUNT(*) FROM orders WHERE marketplace_client_id=1 AND event_id IS NULL;

-- Ticket types cu quota_total=0 (blocat)
SELECT COUNT(*) FROM ticket_types
WHERE event_id IN (SELECT id FROM events WHERE marketplace_client_id=1)
AND quota_total = 0;

-- Evenimente cu imagini externe (ar trebui 0 după fix-paths)
SELECT COUNT(*) FROM events
WHERE marketplace_client_id=1
AND (hero_image_url LIKE 'http%' OR poster_url LIKE 'http%');

-- Ticket types hidden (ar trebui 0 după activare)
SELECT COUNT(*) FROM ticket_types
WHERE event_id IN (SELECT id FROM events WHERE marketplace_client_id=1)
AND status = 'hidden';

-- Ticket types cu quota_sold > quota_total (inconsistență stoc)
-- quota_total=-1 e nelimitat, deci exclude-l
SELECT id, name, quota_total, quota_sold FROM ticket_types
WHERE event_id IN (SELECT id FROM events WHERE marketplace_client_id=1)
AND quota_total >= 0 AND quota_sold > quota_total;
```
