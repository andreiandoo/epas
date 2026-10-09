<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/**
 * Jurnalizeaza actiunile facute automat de sistem (cron, cascade), ca sa apara
 * in istoricul de activitate alaturi de cele facute de oameni.
 *
 * Doua cai:
 *  - run():  pentru modificari prin Eloquent — activitatea scrisa de LogsActivity
 *            e marcata cu `properties.automation` si ramane fara autor
 *            (modelul apeleaza tag() din tapActivity).
 *  - log():  pentru modificari in masa (DB::table / query update), care ocolesc
 *            evenimentele de model si deci nu lasa nicio urma.
 */
class AutomatedActivity
{
    public const TICKET_SCHEDULED_ACTIVATION = 'ticket_scheduled_activation';
    public const TICKET_AUTOSTART_PREVIOUS_SOLD_OUT = 'ticket_autostart_previous_sold_out';
    public const TICKET_AVAILABILITY_ENDED = 'ticket_availability_ended';
    public const TICKET_DISCOUNT_STARTED = 'ticket_discount_started';
    public const TICKET_DISCOUNT_ENDED = 'ticket_discount_ended';
    public const TICKET_DISCOUNT_STOCK_DEPLETED = 'ticket_discount_stock_depleted';
    public const EVENT_ENDED = 'event_ended';
    public const EVENT_FEATURING_EXPIRED = 'event_featuring_expired';

    protected static ?string $current = null;

    protected static array $context = [];

    /**
     * Ruleaza $callback marcand orice activitate scrisa de modele ca automata.
     */
    public static function run(string $action, callable $callback, array $context = []): mixed
    {
        [$previousAction, $previousContext] = [static::$current, static::$context];
        static::$current = $action;
        static::$context = $context;

        try {
            return $callback();
        } finally {
            static::$current = $previousAction;
            static::$context = $previousContext;
        }
    }

    public static function current(): ?string
    {
        return static::$current;
    }

    /**
     * De apelat din tapActivity() al modelului.
     */
    public static function tag(Activity $activity): void
    {
        if (static::$current === null) {
            return;
        }

        $activity->properties = $activity->properties
            ->merge(static::$context)
            ->put('automation', static::$current);
        $activity->causer_id = null;
        $activity->causer_type = null;
    }

    /**
     * Scrie direct o activitate automata pentru $subject.
     *
     * @param array $old  valorile dinainte (doar campurile schimbate)
     * @param array $new  valorile de dupa
     */
    public static function log(Model $subject, string $action, array $old = [], array $new = [], array $context = [], ?string $logName = null): void
    {
        try {
            $properties = array_merge($context, ['automation' => $action]);
            if (! empty($new)) {
                $properties['old'] = $old;
                $properties['attributes'] = $new;
            }

            activity($logName)
                ->performedOn($subject)
                ->causedByAnonymous()
                ->event('updated')
                ->withProperties($properties)
                ->log("Automated: {$action}");
        } catch (\Throwable $e) {
            // Jurnalul nu are voie sa opreasca automatizarea propriu-zisa.
            Log::warning('AutomatedActivity: could not write activity', [
                'action' => $action,
                'subject' => get_class($subject) . '#' . $subject->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * A fost deja jurnalizata actiunea pentru acest subiect de la $since incoace?
     * Folosit pentru momentele care nu schimba nimic in baza de date (ex. inceputul
     * unei reduceri programate), ca sa fie scrise o singura data.
     */
    public static function alreadyLogged(Model $subject, string $action, \DateTimeInterface $since): bool
    {
        return ActivityModel::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('created_at', '>=', $since)
            ->where('properties->automation', $action)
            ->exists();
    }
}
