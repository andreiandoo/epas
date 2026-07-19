<?php

namespace App\Filament\Marketplace\Resources\InstallmentAgreementResource\Pages;

use App\Filament\Marketplace\Resources\InstallmentAgreementResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;

class ViewInstallmentAgreement extends ViewRecord
{
    protected static string $resource = InstallmentAgreementResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Rezumat')->schema([
                TextEntry::make('order_id')->label('Comandă'),
                TextEntry::make('customer_email')->label('Client'),
                TextEntry::make('plan_type')->label('Metodă')
                    ->formatStateUsing(fn ($s) => $s === 'bnpl_single' ? 'BNPL' : 'Rate'),
                TextEntry::make('status')->badge(),
                TextEntry::make('base_total_cents')->label('Preț direct')
                    ->formatStateUsing(fn ($s, $r) => number_format($s / 100, 2) . ' ' . $r->currency),
                TextEntry::make('surcharge_cents')->label('Surcharge')
                    ->formatStateUsing(fn ($s, $r) => number_format($s / 100, 2) . ' ' . $r->currency),
                TextEntry::make('customer_total_cents')->label('Total client')
                    ->formatStateUsing(fn ($s, $r) => number_format($s / 100, 2) . ' ' . $r->currency),
                TextEntry::make('platform_fee_cents')->label('Fee platformă (de la marketplace)')
                    ->formatStateUsing(fn ($s, $r) => number_format($s / 100, 2) . ' ' . $r->currency),
                TextEntry::make('event_start_date')->label('Data eveniment')->date('d.m.Y'),
            ])->columns(3),

            SC\Section::make('Desfășurător de plăți')->schema([
                RepeatableEntry::make('payments')->schema([
                    TextEntry::make('sequence')->label('#')
                        ->formatStateUsing(fn ($s) => $s === 0 ? 'Avans' : "Rata {$s}"),
                    TextEntry::make('due_date')->label('Scadență')->date('d.m.Y'),
                    TextEntry::make('amount_cents')->label('Sumă')
                        ->formatStateUsing(fn ($s, $r) => number_format($s / 100, 2)),
                    TextEntry::make('status')->badge()
                        ->color(fn ($s) => match ($s) {
                            'paid' => 'success', 'failed', 'cancelled' => 'danger',
                            'action_required', 'retrying' => 'warning', default => 'gray',
                        }),
                    TextEntry::make('paid_at')->label('Plătit la')->dateTime('d.m.Y H:i')->placeholder('—'),
                ])->columns(5),
            ]),
        ]);
    }
}
