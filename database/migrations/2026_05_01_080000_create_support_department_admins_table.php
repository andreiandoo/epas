<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot linking support departments to the marketplace admins who
 * handle them. Drives the assignee dropdown on tickets (we'll filter
 * the list to admins associated with the ticket's department) and the
 * "default notify" recipients when a new ticket lands.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_department_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_department_id')
                ->constrained('support_departments')
                ->cascadeOnDelete();
            $table->foreignId('marketplace_admin_id')
                ->constrained('marketplace_admins')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['support_department_id', 'marketplace_admin_id'], 'support_dept_admin_uq');
            $table->index('marketplace_admin_id', 'support_dept_admin_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_department_admins');
    }
};
