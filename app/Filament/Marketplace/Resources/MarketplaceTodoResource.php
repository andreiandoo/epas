<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\MarketplaceTodoResource\Pages;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceTodo;
use App\Models\MarketplaceTodoCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MarketplaceTodoResource extends Resource
{
    protected static ?string $model = MarketplaceTodo::class;

    protected static ?string $navigationLabel = 'TODOs';
    protected static ?string $modelLabel = 'TODO';
    protected static ?string $pluralModelLabel = 'TODOs';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';
    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationBadge(): ?string
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (!$admin) return null;
        $count = static::getEloquentQuery()
            ->whereNotIn('status', [MarketplaceTodo::STATUS_RESOLVED, MarketplaceTodo::STATUS_CLOSED])
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $admin = Auth::guard('marketplace_admin')->user();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $admin?->marketplace_client_id)
            ->with(['creator', 'assignee', 'category']);
    }

    public static function form(Schema $form): Schema
    {
        return $form->components([
            Section::make('Detalii TODO')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Titlu')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('marketplace_todo_category_id')
                        ->label('Categorie problemă')
                        ->options(fn () => MarketplaceTodoCategory::query()
                            ->where('marketplace_client_id', Auth::guard('marketplace_admin')->user()?->marketplace_client_id)
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($c) => [$c->id => $c->name])
                            ->all())
                        ->searchable()
                        ->nullable(),

                    Forms\Components\Select::make('priority')
                        ->label('Prioritate')
                        ->options(MarketplaceTodo::PRIORITY_LABELS)
                        ->required()
                        ->default('normal'),

                    Forms\Components\Select::make('assigned_to_marketplace_admin_id')
                        ->label('Asignat la')
                        ->helperText('Implicit: admin-ul desemnat al marketplace-ului.')
                        ->options(fn () => MarketplaceAdmin::query()
                            ->where('marketplace_client_id', Auth::guard('marketplace_admin')->user()?->marketplace_client_id)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($u) => [$u->id => $u->name . ' — ' . $u->email])
                            ->all())
                        ->searchable()
                        ->nullable()
                        ->default(fn () => Auth::guard('marketplace_admin')->user()?->marketplaceClient?->default_todo_admin_id),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(MarketplaceTodo::STATUS_LABELS)
                        ->required()
                        ->default('open')
                        ->visibleOn('edit'),

                    Forms\Components\RichEditor::make('description')
                        ->label('Descriere')
                        ->toolbarButtons(['bold', 'italic', 'underline', 'strike', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'undo', 'redo'])
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('attachments')
                        ->label('Imagini (drag & drop)')
                        ->helperText('Imagini jpg/png/webp, max 5 MB per fișier.')
                        ->multiple()
                        ->image()
                        ->imageEditor()
                        ->reorderable()
                        ->openable()
                        ->downloadable()
                        ->maxFiles(20)
                        ->maxSize(5120)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->directory(fn () => 'marketplace-todos/' . (Auth::guard('marketplace_admin')->user()?->marketplace_client_id ?? 0))
                        ->disk('public')
                        ->visibility('public')
                        ->preserveFilenames()
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('todo_number')
                    ->label('Nr.')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->limit(60),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categorie')
                    ->badge()
                    ->color(fn ($record) => $record->category?->color ?: 'gray')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creat de')
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Asignat')
                    ->placeholder('— nealocat —')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioritate')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'normal' => 'gray',
                        'low' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => MarketplaceTodo::PRIORITY_LABELS[$state] ?? $state),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'info',
                        'in_progress' => 'warning',
                        'awaiting_response' => 'success',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => MarketplaceTodo::STATUS_LABELS[$state] ?? $state),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Deschis')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Activitate')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Închis')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_activity_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace_todo_category_id')
                    ->label('Categorie')
                    ->options(fn () => MarketplaceTodoCategory::query()
                        ->where('marketplace_client_id', Auth::guard('marketplace_admin')->user()?->marketplace_client_id)
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn ($c) => [$c->id => $c->name])
                        ->all()),

                Tables\Filters\SelectFilter::make('status')
                    ->options(MarketplaceTodo::STATUS_LABELS),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioritate')
                    ->options(MarketplaceTodo::PRIORITY_LABELS),

                Tables\Filters\SelectFilter::make('assigned_to_marketplace_admin_id')
                    ->label('Asignat')
                    ->options(fn () => MarketplaceAdmin::query()
                        ->where('marketplace_client_id', Auth::guard('marketplace_admin')->user()?->marketplace_client_id)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($u) => [$u->id => $u->name])
                        ->all()),
            ])
            ->recordUrl(fn (MarketplaceTodo $record) => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('close')
                        ->label('Închide selectate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update([
                            'status' => MarketplaceTodo::STATUS_CLOSED,
                            'closed_at' => now(),
                            'last_activity_at' => now(),
                        ])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketplaceTodos::route('/'),
            'create' => Pages\CreateMarketplaceTodo::route('/create'),
            'view' => Pages\ViewMarketplaceTodo::route('/{record}'),
            'edit' => Pages\EditMarketplaceTodo::route('/{record}/edit'),
        ];
    }
}
