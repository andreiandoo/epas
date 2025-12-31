<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use App\Models\Tenant;
use App\Services\ContractPdfService;
use Barryvdh\DomPDF\Facade\Pdf;

class ContractController extends Controller
{
    public function __construct(
        private ContractPdfService $contractService
    ) {}

    /**
     * Download tenant contract
     */
    public function download(Tenant $tenant)
    {
        return $this->contractService->download($tenant);
    }

    /**
     * Preview tenant contract in browser
     */
    public function preview(Tenant $tenant)
    {
        return $this->contractService->stream($tenant);
    }

    /**
     * Preview contract template with sample data
     */
    public function previewTemplate(ContractTemplate $template)
    {
        // Create a sample tenant for preview
        $sampleTenant = new Tenant([
            'id' => 1,
            'name' => 'Sample Company SRL',
            'public_name' => 'Sample Events',
            'company_name' => 'Sample Company SRL',
            'cui' => 'RO12345678',
            'reg_com' => 'J40/1234/2024',
            'address' => '123 Sample Street',
            'city' => 'Bucharest',
            'state' => 'Bucharest',
            'country' => 'Romania',
            'vat_payer' => true,
            'contact_first_name' => 'John',
            'contact_last_name' => 'Doe',
            'contact_email' => 'john.doe@example.com',
            'contact_phone' => '+40 721 123 456',
            'contact_position' => 'Director',
            'bank_name' => 'Sample Bank',
            'bank_account' => 'RO49AAAA1B31007593840000',
            'work_method' => $template->work_method ?? 'exclusive',
            'plan' => $template->plan ?? '1percent',
            'commission_rate' => match ($template->work_method) {
                'mixed' => 2,
                'reseller' => 3,
                default => 1,
            },
            'domain' => 'sample-events.com',
            'contract_number' => 'CTR-' . now()->year . '-00001',
        ]);

        $settings = \App\Models\Setting::first();
        $content = $template->processContent($sampleTenant, $settings);

        $pdf = Pdf::loadView('pdfs.contract', [
            'content' => $content,
            'tenant' => $sampleTenant,
            'template' => $template,
            'settings' => $settings,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("Template-Preview-{$template->name}.pdf");
    }
}
