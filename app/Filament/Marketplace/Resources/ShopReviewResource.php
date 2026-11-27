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
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class ShopReviewResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = ShopReview::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Reviews';

    protected static ?string $navigationParentItem = 'Shop';

    protected static \UnitEnum|string|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Review';

    protected static ?string $pluralModelLabel = 'Product Reviews';

    protected static ?string $slug = 'shop-reviews';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('shop');
    }

    public static function infolist(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $schema
            ->components([
                SC\Section::make(__('Review Details'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('product')
                            ->label(__('Product'))
                            ->content(fn ($record) => $record->product?->title[$marketplaceLanguage] ?? 'Unknown'),

                        Forms\Components\Placeholder::make('rating')
                            ->label(__('Rating'))
                            ->content(fn ($record) => new HtmlString(
                                str_repeat('⭐', $record->rating) .
                                str_repeat('☆', 5 - $record->rating) .
                                " ({$record->rating}/5)"
                            )),

                        Forms\Components\Placeholder::make('status')
                            ->label(__('Status'))
                            ->content(fn ($record) => new HtmlString('<span class="px-2 py-1 rounded text-sm font-medium ' . match ($record->status) {
                                'approved' => 'bg-success-100 text-success-700',
                                'pending' => 'bg-warning-100 text-warning-700',
                                'rejected' => 'bg-danger-100 text-danger-700',
                                default => 'bg-gray-100 text-gray-700',
                            } . '">' . ucfirst($record->status) . '</span>')),
                    ]),

                SC\Section::make(__('Reviewer'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('reviewer_name')
                            ->label(__('Name'))
                            ->content(fn ($record) => $record->reviewer_name ?? 'Anonymous'),

                        Forms\Components\Placeholder::make('reviewer_email')
                            ->label(__('Email'))
                            ->content(fn ($record) => $record->reviewer_email ?? 'N/A'),

                        Forms\Components\Placeholder::make('verified_purchase')
                            ->label(__('Verified Purchase'))
                            ->content(fn ($record) => $record->verified_purchase ? 'Yes' : 'No'),
                    ]),

                SC\Section::make(__('Review Content'))
                    ->schema([
                        Forms\Components\Placeholder::make('title')
                            ->label(__('Title'))
                            ->content(fn ($record) => $record->title ?? 'No title'),

                        Forms\Components\Placeholder::make('content')
                            ->label(__('Content'))
                            ->content(fn ($record) => $record->content),
                    ]),

                SC\Section::make(__('Admin Response'))
                    ->visible(fn ($record) => !empty($record->admin_response))
                    ->schema([
                        Forms\Components\Placeholder::make('admin_response')
                            ->label('')
                            ->content(fn ($record) => $record->admin_response),

                        Forms\Components\Placeholder::make('responded_at')
                            ->label(__('Responded At'))
                            ->content(fn ($record) => $record->responded_at?->format('d M Y H:i')),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make(__('Moderation'))
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => __('Pending Review'),
                                'approved' => __('Approved'),
                                'rejected' => __('Rejected'),
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('admin_response')
                            ->label(__('Admin Response'))
                            ->rows(3)
                            ->placeholder(__('Respond to this review (visible to public)')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.title')
                    ->label(__('Product'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[$marketplaceLanguage] ?? 'Unknown') : $state)
                    ->limit(25)
                    ->searchable(),

                Tables\Columns\TextColumn::make('rating')
                    ->label(__('Rating'))
                    ->formatStateUsing(fn ($state) => str_repeat('⭐', $state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviewer_name')
                    ->label(__('Reviewer'))
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('title')
                    ->label(__('Title'))
                    ->limit(30)
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('verified_purchase')
                    ->label(__('Verified'))
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'approved',
                        'warning' => 'pending',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\IconColumn::make('admin_response')
                    ->label(__('Response'))
                    ->boolean()
                    ->getStateUsing(fn ($record) => !empty($record->admin_response))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => __('Pending'),
                        'approved' => __('Approved'),
                        'rejected' => __('Rejected'),
                    ]),
                Tables\Filters\SelectFilter::make('rating')
                    ->options([
                        1 => __('1 Star'),
                        2 => __('2 Stars'),
                        3 => __('3 Stars'),
                        4 => __('4 Stars'),
                        5 => __('5 Stars'),
                    ]),
                Tables\Filters\TernaryFilter::make('verified_purchase')
                    ->label(__('Verified Purchase')),
            ])
            ->recordActions([
                ViewAction::make(),
                Actions\Action::make('approve')
                    ->label(__('Approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'approved')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'approved']);
                        $record->product?->updateReviewStats();
                    }),
                Actions\Action::make('reject')
                    ->label(__('Reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status !== 'rejected')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'rejected']);
                        $record->product?->updateReviewStats();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label(__('Approve Selected'))
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
                        ->label(__('Reject Selected'))
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
