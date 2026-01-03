# Subscription & Season Passes Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Currently only single-event tickets exist. Many venues/organizers need:
1. **Recurring revenue**: Steady income instead of event-by-event sales
2. **Season tickets**: Sports teams, theaters, concert series
3. **Membership programs**: VIP access, priority booking
4. **Bundled experiences**: Multiple events at discounted rates
5. **Customer loyalty**: Lock in attendees for entire seasons

### What This Feature Does
- Season pass products for event series
- Subscription-based membership tiers
- Automatic ticket allocation for pass holders
- Renewal reminders and auto-renewal
- Priority access and early booking windows
- Usage tracking and analytics

---

## Technical Implementation

### 1. Database Migrations

```php
// 2026_01_03_000070_create_subscription_tables.php
Schema::create('subscription_plans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('name');
    $table->string('slug')->index();
    $table->text('description')->nullable();
    $table->enum('type', ['subscription', 'season_pass', 'membership'])->default('subscription');
    $table->decimal('price', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->enum('billing_period', ['monthly', 'quarterly', 'yearly', 'one_time'])->default('yearly');
    $table->integer('duration_days')->nullable(); // For one_time passes
    $table->json('benefits')->nullable();
    $table->integer('max_events')->nullable(); // null = unlimited
    $table->integer('max_guests_per_event')->default(1);
    $table->boolean('includes_priority_booking')->default(false);
    $table->integer('priority_booking_days')->default(0);
    $table->boolean('includes_free_cancellation')->default(false);
    $table->decimal('discount_percentage', 5, 2)->default(0);
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['tenant_id', 'slug']);
});

Schema::create('subscription_plan_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subscription_plan_id')->constrained()->onDelete('cascade');
    $table->foreignId('event_id')->constrained()->onDelete('cascade');
    $table->foreignId('ticket_type_id')->nullable()->constrained();
    $table->integer('tickets_per_event')->default(1);
    $table->timestamps();

    $table->unique(['subscription_plan_id', 'event_id']);
});

Schema::create('customer_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('subscription_plan_id')->constrained();
    $table->foreignId('order_id')->nullable()->constrained();
    $table->string('subscription_number')->unique();
    $table->enum('status', ['active', 'paused', 'cancelled', 'expired', 'past_due'])->default('active');
    $table->decimal('amount_paid', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->string('stripe_subscription_id')->nullable();
    $table->timestamp('started_at');
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('renewed_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->string('cancellation_reason')->nullable();
    $table->boolean('auto_renew')->default(true);
    $table->integer('events_used')->default(0);
    $table->timestamps();

    $table->index(['tenant_id', 'customer_id']);
    $table->index(['status', 'expires_at']);
});

Schema::create('subscription_event_claims', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_subscription_id')->constrained()->onDelete('cascade');
    $table->foreignId('event_id')->constrained();
    $table->foreignId('ticket_id')->nullable()->constrained();
    $table->enum('status', ['pending', 'claimed', 'cancelled', 'expired'])->default('pending');
    $table->timestamp('claimed_at')->nullable();
    $table->timestamp('claim_deadline')->nullable();
    $table->timestamps();

    $table->unique(['customer_subscription_id', 'event_id']);
});

Schema::create('subscription_benefits', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subscription_plan_id')->constrained()->onDelete('cascade');
    $table->string('name');
    $table->text('description')->nullable();
    $table->enum('type', ['discount', 'access', 'perk', 'priority'])->default('perk');
    $table->json('value')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 2. Models

```php
// app/Models/SubscriptionPlan.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SubscriptionPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'type',
        'price', 'currency', 'billing_period', 'duration_days',
        'benefits', 'max_events', 'max_guests_per_event',
        'includes_priority_booking', 'priority_booking_days',
        'includes_free_cancellation', 'discount_percentage',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'benefits' => 'array',
        'max_events' => 'integer',
        'max_guests_per_event' => 'integer',
        'includes_priority_booking' => 'boolean',
        'includes_free_cancellation' => 'boolean',
        'discount_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'subscription_plan_events')
            ->withPivot(['ticket_type_id', 'tickets_per_event'])
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    public function planBenefits(): HasMany
    {
        return $this->hasMany(SubscriptionBenefit::class);
    }

    public function isRecurring(): bool
    {
        return in_array($this->billing_period, ['monthly', 'quarterly', 'yearly']);
    }

    public function getBillingIntervalAttribute(): string
    {
        return match ($this->billing_period) {
            'monthly' => 'month',
            'quarterly' => 'month',
            'yearly' => 'year',
            default => 'year',
        };
    }

    public function getBillingIntervalCountAttribute(): int
    {
        return match ($this->billing_period) {
            'quarterly' => 3,
            default => 1,
        };
    }
}

// app/Models/CustomerSubscription.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSubscription extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'subscription_plan_id', 'order_id',
        'subscription_number', 'status', 'amount_paid', 'currency',
        'stripe_subscription_id', 'started_at', 'expires_at', 'renewed_at',
        'cancelled_at', 'cancellation_reason', 'auto_renew', 'events_used',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'renewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
        'events_used' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(SubscriptionEventClaim::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function canClaimEvent(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->plan->max_events === null) {
            return true;
        }

        return $this->events_used < $this->plan->max_events;
    }

    public function getRemainingEventsAttribute(): ?int
    {
        if ($this->plan->max_events === null) {
            return null;
        }

        return max(0, $this->plan->max_events - $this->events_used);
    }
}

// app/Models/SubscriptionEventClaim.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEventClaim extends Model
{
    protected $fillable = [
        'customer_subscription_id', 'event_id', 'ticket_id',
        'status', 'claimed_at', 'claim_deadline',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'claim_deadline' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class, 'customer_subscription_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
```

### 3. Service Class

```php
// app/Services/Subscription/SubscriptionService.php
<?php

namespace App\Services\Subscription;

use App\Models\SubscriptionPlan;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionEventClaim;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\Payments\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionService
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Purchase a subscription plan
     */
    public function purchase(
        SubscriptionPlan $plan,
        Customer $customer,
        string $paymentMethodId
    ): CustomerSubscription {
        return DB::transaction(function () use ($plan, $customer, $paymentMethodId) {
            // Create Stripe subscription or payment
            if ($plan->isRecurring()) {
                $stripeSubscription = $this->stripeService->createSubscription(
                    $customer,
                    $plan,
                    $paymentMethodId
                );
                $stripeSubscriptionId = $stripeSubscription->id;
            } else {
                // One-time payment
                $this->stripeService->charge(
                    $customer,
                    (int) ($plan->price * 100),
                    $plan->currency,
                    $paymentMethodId
                );
                $stripeSubscriptionId = null;
            }

            $expiresAt = $this->calculateExpiration($plan);

            $subscription = CustomerSubscription::create([
                'tenant_id' => $plan->tenant_id,
                'customer_id' => $customer->id,
                'subscription_plan_id' => $plan->id,
                'subscription_number' => $this->generateSubscriptionNumber(),
                'status' => 'active',
                'amount_paid' => $plan->price,
                'currency' => $plan->currency,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'started_at' => now(),
                'expires_at' => $expiresAt,
                'auto_renew' => $plan->isRecurring(),
            ]);

            // Auto-claim season pass events
            if ($plan->type === 'season_pass') {
                $this->autoClaimSeasonEvents($subscription);
            }

            return $subscription;
        });
    }

    /**
     * Claim a ticket for an event using subscription
     */
    public function claimEvent(
        CustomerSubscription $subscription,
        Event $event,
        int $quantity = 1
    ): SubscriptionEventClaim {
        if (!$subscription->canClaimEvent()) {
            throw new \Exception('No remaining event claims on this subscription');
        }

        // Check if event is included in plan
        $planEvent = $subscription->plan->events()
            ->where('event_id', $event->id)
            ->first();

        if (!$planEvent && !$this->isEventEligible($subscription, $event)) {
            throw new \Exception('This event is not included in your subscription');
        }

        // Check if already claimed
        if ($subscription->claims()->where('event_id', $event->id)->exists()) {
            throw new \Exception('You have already claimed a ticket for this event');
        }

        return DB::transaction(function () use ($subscription, $event, $planEvent, $quantity) {
            // Generate ticket
            $ticketTypeId = $planEvent?->pivot->ticket_type_id
                ?? $event->ticketTypes()->first()?->id;

            $ticket = Ticket::create([
                'tenant_id' => $subscription->tenant_id,
                'event_id' => $event->id,
                'ticket_type_id' => $ticketTypeId,
                'customer_id' => $subscription->customer_id,
                'ticket_number' => Str::upper(Str::random(12)),
                'status' => 'valid',
                'price' => 0, // Included in subscription
                'source' => 'subscription',
            ]);

            // Create claim
            $claim = SubscriptionEventClaim::create([
                'customer_subscription_id' => $subscription->id,
                'event_id' => $event->id,
                'ticket_id' => $ticket->id,
                'status' => 'claimed',
                'claimed_at' => now(),
            ]);

            // Update usage counter
            $subscription->increment('events_used');

            return $claim;
        });
    }

    /**
     * Cancel subscription
     */
    public function cancel(
        CustomerSubscription $subscription,
        ?string $reason = null,
        bool $immediately = false
    ): CustomerSubscription {
        if ($subscription->stripe_subscription_id) {
            $this->stripeService->cancelSubscription(
                $subscription->stripe_subscription_id,
                $immediately
            );
        }

        $subscription->update([
            'status' => $immediately ? 'cancelled' : 'active',
            'auto_renew' => false,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $subscription->fresh();
    }

    /**
     * Pause subscription
     */
    public function pause(CustomerSubscription $subscription): CustomerSubscription
    {
        if ($subscription->stripe_subscription_id) {
            $this->stripeService->pauseSubscription($subscription->stripe_subscription_id);
        }

        $subscription->update(['status' => 'paused']);

        return $subscription->fresh();
    }

    /**
     * Resume subscription
     */
    public function resume(CustomerSubscription $subscription): CustomerSubscription
    {
        if ($subscription->stripe_subscription_id) {
            $this->stripeService->resumeSubscription($subscription->stripe_subscription_id);
        }

        $subscription->update(['status' => 'active']);

        return $subscription->fresh();
    }

    /**
     * Handle renewal
     */
    public function handleRenewal(string $stripeSubscriptionId): void
    {
        $subscription = CustomerSubscription::where('stripe_subscription_id', $stripeSubscriptionId)
            ->firstOrFail();

        $newExpiration = $this->calculateExpiration($subscription->plan);

        $subscription->update([
            'status' => 'active',
            'expires_at' => $newExpiration,
            'renewed_at' => now(),
            'events_used' => 0, // Reset for new period
        ]);
    }

    /**
     * Handle failed payment
     */
    public function handlePaymentFailed(string $stripeSubscriptionId): void
    {
        CustomerSubscription::where('stripe_subscription_id', $stripeSubscriptionId)
            ->update(['status' => 'past_due']);
    }

    /**
     * Get customer's active subscriptions
     */
    public function getCustomerSubscriptions(Customer $customer)
    {
        return CustomerSubscription::where('customer_id', $customer->id)
            ->with(['plan', 'claims.event'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Check if customer has priority access
     */
    public function hasPriorityAccess(Customer $customer, Event $event): bool
    {
        return CustomerSubscription::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereHas('plan', function ($q) use ($event) {
                $q->where('includes_priority_booking', true)
                    ->where('priority_booking_days', '>', 0);
            })
            ->exists();
    }

    /**
     * Get subscriber discount for event
     */
    public function getSubscriberDiscount(Customer $customer, Event $event): float
    {
        $subscription = CustomerSubscription::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereHas('plan', fn($q) => $q->where('discount_percentage', '>', 0))
            ->with('plan')
            ->first();

        return $subscription?->plan->discount_percentage ?? 0;
    }

    /**
     * Auto-claim events for season pass
     */
    protected function autoClaimSeasonEvents(CustomerSubscription $subscription): void
    {
        $events = $subscription->plan->events()
            ->where('start_date', '>', now())
            ->get();

        foreach ($events as $event) {
            try {
                $this->claimEvent($subscription, $event);
            } catch (\Exception $e) {
                // Log but continue
                \Log::warning("Failed to auto-claim event {$event->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if event is eligible for subscription
     */
    protected function isEventEligible(CustomerSubscription $subscription, Event $event): bool
    {
        // Memberships might allow any event
        if ($subscription->plan->type === 'membership') {
            return $event->tenant_id === $subscription->tenant_id;
        }

        return false;
    }

    /**
     * Calculate expiration date
     */
    protected function calculateExpiration(SubscriptionPlan $plan): ?\DateTime
    {
        return match ($plan->billing_period) {
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'yearly' => now()->addYear(),
            'one_time' => $plan->duration_days ? now()->addDays($plan->duration_days) : null,
            default => null,
        };
    }

    /**
     * Generate subscription number
     */
    protected function generateSubscriptionNumber(): string
    {
        do {
            $number = 'SUB-' . Str::upper(Str::random(8));
        } while (CustomerSubscription::where('subscription_number', $number)->exists());

        return $number;
    }

    /**
     * Send renewal reminders
     */
    public function sendRenewalReminders(): int
    {
        $subscriptions = CustomerSubscription::where('status', 'active')
            ->where('auto_renew', true)
            ->whereBetween('expires_at', [now()->addDays(7), now()->addDays(8)])
            ->get();

        foreach ($subscriptions as $subscription) {
            // SendRenewalReminder::dispatch($subscription);
        }

        return $subscriptions->count();
    }

    /**
     * Expire subscriptions
     */
    public function expireSubscriptions(): int
    {
        return CustomerSubscription::where('status', 'active')
            ->where('auto_renew', false)
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
```

### 4. Controller

```php
// app/Http/Controllers/Api/TenantClient/SubscriptionController.php
<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Services\Subscription\SubscriptionService;
use App\Models\SubscriptionPlan;
use App\Models\CustomerSubscription;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptionService) {}

    /**
     * List available subscription plans
     */
    public function plans(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $plans = SubscriptionPlan::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('planBenefits')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['plans' => $plans]);
    }

    /**
     * Get plan details
     */
    public function planDetails(SubscriptionPlan $plan): JsonResponse
    {
        $plan->load(['events', 'planBenefits']);

        return response()->json(['plan' => $plan]);
    }

    /**
     * Purchase subscription
     */
    public function purchase(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $subscription = $this->subscriptionService->purchase(
            $plan,
            $request->user('customer'),
            $request->payment_method_id
        );

        return response()->json([
            'subscription' => $subscription->load('plan'),
            'message' => 'Subscription activated successfully',
        ], 201);
    }

    /**
     * Get my subscriptions
     */
    public function mySubscriptions(Request $request): JsonResponse
    {
        $subscriptions = $this->subscriptionService->getCustomerSubscriptions(
            $request->user('customer')
        );

        return response()->json(['subscriptions' => $subscriptions]);
    }

    /**
     * Get subscription details
     */
    public function subscriptionDetails(Request $request, CustomerSubscription $subscription): JsonResponse
    {
        if ($subscription->customer_id !== $request->user('customer')->id) {
            abort(403);
        }

        $subscription->load(['plan.planBenefits', 'claims.event', 'claims.ticket']);

        return response()->json([
            'subscription' => $subscription,
            'remaining_events' => $subscription->remaining_events,
        ]);
    }

    /**
     * Claim ticket for event
     */
    public function claimEvent(Request $request, CustomerSubscription $subscription): JsonResponse
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'quantity' => 'integer|min:1|max:10',
        ]);

        if ($subscription->customer_id !== $request->user('customer')->id) {
            abort(403);
        }

        $event = Event::findOrFail($request->event_id);

        $claim = $this->subscriptionService->claimEvent(
            $subscription,
            $event,
            $request->quantity ?? 1
        );

        return response()->json([
            'claim' => $claim->load('ticket'),
            'message' => 'Ticket claimed successfully',
        ]);
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request, CustomerSubscription $subscription): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
            'immediately' => 'boolean',
        ]);

        if ($subscription->customer_id !== $request->user('customer')->id) {
            abort(403);
        }

        $subscription = $this->subscriptionService->cancel(
            $subscription,
            $request->reason,
            $request->immediately ?? false
        );

        return response()->json([
            'subscription' => $subscription,
            'message' => 'Subscription cancelled',
        ]);
    }

    /**
     * Pause subscription
     */
    public function pause(Request $request, CustomerSubscription $subscription): JsonResponse
    {
        if ($subscription->customer_id !== $request->user('customer')->id) {
            abort(403);
        }

        $subscription = $this->subscriptionService->pause($subscription);

        return response()->json([
            'subscription' => $subscription,
            'message' => 'Subscription paused',
        ]);
    }

    /**
     * Resume subscription
     */
    public function resume(Request $request, CustomerSubscription $subscription): JsonResponse
    {
        if ($subscription->customer_id !== $request->user('customer')->id) {
            abort(403);
        }

        $subscription = $this->subscriptionService->resume($subscription);

        return response()->json([
            'subscription' => $subscription,
            'message' => 'Subscription resumed',
        ]);
    }
}
```

### 5. Webhook Handler

```php
// Handle Stripe subscription webhooks
class SubscriptionWebhookController extends Controller
{
    public function handle(Request $request, SubscriptionService $subscriptionService): Response
    {
        $event = $request->all();

        switch ($event['type']) {
            case 'invoice.paid':
                $subscriptionId = $event['data']['object']['subscription'];
                if ($subscriptionId) {
                    $subscriptionService->handleRenewal($subscriptionId);
                }
                break;

            case 'invoice.payment_failed':
                $subscriptionId = $event['data']['object']['subscription'];
                if ($subscriptionId) {
                    $subscriptionService->handlePaymentFailed($subscriptionId);
                }
                break;

            case 'customer.subscription.deleted':
                $subscriptionId = $event['data']['object']['id'];
                CustomerSubscription::where('stripe_subscription_id', $subscriptionId)
                    ->update(['status' => 'cancelled']);
                break;
        }

        return response('OK', 200);
    }
}
```

### 6. Routes

```php
// routes/api.php
Route::prefix('tenant-client/subscriptions')->middleware(['tenant'])->group(function () {
    Route::get('/plans', [SubscriptionController::class, 'plans']);
    Route::get('/plans/{plan}', [SubscriptionController::class, 'planDetails']);

    Route::middleware('auth:customer')->group(function () {
        Route::post('/plans/{plan}/purchase', [SubscriptionController::class, 'purchase']);
        Route::get('/my', [SubscriptionController::class, 'mySubscriptions']);
        Route::get('/{subscription}', [SubscriptionController::class, 'subscriptionDetails']);
        Route::post('/{subscription}/claim', [SubscriptionController::class, 'claimEvent']);
        Route::post('/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/{subscription}/pause', [SubscriptionController::class, 'pause']);
        Route::post('/{subscription}/resume', [SubscriptionController::class, 'resume']);
    });
});
```

### 7. Scheduled Commands

```php
// app/Console/Commands/ProcessSubscriptions.php
class ProcessSubscriptions extends Command
{
    protected $signature = 'subscriptions:process';

    public function handle(SubscriptionService $service): int
    {
        $expired = $service->expireSubscriptions();
        $this->info("Expired {$expired} subscriptions");

        $reminders = $service->sendRenewalReminders();
        $this->info("Sent {$reminders} renewal reminders");

        return Command::SUCCESS;
    }
}

// Schedule daily
$schedule->command('subscriptions:process')->daily();
```

---

## Testing Checklist

1. [ ] Subscription plans display correctly
2. [ ] Purchase creates Stripe subscription
3. [ ] One-time passes work without recurring billing
4. [ ] Season pass auto-claims events
5. [ ] Event claiming works and decrements counter
6. [ ] Subscription cancellation works
7. [ ] Pause/resume functionality works
8. [ ] Renewal webhooks update expiration
9. [ ] Failed payment marks as past_due
10. [ ] Priority booking access is granted
11. [ ] Subscriber discounts apply at checkout
12. [ ] Renewal reminders are sent
13. [ ] Expired subscriptions are marked correctly
