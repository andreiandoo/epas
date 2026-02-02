<?php

namespace App\Filament\Marketplace\Resources\AffiliateWithdrawalResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateWithdrawalResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Schemas\Schema;

class ViewAffiliateWithdrawal extends ViewRecord
{
    protected static string $resource = AffiliateWithdrawalResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Infolists\Components\Section::make('Withdrawal Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('reference')
                            ->label('Reference')
                            ->copyable()
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('amount')
                            ->label('Amount')
                            ->money(fn ($record) => $record->currency ?? 'RON'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($record) => $record->getStatusColor()),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Requested At')
                            ->dateTime(),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Affiliate Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('affiliate.name')
                            ->label('Affiliate Name'),

                        Infolists\Components\TextEntry::make('affiliate.code')
                            ->label('Affiliate Code')
                            ->badge()
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('affiliate.contact_email')
                            ->label('Email')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('affiliate.available_balance')
                            ->label('Current Balance')
                            ->money(fn ($record) => $record->currency ?? 'RON'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Payment Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->formatStateUsing(fn ($record) => $record->getPaymentMethodLabel()),

                        Infolists\Components\TextEntry::make('payment_details')
                            ->label('Payment Details')
                            ->formatStateUsing(fn ($record) => $record->getFormattedPaymentDetails()),

                        Infolists\Components\TextEntry::make('transaction_id')
                            ->label('Transaction ID')
                            ->placeholder('Not provided')
                            ->copyable(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Processing Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('processedByUser.name')
                            ->label('Processed By')
                            ->placeholder('Not processed'),

                        Infolists\Components\TextEntry::make('processed_at')
                            ->label('Processed At')
                            ->dateTime()
                            ->placeholder('Not processed'),

                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->placeholder('N/A')
                            ->visible(fn ($record) => $record->status === 'rejected'),

                        Infolists\Components\TextEntry::make('admin_notes')
                            ->label('Admin Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => in_array($record->status, ['processing', 'completed', 'rejected'])),

                Infolists\Components\Section::make('Request Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('requested_ip')
                            ->label('IP Address')
                            ->placeholder('Not recorded'),
                    ])
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
