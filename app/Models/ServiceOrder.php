<?php

namespace App\Models;

use App\Http\Controllers\Api\MarketplaceClient\Organizer\ServiceOrderController;
use App\Jobs\SendNewsletterJob;
use App\Services\OrganizerNotificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ServiceOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'order_number',
        'marketplace_client_id',
        'marketplace_organizer_id',
        'marketplace_event_id',
        'service_type',
        'config',
        'subtotal',
        'tax',
        'total',
        'currency',
        'payment_method',
        'payment_status',
        'paid_at',
        'payment_reference',
        'status',
        'scheduled_at',
        'executed_at',
        'sent_count',
        'brevo_campaign_id',
        'service_start_date',
        'service_end_date',
        'admin_notes',
        'assigned_to',
    ];

    protected $casts = [
        'config' => 'array',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'executed_at' => 'datetime',
        'service_start_date' => 'date',
        'service_end_date' => 'date',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    // Payment status constants
    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_REFUNDED = 'refunded';

    // Service type constants
    public const TYPE_FEATURING = 'featuring';
    public const TYPE_EMAIL = 'email';
    public const TYPE_TRACKING = 'tracking';
    public const TYPE_CAMPAIGN = 'campaign';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber($order->marketplace_client_id);
            }
        });
    }

    /**
     * Generate a unique order number
     */
    public static function generateOrderNumber(int $marketplaceClientId): string
    {
        $year = date('Y');
        $prefix = 'SVC';

        $lastOrder = self::where('marketplace_client_id', $marketplaceClientId)
            ->where('order_number', 'like', "{$prefix}-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->order_number, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $year, $newNumber);
    }

    /**
     * Get service type label
     */
    public function getServiceTypeLabelAttribute(): string
    {
        return match ($this->service_type) {
            self::TYPE_FEATURING => 'Promovare Eveniment',
            self::TYPE_EMAIL => 'Email Marketing',
            self::TYPE_TRACKING => 'Ad Tracking',
            self::TYPE_CAMPAIGN => 'Creare Campanie',
            default => $this->service_type,
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_PAYMENT => 'Asteapta Plata',
            self::STATUS_PROCESSING => 'In Procesare',
            self::STATUS_ACTIVE => 'Activ',
            self::STATUS_COMPLETED => 'Finalizat',
            self::STATUS_CANCELLED => 'Anulat',
            self::STATUS_REFUNDED => 'Rambursat',
            default => $this->status,
        };
    }

    /**
     * Get payment status label
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_PENDING => 'In Asteptare',
            self::PAYMENT_PAID => 'Platit',
            self::PAYMENT_FAILED => 'Esuat',
            self::PAYMENT_REFUNDED => 'Rambursat',
            default => $this->payment_status,
        };
    }

    /**
     * Get status color for badges
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PENDING_PAYMENT => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_ACTIVE => 'success',
            self::STATUS_COMPLETED => 'primary',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_REFUNDED => 'danger',
            default => 'gray',
        };
    }

    /**
     * Mark order as paid
     */
    public function markAsPaid(string $paymentReference = null): self
    {
        $this->update([
            'payment_status' => self::PAYMENT_PAID,
            'paid_at' => now(),
            'payment_reference' => $paymentReference,
            'status' => self::STATUS_PROCESSING,
        ]);

        return $this;
    }

    /**
     * Activate the service
     */
    public function activate(): self
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
        ]);

        // Mark event as featured for the purchased locations
        if ($this->service_type === self::TYPE_FEATURING && $this->marketplace_event_id) {
            $locations = $this->config['locations'] ?? [];
            $updates = [];
            if (in_array('home_hero', $locations))            $updates['is_homepage_featured'] = true;
            if (in_array('home_recommendations', $locations)) $updates['is_general_featured']  = true;
            if (in_array('category', $locations))             $updates['is_category_featured'] = true;
            if (in_array('city', $locations))                 $updates['is_city_featured']     = true;
            if (! empty($updates)) {
                Event::where('id', $this->marketplace_event_id)->update($updates);
            }
        }

        // Create and dispatch email campaign
        if ($this->service_type === self::TYPE_EMAIL) {
            try {
                $this->createEmailCampaign();
            } catch (\Exception $e) {
                Log::error('Failed to create email campaign', [
                    'service_order_id' => $this->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Notify organizer that service has started
        try {
            OrganizerNotificationService::notifyServiceOrderStatus($this, 'started');
        } catch (\Exception $e) {
            Log::warning('Failed to send service started notification', [
                'service_order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this;
    }

    /**
     * Complete the service
     */
    public function complete(): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
        ]);

        // Unmark event featuring for locations that no longer have an active order
        if ($this->service_type === self::TYPE_FEATURING && $this->marketplace_event_id) {
            $locations = $this->config['locations'] ?? [];

            // Find other ACTIVE featuring orders for same event to avoid disabling flags still in use
            $otherActiveLocations = [];
            self::where('marketplace_event_id', $this->marketplace_event_id)
                ->where('id', '!=', $this->id)
                ->where('service_type', self::TYPE_FEATURING)
                ->where('status', self::STATUS_ACTIVE)
                ->get()
                ->each(function ($order) use (&$otherActiveLocations) {
                    $otherActiveLocations = array_merge($otherActiveLocations, $order->config['locations'] ?? []);
                });

            $updates = [];
            if (in_array('home_hero', $locations) && ! in_array('home_hero', $otherActiveLocations)) {
                $updates['is_homepage_featured'] = false;
            }
            if (in_array('home_recommendations', $locations) && ! in_array('home_recommendations', $otherActiveLocations)) {
                $updates['is_general_featured'] = false;
            }
            if (in_array('category', $locations) && ! in_array('category', $otherActiveLocations)) {
                $updates['is_category_featured'] = false;
            }
            if (in_array('city', $locations) && ! in_array('city', $otherActiveLocations)) {
                $updates['is_city_featured'] = false;
            }

            if (! empty($updates)) {
                Event::where('id', $this->marketplace_event_id)->update($updates);
            }
        }

        // Notify organizer that service is completed
        try {
            OrganizerNotificationService::notifyServiceOrderStatus($this, 'completed');
        } catch (\Exception $e) {
            Log::warning('Failed to send service completed notification', [
                'service_order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this;
    }

    /**
     * Mark that results are available
     */
    public function markResultsAvailable(): self
    {
        // Notify organizer that results are ready
        try {
            OrganizerNotificationService::notifyServiceOrderStatus($this, 'results');
        } catch (\Exception $e) {
            Log::warning('Failed to send service results notification', [
                'service_order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this;
    }

    /**
     * Notify about invoice generation
     */
    public function notifyInvoiceGenerated(): self
    {
        // Notify organizer about invoice
        try {
            OrganizerNotificationService::notifyServiceOrderStatus($this, 'invoice');
        } catch (\Exception $e) {
            Log::warning('Failed to send service invoice notification', [
                'service_order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this;
    }

    /**
     * Create email campaign: newsletter + recipients + dispatch job
     */
    protected function createEmailCampaign(): void
    {
        $config = $this->config ?? [];
        $marketplace = $this->marketplaceClient;
        $event = Event::with(['venue', 'artists', 'marketplaceCity'])->find($this->marketplace_event_id);
        $organizer = $this->marketplaceOrganizer;

        if (!$marketplace || !$event || !$organizer) {
            Log::warning('Email campaign missing dependencies', [
                'service_order_id' => $this->id,
                'has_marketplace' => !!$marketplace,
                'has_event' => !!$event,
                'has_organizer' => !!$organizer,
            ]);
            return;
        }

        $template = $config['template'] ?? 'classic';
        // Parse send_date as Europe/Bucharest (client timezone) then convert to UTC for server
        $sendDate = !empty($config['send_date'])
            ? \Carbon\Carbon::parse($config['send_date'], 'Europe/Bucharest')->utc()
            : null;
        $filters = $config['filters'] ?? [];

        // Generate email HTML
        $bodyHtml = $this->generateEmailHtml($template, $event, $marketplace);
        $subject = $this->generateEmailSubject($template, $event);

        // Create newsletter
        $newsletter = MarketplaceNewsletter::create([
            'marketplace_client_id' => $marketplace->id,
            'name' => "Campanie: {$this->getEventName($event)} ({$this->order_number})",
            'subject' => $subject,
            'from_name' => $marketplace->getEmailFromName(),
            'from_email' => $marketplace->getEmailFromAddress(),
            'reply_to' => $marketplace->getEmailFromAddress(),
            'body_html' => $bodyHtml,
            'status' => 'draft',
            'target_lists' => ['service_order_id' => $this->id],
            'total_recipients' => 0,
        ]);

        // Build recipient list using the same filters from the service order config
        $audienceType = $config['audience_type'] ?? 'own';
        $controller = app(ServiceOrderController::class);
        $baseQuery = $controller->buildAudienceBaseQuery($organizer, $audienceType);

        $normalizedFilters = [
            'ageMin' => $filters['age_min'] ?? null,
            'ageMax' => $filters['age_max'] ?? null,
            'gender' => $filters['gender'] ?? null,
            'cities' => $filters['cities'] ?? [],
            'categories' => $filters['categories'] ?? [],
            'genres' => $filters['genres'] ?? [],
        ];

        $filteredQuery = $controller->applyAudienceFilters(
            clone $baseQuery, $normalizedFilters, $organizer, $audienceType
        );

        // Create recipient records
        $customers = $filteredQuery->get();
        $recipientCount = 0;

        foreach ($customers as $customer) {
            if (empty($customer->email)) continue;

            MarketplaceNewsletterRecipient::create([
                'newsletter_id' => $newsletter->id,
                'marketplace_customer_id' => $customer->id,
                'email' => $customer->email,
                'status' => 'pending',
            ]);
            $recipientCount++;
        }

        $newsletter->update(['total_recipients' => $recipientCount]);

        // Update service order with scheduled_at and service_start_date
        $updateData = [];
        if ($sendDate) {
            $updateData['scheduled_at'] = $sendDate;
            $updateData['service_start_date'] = $sendDate->toDateString();
        } else {
            $updateData['scheduled_at'] = now();
            $updateData['service_start_date'] = now()->toDateString();
        }
        $this->update($updateData);

        // Dispatch or schedule
        if ($sendDate && $sendDate->isFuture()) {
            $newsletter->schedule($sendDate->toDateTimeImmutable());
            SendNewsletterJob::dispatch($newsletter)->delay($sendDate);
        } else {
            $newsletter->startSending();
            SendNewsletterJob::dispatch($newsletter);
        }

        Log::info('Email campaign created', [
            'service_order_id' => $this->id,
            'newsletter_id' => $newsletter->id,
            'recipient_count' => $recipientCount,
            'template' => $template,
            'scheduled' => $sendDate?->toDateTimeString(),
        ]);
    }

    /**
     * Subject variants matching the frontend (5 per template)
     */
    protected static array $subjectVariants = [
        'classic' => [
            '%s - Nu rata!',
            'Esti pregatit? %s te asteapta!',
            'Bilete disponibile: %s',
            'Hai la %s! Asigura-ti locul',
            '%s - Evenimentul pe care nu vrei sa il ratezi',
        ],
        'urgent' => [
            'ULTIMELE BILETE pentru %s!',
            'Stoc limitat! %s se vinde rapid',
            'Nu rata %s - mai sunt putine bilete!',
            'Ultimele locuri disponibile la %s',
            'Grabati biletele! %s aproape sold out',
        ],
        'reminder' => [
            'Reminder: %s este in curand!',
            'Nu uita! %s se apropie',
            'Mai sunt cateva zile pana la %s',
            '%s - inca mai poti obtine bilete',
            'Pregateste-te pentru %s!',
        ],
    ];

    /**
     * Promo text variants matching the frontend (5 per template)
     */
    protected static array $promoTextVariants = [
        'classic' => [
            'Evenimentul pe care il asteptai este aproape! Asigura-te ca ai bilete pentru a nu rata aceasta experienta unica.',
            'Un eveniment pe care nu vrei sa il ratezi. Rezerva-ti biletele acum si pregateste-te pentru o seara de neuitat!',
            'Vino sa traiesti o experienta memorabila! Biletele sunt disponibile, nu amana - asigura-ti locul chiar acum.',
            'Esti gata pentru o experienta extraordinara? Biletele se vand repede, asa ca nu ezita sa iti faci rezervarea.',
            'Fii parte din acest eveniment special! Profita de disponibilitate si cumpara biletele cat mai sunt locuri.',
        ],
        'urgent' => [
            'Biletele se vand rapid! Rezerva-ti locul acum pentru a nu ramane pe dinafara.',
            'Stocul este aproape epuizat! Nu mai sta pe ganduri - aceasta ar putea fi ultima ta sansa.',
            'Cererea este uriasa si locurile se termina! Actioneaza acum si nu rata acest eveniment.',
            'Ultimele bilete se vand chiar acum. Daca inca nu ti-ai asigurat locul, acum e momentul!',
            'Disponibilitatea scade rapid! Fiecare minut conteaza - cumpara biletele inainte sa fie prea tarziu.',
        ],
        'reminder' => [
            'Pregateste-te pentru o experienta de neuitat! Nu uita sa iti rezervi biletele daca nu ai facut-o deja.',
            'Evenimentul este chiar dupa colt! Daca nu ai bilete inca, mai ai sansa sa le obtii acum.',
            'Marcheaza-ti in calendar si nu rata! Biletele sunt inca disponibile pentru tine.',
            'Numaratoarea inversa a inceput! Ai tot ce iti trebuie? Daca nu, biletele te asteapta.',
            'Evenimentul se apropie cu pasi repezi. Asigura-te ca esti pregatit - cumpara bilete acum!',
        ],
    ];

    /**
     * Generate email subject based on template type and variant index from config
     */
    protected function getEventName(Event $event): string
    {
        $title = $event->title;
        if (is_array($title)) {
            return $title['ro'] ?? $title['en'] ?? array_values($title)[0] ?? '';
        }
        return $title ?? '';
    }

    protected function getEventStartsAt(Event $event): ?\Carbon\Carbon
    {
        if ($event->event_date) {
            $date = $event->event_date->format('Y-m-d');
            $time = $event->start_time ?? '00:00';
            return \Carbon\Carbon::parse("{$date} {$time}");
        }
        return null;
    }

    protected function getEventImageUrl(Event $event): ?string
    {
        $path = $event->poster_url;
        if (empty($path)) return null;
        if (str_starts_with($path, 'http')) return $path;
        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/');
    }

    protected function getLocalizedField(Model $model, string $field): string
    {
        $value = $model->$field;
        if (is_array($value)) {
            return $value['ro'] ?? $value['en'] ?? array_values($value)[0] ?? '';
        }
        return $value ?? '';
    }

    protected function generateEmailSubject(string $template, Event $event): string
    {
        $name = $this->getEventName($event);
        $variants = self::$subjectVariants[$template] ?? self::$subjectVariants['classic'];
        $config = $this->config ?? [];
        $variantIndices = $config['variant_indices'] ?? [];
        $key = $template . '_subject';
        $idx = isset($variantIndices[$key]) ? (int) $variantIndices[$key] : array_rand($variants);
        $idx = max(0, min($idx, count($variants) - 1));

        return sprintf($variants[$idx], $name);
    }

    /**
     * Get the promo text based on template type and variant index from config
     */
    protected function getPromoText(string $template): string
    {
        $variants = self::$promoTextVariants[$template] ?? self::$promoTextVariants['classic'];
        $config = $this->config ?? [];
        $variantIndices = $config['variant_indices'] ?? [];
        $key = $template . '_promo';
        $idx = isset($variantIndices[$key]) ? (int) $variantIndices[$key] : array_rand($variants);
        $idx = max(0, min($idx, count($variants) - 1));

        return $variants[$idx];
    }

    /**
     * Build venue info HTML section for email
     */
    protected function buildEmailVenueBox(Event $event): string
    {
        $venue = $event->venue;
        $rawName = $venue ? $this->getLocalizedField($venue, 'name') : '';
        $venueName = e($rawName);
        $venueCity = e($venue->city ?? $event->marketplaceCity?->name ?? '');
        if (!$venueName) return '';

        $cityHtml = $venueCity
            ? "<p style=\"margin: 2px 0 0; font-size: 13px; color: #6b7280;\">{$venueCity}</p>"
            : '';

        return <<<HTML
            <tr>
                <td style="padding: 0 30px 20px;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px;">
                        <tr>
                            <td style="padding: 16px 20px;">
                                <table cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="vertical-align: top; padding-right: 12px;">
                                            <div style="width: 40px; height: 40px; background-color: #e5e7eb; border-radius: 8px; text-align: center; line-height: 40px; font-size: 18px;">📍</div>
                                        </td>
                                        <td style="vertical-align: top;">
                                            <p style="margin: 0; font-size: 14px; font-weight: 600; color: #1f2937;">{$venueName}</p>
                                            {$cityHtml}
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
HTML;
    }

    /**
     * Build artists HTML section for email
     */
    protected function buildEmailArtistsBox(Event $event): string
    {
        $artists = $event->artists->pluck('name')->filter()->toArray();
        if (empty($artists)) return '';

        $pills = '';
        foreach ($artists as $artistName) {
            $name = e($artistName);
            $pills .= "<span style=\"display: inline-block; background-color: #f3e8ff; color: #7c3aed; font-size: 13px; font-weight: 500; padding: 4px 14px; border-radius: 20px; margin: 3px 4px;\">{$name}</span> ";
        }

        return <<<HTML
            <tr>
                <td style="padding: 0 30px 20px;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px;">
                        <tr>
                            <td style="padding: 16px 20px;">
                                <p style="margin: 0 0 8px; font-size: 11px; font-weight: 700; color: #7c3aed; text-transform: uppercase; letter-spacing: 1px;">Artisti</p>
                                <div>{$pills}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
HTML;
    }

    /**
     * Build description HTML section for email
     */
    protected function buildEmailDescriptionBox(Event $event): string
    {
        $shortDesc = $this->getLocalizedField($event, 'short_description');
        $fullDesc = $this->getLocalizedField($event, 'description');
        $desc = $shortDesc ?: $fullDesc;
        if (!$desc) return '';

        $truncated = e(mb_strlen($desc) > 300 ? mb_substr($desc, 0, 300) . '...' : $desc);

        return <<<HTML
            <tr>
                <td style="padding: 0 30px 20px; text-align: center;">
                    <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 1.6;">{$truncated}</p>
                </td>
            </tr>
HTML;
    }

    /**
     * Generate responsive email HTML for the campaign
     */
    protected function generateEmailHtml(string $template, Event $event, MarketplaceClient $marketplace): string
    {
        $eventName = e($this->getEventName($event));
        $startsAt = $this->getEventStartsAt($event);
        $eventDate = $startsAt ? $startsAt->format('d.m.Y, H:i') : 'TBA';
        $venue = $event->venue;
        $rawVenueName = $venue ? $this->getLocalizedField($venue, 'name') : 'TBA';
        $venueName = e($rawVenueName);
        $venueCity = e($venue->city ?? $event->marketplaceCity?->name ?? '');
        $venueDisplay = $venueCity ? "{$venueName}, {$venueCity}" : $venueName;
        $imageUrl = $this->getEventImageUrl($event);
        $eventUrl = "https://{$marketplace->domain}/{$event->slug}";
        $marketplaceName = e($marketplace->name);

        $bodyText = e($this->getPromoText($template));

        // Extra sections: description, venue, artists
        $descriptionSection = $this->buildEmailDescriptionBox($event);
        $venueBox = $this->buildEmailVenueBox($event);
        $artistsBox = $this->buildEmailArtistsBox($event);

        $accentColor = '#6366f1';
        $urgentColor = '#ef4444';
        $reminderColor = '#3b82f6';

        // Template-specific settings
        $banner = '';
        $ctaColor = $accentColor;
        $ctaText = 'Cumpara Bilete Acum';

        if ($template === 'urgent') {
            $ctaColor = $urgentColor;
            $ctaText = 'Cumpara ACUM - Stoc Limitat!';
            $banner = <<<HTML
            <tr>
                <td style="padding: 0 30px;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; margin-bottom: 24px;">
                        <tr>
                            <td style="padding: 16px 20px;">
                                <p style="margin: 0; font-weight: bold; color: #b91c1c; font-size: 16px;">⚡ Ultimele bilete disponibile!</p>
                                <p style="margin: 4px 0 0; color: #dc2626; font-size: 14px;">Nu rata sansa de a fi acolo</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
HTML;
        } elseif ($template === 'reminder') {
            $ctaColor = $reminderColor;
            $ctaText = 'Vezi Detalii & Cumpara Bilete';
            $banner = <<<HTML
            <tr>
                <td style="padding: 0 30px;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; margin-bottom: 24px;">
                        <tr>
                            <td style="padding: 16px 20px;">
                                <p style="margin: 0; font-weight: bold; color: #1d4ed8; font-size: 16px;">🎉 Evenimentul se apropie!</p>
                                <p style="margin: 4px 0 0; color: #2563eb; font-size: 14px;">Inca mai poti obtine bilete</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
HTML;
        }

        // Image section - full width for classic/urgent, full width for reminder too
        $imageSection = '';
        if ($imageUrl) {
            $imageSection = <<<HTML
            <tr>
                <td style="padding: 0 30px 20px; text-align: center;">
                    <img src="{$imageUrl}" alt="{$eventName}" width="520" style="max-width: 100%; height: auto; border-radius: 12px; display: block; margin: 0 auto;" />
                </td>
            </tr>
HTML;
        }

        // Reminder template layout: banner → title → image → date/venue gradient box → description/venue/artists → promo → CTA
        if ($template === 'reminder') {
            return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$eventName}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 30px 10px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <tr>
                        <td style="background-color: {$ctaColor}; padding: 20px 30px; text-align: center;">
                            <p style="margin: 0; color: #ffffff; font-size: 18px; font-weight: bold;">{$marketplaceName}</p>
                        </td>
                    </tr>
                    <tr><td style="height: 24px;"></td></tr>

                    {$banner}

                    <tr>
                        <td style="padding: 0 30px 16px; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px; font-weight: bold; color: #1f2937;">{$eventName}</h1>
                        </td>
                    </tr>

                    {$imageSection}

                    <!-- Gradient date/venue box -->
                    <tr>
                        <td style="padding: 0 30px 20px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(to right, #3b82f6, #8b5cf6); border-radius: 12px;">
                                <tr>
                                    <td style="padding: 24px; text-align: center;">
                                        <p style="margin: 0; font-size: 11px; color: rgba(255,255,255,0.8); text-transform: uppercase; letter-spacing: 1px;">Marcheaza in calendar</p>
                                        <p style="margin: 8px 0; font-size: 26px; font-weight: bold; color: #ffffff;">{$eventDate}</p>
                                        <p style="margin: 0; font-size: 16px; color: #ffffff;">{$venueDisplay}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {$descriptionSection}
                    {$venueBox}
                    {$artistsBox}

                    <tr>
                        <td style="padding: 0 30px 24px; text-align: center;">
                            <p style="margin: 0; font-size: 15px; color: #6b7280; line-height: 1.6;">{$bodyText}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 30px 30px; text-align: center;">
                            <a href="{$eventUrl}" target="_blank" style="display: inline-block; background-color: {$ctaColor}; color: #ffffff; padding: 14px 32px; border-radius: 12px; font-size: 16px; font-weight: bold; text-decoration: none;">{$ctaText}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 30px;"><hr style="border: none; border-top: 1px solid #e5e7eb; margin: 0;"></td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 30px 24px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                                Ai primit acest email pentru ca esti abonat la newsletter-ul {$marketplaceName}.<br>
                                <a href="{{unsubscribe_url}}" style="color: {$ctaColor}; text-decoration: underline;">Dezabonare</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
        }

        // Classic and urgent template layout: banner → image → title → date/venue → description/venue/artists → promo → CTA
        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$eventName}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 30px 10px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <tr>
                        <td style="background-color: {$ctaColor}; padding: 20px 30px; text-align: center;">
                            <p style="margin: 0; color: #ffffff; font-size: 18px; font-weight: bold;">{$marketplaceName}</p>
                        </td>
                    </tr>
                    <tr><td style="height: 24px;"></td></tr>

                    {$banner}

                    {$imageSection}

                    <tr>
                        <td style="padding: 0 30px 16px; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px; font-weight: bold; color: #1f2937;">{$eventName}</h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 30px 20px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 12px;">
                                <tr>
                                    <td width="50%" style="padding: 16px; text-align: center; border-right: 1px solid #e5e7eb;">
                                        <p style="margin: 0; font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px;">Data</p>
                                        <p style="margin: 4px 0 0; font-size: 15px; font-weight: 600; color: #1f2937;">{$eventDate}</p>
                                    </td>
                                    <td width="50%" style="padding: 16px; text-align: center;">
                                        <p style="margin: 0; font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px;">Locatie</p>
                                        <p style="margin: 4px 0 0; font-size: 15px; font-weight: 600; color: #1f2937;">{$venueDisplay}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {$descriptionSection}
                    {$venueBox}
                    {$artistsBox}

                    <tr>
                        <td style="padding: 0 30px 24px; text-align: center;">
                            <p style="margin: 0; font-size: 15px; color: #6b7280; line-height: 1.6;">{$bodyText}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 30px 30px; text-align: center;">
                            <a href="{$eventUrl}" target="_blank" style="display: inline-block; background-color: {$ctaColor}; color: #ffffff; padding: 14px 32px; border-radius: 12px; font-size: 16px; font-weight: bold; text-decoration: none;">{$ctaText}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 30px;"><hr style="border: none; border-top: 1px solid #e5e7eb; margin: 0;"></td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 30px 24px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                                Ai primit acest email pentru ca esti abonat la newsletter-ul {$marketplaceName}.<br>
                                <a href="{{unsubscribe_url}}" style="color: {$ctaColor}; text-decoration: underline;">Dezabonare</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Cancel the order
     */
    public function cancel(): self
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);

        return $this;
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_PAYMENT,
        ]);
    }

    /**
     * Check if order can be refunded
     */
    public function canBeRefunded(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID
            && in_array($this->status, [
                self::STATUS_PROCESSING,
                self::STATUS_ACTIVE,
            ]);
    }

    // Relationships

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'marketplace_event_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the linked newsletter for email campaigns
     */
    public function getLinkedNewsletter(): ?MarketplaceNewsletter
    {
        if ($this->service_type !== self::TYPE_EMAIL) return null;

        return MarketplaceNewsletter::where('marketplace_client_id', $this->marketplace_client_id)
            ->whereJsonContains('target_lists->service_order_id', $this->id)
            ->first();
    }

    // Scopes

    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    public function scopeForOrganizer($query, int $organizerId)
    {
        return $query->where('marketplace_organizer_id', $organizerId);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING_PAYMENT,
            self::STATUS_PROCESSING,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_PAID);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('service_type', $type);
    }
}
