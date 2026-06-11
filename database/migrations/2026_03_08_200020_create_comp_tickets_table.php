<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Complimentary / courtesy tickets for press, sponsors, protocol, VIP
        Schema::create('comp_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('recipient_name');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_organization')->nullable()->comment('Press outlet, sponsor name, etc.');

            $table->string('category', 32)->default('protocol')
                ->comment('protocol|press|sponsor|vip|staff|artist|other');
            $table->integer('quantity')->default(1);
            $table->string('seat_labels')->nullable()->comment('Comma-separated seat labels if reserved');
            $table->string('section_preference')->nullable()->comment('Preferred section name');

            $table->string('status', 32)->default('issued')
                ->comment('issued|sent|claimed|used|expired|revoked');

            $table->string('access_code', 64)->nullable()->unique()->comment('Code for claiming tickets');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->text('internal_notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'event_id']);
            $table->index(['status']);
            $table->index(['category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comp_tickets');
    }
};
