<?php

namespace App\Filament\Pages;

use App\Models\ApiKey;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class ApiKeys extends Page implements HasTable
{
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-key';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 70;
    protected static ?string $title = 'API Keys';
    protected string $view = 'filament.pages.api-keys';

    public ?string $newKeyValue = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Generate New Key')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('Key Name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('A descriptive name for this API key'),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->helperText('What will this key be used for?'),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expires At')
                        ->helperText('Leave empty for no expiration'),
                ])
                ->action(function (array $data) {
                    $key = Str::random(64);

                    ApiKey::create([
                        'name' => $data['name'],
                        'key' => $key,
                        'description' => $data['description'] ?? null,
                        'expires_at' => $data['expires_at'] ?? null,
                    ]);

                    $this->newKeyValue = $key;

                    Notification::make()
                        ->title('API Key Created')
                        ->body('Copy the key now - it won\'t be shown again!')
                        ->warning()
                        ->persistent()
                        ->send();
                }),
        ];
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(ApiKey::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('key')
                    ->label('Key (partial)')
                    ->getStateUsing(fn ($record) => substr($record->key, 0, 8) . '...' . substr($record->key, -4))
                    ->copyable()
                    ->copyableState(fn ($record) => $record->key),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->since()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['is_active' => !$record->is_active])),

                Tables\Actions\Action::make('copy')
                    ->label('Copy Key')
                    ->icon('heroicon-o-clipboard')
                    ->action(function ($record) {
                        Notification::make()
                            ->title('Key copied to clipboard')
                            ->success()
                            ->send();
                    })
                    ->extraAttributes(fn ($record) => [
                        'x-on:click' => "navigator.clipboard.writeText('{$record->key}')",
                    ]),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && $user->isSuperAdmin();
    }
}
