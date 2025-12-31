<?php

namespace App\Filament\Tenant\Resources\Tracking;

use App\Filament\Tenant\Resources\Tracking\TxIdentityLinkResource\Pages;
use App\Models\Tracking\TxIdentityLink;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class TxIdentityLinkResource extends Resource
{
    protected static ?string $model = TxIdentityLink::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = 'Analytics & Tracking';
    protected static ?string $navigationLabel = 'Identity Stitching';
    protected static ?string $modelLabel = 'Identity Link';
    protected static ?string $pluralModelLabel = 'Identity Links';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Identity Link Details')
                    ->icon('heroicon-o-link')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('visitor_id')
                            ->label('Visitor ID')
                            ->content(fn ($record) => new HtmlString('<code class="text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">' . $record->visitor_id . '</code>')),
                        Forms\Components\Placeholder::make('person_id')
                            ->label('Person ID')
                            ->content(fn ($record) => new HtmlString('<span class="text-lg font-bold text-primary-600">#' . $record->person_id . '</span>')),
                        Forms\Components\Placeholder::make('link_type')
                            ->label('Link Type')
                            ->content(fn ($record) => new HtmlString('<span class="px-2 py-1 rounded text-sm font-medium ' . match ($record->link_type) {
                                'order_completed' => 'bg-success-100 text-success-700',
                                'login' => 'bg-primary-100 text-primary-700',
                                'registration' => 'bg-info-100 text-info-700',
                                default => 'bg-gray-100 text-gray-700',
                            } . '">' . ucfirst(str_replace('_', ' ', $record->link_type)) . '</span>')),
                        Forms\Components\Placeholder::make('confidence_score')
                            ->label('Confidence')
                            ->content(fn ($record) => new HtmlString(self::confidenceBar($record->confidence_score))),
                        Forms\Components\Placeholder::make('linked_at')
                            ->label('Linked At')
                            ->content(fn ($record) => $record->linked_at?->format('d M Y H:i:s')),
                        Forms\Components\Placeholder::make('source_reference')
                            ->label('Source Reference')
                            ->content(fn ($record) => $record->source_reference ?? 'N/A'),
                    ]),

                SC\Section::make('Person Details')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('person_email')
                            ->label('Email')
                            ->content(fn ($record) => $record->person?->email ?? 'N/A'),
                        Forms\Components\Placeholder::make('person_name')
                            ->label('Name')
                            ->content(fn ($record) => trim(($record->person?->first_name ?? '') . ' ' . ($record->person?->last_name ?? '')) ?: 'N/A'),
                        Forms\Components\Placeholder::make('person_phone')
                            ->label('Phone')
                            ->content(fn ($record) => $record->person?->phone ?? 'N/A'),
                        Forms\Components\Placeholder::make('person_created')
                            ->label('Customer Since')
                            ->content(fn ($record) => $record->person?->created_at?->format('d M Y') ?? 'N/A'),
                    ]),

                SC\Section::make('Metadata')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->meta))
                    ->schema([
                        Forms\Components\Placeholder::make('meta_json')
                            ->label('')
                            ->content(fn ($record) => new HtmlString(
                                '<pre class="bg-gray-50 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto">' .
                                json_encode($record->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) .
                                '</pre>'
                            )),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('linked_at')
                    ->label('Linked')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('visitor_id')
                    ->label('Visitor ID')
                    ->limit(16)
                    ->tooltip(fn ($record) => $record->visitor_id)
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('person_id')
                    ->label('Person')
                    ->formatStateUsing(fn ($state) => '#' . $state)
                    ->url(fn ($record) => $record->person_id ? route('filament.tenant.resources.customers.view', ['record' => $record->person_id]) : null)
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\TextColumn::make('person.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('link_type')
                    ->label('Type')
                    ->colors([
                        'success' => 'order_completed',
                        'primary' => 'login',
                        'info' => 'registration',
                        'gray' => true,
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('confidence_score')
                    ->label('Confidence')
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 0) . '%')
                    ->color(fn ($state) => match (true) {
                        $state >= 0.9 => 'success',
                        $state >= 0.7 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('source_reference')
                    ->label('Source')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('link_type')
                    ->label('Link Type')
                    ->options([
                        'order_completed' => 'Order Completed',
                        'login' => 'Login',
                        'registration' => 'Registration',
                        'email_click' => 'Email Click',
                        'manual' => 'Manual',
                    ]),
                Tables\Filters\Filter::make('high_confidence')
                    ->label('High Confidence (≥90%)')
                    ->query(fn (Builder $query) => $query->where('confidence_score', '>=', 0.9)),
                Tables\Filters\Filter::make('linked_at')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('linked_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('linked_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('linked_at', 'desc');
    }

    protected static function confidenceBar(float $score): string
    {
        $percentage = $score * 100;
        $color = match (true) {
            $score >= 0.9 => 'bg-success-500',
            $score >= 0.7 => 'bg-warning-500',
            default => 'bg-danger-500',
        };

        return <<<HTML
        <div class="flex items-center gap-2">
            <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div class="{$color} h-full rounded-full" style="width: {$percentage}%"></div>
            </div>
            <span class="text-sm font-medium">{$percentage}%</span>
        </div>
        HTML;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTxIdentityLinks::route('/'),
            'view' => Pages\ViewTxIdentityLink::route('/{record}'),
        ];
    }
}
