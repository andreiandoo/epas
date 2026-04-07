<?php

namespace App\Filament\Marketplace\Resources\TaxTemplateResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\TaxTemplateResource;
use App\Models\Event;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
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
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($o) => [$o->id => ($o->company_name ?? $o->name ?? 'Organizator #' . $o->id)])
                                ->toArray();
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set) {
                            $set('event_id', null);
                            $set('payout_id', null);
                        }),

                    Forms\Components\Select::make('event_id')
                        ->label('Eveniment')
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $organizerId = $get('organizer_id');
                            if (!$organizerId) return [];
                            $marketplace = static::getMarketplaceClient();
                            $now = now()->toDateString();

                            $events = Event::where('marketplace_organizer_id', $organizerId)
                                ->where('marketplace_client_id', $marketplace?->id)
                                ->get();

                            $live = $events->filter(fn ($e) => $e->event_date && $e->event_date >= $now)->sortBy('event_date');
                            $ended = $events->filter(fn ($e) => $e->event_date && $e->event_date < $now)->sortByDesc('event_date');
                            $noDate = $events->filter(fn ($e) => !$e->event_date);

                            return $live->concat($ended)->concat($noDate)
                                ->mapWithKeys(function ($e) use ($now) {
                                    $title = is_array($e->title)
                                        ? ($e->title['ro'] ?? $e->title['en'] ?? array_values($e->title)[0] ?? 'Untitled')
                                        : ($e->title ?? 'Event #' . $e->id);
                                    $status = (!$e->event_date) ? 'TBD' : ($e->event_date >= $now ? '🟢' : '🔴');
                                    $date = $e->event_date?->format('d.m.Y') ?? '';
                                    return [$e->id => "{$status} {$title} ({$date})"];
                                })
                                ->toArray();
                        })
                        ->searchable()
                        ->live()
                        ->helperText('Opțional — lasă gol pentru test fără date de eveniment.'),

                    Forms\Components\Select::make('payout_id')
                        ->label('Decont existent')
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $organizerId = $get('organizer_id');
                            if (!$organizerId) return [];
                            $marketplace = static::getMarketplaceClient();

                            $query = MarketplacePayout::where('marketplace_client_id', $marketplace?->id)
                                ->where('marketplace_organizer_id', $organizerId)
                                ->orderByDesc('created_at');

                            if ($get('event_id')) {
                                $query->where('event_id', $get('event_id'));
                            }

                            return $query->get()
                                ->mapWithKeys(fn ($p) => [
                                    $p->id => $p->reference . ' — ' . number_format($p->amount, 2) . ' ' . ($p->currency ?? 'RON') . ' (' . ($p->status ?? '') . ')',
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->helperText('Opțional — selectează un decont pentru a folosi datele lui reale (bilete, comision etc).'),
                ])
                ->action(function (array $data) {
                    $template = $this->record->fresh();
                    $marketplace = static::getMarketplaceClient();
                    $organizer = MarketplaceOrganizer::find($data['organizer_id']);
                    $event = !empty($data['event_id']) ? Event::with(['ticketTypes', 'venue'])->find($data['event_id']) : null;
                    $payout = !empty($data['payout_id']) ? MarketplacePayout::find($data['payout_id']) : null;
                    // Prefer event-specific tax registry
                    $taxRegistry = null;
                    if ($event && $event->marketplace_tax_registry_id) {
                        $taxRegistry = MarketplaceTaxRegistry::find($event->marketplace_tax_registry_id);
                    }
                    if (!$taxRegistry) {
                        $taxRegistry = MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace?->id)->active()->first();
                    }

                    // Build variables from real data
                    $variables = MarketplaceTaxTemplate::getVariablesForContext(
                        taxRegistry: $taxRegistry,
                        marketplace: $marketplace,
                        organizer: $organizer,
                        event: $event ?? $payout?->event,
                        payout: $payout,
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

                    $fullHtml = '<!DOCTYPE html><html lang="ro"><head>'
                        . '<meta charset="UTF-8">'
                        . '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>'
                        . '<style>'
                        . '@page { size: ' . ($orientation === 'landscape' ? 'A4 landscape' : 'A4') . '; margin: 5mm 6mm; }'
                        . '*, th, td, thead th, thead td { font-family: DejaVu Sans, Arial, sans-serif !important; }'
                        . 'body { margin: 0; padding: 0; background: #fff; color: #000; }'
                        . '</style></head><body>' . $combinedContent . '</body></html>';

                    $pdf = Pdf::loadHTML($fullHtml);
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
        $html = preg_replace('/\{\{\s*\w+\s*\}\}/', '', $html);

        // Decode HTML entities to UTF-8 for DomPDF compatibility
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Replace comma-below variants (U+0219/U+021B) with cedilla variants (U+015F/U+015E)
        // DomPDF's DejaVu Sans often lacks the comma-below glyphs
        $html = str_replace(
            ['ș', 'Ș', 'ț', 'Ț'],
            ['ş', 'Ş', 'ţ', 'Ţ'],
            $html
        );

        // Strip CSS properties not supported by DomPDF
        $html = preg_replace('/writing-mode\s*:\s*[^;"]+;?/', '', $html);
        $html = preg_replace('/transform\s*:\s*[^;"]+;?/', '', $html);

        // Replace font-weight:600 with font-weight:bold (DomPDF lacks 600 variant for DejaVu Sans)
        $html = preg_replace('/font-weight\s*:\s*600\b/', 'font-weight:bold', $html);

        // Compress spacing to fit on single page
        $html = preg_replace('/margin-top\s*:\s*1[4-9]px/', 'margin-top:6px', $html);
        $html = preg_replace('/margin-top\s*:\s*[2-9]\dpx/', 'margin-top:6px', $html);
        $html = preg_replace('/margin-bottom\s*:\s*1[4-9]px/', 'margin-bottom:4px', $html);
        $html = preg_replace('/margin-bottom\s*:\s*[2-9]\dpx/', 'margin-bottom:4px', $html);
        $html = preg_replace('/padding\s*:\s*16px/', 'padding:8px', $html);
        $html = preg_replace('/padding\s*:\s*12px\s+20px/', 'padding:6px 12px', $html);
        $html = preg_replace('/padding\s*:\s*16px\s+10px\s+12px\s+20px/', 'padding:8px 8px 6px 12px', $html);
        $html = preg_replace('/padding\s*:\s*16px\s+20px\s+12px\s+0/', 'padding:8px 12px 6px 0', $html);
        // Reduce font sizes slightly
        $html = preg_replace('/font-size\s*:\s*15pt/', 'font-size:13pt', $html);
        $html = preg_replace('/font-size\s*:\s*11\.5pt/', 'font-size:10pt', $html);
        $html = preg_replace('/font-size\s*:\s*11pt/', 'font-size:9.5pt', $html);
        $html = preg_replace('/font-size\s*:\s*10pt/', 'font-size:9pt', $html);

        return $html;
    }
}
