<?php

namespace App\Filament\Tenant\Resources\AffiliateWithdrawalResource\Pages;

use App\Filament\Tenant\Resources\AffiliateWithdrawalResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;

class ViewAffiliateWithdrawal extends ViewRecord
{
    protected static string $resource = AffiliateWithdrawalResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make(__('Withdrawal Details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('reference')
                            ->label(__('Reference'))
                            ->copyable()
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('amount')
                            ->label(__('Amount'))
                            ->money(fn ($record) => $record->currency ?? 'RON'),

                        Infolists\Components\TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn ($record) => $record->getStatusColor()),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('Requested At'))
                            ->dateTime(),
                    ])
                    ->columns(4),

                SC\Section::make(__('Affiliate Information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('affiliate.name')
                            ->label(__('Affiliate Name')),

                        Infolists\Components\TextEntry::make('affiliate.code')
                            ->label(__('Affiliate Code'))
                            ->badge()
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('affiliate.contact_email')
                            ->label('Email')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('affiliate.available_balance')
                            ->label(__('Current Balance'))
                            ->money(fn ($record) => $record->currency ?? 'RON'),
                    ])
                    ->columns(4),

                SC\Section::make(__('Payment Information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label(__('Payment Method'))
                            ->formatStateUsing(fn ($record) => $record->getPaymentMethodLabel()),

                        Infolists\Components\TextEntry::make('payment_details')
                            ->label(__('Payment Details'))
                            ->formatStateUsing(fn ($record) => $record->getFormattedPaymentDetails()),

                        Infolists\Components\TextEntry::make('transaction_id')
                            ->label(__('Transaction ID'))
                            ->placeholder(__('Not provided'))
                            ->copyable(),
                    ])
                    ->columns(3),

                SC\Section::make(__('Processing Information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('processedByUser.name')
                            ->label(__('Processed By'))
                            ->placeholder(__('Not processed')),

                        Infolists\Components\TextEntry::make('processed_at')
                            ->label(__('Processed At'))
                            ->dateTime()
                            ->placeholder(__('Not processed')),

                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->label(__('Rejection Reason'))
                            ->placeholder('N/A')
                            ->visible(fn ($record) => $record->status === 'rejected'),

                        Infolists\Components\TextEntry::make('admin_notes')
                            ->label(__('Admin Notes'))
                            ->placeholder(__('No notes'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => in_array($record->status, ['processing', 'completed', 'rejected'])),

                SC\Section::make(__('Request Information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('requested_ip')
                            ->label(__('IP Address'))
                            ->placeholder(__('Not recorded')),
                    ])
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
