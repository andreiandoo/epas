<?php

namespace Tests\Feature\Sanius;

use App\Models\Event;
use App\Models\Sanius\SaniusContract;
use App\Models\Sanius\SaniusRental;
use App\Models\Sanius\SaniusSetting;
use App\Models\Sanius\SaniusSled;
use App\Models\Sanius\SaniusTab;
use App\Models\Sanius\SaniusTow;
use App\Models\Sanius\SaniusTowShift;
use App\Models\Ticket;
use App\Services\Sanius\ParkingTicketCode;
use App\Services\Sanius\SaniusCircuit;
use App\Services\Sanius\SaniusException;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fluxul Sanius v2 pe o baza SQLite in memorie, doar cu tabelele circuitului:
 * plata la intrare, cicluri, tractari incluse pe ciclu, credit comun, weekend,
 * inchidere cu 0 lei, contract obligatoriu.
 */
class SaniusCyclesFlowTest extends TestCase
{
    private const EVENT_ID = 4234;

    private array $tt = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sanius_test',
            'database.connections.sanius_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('sanius_test');

        Schema::create('ticket_types', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('event_id');
            $t->text('name')->nullable();
            $t->string('sku')->nullable();
            $t->string('service_category')->nullable();
            $t->integer('price_cents')->nullable();
            $t->integer('sale_price_cents')->nullable();
            $t->text('meta')->nullable();
            $t->boolean('is_active')->default(true);
            $t->date('valid_date')->nullable();
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('status')->default('paid');
            $t->text('meta')->nullable();
            $t->timestamps();
        });
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('ticket_type_id')->nullable();
            $t->unsignedBigInteger('event_id')->nullable();
            $t->string('code')->nullable();
            $t->string('barcode')->nullable();
            $t->string('status')->default('valid');
            $t->text('meta')->nullable();
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_18_100000_create_sanius_circuit_tables.php'))->up();
        (require base_path('database/migrations/2026_11_19_100000_sanius_cycles_prepay_contracts.php'))->up();

        $prices = [
            'cycle_weekday' => 30, 'cycle_weekend' => 40, 'tow' => 5,
            'damage_small' => 20, 'damage_medium' => 50, 'damage_large' => 150,
            'lost' => 400, 'parking_car' => 10, 'parking_bus' => 30,
        ];
        foreach ($prices as $key => $price) {
            $this->tt[$key] = DB::table('ticket_types')->insertGetId([
                'event_id' => self::EVENT_ID, 'name' => $key, 'service_category' => 'rental',
                'price_cents' => $price * 100, 'meta' => json_encode(['sanius_circuit' => $key]), 'is_active' => false,
            ]);
        }
        $this->tt['access'] = DB::table('ticket_types')->insertGetId([
            'event_id' => self::EVENT_ID, 'name' => 'Acces adult', 'service_category' => 'access', 'price_cents' => 2500, 'meta' => '{}',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function circuit(array $contract = []): SaniusCircuit
    {
        $s = SaniusSetting::create([
            'event_id' => self::EVENT_ID,
            'mode' => 'live',
            'products' => collect($this->tt)->except('access')->all(),
            'rental' => ['cycle_minutes' => 60, 'tows_per_cycle' => 2, 'grace_minutes' => 10, 'weekend_extra_dates' => ['2026-12-01']],
            'contract' => $contract,
        ]);
        $event = new Event;
        $event->forceFill(['id' => self::EVENT_ID]);

        return new SaniusCircuit($s, $event);
    }

    private function at(string $local): void
    {
        Carbon::setTestNow(Carbon::parse($local, 'Europe/Bucharest')->utc());
    }

    /** Cont pe un tichet de parcare de azi, cu un bilet de acces legat. */
    private function tab(SaniusCircuit $c, string $parkingCode): SaniusTab
    {
        $tab = $c->openTab(ParkingTicketCode::parse($parkingCode), null);
        $orderId = DB::table('orders')->insertGetId(['status' => 'paid']);
        $ticketId = DB::table('tickets')->insertGetId([
            'order_id' => $orderId, 'ticket_type_id' => $this->tt['access'], 'event_id' => self::EVENT_ID,
            'code' => 'ACC'.$orderId, 'barcode' => 'ACC'.$orderId, 'status' => 'valid', 'meta' => '{}',
        ]);
        $c->linkTicketWithOrder($tab, Ticket::with(['ticketType', 'order'])->find($ticketId), null, 'cashier');

        return $tab->fresh();
    }

    private function sled(int $n): SaniusSled
    {
        return SaniusSled::create(['event_id' => self::EVENT_ID, 'number' => $n, 'code' => 'SLED'.$n, 'status' => 'available', 'is_test' => false]);
    }

    private function tows(SaniusRental $r, int $n): void
    {
        $shift = SaniusTowShift::create([
            'event_id' => self::EVENT_ID, 'device_id' => 'dev', 'started_at' => Carbon::now(), 'ended_at' => Carbon::now(), 'is_test' => false,
        ]);
        for ($i = 0; $i < $n; $i++) {
            SaniusTow::create([
                'event_id' => self::EVENT_ID, 'shift_id' => $shift->id, 'sled_id' => $r->sled_id, 'sled_code' => 'x',
                'rental_id' => $r->id, 'client_uuid' => uniqid('t', true), 'scanned_at_device' => Carbon::now(),
                'scanned_at' => Carbon::now(), 'received_at' => Carbon::now(), 'status' => 'counted', 'is_test' => false,
            ]);
        }
    }

    private function qty(array $charge, string $key): int
    {
        return (int) collect($charge['lines'])->where('key', $key)->sum('qty');
    }

    public function test_prepaid_credit_cycles_tows_and_damage_on_a_weekday(): void
    {
        $this->at('2026-11-18 10:00'); // miercuri
        $c = $this->circuit();
        $tab = $this->tab($c, '261118093012341');

        // La intrare: 2 sanii x 1 ciclu
        $quote = $c->prepayQuote(2, 1);
        $this->assertSame('weekday', $quote['day_type']);
        $this->assertSame(30.0, $quote['unit_price']);
        $this->assertSame(60.0, $quote['subtotal']);
        $c->recordPrepaid($tab, $quote, null, 'cash', false, null);

        $a = $c->startRental($tab, $this->sled(1), null);
        $b = $c->startRental($tab, $this->sled(2), null);

        // Sania A: 1 h 5 min (toleranta 10) = 1 ciclu, 3 tractari, 2 incluse
        $this->at('2026-11-18 11:05');
        $this->tows($a, 3);
        $c->requestReturn($a, 'good', null, null, null);
        $a->refresh();
        $this->assertSame('closed', $a->status);
        $this->assertSame(1, $a->cycles_used);
        $this->assertSame(1, $a->tows_paid);
        $this->assertSame('weekday', $a->day_type);

        // Sania B: 2 h 15 min = 3 cicluri, avarie medie
        $this->at('2026-11-18 12:15');
        $c->requestReturn($b, 'damaged', 'medium', null, null);
        $b->refresh();
        $this->assertSame(3, $b->cycles_used);
        $this->assertSame(0, $b->tows_count);
        $this->assertSame(0, $b->tows_paid);

        // Folosite 4, platite 2 -> 2 cicluri de plata + 1 tractare + avaria
        $sum = $c->tabSummary($tab->fresh());
        $charge = $sum['charge'];
        $this->assertSame(2, $this->qty($charge, 'cycle_weekday'));
        $this->assertSame(0, $this->qty($charge, 'cycle_weekend'));
        $this->assertSame(1, $this->qty($charge, 'tow'));
        $this->assertSame(1, $this->qty($charge, 'damage_medium'));
        $this->assertSame(0, $this->qty($charge, 'parking_car')); // 2 h 45 min, sub 4 h cu bilet
        $this->assertSame(115.0, $sum['total']);
        $this->assertSame(['sled_cycles' => 2, 'used' => 2, 'left' => 0], $charge['credit']);
        $this->assertTrue($sum['can_settle']);
    }

    public function test_weekend_and_extra_dates_use_the_weekend_cycle(): void
    {
        $this->at('2026-11-21 10:00'); // sambata
        $c = $this->circuit();
        $tab = $this->tab($c, '261121095512341');
        $r = $c->startRental($tab, $this->sled(1), null);
        $this->at('2026-11-21 10:40');
        $c->requestReturn($r, 'good', null, null, null);

        $charge = $c->tabSummary($tab->fresh())['charge'];
        $this->assertSame(1, $this->qty($charge, 'cycle_weekend'));
        $this->assertSame(0, $this->qty($charge, 'cycle_weekday'));
        $this->assertSame(40.0, $charge['total']);

        // 1 Decembrie (marti) e pe lista de zile cu pret de weekend
        $this->at('2026-12-01 09:00');
        $this->assertSame('weekend', $c->prepayQuote(1, 1)['day_type']);
    }

    public function test_everything_prepaid_closes_with_nothing_to_charge(): void
    {
        $this->at('2026-11-18 10:00');
        $c = $this->circuit();
        $tab = $this->tab($c, '261118095012341');
        $c->recordPrepaid($tab, $c->prepayQuote(1, 2), null, 'card', false, null);

        // o singura sanie 1 h 50 min = 2 cicluri, 4 tractari incluse, 4 facute
        $r = $c->startRental($tab, $this->sled(1), null);
        $this->at('2026-11-18 11:50');
        $this->tows($r, 4);
        $c->requestReturn($r, 'good', null, null, null);

        $sum = $c->tabSummary($tab->fresh());
        $this->assertSame([], $sum['charge']['lines']);
        $this->assertSame(0.0, $sum['total']);
        $this->assertTrue($sum['can_settle']);
    }

    public function test_unused_credit_is_not_refunded_and_extra_sleds_pay_at_exit(): void
    {
        $this->at('2026-11-18 10:00');
        $c = $this->circuit();
        $tab = $this->tab($c, '261118095012341');
        $c->recordPrepaid($tab, $c->prepayQuote(3, 1), null, 'cash', false, null);

        $r = $c->startRental($tab, $this->sled(1), null);
        $this->at('2026-11-18 10:30');
        $c->requestReturn($r, 'good', null, null, null);

        $sum = $c->tabSummary($tab->fresh());
        $this->assertSame(0.0, $sum['total']);
        $this->assertSame(2, $sum['prepaid']['left']);

        // fara plata la intrare: totul la iesire
        $tab2 = $this->tab($c, '261118095112351');
        $r2 = $c->startRental($tab2, $this->sled(2), null);
        $this->at('2026-11-18 11:45');
        $c->requestReturn($r2, 'good', null, null, null);
        $this->assertSame(2, $this->qty($c->tabSummary($tab2->fresh())['charge'], 'cycle_weekday'));
    }

    public function test_refunded_entry_order_gives_no_credit(): void
    {
        $this->at('2026-11-18 10:00');
        $c = $this->circuit();
        $tab = $this->tab($c, '261118095012341');
        $paid = DB::table('orders')->insertGetId(['status' => 'paid']);
        $refunded = DB::table('orders')->insertGetId(['status' => 'refunded']);
        $c->recordPrepaid($tab, $c->prepayQuote(1, 1), $paid, 'cash', false, null);
        $c->recordPrepaid($tab, $c->prepayQuote(2, 1), $refunded, 'cash', false, null);

        $this->assertSame(1, $c->prepaidCycles($tab));
        $this->assertSame(1, $c->tabSummary($tab)['prepaid']['sled_cycles']);
    }

    public function test_required_contract_blocks_the_desk_until_signed(): void
    {
        $this->at('2026-11-18 10:00');
        $c = $this->circuit(['required' => true]);
        $tab = $this->tab($c, '261118095012341');

        try {
            $c->startRental($tab, $this->sled(1), null);
            $this->fail('Sania nu trebuia sa plece fara contract.');
        } catch (SaniusException $e) {
            $this->assertStringContainsString('Contractul', $e->getMessage());
        }

        $body = $c->renderContractBody($tab, ['signer_name' => 'Ion Pop', 'doc_type' => 'ci', 'doc_series' => 'HR', 'doc_number' => '123456']);
        $this->assertStringContainsString('Ion Pop', $body);
        $this->assertStringContainsString('HR', $body);
        $this->assertStringNotContainsString('{nume}', $body);

        $contract = SaniusContract::create([
            'event_id' => self::EVENT_ID, 'tab_id' => $tab->id, 'status' => 'signed', 'signer_name' => 'Ion Pop',
            'doc_type' => 'ci', 'doc_number' => '123456', 'title' => 't', 'body' => $body, 'body_hash' => hash('sha256', $body),
            'signed_at' => Carbon::now(),
        ]);
        $pdf = Pdf::loadView('pdf.sanius-contract', [
            'contract' => $contract->load('tab'), 'signature' => null,
            'signedAt' => $contract->signed_at, 'createdAt' => $contract->created_at,
        ])->output();
        $this->assertStringStartsWith('%PDF', $pdf);

        $rental = $c->startRental($tab->fresh(), $this->sled(2), null);
        $this->assertSame('active', $rental->status);
        $this->assertTrue($c->tabSummary($tab->fresh())['contract']['signed']);
    }
}
