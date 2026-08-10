<?php

namespace Tests\Feature\Shorts;

use App\Filament\Resources\Shorts\ShortResource;
use App\Models\Short;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

/**
 * Compiles the Filament resource's schema and table.
 *
 * Cheap but load-bearing: Filament component APIs shift between minor versions,
 * and a bad chain (a helperText() on a component that has none, say) only
 * surfaces when the page is rendered — i.e. in production, not at deploy time.
 */
class ShortResourceTest extends ShortsTestCase
{
    public function test_form_schema_compiles(): void
    {
        $schema = ShortResource::form(new Schema);

        $this->assertNotEmpty($schema->getComponents());
    }

    public function test_table_compiles_with_its_columns_filters_and_bulk_actions(): void
    {
        $table = ShortResource::table(Table::make($this->tableHost()));

        $columns = array_keys($table->getColumns());

        $this->assertContains('title', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('avg_watch_ratio', $columns);
        $this->assertNotEmpty($table->getFilters());
    }

    public function test_resource_is_not_tenant_scoped(): void
    {
        // Core admin curates the global feed: unlike the tenant twin, this
        // resource must never narrow the query.
        $this->assertFalse(
            (new \ReflectionMethod(ShortResource::class, 'getEloquentQuery'))->getDeclaringClass()->getName() === ShortResource::class,
            'ShortResource must not override getEloquentQuery() — core admin sees every short.'
        );
    }

    public function test_resource_points_at_the_short_model(): void
    {
        $this->assertSame(Short::class, ShortResource::getModel());
        $this->assertSame(['index', 'create', 'edit'], array_keys(ShortResource::getPages()));
    }

    protected function tableHost(): Component&HasTable
    {
        return new class extends Component implements HasTable
        {
            use InteractsWithTable;

            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function render(): string
            {
                return '';
            }
        };
    }
}
