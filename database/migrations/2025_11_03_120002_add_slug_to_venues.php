<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (!Schema::hasColumn('venues', 'slug')) {
                $table->string('slug', 190)->nullable()->unique()->after('name');
            }
        });

        // populăm rapid slug-urile lipsă
        $venues = DB::table('venues')->whereNull('slug')->select('id', 'name')->get();
        foreach ($venues as $v) {
            $base = Str::slug($v->name);
            $slug = $base;
            $i = 1;
            while (DB::table('venues')->where('slug', $slug)->where('id', '!=', $v->id)->exists()) {
                $slug = $base.'-'.$i++;
            }
            DB::table('venues')->where('id', $v->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'slug')) {
                $table->dropUnique('venues_slug_unique');
                $table->dropColumn('slug');
            }
        });
    }
};
