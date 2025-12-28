<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ShopReviewResource\Pages;
use App\Models\Shop\ShopReview;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class ShopReviewResource extends Resource
{
    protected static ?string $model = ShopReview::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Reviews';

    protected static ?string $navigationParentItem = 'Shop';

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Review';

    protected static ?string $pluralModelLabel = 'Product Reviews';

    protected static ?string $slug = 'shop-reviews';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // This is tenant-specific, not applicable to marketplace panel
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $schema
            ->components([
                SC\Section::make('Review Details')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('product')
                            ->label('Product')
                            ->content(fn ($record) => $record->product?->title[$tenantLanguage] ?? 'Unknown'),

                        Forms\Components\Placeholder::make('rating')
                            ->label('Rating')
                            ->content(fn ($record) => new HtmlString(
                                str_repeat('⭐', $record->rating) .
                                str_repeat('☆', 5 - $record->rating) .
                                " ({$record->rating}/5)"
                            )),

                        Forms\Components\Placeholder::make('status')
                            ->label('Status')
                            ->content(fn ($record) => new HtmlString('<span class="px-2 py-1 rounded text-sm font-medium ' . match ($record->status) {
                                'approved' => 'bg-success-100 text-success-700',
                                'pending' => 'bg-warning-100 text-warning-700',
                                'rejected' => 'bg-danger-100 text-danger-700',
                                default => 'bg-gray-100 text-gray-700',
                            } . '">' . ucfirst($record->status) . '</span>')),
                    ]),

                SC\Section::make('Reviewer')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('reviewer_name')
                            ->label('Name')
                            ->content(fn ($record) => $record->reviewer_name ?? 'Anonymous'),

                        Forms\Components\Placeholder::make('reviewer_email')
                            ->label('Email')
                            ->content(fn ($record) => $record->reviewer_email ?? 'N/A'),

                        Forms\Components\Placeholder::make('verified_purchase')
                            ->label('Verified Purchase')
                            ->content(fn ($record) => $record->verified_purchase ? 'Yes' : 'No'),
                    ]),

                SC\Section::make('Review Content')
                    ->schema([
                        Forms\Components\Placeholder::make('title')
                            ->label('Title')
                            ->content(fn ($record) => $record->title ?? 'No title'),

                        Forms\Components\Placeholder::make('content')
                            ->label('Content')
                            ->content(fn ($record) => $record->content),
                    ]),

                SC\Section::make('Admin Response')
                    ->visible(fn ($record) => !empty($record->admin_response))
                    ->schema([
                        Forms\Components\Placeholder::make('admin_response')
                            ->label('')
                            ->content(fn ($record) => $record->admin_response),

                        Forms\Components\Placeholder::make('responded_at')
                            ->label('Responded At')
                            ->content(fn ($record) => $record->responded_at?->format('d M Y H:i')),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Moderation')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('admin_response')
                            ->label('Admin Response')
                            ->rows(3)
                            ->placeholder('Respond to this review (visible to public)'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.title')
                    ->label('Product')
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[$tenantLanguage] ?? 'Unknown') : $state)
                    ->limit(25)
                    ->searchable(),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => str_repeat('⭐', $state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviewer_name')
                    ->label('Reviewer')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->limit(30)
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('verified_purchase')
                    ->label('Verified')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'approved',
                        'warning' => 'pending',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\IconColumn::make('admin_response')
                    ->label('Response')
                    ->boolean()
                    ->getStateUsing(fn ($record) => !empty($record->admin_response))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('rating')
                    ->options([
                        1 => '1 Star',
                        2 => '2 Stars',
                        3 => '3 Stars',
                        4 => '4 Stars',
                        5 => '5 Stars',
                    ]),
                Tables\Filters\TernaryFilter::make('verified_purchase')
                    ->label('Verified Purchase'),
            ])
            ->actions([
                ViewAction::make(),
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'approved')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'approved']);
                        $record->product?->updateReviewStats();
                    }),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status !== 'rejected')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'rejected']);
                        $record->product?->updateReviewStats();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                $record->update(['status' => 'approved']);
                                $record->product?->updateReviewStats();
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject')
                        ->label('Reject Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                $record->update(['status' => 'rejected']);
                                $record->product?->updateReviewStats();
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopReviews::route('/'),
            'view' => Pages\ViewShopReview::route('/{record}'),
            'edit' => Pages\EditShopReview::route('/{record}/edit'),
        ];
    }
}
