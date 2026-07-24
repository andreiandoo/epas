<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_newsletter_recipients', function (Blueprint $table) {
            // Why the recipient unsubscribed. `unsubscribe_reason` holds the
            // chosen category key (too_many / not_relevant / never_signed_up /
            // spam / other); `unsubscribe_reason_detail` holds free text when
            // "other" is picked or an extra note is added. Both optional — the
            // unsubscribe itself never depends on the feedback being provided.
            $table->string('unsubscribe_reason')->nullable()->after('unsubscribed_at');
            $table->text('unsubscribe_reason_detail')->nullable()->after('unsubscribe_reason');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_newsletter_recipients', function (Blueprint $table) {
            $table->dropColumn(['unsubscribe_reason', 'unsubscribe_reason_detail']);
        });
    }
};
