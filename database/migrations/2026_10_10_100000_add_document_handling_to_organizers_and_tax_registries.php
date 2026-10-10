<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            // true = the marketplace prepares and files the event's fiscal
            // documents (cerere vizare, impozit, PV distrugere); false = the
            // organizer handles them on their own.
            $table->boolean('marketplace_manages_documents')->default(false)->after('proxy_admin_id');
        });

        // Starting point: organizers that already gave a proxy authorization.
        DB::table('marketplace_organizers')
            ->where('has_proxy_authorization', true)
            ->update(['marketplace_manages_documents' => true]);

        Schema::table('marketplace_tax_registries', function (Blueprint $table) {
            // How documents reach this city hall: 'email' | 'third_party'.
            $table->string('submission_method', 20)->nullable()->after('email2');
            $table->string('submission_email')->nullable()->after('submission_method');
            $table->string('third_party_name')->nullable()->after('submission_email');
            $table->string('third_party_url', 500)->nullable()->after('third_party_name');
            $table->text('third_party_procedure')->nullable()->after('third_party_url');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_tax_registries', function (Blueprint $table) {
            $table->dropColumn(['submission_method', 'submission_email', 'third_party_name', 'third_party_url', 'third_party_procedure']);
        });

        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn('marketplace_manages_documents');
        });
    }
};
