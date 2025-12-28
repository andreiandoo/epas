<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('verification_token', 64)->unique();
            $table->enum('verification_method', ['dns_txt', 'meta_tag', 'file_upload'])->default('dns_txt');
            $table->enum('status', ['pending', 'verified', 'failed', 'expired'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('verification_data')->nullable();
            $table->timestamps();

            $table->index(['domain_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index('verification_token');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_verifications');
    }
};
