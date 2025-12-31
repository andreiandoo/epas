<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add tax_display_mode to tenants table
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('tax_display_mode')->default('included')->after('vat_payer');
            // Values: 'included' (taxes included in price) or 'added' (taxes added on top)
        });

        // 2. Add icon_svg to general_taxes table
        Schema::table('general_taxes', function (Blueprint $table) {
            $table->text('icon_svg')->nullable()->after('name');
        });

        // 3. Create pivot table for multi-select event types on general_taxes
        Schema::create('general_tax_event_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_tax_id')->constrained('general_taxes')->cascadeOnDelete();
            $table->foreignId('event_type_id')->constrained('event_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['general_tax_id', 'event_type_id']);
        });

        // 4. Migrate existing event_type_id data to pivot table
        $generalTaxes = \DB::table('general_taxes')->whereNotNull('event_type_id')->get();
        foreach ($generalTaxes as $tax) {
            \DB::table('general_tax_event_type')->insert([
                'general_tax_id' => $tax->id,
                'event_type_id' => $tax->event_type_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Remove the old event_type_id column from general_taxes
        Schema::table('general_taxes', function (Blueprint $table) {
            $table->dropForeign(['event_type_id']);
            $table->dropColumn('event_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add event_type_id column
        Schema::table('general_taxes', function (Blueprint $table) {
            $table->foreignId('event_type_id')->nullable()->after('name')->constrained('event_types');
        });

        // Migrate pivot data back to single column (only first event type)
        $pivotData = \DB::table('general_tax_event_type')
            ->select('general_tax_id', \DB::raw('MIN(event_type_id) as event_type_id'))
            ->groupBy('general_tax_id')
            ->get();

        foreach ($pivotData as $data) {
            \DB::table('general_taxes')
                ->where('id', $data->general_tax_id)
                ->update(['event_type_id' => $data->event_type_id]);
        }

        // Drop pivot table
        Schema::dropIfExists('general_tax_event_type');

        // Remove icon_svg from general_taxes
        Schema::table('general_taxes', function (Blueprint $table) {
            $table->dropColumn('icon_svg');
        });

        // Remove tax_display_mode from tenants
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('tax_display_mode');
        });
    }
};
