<?php

namespace App\Services;

use App\Models\ContractTemplate;
use App\Models\ContractVersion;
use App\Models\Setting;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ContractPdfService
{
    /**
     * Generate a PDF contract for a tenant
     */
    public function generate(Tenant $tenant, ?ContractTemplate $template = null): string
    {
        // Find the appropriate template
        $template = $template ?? ContractTemplate::findForTenant($tenant);

        if (!$template) {
            throw new \Exception('No contract template found for this tenant.');
        }

        // Get settings
        $settings = Setting::first();

        // Process template content with tenant variables
        $content = $template->processContent($tenant, $settings);

        // Generate contract number if not exists
        if (!$tenant->contract_number) {
            $tenant->contract_number = 'CTR-' . now()->year . '-' . str_pad($tenant->id, 5, '0', STR_PAD_LEFT);
            $tenant->save();
        }

        // Generate PDF
        $pdf = Pdf::loadView('pdfs.contract', [
            'content' => $content,
            'tenant' => $tenant,
            'template' => $template,
            'settings' => $settings,
        ]);

        // Set PDF options
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);

        // Generate filename
        $filename = $this->generateFilename($tenant);

        // Store the PDF
        $path = 'contracts/' . $filename;
        Storage::disk('public')->put($path, $pdf->output());

        // Update tenant record
        $tenant->update([
            'contract_file' => $path,
            'contract_generated_at' => now(),
            'contract_template_id' => $template->id,
            'contract_status' => 'generated',
            'contract_renewal_date' => now()->addYear(),
        ]);

        // Create version record
        $this->createVersion($tenant, $template, $path);

        return $path;
    }

    /**
     * Create a contract version record
     */
    protected function createVersion(Tenant $tenant, ContractTemplate $template, string $path): ContractVersion
    {
        // Get the next version number
        $lastVersion = $tenant->contractVersions()->max('version_number') ?? 0;
        $versionNumber = $lastVersion + 1;

        // Create tenant data snapshot
        $snapshot = [
            'company_name' => $tenant->company_name,
            'cui' => $tenant->cui,
            'reg_com' => $tenant->reg_com,
            'address' => $tenant->address,
            'city' => $tenant->city,
            'state' => $tenant->state,
            'country' => $tenant->country,
            'contact_first_name' => $tenant->contact_first_name,
            'contact_last_name' => $tenant->contact_last_name,
            'contact_email' => $tenant->contact_email,
            'work_method' => $tenant->work_method,
            'plan' => $tenant->plan,
            'commission_rate' => $tenant->commission_rate,
        ];

        return ContractVersion::create([
            'tenant_id' => $tenant->id,
            'contract_template_id' => $template->id,
            'version_number' => $versionNumber,
            'contract_number' => $tenant->contract_number,
            'file_path' => $path,
            'status' => 'generated',
            'generated_at' => now(),
            'tenant_data_snapshot' => $snapshot,
        ]);
    }

    /**
     * Get the PDF content without saving
     */
    public function getPdfContent(Tenant $tenant, ?ContractTemplate $template = null): string
    {
        $template = $template ?? ContractTemplate::findForTenant($tenant);

        if (!$template) {
            throw new \Exception('No contract template found for this tenant.');
        }

        $settings = Setting::first();
        $content = $template->processContent($tenant, $settings);

        $pdf = Pdf::loadView('pdfs.contract', [
            'content' => $content,
            'tenant' => $tenant,
            'template' => $template,
            'settings' => $settings,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);

        return $pdf->output();
    }

    /**
     * Download the contract PDF
     */
    public function download(Tenant $tenant)
    {
        if (!$tenant->contract_file || !Storage::disk('public')->exists($tenant->contract_file)) {
            // Generate if doesn't exist
            $this->generate($tenant);
        }

        $filename = $this->generateFilename($tenant);

        return Storage::disk('public')->download($tenant->contract_file, $filename);
    }

    /**
     * Stream the contract PDF
     */
    public function stream(Tenant $tenant)
    {
        $template = ContractTemplate::findForTenant($tenant);

        if (!$template) {
            throw new \Exception('No contract template found for this tenant.');
        }

        $settings = Setting::first();
        $content = $template->processContent($tenant, $settings);

        $pdf = Pdf::loadView('pdfs.contract', [
            'content' => $content,
            'tenant' => $tenant,
            'template' => $template,
            'settings' => $settings,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream($this->generateFilename($tenant));
    }

    /**
     * Regenerate contract for a tenant
     */
    public function regenerate(Tenant $tenant, ?ContractTemplate $template = null): string
    {
        // Delete old contract file if exists
        if ($tenant->contract_file && Storage::disk('public')->exists($tenant->contract_file)) {
            Storage::disk('public')->delete($tenant->contract_file);
        }

        return $this->generate($tenant, $template);
    }

    /**
     * Generate filename for contract
     */
    protected function generateFilename(Tenant $tenant): string
    {
        $companyName = preg_replace('/[^a-zA-Z0-9]/', '-', $tenant->company_name ?? $tenant->name);
        $contractNumber = $tenant->contract_number ?? 'CTR-' . $tenant->id;

        return "Contract-{$contractNumber}-{$companyName}.pdf";
    }

    /**
     * Get the full path to contract file
     */
    public function getContractPath(Tenant $tenant): ?string
    {
        if (!$tenant->contract_file) {
            return null;
        }

        return Storage::disk('public')->path($tenant->contract_file);
    }

    /**
     * Get the URL to contract file
     */
    public function getContractUrl(Tenant $tenant): ?string
    {
        if (!$tenant->contract_file) {
            return null;
        }

        return Storage::disk('public')->url($tenant->contract_file);
    }
}
