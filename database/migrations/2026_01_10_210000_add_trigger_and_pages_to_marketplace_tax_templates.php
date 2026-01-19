<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marketplace_tax_templates', function (Blueprint $table) {
            $table->string('trigger')->nullable()->after('type')->comment('after_event_published, after_event_finished');
            $table->string('page_orientation')->default('portrait')->after('html_content')->comment('portrait, landscape');
            $table->longText('html_content_page_2')->nullable()->after('page_orientation');
            $table->string('page_2_orientation')->nullable()->after('html_content_page_2')->comment('portrait, landscape');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_tax_templates', function (Blueprint $table) {
            $table->dropColumn(['trigger', 'page_orientation', 'html_content_page_2', 'page_2_orientation']);
        });
    }
};
