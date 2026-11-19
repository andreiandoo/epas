<?php

namespace Tests\Unit\Sanius;

use App\Models\Sanius\SaniusSetting;
use App\Models\Sanius\SaniusSled;
use App\Models\Sanius\SaniusTowShift;
use App\Services\Sanius\ParkingTicketCode;
use App\Services\Sanius\SaniusPricing;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regulile circuitului Sanius care nu au nevoie de baza de date: codul de pe
 * tichetul de parcare, calculele de bani si cand un sofer blocheaza returnarile.
 */
class SaniusCircuitRulesTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Toate cele 9 tichete reale primite de la Sf. Ana. */
    public static function realTickets(): array
    {
        return [
            ['*261028110496291*', '2026-10-28 11:04', '9629'],
            ['260731180579311', '2026-07-31 18:05', '7931'],
            ['260802153784471', '2026-08-02 15:37', '8447'],
            ['260823152032571', '2026-08-23 15:20', '3257'],
            ['260823152532601', '2026-08-23 15:25', '3260'],
            ['260822105826941', '2026-08-22 10:58', '2694'],
            ['261010141181201', '2026-10-10 14:11', '8120'],
            ['261110120103771', '2026-11-10 12:01', '0377'],
            ['2 6 0 8 2 3 1 5 1 8 3 2 5 6 1', '2026-08-23 15:18', '3256'],
        ];
    }

    #[DataProvider('realTickets')]
    public function test_parses_real_parking_tickets(string $raw, string $local, string $suffix): void
    {
        $p = ParkingTicketCode::parse($raw);

        $this->assertNotNull($p);
        $this->assertSame(15, strlen($p['code']));
        $this->assertSame($local, $p['entry_at']->copy()->setTimezone('Europe/Bucharest')->format('Y-m-d H:i'));
        $this->assertSame($suffix, $p['number_suffix']);
        $this->assertSame('1', $p['terminal']);
    }

    public function test_rejects_codes_that_are_not_parking_tickets(): void
    {
        $this->assertNull(ParkingTicketCode::parse(''));
        $this->assertNull(ParkingTicketCode::parse('ABCDEF1234'));
        $this->assertNull(ParkingTicketCode::parse('26102811049629'));    // 14 cifre
        $this->assertNull(ParkingTicketCode::parse('261328110496291'));   // luna 13
        $this->assertNull(ParkingTicketCode::parse('260230110496291'));   // 30 februarie
        $this->assertNull(ParkingTicketCode::parse('261028250096291'));   // ora 25
        $this->assertNull(ParkingTicketCode::parse('SANIE:ABCDEFGHIJ'));
    }

    public function test_ticket_must_be_from_today_in_romania(): void
    {
        $entry = ParkingTicketCode::parse('261028110496291')['entry_at'];

        $this->assertTrue(ParkingTicketCode::isFromToday($entry, Carbon::parse('2026-10-28 17:30', 'Europe/Bucharest')));
        $this->assertFalse(ParkingTicketCode::isFromToday($entry, Carbon::parse('2026-10-29 09:00', 'Europe/Bucharest')));
        // Din viitor (ceasul barierei inaintea serverului cu mai mult de 15 minute)
        $this->assertFalse(ParkingTicketCode::isFromToday($entry, Carbon::parse('2026-10-28 10:30', 'Europe/Bucharest')));
        $this->assertTrue(ParkingTicketCode::isFromToday($entry, Carbon::parse('2026-10-28 10:55', 'Europe/Bucharest')));
    }

    public function test_parking_with_access_ticket_charges_only_what_exceeds_four_hours(): void
    {
        $rules = SaniusSetting::PARKING_DEFAULTS;

        $this->assertSame(0.0, SaniusPricing::parking(240, true, $rules, 3)['amount']);
        $over = SaniusPricing::parking(241, true, $rules, 3);
        $this->assertSame(1, $over['units']);
        $this->assertSame(3.0, $over['amount']);
        $this->assertSame(2, SaniusPricing::parking(300, true, $rules, 3)['units']);   // 60 min peste
        $this->assertSame(3, SaniusPricing::parking(301, true, $rules, 3)['units']);   // 61 min peste
    }

    public function test_parking_without_access_ticket(): void
    {
        $rules = SaniusSetting::PARKING_DEFAULTS;

        $this->assertSame(0.0, SaniusPricing::parking(10, false, $rules, 3)['amount']);
        $this->assertSame(1, SaniusPricing::parking(11, false, $rules, 3)['units']);
        $this->assertSame(4, SaniusPricing::parking(95, false, $rules, 3)['units']);
        $this->assertSame(60.0, SaniusPricing::parking(95, false, $rules, 15)['amount']); // autobuz
    }

    public function test_cycles_are_started_cycles_after_grace(): void
    {
        $this->assertSame(1, SaniusPricing::cycles(0, 60, 10));
        $this->assertSame(1, SaniusPricing::cycles(55, 60, 10));
        $this->assertSame(1, SaniusPricing::cycles(70, 60, 10));   // 10 min toleranta
        $this->assertSame(2, SaniusPricing::cycles(71, 60, 10));
        $this->assertSame(2, SaniusPricing::cycles(130, 60, 10));
        $this->assertSame(3, SaniusPricing::cycles(131, 60, 10));
        $this->assertSame(2, SaniusPricing::cycles(61, 60, 0));
        $this->assertSame(4, SaniusPricing::cycles(100, 30, 5));   // 95 / 30 -> 4
    }

    public function test_weekend_price_days_in_romanian_time(): void
    {
        // Vineri 23:30 ora RO = vineri; sambata 00:30 ora RO = vineri 22:30 UTC
        $this->assertFalse(SaniusPricing::isWeekend(Carbon::parse('2026-11-20 21:30', 'UTC')));
        $this->assertTrue(SaniusPricing::isWeekend(Carbon::parse('2026-11-20 22:30', 'UTC')));
        $this->assertTrue(SaniusPricing::isWeekend(Carbon::parse('2026-11-22 12:00', 'UTC')));
        // 1 Decembrie 2026 e marti: weekend doar daca organizatorul l-a pus pe lista
        $tue = Carbon::parse('2026-12-01 10:00', 'Europe/Bucharest');
        $this->assertSame('weekday', SaniusPricing::dayType($tue));
        $this->assertSame('weekend', SaniusPricing::dayType($tue, ['2026-12-01']));
    }

    public function test_rental_amount_counts_cycles_tows_per_cycle_and_damage(): void
    {
        $rules = ['cycle_minutes' => 60, 'tows_per_cycle' => 2, 'grace_minutes' => 10];

        // 1 h 40 min -> 2 cicluri, 4 tractari incluse, 6 facute -> 2 platite
        $a = SaniusPricing::rental('weekday', 100, $rules, 30, 5, 6, 'medium', 50);
        $this->assertSame(2, $a['cycles_used']);
        $this->assertSame(60.0, $a['cycles']);
        $this->assertSame(4, $a['tows_included']);
        $this->assertSame(2, $a['tows_paid']);
        $this->assertSame(10.0, $a['tows']);
        $this->assertSame(50.0, $a['damage']);
        $this->assertSame(120.0, $a['total']);

        // 20 min, nicio tractare: un ciclu
        $b = SaniusPricing::rental('weekend', 20, $rules, 40, 5, 0);
        $this->assertSame(1, $b['cycles_used']);
        $this->assertSame(0, $b['tows_included']);
        $this->assertSame(40.0, $b['total']);
        $this->assertSame('weekend', $b['day_type']);

        // avarie fara nivel nu se taxeaza
        $c = SaniusPricing::rental('weekday', 30, $rules, 30, 5, 1, null, 50);
        $this->assertSame(0.0, $c['damage']);
        $this->assertSame(30.0, $c['total']);
    }

    public function test_prepaid_credit_is_shared_by_all_sleds_and_not_refunded(): void
    {
        // 2 sanii x 1 ciclu platite; o sanie 1 ciclu, alta 2 -> 1 ciclu de plata
        $r = SaniusPricing::applyCredit([10 => 1, 11 => 2], 2);
        $this->assertSame([10 => 0, 11 => 1], $r['to_pay']);
        $this->assertSame(0, $r['credit_left']);

        // au platit 2, au luat o singura sanie 2 ore -> nimic de plata
        $r = SaniusPricing::applyCredit([10 => 2], 2);
        $this->assertSame([10 => 0], $r['to_pay']);

        // au platit 3, au folosit 1 -> raman 2 nefolosite (nu se returneaza)
        $r = SaniusPricing::applyCredit([10 => 1], 3);
        $this->assertSame([10 => 0], $r['to_pay']);
        $this->assertSame(2, $r['credit_left']);

        // fara plata la intrare: totul la iesire
        $r = SaniusPricing::applyCredit([10 => 2, 11 => 1], 0);
        $this->assertSame([10 => 2, 11 => 1], $r['to_pay']);
    }

    private function shift(array $attrs): SaniusTowShift
    {
        // Fara baza de date: valorile Carbon brute trec prin cast-uri fara format de conexiune.
        $s = new SaniusTowShift;
        $s->setRawAttributes($attrs);

        return $s;
    }

    public function test_driver_blocks_returns_while_offline_or_with_unsent_scans(): void
    {
        $now = Carbon::parse('2026-12-20 12:00:00');

        $fresh = $this->shift(['last_ping_at' => $now->copy()->subSeconds(5), 'pending_count' => 0]);
        $this->assertFalse($fresh->isBlocking($now, 30));

        $offline = $this->shift(['last_ping_at' => $now->copy()->subSeconds(31), 'pending_count' => 0]);
        $this->assertTrue($offline->isBlocking($now, 30));

        $unsent = $this->shift(['last_ping_at' => $now->copy()->subSeconds(2), 'pending_count' => 3]);
        $this->assertTrue($unsent->isBlocking($now, 30));

        $ended = $this->shift(['last_ping_at' => $now->copy()->subHour(), 'pending_count' => 5, 'ended_at' => $now]);
        $this->assertFalse($ended->isBlocking($now, 30));
    }

    public function test_force_unlock_lasts_until_the_next_ping(): void
    {
        $now = Carbon::parse('2026-12-20 12:00:00');

        $dead = $this->shift([
            'last_ping_at' => $now->copy()->subMinutes(10),
            'pending_count' => 2,
            'force_unlocked_at' => $now->copy()->subMinute(),
        ]);
        $this->assertFalse($dead->isBlocking($now, 30));

        // Telefonul a revenit si a dat ping dupa deblocare, cu scanari netrimise: blocheaza din nou.
        $back = $this->shift([
            'last_ping_at' => $now->copy()->subSeconds(3),
            'pending_count' => 2,
            'force_unlocked_at' => $now->copy()->subMinute(),
        ]);
        $this->assertTrue($back->isBlocking($now, 30));
    }

    public function test_settings_roles_and_parking_rules(): void
    {
        $s = new SaniusSetting;
        $s->members = [['member_id' => 7, 'role' => 'desk'], ['member_id' => 9, 'role' => 'bogus']];
        $s->parking = ['free_minutes_with_access' => '180', 'unknown' => 5];
        $s->mode = 'off';

        $this->assertSame('desk', $s->roleOf(7));
        $this->assertNull($s->roleOf(9));
        $this->assertNull($s->roleOf(8));
        $this->assertNull($s->roleOf(null));
        $this->assertSame(['free_minutes' => 10, 'free_minutes_with_access' => 180, 'unit_minutes' => 30], $s->parkingRules());
        $this->assertTrue($s->isOff());

        $this->assertSame(['cycle_minutes' => 60, 'tows_per_cycle' => 1, 'grace_minutes' => 10, 'weekend_extra_dates' => []], $s->rentalRules());
        $s->rental = ['cycle_minutes' => '90', 'tows_per_cycle' => 3, 'grace_minutes' => '', 'weekend_extra_dates' => ['2026-12-25', 'x', '2026-12-01', '2026-12-25']];
        $this->assertSame(['cycle_minutes' => 90, 'tows_per_cycle' => 3, 'grace_minutes' => 10, 'weekend_extra_dates' => ['2026-12-01', '2026-12-25']], $s->rentalRules());

        $contract = $s->contractRules();
        $this->assertFalse($contract['required']);
        $this->assertSame(['Casa 1'], $contract['stations']);
        $this->assertStringContainsString('{nume}', $contract['body']);

        $s->mode = 'test';
        $this->assertFalse($s->isOff());
        $this->assertTrue($s->isTestMode());
    }

    public function test_sled_qr_codes(): void
    {
        $this->assertSame('ABCDE12345', SaniusSled::normalizeCode('SANIE:abcde12345'));
        $this->assertSame('ABCDE12345', SaniusSled::normalizeCode(' abcde12345 '));
    }
}
