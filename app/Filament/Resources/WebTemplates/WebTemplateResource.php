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

class WebTemplateResource extends Resource
{
    protected static ?string $model = WebTemplate::class;

    protected static UnitEnum|string|null $navigationGroup = 'Web Templates';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Templates';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Tabs::make('Template')
                ->tabs([
                    SC\Tabs\Tab::make('Informații Generale')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            SC\Section::make('Detalii Template')
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Nume Template')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                            if (!$state) return;
                                            $set('slug', \Illuminate\Support\Str::slug($state));
                                        }),

                                    Forms\Components\TextInput::make('slug')
                                        ->label('Slug (URL)')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255),

                                    Forms\Components\Select::make('category')
                                        ->label('Categorie')
                                        ->options(WebTemplateCategory::class)
                                        ->required()
                                        ->searchable(),

                                    Forms\Components\Textarea::make('description')
                                        ->label('Descriere')
                                        ->rows(3)
                                        ->maxLength(1000),

                                    Forms\Components\TextInput::make('version')
                                        ->label('Versiune')
                                        ->default('1.0.0')
                                        ->maxLength(20),
                                ])
                                ->columns(2),

                            SC\Section::make('Vizibilitate')
                                ->schema([
                                    Forms\Components\Toggle::make('is_active')
                                        ->label('Activ')
                                        ->default(true),

                                    Forms\Components\Toggle::make('is_featured')
                                        ->label('Featured')
                                        ->default(false),

                                    Forms\Components\TextInput::make('sort_order')
                                        ->label('Ordine Sortare')
                                        ->numeric()
                                        ->default(0),
                                ])
                                ->columns(3),
                        ]),

                    SC\Tabs\Tab::make('Aspect & Resurse')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            SC\Section::make('Imagini')
                                ->schema([
                                    Forms\Components\FileUpload::make('thumbnail')
                                        ->label('Thumbnail (card)')
                                        ->image()
                                        ->directory('web-templates/thumbnails'),

                                    Forms\Components\FileUpload::make('preview_image')
                                        ->label('Preview (full)')
                                        ->image()
                                        ->directory('web-templates/previews'),
                                ])
                                ->columns(2),

                            SC\Section::make('Configurare Tehnică')
                                ->schema([
                                    Forms\Components\TextInput::make('html_template_path')
                                        ->label('Calea către template HTML')
                                        ->placeholder('templates/theater-classic/index.html'),

                                    Forms\Components\TagsInput::make('tech_stack')
                                        ->label('Tech Stack')
                                        ->placeholder('HTML, Alpine.js, Tailwind CSS')
                                        ->suggestions(['HTML', 'Alpine.js', 'Tailwind CSS', 'JavaScript', 'CSS3']),

                                    Forms\Components\TagsInput::make('compatible_microservices')
                                        ->label('Microservicii Compatibile')
                                        ->suggestions([
                                            'analytics', 'crm', 'shop', 'door-sales',
                                            'affiliate-tracking', 'efactura', 'accounting',
                                            'ticket-customizer', 'sms', 'whatsapp',
                                        ]),
                                ]),
                        ]),

                    SC\Tabs\Tab::make('Culori & Personalizare')
                        ->icon('heroicon-o-swatch')
                        ->schema([
                            SC\Section::make('Schema de Culori Default')
                                ->schema([
                                    Forms\Components\KeyValue::make('color_scheme')
                                        ->label('Culori')
                                        ->keyLabel('Variabilă')
                                        ->valueLabel('Valoare')
                                        ->addActionLabel('Adaugă culoare')
                                        ->default([
                                            'primary' => '#6366f1',
                                            'secondary' => '#8b5cf6',
                                            'accent' => '#f59e0b',
                                            'background' => '#ffffff',
                                            'text' => '#1f2937',
                                        ]),
                                ]),

                            SC\Section::make('Câmpuri Personalizabile')
                                ->description('Definește ce poate personaliza clientul')
                                ->schema([
                                    Forms\Components\Repeater::make('customizable_fields')
                                        ->label('Câmpuri')
                                        ->schema([
                                            Forms\Components\TextInput::make('key')
                                                ->label('Cheie')
                                                ->required(),
                                            Forms\Components\TextInput::make('label')
                                                ->label('Etichetă')
                                                ->required(),
                                            Forms\Components\Select::make('type')
                                                ->label('Tip')
                                                ->options([
                                                    'text' => 'Text',
                                                    'textarea' => 'Textarea',
                                                    'color' => 'Culoare',
                                                    'image' => 'Imagine',
                                                    'url' => 'URL',
                                                    'select' => 'Select',
                                                    'toggle' => 'Toggle',
                                                ])
                                                ->required(),
                                            Forms\Components\TextInput::make('default')
                                                ->label('Valoare Default'),
                                            Forms\Components\TextInput::make('group')
                                                ->label('Grup')
                                                ->placeholder('branding, contact, social'),
                                        ])
                                        ->columns(5)
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                                ]),
                        ]),

                    SC\Tabs\Tab::make('Date Demo')
                        ->icon('heroicon-o-circle-stack')
                        ->schema([
                            SC\Section::make('Date Demo Default')
                                ->description('JSON cu date demo care populează template-ul')
                                ->schema([
                                    Forms\Components\KeyValue::make('default_demo_data.site')
                                        ->label('Date Site')
                                        ->keyLabel('Cheie')
                                        ->valueLabel('Valoare')
                                        ->addActionLabel('Adaugă')
                                        ->default([
                                            'name' => 'Demo Tixello',
                                            'tagline' => 'Platforma ta de ticketing',
                                            'phone' => '+40 700 000 000',
                                            'email' => 'contact@demo.tixello.ro',
                                        ]),
                                ]),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->label('')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category')
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
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('version')
                    ->label('Versiune')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('customizations_count')
                    ->label('Personalizări')
                    ->counts('customizations')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activ')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizat')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categorie')
                    ->options(WebTemplateCategory::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activ'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('preview')
                        ->label('Preview Demo')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (WebTemplate $record) => route('web-template.preview', [
                            'templateSlug' => $record->slug,
                        ]))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('createCustomization')
                        ->label('Creează Personalizare')
                        ->icon('heroicon-o-sparkles')
                        ->color('success')
                        ->url(fn (WebTemplate $record) => WebTemplateCustomizationResource::getUrl('create', [
                            'template_id' => $record->id,
                        ])),
                    Tables\Actions\Action::make('clone')
                        ->label('Duplică Template')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Duplică Template')
                        ->modalDescription('Se va crea o copie a template-ului cu un slug nou. Personalizările nu vor fi copiate.')
                        ->action(function (WebTemplate $record) {
                            $clone = $record->replicate();
                            $clone->name = $record->name . ' (copie)';
                            $clone->slug = $record->slug . '-copie-' . now()->format('His');
                            $clone->is_featured = false;
                            $clone->save();

                            \Filament\Notifications\Notification::make()
                                ->title('Template duplicat')
                                ->body("„{$clone->name}" a fost creat cu succes.")
                                ->success()
                                ->send();

                            return redirect(static::getUrl('edit', ['record' => $clone]));
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CustomizationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebTemplates::route('/'),
            'create' => Pages\CreateWebTemplate::route('/create'),
            'edit' => Pages\EditWebTemplate::route('/{record}/edit'),
        ];
    }
}
