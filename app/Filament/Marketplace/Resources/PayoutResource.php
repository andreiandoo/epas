<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\PayoutResource\Pages;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceAdmin;
use Filament\Forms;
use Filament\Infolists;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Support\Enums\IconPosition;

class PayoutResource extends Resource
{
    protected static ?string $model = MarketplacePayout::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'reference';

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
                Section::make('Payout Request')
                    ->schema([
                        Forms\Components\Placeholder::make('reference_display')
                            ->label('Reference')
                            ->content(fn (?MarketplacePayout $record): string => $record?->reference ?? '-'),

                        Forms\Components\Placeholder::make('organizer_display')
                            ->label('Organizer')
                            ->content(fn (?MarketplacePayout $record): string => $record?->organizer?->name ?? '-'),

                        Forms\Components\Placeholder::make('event_display')
                            ->label('Event')
                            ->content(function (?MarketplacePayout $record): string {
                                if (!$record?->event) return 'General payout (no specific event)';
                                $title = $record->event->title;
                                return is_array($title)
                                    ? ($title['ro'] ?? $title['en'] ?? reset($title) ?? 'Untitled')
                                    : ($title ?? 'Untitled');
                            }),

                        Forms\Components\Placeholder::make('amount_display')
                            ->label('Amount')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('status_display')
                            ->label('Status')
                            ->content(fn (?MarketplacePayout $record): string => $record?->status_label ?? '-'),

                        Forms\Components\Placeholder::make('created_at_display')
                            ->label('Requested At')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->created_at?->format('d.m.Y H:i') ?? '-'),
                    ])
                    ->columns(3),

                Section::make('Amount Breakdown')
                    ->schema([
                        Forms\Components\Placeholder::make('gross_amount_display')
                            ->label('Gross Amount')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->gross_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('commission_amount_display')
                            ->label('Commission')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? '-' . number_format($record->commission_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('fees_amount_display')
                            ->label('Fees')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? '-' . number_format($record->fees_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('net_amount_display')
                            ->label('Net Amount')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->amount, 2) . ' ' . $record->currency : '-'),
                    ])
                    ->columns(4),

                Section::make('Payout Method')
                    ->schema([
                        Forms\Components\Placeholder::make('bank_name_display')
                            ->label('Bank')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->payout_method['bank_name'] ?? '-'),

                        Forms\Components\Placeholder::make('iban_display')
                            ->label('IBAN')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->payout_method['iban'] ?? '-'),

                        Forms\Components\Placeholder::make('account_holder_display')
                            ->label('Account Holder')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->payout_method['account_holder'] ?? '-'),
                    ])
                    ->columns(3),

                Section::make('Organizer Notes')
                    ->schema([
                        Forms\Components\Placeholder::make('organizer_notes_display')
                            ->label('')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->organizer_notes ?? 'No notes from organizer')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(fn (?MarketplacePayout $record): bool => empty($record?->organizer_notes)),

                Section::make('Admin Notes')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->components([
                Section::make('Payout Request')
                    ->schema([
                        Infolists\Components\TextEntry::make('reference')
                            ->copyable()
                            ->copyMessage('Reference copied!')
                            ->copyMessageDuration(1500)
                            ->icon('heroicon-o-clipboard-document')
                            ->iconPosition(IconPosition::After),

                        Infolists\Components\TextEntry::make('organizer.name')
                            ->label('Organizer'),

                        Infolists\Components\TextEntry::make('event.title')
                            ->label('Event')
                            ->placeholder('General payout (no specific event)')
                            ->formatStateUsing(fn ($state) => is_array($state)
                                ? ($state['ro'] ?? $state['en'] ?? reset($state) ?? 'Untitled')
                                : $state),

                        Infolists\Components\TextEntry::make('amount')
                            ->money('RON')
                            ->copyable()
                            ->copyMessage('Amount copied!')
                            ->copyMessageDuration(1500)
                            ->icon('heroicon-o-clipboard-document')
                            ->iconPosition(IconPosition::After),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'info',
                                'processing' => 'primary',
                                'completed' => 'success',
                                'rejected' => 'danger',
                                'cancelled' => 'gray',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Requested At')
                            ->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(3),

                Section::make('Amount Breakdown')
                    ->schema([
                        Infolists\Components\TextEntry::make('gross_amount')
                            ->money('RON')
                            ->label('Gross Amount'),

                        Infolists\Components\TextEntry::make('commission_amount')
                            ->money('RON')
                            ->label('Commission'),

                        Infolists\Components\TextEntry::make('fees_amount')
                            ->money('RON')
                            ->label('Fees'),

                        Infolists\Components\TextEntry::make('amount')
                            ->money('RON')
                            ->label('Net Amount')
                            ->weight('bold'),
                    ])
                    ->columns(4),

                Section::make('Payout Method')
                    ->schema([
                        Infolists\Components\TextEntry::make('payout_method.bank_name')
                            ->label('Bank'),

                        Infolists\Components\TextEntry::make('payout_method.iban')
                            ->label('IBAN')
                            ->copyable()
                            ->copyMessage('IBAN copied!')
                            ->copyMessageDuration(1500)
                            ->icon('heroicon-o-clipboard-document')
                            ->iconPosition(IconPosition::After),

                        Infolists\Components\TextEntry::make('payout_method.account_holder')
                            ->label('Account Holder')
                            ->copyable()
                            ->copyMessage('Account holder copied!')
                            ->copyMessageDuration(1500)
                            ->icon('heroicon-o-clipboard-document')
                            ->iconPosition(IconPosition::After),
                    ])
                    ->columns(3),

                Section::make('Organizer Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('organizer_notes')
                            ->label('')
                            ->placeholder('No notes from organizer')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(fn ($record): bool => empty($record?->organizer_notes)),

                Section::make('Admin Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('admin_notes')
                            ->label('')
                            ->placeholder('No admin notes')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(fn ($record): bool => empty($record?->admin_notes)),

                Section::make('Rejection Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('rejectedByUser.name')
                            ->label('Rejected By'),
                        Infolists\Components\TextEntry::make('rejected_at')
                            ->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->isRejected()),

                Section::make('Payment Confirmation')
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_reference')
                            ->copyable()
                            ->icon('heroicon-o-clipboard-document')
                            ->iconPosition(IconPosition::After),
                        Infolists\Components\TextEntry::make('payment_method'),
                        Infolists\Components\TextEntry::make('payment_notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->payment_reference),

                Section::make('Timeline')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Requested')
                            ->dateTime('d.m.Y H:i'),
                        Infolists\Components\TextEntry::make('approved_at')
                            ->dateTime('d.m.Y H:i')
                            ->visible(fn ($record) => $record->approved_at),
                        Infolists\Components\TextEntry::make('processed_at')
                            ->dateTime('d.m.Y H:i')
                            ->visible(fn ($record) => $record->processed_at),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->dateTime('d.m.Y H:i')
                            ->visible(fn ($record) => $record->completed_at),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->placeholder('General')
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? ($state['ro'] ?? $state['en'] ?? reset($state) ?? 'Untitled')
                        : $state)
                    ->limit(25)
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'processing' => 'primary',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('period_start')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('organizer')
                    ->relationship('organizer', 'name'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeApproved())
                    ->action(function (MarketplacePayout $record): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->approve($admin->id);
                    }),

                Action::make('process')
                    ->label('Mark Processing')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeProcessed())
                    ->action(function (MarketplacePayout $record): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->markAsProcessing($admin->id);
                    }),

                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeCompleted())
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('payment_notes')
                            ->label('Payment Notes')
                            ->rows(2),
                    ])
                    ->action(function (MarketplacePayout $record, array $data): void {
                        $record->complete($data['payment_reference'], $data['payment_notes'] ?? null);
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeRejected())
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (MarketplacePayout $record, array $data): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->reject($admin->id, $data['rejection_reason']);
                    }),

                ViewAction::make(),
            ])
            ->toolbarActions([])
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
            'index' => Pages\ListPayouts::route('/'),
            'view' => Pages\ViewPayout::route('/{record}'),
        ];
    }
}
