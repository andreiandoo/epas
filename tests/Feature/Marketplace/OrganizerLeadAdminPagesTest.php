<?php

namespace Tests\Feature\Marketplace;

use App\Filament\Marketplace\Resources\OrganizerLeadResource\Pages\EditOrganizerLead;
use App\Filament\Marketplace\Resources\OrganizerLeadResource\Pages\ViewOrganizerLead;
use App\Models\Marketplace\OrganizerLead;
use App\Models\Marketplace\OrganizerLeadEvent;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceOrganizer;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The lead pages in the marketplace admin (/marketplace/organizer-leads/{id}) render: the view page used to answer 500
 * (a Filament 3 action class in the "Link campanie" modal). Covers the view page with its header actions, the company
 * and needs section, the timeline, and the edit page.
 *
 * Run: DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit --filter OrganizerLeadAdminPagesTest
 */
class OrganizerLeadAdminPagesTest extends TestCase
{
    protected OrganizerLead $lead;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Builds its own schema: run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
        Schema::create('marketplace_clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->nullable(); $t->string('domain')->nullable(); $t->string('status')->nullable(); $t->text('settings')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('marketplace_admins', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('marketplace_client_id')->nullable(); $t->string('email'); $t->string('name')->nullable(); $t->string('password')->nullable();
            $t->string('role')->nullable(); $t->text('permissions')->nullable(); $t->string('status')->nullable(); $t->string('remember_token')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('email')->nullable(); $t->timestamps();
        });
        Schema::create('marketplace_organizers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('marketplace_client_id')->nullable(); $t->string('email')->unique(); $t->string('slug')->nullable();
            foreach (array_diff((new MarketplaceOrganizer())->getFillable(), ['marketplace_client_id', 'email', 'slug']) as $column) {
                $t->text($column)->nullable();
            }
            $t->softDeletes(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_06_04_122308_create_marketplace_organizer_leads_table.php'))->up();
        (require base_path('database/migrations/2026_06_04_122309_create_marketplace_organizer_lead_events_table.php'))->up();

        // not id 1: the resource is hidden for Ambilet (marketplace client 1)
        $clientId = DB::table('marketplace_clients')->insertGetId(['id' => 5, 'name' => 'bilete.online', 'slug' => 'bilete-online', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $adminId = DB::table('marketplace_admins')->insertGetId(['marketplace_client_id' => $clientId, 'email' => 'admin@example.test', 'name' => 'Admin', 'role' => 'super_admin', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs(MarketplaceAdmin::find($adminId), 'marketplace_admin');
        Filament::setCurrentPanel(Filament::getPanel('marketplace'));

        $this->lead = OrganizerLead::create([
            'marketplace_client_id' => $clientId, 'contact_name' => 'Ana Pop', 'email' => 'ana@example.test', 'location_name' => 'Camera 13', 'city' => 'Brașov',
            'category_slug' => 'escape-rooms', 'status' => OrganizerLead::STATUS_NEW, 'source' => 'partner_signup',
            'meta' => [
                'ip' => '127.0.0.1',
                'company' => ['cui' => '14399840', 'verified' => true, 'name' => 'DANTE INTERNATIONAL SA', 'reg_com' => 'J2002000372404', 'address' => 'Şos. Virtuţii 148', 'city' => 'Sector 6 Mun. Bucureşti', 'county' => 'MUNICIPIUL BUCUREŞTI', 'zip' => '60787', 'vat_payer' => true, 'status' => 'INREGISTRAT din data 29.08.2006', 'inactive' => false, 'deregistered' => false, 'legal_form' => '', 'caen' => '4754'],
                'needs' => ['online_sales', 'scanning'],
            ],
        ]);
        OrganizerLeadEvent::create(['lead_id' => $this->lead->id, 'marketplace_client_id' => $clientId, 'event_type' => OrganizerLeadEvent::TYPE_FORM_SUBMITTED, 'summary' => 'Form submitted for Camera 13 (Brașov)']);
    }

    public function test_view_page_renders_with_company_needs_and_timeline(): void
    {
        Livewire::test(ViewOrganizerLead::class, ['record' => $this->lead->getRouteKey()])
            ->assertOk()
            ->assertSee('DANTE INTERNATIONAL SA')
            ->assertSee('Vânzare online de bilete')
            ->assertSee('Scanare bilete la intrare')
            ->assertSee('Form submitted for Camera 13 (Brașov)');
    }

    public function test_campaign_link_modal_opens_with_the_location_name(): void
    {
        Livewire::test(ViewOrganizerLead::class, ['record' => $this->lead->getRouteKey()])
            ->mountAction('copy_campaign_link')
            ->assertOk()
            ->assertSee('loc=Camera+13', false);
    }

    public function test_the_account_created_with_the_lead_is_shown_with_its_status(): void
    {
        $organizerId = DB::table('marketplace_organizers')->insertGetId(['marketplace_client_id' => 5, 'email' => 'ana@example.test', 'name' => 'Camera 13', 'slug' => 'camera-13', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->lead->forceFill(['meta' => array_merge($this->lead->meta, ['organizer_id' => $organizerId])])->save();

        Livewire::test(ViewOrganizerLead::class, ['record' => $this->lead->getRouteKey()])
            ->assertOk()
            ->assertSee('Cont de operator')
            ->assertSee('Camera 13 (#' . $organizerId . ')')
            ->assertSee('în așteptarea aprobării')
            ->assertSee('/marketplace/organizers/' . $organizerId . '/edit', false);
    }

    public function test_edit_page_renders(): void
    {
        Livewire::test(EditOrganizerLead::class, ['record' => $this->lead->getRouteKey()])
            ->assertOk()
            ->assertSee('DANTE INTERNATIONAL SA');
    }
}
