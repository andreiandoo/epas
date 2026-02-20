<?php

namespace App\Filament\Resources\ChatConversationResource\Pages;

use App\Filament\Resources\ChatConversationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListChatConversations extends ListRecords
{
    protected static string $resource = ChatConversationResource::class;

    public function getTabs(): array
    {
        return [
            'escalated' => Tab::make('Escalated')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'escalated'))
                ->badge(fn () => $this->getModel()::where('status', 'escalated')->count())
                ->badgeColor('danger'),
            'open' => Tab::make('Open')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'open'))
                ->badge(fn () => $this->getModel()::where('status', 'open')->count())
                ->badgeColor('info'),
            'resolved' => Tab::make('Resolved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'resolved')),
            'all' => Tab::make('All'),
        ];
    }
}
