<?php

namespace Tests\Feature\Marketplace;

use App\Http\Controllers\Api\MarketplaceClient\LeadsController;
use App\Models\Marketplace\OrganizerLead;
use App\Models\Marketplace\OrganizerLeadEvent;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The /inregistrare-locatie signup (LeadsController::create) with the company step: the CUI is looked up at ANAF
 * server-side and only ANAF's data is kept, the ticked needs are filtered to the known keys, the city slug is kept.
 * With a password the organizer account is created through the organizer registration (pending, ANAF's company data,
 * a token to sign in), refused cleanly when the email already has an organizer here or another account with another
 * password, and skipped (lead only) when the email is an organizer on another marketplace.
 * ANAF, mail and the queue are faked; the controller is called directly (the API-key middleware isn't under test).
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
            $t->id(); $t->string('name')->nullable(); $t->string('email')->nullable(); $t->timestamps();
        });
        Schema::create('marketplace_organizers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('marketplace_client_id')->nullable();
            $t->string('email')->unique(); // unique across marketplaces, as in production
            $t->string('slug')->nullable();
            foreach (array_diff((new MarketplaceOrganizer())->getFillable(), ['marketplace_client_id', 'email', 'slug']) as $column) {
                $t->text($column)->nullable();
            }
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id(); $t->morphs('tokenable'); $t->text('name'); $t->string('token', 64)->unique(); $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamps();
        });
        Schema::create('marketplace_customers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('marketplace_client_id'); $t->string('email'); $t->string('password')->nullable(); $t->string('wp_password_hash')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('marketplace_artist_accounts', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('marketplace_client_id'); $t->string('email'); $t->string('password')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_06_04_122308_create_marketplace_organizer_leads_table.php'))->up();
        (require base_path('database/migrations/2026_06_04_122309_create_marketplace_organizer_lead_events_table.php'))->up();

        $id = DB::table('marketplace_clients')->insertGetId(['id' => 5, 'name' => 'bilete.online', 'slug' => 'bilete-online', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->client = MarketplaceClient::find($id);
        Mail::fake();
        Queue::fake();
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
        $this->assertNull($this->lastResponse['data']['account']);
        $this->assertSame(0, MarketplaceOrganizer::count());
        Http::assertNothingSent();
    }

    public function test_a_password_creates_the_pending_organizer_account_with_anafs_company(): void
    {
        Http::fake(['webservicesp.anaf.ro/*' => Http::response($this->anafFound())]);

        $lead = $this->submit(['email' => 'Ana@Example.test', 'cui' => 'RO14399840', 'password' => 'o-parola-buna', 'terms_accepted' => true, 'phone' => '0722000111', 'website' => 'https://camera13.ro']);

        $account = $this->lastResponse['data']['account'];
        $this->assertTrue($account['created']);
        $this->assertNotEmpty($account['token']);
        $organizer = MarketplaceOrganizer::findOrFail($account['organizer']['id']);
        $this->assertSame(
            ['pending', 'ana@example.test', 'Muzeul Test', 'Ana Pop', 'pj', 'venue', 5],
            [$organizer->status, $organizer->email, $organizer->name, $organizer->contact_name, $organizer->person_type, $organizer->organizer_type, (int) $organizer->marketplace_client_id]
        );
        $this->assertSame(
            ['DANTE INTERNATIONAL SA', '14399840', 'J2002000372404', 'Şos. Virtuţii 148 spatiul E47', 'Sector 6 Mun. Bucureşti', 'MUNICIPIUL BUCUREŞTI', '60787', true, 'București'],
            [$organizer->company_name, $organizer->company_tax_id, $organizer->company_registration, $organizer->company_address, $organizer->company_city, $organizer->company_county, $organizer->company_zip, (bool) $organizer->vat_payer, $organizer->city]
        );
        $this->assertTrue(Hash::check('o-parola-buna', $organizer->getAttributes()['password']));
        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $organizer->id)->count());
        // the lead points to the account and says so in its timeline; the password is kept nowhere on the lead
        $this->assertSame($organizer->id, $lead->fresh()->meta['organizer_id']);
        $this->assertArrayHasKey('terms_accepted_at', $lead->meta);
        $this->assertSame(1, OrganizerLeadEvent::where('lead_id', $lead->id)->where('event_type', OrganizerLeadEvent::TYPE_ACCOUNT_CREATED)->count());
        $this->assertStringNotContainsString('o-parola-buna', json_encode($lead->fresh()->toArray()) . json_encode(OrganizerLeadEvent::all()->toArray()));
    }

    public function test_anaf_down_still_creates_the_account_with_the_cui_only(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->submit(['cui' => '14399840', 'password' => 'o-parola-buna']);

        $organizer = MarketplaceOrganizer::sole();
        $this->assertSame(['14399840', null], [$organizer->company_tax_id, $organizer->company_name]);
    }

    public function test_an_organizer_here_with_the_same_email_is_refused_before_the_lead(): void
    {
        Http::fake();
        $this->organizer(5, 'ana@example.test');

        $errors = $this->refused(['email' => 'ANA@example.test', 'password' => 'o-parola-buna']);

        $this->assertArrayHasKey('email', $errors);
        $this->assertSame(0, OrganizerLead::count());
        $this->assertSame(1, MarketplaceOrganizer::count());
    }

    public function test_another_account_here_with_another_password_is_refused(): void
    {
        Http::fake();
        DB::table('marketplace_customers')->insert(['marketplace_client_id' => 5, 'email' => 'ana@example.test', 'password' => Hash::make('alta-parola-123'), 'created_at' => now(), 'updated_at' => now()]);

        $errors = $this->refused(['password' => 'o-parola-buna']);

        $this->assertStringContainsString('cont de client', $errors['password'][0]);
        $this->assertSame(0, OrganizerLead::count());
    }

    public function test_an_organizer_of_another_marketplace_gets_the_lead_without_an_account(): void
    {
        Http::fake();
        $this->organizer(1, 'ana@example.test');

        $lead = $this->submit(['password' => 'o-parola-buna']);

        $this->assertSame(['created' => false, 'reason' => 'email_in_use'], $this->lastResponse['data']['account']);
        $this->assertSame(1, MarketplaceOrganizer::count());
        $this->assertArrayNotHasKey('organizer_id', $lead->meta);
    }

    protected array $lastResponse = [];

    protected function request(array $extra): Request
    {
        $body = array_merge([
            'contact_name' => 'Ana Pop', 'email' => 'ana@example.test', 'location_name' => 'Muzeul Test', 'city' => 'București',
        ], $extra);
        $request = Request::create('/api/marketplace-client/leads', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1'], json_encode($body));
        $request->attributes->set('marketplace_client', $this->client);

        return $request;
    }

    protected function submit(array $extra): OrganizerLead
    {
        $response = app(LeadsController::class)->create($this->request($extra));

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->lastResponse = $response->getData(true);

        return OrganizerLead::findOrFail($this->lastResponse['data']['lead_id']);
    }

    /** The 422 the form gets: its errors by field. */
    protected function refused(array $extra): array
    {
        try {
            app(LeadsController::class)->create($this->request($extra));
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('Expected a 422');
    }

    protected function organizer(int $clientId, string $email): void
    {
        DB::table('marketplace_organizers')->insert(['marketplace_client_id' => $clientId, 'email' => $email, 'name' => 'Existent', 'slug' => 'existent-' . $clientId, 'status' => 'active', 'password' => Hash::make('x-parola-123'), 'created_at' => now(), 'updated_at' => now()]);
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
