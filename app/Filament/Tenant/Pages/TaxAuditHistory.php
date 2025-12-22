<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tax\TaxAuditLog;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;

class TaxAuditHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected string $view = 'filament.tenant.pages.tax-audit-history';

    protected static ?string $navigationLabel = 'Audit History';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $navigationParentItem = 'Taxes';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Tax Audit History';

    public function table(Table $table): Table
    {
        $tenant = auth()->user()->tenant;

        return $table
            ->query(
                TaxAuditLog::query()
                    ->where('tenant_id', $tenant?->id)
                    ->orderByDesc('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('M j, Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->getEventLabel())
                    ->color(fn ($record) => $record->getEventColor()),

                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Tax Type')
                    ->formatStateUsing(fn ($record) => $record->getTaxTypeLabel()),

                Tables\Columns\TextColumn::make('tax_name')
                    ->label('Tax')
                    ->state(function ($record) {
                        $values = $record->new_values ?? $record->old_values ?? [];
                        if (isset($values['name'])) {
                            return $values['name'];
                        }
                        // For local taxes, show location
                        if (isset($values['country'])) {
                            return collect([$values['city'] ?? null, $values['county'] ?? null, $values['country']])
                                ->filter()
                                ->implode(', ');
                        }
                        return 'ID: ' . $record->auditable_id;
                    }),

                Tables\Columns\TextColumn::make('user_name')
                    ->label('Changed By')
                    ->searchable(),

                Tables\Columns\TextColumn::make('changes')
                    ->label('Changes')
                    ->state(function ($record) {
                        $changed = $record->getChangedFields();
                        if (empty($changed)) {
                            return $record->event === 'created' ? 'New record' : '-';
                        }
                        return count($changed) . ' field(s)';
                    })
                    ->tooltip(function ($record) {
                        $changed = $record->getChangedFields();
                        if (empty($changed)) {
                            return null;
                        }
                        $lines = [];
                        foreach ($changed as $field => $values) {
                            $old = is_array($values['old']) ? json_encode($values['old']) : ($values['old'] ?? 'null');
                            $new = is_array($values['new']) ? json_encode($values['new']) : ($values['new'] ?? 'null');
                            $lines[] = "{$field}: {$old} → {$new}";
                        }
                        return implode("\n", array_slice($lines, 0, 5));
                    }),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'restored' => 'Restored',
                    ]),

                Tables\Filters\SelectFilter::make('auditable_type')
                    ->label('Tax Type')
                    ->options([
                        'App\\Models\\Tax\\GeneralTax' => 'General Tax',
                        'App\\Models\\Tax\\LocalTax' => 'Local Tax',
                        'App\\Models\\Tax\\TaxExemption' => 'Tax Exemption',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(fn ($record) => view('filament.tenant.pages.partials.audit-details', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
    }
}
