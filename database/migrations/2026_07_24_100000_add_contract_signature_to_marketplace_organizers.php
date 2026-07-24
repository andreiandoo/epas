<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            // Drawn e-signature (SES) the organizer applies to their onboarding
            // contract. `signature_image` is the stored PNG path (public disk),
            // the rest is the audit trail proving who signed, when and from where.
            $table->string('signature_image')->nullable()->after('work_mode');
            $table->timestamp('contract_signed_at')->nullable()->after('signature_image');
            $table->string('contract_signed_ip', 64)->nullable()->after('contract_signed_at');
            $table->text('contract_signed_user_agent')->nullable()->after('contract_signed_ip');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn([
                'signature_image',
                'contract_signed_at',
                'contract_signed_ip',
                'contract_signed_user_agent',
            ]);
        });
    }
};
