<?php

namespace App\Filament\Vendor\Resources;

use App\Filament\Vendor\Resources\ShiftResource\Pages;
use App\Models\VendorShift;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ShiftResource extends Resource
{
    protected static ?string $model = VendorShift::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Shifts';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'shifts';

    public static function canAccess(): bool
    {
        $employee = Auth::guard('vendor_employee')->user();

        return $employee && in_array($employee->role, ['manager', 'supervisor', 'admin']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $employee = Auth::guard('vendor_employee')->user();

        return parent::getEloquentQuery()
            ->where('vendor_id', $employee->vendor_id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('d M H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ended_at')
                    ->label('Ended')
                    ->dateTime('d M H:i')
                    ->placeholder('Active'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(function ($record) {
                        if (! $record->started_at) {
                            return '-';
                        }
                        $end = $record->ended_at ?? now();
                        $minutes = $record->started_at->diffInMinutes($end);
                        $hours = intdiv($minutes, 60);
                        $mins = $minutes % 60;

                        return "{$hours}h {$mins}m";
                    }),

                Tables\Columns\TextColumn::make('sales_count')
                    ->label('Sales')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sales_total_cents')
                    ->label('Revenue')
                    ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2) . ' RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('posDevice.name')
                    ->label('POS Device')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'gray'    => 'completed',
                    ]),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->label('Active Shifts')
                    ->query(fn (Builder $q) => $q->where('status', 'active'))
                    ->toggle(),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $q) => $q->whereDate('started_at', today()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('end_shift')
                    ->label('End Shift')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update([
                        'status'   => 'completed',
                        'ended_at' => now(),
                    ])),
            ])
            ->defaultSort('started_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShifts::route('/'),
        ];
    }
}
