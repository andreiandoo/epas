<?php

namespace App\Services\Coupon;

use App\Models\Coupon\CouponCode;
use App\Models\Coupon\CouponCodeBatch;
use App\Models\Event;
use App\Models\MarketplaceOrganizerPromoCode;
use App\Models\TicketType;
use App\Services\Marketplace\SeriesAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generare în masă de coduri de reducere (/marketplace/coupon-codes-list/bulk).
 *
 * Codurile unui lot sunt unice, de unică folosință (1 utilizare total, 1 per
 * client) și se aplică unui singur tip de bilet. Se predau organizatorului ca
 * CSV, deci sunt ascunse din contul lui (Organizer\PromoCodeController).
 *
 * Inserarea ocolește evenimentele modelului: CouponCodeObserver ar recalcula
 * seriile fiscale ale evenimentului la FIECARE cod (500 de coduri = 500 de
 * recalculări) și activity log-ul ar scrie câte o intrare per cod. Seriile se
 * recalculează o singură dată, după salvarea lotului — rezultatul e același:
 * câte o serie pe cod, ca la codurile create individual.
 */
class BulkCouponCodeGenerator
{
    /** Litere mari + cifre, fără caracterele care se confundă: 0/O, 1/I/L. */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const MAX_QUANTITY = 5000;
    public const MIN_LENGTH = 6;
    public const MAX_LENGTH = 20;

    /**
     * @param  array{event_id:int, ticket_type_id:int, quantity:int, code_length:int, prefix:?string,
     *               campaign_id:?string, discount_type:string, discount_value:float|string,
     *               max_discount_amount:?string, min_purchase_amount:?string, starts_at:?string,
     *               expires_at:?string, first_purchase_only:?bool, combinable:?bool}  $s
     */
    public function generate(array $s, int $marketplaceClientId, ?int $adminId, ?int $userId): CouponCodeBatch
    {
        $event = Event::where('marketplace_client_id', $marketplaceClientId)->findOrFail((int) $s['event_id']);
        $ticketType = TicketType::where('event_id', $event->id)->findOrFail((int) $s['ticket_type_id']);

        $quantity = max(1, min(self::MAX_QUANTITY, (int) $s['quantity']));
        $length = max(self::MIN_LENGTH, min(self::MAX_LENGTH, (int) $s['code_length']));
        $prefix = self::normalizePrefix($s['prefix'] ?? null);

        $codes = $this->uniqueCodes($marketplaceClientId, $quantity, $length, $prefix);

        $batch = DB::transaction(function () use ($s, $event, $ticketType, $quantity, $length, $prefix, $codes, $marketplaceClientId, $adminId, $userId) {
            $batch = CouponCodeBatch::create([
                'marketplace_client_id' => $marketplaceClientId,
                'marketplace_organizer_id' => $event->marketplace_organizer_id,
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'campaign_id' => $s['campaign_id'] ?? null,
                'quantity' => $quantity,
                'code_length' => $length,
                'prefix' => $prefix,
                'settings' => [
                    'discount_type' => $s['discount_type'],
                    'discount_value' => (float) $s['discount_value'],
                    'max_discount_amount' => self::nullableFloat($s['max_discount_amount'] ?? null),
                    'min_purchase_amount' => self::nullableFloat($s['min_purchase_amount'] ?? null),
                    'starts_at' => $s['starts_at'] ?? null,
                    'expires_at' => $s['expires_at'] ?? null,
                    'first_purchase_only' => (bool) ($s['first_purchase_only'] ?? false),
                    'combinable' => (bool) ($s['combinable'] ?? false),
                ],
                'created_by' => $adminId,
            ]);

            $now = now();
            $base = [
                'marketplace_client_id' => $marketplaceClientId,
                'marketplace_organizer_id' => $event->marketplace_organizer_id,
                'campaign_id' => $s['campaign_id'] ?? null,
                'batch_id' => $batch->id,
                'code_type' => 'single_use',
                'discount_type' => $s['discount_type'],
                'discount_value' => (float) $s['discount_value'],
                'max_discount_amount' => self::nullableFloat($s['max_discount_amount'] ?? null),
                'min_purchase_amount' => self::nullableFloat($s['min_purchase_amount'] ?? null),
                'max_uses_total' => 1,
                'max_uses_per_user' => 1,
                'current_uses' => 0,
                'applicable_events' => json_encode([(int) $event->id]),
                'applicable_ticket_types' => json_encode([(int) $ticketType->id]),
                'first_purchase_only' => (bool) ($s['first_purchase_only'] ?? false),
                'starts_at' => !empty($s['starts_at']) ? \Carbon\Carbon::parse($s['starts_at']) : null,
                'expires_at' => !empty($s['expires_at']) ? \Carbon\Carbon::parse($s['expires_at']) : null,
                'status' => 'active',
                'is_public' => false,
                'combinable' => (bool) ($s['combinable'] ?? false),
                'source' => 'admin',
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach (array_chunk($codes, 500) as $chunk) {
                CouponCode::insert(array_map(fn (string $code) => ['id' => (string) Str::uuid(), 'code' => $code] + $base, $chunk));
            }

            return $batch;
        });

        $this->syncSeries($event);

        return $batch;
    }

    /** Seriile fiscale ale evenimentului — o singură dată pentru tot lotul. */
    public function syncSeries(?Event $event): void
    {
        if (!$event) {
            return;
        }

        try {
            app(SeriesAllocator::class)->syncForEvent($event);
        } catch (\Throwable $e) {
            Log::warning('[BulkCouponCodeGenerator] series sync failed for event ' . $event->id . ': ' . $e->getMessage());
        }
    }

    /**
     * $quantity coduri noi, unice în marketplace: nici în coupon_codes (inclusiv
     * șterse), nici în codurile promo ale organizatorilor — checkout-ul caută
     * întâi acolo, deci un cod identic ar aplica altă reducere.
     *
     * @return array<int, string>
     */
    public function uniqueCodes(int $marketplaceClientId, int $quantity, int $length, ?string $prefix): array
    {
        $codes = [];
        for ($round = 0; count($codes) < $quantity; $round++) {
            if ($round >= 20) {
                throw new \RuntimeException('Nu s-au putut genera suficiente coduri unice. Mărește lungimea codului.');
            }

            $candidates = [];
            $missing = $quantity - count($codes);
            while (count($candidates) < $missing) {
                $code = ($prefix ?? '') . self::randomCode($length);
                if (!isset($codes[$code])) {
                    $candidates[$code] = true;
                }
            }

            $taken = [];
            foreach (array_chunk(array_keys($candidates), 1000) as $chunk) {
                $taken += array_flip(CouponCode::withTrashed()
                    ->where('marketplace_client_id', $marketplaceClientId)
                    ->whereIn('code', $chunk)
                    ->pluck('code')->all());
                $taken += array_flip(MarketplaceOrganizerPromoCode::where('marketplace_client_id', $marketplaceClientId)
                    ->whereIn('code', $chunk)
                    ->pluck('code')->all());
            }

            foreach (array_keys($candidates) as $code) {
                if (!isset($taken[$code])) {
                    $codes[$code] = true;
                }
            }
        }

        return array_keys($codes);
    }

    public static function randomCode(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    public static function normalizePrefix(?string $prefix): ?string
    {
        $prefix = strtoupper(trim((string) $prefix));

        return $prefix === '' ? null : $prefix;
    }

    /** CSV pentru organizator: separator „;" și BOM, ca Excel-ul românesc să-l deschidă direct. */
    public function csv(CouponCodeBatch $batch): StreamedResponse
    {
        $batch->loadMissing(['event', 'ticketType', 'organizer']);

        $event = $batch->event;
        $eventTitle = $event
            ? (is_array($event->title) ? ($event->title['ro'] ?? $event->title['en'] ?? (collect($event->title)->first() ?: '')) : (string) $event->title)
            : '';
        $eventDate = ($event?->event_date ?? $event?->range_start_date ?? $event?->starts_at)?->format('d.m.Y') ?? '';
        $ticketName = $batch->ticketType
            ? (is_array($batch->ticketType->name) ? ($batch->ticketType->name['ro'] ?? (collect($batch->ticketType->name)->first() ?: '')) : (string) $batch->ticketType->name)
            : '';
        $organizerName = $batch->organizer?->name ?? '';

        $fileName = 'coduri-' . Str::slug($eventTitle ?: 'eveniment') . '-' . $batch->label . '.csv';

        return response()->streamDownload(function () use ($batch, $eventTitle, $eventDate, $ticketName, $organizerName) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Cod', 'Eveniment', 'Data eveniment', 'Tip bilet', 'Reducere', 'Valabil de la', 'Expiră la', 'Utilizări', 'Status', 'Organizator', 'Lot'], ';');

            $batch->codes()->orderBy('code')->chunk(1000, function ($codes) use ($out, $batch, $eventTitle, $eventDate, $ticketName, $organizerName) {
                foreach ($codes as $c) {
                    $discount = $c->discount_type === 'percentage'
                        ? rtrim(rtrim(number_format((float) $c->discount_value, 2, ',', ''), '0'), ',') . '%'
                        : number_format((float) $c->discount_value, 2, ',', '.') . ' lei';
                    fputcsv($out, [
                        $c->code,
                        $eventTitle,
                        $eventDate,
                        $ticketName,
                        $discount,
                        $c->starts_at?->format('d.m.Y H:i') ?? '',
                        $c->expires_at?->format('d.m.Y H:i') ?? '',
                        $c->current_uses . '/' . ($c->max_uses_total ?? '∞'),
                        ['active' => 'Activ', 'disabled' => 'Inactiv', 'exhausted' => 'Folosit', 'expired' => 'Expirat'][$c->status] ?? $c->status,
                        $organizerName,
                        $batch->label,
                    ], ';');
                }
            });

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function nullableFloat($v): ?float
    {
        return ($v === null || $v === '') ? null : (float) $v;
    }
}
