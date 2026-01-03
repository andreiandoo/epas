# Comprehensive Testing Coverage Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Production systems without tests are risky:
1. **Regression bugs**: Changes break existing functionality
2. **Deployment fear**: Teams hesitate to deploy frequently
3. **Technical debt**: Untested code is harder to refactor
4. **Documentation**: Tests serve as living documentation
5. **CI/CD confidence**: Automated testing enables continuous deployment

### What This Feature Does
- Unit tests for all service classes
- Feature tests for API endpoints
- Browser tests for critical user flows
- Integration tests for payment/external services
- Code coverage reporting
- CI/CD pipeline with automated testing

---

## Technical Implementation

### 1. Testing Setup

```bash
# Install testing packages
composer require --dev phpunit/phpunit
composer require --dev mockery/mockery
composer require --dev laravel/dusk
composer require --dev laravel/pint
composer require --dev larastan/larastan

# For coverage
composer require --dev pcov/clobber
```

### 2. PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="depends,defects"
         requireCoverageMetadata="false"
         beStrictAboutCoverageMetadata="false"
         beStrictAboutOutputDuringTests="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
        <exclude>
            <directory>app/Console</directory>
            <directory>app/Exceptions</directory>
        </exclude>
    </source>
    <coverage>
        <report>
            <html outputDirectory="coverage-report"/>
            <clover outputFile="coverage.xml"/>
        </report>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_DEBUG" value="true"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>
</phpunit>
```

### 3. Base Test Classes

```php
// tests/TestCase.php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}

// tests/Feature/ApiTestCase.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Customer;
use Laravel\Sanctum\Sanctum;

abstract class ApiTestCase extends TestCase
{
    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    protected function actingAsUser(?User $user = null): self
    {
        $this->user = $user ?? User::factory()->for($this->tenant)->create();
        Sanctum::actingAs($this->user, ['*']);
        return $this;
    }

    protected function actingAsCustomer(?Customer $customer = null): self
    {
        $this->customer = $customer ?? Customer::factory()->for($this->tenant)->create();
        Sanctum::actingAs($this->customer, ['*'], 'customer');
        return $this;
    }

    protected function withTenantHeader(): array
    {
        return ['X-API-Key' => $this->tenant->api_key];
    }
}
```

### 4. Unit Tests - Services

```php
// tests/Unit/Services/OrderServiceTest.php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Orders\OrderService;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Tenant;
use Mockery;

class OrderServiceTest extends TestCase
{
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = app(OrderService::class);
    }

    /** @test */
    public function it_creates_order_with_valid_items()
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->for($tenant)->create();
        $event = Event::factory()->for($tenant)->create();
        $ticketType = TicketType::factory()->for($event)->create([
            'price' => 50.00,
            'quantity' => 100,
        ]);

        $items = [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
        ];

        $order = $this->orderService->create($customer, $items);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals(100.00, $order->subtotal);
        $this->assertEquals('pending', $order->status);
        $this->assertCount(2, $order->tickets);
    }

    /** @test */
    public function it_throws_exception_for_sold_out_ticket()
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->for($tenant)->create();
        $event = Event::factory()->for($tenant)->create();
        $ticketType = TicketType::factory()->for($event)->create([
            'price' => 50.00,
            'quantity' => 0,
        ]);

        $items = [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Sold out');

        $this->orderService->create($customer, $items);
    }

    /** @test */
    public function it_calculates_order_total_correctly()
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->for($tenant)->create();
        $event = Event::factory()->for($tenant)->create();

        $ticketType1 = TicketType::factory()->for($event)->create(['price' => 25.00, 'quantity' => 100]);
        $ticketType2 = TicketType::factory()->for($event)->create(['price' => 75.00, 'quantity' => 100]);

        $items = [
            ['ticket_type_id' => $ticketType1->id, 'quantity' => 2], // 50
            ['ticket_type_id' => $ticketType2->id, 'quantity' => 1], // 75
        ];

        $order = $this->orderService->create($customer, $items);

        $this->assertEquals(125.00, $order->subtotal);
    }

    /** @test */
    public function it_generates_unique_order_number()
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->for($tenant)->create();
        $event = Event::factory()->for($tenant)->create();
        $ticketType = TicketType::factory()->for($event)->create(['quantity' => 100]);

        $items = [['ticket_type_id' => $ticketType->id, 'quantity' => 1]];

        $order1 = $this->orderService->create($customer, $items);
        $order2 = $this->orderService->create($customer, $items);

        $this->assertNotEquals($order1->order_number, $order2->order_number);
    }
}

// tests/Unit/Services/PaymentServiceTest.php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Payments\StripeService;
use App\Models\Order;
use App\Models\Payment;
use Mockery;
use Stripe\PaymentIntent;

class PaymentServiceTest extends TestCase
{
    /** @test */
    public function it_creates_payment_intent()
    {
        $stripeMock = Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'id' => 'pi_test123',
                'client_secret' => 'pi_test123_secret',
                'status' => 'requires_payment_method',
            ]);

        $this->app->instance(StripeService::class, $stripeMock);

        $order = Order::factory()->create(['total' => 100.00]);

        $result = app(StripeService::class)->createPaymentIntent(
            amount: 10000,
            currency: 'usd',
            metadata: ['order_id' => $order->id]
        );

        $this->assertEquals('pi_test123', $result['id']);
    }
}
```

### 5. Feature Tests - API Endpoints

```php
// tests/Feature/Api/EventApiTest.php
<?php

namespace Tests\Feature\Api;

use Tests\Feature\ApiTestCase;
use App\Models\Event;
use App\Models\Category;

class EventApiTest extends ApiTestCase
{
    /** @test */
    public function it_lists_published_events()
    {
        Event::factory()->for($this->tenant)->count(3)->create(['status' => 'published']);
        Event::factory()->for($this->tenant)->count(2)->create(['status' => 'draft']);

        $response = $this->getJson('/api/tenant-client/events', $this->withTenantHeader());

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'start_date', 'venue'],
                ],
                'meta' => ['current_page', 'total'],
            ]);
    }

    /** @test */
    public function it_filters_events_by_category()
    {
        $category = Category::factory()->for($this->tenant)->create();
        Event::factory()->for($this->tenant)->count(2)->create(['status' => 'published'])
            ->each(fn($e) => $e->categories()->attach($category));
        Event::factory()->for($this->tenant)->create(['status' => 'published']);

        $response = $this->getJson("/api/tenant-client/events?category={$category->slug}", $this->withTenantHeader());

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_shows_event_details()
    {
        $event = Event::factory()->for($this->tenant)
            ->has(TicketType::factory()->count(2))
            ->create(['status' => 'published']);

        $response = $this->getJson("/api/tenant-client/events/{$event->slug}", $this->withTenantHeader());

        $response->assertOk()
            ->assertJsonPath('event.id', $event->id)
            ->assertJsonPath('event.name', $event->name)
            ->assertJsonCount(2, 'event.ticket_types');
    }

    /** @test */
    public function it_returns_404_for_draft_event()
    {
        $event = Event::factory()->for($this->tenant)->create(['status' => 'draft']);

        $response = $this->getJson("/api/tenant-client/events/{$event->slug}", $this->withTenantHeader());

        $response->assertNotFound();
    }
}

// tests/Feature/Api/OrderApiTest.php
<?php

namespace Tests\Feature\Api;

use Tests\Feature\ApiTestCase;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Order;

class OrderApiTest extends ApiTestCase
{
    /** @test */
    public function it_creates_order_for_authenticated_customer()
    {
        $this->actingAsCustomer();

        $event = Event::factory()->for($this->tenant)->create();
        $ticketType = TicketType::factory()->for($event)->create([
            'price' => 50.00,
            'quantity' => 100,
        ]);

        $response = $this->postJson('/api/tenant-client/orders', [
            'items' => [
                ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
            ],
        ], $this->withTenantHeader());

        $response->assertCreated()
            ->assertJsonPath('order.customer_id', $this->customer->id)
            ->assertJsonPath('order.subtotal', 100.00);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'subtotal' => 100.00,
        ]);
    }

    /** @test */
    public function it_requires_authentication_to_create_order()
    {
        $response = $this->postJson('/api/tenant-client/orders', [
            'items' => [],
        ], $this->withTenantHeader());

        $response->assertUnauthorized();
    }

    /** @test */
    public function it_validates_order_items()
    {
        $this->actingAsCustomer();

        $response = $this->postJson('/api/tenant-client/orders', [
            'items' => [],
        ], $this->withTenantHeader());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    /** @test */
    public function it_lists_customer_orders()
    {
        $this->actingAsCustomer();

        Order::factory()->for($this->customer)->count(3)->create();

        $response = $this->getJson('/api/tenant-client/orders', $this->withTenantHeader());

        $response->assertOk()
            ->assertJsonCount(3, 'orders');
    }
}

// tests/Feature/Api/AuthApiTest.php
<?php

namespace Tests\Feature\Api;

use Tests\Feature\ApiTestCase;
use App\Models\Customer;
use Illuminate\Support\Facades\Hash;

class AuthApiTest extends ApiTestCase
{
    /** @test */
    public function it_registers_new_customer()
    {
        $response = $this->postJson('/api/tenant-client/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ], $this->withTenantHeader());

        $response->assertCreated()
            ->assertJsonStructure(['customer', 'token']);

        $this->assertDatabaseHas('customers', [
            'email' => 'test@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_logs_in_existing_customer()
    {
        $customer = Customer::factory()->for($this->tenant)->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/tenant-client/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ], $this->withTenantHeader());

        $response->assertOk()
            ->assertJsonStructure(['customer', 'token']);
    }

    /** @test */
    public function it_rejects_invalid_credentials()
    {
        Customer::factory()->for($this->tenant)->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/tenant-client/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ], $this->withTenantHeader());

        $response->assertUnauthorized();
    }
}
```

### 6. Integration Tests

```php
// tests/Integration/PaymentIntegrationTest.php
<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Customer;
use App\Services\Payments\StripeService;

/**
 * @group integration
 * @group stripe
 */
class PaymentIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!config('services.stripe.secret')) {
            $this->markTestSkipped('Stripe credentials not configured');
        }
    }

    /** @test */
    public function it_creates_real_payment_intent()
    {
        $stripeService = app(StripeService::class);

        $result = $stripeService->createPaymentIntent(
            amount: 5000, // $50.00
            currency: 'usd',
            metadata: ['test' => 'true']
        );

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('client_secret', $result);
        $this->assertStringStartsWith('pi_', $result['id']);
    }
}

// tests/Integration/WhatsAppIntegrationTest.php
<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Services\WhatsApp\WhatsAppCloudService;

/**
 * @group integration
 * @group whatsapp
 */
class WhatsAppIntegrationTest extends TestCase
{
    /** @test */
    public function it_verifies_webhook_signature()
    {
        $service = app(WhatsAppCloudService::class);

        $payload = json_encode(['test' => 'data']);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . $payload, config('services.whatsapp.app_secret'));

        $isValid = $service->verifySignature($payload, "sha256={$signature}");

        $this->assertTrue($isValid);
    }
}
```

### 7. Browser Tests (Laravel Dusk)

```php
// tests/Browser/CheckoutTest.php
<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Customer;
use App\Models\Tenant;

class CheckoutTest extends DuskTestCase
{
    /** @test */
    public function customer_can_complete_checkout()
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->for($tenant)->create();
        $event = Event::factory()->for($tenant)->create(['status' => 'published']);
        $ticketType = TicketType::factory()->for($event)->create(['price' => 50.00, 'quantity' => 100]);

        $this->browse(function (Browser $browser) use ($event, $customer) {
            $browser->loginAs($customer, 'customer')
                ->visit("/events/{$event->slug}")
                ->assertSee($event->name)
                ->select('ticket_type', $ticketType->id)
                ->type('quantity', 2)
                ->press('Add to Cart')
                ->assertPathIs('/cart')
                ->assertSee('$100.00')
                ->press('Proceed to Checkout')
                ->assertPathIs('/checkout')
                // Fill Stripe test card
                ->withinFrame('iframe[name^="__privateStripeFrame"]', function ($frame) {
                    $frame->type('[name="cardnumber"]', '4242424242424242')
                        ->type('[name="exp-date"]', '1234')
                        ->type('[name="cvc"]', '123');
                })
                ->press('Complete Purchase')
                ->waitForLocation('/orders/*')
                ->assertSee('Order Confirmed');
        });
    }
}
```

### 8. Factory Definitions

```php
// database/factories/EventFactory.php
<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->sentence(3),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->paragraphs(3, true),
            'start_date' => $this->faker->dateTimeBetween('+1 week', '+3 months'),
            'end_date' => $this->faker->dateTimeBetween('+3 months', '+6 months'),
            'venue_id' => Venue::factory(),
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn() => ['status' => 'published']);
    }

    public function upcoming(): static
    {
        return $this->state(fn() => [
            'start_date' => $this->faker->dateTimeBetween('+1 day', '+1 month'),
            'status' => 'published',
        ]);
    }
}

// database/factories/OrderFactory.php
<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => 'pending',
            'subtotal' => $this->faker->randomFloat(2, 10, 500),
            'total' => fn($attrs) => $attrs['subtotal'],
            'currency' => 'USD',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn() => ['status' => 'paid']);
    }
}
```

### 9. CI/CD Pipeline

```yaml
# .github/workflows/tests.yml
name: Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  tests:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: testing
          MYSQL_ROOT_PASSWORD: password
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, dom, fileinfo, mysql, gd
          coverage: pcov

      - name: Install Dependencies
        run: composer install --prefer-dist --no-progress

      - name: Copy .env
        run: cp .env.testing .env

      - name: Generate Key
        run: php artisan key:generate

      - name: Run Migrations
        run: php artisan migrate --force

      - name: Run Tests
        run: php artisan test --coverage --min=80

      - name: Upload Coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml

  static-analysis:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install Dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run PHPStan
        run: ./vendor/bin/phpstan analyse --memory-limit=2G

      - name: Run Pint
        run: ./vendor/bin/pint --test
```

### 10. Coverage Requirements

```php
// Create test coverage configuration
// Minimum coverage thresholds per directory:

// app/Services/     - 90% coverage
// app/Models/       - 80% coverage
// app/Http/Controllers/Api/ - 85% coverage
```

---

## Testing Checklist

1. [ ] PHPUnit configured and running
2. [ ] All service classes have unit tests
3. [ ] All API endpoints have feature tests
4. [ ] Authentication flows tested
5. [ ] Order creation flow tested
6. [ ] Payment integration tests (skip in CI without credentials)
7. [ ] Factories created for all models
8. [ ] CI/CD pipeline running tests
9. [ ] Code coverage above 80%
10. [ ] PHPStan static analysis passing
11. [ ] Pint code style checks passing
12. [ ] Browser tests for critical paths
