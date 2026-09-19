<?php

namespace Tests\Feature\Marketplace;

use App\Http\Controllers\Api\MarketplaceClient\LeadsController;
use App\Models\Marketplace\OrganizerLead;
use App\Models\MarketplaceClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The /inregistrare-locatie signup (LeadsController::create) with the company step: the CUI is looked up at ANAF
 * server-side and only ANAF's data is kept, the ticked needs are filtered to the known keys, the city slug is kept.
 * ANAF is faked; the controller is called directly (the API-key middleware isn't under test).
 *
 * Run: DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit --filter OrganizerLeadSignupTest
 */
class OrganizerLeadSignupTest extends TestCase
{
    protected MarketplaceClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Builds its own schema: run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
        Schema::create('marketplace_clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->nullable(); $t->string('status')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_06_04_122308_create_marketplace_organizer_leads_table.php'))->up();
        (require base_path('database/migrations/2026_06_04_122309_create_marketplace_organizer_lead_events_table.php'))->up();

        $id = DB::table('marketplace_clients')->insertGetId(['name' => 'bilete.online', 'slug' => 'bilete-online', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->client = MarketplaceClient::find($id);
    }

    public function test_company_is_taken_from_anaf_and_needs_are_filtered(): void
    {
        Http::fake(['webservicesp.anaf.ro/*' => Http::response($this->anafFound())]);

        $lead = $this->submit(['cui' => 'RO 14399840', 'needs' => ['scanning', 'bogus', 'online_sales'], 'city_slug' => 'bucuresti']);

        $company = $lead->meta['company'];
        $this->assertTrue($company['verified']);
        $this->assertSame('14399840', $company['cui']);
        $this->assertSame('DANTE INTERNATIONAL SA', $company['name']);
        $this->assertSame('J2002000372404', $company['reg_com']);
        $this->assertSame('Şos. Virtuţii 148 spatiul E47', $company['address']);
        $this->assertSame(['Sector 6 Mun. Bucureşti', 'MUNICIPIUL BUCUREŞTI', '60787'], [$company['city'], $company['county'], $company['zip']]);
        $this->assertTrue($company['vat_payer']);
        $this->assertFalse($company['inactive']);
        $this->assertFalse($company['deregistered']);
        $this->assertSame('4754', $company['caen']);
        // known keys only, in the canonical order
        $this->assertSame(['online_sales', 'scanning'], $lead->meta['needs']);
        $this->assertSame('bucuresti', $lead->meta['city_slug']);
        $this->assertSame('127.0.0.1', $lead->meta['ip']);
        Http::assertSent(fn ($request) => $request[0]['cui'] === '14399840');
    }

    public function test_anaf_not_finding_the_cui_keeps_it_unverified(): void
    {
        Http::fake(['webservicesp.anaf.ro/*' => Http::response(['found' => [], 'notFound' => [12345678]])]);

        $lead = $this->submit(['cui' => '12345678']);

        $this->assertSame(['cui' => '12345678', 'verified' => false], $lead->meta['company']);
    }

    public function test_anaf_down_keeps_the_lead_with_the_cui_unverified(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $lead = $this->submit(['cui' => 'RO14399840']);

        $this->assertSame(['cui' => '14399840', 'verified' => false], $lead->meta['company']);
        $this->assertSame('Muzeul Test', $lead->location_name);
    }

    public function test_deregistered_company_is_flagged(): void
    {
        $payload = $this->anafFound();
        $payload['found'][0]['stare_inactiv']['dataRadiere'] = '2021-03-01';
        $payload['found'][0]['date_generale']['stare_inregistrare'] = 'RADIERE din data 01.03.2021';
        Http::fake(['webservicesp.anaf.ro/*' => Http::response($payload)]);

        $lead = $this->submit(['cui' => '14399840']);

        $this->assertTrue($lead->meta['company']['deregistered']);
    }

    public function test_without_the_new_fields_the_lead_is_as_before(): void
    {
        Http::fake();

        $lead = $this->submit([]);

        $this->assertSame(['ip', 'user_agent'], array_keys($lead->meta));
        Http::assertNothingSent();
    }

    protected function submit(array $extra): OrganizerLead
    {
        $body = array_merge([
            'contact_name' => 'Ana Pop', 'email' => 'ana@example.test', 'location_name' => 'Muzeul Test', 'city' => 'București',
        ], $extra);
        $request = Request::create('/api/marketplace-client/leads', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1'], json_encode($body));
        $request->attributes->set('marketplace_client', $this->client);

        $response = app(LeadsController::class)->create($request);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        return OrganizerLead::findOrFail($response->getData(true)['data']['lead_id']);
    }

    protected function anafFound(): array
    {
        return ['found' => [[
            'date_generale' => [
                'cui' => 14399840, 'denumire' => 'DANTE INTERNATIONAL SA', 'nrRegCom' => 'J2002000372404', 'stare_inregistrare' => 'INREGISTRAT din data 29.08.2006',
                'forma_juridica' => 'SOCIETATE COMERCIALĂ PE ACŢIUNI', 'cod_CAEN' => '4754', 'adresa' => 'MUNICIPIUL BUCUREŞTI, SECTOR 2, STR. GARA HERĂSTRĂU, NR.6',
            ],
            'inregistrare_scop_Tva' => ['scpTVA' => true],
            'stare_inactiv' => ['dataInactivare' => '', 'dataRadiere' => '', 'statusInactivi' => false],
            'adresa_sediu_social' => [
                'sdenumire_Localitate' => 'Sector 6 Mun. Bucureşti', 'sdenumire_Strada' => 'Şos. Virtuţii', 'snumar_Strada' => '148',
                'sdenumire_Judet' => 'MUNICIPIUL BUCUREŞTI', 'sdetalii_Adresa' => 'spatiul E47', 'scod_Postal' => '60787',
            ],
        ]], 'notFound' => []];
    }
}
