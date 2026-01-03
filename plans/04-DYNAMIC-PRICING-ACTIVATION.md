# Dynamic Pricing Activation Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
The dynamic pricing system exists in the codebase but is disabled (`SEATING_DP_ENABLED=false`). This means:
1. **Static pricing only**: Ticket prices don't adjust based on demand
2. **Revenue not optimized**: High-demand events don't capture premium pricing
3. **No demand-based pricing**: Early bird and last-minute pricing isn't automated
4. **Manual intervention required**: Organizers must manually adjust prices

### What This Feature Does
Activates and enhances the existing dynamic pricing system to:
- Enable demand-based pricing for seated events
- Provide multiple pricing strategies (demand, time, inventory)
- Allow real-time price adjustments with configurable limits
- Display price history and analytics
- Give organizers full control over pricing rules
- Implement safeguards (price floors, ceilings, cooldowns)

---

## Technical Implementation

### 1. Enable Dynamic Pricing

Update `.env`:

```
SEATING_DP_ENABLED=true
SEATING_DP_CACHE_TTL=60
SEATING_DP_PRICE_FLOOR_PERCENTAGE=50
SEATING_DP_PRICE_CEILING_PERCENTAGE=200
SEATING_DP_COOLDOWN_MINUTES=5
```

Update `config/seating.php`:

```php
<?php

return [
    // ... existing config

    'dynamic_pricing' => [
        'enabled' => env('SEATING_DP_ENABLED', true), // Changed to true
        'cache_ttl' => env('SEATING_DP_CACHE_TTL', 60),
        'price_floor_percentage' => env('SEATING_DP_PRICE_FLOOR_PERCENTAGE', 50),
        'price_ceiling_percentage' => env('SEATING_DP_PRICE_CEILING_PERCENTAGE', 200),
        'cooldown_minutes' => env('SEATING_DP_COOLDOWN_MINUTES', 5),
        'strategies' => [
            'demand' => true,
            'time' => true,
            'inventory' => true,
        ],
    ],
];
```

### 2. Database Migrations

Create `database/migrations/2026_01_03_000010_enhance_dynamic_pricing.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enhance dynamic pricing rules table
        Schema::table('dynamic_pricing_rules', function (Blueprint $table) {
            $table->string('strategy')->default('demand')->after('name');
            $table->boolean('is_active')->default(true)->after('strategy');
            $table->integer('priority')->default(0)->after('is_active');
            $table->json('conditions')->nullable()->after('priority');
            $table->timestamp('last_applied_at')->nullable();
        });

        // Create price change history table
        Schema::create('price_change_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_type_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('seating_section_id')->nullable()->constrained()->onDelete('cascade');
            $table->decimal('old_price', 10, 2);
            $table->decimal('new_price', 10, 2);
            $table->decimal('base_price', 10, 2);
            $table->string('strategy');
            $table->string('reason')->nullable();
            $table->json('factors')->nullable();
            $table->foreignId('dynamic_pricing_rule_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index(['event_id', 'created_at']);
        });

        // Create demand metrics table
        Schema::create('demand_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_type_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('views')->default(0);
            $table->integer('cart_adds')->default(0);
            $table->integer('purchases')->default(0);
            $table->decimal('conversion_rate', 5, 4)->default(0);
            $table->decimal('velocity', 8, 4)->default(0); // Sales per hour
            $table->date('date');
            $table->integer('hour')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'ticket_type_id', 'date', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_metrics');
        Schema::dropIfExists('price_change_history');

        Schema::table('dynamic_pricing_rules', function (Blueprint $table) {
            $table->dropColumn(['strategy', 'is_active', 'priority', 'conditions', 'last_applied_at']);
        });
    }
};
```

### 3. Models

Create `app/Models/PriceChangeHistory.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceChangeHistory extends Model
{
    protected $table = 'price_change_history';

    protected $fillable = [
        'event_id',
        'ticket_type_id',
        'seating_section_id',
        'old_price',
        'new_price',
        'base_price',
        'strategy',
        'reason',
        'factors',
        'dynamic_pricing_rule_id',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'base_price' => 'decimal:2',
        'factors' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function seatingSection(): BelongsTo
    {
        return $this->belongsTo(SeatingSection::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DynamicPricingRule::class, 'dynamic_pricing_rule_id');
    }

    public function getPriceChangePercentageAttribute(): float
    {
        if ($this->old_price == 0) return 0;
        return (($this->new_price - $this->old_price) / $this->old_price) * 100;
    }
}
```

Create `app/Models/DemandMetric.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandMetric extends Model
{
    protected $fillable = [
        'event_id',
        'ticket_type_id',
        'views',
        'cart_adds',
        'purchases',
        'conversion_rate',
        'velocity',
        'date',
        'hour',
    ];

    protected $casts = [
        'date' => 'date',
        'conversion_rate' => 'decimal:4',
        'velocity' => 'decimal:4',
    ];

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

### 4. Pricing Strategy Services

Create `app/Services/Seating/Strategies/PricingStrategyInterface.php`:

```php
<?php

namespace App\Services\Seating\Strategies;

use App\Models\Event;
use App\Models\TicketType;

interface PricingStrategyInterface
{
    /**
     * Calculate price adjustment multiplier
     * Returns a multiplier (e.g., 1.0 = no change, 1.1 = 10% increase)
     */
    public function calculateMultiplier(Event $event, ?TicketType $ticketType = null): float;

    /**
     * Get strategy name
     */
    public function getName(): string;

    /**
     * Get explanation for the adjustment
     */
    public function getExplanation(float $multiplier): string;
}
```

Create `app/Services/Seating/Strategies/DemandBasedStrategy.php`:

```php
<?php

namespace App\Services\Seating\Strategies;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\DemandMetric;
use Carbon\Carbon;

class DemandBasedStrategy implements PricingStrategyInterface
{
    public function calculateMultiplier(Event $event, ?TicketType $ticketType = null): float
    {
        // Get recent demand metrics
        $metrics = DemandMetric::where('event_id', $event->id)
            ->when($ticketType, fn($q) => $q->where('ticket_type_id', $ticketType->id))
            ->where('date', '>=', now()->subDays(7))
            ->get();

        if ($metrics->isEmpty()) {
            return 1.0; // No adjustment without data
        }

        // Calculate average velocity (sales per hour)
        $avgVelocity = $metrics->avg('velocity');

        // Calculate conversion rate trend
        $avgConversionRate = $metrics->avg('conversion_rate');

        // Determine multiplier based on demand signals
        $multiplier = 1.0;

        // High velocity = increase price
        if ($avgVelocity > 10) {
            $multiplier += 0.15; // +15%
        } elseif ($avgVelocity > 5) {
            $multiplier += 0.08; // +8%
        } elseif ($avgVelocity < 1) {
            $multiplier -= 0.05; // -5%
        }

        // High conversion rate = demand is strong
        if ($avgConversionRate > 0.1) { // 10% conversion
            $multiplier += 0.05;
        }

        return max(0.5, min(2.0, $multiplier)); // Clamp between 50% and 200%
    }

    public function getName(): string
    {
        return 'demand';
    }

    public function getExplanation(float $multiplier): string
    {
        if ($multiplier > 1.1) {
            return 'High demand detected - price increased';
        } elseif ($multiplier < 0.95) {
            return 'Low demand - price reduced to stimulate sales';
        }
        return 'Normal demand levels';
    }
}
```

Create `app/Services/Seating/Strategies/TimeBasedStrategy.php`:

```php
<?php

namespace App\Services\Seating\Strategies;

use App\Models\Event;
use App\Models\TicketType;
use Carbon\Carbon;

class TimeBasedStrategy implements PricingStrategyInterface
{
    public function calculateMultiplier(Event $event, ?TicketType $ticketType = null): float
    {
        $eventDate = $event->start_date;
        $now = Carbon::now();

        if (!$eventDate) {
            return 1.0;
        }

        $daysUntilEvent = $now->diffInDays($eventDate, false);

        // Event has passed
        if ($daysUntilEvent < 0) {
            return 1.0;
        }

        // Last 24 hours - premium pricing
        if ($daysUntilEvent < 1) {
            return 1.25; // +25%
        }

        // Last week - slight increase
        if ($daysUntilEvent <= 7) {
            return 1.0 + (0.15 * (1 - ($daysUntilEvent / 7))); // Up to +15%
        }

        // Early bird (more than 30 days out) - discount
        if ($daysUntilEvent > 30) {
            return 0.90; // -10%
        }

        // 2-4 weeks out
        if ($daysUntilEvent > 14) {
            return 0.95; // -5%
        }

        return 1.0;
    }

    public function getName(): string
    {
        return 'time';
    }

    public function getExplanation(float $multiplier): string
    {
        if ($multiplier > 1.15) {
            return 'Event is approaching - last chance pricing';
        } elseif ($multiplier > 1.0) {
            return 'Event is soon - standard pricing';
        } elseif ($multiplier < 0.95) {
            return 'Early bird discount applied';
        }
        return 'Standard pricing';
    }
}
```

Create `app/Services/Seating/Strategies/InventoryBasedStrategy.php`:

```php
<?php

namespace App\Services\Seating\Strategies;

use App\Models\Event;
use App\Models\TicketType;

class InventoryBasedStrategy implements PricingStrategyInterface
{
    public function calculateMultiplier(Event $event, ?TicketType $ticketType = null): float
    {
        // Calculate remaining inventory percentage
        $totalCapacity = $ticketType
            ? $ticketType->quantity
            : $event->ticketTypes->sum('quantity');

        $soldCount = $ticketType
            ? $ticketType->tickets()->whereNotNull('order_id')->count()
            : $event->tickets()->whereNotNull('order_id')->count();

        if ($totalCapacity == 0) {
            return 1.0;
        }

        $soldPercentage = ($soldCount / $totalCapacity) * 100;
        $remainingPercentage = 100 - $soldPercentage;

        // Almost sold out (< 10% remaining) - premium
        if ($remainingPercentage < 10) {
            return 1.30; // +30%
        }

        // Low inventory (< 25% remaining)
        if ($remainingPercentage < 25) {
            return 1.15; // +15%
        }

        // Half sold
        if ($remainingPercentage < 50) {
            return 1.05; // +5%
        }

        // Lots of inventory (> 75% remaining) - consider discount
        if ($remainingPercentage > 75) {
            return 0.95; // -5%
        }

        return 1.0;
    }

    public function getName(): string
    {
        return 'inventory';
    }

    public function getExplanation(float $multiplier): string
    {
        if ($multiplier > 1.2) {
            return 'Limited availability - high demand pricing';
        } elseif ($multiplier > 1.0) {
            return 'Selling fast - prices adjusted';
        } elseif ($multiplier < 1.0) {
            return 'Good availability - competitive pricing';
        }
        return 'Standard pricing';
    }
}
```

### 5. Enhanced Dynamic Pricing Service

Update/Create `app/Services/Seating/DynamicPricingService.php`:

```php
<?php

namespace App\Services\Seating;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\DynamicPricingRule;
use App\Models\PriceChangeHistory;
use App\Services\Seating\Strategies\DemandBasedStrategy;
use App\Services\Seating\Strategies\TimeBasedStrategy;
use App\Services\Seating\Strategies\InventoryBasedStrategy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DynamicPricingService
{
    protected array $strategies = [];

    public function __construct()
    {
        $this->strategies = [
            'demand' => new DemandBasedStrategy(),
            'time' => new TimeBasedStrategy(),
            'inventory' => new InventoryBasedStrategy(),
        ];
    }

    /**
     * Check if dynamic pricing is enabled
     */
    public function isEnabled(): bool
    {
        return config('seating.dynamic_pricing.enabled', false);
    }

    /**
     * Calculate current price for a ticket type
     */
    public function calculatePrice(Event $event, TicketType $ticketType): array
    {
        if (!$this->isEnabled()) {
            return [
                'price' => $ticketType->price,
                'base_price' => $ticketType->price,
                'is_dynamic' => false,
                'adjustments' => [],
            ];
        }

        $cacheKey = "dynamic_price:{$event->id}:{$ticketType->id}";
        $cacheTtl = config('seating.dynamic_pricing.cache_ttl', 60);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($event, $ticketType) {
            return $this->computePrice($event, $ticketType);
        });
    }

    /**
     * Compute the dynamic price
     */
    protected function computePrice(Event $event, TicketType $ticketType): array
    {
        $basePrice = $ticketType->base_price ?? $ticketType->price;
        $adjustments = [];
        $totalMultiplier = 1.0;

        // Get applicable rules
        $rules = DynamicPricingRule::where('event_id', $event->id)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        // Apply each enabled strategy
        $enabledStrategies = config('seating.dynamic_pricing.strategies', []);

        foreach ($this->strategies as $name => $strategy) {
            if (!($enabledStrategies[$name] ?? false)) {
                continue;
            }

            $multiplier = $strategy->calculateMultiplier($event, $ticketType);

            if ($multiplier != 1.0) {
                $adjustments[] = [
                    'strategy' => $name,
                    'multiplier' => $multiplier,
                    'explanation' => $strategy->getExplanation($multiplier),
                ];
                $totalMultiplier *= $multiplier;
            }
        }

        // Apply custom rules
        foreach ($rules as $rule) {
            $ruleMultiplier = $this->evaluateRule($rule, $event, $ticketType);
            if ($ruleMultiplier != 1.0) {
                $adjustments[] = [
                    'strategy' => 'custom_rule',
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'multiplier' => $ruleMultiplier,
                ];
                $totalMultiplier *= $ruleMultiplier;
            }
        }

        // Apply price floor and ceiling
        $floorPercentage = config('seating.dynamic_pricing.price_floor_percentage', 50) / 100;
        $ceilingPercentage = config('seating.dynamic_pricing.price_ceiling_percentage', 200) / 100;

        $totalMultiplier = max($floorPercentage, min($ceilingPercentage, $totalMultiplier));

        $finalPrice = round($basePrice * $totalMultiplier, 2);

        return [
            'price' => $finalPrice,
            'base_price' => $basePrice,
            'multiplier' => $totalMultiplier,
            'is_dynamic' => $totalMultiplier != 1.0,
            'adjustments' => $adjustments,
        ];
    }

    /**
     * Evaluate a custom pricing rule
     */
    protected function evaluateRule(DynamicPricingRule $rule, Event $event, TicketType $ticketType): float
    {
        $conditions = $rule->conditions ?? [];

        // Evaluate conditions
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $event, $ticketType)) {
                return 1.0; // Condition not met, no adjustment
            }
        }

        // Return the rule's multiplier or percentage adjustment
        if ($rule->percentage_adjustment) {
            return 1 + ($rule->percentage_adjustment / 100);
        }

        return $rule->multiplier ?? 1.0;
    }

    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition(array $condition, Event $event, TicketType $ticketType): bool
    {
        $type = $condition['type'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        switch ($type) {
            case 'days_until_event':
                $daysUntil = now()->diffInDays($event->start_date, false);
                return $this->compare($daysUntil, $operator, $value);

            case 'sold_percentage':
                $soldPercentage = $this->getSoldPercentage($ticketType);
                return $this->compare($soldPercentage, $operator, $value);

            case 'time_of_day':
                $currentHour = now()->hour;
                return $this->compare($currentHour, $operator, $value);

            case 'day_of_week':
                $currentDay = now()->dayOfWeek;
                return $this->compare($currentDay, $operator, $value);

            default:
                return true;
        }
    }

    /**
     * Compare values using operator
     */
    protected function compare($actual, string $operator, $expected): bool
    {
        return match ($operator) {
            '=' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            'in' => in_array($actual, (array) $expected),
            default => true,
        };
    }

    /**
     * Get sold percentage for ticket type
     */
    protected function getSoldPercentage(TicketType $ticketType): float
    {
        if ($ticketType->quantity == 0) return 0;

        $sold = $ticketType->tickets()->whereNotNull('order_id')->count();
        return ($sold / $ticketType->quantity) * 100;
    }

    /**
     * Record a price change
     */
    public function recordPriceChange(
        Event $event,
        ?TicketType $ticketType,
        float $oldPrice,
        float $newPrice,
        float $basePrice,
        string $strategy,
        ?string $reason = null,
        ?array $factors = null,
        ?int $ruleId = null
    ): PriceChangeHistory {
        return PriceChangeHistory::create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType?->id,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'base_price' => $basePrice,
            'strategy' => $strategy,
            'reason' => $reason,
            'factors' => $factors,
            'dynamic_pricing_rule_id' => $ruleId,
        ]);
    }

    /**
     * Get price history for an event
     */
    public function getPriceHistory(Event $event, ?TicketType $ticketType = null, int $days = 30): array
    {
        $query = PriceChangeHistory::where('event_id', $event->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc');

        if ($ticketType) {
            $query->where('ticket_type_id', $ticketType->id);
        }

        return $query->get()->toArray();
    }

    /**
     * Clear price cache for an event
     */
    public function clearCache(Event $event): void
    {
        $ticketTypes = $event->ticketTypes;

        foreach ($ticketTypes as $ticketType) {
            Cache::forget("dynamic_price:{$event->id}:{$ticketType->id}");
        }
    }

    /**
     * Simulate price for given conditions
     */
    public function simulatePrice(Event $event, TicketType $ticketType, array $overrides = []): array
    {
        // Temporarily override conditions for simulation
        // This is useful for testing pricing scenarios

        return $this->computePrice($event, $ticketType);
    }
}
```

### 6. API Controller

Create `app/Http/Controllers/Api/DynamicPricingController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketType;
use App\Services\Seating\DynamicPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicPricingController extends Controller
{
    public function __construct(
        protected DynamicPricingService $pricingService
    ) {}

    /**
     * Get current price for a ticket type
     */
    public function getCurrentPrice(Event $event, TicketType $ticketType): JsonResponse
    {
        $pricing = $this->pricingService->calculatePrice($event, $ticketType);

        return response()->json([
            'ticket_type_id' => $ticketType->id,
            'current_price' => $pricing['price'],
            'base_price' => $pricing['base_price'],
            'is_dynamic' => $pricing['is_dynamic'],
            'savings' => $pricing['base_price'] - $pricing['price'],
            'adjustments' => $pricing['adjustments'],
        ]);
    }

    /**
     * Get price history
     */
    public function getPriceHistory(Request $request, Event $event): JsonResponse
    {
        $ticketTypeId = $request->query('ticket_type_id');
        $days = $request->query('days', 30);

        $ticketType = $ticketTypeId
            ? TicketType::find($ticketTypeId)
            : null;

        $history = $this->pricingService->getPriceHistory($event, $ticketType, $days);

        return response()->json([
            'event_id' => $event->id,
            'history' => $history,
        ]);
    }

    /**
     * Simulate pricing (admin only)
     */
    public function simulate(Request $request, Event $event, TicketType $ticketType): JsonResponse
    {
        $this->authorize('manage', $event);

        $overrides = $request->input('overrides', []);
        $simulation = $this->pricingService->simulatePrice($event, $ticketType, $overrides);

        return response()->json([
            'simulation' => $simulation,
        ]);
    }

    /**
     * Get pricing analytics
     */
    public function getAnalytics(Event $event): JsonResponse
    {
        $this->authorize('manage', $event);

        $history = $this->pricingService->getPriceHistory($event, null, 90);

        $analytics = [
            'total_price_changes' => count($history),
            'avg_price_increase' => collect($history)
                ->where('new_price', '>', 'old_price')
                ->avg(fn($h) => (($h['new_price'] - $h['old_price']) / $h['old_price']) * 100),
            'avg_price_decrease' => collect($history)
                ->where('new_price', '<', 'old_price')
                ->avg(fn($h) => (($h['old_price'] - $h['new_price']) / $h['old_price']) * 100),
            'strategy_breakdown' => collect($history)
                ->groupBy('strategy')
                ->map(fn($group) => $group->count()),
        ];

        return response()->json($analytics);
    }

    /**
     * Clear price cache (admin only)
     */
    public function clearCache(Event $event): JsonResponse
    {
        $this->authorize('manage', $event);

        $this->pricingService->clearCache($event);

        return response()->json(['message' => 'Price cache cleared']);
    }
}
```

### 7. Routes

Add to `routes/api.php`:

```php
Route::prefix('events/{event}')->group(function () {
    Route::get('/pricing/{ticketType}', [DynamicPricingController::class, 'getCurrentPrice']);
    Route::get('/pricing/history', [DynamicPricingController::class, 'getPriceHistory']);

    // Admin only
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/pricing/{ticketType}/simulate', [DynamicPricingController::class, 'simulate']);
        Route::get('/pricing/analytics', [DynamicPricingController::class, 'getAnalytics']);
        Route::post('/pricing/clear-cache', [DynamicPricingController::class, 'clearCache']);
    });
});
```

### 8. Filament Admin Enhancement

Update `app/Filament/Resources/DynamicPricingRuleResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DynamicPricingRuleResource\Pages;
use App\Models\DynamicPricingRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DynamicPricingRuleResource extends Resource
{
    protected static ?string $model = DynamicPricingRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Pricing';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('event_id')
                    ->relationship('event', 'name')
                    ->required()
                    ->searchable(),

                Forms\Components\Select::make('strategy')
                    ->options([
                        'demand' => 'Demand Based',
                        'time' => 'Time Based',
                        'inventory' => 'Inventory Based',
                        'custom' => 'Custom Rule',
                    ])
                    ->required()
                    ->default('custom'),

                Forms\Components\TextInput::make('percentage_adjustment')
                    ->numeric()
                    ->suffix('%')
                    ->helperText('Positive for increase, negative for decrease'),

                Forms\Components\TextInput::make('priority')
                    ->numeric()
                    ->default(0)
                    ->helperText('Higher priority rules are applied first'),

                Forms\Components\Toggle::make('is_active')
                    ->default(true),

                Forms\Components\Repeater::make('conditions')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->options([
                                'days_until_event' => 'Days Until Event',
                                'sold_percentage' => 'Sold Percentage',
                                'time_of_day' => 'Time of Day (Hour)',
                                'day_of_week' => 'Day of Week',
                            ])
                            ->required(),

                        Forms\Components\Select::make('operator')
                            ->options([
                                '=' => 'Equals',
                                '!=' => 'Not Equals',
                                '>' => 'Greater Than',
                                '>=' => 'Greater Than or Equal',
                                '<' => 'Less Than',
                                '<=' => 'Less Than or Equal',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('value')
                            ->required(),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('event.name')
                    ->sortable(),

                Tables\Columns\TextColumn::make('strategy')
                    ->badge(),

                Tables\Columns\TextColumn::make('percentage_adjustment')
                    ->suffix('%')
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('priority')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->relationship('event', 'name'),

                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

### 9. Demand Tracking Job

Create `app/Jobs/TrackDemandMetrics.php`:

```php
<?php

namespace App\Jobs;

use App\Models\DemandMetric;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TrackDemandMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Get active events
        $events = Event::where('start_date', '>', now())
            ->where('status', 'published')
            ->get();

        foreach ($events as $event) {
            $this->trackEventDemand($event);
        }
    }

    protected function trackEventDemand(Event $event): void
    {
        foreach ($event->ticketTypes as $ticketType) {
            // Calculate metrics for the current hour
            $hourStart = now()->startOfHour();
            $hourEnd = now()->endOfHour();

            $views = $this->getViews($event, $ticketType, $hourStart, $hourEnd);
            $cartAdds = $this->getCartAdds($event, $ticketType, $hourStart, $hourEnd);
            $purchases = $this->getPurchases($event, $ticketType, $hourStart, $hourEnd);

            $conversionRate = $views > 0 ? $purchases / $views : 0;
            $velocity = $purchases; // Sales per hour

            DemandMetric::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'ticket_type_id' => $ticketType->id,
                    'date' => now()->toDateString(),
                    'hour' => now()->hour,
                ],
                [
                    'views' => $views,
                    'cart_adds' => $cartAdds,
                    'purchases' => $purchases,
                    'conversion_rate' => $conversionRate,
                    'velocity' => $velocity,
                ]
            );
        }
    }

    protected function getViews(Event $event, $ticketType, $start, $end): int
    {
        // Count from analytics events
        return \DB::table('analytics_events')
            ->where('event_id', $event->id)
            ->where('event_type', 'page_view')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    protected function getCartAdds(Event $event, $ticketType, $start, $end): int
    {
        return \DB::table('analytics_events')
            ->where('event_id', $event->id)
            ->where('event_type', 'add_to_cart')
            ->where('properties->ticket_type_id', $ticketType->id)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    protected function getPurchases(Event $event, $ticketType, $start, $end): int
    {
        return $ticketType->tickets()
            ->whereHas('order', fn($q) => $q->where('status', 'paid'))
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }
}
```

Add to scheduler:

```php
Schedule::job(new TrackDemandMetrics())->hourly();
```

---

## Testing Checklist

1. [ ] Dynamic pricing is enabled via config
2. [ ] Demand-based strategy calculates correctly
3. [ ] Time-based strategy applies early bird/last minute pricing
4. [ ] Inventory-based strategy reacts to sold percentage
5. [ ] Price floor prevents prices from going too low
6. [ ] Price ceiling prevents excessive increases
7. [ ] Price cache works correctly
8. [ ] Custom rules can be created and applied
9. [ ] Price history is recorded
10. [ ] Analytics dashboard shows pricing data
11. [ ] API returns current dynamic prices
12. [ ] Filament admin can manage rules
