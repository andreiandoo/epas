<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the physical inventory in two tables:
 *   - physical_resource_types : the catalog (Kayak, MTB Bike, Lifejacket size M, …)
 *   - physical_resources      : the individual units, each with its own QR
 *
 * A resource_type already existed on physical_resources as a freetext string;
 * we keep it for backwards compatibility but also link to the typed row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_resource_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();        // heroicon name OR emoji
            $table->string('color', 16)->nullable();   // hex/tailwind shorthand
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('linked_ticket_type_ids')->nullable(); // default whitelist for new units
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug'], 'prt_tenant_slug_unique');
            $table->index(['tenant_id', 'is_active'], 'prt_tenant_active_idx');
        });

        Schema::table('physical_resources', function (Blueprint $table) {
            if (! Schema::hasColumn('physical_resources', 'physical_resource_type_id')) {
                $table->foreignId('physical_resource_type_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('physical_resource_types')
                    ->nullOnDelete();
                $table->index('physical_resource_type_id', 'pr_type_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('physical_resources', function (Blueprint $table) {
            if (Schema::hasColumn('physical_resources', 'physical_resource_type_id')) {
                $table->dropConstrainedForeignId('physical_resource_type_id');
            }
        });
        Schema::dropIfExists('physical_resource_types');
    }
};
