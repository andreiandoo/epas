<?php

namespace Tests\Feature\TixelloWidget;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bază pentru suita widget-ului de Android.
 *
 * Rulează pe o conexiune proprie, cu o schemă redusă: stub-urile din
 * `tests/database/widget-migrations` plus migrația reală a token-urilor.
 * Istoricul complet nu poate fi rulat pe SQLite (DECISIONS.md D-002), iar
 * containerul nu are Postgres.
 */
abstract class TixelloWidgetTestCase extends TestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $app['config']->set('database.connections.widget_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $app['config']->set('database.default', 'widget_testing');

        /* Cifrele trebuie să fie deterministe: fără cache între cereri şi cu
           un fus fix, altfel „azi" se mută sub picioarele testului. */
        $app['config']->set('tixello-widget.cache_ttl', 0);
        $app['config']->set('tixello-widget.timezone', 'Europe/Bucharest');

        DB::purge('widget_testing');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'tests/database/widget-migrations',
            '--force' => true,
        ]);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_08_15_120000_create_tixello_widget_tokens_table.php',
            '--force' => true,
        ]);
    }
}
