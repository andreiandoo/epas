<?php

namespace App\Services\Marketplace;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\EventGeneratedDocument;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceEmailTemplate;
use App\Models\MarketplaceNotification;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceTaxRegistry;
use App\Models\MarketplaceTaxTemplate;
use App\Models\OrganizerDocument;
use App\Services\OrganizerNotificationService;
use App\Support\MarketplaceTz;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The fiscal documents an organizer issues for an event from their own
 * account (/organizator/documente):
 *
 *   - cerere de vizare: after the event is published, generated and filed
 *     at least CERERE_DAYS_BEFORE days before it starts. Any later change of
 *     prices, stock or discount codes makes it outdated (fingerprint).
 *   - impozit + PV distrugere: after the event ends, generated together and
 *     filed together by the first 10th after the event. A cancelled event
 *     only needs the PV.
 *
 * Generation goes through EventGeneratedDocument::generateDocument, the same
 * code the operators use, so the event's Documente tab in the marketplace
 * admin shows them as if an operator had made them.
 */
class OrganizerFiscalDocuments
{
    public const GROUP_CERERE = EventDocumentFilingService::GROUP_CERERE;

    public const GROUP_IMPOZIT_PV = EventDocumentFilingService::GROUP_IMPOZIT_PV;

    public const TYPES = ['cerere_avizare', 'declaratie_impozite', 'pv_distrugere'];

    public const LABELS = EventDocumentFilingService::TYPE_LABELS;

    /** Fields of a ticket type that end up on the cerere de vizare. */
    public const DECLARED_TICKET_FIELDS = [
        'name', 'price_cents', 'sale_price_cents', 'display_price', 'quota_total',
        'series_start', 'series_end', 'discount_percent', 'is_declarable', 'is_subscription',
    ];

    /** The "cerere missing" notice goes out once the event is this close. */
    public const CERERE_NOTICE_DAYS = 30;

    protected static ?bool $noticesTable = null;

    public function __construct(
        protected EventDocumentFilingService $filing,
        protected SeriesAllocator $series,
    ) {
    }

    // ------------------------------------------------------------------
    // Dates
    // ------------------------------------------------------------------

    /** First day of the event, in the marketplace timezone. */
    public function startDay(Event $event): ?Carbon
    {
        $date = ($event->is_postponed && $event->postponed_date) ? $event->postponed_date : $event->start_date;
        if (! $date) {
            return null;
        }

        return Carbon::parse(Carbon::parse($date)->format('Y-m-d'), MarketplaceTz::tz($event->marketplaceClient));
    }

    /** Last day of the event, in the marketplace timezone. */
    public function endDay(Event $event): ?Carbon
    {
        $tz = MarketplaceTz::tz($event->marketplaceClient);
        $end = $event->getEffectiveEndDatetime();
        if ($end) {
            return Carbon::parse($end->copy()->setTimezone($tz)->format('Y-m-d'), $tz);
        }

        return $this->startDay($event);
    }

    /** Last day to file the cerere de vizare. */
    public function cerereDeadline(Event $event): ?Carbon
    {
        return $this->startDay($event)?->subDays(OpsBoardService::CERERE_DAYS_BEFORE);
    }

    /** Last day to file impozit + PV: the first 10th after the event. */
    public function taxDeadline(Event $event): ?Carbon
    {
        $end = $this->endDay($event);
        if (! $end) {
            return null;
        }
        $due = $end->copy()->startOfMonth();
        if ($end->day >= OpsBoardService::TAX_DUE_DAY) {
            $due->addMonthNoOverflow();
        }

        return $due->day(OpsBoardService::TAX_DUE_DAY);
    }

    public function isEnded(Event $event): bool
    {
        return $event->isPast();
    }

    // ------------------------------------------------------------------
    // Rules
    // ------------------------------------------------------------------

    /** @return string[] */
    public function groupTypes(Event $event, string $group): array
    {
        return $this->filing->requiredTypes($event, $group);
    }

    /** Why the group can't be generated now, or null when it can. */
    public function blockedReason(Event $event, string $group): ?string
    {
        if ($group === self::GROUP_CERERE) {
            if ($event->is_cancelled) {
                return 'Evenimentul este anulat.';
            }
            if (! $event->is_published) {
                return 'Se poate genera după publicarea evenimentului.';
            }
            if ($this->isEnded($event)) {
                return 'Evenimentul s-a încheiat.';
            }
        } else {
            if (! $event->is_cancelled && ! $this->isEnded($event)) {
                return 'Se pot genera după încheierea evenimentului.';
            }
        }

        if (! $this->registry($event)) {
            return 'Evenimentul nu are direcția fiscală (primăria) setată. Scrie-ne și o configurăm.';
        }

        foreach ($this->groupTypes($event, $group) as $type) {
            $template = $this->template($event, $type);
            if (! $template) {
                return 'Documentul „' . self::LABELS[$type] . '” nu este încă disponibil. Scrie-ne și îl pregătim.';
            }
            if ($template->by_proxy && ! $event->marketplaceOrganizer?->proxy_admin_id) {
                return 'Documentul „' . self::LABELS[$type] . '” se emite prin împuternicit. Scrie-ne și îl pregătim.';
            }
        }

        return null;
    }

    public function registry(Event $event): ?MarketplaceTaxRegistry
    {
        return $event->marketplace_tax_registry_id
            ? MarketplaceTaxRegistry::find($event->marketplace_tax_registry_id)
            : null;
    }

    /**
     * Template the organizer generates with: a direct (not "prin
     * împuternicit") one first, then the default, then the newest.
     */
    public function template(Event $event, string $type): ?MarketplaceTaxTemplate
    {
        return MarketplaceTaxTemplate::query()
            ->where('marketplace_client_id', $event->marketplace_client_id)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN by_proxy THEN 1 ELSE 0 END')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();
    }

    public function marketplaceHasTemplates(int $marketplaceClientId): bool
    {
        return MarketplaceTaxTemplate::where('marketplace_client_id', $marketplaceClientId)
            ->whereIn('type', self::TYPES)
            ->where('is_active', true)
            ->exists();
    }

    // ------------------------------------------------------------------
    // Documents
    // ------------------------------------------------------------------

    /**
     * Every fiscal document of the event, newest first. Operator- and
     * organizer-generated ones live in event_generated_documents; older
     * organizer ones only in organizer_documents.
     *
     * @return array<int, array{ref: string, type: string, label: string, generated_at: Carbon, by: ?string, fingerprint: ?string, file_path: ?string, file_name: ?string}>
     */
    public function documents(Event $event): array
    {
        $out = [];

        $generated = EventGeneratedDocument::query()
            ->where('event_id', $event->id)
            ->with('template:id,type')
            ->get();
        $generatedIds = [];
        foreach ($generated as $doc) {
            $type = $doc->template?->type ?? ($doc->meta['template_type'] ?? null);
            if (! in_array($type, self::TYPES, true)) {
                continue;
            }
            $generatedIds[] = $doc->id;
            $out[] = [
                'ref' => 'g' . $doc->id,
                'type' => $type,
                'label' => self::LABELS[$type],
                'generated_at' => $doc->created_at,
                'by' => $doc->generated_by_name,
                'by_organizer' => $doc->generated_by_type === MarketplaceOrganizer::class,
                'fingerprint' => $doc->meta['fingerprint'] ?? null,
                'file_path' => $doc->file_path,
                'file_name' => $doc->filename,
            ];
        }

        $legacy = OrganizerDocument::query()
            ->where('event_id', $event->id)
            ->whereIn('document_type', self::TYPES)
            ->get();
        foreach ($legacy as $doc) {
            $data = $doc->document_data ?? [];
            if (($data['source'] ?? null) === 'event_generated_document') {
                // Copy of a generated document: shown once, from the source.
                if (in_array((int) ($data['source_id'] ?? 0), $generatedIds, true)) {
                    continue;
                }
            }
            $out[] = [
                'ref' => 'o' . $doc->id,
                'type' => $doc->document_type,
                'label' => self::LABELS[$doc->document_type],
                'generated_at' => $doc->created_at,
                'by' => null,
                'by_organizer' => ($data['source'] ?? null) === null,
                'fingerprint' => $data['fingerprint'] ?? null,
                'file_path' => $doc->file_path,
                'file_name' => $doc->file_name,
            ];
        }

        usort($out, fn ($a, $b) => $b['generated_at'] <=> $a['generated_at']);

        return $out;
    }

    /** Newest document per type, from documents(). */
    public function latestByType(array $documents): array
    {
        $latest = [];
        foreach ($documents as $doc) {
            $latest[$doc['type']] ??= $doc;
        }

        return $latest;
    }

    /**
     * True when the cerere no longer matches the event's tickets. A cerere
     * made before fingerprints existed can't be judged and counts as fine.
     */
    public function isStale(Event $event, ?array $cerere, ?string $currentFingerprint = null): bool
    {
        if (! $cerere || ! $cerere['fingerprint'] || $event->is_cancelled || $this->isEnded($event)) {
            return false;
        }

        return $cerere['fingerprint'] !== ($currentFingerprint ?? $this->series->fiscalFingerprint($event));
    }

    /**
     * Generate the group's documents (one, or impozit + PV together).
     *
     * @return array{success: bool, message: string}
     */
    public function generate(Event $event, string $group, MarketplaceOrganizer $organizer): array
    {
        if ($reason = $this->blockedReason($event, $group)) {
            return ['success' => false, 'message' => $reason];
        }

        $event->loadMissing(['ticketTypes', 'venue']);
        $made = [];
        try {
            foreach ($this->groupTypes($event, $group) as $type) {
                EventGeneratedDocument::generateDocument($event, $this->template($event, $type), $organizer);
                $made[] = self::LABELS[$type];
            }
        } catch (\Throwable $e) {
            Log::error('Organizer fiscal document generation failed', [
                'event_id' => $event->id,
                'group' => $group,
                'organizer_id' => $organizer->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $made
                ? 'S-a generat doar ' . implode(', ', $made) . '. Încearcă din nou pentru restul.'
                : 'Generarea a eșuat. Încearcă din nou sau scrie-ne.'];
        }

        static::flushAttention($organizer->id);

        return ['success' => true, 'message' => count($made) > 1 ? 'Documentele au fost generate.' : 'Documentul a fost generat.'];
    }

    // ------------------------------------------------------------------
    // What the page shows
    // ------------------------------------------------------------------

    public function status(Event $event): array
    {
        $documents = $this->documents($event);
        $latest = $this->latestByType($documents);
        $registry = $this->registry($event);
        $ended = $this->isEnded($event);
        $fingerprint = null;
        if (($latest['cerere_avizare']['fingerprint'] ?? null) && ! $ended && ! $event->is_cancelled) {
            $fingerprint = $this->series->fiscalFingerprint($event);
        }

        $tz = MarketplaceTz::tz($event->marketplaceClient);
        $today = Carbon::now($tz)->startOfDay();
        $fmt = fn (?Carbon $d) => $d?->format('d.m.Y');
        $docOut = fn (?array $d) => $d ? [
            'ref' => $d['ref'],
            'generated_at' => MarketplaceTz::fmt($d['generated_at'], 'd.m.Y H:i', $event->marketplaceClient),
            'by' => $d['by'],
        ] : null;

        $groups = [];
        foreach ([self::GROUP_CERERE, self::GROUP_IMPOZIT_PV] as $group) {
            $types = $this->groupTypes($event, $group);
            $filing = $this->filing->status($event, $group);
            $deadline = $group === self::GROUP_CERERE ? $this->cerereDeadline($event) : $this->taxDeadline($event);
            $docs = [];
            $allGenerated = true;
            foreach ($types as $type) {
                $docs[$type] = $docOut($latest[$type] ?? null);
                $allGenerated = $allGenerated && isset($latest[$type]);
            }
            $stale = $group === self::GROUP_CERERE && $this->isStale($event, $latest['cerere_avizare'] ?? null, $fingerprint);
            $lastFiling = collect($filing['rows'])->pluck('filing')->filter()->sortByDesc('filed_at')->first();

            $groups[$group] = [
                'label' => EventDocumentFilingService::GROUPS[$group],
                'types' => array_map(fn ($t) => ['type' => $t, 'label' => self::LABELS[$t]], $types),
                'blocked' => $this->blockedReason($event, $group),
                'documents' => $docs,
                'generated' => $allGenerated,
                'stale' => $stale,
                'filed' => $filing['filed'] && ! $stale,
                'refile' => $filing['refile'],
                'filed_at' => $lastFiling ? EventDocumentFilingService::stamp($lastFiling['filed_at'], $event) : null,
                'filed_by' => $lastFiling['by'] ?? null,
                'deadline' => $fmt($deadline),
                'overdue' => $deadline && $today->gt($deadline) && ! $filing['filed'],
                // Filed by the operators when the marketplace handles the documents.
                'can_confirm' => $allGenerated && ! $filing['filed'] && ! $stale
                    && ! $event->marketplaceOrganizer?->marketplace_manages_documents,
            ];
        }

        return [
            'event' => [
                'id' => $event->id,
                'name' => $this->text($event->title) ?: 'Eveniment',
                'date' => $fmt($this->startDay($event)),
                'end_date' => $fmt($this->endDay($event)),
                'venue' => $this->text($event->venue?->name),
                'city' => (string) ($event->venue?->city ?? ''),
                'is_published' => (bool) $event->is_published,
                'is_cancelled' => (bool) $event->is_cancelled,
                'is_ended' => $ended,
            ],
            'manages_documents' => (bool) $event->marketplaceOrganizer?->marketplace_manages_documents,
            'registry' => $registry ? [
                'name' => $registry->name,
                'subname' => $registry->subname,
                'address' => trim(implode(', ', array_filter([$registry->address, $registry->city]))),
                'method' => $registry->submission_method,
                'email' => $registry->submissionEmail(),
                'third_party_name' => $registry->third_party_name,
                'third_party_url' => $registry->third_party_url,
                'third_party_procedure' => $registry->third_party_procedure,
            ] : null,
            'groups' => $groups,
            'history' => array_map(fn ($d) => [
                'ref' => $d['ref'],
                'type' => $d['type'],
                'label' => $d['label'],
                'generated_at' => MarketplaceTz::fmt($d['generated_at'], 'd.m.Y H:i', $event->marketplaceClient),
                'by' => $d['by'],
                'current' => ($latest[$d['type']]['ref'] ?? null) === $d['ref'],
            ], $documents),
        ];
    }

    /**
     * Events of the organizer whose documents need attention now, for the
     * banner on every organizer page. Cached for a few minutes.
     *
     * @return array<int, array{event_id: int, event_name: string, issue: string}>
     */
    public function attention(MarketplaceOrganizer $organizer): array
    {
        return Cache::remember(static::attentionKey($organizer->id), 600, function () use ($organizer) {
            if (! $this->marketplaceHasTemplates((int) $organizer->marketplace_client_id)) {
                return [];
            }
            $out = [];
            foreach ($this->candidateEvents((int) $organizer->marketplace_client_id, $organizer->id) as $event) {
                if ($issue = $this->issue($event)) {
                    $out[] = ['event_id' => $event->id, 'event_name' => $this->text($event->title) ?: 'Eveniment', 'issue' => $issue['kind']];
                }
            }

            return $out;
        });
    }

    public static function flushAttention(int $organizerId): void
    {
        Cache::forget(static::attentionKey($organizerId));
    }

    protected static function attentionKey(int $organizerId): string
    {
        return 'organizer_fiscal_attention:v1:' . $organizerId;
    }

    /**
     * Published, non-cancelled events whose documents can still matter:
     * upcoming ones, or ended ones whose impozit deadline hasn't passed.
     */
    public function candidateEvents(?int $marketplaceClientId = null, ?int $organizerId = null)
    {
        $since = now()->subDays(45)->toDateString();

        return Event::query()
            ->whereNotNull('marketplace_organizer_id')
            ->when($marketplaceClientId, fn ($q) => $q->where('marketplace_client_id', $marketplaceClientId))
            ->when($organizerId, fn ($q) => $q->where('marketplace_organizer_id', $organizerId))
            ->where('is_published', true)
            ->where(fn ($q) => $q->where('is_cancelled', false)->orWhereNull('is_cancelled'))
            ->where(function ($q) use ($since) {
                $q->where('event_date', '>=', $since)
                    ->orWhere('range_end_date', '>=', $since)
                    ->orWhere('postponed_date', '>=', $since)
                    ->orWhereIn('duration_mode', ['multi_day', 'recurring']);
            })
            ->with(['marketplaceOrganizer', 'marketplaceClient', 'venue'])
            ->get()
            ->filter(function (Event $event) {
                if ($this->isEnded($event)) {
                    $deadline = $this->taxDeadline($event);

                    return $deadline && Carbon::now(MarketplaceTz::tz($event->marketplaceClient))->startOfDay()->lte($deadline);
                }

                return true;
            })
            ->values();
    }

    /**
     * The one problem an event's documents have right now, if any.
     *
     * @return array{kind: string, key: string, document?: array}|null
     */
    public function issue(Event $event): ?array
    {
        $documents = $this->documents($event);
        $latest = $this->latestByType($documents);

        if ($this->isEnded($event)) {
            foreach ($this->groupTypes($event, self::GROUP_IMPOZIT_PV) as $type) {
                if (! isset($latest[$type])) {
                    return ['kind' => 'impozit_pv_missing', 'key' => 'impozit_pv_due'];
                }
            }

            return null;
        }

        $cerere = $latest['cerere_avizare'] ?? null;
        if (! $cerere) {
            return ['kind' => 'cerere_missing', 'key' => 'cerere_missing'];
        }
        if ($this->isStale($event, $cerere)) {
            return ['kind' => 'cerere_stale', 'key' => 'cerere_stale:' . $cerere['ref'], 'document' => $cerere];
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Notices to the organizer (once per problem)
    // ------------------------------------------------------------------

    public static function noticesAvailable(): bool
    {
        return static::$noticesTable ??= Schema::hasTable('event_document_notices');
    }

    /**
     * Tell the organizer, in their account and by email, about the event's
     * document problem — unless they were already told about this one.
     * Organizers whose documents the marketplace handles get nothing.
     */
    public function notifyIfNeeded(Event $event): ?string
    {
        if (! static::noticesAvailable()) {
            return null;
        }
        $organizer = $event->marketplaceOrganizer;
        if (! $organizer || $organizer->marketplace_manages_documents || $organizer->organizer_type === 'leisure') {
            return null;
        }
        if (! $event->is_published || $event->is_cancelled || ! $this->marketplaceHasTemplates((int) $event->marketplace_client_id)) {
            return null;
        }

        $issue = $this->issue($event);
        if (! $issue) {
            return null;
        }
        if ($issue['kind'] === 'cerere_missing') {
            $start = $this->startDay($event);
            if (! $start || $start->gt(Carbon::now(MarketplaceTz::tz($event->marketplaceClient))->addDays(self::CERERE_NOTICE_DAYS))) {
                return null;
            }
        }

        // Claim the notice first: a second run racing this one inserts nothing.
        $claimed = DB::table('event_document_notices')->insertOrIgnore([
            'event_id' => $event->id,
            'marketplace_organizer_id' => $organizer->id,
            'notice_key' => $issue['key'],
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (! $claimed) {
            return null;
        }

        [$title, $message] = $this->noticeText($event, $issue['kind']);
        $actionUrl = '/organizator/documente?event=' . $event->id;

        try {
            OrganizerNotificationService::notify(
                $organizer,
                MarketplaceNotification::TYPE_DOCUMENT_ACTION,
                $title,
                $message,
                $actionUrl,
                $event,
                ['event_id' => $event->id, 'issue' => $issue['kind']]
            );
        } catch (\Throwable $e) {
            Log::warning('Fiscal document notice: in-app notification failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
        }

        $this->emailNotice($event, $organizer, $title, $message, $actionUrl);
        static::flushAttention($organizer->id);

        return $issue['kind'];
    }

    /** @return array{0: string, 1: string} */
    public function noticeText(Event $event, string $kind): array
    {
        $name = $this->text($event->title) ?: 'eveniment';
        $registry = $this->registry($event);
        $where = $registry ? ' la ' . $registry->name : ' la primărie';

        return match ($kind) {
            'cerere_missing' => [
                'Cererea de vizare nu e generată: ' . $name,
                'Generează cererea de vizare a biletelor și depune-o' . $where
                    . ($this->cerereDeadline($event) ? ' cel târziu pe ' . $this->cerereDeadline($event)->format('d.m.Y') : '') . '.',
            ],
            'cerere_stale' => [
                'Cererea de vizare trebuie regenerată: ' . $name,
                'Prețul, stocul sau codurile de reducere ale biletelor s-au schimbat după ce ai generat cererea de vizare, '
                    . 'așa că ea nu mai corespunde. Regenerează cererea și depune noua versiune' . $where . '.',
            ],
            default => [
                'Impozit și PV distrugere de generat: ' . $name,
                'Evenimentul s-a încheiat. Generează declarația de impozit și PV-ul de distrugere a biletelor și depune-le împreună' . $where
                    . ($this->taxDeadline($event) ? ' până pe ' . $this->taxDeadline($event)->format('d.m.Y') : '') . '.',
            ],
        };
    }

    protected function emailNotice(Event $event, MarketplaceOrganizer $organizer, string $title, string $message, string $actionUrl): void
    {
        $client = $event->marketplaceClient;
        if (! $client || ! $organizer->email || ! filter_var($organizer->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $url = 'https://' . $client->domain . $actionUrl;
            $data = [
                'organizer_name' => (string) ($organizer->name ?: $organizer->company_name),
                'event_name' => $this->text($event->title),
                'notice_title' => $title,
                'notice_message' => $message,
                'action_url' => $url,
                'marketplace_name' => (string) ($client->public_name ?? $client->name),
            ];

            $template = MarketplaceEmailTemplate::query()
                ->where('marketplace_client_id', $client->id)
                ->where('slug', 'organizer_fiscal_documents')
                ->where('is_active', true)
                ->first();
            if ($template) {
                $rendered = $template->render(array_map('e', $data));
                $subject = html_entity_decode($rendered['subject'], ENT_QUOTES);
                $html = $rendered['body_html'];
            } else {
                $d = array_map('e', $data);
                $subject = $title;
                $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#333;line-height:1.5;">'
                    . '<p>Bună ziua' . ($d['organizer_name'] ? ', ' . $d['organizer_name'] : '') . ',</p>'
                    . '<p>' . $d['notice_message'] . '</p>'
                    . '<p style="margin:24px 0;"><a href="' . $d['action_url'] . '" style="background:#A51C30;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold;">Deschide Documente</a></p>'
                    . '<p style="color:#888;font-size:12px;">Primești acest mesaj o singură dată pentru această situație.</p>'
                    . '<p>Echipa ' . $d['marketplace_name'] . '</p>'
                    . '</div>';
            }

            BaseController::sendViaMarketplace($client, $organizer->email, (string) $organizer->name, $subject, $html, [
                'template_slug' => 'organizer_fiscal_documents',
                'marketplace_organizer_id' => $organizer->id,
                'metadata' => ['event_id' => $event->id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Fiscal document notice: email failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
        }
    }

    protected function text($value): string
    {
        if (is_array($value)) {
            return (string) ($value['ro'] ?? $value['en'] ?? (reset($value) ?: ''));
        }

        return (string) ($value ?? '');
    }
}
