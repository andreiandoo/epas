<?php

use App\Models\MarketplaceOrganizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketplace_organizers', 'document_filing_exceptions')) {
            Schema::table('marketplace_organizers', function (Blueprint $table) {
                // Registre fiscale (primării) unde marketplace-ul NU poate depune
                // în numele organizatorului, deși îi gestionează documentele.
                $table->json('document_filing_exceptions')->nullable()->after('marketplace_manages_documents');
            });
        }

        // Organizatorii care au deja documentele la noi pornesc cu excepțiile
        // implicite, doar cele care există în registrul marketplace-ului lor.
        $registries = DB::table('marketplace_tax_registries')
            ->whereIn('id', MarketplaceOrganizer::DEFAULT_FILING_EXCEPTION_REGISTRY_IDS)
            ->get(['id', 'marketplace_client_id'])
            ->groupBy('marketplace_client_id');

        foreach ($registries as $clientId => $rows) {
            DB::table('marketplace_organizers')
                ->where('marketplace_client_id', $clientId)
                ->where('marketplace_manages_documents', true)
                ->whereNull('document_filing_exceptions')
                ->update(['document_filing_exceptions' => json_encode($rows->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all())]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_organizers', 'document_filing_exceptions')) {
            Schema::table('marketplace_organizers', function (Blueprint $table) {
                $table->dropColumn('document_filing_exceptions');
            });
        }
    }
};
