#!/bin/bash
# =============================================================================
# Import AmBilet → Tixello | 24 Mar 2026 → 8 Apr 2026
# =============================================================================
# Rulează pe serverul Tixello, din directorul core/ (Laravel root)
# CSV-urile trebuie copiate în: resources/marketplaces/ambilet/old_database/new_upload/
# =============================================================================

set -e  # Stop on first error

INC="resources/marketplaces/ambilet/old_database/new_upload"

echo "=========================================="
echo "  AmBilet Import: 24 Mar - 8 Apr 2026"
echo "=========================================="

# 0. Backup map files
echo ""
echo ">>> STEP 0: Backup map files"
cp storage/app/import_maps/events_map.json storage/app/import_maps/events_map_backup_$(date +%Y%m%d_%H%M).json 2>/dev/null || echo "  (no events_map to backup)"
cp storage/app/import_maps/orders_map.json storage/app/import_maps/orders_map_backup_$(date +%Y%m%d_%H%M).json 2>/dev/null || echo "  (no orders_map to backup)"
cp storage/app/import_maps/ticket_types_map.json storage/app/import_maps/ticket_types_map_backup_$(date +%Y%m%d_%H%M).json 2>/dev/null || echo "  (no ticket_types_map to backup)"
cp storage/app/import_maps/tickets_map.json storage/app/import_maps/tickets_map_backup_$(date +%Y%m%d_%H%M).json 2>/dev/null || echo "  (no tickets_map to backup)"
echo "  Done."

# 1. Import evenimente noi
echo ""
echo ">>> STEP 1: Import evenimente noi (61 expected)"
php artisan import:ambilet-events $INC/inc_events_new.csv --fallback-organizer=54

# 2. Import ticket types
echo ""
echo ">>> STEP 2: Import ticket types (280 expected)"
php artisan import:ambilet-ticket-types $INC/inc_ticket_types.csv

# 3. Import comenzi noi
echo ""
echo ">>> STEP 3: Import comenzi noi (2068 expected)"
php artisan import:ambilet-orders $INC/inc_orders.csv

# 4. Import bilete noi
echo ""
echo ">>> STEP 4: Import bilete noi (4725 expected)"
cp $INC/inc_order_item_map.csv $INC/order_item_map.csv
php artisan import:ambilet-tickets $INC/inc_ticket_instances.csv

# 5. Fix order statuses
echo ""
echo ">>> STEP 5: Fix order statuses (OBLIGATORIU)"
php artisan fix:ambilet-order-statuses $INC/inc_orders.csv

# 6. Fix event fields
echo ""
echo ">>> STEP 6: Fix event fields (NEW fara --skip-images, MODIFIED cu --skip-images)"
php artisan fix:ambilet-event-fields $INC/inc_event_fields_fix.csv
php artisan fix:ambilet-event-fields $INC/inc_event_fields_fix_modified.csv --skip-images

# 7. Fix event dates
echo ""
echo ">>> STEP 7: Fix event dates (NEW + MODIFIED)"
php artisan fix:ambilet-event-dates $INC/inc_event_dates_fix.csv
php artisan fix:ambilet-event-dates $INC/inc_event_dates_fix_modified.csv

# 8. Download imagini + fix paths
echo ""
echo ">>> STEP 8: Download imagini + fix paths"
php artisan fix:ambilet-event-images
php artisan fix:ambilet-event-images --fix-paths

# 9. Activare ticket types
echo ""
echo ">>> STEP 9: Activare ticket types"
php artisan tinker --execute='$ids=DB::table("events")->where("marketplace_client_id",1)->pluck("id");echo "Activated: ".DB::table("ticket_types")->whereIn("event_id",$ids)->where("status","hidden")->update(["status"=>"active","updated_at"=>now()]).PHP_EOL;'

# 10. Link orphan tickets
echo ""
echo ">>> STEP 10: Link orphan tickets"
php artisan fix:ambilet-orphan-tickets $INC/inc_ticket_order_map.csv

# 11. Import comisioane
echo ""
echo ">>> STEP 11: Import comisioane ticket types"
php artisan fix:ambilet-ticket-type-commissions resources/marketplaces/ambilet/old_database/2026/product_comissions_data.csv

# 12. Generate SKU + serie
echo ""
echo ">>> STEP 12: Generate SKU si serie pe ticket types"
php artisan fix:ambilet-ticket-type-series

# 13. Fix availability
echo ""
echo ">>> STEP 13: Fix availability (dezactivare produse scoase + sale prices)"
php artisan fix:ambilet-ticket-type-availability

# 14-15. Reactivare + future products — MANUAL (vezi MIGRATION_GUIDE.md pasi 14-15)
echo ""
echo ">>> STEP 14-15: SKIP — reactivare si future products se fac manual (vezi ghid)"

# 16. Mark scanned tickets as used
echo ""
echo ">>> STEP 16: Mark scanned tickets as used"
php artisan tinker --execute='$fixed=DB::table("tickets")->where("marketplace_client_id",1)->where("status","valid")->whereNotNull("checked_in_at")->update(["status"=>"used","updated_at"=>now()]);echo "Marked used: $fixed".PHP_EOL;'

# 17. Import order attribution
echo ""
echo ">>> STEP 17: Import order attribution (UTM, device, referrer)"
php artisan import:ambilet-order-attribution $INC/inc_order_attribution.csv

# 18. Verificare
echo ""
echo "=========================================="
echo "  VERIFICARE FINALA"
echo "=========================================="
php artisan tinker --execute='
echo "Events:       ".DB::table("events")->where("marketplace_client_id",1)->count().PHP_EOL;
echo "Ticket Types: ".DB::table("ticket_types")->whereIn("event_id",DB::table("events")->where("marketplace_client_id",1)->pluck("id"))->count().PHP_EOL;
echo "Orders:       ".DB::table("orders")->where("marketplace_client_id",1)->count().PHP_EOL;
echo "Tickets:      ".DB::table("tickets")->where("marketplace_client_id",1)->count().PHP_EOL;
echo "Customers:    ".DB::table("marketplace_customers")->where("marketplace_client_id",1)->count().PHP_EOL;
echo PHP_EOL;
echo "--- Sanity checks ---".PHP_EOL;
echo "Orphan tickets (no order): ".DB::table("tickets")->where("marketplace_client_id",1)->whereNull("order_id")->count().PHP_EOL;
echo "Orders no event_id:        ".DB::table("orders")->where("marketplace_client_id",1)->whereNull("event_id")->count().PHP_EOL;
echo "TT hidden:                 ".DB::table("ticket_types")->whereIn("event_id",DB::table("events")->where("marketplace_client_id",1)->pluck("id"))->where("status","hidden")->count().PHP_EOL;
echo "TT quota_sold > total:     ".DB::table("ticket_types")->whereIn("event_id",DB::table("events")->where("marketplace_client_id",1)->pluck("id"))->where("quota_total",">=",0)->whereColumn("quota_sold",">","quota_total")->count().PHP_EOL;
echo "External images:           ".DB::table("events")->where("marketplace_client_id",1)->where(function($q){$q->where("hero_image_url","LIKE","http%")->orWhere("poster_url","LIKE","http%");})->count().PHP_EOL;
'

echo ""
echo "=========================================="
echo "  DONE! Nu uita sa dai clear cache pe bilete.online"
echo "=========================================="
