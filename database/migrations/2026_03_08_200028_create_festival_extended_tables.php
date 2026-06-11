<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each table wrapped in hasTable check — a previous partial run may have
        // created some tables before failing on the JSON default value issue.

        // ── Festival pass purchases ─────────────────────────
        if (!Schema::hasTable('festival_pass_purchases')) {
            Schema::create('festival_pass_purchases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('festival_pass_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->string('code')->unique()->comment('Unique pass code / barcode');
                $table->string('holder_name')->nullable()->comment('Name printed on wristband');
                $table->string('holder_email')->nullable();
                $table->string('holder_phone')->nullable();
                $table->string('status')->default('active')->comment('active|checked_in|expired|cancelled|refunded|transferred');
                $table->dateTime('activated_at')->nullable();
                $table->dateTime('checked_in_at')->nullable();
                $table->string('checked_in_gate')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->string('cancel_reason')->nullable();
                $table->json('day_checkins')->nullable()->comment('JSON: {day_id: checked_in_at} per-day tracking');
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'customer_id']);
                $table->index(['festival_pass_id', 'status']);
            });
        }

        // ── Festival addon purchases ────────────────────────
        if (!Schema::hasTable('festival_addon_purchases')) {
            Schema::create('festival_addon_purchases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('festival_addon_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('festival_pass_purchase_id')->nullable()->constrained()->nullOnDelete()->comment('Linked pass if addon requires one');
                $table->string('code')->unique();
                $table->integer('quantity')->default(1);
                $table->integer('price_cents_paid');
                $table->string('currency', 3)->default('RON');
                $table->string('status')->default('active')->comment('active|used|expired|cancelled|refunded');
                $table->json('selected_options')->nullable()->comment('JSON: chosen variant/zone/dates');
                $table->string('assigned_spot')->nullable()->comment('e.g. camping zone B, spot 42 / parking P3-15');
                $table->dateTime('valid_from')->nullable();
                $table->dateTime('valid_until')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'customer_id']);
                $table->index(['festival_addon_id', 'status']);
            });
        }

        // ── Wristbands ──────────────────────────────────────
        if (!Schema::hasTable('wristbands')) {
            Schema::create('wristbands', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_pass_purchase_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('uid')->unique()->comment('NFC UID or QR code');
                $table->string('wristband_type')->default('nfc')->comment('nfc|qr|rfid|hybrid');
                $table->string('status')->default('unassigned')->comment('unassigned|assigned|activated|disabled|lost|returned');
                $table->integer('balance_cents')->default(0)->comment('Cashless payment balance');
                $table->string('currency', 3)->default('RON');
                $table->dateTime('assigned_at')->nullable();
                $table->dateTime('activated_at')->nullable();
                $table->dateTime('disabled_at')->nullable();
                $table->string('disabled_reason')->nullable();
                $table->json('access_zones')->nullable()->comment('JSON: zones this wristband can access');
                $table->json('scan_log')->nullable()->comment('JSON: last N scans for quick reference');
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['festival_pass_purchase_id']);
            });
        }

        // ── Festival sponsors ───────────────────────────────
        if (!Schema::hasTable('festival_sponsors')) {
            Schema::create('festival_sponsors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('tier')->comment('title|platinum|gold|silver|bronze|media|community');
                $table->text('description')->nullable();
                $table->string('logo_url')->nullable();
                $table->string('website_url')->nullable();
                $table->json('placements')->nullable()->comment('JSON: [stage_banner, wristband, app, mainstage_screen, ticket]');
                $table->json('sponsored_stage_ids')->nullable()->comment('Stages this sponsor is attached to');
                $table->json('sponsored_day_ids')->nullable()->comment('Days this sponsor covers');
                $table->integer('contract_value_cents')->nullable();
                $table->string('currency', 3)->default('RON');
                $table->string('contact_name')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('contact_phone')->nullable();
                $table->string('status')->default('active')->comment('draft|active|paused|completed');
                $table->integer('sort_order')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
            });
        }

        // ── Waitlist ────────────────────────────────────────
        if (!Schema::hasTable('waitlists')) {
            Schema::create('waitlists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('email');
                $table->string('name')->nullable();
                $table->string('phone')->nullable();
                $table->string('waitable_type')->comment('FestivalPass, FestivalAddon, TicketType, FlexPass');
                $table->unsignedBigInteger('waitable_id');
                $table->integer('quantity')->default(1);
                $table->string('status')->default('waiting')->comment('waiting|notified|converted|expired|cancelled');
                $table->dateTime('notified_at')->nullable();
                $table->dateTime('converted_at')->nullable();
                $table->dateTime('expires_at')->nullable()->comment('How long they have to purchase after notification');
                $table->string('notification_token')->nullable()->unique()->comment('Token in purchase link');
                $table->integer('position')->default(0)->comment('Queue position');
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['waitable_type', 'waitable_id', 'status']);
                $table->index(['tenant_id', 'email']);
            });
        }

        // ── Festival map / points of interest ───────────────
        if (!Schema::hasTable('festival_maps')) {
            Schema::create('festival_maps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name')->comment('e.g. Main Site, Camping Area');
                $table->string('image_url')->nullable()->comment('Map image / SVG');
                $table->json('bounds')->nullable()->comment('JSON: {north, south, east, west} geo bounds');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('festival_points_of_interest')) {
            Schema::create('festival_points_of_interest', function (Blueprint $table) {
                $table->id();
                $table->foreignId('festival_map_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete()->comment('Link to stage if this POI is a stage');
                $table->string('name');
                $table->string('category')->comment('stage|food|drink|toilet|first_aid|info|atm|camping|parking|entrance|exit|shower|charging|merch|vip|chill|art_installation|workshop');
                $table->string('icon')->nullable();
                $table->text('description')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->json('pixel_position')->nullable()->comment('JSON: {x, y} position on map image');
                $table->string('status')->default('active')->comment('active|closed|temporary');
                $table->json('operating_hours')->nullable()->comment('JSON: {open, close} or per-day schedule');
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['festival_map_id', 'category']);
            });
        }

        // ── Schedule favorites ──────────────────────────────
        if (!Schema::hasTable('festival_schedule_favorites')) {
            Schema::create('festival_schedule_favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_lineup_slot_id')->constrained()->cascadeOnDelete();
                $table->boolean('notify_before')->default(true)->comment('Send reminder before set starts');
                $table->integer('notify_minutes_before')->default(15);
                $table->timestamps();

                $table->unique(['customer_id', 'festival_lineup_slot_id'], 'fav_customer_slot_unique');
            });
        }

        // ── Artist set notifications ────────────────────────
        if (!Schema::hasTable('artist_set_notifications')) {
            Schema::create('artist_set_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_lineup_slot_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_schedule_favorite_id')->nullable()->constrained()->nullOnDelete();
                $table->string('channel')->default('push')->comment('push|sms|email');
                $table->string('status')->default('pending')->comment('pending|sent|failed|cancelled');
                $table->dateTime('scheduled_at');
                $table->dateTime('sent_at')->nullable();
                $table->string('failure_reason')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['scheduled_at', 'status']);
            });
        }

        // ── Festival alerts (weather, safety, announcements) ─
        if (!Schema::hasTable('festival_alerts')) {
            Schema::create('festival_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->string('alert_type')->comment('weather|safety|schedule_change|emergency|info|lost_found');
                $table->string('severity')->default('info')->comment('info|warning|critical|emergency');
                $table->string('title');
                $table->text('message');
                $table->json('affected_stage_ids')->nullable();
                $table->json('affected_day_ids')->nullable();
                $table->json('channels')->nullable()->comment('JSON: [push, sms, app, screen, speaker] — defaults to ["push"] in app');
                $table->string('status')->default('draft')->comment('draft|active|resolved|expired');
                $table->dateTime('published_at')->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->string('resolved_by')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status', 'severity']);
                $table->index(['published_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('festival_alerts');
        Schema::dropIfExists('artist_set_notifications');
        Schema::dropIfExists('festival_schedule_favorites');
        Schema::dropIfExists('festival_points_of_interest');
        Schema::dropIfExists('festival_maps');
        Schema::dropIfExists('waitlists');
        Schema::dropIfExists('festival_sponsors');
        Schema::dropIfExists('wristbands');
        Schema::dropIfExists('festival_addon_purchases');
        Schema::dropIfExists('festival_pass_purchases');
    }
};
