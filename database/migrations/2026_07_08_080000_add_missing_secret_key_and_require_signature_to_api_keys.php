<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Backfill columns that the ApiKey model has been referencing in
     * $fillable for months but that never landed in any migration:
     *
     *   - secret_key       encrypted HMAC secret paired with the public
     *                      key; set on model::generate() and used by
     *                      verifySignature() when require_signature is on.
     *
     *   - require_signature toggle that flips the middleware into HMAC
     *                       enforcement mode (X-Timestamp + X-Signature).
     *
     * Latent because the /admin/api-keys create form previously wrote
     * only name/description/expires_at, so Eloquent never surfaced
     * either column in the INSERT statement. Etapa A extended the form
     * to include the HMAC toggle, which finally exposed the drift as
     * "column require_signature does not exist" at INSERT time.
     *
     * Both columns land with safe defaults (NULL / false) — existing
     * keys keep their behavior, no data change required.
     */
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            if (!Schema::hasColumn('api_keys', 'secret_key')) {
                // text (not string) because the encrypted-at-rest payload
                // can grow past 255 chars once Laravel Crypt wraps it.
                $table->text('secret_key')->nullable()->after('key_hash');
            }

            if (!Schema::hasColumn('api_keys', 'require_signature')) {
                $table->boolean('require_signature')->default(false)->after('permissions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            if (Schema::hasColumn('api_keys', 'require_signature')) {
                $table->dropColumn('require_signature');
            }
            if (Schema::hasColumn('api_keys', 'secret_key')) {
                $table->dropColumn('secret_key');
            }
        });
    }
};
