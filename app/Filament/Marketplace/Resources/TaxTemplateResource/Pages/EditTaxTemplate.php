<?php

namespace App\Filament\Marketplace\Resources\TaxTemplateResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\TaxTemplateResource;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceTaxRegistry;
use App\Models\MarketplaceTaxTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditTaxTemplate extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = TaxTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_generate')
                ->label('Testează')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('organizer_id')
                        ->label('Organizator')
                        ->options(function () {
                            $marketplace = static::getMarketplaceClient();
                            if (!$marketplace) return [];
                            return MarketplaceOrganizer::where('marketplace_client_id', $marketplace->id)
                                ->orderBy('company_name')
                                ->pluck('company_name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (\Filament\Schemas\Components\Utilities\Set $set) => $set('event_id', null)),

                    Forms\Components\Select::make('event_id')
                        ->label('Eveniment')
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $organizerId = $get('organizer_id');
                            if (!$organizerId) return [];
                            return MarketplaceEvent::where('marketplace_organizer_id', $organizerId)
                                ->orderByDesc('starts_at')
                                ->get()
                                ->mapWithKeys(fn ($e) => [
                                    $e->id => $e->name . ' (' . ($e->starts_at?->format('d.m.Y') ?? 'N/A') . ')',
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->helperText('Opțional — lasă gol pentru test fără date de eveniment.'),
                ])
                ->action(function (array $data) {
                    $template = $this->record;
                    $marketplace = static::getMarketplaceClient();
                    $organizer = MarketplaceOrganizer::find($data['organizer_id']);
                    $event = !empty($data['event_id']) ? MarketplaceEvent::with(['ticketTypes', 'venue'])->find($data['event_id']) : null;
                    $taxRegistry = MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace?->id)->active()->first();

                    // Build variables from real data
                    $variables = MarketplaceTaxTemplate::getVariablesForContext(
                        taxRegistry: $taxRegistry,
                        marketplace: $marketplace,
                        organizer: $organizer,
                        event: $event,
                    );

                    // Generate PDF pages
                    $pages = [];

                    if ($template->html_content) {
                        $pages[] = [
                            'content' => $this->processTemplateContent($template->html_content, $variables),
                            'orientation' => $template->page_orientation ?? 'portrait',
                        ];
                    }

                    if ($template->html_content_page_2) {
                        $pages[] = [
                            'content' => $this->processTemplateContent($template->html_content_page_2, $variables),
                            'orientation' => $template->page_2_orientation ?? $template->page_orientation ?? 'portrait',
                        ];
                    }

                    if (empty($pages)) {
                        \Filament\Notifications\Notification::make()
                            ->title('Template-ul nu are conținut HTML.')
                            ->warning()
                            ->send();
                        return;
                    }

                    // Combine all pages with page breaks
                    $combinedContent = implode('<div style="page-break-before:always;"></div>', array_column($pages, 'content'));
                    $orientation = $pages[0]['orientation'];

                    $pdf = Pdf::loadView('pdfs.tax-template', [
                        'content' => $combinedContent,
                        'orientation' => $orientation,
                    ]);
                    $pdf->setPaper('a4', $orientation);
                    $pdf->setOption('isHtml5ParserEnabled', true);
                    $pdf->setOption('isRemoteEnabled', true);

                    $filename = 'test-' . ($template->slug ?? 'template') . '-' . now()->format('Y-m-d_His') . '.pdf';

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, $filename, [
                        'Content-Type' => 'application/pdf',
                    ]);
                })
                ->modalHeading('Testează Generare Document')
                ->modalDescription('Selectează un organizator și opțional un eveniment pentru a genera un PDF de test cu date reale.')
                ->modalSubmitActionLabel('Generează PDF')
                ->modalIcon('heroicon-o-document-arrow-down'),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // When source mode is ON, the textarea has the raw HTML with inline CSS preserved.
        // The RichEditor (Trix) strips inline styles, so we bypass it entirely.
        if (!empty($data['page1_source_mode']) && isset($data['html_content_source'])) {
            $data['html_content'] = $data['html_content_source'];
        }

        if (!empty($data['page2_source_mode']) && isset($data['html_content_page_2_source'])) {
            $data['html_content_page_2'] = $data['html_content_page_2_source'];
        }

        // Remove virtual fields that shouldn't be persisted
        unset(
            $data['page1_source_mode'],
            $data['page2_source_mode'],
            $data['html_content_source'],
            $data['html_content_page_2_source'],
        );

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Process template HTML with variables
     */
    private function processTemplateContent(string $html, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_array($value)) continue;
            $html = preg_replace(
                '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/',
                (string) ($value ?? ''),
                $html
            );
        }

        // Remove any remaining unreplaced variables
        return preg_replace('/\{\{\s*\w+\s*\}\}/', '', $html);
    }
}
