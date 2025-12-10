<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\TicketTemplateResource\Pages;
use App\Models\TicketTemplate;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class TicketTemplateResource extends Resource
{
    protected static ?string $model = TicketTemplate::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Ticket Templates';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Template';

    protected static ?string $pluralModelLabel = 'Ticket Templates';

    protected static ?string $slug = 'ticket-templates';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) return false;

        return $tenant->microservices()
            ->where('slug', 'ticket-customizer')
            ->wherePivot('is_active', true)
            ->exists();
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;

        return $schema
            ->schema([
                Forms\Components\Hidden::make('tenant_id')
                    ->default($tenant?->id),

                SC\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Set as Default')
                            ->helperText('Make this the default template'),
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

                SC\Section::make('Preview')
                    ->schema([
                        Forms\Components\Placeholder::make('preview_display')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="text-gray-500 text-sm">Save the template first to see preview.</div>'
                                    );
                                }
                                if (!$record->preview_image) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="text-gray-500 text-sm">No preview yet. Use the Visual Editor to design your ticket.</div>'
                                    );
                                }
                                $url = \Illuminate\Support\Facades\Storage::disk('public')->url($record->preview_image);
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="border rounded-lg p-2 bg-gray-50 inline-block">
                                        <img src="' . $url . '" class="max-h-64 max-w-full" alt="Template Preview" />
                                    </div>'
                                );
                            }),

                        Forms\Components\Placeholder::make('editor_link')
                            ->label('')
                            ->content(fn ($record) => $record
                                ? new \Illuminate\Support\HtmlString(
                                    '<a href="/tenant/ticket-customizer/' . $record->id . '/editor"
                                       target="_blank"
                                       class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium transition">
                                       <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                       Open Visual Editor
                                    </a>'
                                )
                                : new \Illuminate\Support\HtmlString(
                                    '<span class="text-gray-500">Save the template first to access the visual editor</span>'
                                )
                            ),
                    ])->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview_image')
                    ->label('Preview')
                    ->square()
                    ->size(60),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'active',
                        'gray' => 'archived',
                    ]),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\TextColumn::make('size_display')
                    ->label('Size')
                    ->getStateUsing(function ($record) {
                        $meta = $record->template_data['meta'] ?? [];
                        $w = $meta['size_mm']['w'] ?? '?';
                        $h = $meta['size_mm']['h'] ?? '?';
                        return "{$w}×{$h}mm";
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modified')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Actions\Action::make('editor')
                    ->label('Visual Editor')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->url(fn ($record) => "/tenant/ticket-customizer/{$record->id}/editor")
                    ->openUrlInNewTab(),
                Actions\Action::make('set_default')
                    ->label('Set Default')
                    ->icon('heroicon-o-star')
                    ->color('gray')
                    ->visible(fn ($record) => !$record->is_default)
                    ->action(fn ($record) => $record->setAsDefault())
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketTemplates::route('/'),
            'create' => Pages\CreateTicketTemplate::route('/create'),
            'edit' => Pages\EditTicketTemplate::route('/{record}/edit'),
        ];
    }
}
