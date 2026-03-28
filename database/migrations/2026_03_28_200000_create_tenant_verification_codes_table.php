<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tenant_type'); // TenantType enum value
            $table->string('code', 20)->unique(); // e.g. TXV-A8K3M2
            $table->string('entity_name'); // name entered by user (artist/venue etc.)
            $table->unsignedBigInteger('matched_entity_id')->nullable();
            $table->string('matched_entity_type')->nullable(); // App\Models\Artist, App\Models\Venue etc.
            $table->string('status')->default('pending'); // pending, verified, expired
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by')->nullable(); // platform/method of verification
            $table->timestamp('expires_at');
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['code', 'status']);
            $table->index('tenant_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_verification_codes');
    }
};
