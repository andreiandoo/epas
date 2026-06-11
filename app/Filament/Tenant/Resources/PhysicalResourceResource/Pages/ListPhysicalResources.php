<?php

namespace App\Filament\Tenant\Resources\PhysicalResourceResource\Pages;

use App\Filament\Tenant\Resources\PhysicalResourceResource;
use App\Models\Leisure\PhysicalResource;
use App\Models\Leisure\PhysicalResourceType;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListPhysicalResources extends ListRecords
{
    protected static string $resource = PhysicalResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('bulkAdd')
                ->label('Adaugă în bulk')
                ->icon('heroicon-o-squares-plus')
                ->color('success')
                ->modalHeading('Adaugă mai multe unități deodată')
                ->modalDescription('Generează N unități pentru un tip de resursă, cu nume auto-numerotate și QR-uri unice.')
                ->modalSubmitActionLabel('Generează')
                ->form([
                    Forms\Components\Select::make('type_id')
                        ->label('Tip resursă')
                        ->options(fn () => PhysicalResourceType::where('tenant_id', auth()->user()?->tenant?->id)
                            ->where('is_active', true)
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('count')
                        ->label('Câte unități')
                        ->numeric()->minValue(1)->maxValue(200)
                        ->default(5)
                        ->required(),
                    Forms\Components\TextInput::make('name_prefix')
                        ->label('Prefix nume')
                        ->placeholder('ex: Kayak Roșu #')
                        ->helperText('Numele final = prefix + index începând de la "start_index".')
                        ->required(),
                    Forms\Components\TextInput::make('start_index')
                        ->label('Index pornire')
                        ->numeric()->default(1)->minValue(1)
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'available' => 'Disponibilă',
                            'maintenance' => 'Mentenanță',
                        ])
                        ->default('available'),
                ])
                ->action(function (array $data) {
                    $tenantId = auth()->user()?->tenant?->id;
                    $type = PhysicalResourceType::find($data['type_id']);
                    if (! $type || $type->tenant_id !== $tenantId) {
                        Notification::make()->danger()->title('Tip invalid')->send();
                        return;
                    }
                    $created = 0;
                    for ($i = 0; $i < (int) $data['count']; $i++) {
                        $idx = (int) $data['start_index'] + $i;
                        PhysicalResource::create([
                            'tenant_id' => $tenantId,
                            'physical_resource_type_id' => $type->id,
                            'resource_type' => $type->slug,
                            'name' => $data['name_prefix'] . $idx,
                            'label' => strtoupper(Str::slug($type->slug)) . '-' . str_pad((string) $idx, 2, '0', STR_PAD_LEFT),
                            'qr_code' => PhysicalResource::generateQrCode($tenantId, $type->slug),
                            'status' => $data['status'] ?? 'available',
                            'linked_ticket_type_ids' => $type->linked_ticket_type_ids,
                        ]);
                        $created++;
                    }
                    Notification::make()
                        ->success()->title("Am adăugat {$created} unități")
                        ->body("Tip: {$type->name}")
                        ->send();
                }),

            Actions\CreateAction::make()->label('Adaugă o unitate'),
        ];
    }
}
