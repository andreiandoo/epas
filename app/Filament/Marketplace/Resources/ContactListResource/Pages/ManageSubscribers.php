<?php

namespace App\Filament\Marketplace\Resources\ContactListResource\Pages;

use App\Filament\Marketplace\Resources\ContactListResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceCustomer;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;

class ManageSubscribers extends Page implements HasTable
{
    use InteractsWithTable;
    use HasMarketplaceContext;

    protected static string $resource = ContactListResource::class;
    protected string $view = 'filament.marketplace.pages.manage-subscribers';

    public $record;

    public function mount($record): void
    {
        $this->record = static::getResource()::resolveRecordRouteBinding($record);
    }

    public function getTitle(): string
    {
        return "Manage Subscribers: {$this->record->name}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MarketplaceCustomer::query()
                    ->whereHas('contactLists', function ($q) {
                        $q->where('marketplace_contact_lists.id', $this->record->id);
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contactLists.pivot.status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $pivot = $record->contactLists->firstWhere('id', $this->record->id)?->pivot;
                        return $pivot?->status ?? 'unknown';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'subscribed' => 'success',
                        'unsubscribed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label('Subscribed')
                    ->getStateUsing(function ($record) {
                        $pivot = $record->contactLists->firstWhere('id', $this->record->id)?->pivot;
                        return $pivot?->subscribed_at;
                    })
                    ->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add_subscriber')
                    ->label('Add Subscriber')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $marketplace = static::getMarketplaceClient();
                                return MarketplaceCustomer::where('marketplace_client_id', $marketplace?->id)
                                    ->whereDoesntHave('contactLists', function ($q) {
                                        $q->where('marketplace_contact_lists.id', $this->record->id);
                                    })
                                    ->where(function ($q) use ($search) {
                                        $q->where('email', 'like', "%{$search}%")
                                          ->orWhere('first_name', 'like', "%{$search}%")
                                          ->orWhere('last_name', 'like', "%{$search}%");
                                    })
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [$c->id => "{$c->full_name} <{$c->email}>"]);
                            })
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $this->record->addSubscriber($data['customer_id']);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('unsubscribe')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $this->record->removeSubscriber($record->id);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('unsubscribe_selected')
                    ->label('Unsubscribe Selected')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        foreach ($records as $record) {
                            $this->record->removeSubscriber($record->id);
                        }
                    }),
            ]);
    }
}
