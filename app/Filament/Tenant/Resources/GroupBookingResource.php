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

    protected static ?string $navigationParentItem = 'Group Booking';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Group Booking';

    protected static ?string $pluralModelLabel = 'Group Bookings';

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

                SC\Section::make('Group Information')
                    ->schema([
                        Forms\Components\TextInput::make('group_name')
                            ->label('Group Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Company Outing, School Trip'),

                        Forms\Components\Select::make('group_type')
                            ->label('Group Type')
                            ->options([
                                'corporate' => 'Corporate / Business',
                                'school' => 'School / Educational',
                                'family' => 'Family & Friends',
                                'club' => 'Club / Organization',
                                'tour' => 'Tour Group',
                                'other' => 'Other',
                            ])
                            ->default('corporate')
                            ->required(),

                        Forms\Components\Select::make('event_id')
                            ->label('Event')
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
                            ->label('Group Organizer')
                            ->options(function () use ($tenant) {
                                return Customer::where('tenant_id', $tenant?->id)
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [$c->id => $c->full_name . ' (' . $c->email . ')']);
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Contact person for this group'),
                    ])->columns(2),

                SC\Section::make('Tickets & Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('total_tickets')
                            ->label('Total Tickets')
                            ->numeric()
                            ->required()
                            ->minValue(2)
                            ->live()
                            ->helperText('Minimum 2 tickets for group booking'),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->prefix('€')
                            ->required(),

                        Forms\Components\TextInput::make('discount_percentage')
                            ->label('Discount %')
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
                            ->label('Discount Amount')
                            ->numeric()
                            ->prefix('€')
                            ->default(0),
                    ])->columns(4),

                SC\Section::make('Status & Payment')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'pending' => 'Pending Confirmation',
                                'confirmed' => 'Confirmed',
                                'paid' => 'Paid',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),

                        Forms\Components\Select::make('payment_type')
                            ->label('Payment Method')
                            ->options([
                                'full' => 'Full Payment',
                                'split' => 'Split Payment (members pay individually)',
                                'invoice' => 'Invoice (for companies)',
                            ])
                            ->default('full'),

                        Forms\Components\DateTimePicker::make('deadline_at')
                            ->label('Payment Deadline'),

                        Forms\Components\DateTimePicker::make('confirmed_at')
                            ->label('Confirmed At')
                            ->disabled(),
                    ])->columns(4),

                SC\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Internal Notes')
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
                    ->label('Group')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\BadgeColumn::make('group_type')
                    ->label('Type')
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
                    ->label('Amount')
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
                    ->label('Organizer')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('deadline_at')
                    ->label('Deadline')
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
                        'corporate' => 'Corporate',
                        'school' => 'School',
                        'family' => 'Family',
                        'club' => 'Club',
                        'tour' => 'Tour',
                        'other' => 'Other',
                    ]),
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Actions\Action::make('confirm')
                    ->label('Confirm')
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
                    ->label('Mark Paid')
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
