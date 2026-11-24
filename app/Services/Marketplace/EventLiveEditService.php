<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\MarketplaceEventCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies organizer event edits — the "safe" subset that is allowed on a LIVE
 * (published) event. Deliberately excludes ticket-type restructuring, which is
 * handled separately (and never on a live event, since deleting + recreating
 * ticket types would destroy already-sold tickets).
 *
 * Shared by:
 *  - the organizer API (EventsController) for draft edits + live edits
 *  - the pending-changes approval flow (organizer without allow_live_edits →
 *    changes parked on the event, applied here when a marketplace admin
 *    approves them).
 */
class EventLiveEditService
{
    /**
     * Map the validated organizer payload to Event column values. Pure — no
     * side effects. Only keys present in $validated are mapped, so callers can
     * pass a partial set.
     */
    public function mapToEventData(array $validated, Event $event): array
    {
        $updateData = [];

        if (isset($validated['name'])) {
            $updateData['title'] = ['ro' => $validated['name'], 'en' => $validated['name']];
        }
        if (array_key_exists('description', $validated)) {
            $desc = $validated['description'] ?? '';
            $updateData['description'] = ['ro' => $desc, 'en' => $desc];
        }
        if (array_key_exists('ticket_terms', $validated)) {
            $terms = $validated['ticket_terms'] ?? '';
            $updateData['ticket_terms'] = ['ro' => $terms, 'en' => $terms];
        }
        if (array_key_exists('thank_you_message', $validated)) {
            $tym = $validated['thank_you_message'] ?? '';
            $updateData['thank_you_message'] = ($tym !== '' && $tym !== null)
                ? ['ro' => $tym, 'en' => $tym]
                : null;
        }
        if (array_key_exists('short_description', $validated)) {
            $short = $validated['short_description'] ?? '';
            $updateData['short_description'] = ['ro' => $short, 'en' => $short];
        }

        $durationMode = $validated['duration_mode'] ?? $event->duration_mode ?? 'single_day';
        if (isset($validated['duration_mode'])) {
            $updateData['duration_mode'] = $durationMode;
        }

        if (isset($validated['starts_at']) || isset($validated['ends_at'])) {
            if ($durationMode === 'range') {
                $updateData['event_date'] = null;
                $updateData['start_time'] = null;
                $updateData['end_time'] = null;
                $updateData['door_time'] = null;
                if (isset($validated['starts_at'])) {
                    $startsAt = Carbon::parse($validated['starts_at']);
                    $updateData['range_start_date'] = $startsAt->toDateString();
                    $updateData['range_start_time'] = $startsAt->format('H:i');
                }
                if (isset($validated['ends_at'])) {
                    $endsAt = Carbon::parse($validated['ends_at']);
                    $updateData['range_end_date'] = $endsAt->toDateString();
                    $updateData['range_end_time'] = $endsAt->format('H:i');
                }
            } else {
                $updateData['range_start_date'] = null;
                $updateData['range_end_date'] = null;
                $updateData['range_start_time'] = null;
                $updateData['range_end_time'] = null;
                if (isset($validated['starts_at'])) {
                    $startsAt = Carbon::parse($validated['starts_at']);
                    $updateData['event_date'] = $startsAt->toDateString();
                    $updateData['start_time'] = $startsAt->format('H:i');
                }
                if (isset($validated['ends_at'])) {
                    $endsAt = Carbon::parse($validated['ends_at']);
                    $updateData['end_time'] = $endsAt->format('H:i');
                }
            }
        }
        if (isset($validated['doors_open_at'])) {
            $updateData['door_time'] = Carbon::parse($validated['doors_open_at'])->format('H:i');
        }
        if (isset($validated['venue_id'])) {
            $updateData['venue_id'] = $validated['venue_id'];
            $updateData['suggested_venue_name'] = null;
        } elseif (isset($validated['venue_name'])) {
            $updateData['suggested_venue_name'] = $validated['venue_name'];
        }
        if (isset($validated['venue_address'])) {
            $updateData['address'] = $validated['venue_address'];
        }
        foreach (['marketplace_event_category_id', 'website_url', 'event_website_url', 'facebook_url', 'video_url'] as $key) {
            if (isset($validated[$key])) {
                $updateData[$key] = $validated[$key];
            }
        }

        return $updateData;
    }

    /**
     * Sync the relation fields (category-driven event types, genres, artists).
     */
    public function syncRelations(Event $event, array $validated): void
    {
        if (isset($validated['marketplace_event_category_id'])) {
            $category = MarketplaceEventCategory::find($validated['marketplace_event_category_id']);
            if ($category && !empty($category->event_type_ids)) {
                $event->eventTypes()->sync($category->event_type_ids);
            }
        }
        if (isset($validated['genre_ids'])) {
            $event->eventGenres()->sync($validated['genre_ids']);
        }
        if (isset($validated['artist_ids'])) {
            $event->artists()->sync($validated['artist_ids']);
        }
    }

    /**
     * Apply the safe fields + relations to the event immediately (used for
     * drafts, allow_live_edits organizers, and admin approval of pending
     * changes). Existing ticket types are NEVER modified on a live event
     * (would corrupt sold orders), but the organizer IS allowed to APPEND
     * new ticket types — the frontend renders existing rows read-only and
     * only submits payload rows for cards the operator explicitly added
     * via "Adaugă alt tip de bilet".
     *
     * Additive-only guard: for every ticket_types row in the payload, we
     * skip when an active ticket type with the same name already exists
     * on the event. That way a stale/replayed submit can never duplicate
     * or overwrite an existing type.
     */
    public function applyFields(Event $event, array $validated): void
    {
        DB::transaction(function () use ($event, $validated) {
            $updateData = $this->mapToEventData($validated, $event);
            if (!empty($updateData)) {
                $event->update($updateData);
            }
            $this->syncRelations($event, $validated);
            $this->appendNewTicketTypes($event, $validated['ticket_types'] ?? []);
        });
    }

    /**
     * Create NEW ticket types on the event (never modifies existing ones).
     * Called from applyFields — safe on both draft and live events since it
     * only inserts, never updates. Existing types are matched by
     * case-insensitive name so a resubmit of the same payload is idempotent.
     */
    public function appendNewTicketTypes(Event $event, array $rows): void
    {
        if (empty($rows)) {
            return;
        }
        // Pre-load current type names once so we can dedupe without N queries.
        $existingNames = $event->ticketTypes()
            ->pluck('name')
            ->map(fn ($n) => is_array($n) ? ($n['ro'] ?? $n['en'] ?? reset($n)) : $n)
            ->filter()
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->all();
        $existingSet = array_flip($existingNames);

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($existingSet[$key])) {
                // Already on the event (either pre-existing or added earlier
                // in this same loop) — skip. Keeps additive-only semantics.
                continue;
            }
            $created = \App\Models\TicketType::create([
                'event_id' => $event->id,
                'name' => $name,
                'description' => $row['description'] ?? null,
                'currency' => 'RON',
                'price_max' => $row['price'] ?? 0,
                'capacity' => $row['quantity'] ?? null, // null becomes -1 = unlimited
                'min_per_order' => $row['min_per_order'] ?? 1,
                'max_per_order' => $row['max_per_order'] ?? 10,
                'quota_sold' => 0,
                'status' => 'active',
            ]);
            $existingSet[$key] = true;

            // Bifa „Vinde și la intrare” pe un bilet nou → geamănul POS, creat odată cu el.
            $pos = $row['pos'] ?? null;
            if (is_array($pos) && !empty($pos['enabled'])) {
                $twin = PosTwinTicketService::createTwin(
                    $created,
                    $pos['name'] ?? null,
                    $pos['price'] ?? null,
                    $pos['quantity'] ?? null,
                    array_key_exists('quantity', $pos)
                );
                $existingSet[mb_strtolower(PosTwinTicketService::plainName($twin))] = true;
            }
        }
    }

    /**
     * Park a live edit as a pending change set — the live event is untouched
     * until an admin approves. Existing ticket_types are stripped (the
     * additive-only path only accepts new types AFTER apply/approve).
     */
    public function storePending(Event $event, array $validated): void
    {
        // Parked ticket_types stay in the payload so the admin approval
        // step (approvePending → applyFields) can insert them via the
        // additive-only guard above. Prior behaviour dropped them
        // entirely — that was safe when applyFields refused ticket_types
        // outright, but now the append path can handle them so we keep
        // them for the admin to review.
        // The form sends back every field, not just the edited ones. _base keeps
        // what the event looked like at submit time, so the review can tell
        // what the organizer actually changed.
        unset($validated[self::BASE_KEY]);
        $validated[self::BASE_KEY] = $this->snapshot($event, $validated);

        $event->update([
            'pending_changes' => $validated,
            'pending_changes_status' => 'pending',
            'pending_changes_submitted_at' => now(),
        ]);
    }

    /**
     * Approve parked changes: apply only what the organizer changed (see
     * diffPending), then clear the pending state. Fields the organizer left
     * alone are not touched, so edits made from admin meanwhile survive.
     */
    public function approvePending(Event $event): void
    {
        $diff = $this->diffPending($event);

        DB::transaction(function () use ($event, $diff) {
            $apply = $diff['apply'];

            if (!empty($apply['columns'])) {
                $event->update($apply['columns']);
            }
            if (!empty($apply['category_changed'])) {
                $category = MarketplaceEventCategory::find($apply['columns']['marketplace_event_category_id'] ?? null);
                if ($category && !empty($category->event_type_ids)) {
                    $event->eventTypes()->sync($category->event_type_ids);
                }
            }
            if (array_key_exists('genre_ids', $apply)) {
                $event->eventGenres()->sync($apply['genre_ids']);
            }
            if (array_key_exists('artist_ids', $apply)) {
                $event->artists()->sync($apply['artist_ids']);
            }
            if (!empty($apply['ticket_types'])) {
                $this->appendNewTicketTypes($event, $apply['ticket_types']);
            }

            $event->update([
                'pending_changes' => null,
                'pending_changes_status' => null,
                'pending_changes_submitted_at' => null,
            ]);
        });
    }

    public const BASE_KEY = '_base';

    private const TRANSLATED = ['title', 'description', 'ticket_terms', 'thank_you_message', 'short_description'];
    private const LONG_TEXT = ['description', 'ticket_terms', 'thank_you_message', 'short_description'];
    private const DATES = ['event_date', 'range_start_date', 'range_end_date'];
    private const TIMES = ['start_time', 'end_time', 'door_time', 'range_start_time', 'range_end_time'];

    private const LABELS = [
        'title' => 'Titlu',
        'short_description' => 'Descriere scurtă',
        'description' => 'Descriere',
        'ticket_terms' => 'Termeni bilete',
        'thank_you_message' => 'Mesaj de mulțumire',
        'duration_mode' => 'Tip durată',
        'event_date' => 'Data',
        'start_time' => 'Ora început',
        'end_time' => 'Ora sfârșit',
        'door_time' => 'Deschidere uși',
        'range_start_date' => 'Data început',
        'range_start_time' => 'Ora început',
        'range_end_date' => 'Data sfârșit',
        'range_end_time' => 'Ora sfârșit',
        'venue_id' => 'Locație',
        'suggested_venue_name' => 'Locație propusă (text)',
        'address' => 'Adresă',
        'marketplace_event_category_id' => 'Categorie',
        'website_url' => 'Website',
        'event_website_url' => 'Website eveniment',
        'facebook_url' => 'Facebook',
        'video_url' => 'Video',
    ];

    /**
     * What the organizer changed in the parked set, compared to the event as
     * it was at submit time (_base) — or to the live event for sets parked
     * before _base existed. Read-only.
     *
     * Returns:
     *  - rows: for display (label, before, after, long-text word diff, conflict)
     *  - apply: exactly what approvePending writes
     *  - has_base: false for old sets (compared against live)
     */
    public function diffPending(Event $event): array
    {
        $pending = is_array($event->pending_changes) ? $event->pending_changes : [];
        $base = is_array($pending[self::BASE_KEY] ?? null) ? $pending[self::BASE_KEY] : null;
        unset($pending[self::BASE_KEY]);

        $rows = [];
        $apply = ['columns' => []];

        $mapped = $pending ? $this->mapToEventData($pending, $event) : [];
        foreach ($mapped as $column => $newRaw) {
            if ($column === 'suggested_venue_name') {
                continue; // handled with venue_id below
            }
            $new = $this->comparable($column, $newRaw);
            $live = $this->comparable($column, $event->getAttribute($column));
            $was = $base !== null && array_key_exists($column, $base) ? $base[$column] : $live;

            if ($new === $was || $new === $live) {
                continue;
            }

            $apply['columns'][$column] = in_array($column, self::TRANSLATED, true)
                ? $this->mergeTranslation($event->getAttribute($column), $newRaw)
                : $newRaw;

            $rows[] = $this->row($column, $live, $new, $base !== null && $live !== $was);
        }

        // A new venue clears the free-text suggestion; a new suggestion alone is shown as such.
        if (array_key_exists('venue_id', $apply['columns'])) {
            $apply['columns']['suggested_venue_name'] = null;
        } elseif (!empty($mapped['suggested_venue_name'])) {
            $new = $this->comparable('suggested_venue_name', $mapped['suggested_venue_name']);
            $live = $this->comparable('suggested_venue_name', $event->suggested_venue_name);
            $was = $base['suggested_venue_name'] ?? $live;
            if ($new !== $was && $new !== $live) {
                $apply['columns']['suggested_venue_name'] = $mapped['suggested_venue_name'];
                $rows[] = $this->row('suggested_venue_name', $live, $new, $base !== null && $live !== $was);
            }
        }

        $apply['category_changed'] = array_key_exists('marketplace_event_category_id', $apply['columns']);

        // Genres / artists — compared as id sets.
        $relations = [
            'genre_ids' => ['Genuri', $event->eventGenres()->pluck('event_genres.id')->all(), \App\Models\EventGenre::class],
            'artist_ids' => ['Artiști', $event->artists()->pluck('artists.id')->all(), \App\Models\Artist::class],
        ];
        foreach ($relations as $key => [$label, $liveIds, $model]) {
            if (!isset($pending[$key]) || !is_array($pending[$key])) {
                continue;
            }
            $new = $this->idSet($pending[$key]);
            $live = $this->idSet($liveIds);
            $was = $base !== null && array_key_exists($key, $base) ? $this->idSet($base[$key]) : $live;
            if ($new === $was || $new === $live) {
                continue;
            }
            $apply[$key] = $new;

            $names = $model::whereIn('id', array_merge($new, $live))->get()
                ->mapWithKeys(fn ($m) => [$m->id => $this->plain($m->name)]);
            $rows[] = [
                'label' => $label,
                'type' => 'set',
                'added' => array_values(array_map(fn ($id) => $names[$id] ?? "#{$id}", array_diff($new, $live))),
                'removed' => array_values(array_map(fn ($id) => $names[$id] ?? "#{$id}", array_diff($live, $new))),
                'conflict' => $base !== null && $live !== $was,
            ];
        }

        // New ticket types — same name rule as appendNewTicketTypes.
        $existing = $event->ticketTypes()->pluck('name')
            ->map(fn ($n) => mb_strtolower(trim((string) $this->plain($n))))
            ->flip();
        $newTypes = [];
        foreach ((array) ($pending['ticket_types'] ?? []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || isset($existing[mb_strtolower($name)])) {
                continue;
            }
            $existing[mb_strtolower($name)] = true;
            $newTypes[] = $row;
        }
        if ($newTypes) {
            $apply['ticket_types'] = $newTypes;
            $rows[] = ['label' => 'Tipuri de bilet noi', 'type' => 'tickets', 'tickets' => $newTypes, 'conflict' => false];
        }

        return ['rows' => $rows, 'apply' => $apply, 'has_base' => $base !== null];
    }

    /** Comparable values of every field the payload would touch, as they are now. */
    private function snapshot(Event $event, array $validated): array
    {
        $snap = [];
        foreach (array_keys($this->mapToEventData($validated, $event)) as $column) {
            $snap[$column] = $this->comparable($column, $event->getAttribute($column));
        }
        $snap['genre_ids'] = $this->idSet($event->eventGenres()->pluck('event_genres.id')->all());
        $snap['artist_ids'] = $this->idSet($event->artists()->pluck('artists.id')->all());

        return $snap;
    }

    private function comparable(string $column, $value): string
    {
        if (in_array($column, self::TRANSLATED, true)) {
            $text = (string) $this->plain($value);
            return trim(preg_replace('/\s+/u', ' ', str_replace('&nbsp;', ' ', $text)));
        }
        if ($value === null || $value === '') {
            return '';
        }
        if (in_array($column, self::DATES, true)) {
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                return (string) $value;
            }
        }
        if (in_array($column, self::TIMES, true)) {
            return substr((string) $value, 0, 5);
        }

        return trim((string) $value);
    }

    /** Romanian text of a translatable value (ro, then en, then the first one). */
    private function plain($value): ?string
    {
        if (is_array($value)) {
            foreach (['ro', 'en'] as $loc) {
                if (isset($value[$loc]) && $value[$loc] !== '') {
                    return (string) $value[$loc];
                }
            }
            $first = collect($value)->first(fn ($v) => $v !== null && $v !== '');
            return $first === null ? null : (string) $first;
        }

        return $value === null ? null : (string) $value;
    }

    /**
     * New ro text on top of the existing translations. en follows ro only when
     * it was empty or a copy of the old ro — a real English text is kept.
     */
    private function mergeTranslation($current, $new): ?array
    {
        $newRo = is_array($new) ? ($new['ro'] ?? null) : $new;
        if ($newRo === null || $newRo === '') {
            return null;
        }
        $current = is_array($current) ? $current : ($current ? ['ro' => (string) $current] : []);
        $oldRo = $current['ro'] ?? null;
        $oldEn = $current['en'] ?? null;

        $current['ro'] = $newRo;
        if ($oldEn === null || $oldEn === '' || $oldEn === $oldRo) {
            $current['en'] = $newRo;
        }

        return $current;
    }

    private function idSet($ids): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter((array) $ids, fn ($v) => $v !== null && $v !== ''))));
        sort($ids);

        return $ids;
    }

    private function row(string $column, string $before, string $after, bool $conflict): array
    {
        $row = [
            'label' => self::LABELS[$column] ?? $column,
            'type' => in_array($column, self::LONG_TEXT, true) ? 'text' : 'value',
            'before' => $this->display($column, $before),
            'after' => $this->display($column, $after),
            'conflict' => $conflict,
        ];
        if ($row['type'] === 'text') {
            [$row['before_html'], $row['after_html']] = $this->wordDiff($before, $after);
            $row['format_only'] = $row['before'] === $row['after'];
        }

        return $row;
    }

    private function display(string $column, string $value): string
    {
        if ($value === '') {
            return '';
        }

        return match (true) {
            $column === 'venue_id' => (string) ($this->plain(\App\Models\Venue::find($value)?->name) ?? "#{$value}"),
            $column === 'marketplace_event_category_id' => (string) ($this->plain(MarketplaceEventCategory::find($value)?->name) ?? "#{$value}"),
            $column === 'duration_mode' => match ($value) {
                'single_day' => 'O zi', 'range' => 'Interval', 'multi_day' => 'Mai multe zile', default => $value,
            },
            in_array($column, self::DATES, true) => rescue(fn () => Carbon::parse($value)->format('d.m.Y'), $value, false),
            in_array($column, self::TRANSLATED, true) => $this->toText($value),
            default => $value,
        };
    }

    /** HTML → readable plain text (paragraphs and line breaks kept as new lines). */
    private function toText(string $html): string
    {
        $text = preg_replace('#<\s*br\s*/?>#i', "\n", $html);
        $text = preg_replace('#</\s*(p|div|li|h[1-6])\s*>#i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/u", ' ', $text);

        return trim(preg_replace("/\n\s*\n+/u", "\n", $text));
    }

    /**
     * Word-level diff of two texts → [beforeHtml, afterHtml] with removed words
     * in <del> and added ones in <ins>. Common head/tail are skipped before the
     * LCS; a very long changed middle is marked as a whole.
     */
    private function wordDiff(string $before, string $after): array
    {
        $split = fn (string $s) => preg_split('/(?<=\s)(?=\S)/u', $this->toText($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $a = $split($before);
        $b = $split($after);
        $key = fn ($t) => trim($t);

        $head = 0;
        while ($head < count($a) && $head < count($b) && $key($a[$head]) === $key($b[$head])) {
            $head++;
        }
        $tail = 0;
        while ($tail < count($a) - $head && $tail < count($b) - $head
            && $key($a[count($a) - 1 - $tail]) === $key($b[count($b) - 1 - $tail])) {
            $tail++;
        }
        $midA = array_slice($a, $head, count($a) - $head - $tail);
        $midB = array_slice($b, $head, count($b) - $head - $tail);

        $esc = fn (array $tokens) => nl2br(e(implode('', $tokens)));
        $mark = fn (array $tokens, string $tag) => $tokens ? "<{$tag}>" . $esc($tokens) . "</{$tag}>" : '';

        $outA = $esc(array_slice($a, 0, $head));
        $outB = $esc(array_slice($b, 0, $head));

        $n = count($midA);
        $m = count($midB);
        if ($n * $m > 400000) {
            $outA .= $mark($midA, 'del');
            $outB .= $mark($midB, 'ins');
        } else {
            // LCS lengths, then walk forward grouping runs.
            $L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
            for ($i = $n - 1; $i >= 0; $i--) {
                for ($j = $m - 1; $j >= 0; $j--) {
                    $L[$i][$j] = $key($midA[$i]) === $key($midB[$j])
                        ? $L[$i + 1][$j + 1] + 1
                        : max($L[$i + 1][$j], $L[$i][$j + 1]);
                }
            }
            $i = $j = 0;
            $same = $sameB = $del = $ins = [];
            $flush = function () use (&$same, &$sameB, &$del, &$ins, &$outA, &$outB, $esc, $mark) {
                $outA .= $mark($del, 'del');
                $outB .= $mark($ins, 'ins');
                $outA .= $esc($same);
                $outB .= $esc($sameB);
                $same = $sameB = $del = $ins = [];
            };
            while ($i < $n || $j < $m) {
                if ($i < $n && $j < $m && $key($midA[$i]) === $key($midB[$j])) {
                    if ($del || $ins) {
                        $flush();
                    }
                    $same[] = $midA[$i];
                    $sameB[] = $midB[$j];
                    $i++;
                    $j++;
                } elseif ($j < $m && ($i >= $n || $L[$i][$j + 1] >= $L[$i + 1][$j])) {
                    if ($same) {
                        $flush();
                    }
                    $ins[] = $midB[$j++];
                } else {
                    if ($same) {
                        $flush();
                    }
                    $del[] = $midA[$i++];
                }
            }
            $flush();
        }

        $tailTokensA = array_slice($a, count($a) - $tail);
        $tailTokensB = array_slice($b, count($b) - $tail);

        return [$outA . $esc($tailTokensA), $outB . $esc($tailTokensB)];
    }

    /**
     * Reject parked changes: discard them, leave the live event as-is.
     */
    public function rejectPending(Event $event): void
    {
        $event->update([
            'pending_changes' => null,
            'pending_changes_status' => 'rejected',
            'pending_changes_submitted_at' => null,
        ]);
    }
}
