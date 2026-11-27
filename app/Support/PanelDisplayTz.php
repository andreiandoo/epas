<?php

namespace App\Support;

use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;

/**
 * Fus orar implicit la AFIȘARE pentru coloanele/intrările de text din panourile
 * marketplace și tenant. Nu atinge DateTimePicker/DatePicker/TimePicker și nu
 * apelează FilamentTimezone::set(), deci valorile salvate din formulare nu se schimbă.
 *
 * Se aplică doar câmpurilor care sunt momente reale generate de sistem (lista de
 * mai jos). Câmpurile introduse manual din pickere (starts_at, expires_at, published_at…)
 * sunt stocate ca „ceas de perete” și rămân neatinse până la pasul separat de pickere.
 * O coloană care setează explicit ->timezone()/dateTime(..., tz) își păstrează fusul.
 */
class PanelDisplayTz
{
    private static bool $registered = false;

    /** Câmpuri-instant generate de sistem. */
    public const INSTANT_FIELDS = [
        'created_at', 'updated_at', 'deleted_at',
        'sent_at', 'paid_at', 'processed_at', 'approved_at', 'rejected_at', 'accepted_at',
        'verified_at', 'requested_at', 'activated_at', 'redeemed_at', 'completed_at', 'read_at',
        'submitted_at', 'subscribed_at', 'generated_at', 'checked_in_at', 'held_until',
        'last_login_at', 'last_used_at', 'last_activity_at', 'last_accessed_at', 'last_synced_at',
        'opened_at', 'closed_at', 'earned_at', 'last_earned_at', 'last_spent_at',
        'last_xp_earned_at', 'voucher_used_at', 'claim_submitted_at', 'allocated_at',
    ];

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        TextColumn::configureUsing(function (TextColumn $column): void {
            $column->timezone(fn (TextColumn $c): ?string => self::resolve($c->getName()));
        });

        TextEntry::configureUsing(function (TextEntry $entry): void {
            $entry->timezone(fn (TextEntry $e): ?string => self::resolve($e->getName()));
        });
    }

    private static function resolve(?string $name): ?string
    {
        $field = $name ? last(explode('.', $name)) : null;
        if (! in_array($field, self::INSTANT_FIELDS, true)) {
            return null;
        }

        try {
            return match (Filament::getCurrentPanel()?->getId()) {
                'marketplace' => MarketplaceTz::tz(),
                'tenant' => TenantTz::tz(),
                default => null,
            };
        } catch (\Throwable $e) {
            return null;
        }
    }
}
