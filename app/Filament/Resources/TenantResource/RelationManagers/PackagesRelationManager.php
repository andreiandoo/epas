<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Models\TenantPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class PackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'packages';

    protected static ?string $title = 'Deployment Packages';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->columns([
                Tables\Columns\TextColumn::make('domain.domain')
                    ->label('Domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'generating',
                        'success' => 'ready',
                        'danger' => 'expired',
                        'gray' => 'invalidated',
                    ]),
                Tables\Columns\TextColumn::make('file_size')
                    ->formatStateUsing(fn (TenantPackage $record) => $record->getFileSizeFormatted())
                    ->label('Size'),
                Tables\Columns\TextColumn::make('download_count')
                    ->label('Downloads')
                    ->sortable(),
                Tables\Columns\TextColumn::make('enabled_modules')
                    ->formatStateUsing(fn (?array $state) => $state ? count($state) . ' modules' : '-')
                    ->label('Modules'),
                Tables\Columns\TextColumn::make('generated_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_downloaded_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'generating' => 'Generating',
                        'ready' => 'Ready',
                        'expired' => 'Expired',
                        'invalidated' => 'Invalidated',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    // Download package
                    Tables\Actions\Action::make('download')
                        ->label('Download Package')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (TenantPackage $record) {
                            if (!$record->file_path || !Storage::exists($record->file_path)) {
                                Notification::make()
                                    ->title('Package file not found')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $record->incrementDownloadCount();

                            return response()->download(
                                Storage::path($record->file_path),
                                "tixello-{$record->domain->domain}-v{$record->version}.js"
                            );
                        })
                        ->visible(fn (TenantPackage $record) => $record->isReady()),

                    // View installation code
                    Tables\Actions\Action::make('viewCode')
                        ->label('View Install Code')
                        ->icon('heroicon-o-code-bracket')
                        ->color('info')
                        ->modalHeading('Installation Code')
                        ->modalContent(fn (TenantPackage $record) => view('filament.modals.package-install-code', [
                            'package' => $record,
                        ]))
                        ->modalSubmitAction(false)
                        ->visible(fn (TenantPackage $record) => $record->isReady()),

                    // View enabled modules
                    Tables\Actions\Action::make('viewModules')
                        ->label('View Modules')
                        ->icon('heroicon-o-squares-2x2')
                        ->color('gray')
                        ->modalHeading('Enabled Modules')
                        ->modalContent(fn (TenantPackage $record) => view('filament.modals.package-modules', [
                            'package' => $record,
                        ]))
                        ->modalSubmitAction(false),

                    // Invalidate package
                    Tables\Actions\Action::make('invalidate')
                        ->label('Invalidate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Invalidate Package')
                        ->modalDescription('This will invalidate the package. The tenant will need to download a new version.')
                        ->action(function (TenantPackage $record) {
                            $record->invalidate();

                            Notification::make()
                                ->title('Package invalidated')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (TenantPackage $record) => $record->isReady()),
                ]),
            ])
            ->defaultSort('generated_at', 'desc');
    }
}
