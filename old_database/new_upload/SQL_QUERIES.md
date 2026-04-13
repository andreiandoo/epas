# SQL Queries - Migrare 24 Mar → 8 Apr 2026

Interval: `2026-03-24 00:00:00` → `2026-04-08 23:59:59`

Rulează în phpMyAdmin pe baza de date WordPress (ambilet.ro).
Export fiecare query ca CSV cu header (prima linie = nume coloane).
Salvează fișierele în acest folder (`new_upload/`).

---

## 1. Evenimente NOI → `inc_events_new.csv`

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
  AND p.post_date > '2026-03-24 00:00:00' AND p.post_date <= '2026-04-08 23:59:59'
ORDER BY p.ID
```

---

## 2. Event Fields Fix (NEW) → `inc_event_fields_fix.csv`

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
  AND p.post_date > '2026-03-24 00:00:00' AND p.post_date <= '2026-04-08 23:59:59'
ORDER BY p.ID
```

---

## 3. Event Fields Fix (MODIFIED) → `inc_event_fields_fix_modified.csv`

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
  AND p.post_date <= '2026-03-24 00:00:00'
  AND p.post_modified > '2026-03-24 00:00:00' AND p.post_modified <= '2026-04-08 23:59:59'
ORDER BY p.ID
```

---

## 4. Event Dates Fix (NEW) → `inc_event_dates_fix.csv`

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
  AND p.post_date > '2026-03-24 00:00:00' AND p.post_date <= '2026-04-08 23:59:59'
ORDER BY p.ID
```

---

## 5. Event Dates Fix (MODIFIED) → `inc_event_dates_fix_modified.csv`

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
  AND p.post_date <= '2026-03-24 00:00:00'
  AND p.post_modified > '2026-03-24 00:00:00' AND p.post_modified <= '2026-04-08 23:59:59'
ORDER BY p.ID
```

---

## 6. Ticket Types (NEW + MODIFIED) → `inc_ticket_types.csv`

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
  AND GREATEST(p.post_date, p.post_modified) > '2026-03-24 00:00:00'
  AND GREATEST(p.post_date, p.post_modified) <= '2026-04-08 23:59:59'
ORDER BY p.ID
```

---

## 7. Comenzi NOI → `inc_orders.csv`

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
  AND p.post_date > '2026-03-24 00:00:00' AND p.post_date <= '2026-04-08 23:59:59'
ORDER BY p.ID
```

---

## 8. Order Item Map → `inc_order_item_map.csv`

```sql
SELECT
    oi.order_item_id,
    oi.order_id AS wp_order_id
FROM wpyt_woocommerce_order_items oi
JOIN wpyt_posts p ON p.ID = oi.order_id
WHERE p.post_type = 'shop_order'
  AND p.post_date > '2026-03-24 00:00:00' AND p.post_date <= '2026-04-08 23:59:59'
  AND oi.order_item_type = 'line_item'
ORDER BY oi.order_item_id
```

---

## 9. Bilete NOI → `inc_ticket_instances.csv`

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
  AND p.post_date > '2026-03-24 00:00:00' AND p.post_date <= '2026-04-08 23:59:59'
ORDER BY p.ID
```

---

## 10. Order Attribution → `inc_order_attribution.csv`

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
  AND p.post_date > '2026-03-24 00:00:00' AND p.post_date <= '2026-04-08 23:59:59'
  AND pm.meta_key IN ('_wc_order_attribution_device_type','_wc_order_attribution_source_type','_wc_order_attribution_utm_source','_wc_order_attribution_utm_medium','_wc_order_attribution_utm_content','_wc_order_attribution_referrer','_wc_order_attribution_session_entry','_wc_order_attribution_session_count','_wc_order_attribution_session_pages','_customer_ip_address','_wc_order_attribution_user_agent')
GROUP BY p.ID ORDER BY p.ID
```

---

## 11. Ticket-Order Map → `inc_ticket_order_map.csv`

```sql
SELECT
    t.ID AS wp_ticket_id,
    oi.order_id AS wp_order_id
FROM wpyt_posts t
JOIN wpyt_postmeta pm ON pm.post_id = t.ID AND pm.meta_key = 'item_id'
JOIN wpyt_woocommerce_order_items oi ON oi.order_item_id = pm.meta_value
WHERE t.post_type = 'tc_tickets_instances'
  AND t.post_date > '2026-03-24 00:00:00' AND t.post_date <= '2026-04-08 23:59:59'
ORDER BY t.ID
```

---

## Checklist export phpMyAdmin

Pentru fiecare query:
1. Rulează query-ul în phpMyAdmin (tab SQL)
2. Click **Export** pe rezultat
3. Format: **CSV** (cu header = include column names)
4. Salvează cu numele indicat mai sus

### Fișiere finale (11 CSV-uri):

| # | Fișier | Query |
|---|--------|-------|
| 1 | `inc_events_new.csv` | Evenimente NOI |
| 2 | `inc_event_fields_fix.csv` | Event fields (NEW) |
| 3 | `inc_event_fields_fix_modified.csv` | Event fields (MODIFIED) |
| 4 | `inc_event_dates_fix.csv` | Event dates (NEW) |
| 5 | `inc_event_dates_fix_modified.csv` | Event dates (MODIFIED) |
| 6 | `inc_ticket_types.csv` | Ticket types (NEW+MODIFIED) |
| 7 | `inc_orders.csv` | Comenzi NOI |
| 8 | `inc_order_item_map.csv` | Order item map |
| 9 | `inc_ticket_instances.csv` | Bilete NOI |
| 10 | `inc_order_attribution.csv` | Order attribution |
| 11 | `inc_ticket_order_map.csv` | Ticket-order map |
