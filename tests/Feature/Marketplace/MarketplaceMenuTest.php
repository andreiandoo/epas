<?php

namespace Tests\Feature\Marketplace;

use App\Filament\Marketplace\Pages\Dashboard;
use App\Filament\Marketplace\Pages\Settings;
use App\Filament\Marketplace\Resources\BlogArticleResource;
use App\Filament\Marketplace\Resources\BlogCategoryResource;
use App\Filament\Marketplace\Resources\OrganizerLeadResource;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceClient;
use App\Support\Marketplace\MarketplaceMenu;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Setări → Meniu: the entries a marketplace hides leave its sidebar and answer 403, for that marketplace only. Covers
 * what can be saved, the entries nested under a hidden one, the routes blocked (every page of a hidden resource), the
 * menu items hidden by path, a real request answering 403, the list offered in Setări and another marketplace left
 * untouched.
 *
 * Run: DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit --filter MarketplaceMenuTest
 */
class MarketplaceMenuTest extends TestCase
{
    protected MarketplaceAdmin $admin;

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
        Schema::create('microservices', function (Blueprint $t) {
            $t->id(); $t->string('slug'); $t->text('name')->nullable(); $t->timestamps();
        });
        Schema::create('marketplace_client_microservices', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('marketplace_client_id'); $t->unsignedBigInteger('microservice_id'); $t->string('status')->nullable(); $t->boolean('is_active')->default(true);
            $t->timestamp('activated_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->text('settings')->nullable(); $t->text('usage_stats')->nullable(); $t->boolean('is_default')->default(false);
            $t->integer('sort_order')->default(0); $t->decimal('billing_amount', 10, 2)->nullable(); $t->string('billing_cycle')->nullable(); $t->timestamps();
        });
        $hidden = json_encode(['hidden_navigation' => [OrganizerLeadResource::class, BlogArticleResource::class, Dashboard::class, Settings::class, 'App\\Nope', 42]]);
        DB::table('marketplace_clients')->insert([
            ['id' => 5, 'name' => 'bilete.online', 'slug' => 'bilete-online', 'status' => 'active', 'settings' => $hidden, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Alt marketplace', 'slug' => 'alt', 'status' => 'active', 'settings' => json_encode(['site_title' => 'Alt']), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $id = DB::table('marketplace_admins')->insertGetId(['marketplace_client_id' => 5, 'email' => 'admin@example.test', 'name' => 'Admin', 'role' => 'super_admin', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->admin = MarketplaceAdmin::find($id);
        Filament::setCurrentPanel(Filament::getPanel('marketplace'));
    }

    public function test_only_real_hideable_entries_are_kept_and_nested_ones_follow_their_parent(): void
    {
        $client = MarketplaceClient::find(5);

        // the dashboard, the settings page and anything that isn't an entry of the panel are dropped
        $this->assertSame([OrganizerLeadResource::class, BlogArticleResource::class], MarketplaceMenu::storedClasses($client));
        // Blog Categories sits under Blog in the menu: hidden with it
        $hidden = MarketplaceMenu::hiddenClasses($client);
        $this->assertContains(BlogCategoryResource::class, $hidden);
        $this->assertNotContains(Settings::class, $hidden);
        // another marketplace hides nothing
        $this->assertSame([], MarketplaceMenu::hiddenClasses(MarketplaceClient::find(6)));
    }

    public function test_every_page_of_a_hidden_resource_is_blocked_and_nothing_else(): void
    {
        $hidden = MarketplaceMenu::hiddenClasses(MarketplaceClient::find(5));

        foreach (['index', 'view', 'edit', 'create'] as $page) {
            $this->assertTrue(MarketplaceMenu::isHiddenRoute('filament.marketplace.resources.organizer-leads.' . $page, $hidden), $page);
        }
        $this->assertTrue(MarketplaceMenu::isHiddenRoute(BlogCategoryResource::getRouteBaseName(Filament::getPanel('marketplace')) . '.index', $hidden));
        $this->assertFalse(MarketplaceMenu::isHiddenRoute('filament.marketplace.resources.organizers.index', $hidden));
        $this->assertFalse(MarketplaceMenu::isHiddenRoute(Settings::getRouteName(Filament::getPanel('marketplace')), $hidden));
        $this->assertFalse(MarketplaceMenu::isHiddenRoute(Dashboard::getRouteName(Filament::getPanel('marketplace')), $hidden));
        $this->assertFalse(MarketplaceMenu::isHiddenRoute('', $hidden));
    }

    public function test_menu_items_leading_to_a_hidden_entry_are_made_invisible(): void
    {
        $items = [
            NavigationItem::make('Lead-uri partener')->url(OrganizerLeadResource::getNavigationUrl() . '?tableFilters=x'),
            NavigationItem::make('Blog Categories')->url(BlogCategoryResource::getNavigationUrl()),
            NavigationItem::make('Setări')->url(Settings::getNavigationUrl()),
        ];

        MarketplaceMenu::hideItems($items, MarketplaceMenu::hiddenClasses(MarketplaceClient::find(5)));

        $this->assertSame([false, false, true], array_map(fn (NavigationItem $item) => $item->isVisible(), $items));
    }

    public function test_opening_a_hidden_page_answers_403(): void
    {
        $this->actingAs($this->admin, 'marketplace_admin')
            ->get('/marketplace/organizer-leads')
            ->assertForbidden();

        // the same page with nothing hidden isn't refused by the menu (whatever else the bare test schema makes it do)
        DB::table('marketplace_clients')->where('id', 5)->update(['settings' => json_encode(['site_title' => 'x'])]);
        $this->assertNotSame(403, $this->actingAs($this->admin->fresh(), 'marketplace_admin')->get('/marketplace/organizer-leads')->getStatusCode());
    }

    public function test_the_list_in_settings_offers_the_menu_entries_and_keeps_what_is_hidden(): void
    {
        $this->actingAs($this->admin, 'marketplace_admin');

        $options = MarketplaceMenu::options(MarketplaceClient::find(5));

        $this->assertSame('Sales · Lead-uri partener', $options[OrganizerLeadResource::class] ?? null);
        $this->assertSame('Content · Blog', $options[BlogArticleResource::class] ?? null);
        $this->assertArrayNotHasKey(Dashboard::class, $options);
        $this->assertArrayNotHasKey(Settings::class, $options);
    }
}
