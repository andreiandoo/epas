<?php

namespace App\Filament\Resources\WebTemplates;

use App\Enums\WebTemplateCategory;
use App\Models\WebTemplate;
use App\Models\WebTemplateCustomization;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;

class WebTemplateCustomizationResource extends Resource
{
    protected static ?string $model = WebTemplateCustomization::class;

    protected static UnitEnum|string|null $navigationGroup = 'Web Templates';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Personalizări';
    protected static ?string $modelLabel = 'Personalizare';
    protected static ?string $pluralModelLabel = 'Personalizări';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Selectare Template')
                ->schema([
                    Forms\Components\Select::make('web_template_id')
                        ->label('Template')
                        ->relationship('template', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            if (!$state) return;
                            $template = WebTemplate::find($state);
                            if ($template) {
                                $set('_template_category', $template->category->value);
                            }
                        }),

                    Forms\Components\TextInput::make('label')
                        ->label('Denumire Personalizare')
                        ->placeholder('ex: Demo pentru Teatrul Național')
                        ->maxLength(255),

                    Forms\Components\Select::make('tenant_id')
                        ->label('Tenant (opțional)')
                        ->relationship('tenant', 'public_name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'draft' => 'Draft',
                            'active' => 'Activ',
                            'expired' => 'Expirat',
                        ])
                        ->default('active')
                        ->required(),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expiră la')
                        ->nullable(),
                ])
                ->columns(2),

            SC\Section::make('Date de Personalizare')
                ->description('Adaugă datele personalizate ale clientului (logo, nume, culori, etc.)')
                ->schema([
                    Forms\Components\KeyValue::make('customization_data')
                        ->label('Personalizări')
                        ->keyLabel('Cheie')
                        ->valueLabel('Valoare')
                        ->addActionLabel('Adaugă personalizare')
                        ->default([]),
                ]),

            SC\Section::make('Override-uri Date Demo')
                ->description('Modificări peste datele demo default ale template-ului')
                ->schema([
                    Forms\Components\KeyValue::make('demo_data_overrides')
                        ->label('Override-uri')
                        ->keyLabel('Cheie')
                        ->valueLabel('Valoare')
                        ->addActionLabel('Adaugă override'),
                ])
                ->collapsed(),

            SC\Section::make('Link Preview')
                ->schema([
                    Forms\Components\Placeholder::make('unique_token_display')
                        ->label('Token Unic')
                        ->content(fn (?WebTemplateCustomization $record) => $record?->unique_token ?? 'Se generează automat la salvare'),

                    Forms\Components\Placeholder::make('preview_link')
                        ->label('Link Preview')
                        ->content(function (?WebTemplateCustomization $record) {
                            if (!$record || !$record->template) {
                                return 'Salvează pentru a genera link-ul de preview';
                            }
                            $url = route('web-template.customized-preview', [
                                'templateSlug' => $record->template->slug,
                                'token' => $record->unique_token,
                            ]);
                            return new \Illuminate\Support\HtmlString(
                                "<a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 hover:underline\">{$url}</a>"
                            );
                        }),

                    Forms\Components\Placeholder::make('demo_url_display')
                        ->label('URL Demo Public')
                        ->content(function (?WebTemplateCustomization $record) {
                            if (!$record || !$record->template) {
                                return 'Salvează pentru a genera URL-ul demo';
                            }
                            $url = $record->getPreviewUrl();
                            return new \Illuminate\Support\HtmlString(
                                "<span class=\"text-sm text-gray-500\">{$url}</span>"
                            );
                        }),

                    Forms\Components\Placeholder::make('view_stats')
                        ->label('Statistici')
                        ->content(function (?WebTemplateCustomization $record) {
                            if (!$record) return '-';
                            $lastViewed = $record->last_viewed_at
                                ? $record->last_viewed_at->diffForHumans()
                                : 'niciodată';
                            return "{$record->viewed_count} vizualizări · Ultima: {$lastViewed}";
                        }),
                ])
                ->visible(fn (?WebTemplateCustomization $record) => $record !== null),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('template.name')
                    ->label('Template')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('template.category')
                    ->label('Categorie')
                    ->badge()
                    ->formatStateUsing(fn (WebTemplateCategory $state) => $state->label())
                    ->color(fn (WebTemplateCategory $state) => match ($state) {
                        WebTemplateCategory::SimpleOrganizer => 'info',
                        WebTemplateCategory::Marketplace => 'success',
                        WebTemplateCategory::ArtistAgency => 'warning',
                        WebTemplateCategory::Theater => 'danger',
                        WebTemplateCategory::Festival => 'primary',
                        WebTemplateCategory::Stadium => 'gray',
                    }),

                Tables\Columns\TextColumn::make('label')
                    ->label('Denumire')
                    ->searchable()
                    ->placeholder('(fără denumire)'),

                Tables\Columns\TextColumn::make('unique_token')
                    ->label('Token')
                    ->badge()
                    ->color('gray')
                    ->copyable(),

                Tables\Columns\TextColumn::make('tenant.public_name')
                    ->label('Tenant')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'draft' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('viewed_count')
                    ->label('Vizualizări')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creat')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('web_template_id')
                    ->label('Template')
                    ->relationship('template', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Activ',
                        'expired' => 'Expirat',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('openPreview')
                        ->label('Deschide Preview')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (WebTemplateCustomization $record) => route('web-template.customized-preview', [
                            'templateSlug' => $record->template->slug,
                            'token' => $record->unique_token,
                        ]))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('copyLink')
                        ->label('Copiază Link')
                        ->icon('heroicon-o-clipboard-document')
                        ->action(function (WebTemplateCustomization $record) {
                            // Handled via JS in frontend
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebTemplateCustomizations::route('/'),
            'create' => Pages\CreateWebTemplateCustomization::route('/create'),
            'edit' => Pages\EditWebTemplateCustomization::route('/{record}/edit'),
        ];
    }
}
