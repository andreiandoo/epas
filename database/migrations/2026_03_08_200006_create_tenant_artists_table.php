<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Optional link to global artist record');

            $table->string('name');
            $table->string('slug')->index();
            $table->string('role', 64)->nullable()
                ->comment('actor|soloist|conductor|choreographer|musician|director|etc.');
            $table->json('bio')->nullable()->comment('Translatable biography');
            $table->string('photo_url')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Contract/employment info
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->boolean('is_resident')->default(false)->comment('Permanent member of the institution');

            $table->json('meta')->nullable();
            $table->string('status', 32)->default('active')->comment('active|inactive');
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'role']);
            $table->index(['tenant_id', 'status']);
        });

        // Pivot: which tenant artists perform in which events (cast/distribution)
        Schema::create('event_tenant_artist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_artist_id')->constrained()->cascadeOnDelete();
            $table->string('role_in_event')->nullable()->comment('Character/role name in this specific production');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'tenant_artist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tenant_artist');
        Schema::dropIfExists('tenant_artists');
    }
};
