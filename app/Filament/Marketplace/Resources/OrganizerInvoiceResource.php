<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\OrganizerInvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\MarketplaceOrganizer;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class OrganizerInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationLabel = 'Facturi Organizatori';
    protected static ?string $modelLabel = 'Factură';
    protected static ?string $pluralModelLabel = 'Facturi Organizatori';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';
    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceAdmin?->marketplace_client_id)
            ->whereNotNull('marketplace_organizer_id');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Detalii factură')
                    ->schema([
                        Forms\Components\TextInput::make('number')
                            ->label('Număr factură')
                            ->disabled(),

                        Forms\Components\Select::make('marketplace_organizer_id')
                            ->label('Organizator')
                            ->relationship('organizer', 'name')
                            ->disabled()
                            ->preload(),

                        Forms\Components\Select::make('type')
                            ->label('Tip')
                            ->options([
                                'proforma' => 'Proforma',
                                'fiscal' => 'Fiscală',
                            ])
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'outstanding' => 'Neachitată',
                                'paid' => 'Achitată',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Descriere')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Date')
                    ->schema([
                        Forms\Components\DatePicker::make('issue_date')
                            ->label('Data emiterii')
                            ->required(),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('Data scadentă')
                            ->required(),

                        Forms\Components\DatePicker::make('period_start')
                            ->label('Perioadă de la'),

                        Forms\Components\DatePicker::make('period_end')
                            ->label('Perioadă până la'),
                    ])->columns(4),

                Section::make('Valori')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                $vatRate = (float) ($get('vat_rate') ?? 0);
                                $subtotal = (float) ($state ?? 0);
                                $vatAmount = $vatRate > 0 ? round($subtotal * $vatRate / 100, 2) : 0;
                                $set('vat_amount', $vatAmount);
                                $set('amount', $subtotal + $vatAmount);
                            }),

                        Forms\Components\TextInput::make('vat_rate')
                            ->label('TVA (%)')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                $subtotal = (float) ($get('subtotal') ?? 0);
                                $vatRate = (float) ($state ?? 0);
                                $vatAmount = $vatRate > 0 ? round($subtotal * $vatRate / 100, 2) : 0;
                                $set('vat_amount', $vatAmount);
                                $set('amount', $subtotal + $vatAmount);
                            }),

                        Forms\Components\TextInput::make('vat_amount')
                            ->label('Valoare TVA')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Total')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('currency')
                            ->label('Monedă')
                            ->default('RON')
                            ->maxLength(3),
                    ])->columns(5),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Număr')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizator')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fiscal' => 'info',
                        'proforma' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'fiscal' => 'Fiscală',
                        'proforma' => 'Proforma',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Data emiterii')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Perioadă')
                    ->formatStateUsing(function ($state, Invoice $record) {
                        if (!$record->period_start || !$record->period_end) return '-';
                        return $record->period_start->format('d.m.Y') . ' - ' . $record->period_end->format('d.m.Y');
                    }),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money(fn (Invoice $record) => $record->currency ?? 'RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('vat_amount')
                    ->label('TVA')
                    ->money(fn (Invoice $record) => $record->currency ?? 'RON'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Total')
                    ->money(fn (Invoice $record) => $record->currency ?? 'RON')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'outstanding' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Achitată',
                        'outstanding' => 'Neachitată',
                        default => $state,
                    }),
            ])
            ->defaultSort('issue_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace_organizer_id')
                    ->label('Organizator')
                    ->relationship('organizer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'outstanding' => 'Neachitată',
                        'paid' => 'Achitată',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tip')
                    ->options([
                        'fiscal' => 'Fiscală',
                        'proforma' => 'Proforma',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Invoice $record) => "Factură #{$record->number}")
                    ->modalContent(function (Invoice $record) {
                        return new HtmlString(static::renderInvoiceHtml($record));
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Închide'),
            ])
            ->toolbarActions([]);
    }

    /**
     * Render invoice as HTML for preview modal.
     */
    public static function renderInvoiceHtml(Invoice $record): string
    {
        $meta = $record->meta ?? [];
        $issuer = $meta['issuer'] ?? [];
        $client = $meta['client'] ?? [];
        $items = $meta['items'] ?? [];
        $currency = $record->currency ?? 'RON';

        $issuerHtml = '<div>';
        $issuerHtml .= '<h4 style="font-weight:600;text-transform:uppercase;font-size:12px;color:#6b7280;margin-bottom:8px;">Emitent</h4>';
        $issuerHtml .= '<p style="font-weight:600;">' . e($issuer['name'] ?? '-') . '</p>';
        if (!empty($issuer['cui'])) $issuerHtml .= '<p style="color:#6b7280;font-size:14px;">CUI: ' . e($issuer['cui']) . '</p>';
        if (!empty($issuer['reg_com'])) $issuerHtml .= '<p style="color:#6b7280;font-size:14px;">Reg. Com.: ' . e($issuer['reg_com']) . '</p>';
        if (!empty($issuer['address'])) $issuerHtml .= '<p style="color:#6b7280;font-size:14px;">' . e($issuer['address']) . '</p>';
        if (!empty($issuer['bank_name']) && !empty($issuer['iban'])) {
            $issuerHtml .= '<p style="color:#6b7280;font-size:14px;">' . e($issuer['bank_name']) . ': ' . e($issuer['iban']) . '</p>';
        }
        $issuerHtml .= '</div>';

        $clientHtml = '<div>';
        $clientHtml .= '<h4 style="font-weight:600;text-transform:uppercase;font-size:12px;color:#6b7280;margin-bottom:8px;">Client</h4>';
        $clientHtml .= '<p style="font-weight:600;">' . e($client['name'] ?? '-') . '</p>';
        if (!empty($client['cui'])) $clientHtml .= '<p style="color:#6b7280;font-size:14px;">CUI: ' . e($client['cui']) . '</p>';
        if (!empty($client['address'])) $clientHtml .= '<p style="color:#6b7280;font-size:14px;">' . e($client['address']) . '</p>';
        $clientHtml .= '</div>';

        $itemsHtml = '';
        foreach ($items as $item) {
            $itemsHtml .= '<tr>';
            $itemsHtml .= '<td style="padding:8px;border-bottom:1px solid #e5e7eb;">' . e($item['description'] ?? '') . '</td>';
            $itemsHtml .= '<td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">' . ($item['quantity'] ?? 0) . '</td>';
            $itemsHtml .= '<td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">' . number_format($item['price'] ?? 0, 2) . ' ' . $currency . '</td>';
            $itemsHtml .= '<td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:600;">' . number_format($item['total'] ?? 0, 2) . ' ' . $currency . '</td>';
            $itemsHtml .= '</tr>';
        }

        $statusLabel = $record->status === 'paid' ? 'Achitată' : 'Neachitată';
        $statusColor = $record->status === 'paid' ? '#059669' : '#d97706';

        return <<<HTML
        <div style="font-family:Arial,sans-serif;max-width:700px;margin:0 auto;">
            <div style="display:flex;gap:24px;margin-bottom:24px;">
                {$issuerHtml}
                {$clientHtml}
            </div>
            <table style="width:100%;border-collapse:collapse;margin-top:16px;">
                <thead>
                    <tr style="background:#f9fafb;">
                        <th style="padding:8px;text-align:left;font-size:12px;text-transform:uppercase;color:#6b7280;">Descriere</th>
                        <th style="padding:8px;text-align:right;font-size:12px;text-transform:uppercase;color:#6b7280;">Cantitate</th>
                        <th style="padding:8px;text-align:right;font-size:12px;text-transform:uppercase;color:#6b7280;">Preț</th>
                        <th style="padding:8px;text-align:right;font-size:12px;text-transform:uppercase;color:#6b7280;">Total</th>
                    </tr>
                </thead>
                <tbody>{$itemsHtml}</tbody>
            </table>
            <div style="margin-top:16px;text-align:right;">
                <p>Subtotal: <strong>{$record->subtotal} {$currency}</strong></p>
                <p>TVA ({$record->vat_rate}%): <strong>{$record->vat_amount} {$currency}</strong></p>
                <p style="font-size:18px;margin-top:8px;">Total: <strong>{$record->amount} {$currency}</strong></p>
            </div>
            <div style="margin-top:16px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #e5e7eb;padding-top:16px;">
                <span style="background:{$statusColor};color:#fff;padding:4px 12px;border-radius:12px;font-size:13px;">{$statusLabel}</span>
                <span style="color:#6b7280;font-size:14px;">Emisă: {$record->issue_date?->format('d.m.Y')} | Scadentă: {$record->due_date?->format('d.m.Y')}</span>
            </div>
        </div>
        HTML;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizerInvoices::route('/'),
            'edit' => Pages\EditOrganizerInvoice::route('/{record}/edit'),
        ];
    }
}
