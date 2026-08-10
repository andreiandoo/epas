<?php

namespace Tests\Feature\Shorts;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base case for the Shorts suite.
 *
 * Runs against a private connection carrying a *scoped* schema: the upstream
 * stubs in tests/database/migrations plus every shorts migration. The
 * repository's full migration history cannot replay on SQLite (a pre-existing
 * migration drops an indexed column), so replaying all 747 is not an option
 * here — see DECISIONS.md.
 *
 * Default driver is in-memory SQLite so the suite needs no services. Set
 * SHORTS_TEST_PGSQL=1 (plus DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME) to run the
 * same suite on PostgreSQL, which is what core.tixello.com runs.
 *
 * New shorts migrations are picked up automatically, provided they follow the
 * <timestamp>_shorts_<description>.php naming convention.
 */
abstract class ShortsTestCase extends TestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // SHORTS_TEST_PGSQL=1 points the suite at a real PostgreSQL, which is
        // what production runs. SQLite stays the default so the suite needs no
        // services, but a green SQLite run does NOT prove Postgres compatibility
        // — json/LIKE, GROUP BY strictness and index-name limits all differ.
        $app['config']->set('database.connections.shorts_testing', env('SHORTS_TEST_PGSQL')
            ? [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'shorts_pg'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]
            : [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        $app['config']->set('database.default', 'shorts_testing');

        // Keep the feature deterministic under test regardless of local .env.
        $app['config']->set('services.video.driver', 'null');
        $app['config']->set('shorts.telemetry.impression_sampling', 1);

        DB::purge('shorts_testing');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateScopedSchema();
    }

    protected function migrateScopedSchema(): void
    {
        // On a shared Postgres the schema persists between tests, so start clean.
        if (env('SHORTS_TEST_PGSQL')) {
            DB::statement('DROP SCHEMA public CASCADE');
            DB::statement('CREATE SCHEMA public');
        }

        Artisan::call('migrate', [
            '--path' => 'tests/database/migrations',
            '--force' => true,
        ]);

        foreach ($this->shortsMigrationFiles() as $file) {
            Artisan::call('migrate', [
                '--path' => $file,
                '--force' => true,
            ]);
        }
    }

    /**
     * Every shorts migration, in filename (i.e. chronological) order.
     *
     * Discovery relies on the naming convention every Shorts migration follows:
     * <timestamp>_shorts_<description>.php. Keep new ones on that convention and
     * this suite picks them up with no edit here.
     *
     * @return array<int, string>
     */
    protected function shortsMigrationFiles(): array
    {
        $files = glob(database_path('migrations/*_shorts_*.php')) ?: [];

        $files = array_filter(
            $files,
            fn (string $path) => preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_shorts_/', basename($path)) === 1,
        );

        sort($files);

        return array_map(
            fn (string $path) => 'database/migrations/'.basename($path),
            $files,
        );
    }
}
