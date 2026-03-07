<?php

namespace App\Filament\Marketplace\Resources\TaxTemplateResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\TaxTemplateResource;
use Filament\Actions;
use Filament\Schemas;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListTaxTemplates extends ListRecords
{
    use HasMarketplaceContext;

    protected static string $resource = TaxTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('contractSettings')
                ->label('Contract Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->modalHeading('Contract & Document Settings')
                ->modalDescription('Configure contract numbering and upload your signature image.')
                ->modalWidth('lg')
                ->form([
                    Schemas\Components\Section::make('Contract Numbering')
                        ->description('Set the next contract number. Each generated organizer contract will use this number and auto-increment it.')
                        ->schema([
                            Schemas\Components\TextInput::make('next_contract_number')
                                ->label('Next Contract Number')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->helperText('This number will be used for the next generated contract, then auto-incremented.'),
                        ]),
                    Schemas\Components\Section::make('Signature Image')
                        ->description('Upload a signature image to use in contracts via the {{marketplace_signature_image}} variable.')
                        ->schema([
                            Schemas\Components\FileUpload::make('signature_image')
                                ->label('Signature Image')
                                ->image()
                                ->disk('public')
                                ->directory('marketplace-signatures')
                                ->maxSize(1024) // 1MB
                                ->helperText('Upload a PNG/JPG signature image (max 1MB). Use {{marketplace_signature_image}} in your template to place it.'),
                        ]),
                ])
                ->fillForm(function () {
                    $marketplace = static::getMarketplaceClient();
                    return [
                        'next_contract_number' => $marketplace?->next_contract_number ?? 1,
                        'signature_image' => $marketplace?->signature_image,
                    ];
                })
                ->action(function (array $data) {
                    $marketplace = static::getMarketplaceClient();
                    if (!$marketplace) {
                        return;
                    }

                    $updateData = [
                        'next_contract_number' => (int) $data['next_contract_number'],
                    ];

                    // Handle signature image
                    if (array_key_exists('signature_image', $data)) {
                        $updateData['signature_image'] = $data['signature_image'];
                    }

                    $marketplace->update($updateData);

                    Notification::make()
                        ->title('Settings saved')
                        ->body("Next contract number: {$data['next_contract_number']}")
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
