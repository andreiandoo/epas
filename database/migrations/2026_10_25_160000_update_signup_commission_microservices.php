<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Live Chat strânge date despre vizitatori, deci rămâne la prețul lui și nu mai ridică
 * comisionul. Exportul de clienți scoate date din Tixello, deci îl ridică.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('microservices')->where('slug', 'live-chat')->update(['raises_commission' => false]);
        DB::table('microservices')->where('slug', 'customers-export')->update(['raises_commission' => true, 'signup_featured' => true]);
    }

    public function down(): void
    {
        DB::table('microservices')->where('slug', 'live-chat')->update(['raises_commission' => true]);
        DB::table('microservices')->where('slug', 'customers-export')->update(['raises_commission' => false, 'signup_featured' => false]);
    }
};
