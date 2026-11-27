<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\EmailLogResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Support\MarketplaceTz;
use App\Jobs\ResendEmailJob;
use App\Models\MarketplaceEmailLog;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;

class EmailLogResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceEmailLog::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Email Logs';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id)
            ->latest();
    }

    public static function canCreate(): bool
    {
        return false; // Email logs are created automatically
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make(__('Email Details'))
                    ->schema([
                        Forms\Components\TextInput::make('to_email')
                            ->label(__('To'))
                            ->disabled(),
                        Forms\Components\TextInput::make('to_name')
                            ->label(__('Recipient Name'))
                            ->disabled(),
                        Forms\Components\TextInput::make('from_email')
                            ->label(__('From'))
                            ->disabled(),
                        Forms\Components\TextInput::make('subject')
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => __('Pending'),
                                'sent' => __('Sent'),
                                'delivered' => __('Delivered'),
                                'opened' => __('Opened'),
                                'clicked' => __('Clicked'),
                                'bounced' => __('Bounced'),
                                'failed' => __('Failed'),
                            ])
                            ->disabled(),
                        Forms\Components\TextInput::make('template_slug')
                            ->label(__('Template'))
                            ->disabled(),
                    ])->columns(2),

                SC\Section::make(__('Tracking'))
                    ->schema([
                        Forms\Components\DateTimePicker::make('sent_at')
                            ->timezone(MarketplaceTz::tz())
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('delivered_at')
                            ->timezone(MarketplaceTz::tz())
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('opened_at')
                            ->timezone(MarketplaceTz::tz())
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('clicked_at')
                            ->timezone(MarketplaceTz::tz())
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('bounced_at')
                            ->timezone(MarketplaceTz::tz())
                            ->disabled(),
                    ])->columns(3),

                SC\Section::make(__('Content'))
                    ->schema([
                        Forms\Components\Placeholder::make('body_html_rendered')
                            ->label(__('HTML Body'))
                            ->content(function ($record) {
                                if (empty($record?->body_html)) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<p class="text-sm text-gray-500">No HTML body</p>'
                                    );
                                }
                                $encoded = htmlspecialchars($record->body_html, ENT_QUOTES, 'UTF-8');
                                return new \Illuminate\Support\HtmlString(
                                    '<iframe
                                        srcdoc="' . $encoded . '"
                                        sandbox="allow-same-origin"
                                        style="width:100%; min-height:500px; border:1px solid #e5e7eb; border-radius:0.5rem; background:#fff;"
                                    ></iframe>'
                                );
                            })
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('error_message')
                            ->label(__('Error Message'))
                            ->disabled()
                            ->visible(fn ($record) => !empty($record?->error_message))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('to_email')
                    ->label(__('To'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('template_slug')
                    ->label(__('Template'))
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'sent' => 'info',
                        'delivered' => 'success',
                        'opened' => 'success',
                        'clicked' => 'success',
                        'bounced' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime(timezone: MarketplaceTz::tz())
                    ->sortable(),
                Tables\Columns\IconColumn::make('opened_at')
                    ->label(__('Opened'))
                    ->boolean()
                    ->getStateUsing(fn ($record) => !is_null($record->opened_at)),
                Tables\Columns\IconColumn::make('clicked_at')
                    ->label(__('Clicked'))
                    ->boolean()
                    ->getStateUsing(fn ($record) => !is_null($record->clicked_at)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => __('Pending'),
                        'sent' => __('Sent'),
                        'delivered' => __('Delivered'),
                        'opened' => __('Opened'),
                        'clicked' => __('Clicked'),
                        'bounced' => __('Bounced'),
                        'failed' => __('Failed'),
                    ]),
                Tables\Filters\SelectFilter::make('template_slug')
                    ->label(__('Template'))
                    ->options(\App\Models\MarketplaceEmailTemplate::TEMPLATE_SLUGS),
                Tables\Filters\Filter::make('sent_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('sent_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('sent_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resend')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => in_array($record->status, ['failed', 'bounced']))
                    ->action(function ($record) {
                        // Clone and resend
                        $newLog = $record->replicate();
                        $newLog->status = 'pending';
                        $newLog->sent_at = null;
                        $newLog->error_message = null;
                        $newLog->save();

                        // Dispatch job to send email
                        ResendEmailJob::dispatch($newLog);

                        Notification::make()
                            ->title(__('Email queued for resend'))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailLogs::route('/'),
            'view' => Pages\ViewEmailLog::route('/{record}'),
        ];
    }
}
