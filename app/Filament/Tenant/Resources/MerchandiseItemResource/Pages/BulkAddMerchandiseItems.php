<?php

namespace App\Filament\Tenant\Resources\MerchandiseItemResource\Pages;

use App\Filament\Tenant\Resources\MerchandiseItemResource;
use App\Models\FestivalEdition;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseSupplier;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Illuminate\Database\Eloquent\Builder;

class BulkAddMerchandiseItems extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MerchandiseItemResource::class;
    protected static ?string $title = 'Adaugare in bulk';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-plus';

    protected string $view = 'filament.tenant.pages.bulk-add-merchandise-items';

    public ?array $headerData = [];
    public ?array $rows = [];
    public int $savedCount = 0;
    public bool $showResults = false;

    public function mount(): void
    {
        $this->headerData = [
            'festival_edition_id' => null,
            'merchandise_supplier_id' => null,
            'currency' => 'RON',
            'vat_rate' => 19,
            'vat_included' => false,
            'invoice_number' => null,
            'invoice_date' => null,
        ];

        // Start with 5 empty rows
        $this->rows = array_fill(0, 5, [
            'name' => '', 'type' => 'consumable', 'unit' => 'buc',
            'quantity' => '', 'price' => '',
        ]);
    }

    public function headerForm(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;

        return $schema
            ->statePath('headerData')
            ->schema([
                SC\Section::make('Setari comune (se aplica tuturor produselor)')
                    ->description('Completeaza mai intai aceste campuri, apoi adauga produsele in tabelul de mai jos.')
                    ->schema([
                        Forms\Components\Select::make('festival_edition_id')
                            ->label('Editie festival')
                            ->options(
                                FestivalEdition::where('tenant_id', $tenant?->id)
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('merchandise_supplier_id')
                            ->label('Furnizor')
                            ->options(
                                MerchandiseSupplier::where('tenant_id', $tenant?->id)
                                    ->pluck('name', 'id')
                            )
                            ->searchable(),
                        Forms\Components\Select::make('currency')
                            ->label('Moneda')
                            ->options(['RON' => 'RON', 'EUR' => 'EUR'])
                            ->default('RON'),
                        Forms\Components\TextInput::make('vat_rate')
                            ->label('Cota TVA %')
                            ->numeric()
                            ->default(19)
                            ->suffix('%'),
                        Forms\Components\Toggle::make('vat_included')
                            ->label('TVA inclus in pretul de achizitie')
                            ->default(false)
                            ->helperText('Daca DA, pretul introdus contine deja TVA. Daca NU, TVA-ul se va calcula separat.'),
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Nr. factura'),
                        Forms\Components\DatePicker::make('invoice_date')
                            ->label('Data factura'),
                    ])->columns(4),
            ]);
    }

    protected function getForms(): array
    {
        return ['headerForm'];
    }

    public function addRows(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->rows[] = [
                'name' => '', 'type' => 'consumable', 'unit' => 'buc',
                'quantity' => '', 'price' => '',
            ];
        }
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            Notification::make()->title('Eroare: nu exista tenant.')->danger()->send();
            return;
        }

        $editionId = $this->headerData['festival_edition_id'] ?? null;
        if (!$editionId) {
            Notification::make()->title('Selecteaza editia de festival.')->warning()->send();
            return;
        }

        $vatRate = (float) ($this->headerData['vat_rate'] ?? 19);
        $vatIncluded = (bool) ($this->headerData['vat_included'] ?? false);

        $saved = 0;
        $errors = [];

        foreach ($this->rows as $i => $row) {
            $name = trim($row['name'] ?? '');
            if (!$name) continue;

            $quantity = (float) ($row['quantity'] ?? 0);
            $priceInput = (float) ($row['price'] ?? 0);

            if ($quantity <= 0 || $priceInput <= 0) {
                $errors[] = "Rand " . ($i + 1) . " ({$name}): cantitate sau pret invalid.";
                continue;
            }

            // Convert price to cents
            if ($vatIncluded) {
                // Price already includes VAT — store as-is
                $priceCents = (int) round($priceInput * 100);
            } else {
                // Price is without VAT — store without VAT (VAT calculated separately)
                $priceCents = (int) round($priceInput * 100);
            }

            try {
                MerchandiseItem::create([
                    'tenant_id' => $tenant->id,
                    'festival_edition_id' => $editionId,
                    'merchandise_supplier_id' => $this->headerData['merchandise_supplier_id'] ?? null,
                    'name' => $name,
                    'type' => $row['type'] ?? 'consumable',
                    'unit' => $row['unit'] ?? 'buc',
                    'quantity' => $quantity,
                    'acquisition_price_cents' => $priceCents,
                    'currency' => $this->headerData['currency'] ?? 'RON',
                    'vat_rate' => $vatRate,
                    'invoice_number' => $this->headerData['invoice_number'] ?? null,
                    'invoice_date' => $this->headerData['invoice_date'] ?? null,
                    'meta' => ['vat_included_in_price' => $vatIncluded],
                ]);
                $saved++;
            } catch (\Throwable $e) {
                $errors[] = "Rand " . ($i + 1) . " ({$name}): " . $e->getMessage();
            }
        }

        $this->savedCount = $saved;
        $this->showResults = true;

        if ($saved > 0) {
            Notification::make()
                ->title("{$saved} produse adaugate cu succes!")
                ->success()
                ->send();
        }

        if (!empty($errors)) {
            Notification::make()
                ->title('Unele randuri au erori')
                ->body(implode("\n", array_slice($errors, 0, 5)))
                ->warning()
                ->send();
        }
    }

    public function resetForm(): void
    {
        $this->showResults = false;
        $this->savedCount = 0;
        $this->rows = array_fill(0, 5, [
            'name' => '', 'type' => 'consumable', 'unit' => 'buc',
            'quantity' => '', 'price' => '',
        ]);
    }
}
