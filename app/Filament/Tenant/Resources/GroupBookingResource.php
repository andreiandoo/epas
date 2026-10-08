<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\GroupBookingResource\Pages;
use App\Models\GroupBooking;
use App\Models\Event;
use App\Models\Customer;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class GroupBookingResource extends Resource
{
    protected static ?string $model = GroupBooking::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Group Bookings';

    public static function getNavigationLabel(): string
    {
        return __('Group Bookings');
    }

    protected static ?string $navigationParentItem = 'Group Booking';

    public static function getNavigationParentItem(): ?string
    {
        return __('Group Booking');
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Group Booking';

    public static function getModelLabel(): string
    {
        return __('Group Booking');
    }

    protected static ?string $pluralModelLabel = 'Group Bookings';

    public static function getPluralModelLabel(): string
    {
        return __('Group Bookings');
    }

    protected static ?string $slug = 'group-bookings';

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
            ->where('slug', 'group-booking')
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

                SC\Section::make(__('Group Information'))
                    ->schema([
                        Forms\Components\TextInput::make('group_name')
                            ->label(__('Group Name'))
                            ->required()
                            ->maxLength(255)
                            ->placeholder(__('e.g., Company Outing, School Trip')),

                        Forms\Components\Select::make('group_type')
                            ->label(__('Group Type'))
                            ->options([
                                'corporate' => __('Corporate / Business'),
                                'school' => __('School / Educational'),
                                'family' => __('Family & Friends'),
                                'club' => __('Club / Organization'),
                                'tour' => __('Tour Group'),
                                'other' => __('Other'),
                            ])
                            ->default('corporate')
                            ->required(),

                        Forms\Components\Select::make('event_id')
                            ->label(__('Event'))
                            ->options(function () use ($tenant) {
                                $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';
                                return Event::where('tenant_id', $tenant?->id)
                                    ->where('status', 'published')
                                    ->get()
                                    ->mapWithKeys(function ($e) use ($tenantLanguage) {
                                        $title = is_array($e->title)
                                            ? ($e->title[$tenantLanguage] ?? $e->title['en'] ?? array_values($e->title)[0] ?? 'Untitled')
                                            : ($e->title ?? 'Untitled');
                                        return [$e->id => $title];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('organizer_customer_id')
                            ->label(__('Group Organizer'))
                            ->options(function () use ($tenant) {
                                return Customer::where('tenant_id', $tenant?->id)
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [$c->id => $c->full_name . ' (' . $c->email . ')']);
                            })
                            ->searchable()
                            ->preload()
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Contact person for this group'),
                    ])->columns(2),

                SC\Section::make(__('Tickets & Pricing'))
                    ->schema([
                        Forms\Components\TextInput::make('total_tickets')
                            ->label(__('Total Tickets'))
                            ->numeric()
                            ->required()
                            ->minValue(2)
                            ->live()
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Minimum 2 tickets for group booking'),

                        Forms\Components\TextInput::make('total_amount')
                            ->label(__('Total Amount'))
                            ->numeric()
                            ->prefix('€')
                            ->required(),

                        Forms\Components\TextInput::make('discount_percentage')
                            ->label(__('Discount %'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                $total = floatval($get('total_amount') ?? 0);
                                if ($state && $total > 0) {
                                    $discount = $total * (floatval($state) / 100);
                                    $set('discount_amount', round($discount, 2));
                                }
                            }),

                        Forms\Components\TextInput::make('discount_amount')
                            ->label(__('Discount Amount'))
                            ->numeric()
                            ->prefix('€')
                            ->default(0),
                    ])->columns(4),

                SC\Section::make(__('Status & Payment'))
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'pending' => __('Pending Confirmation'),
                                'confirmed' => 'Confirmed',
                                'paid' => 'Paid',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),

                        Forms\Components\Select::make('payment_type')
                            ->label(__('Payment Method'))
                            ->options([
                                'full' => __('Full Payment'),
                                'split' => __('Split Payment (members pay individually)'),
                                'invoice' => __('Invoice (for companies)'),
                            ])
                            ->default('full'),

                        Forms\Components\DateTimePicker::make('deadline_at')
                            ->label(__('Payment Deadline')),

                        Forms\Components\DateTimePicker::make('confirmed_at')
                            ->label(__('Confirmed At'))
                            ->disabled(),
                    ])->columns(4),

                SC\Section::make(__('Notes'))
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('Internal Notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group_name')
                    ->label(__('Group'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('event.title')
                    ->label(__('Event'))
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\BadgeColumn::make('group_type')
                    ->label(__('Type'))
                    ->colors([
                        'primary' => 'corporate',
                        'success' => 'school',
                        'warning' => 'family',
                        'info' => 'club',
                        'gray' => fn ($state) => in_array($state, ['tour', 'other']),
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('total_tickets')
                    ->label('Tickets')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('final_amount')
                    ->label(__('Amount'))
                    ->getStateUsing(fn ($record) => '€' . number_format($record->getFinalAmount(), 2))
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw('(total_amount - discount_amount) ' . $direction)),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'pending',
                        'info' => 'confirmed',
                        'success' => 'paid',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('organizer.full_name')
                    ->label(__('Organizer'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('deadline_at')
                    ->label(__('Deadline'))
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('group_type')
                    ->options([
                        'corporate' => __('Corporate'),
                        'school' => __('School'),
                        'family' => __('Family'),
                        'club' => 'Club',
                        'tour' => __('Tour'),
                        'other' => __('Other'),
                    ]),
                Tables\Filters\SelectFilter::make('event_id')
                    ->label(__('Event'))
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Actions\Action::make('confirm')
                    ->label(__('Confirm'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['draft', 'pending']))
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'confirmed',
                            'confirmed_at' => now(),
                        ]);
                    })
                    ->requiresConfirmation(),
                Actions\Action::make('mark_paid')
                    ->label(__('Mark Paid'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'confirmed')
                    ->action(fn ($record) => $record->update(['status' => 'paid']))
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => Pages\ListGroupBookings::route('/'),
            'create' => Pages\CreateGroupBooking::route('/create'),
            'view' => Pages\ViewGroupBooking::route('/{record}'),
            'edit' => Pages\EditGroupBooking::route('/{record}/edit'),
        ];
    }
}
