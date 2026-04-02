<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestivalClosureChecklist extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'status',
        'started_at', 'completed_at', 'started_by', 'steps', 'meta',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'steps'        => 'array',
        'meta'         => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }
    public function startedByUser(): BelongsTo { return $this->belongsTo(User::class, 'started_by'); }

    public static function defaultSteps(): array
    {
        return [
            ['id' => '1.1', 'phase' => 'stop_operations', 'label' => 'Announce vendors: last 30 minutes', 'done' => false],
            ['id' => '1.2', 'phase' => 'stop_operations', 'label' => 'Stop top-ups (online + physical)', 'done' => false],
            ['id' => '1.3', 'phase' => 'stop_operations', 'label' => 'Stop POS charges', 'done' => false],
            ['id' => '1.4', 'phase' => 'stop_operations', 'label' => 'Force-close all active shifts', 'done' => false],
            ['id' => '2.1', 'phase' => 'reconciliation', 'label' => 'Force sync all offline POS', 'done' => false],
            ['id' => '2.2', 'phase' => 'reconciliation', 'label' => 'Verify 0 pending syncs', 'done' => false],
            ['id' => '2.3', 'phase' => 'reconciliation', 'label' => 'Run final reconciliation', 'done' => false],
            ['id' => '3.1', 'phase' => 'stock_returns', 'label' => 'Collect vendor stock reports', 'done' => false],
            ['id' => '3.2', 'phase' => 'stock_returns', 'label' => 'Process stock returns to festival', 'done' => false],
            ['id' => '3.3', 'phase' => 'stock_returns', 'label' => 'Log waste/losses per vendor', 'done' => false],
            ['id' => '4.1', 'phase' => 'finance', 'label' => 'Calculate final vendor summaries', 'done' => false],
            ['id' => '4.2', 'phase' => 'finance', 'label' => 'Review fee rules applied', 'done' => false],
            ['id' => '4.3', 'phase' => 'finance', 'label' => 'Generate payout reports', 'done' => false],
            ['id' => '5.1', 'phase' => 'cashouts', 'label' => 'Process remaining physical cashouts', 'done' => false],
            ['id' => '5.2', 'phase' => 'cashouts', 'label' => 'Queue auto-cashout for remaining balances', 'done' => false],
            ['id' => '5.3', 'phase' => 'cashouts', 'label' => 'Send end-of-festival notification to customers', 'done' => false],
            ['id' => '6.1', 'phase' => 'reports', 'label' => 'Generate final edition report', 'done' => false],
            ['id' => '6.2', 'phase' => 'reports', 'label' => 'Archive NFC keys', 'done' => false],
            ['id' => '6.3', 'phase' => 'reports', 'label' => 'Mark edition as completed', 'done' => false],
        ];
    }

    public function markStepDone(string $stepId): void
    {
        $steps = $this->steps ?? self::defaultSteps();
        foreach ($steps as &$step) {
            if ($step['id'] === $stepId) {
                $step['done'] = true;
                $step['done_at'] = now()->toIso8601String();
                break;
            }
        }
        $this->update(['steps' => $steps]);
    }

    public function completionPercentage(): int
    {
        $steps = $this->steps ?? [];
        if (empty($steps)) return 0;
        $done = count(array_filter($steps, fn ($s) => $s['done'] ?? false));
        return (int) round($done / count($steps) * 100);
    }
}
