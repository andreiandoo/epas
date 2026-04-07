<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->boolean('has_proxy_authorization')->default(false)->after('verified_at');
            $table->string('proxy_authorization_file')->nullable()->after('has_proxy_authorization');
            $table->foreignId('proxy_admin_id')->nullable()->after('proxy_authorization_file')
                ->constrained('marketplace_admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proxy_admin_id');
            $table->dropColumn(['has_proxy_authorization', 'proxy_authorization_file']);
        });
    }
};
