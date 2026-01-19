<?php

namespace App\Filament\Resources\TicketTemplates;

use App\Models\TicketTemplate;
use App\Services\TicketCustomizer\TicketVariableService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use BackedEnum;
use UnitEnum;

class TicketTemplateResource extends Resource
{
    protected static ?string $model = TicketTemplate::class;

    protected static UnitEnum|string|null $navigationGroup = 'Design';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Ticket Templates';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Basic Information')
                ->description('Template name and details')
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->label('Tenant')
                        ->relationship('tenant', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->helperText('Select the tenant for this template'),

                    Forms\Components\TextInput::make('name')
                        ->label('Template Name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Display name for this ticket template'),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->maxLength(1000)
                        ->rows(3)
                        ->helperText('Optional description of this template')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'draft' => 'Draft',
                            'active' => 'Active',
                            'archived' => 'Archived',
                        ])
                        ->default('draft')
                        ->required()
                        ->helperText('Template status'),

                    Forms\Components\Toggle::make('is_default')
                        ->label('Set as Default')
                        ->helperText('Make this the default template for the tenant')
                        ->default(false),
                ])->columns(2),

            SC\Section::make('Template Design')
                ->description('Canvas settings and dimensions')
                ->schema([
                    Forms\Components\Select::make('template_data.meta.orientation')
                        ->label('Orientation')
                        ->options([
                            'portrait' => 'Portrait',
                            'landscape' => 'Landscape',
                        ])
                        ->default('landscape')
                        ->required()
                        ->live(),

                    Forms\Components\TextInput::make('template_data.meta.dpi')
                        ->label('DPI')
                        ->numeric()
                        ->default(300)
                        ->required()
                        ->minValue(72)
                        ->maxValue(600)
                        ->helperText('Print resolution (300 DPI recommended)'),

                    Forms\Components\TextInput::make('template_data.meta.size_mm.w')
                        ->label('Width (mm)')
                        ->numeric()
                        ->default(200)
                        ->required()
                        ->minValue(10)
                        ->helperText('Canvas width in millimeters'),

                    Forms\Components\TextInput::make('template_data.meta.size_mm.h')
                        ->label('Height (mm)')
                        ->numeric()
                        ->default(100)
                        ->required()
                        ->minValue(10)
                        ->helperText('Canvas height in millimeters'),

                    Forms\Components\TextInput::make('template_data.meta.bleed_mm')
                        ->label('Bleed (mm)')
                        ->numeric()
                        ->default(3)
                        ->minValue(0)
                        ->helperText('Print bleed area in millimeters'),

                    Forms\Components\TextInput::make('template_data.meta.safe_area_mm')
                        ->label('Safe Area (mm)')
                        ->numeric()
                        ->default(5)
                        ->minValue(0)
                        ->helperText('Safe area margin in millimeters'),
                ])->columns(3),

            SC\Section::make('Template JSON')
                ->description('Advanced: Edit template JSON directly (use WYSIWYG editor for visual design)')
                ->schema([
                    Forms\Components\Textarea::make('template_json_editor')
                        ->label('Template JSON')
                        ->rows(20)
                        ->columnSpanFull()
                        ->helperText('Direct JSON editing - advanced users only. Use the React WYSIWYG editor for visual design.')
                        ->formatStateUsing(function ($record) {
                            if ($record && $record->template_data) {
                                return json_encode($record->template_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            }
                            return '';
                        })
                        ->dehydrated(false),

                    Forms\Components\Placeholder::make('wysiwyg_editor_link')
                        ->label('')
                        ->content(fn ($record) => $record
                            ? new \Illuminate\Support\HtmlString(
                                '<a href="/admin/ticket-customizer/' . $record->id . '"
                                   target="_blank"
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                                   <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                   Open Visual Editor
                                </a>'
                            )
                            : 'Save the template first to access the visual editor'
                        ),
                ]),

            SC\Section::make('Preview')
                ->description('Template preview image')
                ->schema([
                    Forms\Components\Placeholder::make('preview_display')
                        ->label('Preview')
                        ->content(function ($record) {
                            if (!$record || !$record->preview_image) {
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="text-gray-500 text-sm">No preview generated yet. Use the Visual Editor or click "Generate Preview" button.</div>'
                                );
                            }
                            $url = \Illuminate\Support\Facades\Storage::disk('public')->url($record->preview_image);
                            return new \Illuminate\Support\HtmlString(
                                '<div class="border rounded-lg p-2 bg-gray-50 inline-block">
                                    <object data="' . $url . '" type="image/svg+xml" class="max-h-96 max-w-full">
                                        <img src="' . $url . '" class="max-h-96 max-w-full" alt="Template Preview" />
                                    </object>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Last updated: ' . ($record->updated_at?->format('Y-m-d H:i:s') ?? 'Unknown') . '</p>'
                            );
                        })
                        ->columnSpanFull(),
                ]),

            SC\Section::make('Metadata')
                ->description('Version and tracking information')
                ->schema([
                    Forms\Components\TextInput::make('version')
                        ->label('Version')
                        ->numeric()
                        ->disabled()
                        ->helperText('Template version number'),

                    Forms\Components\Select::make('parent_id')
                        ->label('Parent Template')
                        ->relationship('parent', 'name')
                        ->searchable()
                        ->disabled()
                        ->helperText('Original template if this is a version'),

                    Forms\Components\Placeholder::make('created_at')
                        ->label('Created At')
                        ->content(fn ($record) => $record?->created_at?->format('Y-m-d H:i:s') ?? '-'),

                    Forms\Components\Placeholder::make('updated_at')
                        ->label('Updated At')
                        ->content(fn ($record) => $record?->updated_at?->format('Y-m-d H:i:s') ?? '-'),
                ])
                ->columns(2)
                ->visible(fn ($record) => $record !== null),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview_image')
                    ->label('Preview')
                    ->square()
                    ->defaultImageUrl(url('/images/ticket-placeholder.png')),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'active',
                        'secondary' => 'archived',
                    ])
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('size_display')
                    ->label('Size')
                    ->getStateUsing(function ($record) {
                        $meta = $record->template_data['meta'] ?? [];
                        $w = $meta['size_mm']['w'] ?? '?';
                        $h = $meta['size_mm']['h'] ?? '?';
                        $dpi = $meta['dpi'] ?? '?';
                        return "{$w}×{$h}mm @ {$dpi}DPI";
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ]),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default Template')
                    ->placeholder('All templates')
                    ->trueLabel('Default only')
                    ->falseLabel('Non-default only'),

                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('id', 'desc'); // Using id instead of updated_at to avoid MySQL sort buffer memory issues
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketTemplates::route('/'),
            'create' => Pages\CreateTicketTemplate::route('/create'),
            'edit' => Pages\EditTicketTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'active')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
