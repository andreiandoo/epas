<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\SupportProblemTypeResource\Pages;
use App\Models\SupportDepartment;
use App\Models\SupportProblemType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupportProblemTypeResource extends Resource
{
    protected static ?string $model = SupportProblemType::class;
    protected static ?string $navigationLabel = 'Tipuri probleme';
    protected static ?string $modelLabel = 'tip problemă';
    protected static ?string $pluralModelLabel = 'tipuri probleme';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-list-bullet';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 81;

    public static function getEloquentQuery(): Builder
    {
        $admin = Auth::guard('marketplace_admin')->user();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $admin?->marketplace_client_id);
    }

    public static function form(Schema $form): Schema
    {
        return $form->components([
            Section::make()->schema([
                Forms\Components\Select::make('support_department_id')
                    ->label('Departament')
                    ->options(fn () => SupportDepartment::query()
                        ->where('marketplace_client_id', Auth::guard('marketplace_admin')->user()?->marketplace_client_id)
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn ($d) => [$d->id => $d->getTranslation('name', 'ro') ?: $d->slug])
                        ->all())
                    ->required(),

                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(64),

                Forms\Components\KeyValue::make('name')
                    ->label('Nume (per limbă)')
                    ->keyLabel('Cod limbă')
                    ->valueLabel('Denumire')
                    ->keyPlaceholder('ro')
                    ->required(),

                Forms\Components\KeyValue::make('description')
                    ->label('Descriere (per limbă)')
                    ->keyLabel('Cod limbă')
                    ->valueLabel('Descriere'),

                Forms\Components\CheckboxList::make('required_fields')
                    ->label('Câmpuri obligatorii pe formular')
                    ->options([
                        'url' => 'URL pagină',
                        'invoice_series' => 'Seria decont',
                        'invoice_number' => 'Număr decont',
                        'event_id' => 'Selector eveniment',
                        'module_name' => 'Nume modul',
                    ])
                    ->columns(2)
                    ->helperText('Câmpurile selectate apar dinamic pe formularul organizatorului și sunt validate ca obligatorii.'),

                Forms\Components\CheckboxList::make('allowed_opener_types')
                    ->label('Vizibil pentru')
                    ->options([
                        'organizer' => 'Organizatori',
                        'customer' => 'Cumpărători (viitor)',
                    ])
                    ->columns(2)
                    ->default(['organizer', 'customer']),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->default(0),

                Forms\Components\Toggle::make('is_active')
                    ->label('Activ')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with('department'))
            ->columns([
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departament')
                    ->state(fn (SupportProblemType $r) => $r->department?->getTranslation('name', 'ro') ?? '—')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->state(fn (SupportProblemType $r) => $r->getTranslation('name', 'ro') ?: $r->slug),
                Tables\Columns\TextColumn::make('slug')->fontFamily('mono')->size('xs'),
                Tables\Columns\TextColumn::make('required_fields')
                    ->label('Câmpuri')
                    ->state(fn (SupportProblemType $r) => empty($r->required_fields) ? '—' : implode(', ', $r->required_fields))
                    ->size('xs'),
                Tables\Columns\TextColumn::make('allowed_opener_types')
                    ->label('Vizibil')
                    ->state(fn (SupportProblemType $r) => empty($r->allowed_opener_types) ? 'toți' : implode(', ', $r->allowed_opener_types))
                    ->size('xs'),
                Tables\Columns\IconColumn::make('is_active')->label('Activ')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('support_department_id')
                    ->label('Departament')
                    ->options(fn () => SupportDepartment::query()
                        ->where('marketplace_client_id', Auth::guard('marketplace_admin')->user()?->marketplace_client_id)
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn ($d) => [$d->id => $d->getTranslation('name', 'ro') ?: $d->slug])
                        ->all()),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportProblemTypes::route('/'),
            'create' => Pages\CreateSupportProblemType::route('/create'),
            'edit' => Pages\EditSupportProblemType::route('/{record}/edit'),
        ];
    }
}
