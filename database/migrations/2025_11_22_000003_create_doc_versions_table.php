<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doc_versions')) {
            return;
        }

        Schema::create('doc_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->longText('content');
            $table->text('changelog')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['doc_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_versions');
    }
};
