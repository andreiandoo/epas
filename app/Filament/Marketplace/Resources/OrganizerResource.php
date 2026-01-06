<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\OrganizerResource\Pages;
use App\Models\MarketplaceOrganizer;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;

class OrganizerResource extends Resource
{
    protected static ?string $model = MarketplaceOrganizer::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        if (!$marketplaceAdmin) return null;

        $count = static::getEloquentQuery()
            ->where('status', 'pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceAdmin?->marketplace_client_id);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Organizer Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Parolă')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => \Illuminate\Support\Facades\Hash::make($state))
                            ->helperText(fn (string $context): string =>
                                $context === 'create'
                                    ? 'Parola pentru contul organizatorului'
                                    : 'Lasă gol pentru a păstra parola existentă')
                            ->revealable()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('contact_name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('website')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Company Information')
                    ->schema([
                        Forms\Components\TextInput::make('company_name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('company_tax_id')
                            ->label('Tax ID / VAT')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('company_registration')
                            ->label('Registration Number')
                            ->maxLength(100),

                        Forms\Components\Textarea::make('company_address')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->maxLength(255)
                            ->placeholder('e.g., ING Bank, BRD, BCR'),

                        Forms\Components\TextInput::make('iban')
                            ->label('IBAN')
                            ->maxLength(34)
                            ->placeholder('e.g., RO49AAAA1B31007593840000')
                            ->helperText('Used for payouts to this organizer'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Section::make('Termeni și Condiții Bilete')
                    ->description('Acești termeni vor fi preluați automat în câmpul "Termeni bilete" când creați un eveniment nou pentru acest organizator')
                    ->schema([
                        Forms\Components\RichEditor::make('ticket_terms')
                            ->label('Termeni și condiții standard')
                            ->helperText('Textul de aici va fi copiat automat în secțiunea Ticket Terms la crearea unui eveniment')
                            ->columnSpanFull()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('organizer-terms')
                            ->fileAttachmentsVisibility('public'),
                    ])
                    ->collapsed(),

                Section::make('Status & Commission')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                            ])
                            ->required()
                            ->default('pending'),

                        Forms\Components\TextInput::make('commission_rate')
                            ->label('Commission Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->step(0.5)
                            ->suffix('%')
                            ->helperText('Leave empty to use marketplace default'),

                        Forms\Components\DateTimePicker::make('verified_at')
                            ->label('Verified At'),
                    ])
                    ->columns(3),

                Section::make('Feature Access')
                    ->schema([
                        Forms\Components\Toggle::make('gamification_enabled')
                            ->label('Gamification Enabled')
                            ->helperText('Allow this organizer to use customer points for discounts on their events'),

                        Forms\Components\Toggle::make('invitations_enabled')
                            ->label('Invitations Enabled')
                            ->default(true)
                            ->helperText('Allow this organizer to create and manage event invitations'),
                    ])
                    ->columns(2),

                Section::make('Financial Summary')
                    ->schema([
                        Forms\Components\Placeholder::make('total_revenue_display')
                            ->label('Total Revenue')
                            ->content(fn (?MarketplaceOrganizer $record): string =>
                                $record ? number_format($record->total_revenue, 2) . ' RON' : '-'),

                        Forms\Components\Placeholder::make('available_balance_display')
                            ->label('Available Balance')
                            ->content(fn (?MarketplaceOrganizer $record): string =>
                                $record ? number_format($record->available_balance, 2) . ' RON' : '-'),

                        Forms\Components\Placeholder::make('pending_balance_display')
                            ->label('Pending Balance')
                            ->content(fn (?MarketplaceOrganizer $record): string =>
                                $record ? number_format($record->pending_balance, 2) . ' RON' : '-'),

                        Forms\Components\Placeholder::make('total_paid_out_display')
                            ->label('Total Paid Out')
                            ->content(fn (?MarketplaceOrganizer $record): string =>
                                $record ? number_format($record->total_paid_out, 2) . ' RON' : '-'),
                    ])
                    ->columns(4)
                    ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&background=10b981&color=fff'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('total_events')
                    ->label('Events')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('available_balance')
                    ->label('Balance')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]),

                Tables\Filters\TernaryFilter::make('verified')
                    ->label('Verified')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('verified_at'),
                        false: fn (Builder $query) => $query->whereNull('verified_at'),
                    ),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplaceOrganizer $record): bool => $record->status === 'pending')
                    ->action(function (MarketplaceOrganizer $record): void {
                        $record->update(['status' => 'active']);
                    }),

                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplaceOrganizer $record): bool => $record->verified_at === null && $record->status === 'active')
                    ->action(function (MarketplaceOrganizer $record): void {
                        $record->update(['verified_at' => now()]);
                    }),

                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplaceOrganizer $record): bool => $record->status === 'active')
                    ->action(function (MarketplaceOrganizer $record): void {
                        $record->update(['status' => 'suspended']);
                    }),

                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplaceOrganizer $record): bool => $record->status === 'suspended')
                    ->action(function (MarketplaceOrganizer $record): void {
                        $record->update(['status' => 'active']);
                    }),

                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $records->each(fn ($record) => $record->update(['status' => 'active']));
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListOrganizers::route('/'),
            'create' => Pages\CreateOrganizer::route('/create'),
            'view' => Pages\ViewOrganizer::route('/{record}'),
            'edit' => Pages\EditOrganizer::route('/{record}/edit'),
        ];
    }
}
