<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\SupportDepartmentResource\Pages;
use App\Models\MarketplaceAdmin;
use App\Models\SupportDepartment;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupportDepartmentResource extends Resource
{
    protected static ?string $model = SupportDepartment::class;
    protected static ?string $navigationLabel = 'Departamente suport';
    protected static ?string $modelLabel = 'departament';
    protected static ?string $pluralModelLabel = 'departamente';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 80;

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
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(64)
                    ->helperText('Identificator stabil; nu schimba după ce ai tichete care folosesc departamentul.'),

                Forms\Components\KeyValue::make('name')
                    ->label('Nume (per limbă)')
                    ->keyLabel('Cod limbă')
                    ->valueLabel('Denumire')
                    ->keyPlaceholder('ro')
                    ->valuePlaceholder('Tehnic')
                    ->required(),

                Forms\Components\KeyValue::make('description')
                    ->label('Descriere (per limbă)')
                    ->keyLabel('Cod limbă')
                    ->valueLabel('Descriere'),

                Forms\Components\TagsInput::make('notify_emails')
                    ->label('Email-uri pentru notificare')
                    ->placeholder('suport@ambilet.ro')
                    ->helperText('Echipa care primește email când se deschide un tichet pe acest departament.'),

                Forms\Components\Select::make('admins')
                    ->label('Membri echipă alocați')
                    ->helperText('Acești utilizatori vor putea fi asignați pe tichetele care ajung pe acest departament.')
                    ->relationship(
                        'admins',
                        'name',
                        fn ($query) => $query->where(
                            'marketplace_client_id',
                            \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->user()?->marketplace_client_id
                        )->orderBy('name'),
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn (MarketplaceAdmin $admin) => $admin->name . ' — ' . $admin->email),

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
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->state(fn (SupportDepartment $r) => $r->getTranslation('name', 'ro') ?: $r->slug),
                Tables\Columns\TextColumn::make('slug')->fontFamily('mono')->size('xs'),
                Tables\Columns\TextColumn::make('problem_types_count')
                    ->label('Tipuri probleme')
                    ->counts('problemTypes'),
                Tables\Columns\IconColumn::make('is_active')->label('Activ')->boolean(),
            ])
            ->defaultSort('sort_order')
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
            'index' => Pages\ListSupportDepartments::route('/'),
            'create' => Pages\CreateSupportDepartment::route('/create'),
            'edit' => Pages\EditSupportDepartment::route('/{record}/edit'),
        ];
    }
}
