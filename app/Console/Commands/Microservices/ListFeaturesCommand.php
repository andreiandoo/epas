<?php

namespace App\Console\Commands\Microservices;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListFeaturesCommand extends Command
{
    protected $signature = 'feature-flags:list';

    protected $description = 'List all feature flags';

    public function handle(): int
    {
        $flags = DB::table('feature_flags')
            ->orderBy('key')
            ->get(['key', 'name', 'is_enabled', 'rollout_strategy', 'rollout_percentage']);

        if ($flags->isEmpty()) {
            $this->warn('No feature flags found.');
            return self::SUCCESS;
        }

        $rows = $flags->map(function ($flag) {
            return [
                'key' => $flag->key,
                'name' => $flag->name,
                'enabled' => $flag->is_enabled ? '✓' : '✗',
                'strategy' => $flag->rollout_strategy,
                'percentage' => $flag->rollout_percentage ?? 'N/A',
            ];
        })->toArray();

        $this->table(
            ['Key', 'Name', 'Enabled', 'Strategy', 'Percentage'],
            $rows
        );

        return self::SUCCESS;
    }
}
