<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            // Manually-uploaded contract (public disk path) for organizers the
            // system never auto-generated a contract for. System-generated
            // contracts stay in organizer_documents (document_type=organizer_contract).
            $table->string('contract_document')->nullable()->after('contract_date');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn('contract_document');
        });
    }
};
