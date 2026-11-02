<?php

namespace App\Support\Marketplace;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Bulk delete for models with deletionBlocker(): removes what it can and
 * says what stayed and why, instead of failing on the first protected row.
 */
class SafeBulkDelete
{
    public static function run(Collection $records, callable $label): void
    {
        $deleted = 0;
        $kept = [];

        foreach ($records as $record) {
            $blocker = $record->deletionBlocker();

            if ($blocker) {
                $kept[] = $label($record) . ': ' . $blocker;

                continue;
            }

            try {
                $record->delete();
                $deleted++;
            } catch (\Throwable $e) {
                report($e);
                $kept[] = $label($record) . ': nu a putut fi șters.';
            }
        }

        if (empty($kept)) {
            Notification::make()->title("Șterse: {$deleted}")->success()->send();

            return;
        }

        $shown = collect($kept)->take(5)->map(fn ($line) => Str::limit($line, 120))->implode(' · ');
        $more = count($kept) > 5 ? ' · și încă ' . (count($kept) - 5) : '';

        Notification::make()
            ->title("Șterse: {$deleted}. Păstrate: " . count($kept))
            ->body($shown . $more)
            ->warning()
            ->persistent()
            ->send();
    }
}
