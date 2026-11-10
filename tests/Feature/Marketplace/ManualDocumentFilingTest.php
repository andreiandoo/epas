<?php

namespace Tests\Feature\Marketplace;

use App\Models\Event;
use App\Models\EventDocumentFiling;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceOrganizer;
use App\Models\OrganizerDocument;
use App\Services\Marketplace\EventDocumentFilingService;
use App\Services\Marketplace\OrganizerFiscalDocuments;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Schemă redusă: istoricul complet de migrații nu rulează pe SQLite. */
class ManualDocumentFilingTest extends TestCase
{
    private MarketplaceAdmin $admin;

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $app['config']->set('database.connections.filing_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('database.default', 'filing_testing');
        DB::purge('filing_testing');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('marketplace_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->text('settings')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('marketplace_organizers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_client_id')->nullable();
            $t->string('name')->nullable();
            $t->boolean('marketplace_manages_documents')->default(false);
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('marketplace_tax_registries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_client_id')->nullable();
            $t->string('name')->nullable();
            $t->string('subname')->nullable();
            $t->string('city')->nullable();
            $t->string('address')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('submission_method')->nullable();
            $t->string('submission_email')->nullable();
            $t->string('third_party_name')->nullable();
            $t->string('third_party_url')->nullable();
            $t->text('third_party_procedure')->nullable();
            $t->timestamps();
        });
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_client_id')->nullable();
            $t->foreignId('marketplace_organizer_id')->nullable();
            $t->foreignId('marketplace_tax_registry_id')->nullable();
            $t->boolean('is_cancelled')->default(false);
            $t->timestamps();
        });
        Schema::create('marketplace_tax_templates', function (Blueprint $t) {
            $t->id();
            $t->string('type');
            $t->timestamps();
        });
        Schema::create('event_generated_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id');
            $t->foreignId('marketplace_tax_template_id');
            $t->string('file_path')->nullable();
            $t->string('filename')->nullable();
            $t->timestamps();
        });
        Schema::create('organizer_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->nullable();
            $t->string('document_type');
            $t->string('file_path')->nullable();
            $t->string('file_name')->nullable();
            $t->timestamps();
        });
        Schema::create('event_document_filings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_client_id');
            $t->foreignId('event_id');
            $t->foreignId('marketplace_tax_registry_id')->nullable();
            $t->string('document_type', 40);
            $t->timestamp('document_generated_at')->nullable();
            $t->string('file_name')->nullable();
            $t->string('method', 20);
            $t->string('sent_to')->nullable();
            $t->string('third_party_name')->nullable();
            $t->unsignedBigInteger('email_log_id')->nullable();
            $t->string('registration_number', 100)->nullable();
            $t->timestamp('registration_number_at')->nullable();
            $t->string('registration_number_by_name')->nullable();
            $t->foreignId('filed_by_id')->nullable();
            $t->string('filed_by_name')->nullable();
            $t->timestamp('filed_at');
            $t->timestamp('voided_at')->nullable();
            $t->string('voided_by_name')->nullable();
            $t->timestamps();
        });

        // Static between tests, and the in-memory database is rebuilt each time.
        foreach (['tableExists', 'registrationColumn'] as $flag) {
            (new \ReflectionProperty(EventDocumentFilingService::class, $flag))->setValue(null, null);
        }

        DB::table('marketplace_clients')->insert(['id' => 1, 'name' => 'Ambilet']);
        // Organizer the marketplace doesn't manage, and a registry with no method.
        DB::table('marketplace_organizers')->insert(['id' => 1, 'marketplace_client_id' => 1, 'name' => 'Org', 'marketplace_manages_documents' => false]);
        DB::table('marketplace_tax_registries')->insert([
            'id' => 1, 'marketplace_client_id' => 1, 'name' => 'DITL Sector 1', 'address' => 'Str. Exemplu 1',
            'email' => 'taxe@primarie.test',
        ]);
        DB::table('events')->insert([
            'id' => 10, 'marketplace_client_id' => 1, 'marketplace_organizer_id' => 1, 'marketplace_tax_registry_id' => 1,
        ]);
        DB::table('marketplace_tax_templates')->insert(['id' => 1, 'type' => 'cerere_avizare']);
        DB::table('event_generated_documents')->insert([
            'event_id' => 10, 'marketplace_tax_template_id' => 1, 'file_path' => 'docs/cerere.pdf',
            'filename' => 'cerere.pdf', 'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
        ]);

        $this->admin = (new MarketplaceAdmin)->forceFill(['id' => 5, 'name' => 'Operator', 'marketplace_client_id' => 1]);
    }

    private function filing(array $payload, int $eventId = 10)
    {
        return $this->actingAs($this->admin, 'marketplace_admin')
            ->postJson("/marketplace/api/events/{$eventId}/document-filing", $payload);
    }

    public function test_operator_confirms_a_manual_filing_despite_missing_settings(): void
    {
        $this->filing(['action' => 'confirm_manual', 'document_type' => 'cerere_avizare'])
            ->assertOk()->assertJson(['success' => true]);

        $filing = EventDocumentFiling::sole();
        $this->assertSame(EventDocumentFiling::METHOD_MANUAL, $filing->method);
        $this->assertSame('Operator', $filing->filed_by_name);
        $this->assertSame(1, (int) $filing->marketplace_tax_registry_id);

        $status = app(EventDocumentFilingService::class)->status(Event::find(10), EventDocumentFilingService::GROUP_CERERE);
        $this->assertTrue($status['filed']);

        $this->filing(['action' => 'confirm_manual', 'document_type' => 'cerere_avizare'])->assertStatus(422);
        $this->assertSame(1, EventDocumentFiling::count());
    }

    public function test_a_document_not_generated_here_can_be_marked_as_filed_outside(): void
    {
        $this->filing(['action' => 'confirm_manual', 'document_type' => 'decont'])->assertStatus(422);

        $this->filing(['action' => 'confirm_manual', 'document_type' => 'pv_distrugere', 'filed_on' => '2026-10-01'])->assertOk();

        $filing = EventDocumentFiling::sole();
        $this->assertNull($filing->document_generated_at);
        $this->assertNull($filing->file_name);

        $service = app(EventDocumentFilingService::class);
        $this->assertTrue($service->status(Event::find(10), EventDocumentFilingService::GROUP_IMPOZIT_PV)['rows']['pv_distrugere']['filed']);

        // A PDF generated afterwards doesn't reopen a filing made outside the platform.
        DB::table('marketplace_tax_templates')->insert(['id' => 3, 'type' => 'pv_distrugere']);
        DB::table('event_generated_documents')->insert([
            'event_id' => 10, 'marketplace_tax_template_id' => 3, 'file_path' => 'docs/pv.pdf',
            'filename' => 'pv.pdf', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertTrue($service->status(Event::find(10), EventDocumentFilingService::GROUP_IMPOZIT_PV)['rows']['pv_distrugere']['filed']);
    }

    public function test_a_past_filing_day_covers_a_document_generated_after_it(): void
    {
        // The cerere was generated an hour ago; it was filed on paper weeks before.
        $this->filing([
            'action' => 'confirm_manual', 'document_type' => 'cerere_avizare',
            'filed_on' => now('Europe/Bucharest')->subDays(20)->toDateString(), 'registration_number' => ' 45/2026 ',
        ])->assertOk();

        $filing = EventDocumentFiling::sole();
        $this->assertSame('45/2026', $filing->registration_number);
        $this->assertSame('Operator', $filing->registration_number_by_name);
        $this->assertTrue($filing->filed_at->lt(now()->subDays(19)));

        $status = app(EventDocumentFilingService::class)->status(Event::find(10), EventDocumentFilingService::GROUP_CERERE);
        $this->assertTrue($status['filed']);

        // Regenerated after the filing was recorded: the filed version is old now.
        DB::table('event_generated_documents')->insert([
            'event_id' => 10, 'marketplace_tax_template_id' => 1, 'file_path' => 'docs/cerere2.pdf',
            'filename' => 'cerere2.pdf', 'created_at' => now()->addMinute(), 'updated_at' => now()->addMinute(),
        ]);
        $status = app(EventDocumentFilingService::class)->status(Event::find(10), EventDocumentFilingService::GROUP_CERERE);
        $this->assertFalse($status['filed']);
        $this->assertTrue($status['refile']);
    }

    public function test_a_future_or_invalid_day_is_refused_and_the_number_is_optional(): void
    {
        $base = ['action' => 'confirm_manual', 'document_type' => 'cerere_avizare'];

        $this->filing($base + ['filed_on' => now('Europe/Bucharest')->addDays(2)->toDateString()])->assertStatus(422);
        $this->filing($base + ['filed_on' => '2026-02-30'])->assertStatus(422);
        $this->filing($base + ['filed_on' => 'ieri'])->assertStatus(422);
        $this->filing($base + ['registration_number' => str_repeat('1', 101)])->assertStatus(422);
        $this->assertSame(0, EventDocumentFiling::count());

        $this->filing($base + ['filed_on' => now('Europe/Bucharest')->toDateString()])->assertOk();
        $this->assertNull(EventDocumentFiling::sole()->registration_number);
    }

    public function test_the_last_filing_written_wins_even_with_an_earlier_day(): void
    {
        // An old filing of a previous version, then the current version
        // marked as filed on an earlier day.
        EventDocumentFiling::create([
            'marketplace_client_id' => 1, 'event_id' => 10, 'document_type' => 'cerere_avizare',
            'document_generated_at' => now()->subDays(3), 'method' => EventDocumentFiling::METHOD_EMAIL,
            'filed_at' => now()->subDays(2),
        ]);
        $service = app(EventDocumentFilingService::class);
        $this->assertFalse($service->status(Event::find(10), EventDocumentFilingService::GROUP_CERERE)['filed']);

        $this->filing([
            'action' => 'confirm_manual', 'document_type' => 'cerere_avizare',
            'filed_on' => now('Europe/Bucharest')->subDays(5)->toDateString(),
        ])->assertOk();

        $this->assertTrue($service->status(Event::find(10), EventDocumentFilingService::GROUP_CERERE)['filed']);
    }

    public function test_impozit_and_pv_are_marked_one_by_one(): void
    {
        DB::table('marketplace_tax_templates')->insert([['id' => 2, 'type' => 'declaratie_impozite'], ['id' => 3, 'type' => 'pv_distrugere']]);
        foreach ([2, 3] as $template) {
            DB::table('event_generated_documents')->insert([
                'event_id' => 10, 'marketplace_tax_template_id' => $template, 'file_path' => "docs/{$template}.pdf",
                'filename' => "{$template}.pdf", 'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
            ]);
        }

        $service = app(EventDocumentFilingService::class);
        $this->filing(['action' => 'confirm_manual', 'document_type' => 'pv_distrugere'])->assertOk();

        $status = $service->status(Event::find(10), EventDocumentFilingService::GROUP_IMPOZIT_PV);
        $this->assertTrue($status['rows']['pv_distrugere']['filed']);
        $this->assertFalse($status['rows']['declaratie_impozite']['filed']);
        $this->assertFalse($status['filed']);

        $this->filing(['action' => 'confirm_manual', 'document_type' => 'declaratie_impozite'])->assertOk();
        $this->assertTrue($service->status(Event::find(10), EventDocumentFilingService::GROUP_IMPOZIT_PV)['filed']);
    }

    public function test_registration_number_needs_a_confirmed_filing_and_can_be_changed(): void
    {
        $save = ['action' => 'registration_number', 'document_type' => 'cerere_avizare', 'registration_number' => ' 123/2026 '];

        $this->filing($save)->assertStatus(422);

        $this->filing(['action' => 'confirm_manual', 'document_type' => 'cerere_avizare'])->assertOk();
        $this->filing(['action' => 'registration_number', 'document_type' => 'cerere_avizare', 'registration_number' => '   '])->assertStatus(422);
        $this->filing($save)->assertOk();

        $filing = EventDocumentFiling::sole();
        $this->assertSame('123/2026', $filing->registration_number);
        $this->assertSame('Operator', $filing->registration_number_by_name);

        $this->filing(['registration_number' => '124/2026'] + $save)->assertOk();
        $this->assertSame('124/2026', $filing->fresh()->registration_number);
    }

    public function test_another_marketplaces_event_is_refused(): void
    {
        DB::table('events')->insert(['id' => 11, 'marketplace_client_id' => 2]);

        $this->filing(['action' => 'confirm_manual', 'document_type' => 'cerere_avizare'], 11)->assertNotFound();
    }

    public function test_the_existing_group_actions_still_answer(): void
    {
        $this->filing(['group' => 'nope', 'action' => 'send_email'])->assertStatus(422);
    }

    public function test_views_show_where_to_file_then_the_number_button(): void
    {
        $service = app(EventDocumentFilingService::class);
        $event = Event::find(10);
        $render = function () use ($service, $event) {
            return view('filament.marketplace.components.event-document-filing', [
                'event' => $event,
                'groups' => array_map(fn ($g) => $service->status($event, $g), array_keys($service::GROUPS)),
                'isSuperAdmin' => false,
            ])->render();
        };

        $before = $render();
        $this->assertStringContainsString('Am depus', $before);
        $this->assertStringContainsString('Data depunerii', $before);
        $this->assertStringContainsString('DITL Sector 1', $before);
        $this->assertStringContainsString('Str. Exemplu 1', $before);
        $this->assertStringContainsString('taxe@primarie.test', $before);
        $this->assertStringNotContainsString('nr. înregistrare', $before);

        $service->confirmManual($event, 'cerere_avizare', $this->admin);
        $service->saveRegistrationNumber($event, 'cerere_avizare', '77/A', $this->admin);

        $after = $render();
        $this->assertStringContainsString('Modifică nr. înregistrare', $after);
        $this->assertStringContainsString('77/A', $after);

        $document = (new OrganizerDocument)->forceFill(['document_type' => 'cerere_avizare', 'file_path' => 'docs/old.pdf']);
        $page = view('filament.marketplace.components.organizer-document-filing', [
            'document' => $document,
            'event' => $event,
            'status' => $service->documentStatus($event, 'cerere_avizare'),
        ])->render();
        $this->assertStringContainsString('Depus', $page);
        $this->assertStringContainsString('77/A', $page);
        $this->assertStringContainsString('versiune mai nouă', $page);
    }

    public function test_ops_board_cells_close_with_a_past_or_outside_filing(): void
    {
        $board = app(\App\Services\Marketplace\OpsBoardService::class);
        foreach (['tz' => 'Europe/Bucharest', 'today' => now('Europe/Bucharest')->startOfDay()] as $prop => $value) {
            (new \ReflectionProperty($board, $prop))->setValue($board, $value);
        }
        $cell = fn (?string $generatedAt, ?array $filing) => (new \ReflectionMethod($board, 'documentCell'))
            ->invoke($board, $generatedAt, $filing, true, now('Europe/Bucharest')->subDays(10)->startOfDay(), '/x');

        $generatedAt = now()->subHour()->format('Y-m-d H:i:s');
        $past = now()->subDays(20)->format('Y-m-d H:i:s');
        $filing = ['filed_at' => $past, 'document_generated_at' => $generatedAt, 'method_label' => 'depunere manuală', 'by' => 'Operator'];

        $this->assertSame('overdue', $cell(null, null)['state']);
        $this->assertSame('done', $cell(null, ['document_generated_at' => null] + $filing)['state']);
        $this->assertSame('done', $cell($generatedAt, $filing)['state']);
        $this->assertSame('overdue', $cell(now()->format('Y-m-d H:i:s'), $filing)['state']);
    }

    public function test_organizer_notices_are_on_hold(): void
    {
        $this->assertTrue(OrganizerFiscalDocuments::NOTICES_ON_HOLD);

        $documents = app(OrganizerFiscalDocuments::class);
        $this->assertNull($documents->notifyIfNeeded(Event::find(10)));
        $this->assertSame([], $documents->attention(MarketplaceOrganizer::find(1)));
    }
}
