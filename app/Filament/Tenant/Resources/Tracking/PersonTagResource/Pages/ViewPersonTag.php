<?php

namespace App\Filament\Tenant\Resources\Tracking\PersonTagResource\Pages;

use App\Filament\Tenant\Resources\Tracking\PersonTagResource;
use App\Models\Tracking\PersonTag;
use App\Models\Tracking\PersonTagLog;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPersonTag extends ViewRecord
{
    protected static string $resource = PersonTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->hidden(fn() => $this->record->is_system),
            Actions\DeleteAction::make()
                ->hidden(fn() => $this->record->is_system),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Tag Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('slug')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('category')
                            ->badge()
                            ->formatStateUsing(fn($state) => PersonTag::CATEGORIES[$state] ?? $state),
                        Infolists\Components\ColorEntry::make('color'),
                        Infolists\Components\TextEntry::make('icon'),
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('assignments_count')
                            ->label('Persons Tagged')
                            ->state(fn(PersonTag $record) => $record->assignments()->count()),

                        Infolists\Components\TextEntry::make('recent_assignments')
                            ->label('Assigned Last 7 Days')
                            ->state(fn(PersonTag $record) =>
                                PersonTagLog::forTag($record->id)
                                    ->ofAction('assigned')
                                    ->where('created_at', '>=', now()->subDays(7))
                                    ->count()
                            ),

                        Infolists\Components\TextEntry::make('recent_removals')
                            ->label('Removed Last 7 Days')
                            ->state(fn(PersonTag $record) =>
                                PersonTagLog::forTag($record->id)
                                    ->ofAction('removed')
                                    ->where('created_at', '>=', now()->subDays(7))
                                    ->count()
                            ),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Auto-Tagging Rule')
                    ->schema([
                        Infolists\Components\TextEntry::make('rule.name')
                            ->label('Rule Name'),
                        Infolists\Components\TextEntry::make('rule.match_type')
                            ->label('Match Type')
                            ->badge(),
                        Infolists\Components\IconEntry::make('rule.is_active')
                            ->label('Active')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('rule.last_run_at')
                            ->label('Last Run')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('rule.last_run_count')
                            ->label('Last Run Count'),
                    ])
                    ->columns(3)
                    ->visible(fn(PersonTag $record) => $record->rule !== null),
            ]);
    }
}
