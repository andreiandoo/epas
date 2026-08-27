<?php

namespace Tests\Feature\Chat;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base for the live-chat suite. Runs on an isolated in-memory SQLite connection
 * with a reduced schema: the upstream stubs (marketplace_clients / admins /
 * support_departments) plus the real chat migrations. The full history cannot
 * run on SQLite (DECISIONS.md D-002) and the container has no Postgres.
 */
abstract class ChatTestCase extends TestCase
{
    /** @var array<int, string> Real chat migration files, applied in order. */
    private array $chatMigrations = [
        'database/migrations/2026_08_27_100000_create_chat_conversations_table.php',
        'database/migrations/2026_08_27_100001_create_chat_messages_table.php',
        'database/migrations/2026_08_27_100002_create_chat_operator_statuses_table.php',
        'database/migrations/2026_08_27_100003_create_chat_operator_schedules_table.php',
        'database/migrations/2026_08_27_100004_create_chat_holidays_table.php',
        'database/migrations/2026_08_27_100005_create_chat_canned_responses_table.php',
        'database/migrations/2026_08_27_100006_create_chat_blocklist_table.php',
    ];

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $app['config']->set('database.connections.chat_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $app['config']->set('database.default', 'chat_testing');

        // Deterministic: array cache, polling transport (broadcasts inert).
        $app['config']->set('cache.default', 'array');
        $app['config']->set('chat.transport', 'polling');

        DB::purge('chat_testing');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Upstream stubs first, then the real chat migrations.
        Artisan::call('migrate', [
            '--path' => 'tests/database/chat-migrations',
            '--force' => true,
        ]);

        foreach ($this->chatMigrations as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }
    }
}
