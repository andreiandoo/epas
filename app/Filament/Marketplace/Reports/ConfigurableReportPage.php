<?php

namespace App\Filament\Marketplace\Reports;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceSavedReport;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pagină de raport cu coloane alese de admin, salvate ca rapoarte cu nume
 * (comune pentru toți adminii marketplace-ului).
 *
 * Fiecare pagină concretă dă catalogul de coloane. O coloană din catalog:
 *   label      eticheta
 *   group      grupul din formularul de alegere
 *   type       text | bool | enum | number | money | percent | date | datetime | list
 *   attr       coloana din tabel (sortare, căutare, filtrare)
 *   expr       expresie SQL în loc de attr, cu `bindings`; `select` => true o
 *              aduce și ca valoare (subinterogări, COALESCE cu valori implicite)
 *   state      fn ($record) — valoarea, când nu e direct în attr
 *   format     fn ($state, $record) — textul afișat (și exportat)
 *   options    [valoare => etichetă] pentru enum
 *   limit      trunchiere la afișare (textul complet rămâne în export)
 *   filterable / sortable / searchable — implicit da, dacă există attr/expr
 */
abstract class ConfigurableReportPage extends Page implements HasForms, HasTable
{
    use HasMarketplaceContext;
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.marketplace.pages.configurable-report';

    #[Url]
    public ?int $report = null;

    protected ?array $catalogCache = null;

    protected ?Collection $savedReportsCache = null;

    /** organizers | tax_registries */
    abstract protected static function entity(): string;

    /** @return array<string, array> */
    abstract protected function catalog(): array;

    /** Rapoartele create automat când nu există niciunul. @return array<int, array{name: string, columns: array}> */
    abstract protected function presets(): array;

    abstract protected function baseQuery(): Builder;

    /** Coloana afișată mereu prima, cu link spre editare. */
    abstract protected function titleColumnKey(): string;

    abstract protected function editUrl(Model $record): string;

    abstract protected function exportBaseName(): string;

    public function mount(): void
    {
        $this->ensurePresets();

        if (! $this->report || ! $this->savedReports()->firstWhere('id', $this->report)) {
            $this->report = $this->savedReports()->first()?->id;
        }
    }

    protected function columnCatalog(): array
    {
        return $this->catalogCache ??= $this->catalog();
    }

    public function savedReports(): Collection
    {
        $clientId = static::getMarketplaceClient()?->id;
        if (! $clientId) {
            return collect();
        }

        return $this->savedReportsCache ??= MarketplaceSavedReport::query()
            ->for($clientId, static::entity())
            ->get();
    }

    public function currentReport(): ?MarketplaceSavedReport
    {
        return $this->report ? $this->savedReports()->firstWhere('id', $this->report) : null;
    }

    public function reportUrl(int $id): string
    {
        return static::getUrl(['report' => $id]);
    }

    protected function ensurePresets(): void
    {
        $clientId = static::getMarketplaceClient()?->id;
        if (! $clientId) {
            return;
        }

        $exists = MarketplaceSavedReport::where('marketplace_client_id', $clientId)
            ->where('entity', static::entity())
            ->exists();
        if ($exists) {
            return;
        }

        foreach ($this->presets() as $i => $preset) {
            MarketplaceSavedReport::create([
                'marketplace_client_id' => $clientId,
                'entity' => static::entity(),
                'name' => $preset['name'],
                'columns' => array_values($preset['columns']),
                'sort_order' => $i,
            ]);
        }
        $this->savedReportsCache = null;
    }

    /** Coloanele raportului curent, în ordinea catalogului, fără coloana-titlu. */
    protected function selectedColumnKeys(): array
    {
        $catalog = $this->columnCatalog();
        $chosen = (array) ($this->currentReport()?->columns ?? []);

        return array_values(array_filter(
            array_keys($catalog),
            fn ($key) => $key !== $this->titleColumnKey() && in_array($key, $chosen, true)
        ));
    }

    // ------------------------------------------------------------------
    // Tabel
    // ------------------------------------------------------------------

    public function table(Table $table): Table
    {
        $catalog = $this->columnCatalog();
        $keys = $this->selectedColumnKeys();
        $titleKey = $this->titleColumnKey();

        $columns = [
            $this->makeColumn($titleKey, $catalog[$titleKey])
                ->url(fn (Model $record) => $this->editUrl($record))
                ->openUrlInNewTab()
                ->color('primary')
                ->weight('medium'),
        ];
        $filters = [];

        foreach ($keys as $key) {
            $columns[] = $this->makeColumn($key, $catalog[$key]);
            if ($filter = $this->makeFilter($key, $catalog[$key])) {
                $filters[] = $filter;
            }
        }

        return $table
            ->query(fn () => $this->reportQuery())
            ->columns($columns)
            // Iconița de filtre stă în dreapta căutării, pe același rând.
            ->filters($filters, layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(2)
            ->filtersFormWidth('4xl')
            ->toolbarActions($this->bulkActions())
            ->defaultSort($titleKey)
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->striped()
            ->emptyStateHeading('Niciun rezultat')
            ->emptyStateDescription('Schimbă filtrele sau alege alt raport.');
    }

    protected function reportQuery(): Builder
    {
        $query = $this->baseQuery();
        $model = $query->getModel();
        $query->select($model->getTable().'.*');

        foreach ($this->selectedColumnKeys() as $key) {
            $def = $this->columnCatalog()[$key];
            if (! empty($def['select']) && ! empty($def['expr'])) {
                $query->selectRaw('('.$def['expr'].') as '.$key, $def['bindings'] ?? []);
            }
        }

        return $query;
    }

    protected function makeColumn(string $key, array $def): Tables\Columns\Column
    {
        $type = $def['type'] ?? 'text';
        $attr = $def['attr'] ?? null;

        $column = $type === 'bool'
            ? Tables\Columns\IconColumn::make($key)->boolean()->alignCenter()
            : Tables\Columns\TextColumn::make($key);

        $column->label($def['label']);

        // Textul afișat se calculează direct ca valoare a coloanei: Filament
        // nu apelează formatStateUsing pe valori goale, iar unele coloane
        // au text și pentru gol (ex. „Nesetat”).
        if ($type === 'bool') {
            $column->state(fn (Model $record) => (bool) $this->rawState($key, $def, $record));
        } else {
            $column
                ->state(fn (Model $record) => $this->formatValue($def, $this->rawState($key, $def, $record), $record))
                ->placeholder('—');
            if (in_array($type, ['number', 'money', 'percent'], true)) {
                $column->alignEnd();
            }
            // Textele lungi: maximum două rânduri, restul în tooltip, ca să
            // nu se lățească tabelul.
            if (in_array($type, ['text', 'list', 'enum'], true)) {
                $column
                    ->wrap()
                    ->lineClamp(2)
                    ->extraAttributes(['style' => 'max-width: 20rem; min-width: 6rem;'])
                    ->tooltip(fn ($state) => (is_string($state) && mb_strlen($state) > 40) ? $state : null);
            }
        }

        $sortable = $def['sortable'] ?? (isset($def['expr']) || $attr);
        if ($sortable) {
            if (isset($def['expr'])) {
                $expr = $def['expr'];
                $bindings = $def['bindings'] ?? [];
                $column->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw(
                    '('.$expr.') '.($direction === 'desc' ? 'desc' : 'asc'),
                    $bindings
                ));
            } else {
                $column->sortable([$attr]);
            }
        }

        $searchable = $def['searchable'] ?? ($type === 'text' && $attr && ! isset($def['expr']));
        if ($searchable && $attr) {
            $column->searchable([$attr]);
        }

        return $column;
    }

    /** Textul afișat pentru o valoare — același în tabel și în export. */
    protected function formatValue(array $def, $state, Model $record): ?string
    {
        if (isset($def['format'])) {
            return ($def['format'])($state, $record);
        }

        if ($state === null || $state === '' || $state === []) {
            return null;
        }

        return match ($def['type'] ?? 'text') {
            'bool' => $state ? 'Da' : 'Nu',
            'enum' => $def['options'][$state] ?? (string) $state,
            'number' => number_format((float) $state, (float) $state == (int) $state ? 0 : 2, ',', '.'),
            'money' => number_format((float) $state, 2, ',', '.').' RON',
            'percent' => rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',').'%',
            'date' => $this->asDate($state)?->format('d.m.Y'),
            'datetime' => $this->asDate($state)?->format('d.m.Y H:i'),
            'list' => is_array($state) ? implode(', ', array_filter(array_map('strval', $state))) : (string) $state,
            default => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : (string) $state,
        };
    }

    protected function asDate($value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Filtre
    // ------------------------------------------------------------------

    protected function makeFilter(string $key, array $def): ?Tables\Filters\BaseFilter
    {
        $attr = $def['attr'] ?? null;
        $expr = $def['expr'] ?? null;
        if (($def['filterable'] ?? true) === false || (! $attr && ! $expr)) {
            return null;
        }

        $label = $def['label'];
        $bindings = $def['bindings'] ?? [];
        $target = $expr ? '('.$expr.')' : $this->baseQuery()->getModel()->qualifyColumn($attr);
        $raw = (bool) $expr;

        switch ($def['type'] ?? 'text') {
            case 'bool':
                return Tables\Filters\TernaryFilter::make($key)
                    ->label($label)
                    ->trueLabel('Da')
                    ->falseLabel('Nu')
                    ->queries(
                        true: fn (Builder $query) => $raw ? $query->whereRaw($target, $bindings) : $query->where($target, true),
                        false: fn (Builder $query) => $raw
                            ? $query->whereRaw('NOT '.$target, $bindings)
                            : $query->where(fn ($w) => $w->where($target, false)->orWhereNull($target)),
                        blank: fn (Builder $query) => $query,
                    );

            case 'enum':
                $options = ($def['options'] ?? []) + ['__none' => '(necompletat)'];

                return Tables\Filters\SelectFilter::make($key)
                    ->label($label)
                    ->multiple()
                    ->options($options)
                    ->query(function (Builder $query, array $data) use ($target, $bindings, $raw) {
                        $values = array_values(array_filter((array) ($data['values'] ?? []), fn ($v) => $v !== null && $v !== ''));
                        if (! $values) {
                            return $query;
                        }
                        $withNull = in_array('__none', $values, true);
                        $values = array_values(array_diff($values, ['__none']));

                        return $query->where(function ($w) use ($values, $withNull, $target, $bindings, $raw) {
                            if ($values) {
                                $raw
                                    ? $w->orWhereRaw($target.' IN ('.implode(',', array_fill(0, count($values), '?')).')', array_merge($bindings, $values))
                                    : $w->orWhereIn($target, $values);
                            }
                            if ($withNull) {
                                $raw
                                    ? $w->orWhereRaw($target.' IS NULL', $bindings)
                                    : $w->orWhereNull($target)->orWhere($target, '');
                            }
                        });
                    });

            case 'number':
            case 'money':
            case 'percent':
                return Tables\Filters\Filter::make($key)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('min')->label($label.' — de la')->numeric(),
                            TextInput::make('max')->label('până la')->numeric(),
                        ]),
                    ])
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data) use ($target, $bindings, $raw) {
                        foreach (['min' => '>=', 'max' => '<='] as $field => $op) {
                            $v = $data[$field] ?? null;
                            if ($v === null || $v === '') {
                                continue;
                            }
                            $raw
                                ? $query->whereRaw($target.' '.$op.' CAST(? AS DECIMAL(20,4))', array_merge($bindings, [(float) $v]))
                                : $query->where($target, $op, (float) $v);
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data) use ($label) {
                        $parts = [];
                        if (($data['min'] ?? '') !== '' && $data['min'] !== null) {
                            $parts[] = '≥ '.$data['min'];
                        }
                        if (($data['max'] ?? '') !== '' && $data['max'] !== null) {
                            $parts[] = '≤ '.$data['max'];
                        }

                        return $parts ? $label.': '.implode(', ', $parts) : null;
                    });

            case 'date':
            case 'datetime':
                return Tables\Filters\Filter::make($key)
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('from')->label($label.' — de la')->native(false)->displayFormat('d.m.Y'),
                            DatePicker::make('until')->label('până la')->native(false)->displayFormat('d.m.Y'),
                        ]),
                    ])
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data) use ($target, $bindings, $raw) {
                        if (! empty($data['from'])) {
                            $raw ? $query->whereRaw('DATE'.$target.' >= ?', array_merge($bindings, [$data['from']])) : $query->whereDate($target, '>=', $data['from']);
                        }
                        if (! empty($data['until'])) {
                            $raw ? $query->whereRaw('DATE'.$target.' <= ?', array_merge($bindings, [$data['until']])) : $query->whereDate($target, '<=', $data['until']);
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data) use ($label) {
                        $parts = array_filter([
                            ! empty($data['from']) ? 'de la '.Carbon::parse($data['from'])->format('d.m.Y') : null,
                            ! empty($data['until']) ? 'până la '.Carbon::parse($data['until'])->format('d.m.Y') : null,
                        ]);

                        return $parts ? $label.': '.implode(' ', $parts) : null;
                    });

            case 'text':
                if ($raw) {
                    return null;
                }

                return Tables\Filters\Filter::make($key)
                    ->schema([
                        TextInput::make('value')->label($label.' conține'),
                    ])
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->where($target, 'like', '%'.$data['value'].'%')
                        : $query)
                    ->indicateUsing(fn (array $data) => filled($data['value'] ?? null) ? $label.': '.$data['value'] : null);
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Acțiuni: rapoarte + export
    // ------------------------------------------------------------------

    /**
     * Setările din raportul curent care pot fi schimbate în bulk: coloanele
     * da/nu care sunt câmpuri reale, plus cele marcate cu `bulk` în catalog
     * (ex. mod comision, cu opțiunea „implicit”).
     *
     * @return array<string, array{label: string, attr: string, options: array}>
     */
    protected function bulkEditableColumns(): array
    {
        $out = [];
        foreach ($this->selectedColumnKeys() as $key) {
            $def = $this->columnCatalog()[$key];
            $bulk = $def['bulk'] ?? null;
            if ($bulk === false) {
                continue;
            }
            if (is_array($bulk)) {
                $out[$key] = ['label' => $def['label'], 'attr' => $bulk['attr'], 'options' => $bulk['options']];
            } elseif (($def['type'] ?? null) === 'bool' && ! empty($def['attr']) && empty($def['expr'])) {
                $out[$key] = ['label' => $def['label'], 'attr' => $def['attr'], 'options' => ['1' => 'Da', '0' => 'Nu']];
            }
        }

        return $out;
    }

    protected function bulkActions(): array
    {
        $editable = $this->bulkEditableColumns();
        if (! $editable) {
            return [];
        }

        return [
            BulkActionGroup::make([
                BulkAction::make('applySetting')
                    ->label('Aplică o setare')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->modalHeading('Aplică o setare pe rândurile selectate')
                    ->modalDescription('Se aplică doar pe rândurile bifate. Setările disponibile sunt coloanele din raportul curent care se pot modifica.')
                    ->modalSubmitActionLabel('Aplică')
                    ->schema([
                        Select::make('field')
                            ->label('Setare')
                            ->options(collect($editable)->map(fn ($e) => $e['label'])->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('value', null)),
                        Select::make('value')
                            ->label('Valoare nouă')
                            ->options(fn (Get $get) => $editable[$get('field')]['options'] ?? [])
                            ->required()
                            ->visible(fn (Get $get) => filled($get('field'))),
                    ])
                    ->action(function (Collection $records, array $data) use ($editable) {
                        $setting = $editable[$data['field']] ?? null;
                        if (! $setting || ! array_key_exists((string) $data['value'], $setting['options'])) {
                            return;
                        }

                        $value = match (true) {
                            $data['value'] === '__null' => null,
                            $setting['options'] === ['1' => 'Da', '0' => 'Nu'] => (bool) (int) $data['value'],
                            default => $data['value'],
                        };

                        $changed = 0;
                        foreach ($records as $record) {
                            $record->setAttribute($setting['attr'], $value);
                            if ($record->isDirty($setting['attr'])) {
                                $record->save();
                                $changed++;
                            }
                        }

                        Notification::make()
                            ->title("{$setting['label']}: {$setting['options'][(string) $data['value']]}")
                            ->body("Modificat la {$changed} din {$records->count()} rânduri selectate.")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])->label('Acțiuni pe selecție'),
        ];
    }

    /** Textul scurt de sub titlu. */
    public function introText(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => $this->currentReport() !== null)
                ->action(fn () => $this->exportCsv()),

            Action::make('editReport')
                ->label('Modifică raportul')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn () => $this->currentReport() !== null)
                ->modalHeading('Modifică raportul')
                ->modalWidth('5xl')
                ->fillForm(fn () => $this->reportFormData($this->currentReport()))
                ->schema(fn () => $this->reportFormSchema())
                ->action(function (array $data) {
                    $report = $this->currentReport();
                    if (! $report) {
                        return;
                    }
                    $report->update([
                        'name' => trim($data['name']),
                        'columns' => $this->columnsFromFormData($data),
                    ]);
                    Notification::make()->title('Raport salvat')->success()->send();
                    $this->redirect($this->reportUrl($report->id));
                }),

            Action::make('newReport')
                ->label('Raport nou')
                ->icon('heroicon-o-plus')
                ->modalHeading('Raport nou')
                ->modalWidth('5xl')
                ->fillForm(fn () => $this->reportFormData(null))
                ->schema(fn () => $this->reportFormSchema())
                ->action(function (array $data) {
                    $clientId = static::getMarketplaceClient()?->id;
                    if (! $clientId) {
                        return;
                    }
                    $admin = Auth::guard('marketplace_admin')->user();
                    $report = MarketplaceSavedReport::create([
                        'marketplace_client_id' => $clientId,
                        'entity' => static::entity(),
                        'name' => trim($data['name']),
                        'columns' => $this->columnsFromFormData($data),
                        'sort_order' => (int) $this->savedReports()->max('sort_order') + 1,
                        'created_by' => $admin?->getAuthIdentifier(),
                        'created_by_name' => $admin?->name ?? $admin?->email,
                    ]);
                    Notification::make()->title('Raport creat')->success()->send();
                    $this->redirect($this->reportUrl($report->id));
                }),

            Action::make('deleteReport')
                ->label('Șterge raportul')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => $this->currentReport() !== null)
                ->requiresConfirmation()
                ->modalHeading(fn () => 'Ștergi raportul „'.$this->currentReport()?->name.'”?')
                ->modalDescription('Se șterge doar configurația raportului, datele nu sunt atinse. Raportul dispare pentru toți adminii.')
                ->action(function () {
                    $this->currentReport()?->delete();
                    Notification::make()->title('Raport șters')->success()->send();
                    $this->redirect(static::getUrl());
                }),
        ];
    }

    protected function reportFormSchema(): array
    {
        $groups = [];
        foreach ($this->columnCatalog() as $key => $def) {
            if ($key === $this->titleColumnKey()) {
                continue;
            }
            $groups[$def['group'] ?? 'Altele'][$key] = $def['label'];
        }

        $schema = [
            TextInput::make('name')
                ->label('Numele raportului')
                ->required()
                ->maxLength(120),
        ];

        foreach ($groups as $group => $options) {
            $schema[] = Section::make($group)
                ->collapsible()
                ->compact()
                ->schema([
                    CheckboxList::make('cols.'.Str::slug($group, '_'))
                        ->hiddenLabel()
                        ->options($options)
                        ->columns(3)
                        ->bulkToggleable(),
                ]);
        }

        return $schema;
    }

    protected function reportFormData(?MarketplaceSavedReport $report): array
    {
        $chosen = (array) ($report?->columns ?? []);
        $data = ['name' => $report?->name ?? '', 'cols' => []];

        foreach ($this->columnCatalog() as $key => $def) {
            if ($key === $this->titleColumnKey()) {
                continue;
            }
            $slug = Str::slug($def['group'] ?? 'Altele', '_');
            $data['cols'][$slug] ??= [];
            if (in_array($key, $chosen, true)) {
                $data['cols'][$slug][] = $key;
            }
        }

        return $data;
    }

    protected function columnsFromFormData(array $data): array
    {
        $picked = collect($data['cols'] ?? [])->flatten()->filter()->all();

        return array_values(array_filter(
            array_keys($this->columnCatalog()),
            fn ($key) => in_array($key, $picked, true)
        ));
    }

    public function exportCsv(): StreamedResponse
    {
        $catalog = $this->columnCatalog();
        $keys = array_merge([$this->titleColumnKey()], $this->selectedColumnKeys());
        $query = $this->getFilteredSortedTableQuery() ?? $this->reportQuery();

        $filename = Str::slug($this->exportBaseName().' '.($this->currentReport()?->name ?? '')).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($catalog, $keys, $query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(array_map(fn ($k) => $catalog[$k]['label'], $keys), ['Link']), ';', '"', '\\');

            $query->chunk(500, function ($records) use ($out, $catalog, $keys) {
                foreach ($records as $record) {
                    $row = [];
                    foreach ($keys as $key) {
                        $row[] = (string) ($this->formatValue($catalog[$key], $this->rawState($key, $catalog[$key], $record), $record) ?? '');
                    }
                    $row[] = $this->editUrl($record);
                    fputcsv($out, $row, ';', '"', '\\');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function rawState(string $key, array $def, Model $record)
    {
        if (isset($def['state'])) {
            return ($def['state'])($record);
        }
        if (! empty($def['select'])) {
            return $record->getAttribute($key);
        }

        return $record->getAttribute($def['attr'] ?? $key);
    }
}
