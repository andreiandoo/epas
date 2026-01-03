# Ticket Resale Marketplace Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Customers who can't attend events have no official way to resell tickets:
1. **Lost revenue**: Customers stuck with unusable tickets
2. **Scalping**: Third-party sites charging excessive markups
3. **Fraud risk**: Unofficial resales lead to fake ticket scams
4. **Missed opportunities**: Fans who want tickets can't get them after sellout

### What This Feature Does
- Official peer-to-peer ticket resale marketplace
- Price controls (cap at face value or small markup)
- Secure transfer with ticket regeneration
- Platform commission on resales
- Seller verification and buyer protection
- Waitlist integration for sold-out events

---

## Technical Implementation

### 1. Database Migrations

```php
// 2026_01_03_000060_create_resale_tables.php
Schema::create('resale_listings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('ticket_id')->constrained();
    $table->foreignId('seller_id')->constrained('customers');
    $table->foreignId('event_id')->constrained();
    $table->decimal('original_price', 10, 2);
    $table->decimal('asking_price', 10, 2);
    $table->decimal('platform_fee', 10, 2)->default(0);
    $table->decimal('seller_payout', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->enum('status', ['active', 'pending', 'sold', 'cancelled', 'expired'])->default('active');
    $table->text('seller_notes')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('sold_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['tenant_id', 'event_id', 'status']);
    $table->index(['status', 'expires_at']);
});

Schema::create('resale_purchases', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('listing_id')->constrained('resale_listings');
    $table->foreignId('buyer_id')->constrained('customers');
    $table->foreignId('new_ticket_id')->nullable()->constrained('tickets');
    $table->foreignId('payment_id')->nullable();
    $table->decimal('purchase_price', 10, 2);
    $table->decimal('buyer_fee', 10, 2)->default(0);
    $table->decimal('total_paid', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->enum('status', ['pending', 'processing', 'completed', 'refunded', 'failed'])->default('pending');
    $table->string('payment_intent_id')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->index(['tenant_id', 'buyer_id']);
    $table->index(['listing_id']);
});

Schema::create('resale_waitlist', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('event_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('ticket_type_id')->nullable()->constrained();
    $table->integer('quantity')->default(1);
    $table->decimal('max_price', 10, 2)->nullable();
    $table->boolean('notify_email')->default(true);
    $table->boolean('notify_sms')->default(false);
    $table->enum('status', ['active', 'fulfilled', 'cancelled'])->default('active');
    $table->timestamps();

    $table->unique(['tenant_id', 'event_id', 'customer_id', 'ticket_type_id']);
});

Schema::create('resale_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->boolean('resale_enabled')->default(false);
    $table->decimal('max_markup_percentage', 5, 2)->default(0); // 0 = face value only
    $table->decimal('platform_fee_percentage', 5, 2)->default(10);
    $table->decimal('seller_fee_percentage', 5, 2)->default(5);
    $table->decimal('buyer_fee_percentage', 5, 2)->default(5);
    $table->integer('listing_expiry_hours')->default(72);
    $table->boolean('require_seller_verification')->default(false);
    $table->boolean('auto_approve_listings')->default(true);
    $table->timestamps();

    $table->unique('tenant_id');
});

// Add resale flags to tickets
Schema::table('tickets', function (Blueprint $table) {
    $table->boolean('is_resalable')->default(true);
    $table->boolean('is_resold')->default(false);
    $table->foreignId('original_ticket_id')->nullable()->constrained('tickets');
});
```

### 2. Models

```php
// app/Models/ResaleListing.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResaleListing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'ticket_id', 'seller_id', 'event_id',
        'original_price', 'asking_price', 'platform_fee', 'seller_payout',
        'currency', 'status', 'seller_notes', 'expires_at', 'sold_at',
    ];

    protected $casts = [
        'original_price' => 'decimal:2',
        'asking_price' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'seller_payout' => 'decimal:2',
        'expires_at' => 'datetime',
        'sold_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function purchase(): HasOne
    {
        return $this->hasOne(ResalePurchase::class, 'listing_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}

// app/Models/ResalePurchase.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResalePurchase extends Model
{
    protected $fillable = [
        'tenant_id', 'listing_id', 'buyer_id', 'new_ticket_id', 'payment_id',
        'purchase_price', 'buyer_fee', 'total_paid', 'currency',
        'status', 'payment_intent_id', 'completed_at',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'buyer_fee' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ResaleListing::class, 'listing_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function newTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'new_ticket_id');
    }
}

// app/Models/ResaleWaitlist.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResaleWaitlist extends Model
{
    protected $table = 'resale_waitlist';

    protected $fillable = [
        'tenant_id', 'event_id', 'customer_id', 'ticket_type_id',
        'quantity', 'max_price', 'notify_email', 'notify_sms', 'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'max_price' => 'decimal:2',
        'notify_email' => 'boolean',
        'notify_sms' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
}
```

### 3. Service Class

```php
// app/Services/Resale/ResaleService.php
<?php

namespace App\Services\Resale;

use App\Models\ResaleListing;
use App\Models\ResalePurchase;
use App\Models\ResaleWaitlist;
use App\Models\ResaleSettings;
use App\Models\Ticket;
use App\Models\Customer;
use App\Models\Event;
use App\Services\Payments\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResaleService
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Create a resale listing
     */
    public function createListing(
        Ticket $ticket,
        Customer $seller,
        float $askingPrice
    ): ResaleListing {
        // Validate ticket is resalable
        if (!$ticket->is_resalable) {
            throw new \Exception('This ticket cannot be resold');
        }

        if ($ticket->customer_id !== $seller->id) {
            throw new \Exception('You do not own this ticket');
        }

        // Check if already listed
        if (ResaleListing::where('ticket_id', $ticket->id)->active()->exists()) {
            throw new \Exception('This ticket is already listed for resale');
        }

        $settings = $this->getSettings($ticket->tenant_id);

        // Validate price against max markup
        $originalPrice = $ticket->price;
        $maxPrice = $originalPrice * (1 + ($settings->max_markup_percentage / 100));

        if ($askingPrice > $maxPrice) {
            throw new \Exception("Price cannot exceed {$maxPrice} ({$settings->max_markup_percentage}% above face value)");
        }

        // Calculate fees
        $platformFee = $askingPrice * ($settings->platform_fee_percentage / 100);
        $sellerFee = $askingPrice * ($settings->seller_fee_percentage / 100);
        $sellerPayout = $askingPrice - $platformFee - $sellerFee;

        $listing = ResaleListing::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'seller_id' => $seller->id,
            'event_id' => $ticket->event_id,
            'original_price' => $originalPrice,
            'asking_price' => $askingPrice,
            'platform_fee' => $platformFee,
            'seller_payout' => $sellerPayout,
            'currency' => $ticket->currency ?? 'USD',
            'status' => $settings->auto_approve_listings ? 'active' : 'pending',
            'expires_at' => now()->addHours($settings->listing_expiry_hours),
        ]);

        // Mark ticket as listed
        $ticket->update(['status' => 'listed_for_resale']);

        // Notify waitlist
        $this->notifyWaitlist($listing);

        return $listing;
    }

    /**
     * Purchase a resale listing
     */
    public function purchase(
        ResaleListing $listing,
        Customer $buyer,
        string $paymentMethodId
    ): ResalePurchase {
        if (!$listing->isActive()) {
            throw new \Exception('This listing is no longer available');
        }

        if ($listing->seller_id === $buyer->id) {
            throw new \Exception('You cannot purchase your own listing');
        }

        $settings = $this->getSettings($listing->tenant_id);
        $buyerFee = $listing->asking_price * ($settings->buyer_fee_percentage / 100);
        $totalPaid = $listing->asking_price + $buyerFee;

        return DB::transaction(function () use ($listing, $buyer, $paymentMethodId, $buyerFee, $totalPaid) {
            // Lock the listing
            $listing = ResaleListing::lockForUpdate()->find($listing->id);

            if (!$listing->isActive()) {
                throw new \Exception('This listing was just purchased');
            }

            // Mark as pending
            $listing->update(['status' => 'pending']);

            // Create purchase record
            $purchase = ResalePurchase::create([
                'tenant_id' => $listing->tenant_id,
                'listing_id' => $listing->id,
                'buyer_id' => $buyer->id,
                'purchase_price' => $listing->asking_price,
                'buyer_fee' => $buyerFee,
                'total_paid' => $totalPaid,
                'currency' => $listing->currency,
                'status' => 'processing',
            ]);

            // Process payment
            try {
                $paymentIntent = $this->stripeService->createPaymentIntent(
                    amount: (int) ($totalPaid * 100),
                    currency: $listing->currency,
                    paymentMethodId: $paymentMethodId,
                    metadata: [
                        'type' => 'resale_purchase',
                        'listing_id' => $listing->id,
                        'purchase_id' => $purchase->id,
                    ]
                );

                $purchase->update([
                    'payment_intent_id' => $paymentIntent->id,
                ]);

                if ($paymentIntent->status === 'succeeded') {
                    $this->completePurchase($purchase);
                }
            } catch (\Exception $e) {
                $listing->update(['status' => 'active']);
                $purchase->update(['status' => 'failed']);
                throw $e;
            }

            return $purchase->fresh();
        });
    }

    /**
     * Complete a purchase after payment success
     */
    public function completePurchase(ResalePurchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $listing = $purchase->listing;
            $oldTicket = $listing->ticket;

            // Invalidate old ticket
            $oldTicket->update([
                'status' => 'transferred',
                'is_resold' => true,
            ]);

            // Generate new ticket for buyer
            $newTicket = $oldTicket->replicate();
            $newTicket->customer_id = $purchase->buyer_id;
            $newTicket->ticket_number = Str::upper(Str::random(12));
            $newTicket->qr_code = null; // Will be regenerated
            $newTicket->barcode = null;
            $newTicket->status = 'valid';
            $newTicket->is_resalable = true;
            $newTicket->is_resold = true;
            $newTicket->original_ticket_id = $oldTicket->id;
            $newTicket->save();

            // Update purchase
            $purchase->update([
                'new_ticket_id' => $newTicket->id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Update listing
            $listing->update([
                'status' => 'sold',
                'sold_at' => now(),
            ]);

            // Schedule seller payout
            $this->schedulePayout($listing);

            // Remove buyer from waitlist
            ResaleWaitlist::where('customer_id', $purchase->buyer_id)
                ->where('event_id', $listing->event_id)
                ->delete();
        });
    }

    /**
     * Cancel a listing
     */
    public function cancelListing(ResaleListing $listing, Customer $seller): void
    {
        if ($listing->seller_id !== $seller->id) {
            throw new \Exception('You do not own this listing');
        }

        if (!in_array($listing->status, ['active', 'pending'])) {
            throw new \Exception('This listing cannot be cancelled');
        }

        $listing->update(['status' => 'cancelled']);
        $listing->ticket->update(['status' => 'valid']);
    }

    /**
     * Join waitlist for event
     */
    public function joinWaitlist(
        Event $event,
        Customer $customer,
        ?int $ticketTypeId = null,
        int $quantity = 1,
        ?float $maxPrice = null
    ): ResaleWaitlist {
        return ResaleWaitlist::updateOrCreate(
            [
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'customer_id' => $customer->id,
                'ticket_type_id' => $ticketTypeId,
            ],
            [
                'quantity' => $quantity,
                'max_price' => $maxPrice,
                'status' => 'active',
            ]
        );
    }

    /**
     * Leave waitlist
     */
    public function leaveWaitlist(Event $event, Customer $customer): void
    {
        ResaleWaitlist::where('event_id', $event->id)
            ->where('customer_id', $customer->id)
            ->update(['status' => 'cancelled']);
    }

    /**
     * Get active listings for event
     */
    public function getEventListings(Event $event, array $filters = [])
    {
        $query = ResaleListing::where('event_id', $event->id)
            ->active()
            ->with(['ticket.ticketType', 'seller:id,first_name']);

        if (isset($filters['ticket_type_id'])) {
            $query->whereHas('ticket', fn($q) =>
                $q->where('ticket_type_id', $filters['ticket_type_id'])
            );
        }

        if (isset($filters['max_price'])) {
            $query->where('asking_price', '<=', $filters['max_price']);
        }

        return $query->orderBy('asking_price', 'asc')->paginate(20);
    }

    /**
     * Notify waitlist of new listing
     */
    protected function notifyWaitlist(ResaleListing $listing): void
    {
        $ticket = $listing->ticket;

        $waitlistEntries = ResaleWaitlist::where('event_id', $listing->event_id)
            ->where('status', 'active')
            ->where(function ($q) use ($ticket, $listing) {
                $q->whereNull('ticket_type_id')
                    ->orWhere('ticket_type_id', $ticket->ticket_type_id);
            })
            ->where(function ($q) use ($listing) {
                $q->whereNull('max_price')
                    ->orWhere('max_price', '>=', $listing->asking_price);
            })
            ->get();

        foreach ($waitlistEntries as $entry) {
            // Dispatch notification job
            // NotifyWaitlistMember::dispatch($entry, $listing);
        }
    }

    /**
     * Schedule payout to seller
     */
    protected function schedulePayout(ResaleListing $listing): void
    {
        // In production, use Stripe Connect or similar
        // ScheduleResalePayout::dispatch($listing)->delay(now()->addHours(24));
    }

    /**
     * Get resale settings for tenant
     */
    public function getSettings(int $tenantId): ResaleSettings
    {
        return ResaleSettings::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'resale_enabled' => false,
                'max_markup_percentage' => 0,
                'platform_fee_percentage' => 10,
                'seller_fee_percentage' => 5,
                'buyer_fee_percentage' => 5,
                'listing_expiry_hours' => 72,
            ]
        );
    }

    /**
     * Expire old listings
     */
    public function expireListings(): int
    {
        $expired = ResaleListing::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $listing) {
            $listing->update(['status' => 'expired']);
            $listing->ticket->update(['status' => 'valid']);
        }

        return $expired->count();
    }
}
```

### 4. Controller

```php
// app/Http/Controllers/Api/TenantClient/ResaleController.php
<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Services\Resale\ResaleService;
use App\Models\ResaleListing;
use App\Models\Ticket;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResaleController extends Controller
{
    public function __construct(protected ResaleService $resaleService) {}

    /**
     * Get listings for an event
     */
    public function eventListings(Request $request, Event $event): JsonResponse
    {
        $listings = $this->resaleService->getEventListings($event, $request->all());

        return response()->json($listings);
    }

    /**
     * Create a resale listing
     */
    public function createListing(Request $request): JsonResponse
    {
        $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'asking_price' => 'required|numeric|min:0',
        ]);

        $ticket = Ticket::findOrFail($request->ticket_id);
        $seller = $request->user('customer');

        $listing = $this->resaleService->createListing(
            $ticket,
            $seller,
            $request->asking_price
        );

        return response()->json([
            'listing' => $listing->load('ticket'),
            'message' => 'Ticket listed for resale',
        ], 201);
    }

    /**
     * Cancel a listing
     */
    public function cancelListing(Request $request, ResaleListing $listing): JsonResponse
    {
        $this->resaleService->cancelListing($listing, $request->user('customer'));

        return response()->json(['message' => 'Listing cancelled']);
    }

    /**
     * Purchase a listing
     */
    public function purchase(Request $request, ResaleListing $listing): JsonResponse
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $purchase = $this->resaleService->purchase(
            $listing,
            $request->user('customer'),
            $request->payment_method_id
        );

        return response()->json([
            'purchase' => $purchase,
            'message' => 'Purchase initiated',
        ]);
    }

    /**
     * Get seller's listings
     */
    public function myListings(Request $request): JsonResponse
    {
        $listings = ResaleListing::where('seller_id', $request->user('customer')->id)
            ->with(['ticket.event', 'ticket.ticketType'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['listings' => $listings]);
    }

    /**
     * Get buyer's purchases
     */
    public function myPurchases(Request $request): JsonResponse
    {
        $purchases = $request->user('customer')
            ->resalePurchases()
            ->with(['listing.ticket.event', 'newTicket'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['purchases' => $purchases]);
    }

    /**
     * Join waitlist
     */
    public function joinWaitlist(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'ticket_type_id' => 'nullable|exists:ticket_types,id',
            'quantity' => 'integer|min:1|max:10',
            'max_price' => 'nullable|numeric|min:0',
        ]);

        $waitlist = $this->resaleService->joinWaitlist(
            $event,
            $request->user('customer'),
            $request->ticket_type_id,
            $request->quantity ?? 1,
            $request->max_price
        );

        return response()->json([
            'waitlist' => $waitlist,
            'message' => 'Added to waitlist',
        ]);
    }

    /**
     * Leave waitlist
     */
    public function leaveWaitlist(Request $request, Event $event): JsonResponse
    {
        $this->resaleService->leaveWaitlist($event, $request->user('customer'));

        return response()->json(['message' => 'Removed from waitlist']);
    }

    /**
     * Get resale settings for tenant
     */
    public function settings(Request $request): JsonResponse
    {
        $settings = $this->resaleService->getSettings($request->attributes->get('tenant_id'));

        return response()->json([
            'enabled' => $settings->resale_enabled,
            'max_markup_percentage' => $settings->max_markup_percentage,
            'buyer_fee_percentage' => $settings->buyer_fee_percentage,
        ]);
    }
}
```

### 5. Webhook Handler

```php
// app/Http/Controllers/Webhooks/ResalePaymentWebhookController.php
class ResalePaymentWebhookController extends Controller
{
    public function handle(Request $request, ResaleService $resaleService): Response
    {
        $payload = $request->all();

        if ($payload['type'] === 'payment_intent.succeeded') {
            $metadata = $payload['data']['object']['metadata'] ?? [];

            if (($metadata['type'] ?? null) === 'resale_purchase') {
                $purchase = ResalePurchase::find($metadata['purchase_id']);
                if ($purchase && $purchase->status === 'processing') {
                    $resaleService->completePurchase($purchase);
                }
            }
        }

        return response('OK', 200);
    }
}
```

### 6. Routes

```php
// routes/api.php
Route::prefix('tenant-client/resale')->middleware(['tenant'])->group(function () {
    Route::get('/settings', [ResaleController::class, 'settings']);
    Route::get('/events/{event}/listings', [ResaleController::class, 'eventListings']);

    Route::middleware('auth:customer')->group(function () {
        Route::post('/listings', [ResaleController::class, 'createListing']);
        Route::delete('/listings/{listing}', [ResaleController::class, 'cancelListing']);
        Route::post('/listings/{listing}/purchase', [ResaleController::class, 'purchase']);

        Route::get('/my/listings', [ResaleController::class, 'myListings']);
        Route::get('/my/purchases', [ResaleController::class, 'myPurchases']);

        Route::post('/events/{event}/waitlist', [ResaleController::class, 'joinWaitlist']);
        Route::delete('/events/{event}/waitlist', [ResaleController::class, 'leaveWaitlist']);
    });
});
```

### 7. Scheduled Commands

```php
// app/Console/Commands/ExpireResaleListings.php
class ExpireResaleListings extends Command
{
    protected $signature = 'resale:expire-listings';
    protected $description = 'Expire old resale listings';

    public function handle(ResaleService $resaleService): int
    {
        $count = $resaleService->expireListings();
        $this->info("Expired {$count} listings");
        return Command::SUCCESS;
    }
}

// Schedule in app/Console/Kernel.php
$schedule->command('resale:expire-listings')->hourly();
```

---

## Testing Checklist

1. [ ] Listing creation validates ownership
2. [ ] Price cap is enforced based on settings
3. [ ] Fees are calculated correctly
4. [ ] Purchase locks listing to prevent double sales
5. [ ] Payment is processed via Stripe
6. [ ] New ticket is generated for buyer
7. [ ] Old ticket is invalidated
8. [ ] QR code is regenerated for new ticket
9. [ ] Waitlist notifications are sent
10. [ ] Listings expire correctly
11. [ ] Seller can cancel active listings
12. [ ] Buyer cannot buy own listing
13. [ ] Resale settings are tenant-specific
