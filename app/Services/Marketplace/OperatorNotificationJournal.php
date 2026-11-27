<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Jurnalul zilnic al operatorului din panoul /marketplace.
 *
 * - arată doar ultimele 3 zile;
 * - fiecare rol vede doar tipurile lui;
 * - doar tipurile care cer acțiune au stare „necitit”, iar starea e per operator
 *   (nu mai atinge read_at, care e al organizatorului).
 */
class OperatorNotificationJournal
{
    public const RETENTION_DAYS = 3;

    // Tipuri care cer ca operatorul să le vadă/acționeze.
    public const ACTION_TYPES = [
        MarketplaceNotification::TYPE_REFUND_REQUEST,
        MarketplaceNotification::TYPE_PAYOUT_REQUEST,
        MarketplaceNotification::TYPE_DOCUMENT_ACTION,
        MarketplaceNotification::TYPE_COMPANY_INVOICE_REQUEST,
    ];

    private const MODERATOR_TYPES = [
        MarketplaceNotification::TYPE_EVENT_CREATED,
        MarketplaceNotification::TYPE_EVENT_UPDATED,
        MarketplaceNotification::TYPE_TICKET_SALE,
        MarketplaceNotification::TYPE_ARTIST_CREATED,
        MarketplaceNotification::TYPE_VENUE_CREATED,
        MarketplaceNotification::TYPE_SEATING_LAYOUT_CREATED,
        MarketplaceNotification::TYPE_SERVICE_ORDER_COMPLETED,
        MarketplaceNotification::TYPE_SERVICE_ORDER_RESULTS,
        MarketplaceNotification::TYPE_SERVICE_ORDER_STARTED,
        MarketplaceNotification::TYPE_CHAT_STARTED,
        MarketplaceNotification::TYPE_CHAT_CLAIMED,
        MarketplaceNotification::TYPE_CHAT_CLOSED,
    ];

    private const ADMIN_TYPES = [
        MarketplaceNotification::TYPE_EVENT_CREATED,
        MarketplaceNotification::TYPE_EVENT_UPDATED,
        MarketplaceNotification::TYPE_TICKET_SALE,
        MarketplaceNotification::TYPE_REFUND_REQUEST,
        MarketplaceNotification::TYPE_ORGANIZER_REGISTRATION,
        MarketplaceNotification::TYPE_PAYOUT_REQUEST,
        MarketplaceNotification::TYPE_PAYOUT_APPROVED,
        MarketplaceNotification::TYPE_PAYOUT_PROCESSING,
        MarketplaceNotification::TYPE_PAYOUT_COMPLETED,
        MarketplaceNotification::TYPE_PAYOUT_REJECTED,
        MarketplaceNotification::TYPE_DOCUMENT_GENERATED,
        MarketplaceNotification::TYPE_DOCUMENT_ACTION,
        MarketplaceNotification::TYPE_CUSTOMER_REGISTRATION,
        MarketplaceNotification::TYPE_SERVICE_ORDER,
        MarketplaceNotification::TYPE_ARTIST_CREATED,
        MarketplaceNotification::TYPE_VENUE_CREATED,
        MarketplaceNotification::TYPE_SEATING_LAYOUT_CREATED,
        MarketplaceNotification::TYPE_SERVICE_ORDER_COMPLETED,
        MarketplaceNotification::TYPE_SERVICE_ORDER_INVOICE,
        MarketplaceNotification::TYPE_SERVICE_ORDER_RESULTS,
        MarketplaceNotification::TYPE_SERVICE_ORDER_STARTED,
        MarketplaceNotification::TYPE_COMPANY_INVOICE_REQUEST,
        MarketplaceNotification::TYPE_CHAT_STARTED,
        MarketplaceNotification::TYPE_CHAT_CLAIMED,
        MarketplaceNotification::TYPE_CHAT_CLOSED,
    ];

    public function admin(): ?MarketplaceAdmin
    {
        // Garda direct întâi: jurnalul e cerut și din endpoint-ul barei, în afara unei pagini Filament.
        $user = auth('marketplace_admin')->user() ?? filament()->auth()->user();

        return $user instanceof MarketplaceAdmin ? $user : null;
    }

    public function clientId(): ?int
    {
        $admin = $this->admin();
        if ($admin && $admin->marketplace_client_id) {
            return (int) $admin->marketplace_client_id;
        }

        return session('super_admin_marketplace_client_id') ? (int) session('super_admin_marketplace_client_id') : null;
    }

    public function isSuperAdmin(?MarketplaceAdmin $admin): bool
    {
        return session('marketplace_is_super_admin') || ($admin && $admin->isSuperAdmin());
    }

    public function visibleTypes(?MarketplaceAdmin $admin): array
    {
        $types = $this->roleTypes($admin);
        $scope = \App\Services\OperatorCockpit\DepartmentScope::for($admin)['journal_types'];

        return $scope === null ? $types : array_values(array_intersect($types, $scope));
    }

    private function roleTypes(?MarketplaceAdmin $admin): array
    {
        if ($this->isSuperAdmin($admin)) {
            return array_values(array_unique(array_merge(self::ADMIN_TYPES, [MarketplaceNotification::TYPE_ADMIN_USER_ADDED])));
        }

        return match ($admin?->role) {
            'admin' => self::ADMIN_TYPES,
            'moderator' => self::MODERATOR_TYPES,
            default => [],
        };
    }

    public static function isActionType(?string $type): bool
    {
        return in_array($type, self::ACTION_TYPES, true);
    }

    /**
     * Notificările din jurnal pentru operatorul curent, cu atributul read_by_me.
     */
    public function query(): Builder
    {
        $admin = $this->admin();
        $adminId = $admin?->id ?? 0;

        return MarketplaceNotification::query()
            ->where('marketplace_client_id', $this->clientId() ?? 0)
            ->whereIn('type', $this->visibleTypes($admin) ?: ['__none__'])
            ->where('created_at', '>=', now()->subDays(self::RETENTION_DAYS))
            ->withExists(['operatorReads as read_by_me' => fn ($q) => $q->where('marketplace_admin_id', $adminId)]);
    }

    /**
     * Ultimele intrări pentru sertarul barei de atenție.
     */
    public function forBar(int $limit = 40): array
    {
        $labels = MarketplaceNotification::getTypeLabels();

        // Ultimele intrări + toate cele care cer acțiune și n-au fost văzute, ca numărul de pe tab
        // să corespundă cu ce apare la „Cer acțiune”.
        $latest = $this->query()->orderByDesc('id')->limit($limit)->get();
        $pending = $this->unreadQuery()->orderByDesc('id')->limit(100)->get();
        $all = $latest->concat($pending)->unique('id')->sortByDesc('id')->values();
        $states = $this->actionStates($all);

        return $all
            ->map(function (MarketplaceNotification $n) use ($labels, $states) {
                $st = $states[$n->id] ?? null;
                $url = (string) ($n->action_url ?? '');
                // Serverul e pe UTC; operatorii citesc ora României.
                $at = $n->created_at?->copy()->timezone('Europe/Bucharest');
                $today = now('Europe/Bucharest');

                return [
                    'id' => (int) $n->id,
                    'type' => $n->type,
                    'label' => __($labels[$n->type] ?? $n->type),
                    'color' => $n->color ?: (MarketplaceNotification::getTypeColors()[$n->type] ?? 'primary'),
                    'title' => (string) $n->title,
                    'message' => $n->message ? mb_strimwidth((string) $n->message, 0, 160, '…') : null,
                    'time' => $at && $at->isSameDay($today) ? $at->format('H:i') : $at?->format('d.m H:i'),
                    'day' => ! $at ? '' : ($at->isSameDay($today) ? __('Azi') : ($at->isSameDay($today->copy()->subDay()) ? __('Ieri') : $at->format('d.m'))),
                    // Linkurile notificărilor de organizator duc în contul lui, nu în panou.
                    'url' => $st['url'] ?? (str_starts_with($url, '/marketplace') ? $url : null),
                    // Cere acțiune doar cât timp cererea din spate e încă deschisă.
                    'attention' => $n->needsOperatorAttention() && ($st['open'] ?? true),
                    'explain' => $st['explain'] ?? null,
                    'state' => $st['state'] ?? null,
                    'done' => $st !== null && ! $st['open'],
                ];
            })
            ->all();
    }

    public function unreadQuery(): Builder
    {
        $adminId = $this->admin()?->id ?? 0;

        return $this->query()
            ->whereIn('type', self::ACTION_TYPES)
            ->whereDoesntHave('operatorReads', fn ($q) => $q->where('marketplace_admin_id', $adminId));
    }

    public function unreadCount(): int
    {
        // Doar cele a căror cerere e încă deschisă (factura încă neîncărcată, decontul încă neaprobat etc.).
        $rows = $this->unreadQuery()->orderByDesc('id')->limit(200)->get();
        $states = $this->actionStates($rows);

        return $rows->filter(fn ($n) => $states[$n->id]['open'] ?? true)->count();
    }

    /**
     * Starea de acum a cererii din spatele fiecărei notificări care cere acțiune, plus o
     * explicație pentru operator și linkul din panou unde se rezolvă.
     *
     * @return array<int, array{open: bool, state: ?string, explain: string, url: ?string}>
     */
    public function actionStates($notifications): array
    {
        $out = [];
        $byType = collect($notifications)->filter(fn ($n) => self::isActionType($n->type))->groupBy('type');

        $panel = function (string $resource, string $page, $id): ?string {
            try {
                return $resource::getUrl($page, ['record' => $id], isAbsolute: false, panel: 'marketplace');
            } catch (\Throwable) {
                return null;
            }
        };

        // Factură pe firmă: clientul a cerut-o, organizatorul trebuie să încarce factura pentru bilete.
        if ($rows = $byType->get(MarketplaceNotification::TYPE_COMPANY_INVOICE_REQUEST)) {
            $models = \App\Models\OrderCompanyInvoice::query()->whereIn('id', $rows->pluck('actionable_id')->filter())->with('order:id,order_number')->get()->keyBy('id');
            foreach ($rows as $n) {
                $m = $models->get($n->actionable_id);
                $status = $m?->status;
                $open = in_array($status, [\App\Models\OrderCompanyInvoice::STATUS_REQUESTED, \App\Models\OrderCompanyInvoice::STATUS_PENDING, \App\Models\OrderCompanyInvoice::STATUS_FAILED], true);
                $sent = $m?->sent_to_customer_at ? ' · ' . __('trimisă clientului :date', ['date' => $m->sent_to_customer_at->copy()->timezone('Europe/Bucharest')->format('d.m H:i')]) : '';
                $out[$n->id] = [
                    'open' => $m ? $open : false,
                    'state' => $m ? (__(\App\Models\OrderCompanyInvoice::STATUS_LABELS[$status] ?? $status) . $sent) : __('cererea nu mai există'),
                    'explain' => ($m?->order?->order_number
                            ? __('Clientul a cerut factură pe firmă pentru comanda :order.', ['order' => $m->order->order_number])
                            : __('Clientul a cerut factură pe firmă.'))
                        . ' ' . __('Factura pentru bilete o emite organizatorul și o încarcă în contul lui; o trimitem noi clientului. Urmărești până apare încărcată.'),
                    'url' => $m?->order_id ? $panel(\App\Filament\Marketplace\Resources\OrderResource::class, 'view', $m->order_id) : null,
                ];
            }
        }

        // Cerere de rambursare: se aprobă sau se respinge din Rambursări.
        if ($rows = $byType->get(MarketplaceNotification::TYPE_REFUND_REQUEST)) {
            $ids = $rows->map(fn ($n) => $n->actionable_type === \App\Models\MarketplaceRefundRequest::class ? $n->actionable_id : data_get($n->data, 'refund_request_id'))->filter();
            $models = \App\Models\MarketplaceRefundRequest::query()->whereIn('id', $ids)->get()->keyBy('id');
            $labels = [
                'pending' => __('în așteptare'), 'under_review' => __('în analiză'), 'approved' => __('aprobată, de procesat'),
                'processing' => __('în procesare'), 'refunded' => __('rambursată'), 'partially_refunded' => __('rambursată parțial'),
                'rejected' => __('respinsă'), 'failed' => __('eșuată la procesator'),
            ];
            foreach ($rows as $n) {
                $id = $n->actionable_type === \App\Models\MarketplaceRefundRequest::class ? $n->actionable_id : data_get($n->data, 'refund_request_id');
                $m = $id ? $models->get($id) : null;
                $out[$n->id] = [
                    'open' => $m ? in_array($m->status, ['pending', 'under_review', 'approved', 'failed'], true) : true,
                    'state' => $m ? ($labels[$m->status] ?? $m->status) : null,
                    'explain' => __('Un client a cerut rambursarea unei comenzi. Se analizează și se aprobă sau se respinge din Rambursări.'),
                    'url' => $m ? $panel(\App\Filament\Marketplace\Resources\RefundRequestResource::class, 'view', $m->id) : null,
                ];
            }
        }

        // Cerere de plată (decont): se verifică și se aprobă din Deconturi.
        if ($rows = $byType->get(MarketplaceNotification::TYPE_PAYOUT_REQUEST)) {
            $ids = $rows->map(fn ($n) => $n->actionable_type === \App\Models\MarketplacePayout::class ? $n->actionable_id : data_get($n->data, 'payout_id'))->filter();
            $models = \App\Models\MarketplacePayout::query()->whereIn('id', $ids)->get(['id', 'status', 'reference'])->keyBy('id');
            $labels = ['pending' => __('așteaptă aprobarea'), 'approved' => __('aprobat, de plătit'), 'processing' => __('în plată'), 'completed' => __('plătit'), 'rejected' => __('respins'), 'cancelled' => __('anulat')];
            foreach ($rows as $n) {
                $id = $n->actionable_type === \App\Models\MarketplacePayout::class ? $n->actionable_id : data_get($n->data, 'payout_id');
                $m = $id ? $models->get($id) : null;
                $out[$n->id] = [
                    'open' => $m ? $m->status === 'pending' : true,
                    'state' => $m ? ($labels[$m->status] ?? $m->status) : null,
                    'explain' => __('Organizatorul a cerut decontul :ref. Îl verifici și îl aprobi din Deconturi.', ['ref' => (string) ($m?->reference ?? '')]),
                    'url' => $m ? $panel(\App\Filament\Marketplace\Resources\PayoutResource::class, 'view', $m->id) : null,
                ];
            }
        }

        // Documente de emis: organizatorul are de generat cererea de vizare / impozitul și PV-ul.
        if ($rows = $byType->get(MarketplaceNotification::TYPE_DOCUMENT_ACTION)) {
            $svc = app(\App\Services\Marketplace\OrganizerFiscalDocuments::class);
            $events = \App\Models\Event::query()->whereIn('id', $rows->map(fn ($n) => data_get($n->data, 'event_id') ?: $n->actionable_id)->filter())->get()->keyBy('id');
            $what = ['cerere_missing' => __('cererea de vizare'), 'cerere_stale' => __('cererea de vizare din nou (s-au schimbat biletele)'), 'impozit_pv_missing' => __('declarația de impozit și PV-ul de distrugere')];
            foreach ($rows as $n) {
                $eventId = data_get($n->data, 'event_id') ?: $n->actionable_id;
                $event = $eventId ? $events->get($eventId) : null;
                $issue = data_get($n->data, 'issue');
                $still = null;
                try {
                    $current = $event ? $svc->issue($event) : null;
                    $still = $current && (! $issue || $current['kind'] === $issue);
                } catch (\Throwable) {
                    $still = true;
                }
                $out[$n->id] = [
                    'open' => (bool) $still,
                    'state' => $still ? __('încă negenerat') : __('rezolvat'),
                    'explain' => __('Organizatorul are de generat :what pentru eveniment. Termenele și depunerea le urmărești în Ops board.', ['what' => $what[$issue] ?? __('documentele fiscale')]),
                    'url' => '/marketplace/ops-board',
                ];
            }
        }

        return $out;
    }

    public function markRead(int $notificationId): void
    {
        $adminId = $this->admin()?->id;
        if (! $adminId) {
            return;
        }

        $exists = $this->query()->whereKey($notificationId)->exists();
        if (! $exists) {
            return;
        }

        DB::table('marketplace_notification_reads')->insertOrIgnore([
            'marketplace_notification_id' => $notificationId,
            'marketplace_admin_id' => $adminId,
            'read_at' => now(),
        ]);
    }

    public function markUnread(int $notificationId): void
    {
        $adminId = $this->admin()?->id;
        if (! $adminId) {
            return;
        }

        DB::table('marketplace_notification_reads')
            ->where('marketplace_notification_id', $notificationId)
            ->where('marketplace_admin_id', $adminId)
            ->delete();
    }

    public function markAllRead(): int
    {
        $adminId = $this->admin()?->id;
        if (! $adminId) {
            return 0;
        }

        $now = now();
        $rows = $this->unreadQuery()->pluck('id')->map(fn ($id) => [
            'marketplace_notification_id' => $id,
            'marketplace_admin_id' => $adminId,
            'read_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('marketplace_notification_reads')->insertOrIgnore($chunk);
        }

        return count($rows);
    }
}
