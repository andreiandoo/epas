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
    private ?int $defaultTenantId = null;

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

        $this->makeOrder(['tenant_id' => $tenant, 'total' => 100]);
        $this->makeOrder(['tenant_id' => $tenant, 'total' => 50]);
        $this->makeOrder(['marketplace_client_id' => $marketplace, 'total' => 200]);

        $stats = $this->widgetGet('/api/tixello-widget/summary')
            ->assertOk()
            ->json('data.stats');

        $this->assertEquals(350.0, $stats['sales']['total']);
        $this->assertEquals(3, $stats['sales']['total_orders']);
        /* Tenant 10% din 150 = 15; marketplace 1% din 200 = 2. */
        $this->assertEquals(17.0, $stats['revenue']['total']);
        $this->assertEquals(17.0, $stats['revenue']['tickets_total']);
    }

    public function test_doar_comenzile_platite_intra_in_cifre(): void
    {
        $this->makeOrder(['total' => 100, 'status' => 'paid']);
        $this->makeOrder(['total' => 999, 'status' => 'pending']);
        $this->makeOrder(['total' => 999, 'status' => 'cancelled']);
        $this->makeOrder(['total' => 999, 'status' => 'failed']);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(100.0, $stats['sales']['total']);
        $this->assertEquals(10.0, $stats['revenue']['total']);
    }

    public function test_comenzile_vechi_cad_pe_total_cents(): void
    {
        /* Aşa arată comenzile de dinainte de coloana `total`. */
        $this->makeOrder(['total' => 0, 'total_cents' => 12345]);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(123.45, $stats['sales']['total']);
    }

    public function test_sumele_in_alta_moneda_sunt_convertite(): void
    {
        $this->makeExchangeRate('EUR', 'RON', 5.0);

        $this->makeOrder(['total' => 100, 'currency' => 'EUR']);
        $this->makeOrder(['total' => 500, 'currency' => 'RON']);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        /* 500 RON / 5 = 100 EUR, deci 200 EUR în total. */
        $this->assertEquals(200.0, $stats['sales']['total']);
        /* 10% din fiecare, convertit: 10 EUR + 50 RON (= 10 EUR) = 20 EUR. */
        $this->assertEquals(20.0, $stats['revenue']['total']);
        /* A doua monedă e informativă: 200 EUR × 5. */
        $this->assertEquals(1000.0, $stats['sales']['total_secondary']);
    }

    public function test_azi_se_taie_in_fusul_romaniei_nu_in_utc(): void
    {
        /* 14 august, 22:30 UTC = 15 august, 01:30 la Bucureşti — adică AZI.
           În UTC ar cădea ieri, iar cifra ar fi greşită pentru un telefon
           ţinut în România. */
        $this->makeOrder(['total' => 40, 'created_at' => '2026-08-14 22:30:00']);

        /* 15 august, 22:30 UTC = 16 august, 01:30 la Bucureşti — adică MÂINE. */
        $this->makeOrder(['total' => 70, 'created_at' => '2026-08-15 22:30:00']);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(110.0, $stats['sales']['total']);
        $this->assertEquals(40.0, $stats['sales']['today']);
        $this->assertEquals(4.0, $stats['revenue']['today']);
    }

    public function test_biletele_se_numara_doar_valide_si_din_comenzi_platite(): void
    {
        $paid = $this->makeOrder(['total' => 100]);
        $pending = $this->makeOrder(['total' => 100,  'status' => 'pending']);

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
            
        ]);

        $commission = $this->widgetGet('/api/tixello-widget/summary')->json('data.commissions.0');

        $this->assertSame('Untold 2026', $commission['event']);
        $this->assertSame('Ambilet', $commission['source']);
    }

    public function test_fara_eveniment_pe_comanda_cade_pe_numele_liniei(): void
    {
        $order = $this->makeOrder(['total' => 100]);

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

    public function test_clientii_cu_rata_zero_nu_produc_comision(): void
    {
        /* Comisionul Tixello e rata clientului × valoarea comenzii. Un client
           fără rată (contract special, migrare în curs) nu aduce venit, deci
           nu are ce căuta nici în listă, nici în alerte. */
        $tenantFaraRata = $this->makeTenant('Tenant fără rată', 0.0);

        $this->makeOrder(['tenant_id' => $tenantFaraRata, 'total' => 1000]);

        $data = $this->widgetGet('/api/tixello-widget/summary')->json('data');

        $this->assertSame([], $data['commissions']);
        $this->assertEquals(0.0, $data['stats']['revenue']['total']);
        /* Vânzarea însă s-a întâmplat şi se vede ca atare. */
        $this->assertEquals(1000.0, $data['stats']['sales']['total']);
    }

    public function test_doar_comisioanele_noi_declanseaza_alerta(): void
    {
        $first = $this->makeOrder(['total' => 100]);
        $second = $this->makeOrder(['total' => 200, ]);

        $data = $this->widgetGet("/api/tixello-widget/summary?since_commission_id={$first}")
            ->json('data');

        $this->assertCount(2, $data['commissions']);
        $this->assertCount(1, $data['new_commissions']);
        $this->assertSame($second, $data['new_commissions'][0]['id']);
        $this->assertSame($second, $data['cursor']['last_commission_id']);
    }

    public function test_fara_cursor_nimic_nu_e_marcat_ca_nou(): void
    {
        $this->makeOrder(['total' => 100]);

        $data = $this->widgetGet('/api/tixello-widget/summary')->json('data');

        /* Prima pornire a telefonului: lista se vede, dar nu sună. */
        $this->assertCount(1, $data['commissions']);
        $this->assertSame([], $data['new_commissions']);
    }

    public function test_alerteaza_si_comisioanele_din_afara_listei_afisate(): void
    {
        /* Regresie: alertele se filtrau din lista de afişare (5 poziţii), deci
           dacă între două interogări intrau 9 comisioane, 4 dispăreau fără
           sunet. Alertele au acum interogarea lor. */
        $ids = [];
        foreach (range(1, 9) as $i) {
            $ids[] = $this->makeOrder(['total' => 10 * $i]);
        }

        $cursor = $ids[0];

        $data = $this->widgetGet("/api/tixello-widget/summary?since_commission_id={$cursor}")
            ->json('data');

        $this->assertCount(5, $data['commissions'], 'Widget-ul afişează tot 5.');
        $this->assertCount(8, $data['new_commissions'], 'Dar alertele le prind pe toate cele noi.');
        $this->assertEquals(end($ids), $data['cursor']['last_commission_id']);
    }

    public function test_alertele_sunt_plafonate(): void
    {
        config(['tixello-widget.new_commissions_cap' => 3]);

        $first = $this->makeOrder(['total' => 10]);
        foreach (range(1, 6) as $i) {
            $this->makeOrder(['total' => 10]);
        }

        $data = $this->widgetGet("/api/tixello-widget/summary?since_commission_id={$first}")
            ->json('data');

        /* Un telefon întors după o pauză lungă nu trebuie să sune de 300 de ori. */
        $this->assertCount(3, $data['new_commissions']);
    }

    public function test_cursorul_nu_da_inapoi_cand_nu_e_nimic_nou(): void
    {
        $order = $this->makeOrder(['total' => 100]);

        $data = $this->widgetGet("/api/tixello-widget/summary?since_commission_id={$order}")
            ->json('data');

        $this->assertSame([], $data['new_commissions']);
        $this->assertEquals($order, $data['cursor']['last_commission_id']);
    }

    public function test_comenzile_vechi_si_noi_se_aduna_impreuna(): void
    {
        /* Regresie: căderea pe `total_cents` se făcea pe suma întregului grup,
           deci o comandă veche (doar cenţi) alături de una nouă (`total`) era
           numărată ca 0. */
        $this->makeOrder(['total' => 100, 'total_cents' => 0, ]);
        $this->makeOrder(['total' => 0, 'total_cents' => 4500, ]);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(145.0, $stats['sales']['total']);
    }

    public function test_azi_poate_fi_taiat_dupa_data_platii(): void
    {
        config(['tixello-widget.today_basis' => 'paid_at']);

        /* Comandă intrată ieri, plătită azi: pe `paid_at` e venitul de azi. */
        $this->makeOrder([
            'total' => 80,
            
            'created_at' => '2026-08-13 10:00:00',
            'paid_at' => '2026-08-15 09:00:00',
        ]);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(80.0, $stats['sales']['today']);
        $this->assertEquals(8.0, $stats['revenue']['today']);
    }

    public function test_limita_de_comisioane_e_plafonata(): void
    {
        $this->widgetGet('/api/tixello-widget/summary?limit=999')->assertStatus(422);
    }

    // =====================================================================
    // Veniturile Tixello core
    // =====================================================================

    public function test_venitul_e_al_tixello_nu_al_marketplaceului(): void
    {
        /* Regresie de fond: prima versiune însuma `orders.commission_amount`,
           care e comisionul MARKETPLACE-ULUI către organizatorii lui — bani
           care nu ajung niciodată la Tixello. Aici marketplace-ul ia 20%, dar
           Tixello are 1%: cifra trebuie să fie 2, nu 40. */
        $marketplace = $this->makeMarketplace('Ambilet', 1.0);

        DB::table('orders')->insert([
            'marketplace_client_id' => $marketplace,
            'status' => 'paid',
            'total' => 200,
            'total_cents' => 0,
            'commission_amount' => 40,
            'currency' => 'EUR',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(2.0, $stats['revenue']['total']);
    }

    public function test_fiecare_client_aduce_venit_cu_rata_lui(): void
    {
        $ieftin = $this->makeMarketplace('Marketplace 1%', 1.0);
        $scump = $this->makeTenant('Tenant 12%', 12.0);

        $this->makeOrder(['marketplace_client_id' => $ieftin, 'total' => 1000]);
        $this->makeOrder(['tenant_id' => $scump, 'total' => 1000]);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(130.0, $stats['revenue']['total']);
    }

    public function test_retururile_nu_iau_comisionul_inapoi(): void
    {
        /* Regula de afaceri: comisionul e pe vânzare. O restituire, integrală
           sau parţială, nu i-l ia lui Tixello. Înainte, comanda ieşea cu totul
           din cifre — dispărea şi vânzarea. */
        $tenant = $this->makeTenant('Teatru', 10.0);

        $this->makeOrder(['tenant_id' => $tenant, 'total' => 500, 'status' => 'partially_refunded']);
        $this->makeOrder(['tenant_id' => $tenant, 'total' => 300, 'status' => 'refunded']);

        $stats = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats');

        $this->assertEquals(800.0, $stats['sales']['total']);
        $this->assertEquals(80.0, $stats['revenue']['total']);
    }

    public function test_serviciile_aduc_jumatate_din_valoare(): void
    {
        $marketplace = $this->makeMarketplace('Ambilet', 1.0);

        DB::table('service_orders')->insert([
            'marketplace_client_id' => $marketplace,
            'service_type' => 'featuring',
            'total' => 300,
            'currency' => 'EUR',
            'payment_status' => 'paid',
            'status' => 'completed',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /* Neplătit — nu e venit. */
        DB::table('service_orders')->insert([
            'marketplace_client_id' => $marketplace,
            'total' => 999,
            'currency' => 'EUR',
            'payment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $revenue = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats.revenue');

        /* TIXELLO_SHARE = 50% din 300. */
        $this->assertEquals(150.0, $revenue['services_total']);
        $this->assertEquals(150.0, $revenue['services_today']);
        $this->assertEquals(150.0, $revenue['total']);
    }

    public function test_abonamentele_lunare_se_arata_separat_nu_adunate(): void
    {
        $marketplace = $this->makeMarketplace('Ambilet', 1.0);

        DB::table('marketplace_client_microservices')->insert([
            'marketplace_client_id' => $marketplace,
            'is_active' => true,
            'billing_amount' => 99,
            'billing_cycle' => 'monthly',
            'activated_at' => now()->subMonths(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('marketplace_client_microservices')->insert([
            'marketplace_client_id' => $marketplace,
            'is_active' => true,
            'billing_amount' => 500,
            'billing_cycle' => 'one_time',
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $revenue = $this->widgetGet('/api/tixello-widget/summary')->json('data.stats.revenue');

        /* Taxa one-time are dată, deci intră în total şi în „azi". */
        $this->assertEquals(500.0, $revenue['one_time_total']);
        $this->assertEquals(500.0, $revenue['one_time_today']);
        $this->assertEquals(500.0, $revenue['total']);

        /* Abonamentul lunar e o RATĂ, nu o sumă acumulată: se arată separat,
           altfel „all time" ar fi o cifră fără sens. */
        $this->assertEquals(99.0, $revenue['recurring_monthly']);
    }

    // =====================================================================
    // Helpere
    // =====================================================================

    private function widgetGet(string $uri)
    {
        return $this->withToken($this->plainToken)->getJson($uri);
    }

    private function makeTenant(string $name, float $commissionRate = 10.0): int
    {
        return DB::table('tenants')->insertGetId([
            'name' => $name,
            'commission_rate' => $commissionRate,
            'slug' => str($name)->slug()->value(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeMarketplace(string $name, float $commissionRate = 1.0): int
    {
        return DB::table('marketplace_clients')->insertGetId([
            'name' => $name,
            'commission_rate' => $commissionRate,
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

        /* Fără un client cu rată, comanda n-ar produce venit Tixello, iar
           testele ar verifica zerouri. Cel implicit are 10%. */
        if (! isset($attributes['tenant_id']) && ! isset($attributes['marketplace_client_id'])) {
            $attributes['tenant_id'] = $this->defaultTenantId ??= $this->makeTenant('Tenant implicit', 10.0);
        }

        return DB::table('orders')->insertGetId(array_merge([
            'status' => 'paid',
            'payment_status' => 'paid',
            'total' => 0,
            'total_cents' => 0,
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
