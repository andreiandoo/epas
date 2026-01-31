<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_packages')) {
            return;
        }

        Schema::create('tenant_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('version', 20)->default('1.0.0');
            $table->string('package_hash', 64);
            $table->string('integrity_hash', 128)->comment('SRI hash for script tag');
            $table->enum('status', ['generating', 'ready', 'expired', 'invalidated'])->default('generating');
            $table->json('config_snapshot')->comment('Encrypted tenant config at generation time');
            $table->json('enabled_modules')->comment('List of enabled microservices/features');
            $table->json('theme_config')->nullable()->comment('Tenant branding/theme settings');
            $table->string('file_path')->nullable()->comment('Path to generated package file');
            $table->integer('file_size')->nullable()->comment('Package size in bytes');
            $table->integer('download_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['domain_id', 'status']);
            $table->index('package_hash');
            $table->unique(['domain_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_packages');
    }
};
