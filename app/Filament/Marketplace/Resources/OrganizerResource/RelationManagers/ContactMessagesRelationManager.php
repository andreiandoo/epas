<?php

namespace App\Filament\Marketplace\Resources\OrganizerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ContactMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'contactMessages';

    protected static ?string $title = 'Mesaje';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nume')
                    ->getStateUsing(fn ($record) => $record->first_name . ' ' . $record->last_name)
                    ->searchable(query: function ($query, string $search) {
                        $query->where('first_name', 'ilike', "%{$search}%")
                              ->orWhere('last_name', 'ilike', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('message')
                    ->label('Mesaj')
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->message)
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'unread' => 'Necitit',
                        'read' => 'Citit',
                        'replied' => 'Răspuns',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'unread' => 'danger',
                        'read' => 'warning',
                        'replied' => 'success',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_message')
                    ->label('Vezi')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn ($record) => 'Mesaj de la ' . $record->first_name . ' ' . $record->last_name)
                    ->modalContent(fn ($record) => new \Illuminate\Support\HtmlString(
                        '<div style="font-size:14px;line-height:1.6;">'
                        . '<p><strong>Nume:</strong> ' . e($record->first_name . ' ' . $record->last_name) . '</p>'
                        . '<p><strong>Email:</strong> ' . e($record->email) . '</p>'
                        . ($record->phone ? '<p><strong>Telefon:</strong> ' . e($record->phone) . '</p>' : '')
                        . '<p><strong>Data:</strong> ' . $record->created_at->format('d.m.Y H:i') . '</p>'
                        . '<hr style="margin:12px 0;border:none;border-top:1px solid #e5e7eb;">'
                        . '<div style="white-space:pre-wrap;background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #e5e7eb;">' . e($record->message) . '</div>'
                        . '</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Închide')
                    ->after(function ($record) {
                        if ($record->status === 'unread') {
                            $record->update(['status' => 'read']);
                        }
                    }),

                Tables\Actions\Action::make('mark_replied')
                    ->label('Marcat răspuns')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'replied')
                    ->requiresConfirmation(false)
                    ->action(fn ($record) => $record->update(['status' => 'replied'])),

                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Niciun mesaj')
            ->emptyStateDescription('Mesajele trimise prin formularul de contact de pe profilul public vor apărea aici.');
    }
}
