<?php

namespace Tests\Feature\TixelloWidget;

use App\Models\TixelloWidgetToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contractul dintre server şi widget-ul de Android.
 *
 * Ce se verifică aici: că cifrele adună peste TOŢI tenanţii şi TOATE
 * marketplace-urile, că „azi" se taie în fusul României, că sumele în alte
 * monede sunt convertite şi că alerta se declanşează exact pentru comisioanele
 * pe care telefonul nu le-a văzut.
 */
class TixelloWidgetApiTest extends TixelloWidgetTestCase
{
    private string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->plainToken] = TixelloWidgetToken::issue('Telefon de test');

        /* 15 august 2026, 12:00 UTC = 15:00 la Bucureşti. */
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // =====================================================================
    // Autentificare
    // =====================================================================

    public function test_fara_token_endpointul_e_inchis(): void
    {
        $this->getJson('/api/tixello-widget/summary')->assertStatus(401);
    }

    public function test_token_gresit_e_respins(): void
    {
        $this->withToken('twg_altceva')
            ->getJson('/api/tixello-widget/summary')
            ->assertStatus(401);
    }

    public function test_token_revocat_nu_mai_merge(): void
    {
        TixelloWidgetToken::first()->revoke();

        $this->widgetGet('/api/tixello-widget/summary')->assertStatus(401);
    }

    public function test_token_expirat_nu_mai_merge(): void
    {
        [, $expired] = TixelloWidgetToken::issue('Vechi', now()->subDay());

        $this->withToken($expired)
            ->getJson('/api/tixello-widget/summary')
            ->assertStatus(401);
    }

    public function test_pingul_confirma_tokenul_si_ii_spune_numele(): void
    {
        $this->widgetGet('/api/tixello-widget/ping')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('token_name', 'Telefon de test');
    }

    public function test_folosirea_marcheaza_tokenul(): void
    {
        $this->widgetGet('/api/tixello-widget/ping')->assertOk();

        $this->assertNotNull(TixelloWidgetToken::first()->last_used_at);
    }

    // =====================================================================
    // Cifre
    // =====================================================================

    public function test_aduna_peste_toti_tenantii_si_marketplaceurile(): void
    {
        $tenant = $this->makeTenant('Teatrul Mic');
        $marketplace = $this->makeMarketplace('Ambilet');

        $this->makeOrder(['tenant_id' => $tenant, 'total' => 100, 'commission_amount' => 5]);
        $this->makeOrder(['tenant_id' => $tenant, 'total' => 50, 'commission_amount' => 2.5]);
        $this->makeOrder(['marketplace_client_id' => $marketplace, 'total' => 200, 'commission_amount' => 10]);

        $stats = $this->widgetGet('/api/tixello-widget/summary')
            ->assertOk()
            ->json('data.stats');

        $this->assertEquals(350.0, $stats['sales']['total']);
        $this->assertEquals(3, $stats['sales']['total_orders']);
        $this->assertEquals(17.5, $stats['revenue']['total']);
    }

    public function test_doar_comenzile_platite_intra_in_cifre(): void
    {
        $this->makeOrder(['total' => 100, 'commission_amount' => 5, 'status' => 'paid']);
        $this->makeOrder(['total' => 999, 'commission_amount' => 99, 'status' => 'pending']);
        $this->makeOrder(['total' => 999, 'commission_amount' => 99, 'status' => 'cancelled']);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(100.0, $stats['sales']['total']);
        $this->assertEquals(5.0, $stats['revenue']['total']);
    }

    public function test_comenzile_vechi_cad_pe_total_cents(): void
    {
        /* Aşa arată comenzile de dinainte de coloana `total`. */
        $this->makeOrder(['total' => 0, 'total_cents' => 12345, 'commission_amount' => 1.23]);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(123.45, $stats['sales']['total']);
    }

    public function test_sumele_in_alta_moneda_sunt_convertite(): void
    {
        $this->makeExchangeRate('EUR', 'RON', 5.0);

        $this->makeOrder(['total' => 100, 'commission_amount' => 10, 'currency' => 'EUR']);
        $this->makeOrder(['total' => 500, 'commission_amount' => 50, 'currency' => 'RON']);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        /* 500 RON / 5 = 100 EUR, deci 200 EUR în total. */
        $this->assertEquals(200.0, $stats['sales']['total']);
        $this->assertEquals(20.0, $stats['revenue']['total']);
        /* A doua monedă e informativă: 200 EUR × 5. */
        $this->assertEquals(1000.0, $stats['sales']['total_secondary']);
    }

    public function test_azi_se_taie_in_fusul_romaniei_nu_in_utc(): void
    {
        /* 14 august, 22:30 UTC = 15 august, 01:30 la Bucureşti — adică AZI.
           În UTC ar cădea ieri, iar cifra ar fi greşită pentru un telefon
           ţinut în România. */
        $this->makeOrder(['total' => 40, 'commission_amount' => 4, 'created_at' => '2026-08-14 22:30:00']);

        /* 15 august, 22:30 UTC = 16 august, 01:30 la Bucureşti — adică MÂINE. */
        $this->makeOrder(['total' => 70, 'commission_amount' => 7, 'created_at' => '2026-08-15 22:30:00']);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(110.0, $stats['sales']['total']);
        $this->assertEquals(40.0, $stats['sales']['today']);
        $this->assertEquals(4.0, $stats['revenue']['today']);
    }

    public function test_biletele_se_numara_doar_valide_si_din_comenzi_platite(): void
    {
        $paid = $this->makeOrder(['total' => 100, 'commission_amount' => 5]);
        $pending = $this->makeOrder(['total' => 100, 'commission_amount' => 5, 'status' => 'pending']);

        $this->makeTicket($paid, 'valid', '2026-08-15 09:00:00');
        $this->makeTicket($paid, 'valid', '2026-08-10 09:00:00');
        $this->makeTicket($paid, 'cancelled', '2026-08-15 09:00:00');
        $this->makeTicket($pending, 'valid', '2026-08-15 09:00:00');

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(2, $stats['tickets']['total']);
        $this->assertEquals(1, $stats['tickets']['today']);
    }

    public function test_clientii_aduna_tenant_si_marketplace(): void
    {
        $this->makeCustomer('customers', '2026-08-15 08:00:00');
        $this->makeCustomer('customers', '2026-07-01 08:00:00');
        $this->makeCustomer('marketplace_customers', '2026-08-15 08:00:00');
        $this->makeCustomer('marketplace_customers', '2026-01-01 08:00:00');

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(4, $stats['customers']['total']);
        $this->assertEquals(2, $stats['customers']['today']);
        $this->assertEquals(2, $stats['customers']['tenant']);
        $this->assertEquals(2, $stats['customers']['marketplace']);
    }

    // =====================================================================
    // Comisioane
    // =====================================================================

    public function test_intoarce_ultimele_cinci_comisioane_cu_evenimentul_lor(): void
    {
        $tenant = $this->makeTenant('Teatrul Mic');
        $event = $this->makeEvent('Hamlet', $tenant);

        foreach (range(1, 7) as $i) {
            $this->makeOrder([
                'tenant_id' => $tenant,
                'event_id' => $event,
                'total' => 10 * $i,
                'commission_amount' => $i,
            ]);
        }

        $commissions = $this->widgetGet('/api/tixello-widget/summary')->json('data.commissions');

        $this->assertCount(5, $commissions);
        /* Cel mai nou primul — widget-ul le arată în ordinea asta. */
        $this->assertEquals(7.0, $commissions[0]['amount']);
        $this->assertSame('Hamlet', $commissions[0]['event']);
        $this->assertSame('Teatrul Mic', $commissions[0]['source']);
    }

    public function test_comisioanele_de_marketplace_isi_iau_numele_evenimentului(): void
    {
        $marketplace = $this->makeMarketplace('Ambilet');
        $marketplaceEvent = DB::table('marketplace_events')->insertGetId([
            'marketplace_client_id' => $marketplace,
            'name' => 'Untold 2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->makeOrder([
            'marketplace_client_id' => $marketplace,
            'marketplace_event_id' => $marketplaceEvent,
            'total' => 300,
            'commission_amount' => 15,
        ]);

        $commission = $this->widgetGet('/api/tixello-widget/summary')->json('data.commissions.0');

        $this->assertSame('Untold 2026', $commission['event']);
        $this->assertSame('Ambilet', $commission['source']);
    }

    public function test_fara_eveniment_pe_comanda_cade_pe_numele_liniei(): void
    {
        $order = $this->makeOrder(['total' => 100, 'commission_amount' => 5]);

        DB::table('order_items')->insert([
            'order_id' => $order,
            'name' => 'Bilet Gala',
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commission = $this->widgetGet('/api/tixello-widget/summary')->json('data.commissions.0');

        $this->assertSame('Bilet Gala', $commission['event']);
    }

    public function test_comenzile_fara_comision_nu_apar_in_lista(): void
    {
        $this->makeOrder(['total' => 100, 'commission_amount' => 0]);

        $this->assertSame([], $this->widgetGet('/api/tixello-widget/summary')->json('data.commissions'));
    }

    public function test_doar_comisioanele_noi_declanseaza_alerta(): void
    {
        $first = $this->makeOrder(['total' => 100, 'commission_amount' => 5]);
        $second = $this->makeOrder(['total' => 200, 'commission_amount' => 10]);

        $data = $this->widgetGet("/api/tixello-widget/summary?since_commission_id={$first}")
            ->json('data');

        $this->assertCount(2, $data['commissions']);
        $this->assertCount(1, $data['new_commissions']);
        $this->assertSame($second, $data['new_commissions'][0]['id']);
        $this->assertSame($second, $data['cursor']['last_commission_id']);
    }

    public function test_fara_cursor_nimic_nu_e_marcat_ca_nou(): void
    {
        $this->makeOrder(['total' => 100, 'commission_amount' => 5]);

        $data = $this->widgetGet('/api/tixello-widget/summary')->json('data');

        /* Prima pornire a telefonului: lista se vede, dar nu sună. */
        $this->assertCount(1, $data['commissions']);
        $this->assertSame([], $data['new_commissions']);
    }

    public function test_limita_de_comisioane_e_plafonata(): void
    {
        $this->widgetGet('/api/tixello-widget/summary?limit=999')->assertStatus(422);
    }

    // =====================================================================
    // Helpere
    // =====================================================================

    private function widgetGet(string $uri)
    {
        return $this->withToken($this->plainToken)->getJson($uri);
    }

    private function makeTenant(string $name): int
    {
        return DB::table('tenants')->insertGetId([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeMarketplace(string $name): int
    {
        return DB::table('marketplace_clients')->insertGetId([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Titlul evenimentelor e traductibil în producţie: JSON pe locale. */
    private function makeEvent(string $title, ?int $tenantId = null): int
    {
        return DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
            'title' => json_encode(['ro' => $title]),
            'slug' => str($title)->slug()->value(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeOrder(array $attributes = []): int
    {
        $createdAt = $attributes['created_at'] ?? now()->toDateTimeString();

        return DB::table('orders')->insertGetId(array_merge([
            'status' => 'paid',
            'payment_status' => 'paid',
            'total' => 0,
            'total_cents' => 0,
            'commission_amount' => 0,
            'currency' => 'EUR',
            'paid_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ], $attributes));
    }

    private function makeTicket(int $orderId, string $status, string $createdAt): int
    {
        return DB::table('tickets')->insertGetId([
            'order_id' => $orderId,
            'status' => $status,
            'code' => uniqid('t'),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function makeCustomer(string $table, string $createdAt): int
    {
        return DB::table($table)->insertGetId([
            'email' => uniqid('c') . '@example.test',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function makeExchangeRate(string $base, string $target, float $rate): void
    {
        DB::table('exchange_rates')->insert([
            'date' => now()->toDateString(),
            'base_currency' => $base,
            'target_currency' => $target,
            'rate' => $rate,
            'source' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
