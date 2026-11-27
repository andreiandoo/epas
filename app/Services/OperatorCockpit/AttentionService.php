<?php

namespace App\Services\OperatorCockpit;

use App\Filament\Marketplace\Resources\ArtistAccountResource;
use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Resources\MarketplaceTodoResource;
use App\Filament\Marketplace\Resources\SupportTicketResource;
use App\Models\Chat\ChatConversation;
use App\Models\Event;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceArtistAccount;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceTodo;
use App\Models\SupportTicket;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bara de atenție a operatorilor: ce așteaptă pe cineva, calculat live din tabelele sursă.
 *
 * Reguli:
 * - un element apare dacă e nealocat (și operatorul are acces la departamentul lui) sau e al operatorului;
 * - dispare când e rezolvat sau preluat de altcineva;
 * - super-adminul vede tot, inclusiv ce lucrează ceilalți.
 */
class AttentionService
{
    public const CATEGORIES = ['chat', 'ticket', 'todo', 'artist', 'event', 'service', 'docs'];

    private MarketplaceAdmin $admin;
    private MarketplaceClient $client;
    private bool $isSuper;
    private array $thresholds;
    private int $limit;
    /** @var int[]|null */
    private ?array $departmentIds = null;
    private array $adminNames = [];

    public static function isEnabledFor(?MarketplaceClient $client): bool
    {
        return (bool) $client?->hasMicroservice(config('operator_cockpit.microservice_slug', 'operator-cockpit'));
    }

    /**
     * Varianta cu cache, pentru observatori (rulează la fiecare salvare).
     */
    public static function isEnabledForClientId(?int $marketplaceClientId): bool
    {
        if (! $marketplaceClientId) {
            return false;
        }

        return (bool) \Illuminate\Support\Facades\Cache::remember('cockpit-enabled:' . $marketplaceClientId, 300, function () use ($marketplaceClientId) {
            return self::isEnabledFor(MarketplaceClient::find($marketplaceClientId));
        });
    }

    public function forAdmin(MarketplaceAdmin $admin): static
    {
        $this->admin = $admin;
        $this->client = $admin->marketplaceClient;
        $this->isSuper = $admin->isSuperAdmin() || (bool) session('marketplace_is_super_admin');
        $this->limit = (int) config('operator_cockpit.max_items_per_category', 40);

        $overrides = (array) (($this->client->getMicroserviceConfig(config('operator_cockpit.microservice_slug')) ?? [])['thresholds'] ?? []);
        $this->thresholds = array_merge((array) config('operator_cockpit.thresholds', []), array_filter($overrides, 'is_numeric'));

        return $this;
    }

    public function isSuper(): bool
    {
        return $this->isSuper;
    }

    /**
     * Toate elementele vizibile pentru operator, grupate pe categorie.
     */
    public function snapshot(): array
    {
        $snoozed = $this->snoozedKeys();
        $result = [];

        // Departamentele operatorului decid ce categorii primește (super-adminul le vede pe toate).
        $allowed = DepartmentScope::for($this->admin)['categories'];

        foreach (self::CATEGORIES as $category) {
            if ($allowed !== null && ! in_array($category, $allowed, true)) {
                $result[$category] = [];
                continue;
            }

            try {
                $items = match ($category) {
                    'chat' => $this->chatItems(),
                    'ticket' => $this->ticketItems(),
                    'todo' => $this->todoItems(),
                    'artist' => $this->artistItems(),
                    'event' => $this->eventItems(),
                    'service' => $this->serviceItems(),
                    'docs' => $this->docItems(),
                };
            } catch (\Throwable $e) {
                // O categorie stricată nu trebuie să golească toată bara.
                report($e);
                $items = [];
            }

            $items = array_values(array_filter($items, fn ($i) => ! in_array($i['key'], $snoozed, true)));
            $result[$category] = $items;
        }

        return $result;
    }

    // -------------------------------------------------------------------- chat

    private function chatItems(): array
    {
        if (! $this->client->hasMicroservice('live-chat')) {
            return [];
        }

        $adminId = (int) $this->admin->id;

        $query = ChatConversation::query()
            ->where('marketplace_client_id', $this->client->id)
            ->where(function (Builder $q) use ($adminId) {
                $q->where(function (Builder $pool) {
                    $pool->whereNull('assigned_to_marketplace_admin_id')
                        ->whereIn('status', [ChatConversation::STATUS_QUEUED, ChatConversation::STATUS_OFFLINE_MESSAGE]);
                    $this->applyDepartmentFilter($pool, 'support_department_id');
                })->orWhere(function (Builder $mine) use ($adminId) {
                    $mine->where('status', ChatConversation::STATUS_ACTIVE);
                    if (! $this->isSuper) {
                        $mine->where('assigned_to_marketplace_admin_id', $adminId);
                    }
                });
            })
            ->withCount(['messages as unread_incoming' => fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('author_type')->orWhere('author_type', '!=', 'staff'))
                ->where('is_internal', false)
                ->whereNull('read_at')])
            ->orderByRaw('COALESCE(queued_at, created_at) asc')
            ->limit($this->limit * 2);

        $items = [];
        foreach ($query->get() as $c) {
            $owner = $c->assigned_to_marketplace_admin_id;
            $isMine = (int) $owner === $adminId;

            // Chat-urile mele active apar doar dacă au mesaje necitite de la client.
            if ($owner && $isMine && (int) $c->unread_incoming === 0) {
                continue;
            }

            $waitingSince = $c->queued_at ?? $c->created_at;
            $who = $c->guest_name ?: ($c->context['visitor_name'] ?? null) ?: 'Vizitator';
            $meta = $owner
                ? ($isMine ? 'al tău' : 'preluat de ' . $this->adminName($owner)) . ' · ' . (int) $c->unread_incoming . ' necitite'
                : ($c->status === ChatConversation::STATUS_OFFLINE_MESSAGE ? 'mesaj lăsat' : 'în coadă') . ' · ' . $this->ago($waitingSince);

            $items[] = $this->item(
                'chat',
                $c->id,
                $who . ($c->subject ? ' · ' . $c->subject : ''),
                $this->visitorLabel($c->visitor_type) . ' · ' . $meta,
                $owner,
                ! $owner && $waitingSince && $waitingSince->lt(now()->subMinutes((int) $this->thresholds['chat_queue_minutes'])),
                '/marketplace/chat-console?c=' . urlencode((string) $c->reference),
                $waitingSince,
            );

            if (count($items) >= $this->limit) {
                break;
            }
        }

        return $items;
    }

    // ------------------------------------------------------------------ ticket

    private function ticketItems(): array
    {
        $adminId = (int) $this->admin->id;

        $query = SupportTicket::query()
            ->where('marketplace_client_id', $this->client->id)
            ->where(function (Builder $q) use ($adminId) {
                $q->where(function (Builder $pool) {
                    $pool->whereNull('assigned_to_marketplace_admin_id')
                        ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS]);
                    $this->applyDepartmentFilter($pool, 'support_department_id');
                })->orWhere(function (Builder $assigned) use ($adminId) {
                    $assigned->whereNotNull('assigned_to_marketplace_admin_id')
                        ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS]);
                    if (! $this->isSuper) {
                        $assigned->where('assigned_to_marketplace_admin_id', $adminId);
                    }
                });
            })
            ->with('department:id,name')
            ->orderByRaw('COALESCE(opened_at, created_at) asc')
            ->limit($this->limit);

        $items = [];
        foreach ($query->get() as $t) {
            $owner = $t->assigned_to_marketplace_admin_id;
            $opened = $t->opened_at ?? $t->created_at;
            $lastActivity = $t->last_activity_at ?? $opened;

            $late = $owner
                ? $lastActivity && $lastActivity->lt(now()->subMinutes((int) $this->thresholds['ticket_mine_idle_minutes']))
                : $opened && $opened->lt(now()->subMinutes((int) $this->thresholds['ticket_unassigned_minutes']));

            $dept = $this->departmentName($t);
            $meta = trim(($dept ? $dept . ' · ' : '') . $this->ownerLabel($owner) . ' · ' . ($owner ? 'activitate ' . $this->ago($lastActivity) : 'deschis ' . $this->ago($opened)));

            $items[] = $this->item(
                'ticket',
                $t->id,
                trim(($t->ticket_number ? $t->ticket_number . ' ' : '') . (string) $t->subject),
                $meta,
                $owner,
                $late,
                $this->resourceUrl(SupportTicketResource::class, 'view', $t->id),
                $opened,
            );
        }

        return $items;
    }

    // -------------------------------------------------------------------- todo

    private function todoItems(): array
    {
        $adminId = (int) $this->admin->id;

        $query = MarketplaceTodo::query()
            ->where('marketplace_client_id', $this->client->id)
            ->whereIn('status', [MarketplaceTodo::STATUS_OPEN, MarketplaceTodo::STATUS_IN_PROGRESS])
            ->where(function (Builder $q) use ($adminId) {
                $q->whereNull('assigned_to_marketplace_admin_id');
                if ($this->isSuper) {
                    $q->orWhereNotNull('assigned_to_marketplace_admin_id');
                } else {
                    $q->orWhere('assigned_to_marketplace_admin_id', $adminId);
                }
            })
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByRaw('COALESCE(opened_at, created_at) asc')
            ->limit($this->limit);

        $items = [];
        foreach ($query->get() as $t) {
            $owner = $t->assigned_to_marketplace_admin_id;
            $since = $t->last_activity_at ?? $t->opened_at ?? $t->created_at;
            $late = $t->priority === 'urgent'
                || ($since && $since->lt(now()->subMinutes((int) $this->thresholds['todo_idle_minutes'])));

            $priority = MarketplaceTodo::PRIORITY_LABELS[$t->priority] ?? null;
            $meta = trim(($priority ? 'prioritate ' . mb_strtolower($priority) . ' · ' : '') . $this->ownerLabel($owner) . ' · ' . $this->ago($since));

            $items[] = $this->item(
                'todo',
                $t->id,
                (string) $t->title,
                $meta,
                $owner,
                $late,
                $this->resourceUrl(MarketplaceTodoResource::class, 'view', $t->id),
                $t->opened_at ?? $t->created_at,
            );
        }

        return $items;
    }

    // ------------------------------------------------------------------ artist

    private function artistItems(): array
    {
        if (! $this->hasAssignmentColumn('marketplace_artist_accounts')) {
            return [];
        }

        $query = MarketplaceArtistAccount::query()
            ->where('marketplace_client_id', $this->client->id)
            ->where('status', 'pending')
            ->with('artist:id,name');
        $this->applyOwnerFilter($query);
        $query->orderBy('created_at')->limit($this->limit);

        $items = [];
        foreach ($query->get() as $a) {
            $owner = $a->assigned_to_marketplace_admin_id;
            $name = $a->artist?->name ?: trim($a->first_name . ' ' . $a->last_name) ?: $a->email;
            if (is_array($name)) {
                $name = $name['ro'] ?? $name['en'] ?? reset($name);
            }

            $items[] = $this->item(
                'artist',
                $a->id,
                (string) $name . ($a->artist_id ? '' : ' · cont nou'),
                $this->ownerLabel($owner) . ' · în așteptare ' . $this->ago($a->created_at),
                $owner,
                $a->created_at && $a->created_at->lt(now()->subMinutes((int) $this->thresholds['artist_pending_minutes'])),
                $this->resourceUrl(ArtistAccountResource::class, 'view', $a->id),
                $a->created_at,
            );
        }

        return $items;
    }

    // ------------------------------------------------------------------- event

    /**
     * Două nevoi diferite:
     * - aprobare: eveniment nou trimis de organizator, încă nepublicat. Cere o verificare
     *   completă, deci se preia (un singur operator îl lucrează);
     * - modificări: organizatorul a schimbat un eveniment live. E o decizie rapidă pe un diff,
     *   nu se alocă nimănui; dispare când cineva aprobă sau respinge.
     * Evenimentele terminate sau anulate nu mai apar.
     */
    private function eventItems(): array
    {
        if (! $this->hasAssignmentColumn('events')) {
            return [];
        }

        $columns = ['id', 'title', 'marketplace_organizer_id', 'is_published', 'submitted_at', 'pending_changes_status', 'pending_changes_submitted_at', 'event_date', 'range_start_date', 'range_end_date', 'assigned_to_marketplace_admin_id', 'created_at'];

        $base = fn () => Event::query()
            ->where('marketplace_client_id', $this->client->id)
            ->where(fn ($q) => $q->where('is_cancelled', false)->orWhereNull('is_cancelled'))
            ->where(fn ($q) => $q->whereRaw('COALESCE(range_end_date, event_date) IS NULL')
                ->orWhereRaw('COALESCE(range_end_date, event_date) >= CURRENT_DATE'))
            ->select($columns)
            ->with('marketplaceOrganizer:id,name');

        $approval = $base()
            ->whereNotNull('submitted_at')
            ->where(fn ($p) => $p->where('is_published', false)->orWhereNull('is_published'))
            ->whereNull('rejected_at')
            ->where(fn ($q) => $q->whereNull('pending_changes_status')->orWhere('pending_changes_status', '!=', 'pending'));
        $this->applyOwnerFilter($approval);
        $approval = $approval->orderBy('submitted_at')->limit($this->limit)->get();

        // Fără select restrâns: diff-ul compară cu toate coloanele evenimentului.
        $changes = Event::query()
            ->where('marketplace_client_id', $this->client->id)
            ->where(fn ($q) => $q->where('is_cancelled', false)->orWhereNull('is_cancelled'))
            ->where(fn ($q) => $q->whereRaw('COALESCE(range_end_date, event_date) IS NULL')
                ->orWhereRaw('COALESCE(range_end_date, event_date) >= CURRENT_DATE'))
            ->where('pending_changes_status', 'pending')
            ->where(fn ($q) => $q->whereNull('marketplace_organizer_id')
                ->orWhereHas('marketplaceOrganizer', fn ($o) => $o->where(fn ($w) => $w->where('allow_live_edits', false)->orWhereNull('allow_live_edits'))))
            ->with('marketplaceOrganizer:id,name')
            ->orderByRaw('COALESCE(pending_changes_submitted_at, created_at) asc')
            ->limit($this->limit)
            ->get()
            // Modificările aplicate deja direct pe eveniment (fără „Aprobă”) nu mai cer nimic.
            ->filter(function (Event $e) {
                try {
                    return count(app(\App\Services\Marketplace\EventLiveEditService::class)->diffPending($e)['rows'] ?? []) > 0;
                } catch (\Throwable $ex) {
                    report($ex);
                    return true;
                }
            });

        $items = [];
        foreach ($approval as $e) {
            $items[] = $this->eventItem($e, 'approval');
        }
        foreach ($changes as $e) {
            $items[] = $this->eventItem($e, 'changes');
        }

        return $items;
    }

    private function eventItem(Event $e, string $kind): array
    {
        $soonLimit = now()->addDays((int) $this->thresholds['event_starts_within_days'])->endOfDay();
        $since = $kind === 'changes'
            ? ($e->pending_changes_submitted_at ?? $e->created_at)
            : ($e->submitted_at ?? $e->created_at);
        $startsAt = $e->event_date ?? $e->range_start_date;

        $late = ($since && $since->lt(now()->subMinutes((int) $this->thresholds['event_waiting_minutes'])))
            || ($startsAt && $startsAt->lte($soonLimit) && $startsAt->gte(now()->startOfDay()));

        $title = $e->getTranslation('title', 'ro') ?: $e->getTranslation('title', 'en');
        $organizer = $e->marketplaceOrganizer?->name;

        $parts = [];
        if ($kind === 'approval') {
            $parts[] = $this->ownerLabel($e->assigned_to_marketplace_admin_id);
            $parts[] = 'trimis ' . $this->ago($since);
        } else {
            $parts[] = 'trimise ' . $this->ago($since);
        }
        if ($startsAt) {
            $parts[] = 'începe ' . $startsAt->format('d.m');
        }
        if ($organizer) {
            $parts[] = $organizer;
        }

        $item = $this->item(
            'event',
            $e->id,
            (string) ($title ?: 'Eveniment #' . $e->id),
            implode(' · ', $parts),
            $kind === 'approval' ? $e->assigned_to_marketplace_admin_id : null,
            $late,
            $this->resourceUrl(EventResource::class, 'edit', $e->id),
            $since,
        );

        // Cheie separată pentru modificări, ca „Amână” să nu ascundă și aprobarea aceluiași eveniment.
        if ($kind === 'changes') {
            $item['key'] = 'eventchg:' . $e->id;
        }
        $item['kind'] = $kind;
        $item['claimable'] = $kind === 'approval';

        return $item;
    }

    // ----------------------------------------------------------------- service

    /**
     * Comenzi de servicii care așteaptă operatorul: plată prin transfer de confirmat sau
     * comandă plătită încă neactivată (cele plătite cu cardul se activează singure).
     */
    private function serviceItems(): array
    {
        if (! Schema::hasTable('service_orders')) {
            return [];
        }

        $orders = \App\Models\ServiceOrder::query()
            ->where('marketplace_client_id', $this->client->id)
            ->where(function (Builder $q) {
                $q->where(fn ($w) => $w->where('status', \App\Models\ServiceOrder::STATUS_PENDING_PAYMENT)
                        ->where('payment_method', 'transfer')
                        ->where('payment_status', \App\Models\ServiceOrder::PAYMENT_PENDING))
                  ->orWhere(fn ($w) => $w->where('status', \App\Models\ServiceOrder::STATUS_PROCESSING)
                        ->where('payment_status', \App\Models\ServiceOrder::PAYMENT_PAID));
            })
            ->with('marketplaceOrganizer:id,name')
            ->orderBy('created_at')
            ->limit($this->limit)
            ->get();

        $items = [];
        foreach ($orders as $o) {
            $toPay = $o->status === \App\Models\ServiceOrder::STATUS_PENDING_PAYMENT;
            $since = $toPay ? $o->created_at : ($o->paid_at ?? $o->updated_at);
            $total = $o->total !== null ? number_format((float) $o->total, 2, ',', '.') . ' ' . ($o->currency ?? 'RON') : null;

            $item = $this->item(
                'service',
                $o->id,
                trim(($o->order_number ? $o->order_number . ' · ' : '') . $o->service_type_label),
                implode(' · ', array_filter([
                    $toPay ? 'plată prin transfer de confirmat' : 'plătită, de activat',
                    $o->marketplaceOrganizer?->name,
                    $total,
                    $this->ago($since),
                ])),
                null,
                $since && $since->lt(now()->subDay()),
                $this->resourceUrl(\App\Filament\Marketplace\Resources\ServiceOrderResource::class, 'view', $o->id),
                $since,
            );
            $item['claimable'] = false;
            $item['kind'] = $toPay ? 'payment' : 'activate';
            $items[] = $item;
        }

        return $items;
    }

    // -------------------------------------------------------------------- docs

    /**
     * Documentele fiscale ale evenimentelor: cerere de vizare negenerată sau de refăcut,
     * impozit și PV de distrugere după eveniment. Calcul greu, ținut 10 minute în cache.
     */
    private function docItems(): array
    {
        $rows = \Illuminate\Support\Facades\Cache::remember('cockpit-docs-v2:' . $this->client->id, 600, function () {
            $svc = app(\App\Services\Marketplace\OrganizerFiscalDocuments::class);
            if (! $svc->marketplaceHasTemplates((int) $this->client->id)) {
                return [];
            }

            $out = [];
            foreach ($svc->candidateEvents((int) $this->client->id) as $event) {
                $issue = $svc->issue($event);
                if (! $issue) {
                    continue;
                }
                $deadline = $issue['kind'] === 'impozit_pv_missing' ? $svc->taxDeadline($event) : $svc->cerereDeadline($event);
                $name = $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en');
                $start = $svc->startDay($event);
                $out[] = [
                    'id' => (int) $event->id,
                    'kind' => $issue['kind'],
                    'title' => (string) ($name ?: 'Eveniment #' . $event->id),
                    'event_date' => $start?->format('d.m.Y'),
                    'organizer' => $event->marketplaceOrganizer?->name,
                    'deadline' => $deadline?->toDateString(),
                ];
            }

            return $out;
        });

        $labels = [
            'cerere_missing' => 'cerere de vizare de generat',
            'cerere_stale' => 'cerere de vizare de refăcut',
            'impozit_pv_missing' => 'impozit + PV distrugere de generat',
        ];

        $items = [];
        foreach (array_slice($rows, 0, $this->limit) as $r) {
            $deadline = $r['deadline'] ? \Carbon\Carbon::parse($r['deadline']) : null;
            $item = $this->item(
                'docs',
                $r['id'],
                $r['title'],
                implode(' · ', array_filter([
                    $labels[$r['kind']] ?? $r['kind'],
                    $deadline ? 'termen ' . $deadline->format('d.m.Y') : null,
                    $r['organizer'],
                ])),
                null,
                $deadline !== null && $deadline->lte(now()->addDays(3)->endOfDay()),
                $this->resourceUrl(EventResource::class, 'edit', $r['id']),
                null,
            );
            $item['key'] = 'docs:' . $r['id'] . ':' . $r['kind'];
            $item['claimable'] = false;
            $item['kind'] = $r['kind'];
            // Afișate separat în sertar: tipul documentului îngroșat și termenul vizibil.
            $item['doc_label'] = $labels[$r['kind']] ?? $r['kind'];
            $item['deadline'] = $deadline?->format('d.m.Y');
            $item['deadline_passed'] = $deadline !== null && $deadline->lt(now()->startOfDay());
            $item['event_date'] = $r['event_date'] ?? null;
            $item['organizer'] = $r['organizer'];
            $items[] = $item;
        }

        return $items;
    }

    // ------------------------------------------------------------------ claim

    /**
     * Preia un element. Reușește doar dacă e încă nealocat (sau deja al operatorului).
     *
     * @return array{ok: bool, message?: string, url?: string}
     */
    public function claim(string $key): array
    {
        [$category, $id] = $this->parseKey($key);
        $adminId = (int) $this->admin->id;

        $model = match ($category) {
            'chat' => ChatConversation::query(),
            'ticket' => SupportTicket::query(),
            'todo' => MarketplaceTodo::query(),
            'artist' => MarketplaceArtistAccount::query(),
            'event' => Event::query(),
            default => null,
        };

        if (! $model) {
            return ['ok' => false, 'message' => 'Element necunoscut.'];
        }

        $record = $model->where('marketplace_client_id', $this->client->id)->whereKey($id)->first();
        if (! $record) {
            return ['ok' => false, 'message' => 'Elementul nu mai există.'];
        }

        $current = $record->assigned_to_marketplace_admin_id;
        if ($current && (int) $current !== $adminId) {
            return ['ok' => false, 'message' => 'L-a preluat deja ' . $this->adminName($current) . '.'];
        }

        if ($category === 'chat') {
            app(\App\Services\Chat\ChatRoutingService::class)->assignTo($record, $adminId);
        } else {
            $update = ['assigned_to_marketplace_admin_id' => $adminId];
            if (in_array($category, ['artist', 'event'], true)) {
                $update['assigned_at'] = now();
            }

            // Update condiționat: dacă între timp l-a luat altcineva, nu suprascriem.
            $affected = $record->newQuery()
                ->whereKey($record->getKey())
                ->where(fn ($q) => $q->whereNull('assigned_to_marketplace_admin_id')->orWhere('assigned_to_marketplace_admin_id', $adminId))
                ->update($update);

            if ($affected === 0) {
                return ['ok' => false, 'message' => 'L-a preluat deja altcineva.'];
            }

            // Update-ul direct nu declanșează observatorii; anunțăm explicit ceilalți operatori.
            CockpitSignal::changed((int) $this->client->id, $category);
        }

        return ['ok' => true];
    }

    public function snooze(string $key): void
    {
        $this->parseKey($key);
        DB::table('operator_attention_snoozes')->updateOrInsert(
            ['marketplace_admin_id' => $this->admin->id, 'item_key' => $key],
            ['until' => now()->addMinutes((int) config('operator_cockpit.snooze_minutes', 60))]
        );
    }

    // ----------------------------------------------------------------- helpers

    private function item(string $category, int $id, string $title, string $meta, $owner, bool $late, string $url, ?CarbonInterface $since): array
    {
        $owner = $owner ? (int) $owner : null;

        return [
            'key' => $category . ':' . $id,
            'category' => $category,
            'title' => mb_strimwidth($title, 0, 120, '…'),
            'meta' => $meta,
            'owner_id' => $owner,
            'owner_name' => $owner ? $this->adminName($owner) : null,
            'mine' => $owner === (int) $this->admin->id,
            'late' => $late,
            'url' => $url,
            'since' => $since?->toIso8601String(),
            'kind' => null,
            'claimable' => true,
        ];
    }

    private function parseKey(string $key): array
    {
        if (! preg_match('/^(chat|ticket|todo|artist|event|eventchg|service|docs):(\d+)(?::[a-z_]+)?$/', $key, $m)) {
            throw new \InvalidArgumentException('Cheie invalidă.');
        }

        return [$m[1], (int) $m[2]];
    }

    /**
     * Nesuper-adminii văd doar ce e nealocat sau al lor.
     */
    private function applyOwnerFilter(Builder $query): void
    {
        if ($this->isSuper) {
            return;
        }

        $adminId = (int) $this->admin->id;
        $query->where(fn ($q) => $q->whereNull('assigned_to_marketplace_admin_id')->orWhere('assigned_to_marketplace_admin_id', $adminId));
    }

    /**
     * Elementele nealocate se văd după departamentul de suport. Operatorii fără departament
     * și super-adminii văd tot; elementele fără departament se văd de toată lumea.
     */
    private function applyDepartmentFilter(Builder $query, string $column): void
    {
        if ($this->isSuper) {
            return;
        }

        $departments = $this->departmentIds();
        if (! $departments) {
            return;
        }

        $query->where(fn ($q) => $q->whereNull($column)->orWhereIn($column, $departments));
    }

    private function departmentIds(): array
    {
        if ($this->departmentIds === null) {
            $this->departmentIds = Schema::hasTable('support_department_admins')
                ? DB::table('support_department_admins')
                    ->where('marketplace_admin_id', $this->admin->id)
                    ->pluck('support_department_id')
                    ->map(fn ($id) => (int) $id)
                    ->all()
                : [];
        }

        return $this->departmentIds;
    }

    private function departmentName(SupportTicket $ticket): ?string
    {
        $name = $ticket->department?->name;
        if (is_array($name)) {
            $name = $name['ro'] ?? $name['en'] ?? reset($name);
        }

        return $name ?: null;
    }

    private function snoozedKeys(): array
    {
        if (! Schema::hasTable('operator_attention_snoozes')) {
            return [];
        }

        return DB::table('operator_attention_snoozes')
            ->where('marketplace_admin_id', $this->admin->id)
            ->where('until', '>', now())
            ->pluck('item_key')
            ->all();
    }

    private function hasAssignmentColumn(string $table): bool
    {
        static $cache = [];

        return $cache[$table] ??= Schema::hasColumn($table, 'assigned_to_marketplace_admin_id');
    }

    private function ownerLabel($owner): string
    {
        if (! $owner) {
            return 'nealocat';
        }

        return (int) $owner === (int) $this->admin->id ? 'al tău' : 'preluat de ' . $this->adminName($owner);
    }

    private function adminName($id): string
    {
        $id = (int) $id;
        if (! array_key_exists($id, $this->adminNames)) {
            $name = MarketplaceAdmin::query()->whereKey($id)->value('name');
            $this->adminNames[$id] = $name ? trim(preg_replace('/\s*\(System\)$/', '', $name)) : 'alt operator';
        }

        return $this->adminNames[$id];
    }

    private function visitorLabel(?string $type): string
    {
        return match ($type) {
            ChatConversation::VISITOR_ORGANIZER => 'organizator',
            ChatConversation::VISITOR_ARTIST => 'artist',
            ChatConversation::VISITOR_CUSTOMER => 'client',
            default => 'vizitator',
        };
    }

    private function ago(?CarbonInterface $date): string
    {
        if (! $date) {
            return '';
        }

        $minutes = (int) $date->diffInMinutes(now());
        if ($minutes < 1) {
            return 'acum';
        }
        if ($minutes < 60) {
            return 'de ' . $minutes . ' min';
        }
        $hours = intdiv($minutes, 60);
        if ($hours < 48) {
            return 'de ' . $hours . 'h';
        }

        return 'de ' . intdiv($hours, 24) . ' zile';
    }

    private function resourceUrl(string $resource, string $page, int $id): string
    {
        try {
            return $resource::getUrl($page, ['record' => $id], isAbsolute: false, panel: 'marketplace');
        } catch (\Throwable) {
            return '/marketplace';
        }
    }
}
