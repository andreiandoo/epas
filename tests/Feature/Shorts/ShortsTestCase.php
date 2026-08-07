<?php

namespace Tests\Feature\Shorts;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base case for the Shorts suite.
 *
 * Runs against a private in-memory SQLite connection carrying a *scoped*
 * schema: the upstream stubs in tests/database/migrations plus every shorts
 * migration. The repository's full migration history cannot replay on SQLite
 * (a pre-existing migration drops an indexed column), so replaying all 747 is
 * not an option here — see DECISIONS.md.
 *
 * New shorts migrations are picked up automatically: any file in
 * database/migrations whose name contains "short".
 */
abstract class ShortsTestCase extends TestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $app['config']->set('database.connections.shorts_testing', [
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
