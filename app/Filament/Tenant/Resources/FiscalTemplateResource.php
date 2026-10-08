<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\FiscalTemplateResource\Pages;
use App\Models\TenantTaxTemplate;
use App\Services\Tenant\TenantFiscalDocuments;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Șabloanele documentelor fiscale ale tenantului (cerere de vizare, impozit pe spectacole, PV de distrugere).
 * Sunt create de echipa Tixello (tenant:copy-fiscal-templates); tenantul le poate doar ajusta textul.
 */
class FiscalTemplateResource extends Resource
{
    protected static ?string $model = TenantTaxTemplate::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 60;
    protected static ?string $navigationLabel = 'Șabloane documente';
    protected static ?string $modelLabel = 'Șablon de document';
    protected static ?string $pluralModelLabel = 'Șabloane de documente';
    protected static ?string $slug = 'fiscal-templates';

    public static function shouldRegisterNavigation(): bool
    {
        if (! TenantFiscalDocuments::available()) {
            return false;
        }
        $tenant = auth()->user()?->tenant;

        return $tenant ? TenantTaxTemplate::where('tenant_id', $tenant->id)->exists() : false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()?->tenant;

        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Șablon')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Nume')->required()->maxLength(190),
                    Forms\Components\Select::make('page_orientation')
                        ->label('Orientare pagină')
                        ->options(['portrait' => 'Portret', 'landscape' => 'Peisaj'])
                        ->required(),
                    Forms\Components\Toggle::make('is_active')->label('Activ')->inline(false),
                    Forms\Components\Placeholder::make('how')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(new HtmlString(
                            '<div style="font-size:13px;line-height:1.5;opacity:.85">Textul e în format HTML. Valorile dintre acolade duble, de exemplu '
                            . '<code>{{ event_name }}</code>, <code>{{ organizer_company_name }}</code> sau <code>{{ ticket_types_rows }}</code>, sunt completate '
                            . 'automat la generare cu datele evenimentului, ale firmei tale și ale direcției fiscale: nu le șterge și nu le rescrie. '
                            . 'Poți modifica liber restul textului.</div>'
                        )),
                ]),

            SC\Section::make('Conținut')
                ->schema([
                    Forms\Components\Textarea::make('html_content')
                        ->label('Pagina 1 (HTML)')
                        ->rows(26)
                        ->extraInputAttributes(['style' => 'font-family:ui-monospace,Consolas,monospace;font-size:12px;', 'spellcheck' => 'false']),
                    Forms\Components\Textarea::make('html_content_page_2')
                        ->label('Pagina 2 (HTML, opțional)')
                        ->rows(14)
                        ->extraInputAttributes(['style' => 'font-family:ui-monospace,Consolas,monospace;font-size:12px;', 'spellcheck' => 'false']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Document')->weight('bold'),
                Tables\Columns\TextColumn::make('page_orientation')
                    ->label('Orientare')
                    ->formatStateUsing(fn ($state) => $state === 'landscape' ? 'Peisaj' : 'Portret'),
                Tables\Columns\IconColumn::make('is_active')->label('Activ')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('Modificat')->dateTime('d.m.Y H:i'),
            ])
            ->recordActions([EditAction::make()->label('Editează')])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiscalTemplates::route('/'),
            'edit'  => Pages\EditFiscalTemplate::route('/{record}/edit'),
        ];
    }
}
