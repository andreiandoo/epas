<?php

namespace App\Services\Onboarding;

use App\Jobs\GeneratePackageJob;
use App\Mail\ContractMail;
use App\Mail\Signup\TenantApprovedMail;
use App\Mail\Signup\TenantRejectedMail;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\TenantSite;
use App\Models\User;
use App\Services\CloudflareService;
use App\Services\ContractPdfService;
use App\Services\TenantSites\SiteConfigBuilder;
use App\Services\TenantSites\TemplateRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aprobarea / respingerea înscrierilor din coada „Tenanți de aprobat”. La aprobare
 * se aplică alegerile din înscriere: domeniul, site-ul (ca ciornă) și contractul.
 */
class TenantApprovalService
{
    public function __construct(
        private CloudflareService $cloudflare,
        private SiteConfigBuilder $siteBuilder,
        private TemplateRegistry $templates,
    ) {}

    public function approve(Tenant $tenant, User $admin): void
    {
        if (! $tenant->isAwaitingApproval()) {
            return;
        }

        $domains = DB::transaction(function () use ($tenant, $admin) {
            $tenant->update([
                'status' => 'active',
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'rejected_at' => null,
                'rejected_reason' => null,
                'billing_starts_at' => now(),
                'billing_cycle_days' => $tenant->billing_cycle_days ?: 30,
            ]);

            $domains = $this->createDomains($tenant);
            $this->createSiteDraft($tenant);

            return $domains;
        });

        foreach ($domains as $domain) {
            GeneratePackageJob::dispatch($domain);
        }

        $this->sendContract($tenant);

        try {
            app(SignupMailer::class)->send($tenant->contact_email, new TenantApprovedMail($tenant->fresh(['domains.verifications'])), 'signup_approved', $tenant->id);
        } catch (\Throwable $e) {
            Log::error('Signup: emailul de aprobare nu a plecat', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
        }
    }

    public function reject(Tenant $tenant, User $admin, string $reason): void
    {
        if (! $tenant->isAwaitingApproval()) {
            return;
        }

        $tenant->update([
            'status' => 'rejected',
            'approval_status' => 'rejected',
            'rejected_at' => now(),
            'rejected_reason' => $reason,
            'approved_by' => $admin->id,
        ]);

        try {
            app(SignupMailer::class)->send($tenant->contact_email, new TenantRejectedMail($tenant), 'signup_rejected', $tenant->id);
        } catch (\Throwable $e) {
            Log::error('Signup: emailul de respingere nu a plecat', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
        }
    }

    /** @return Domain[] */
    private function createDomains(Tenant $tenant): array
    {
        $site = $tenant->signup_choices['site'] ?? null;
        $mode = $site['mode'] ?? null;

        if ($mode === 'subdomain' && ! empty($site['subdomain'])) {
            $subdomain = $site['subdomain'];
            $base = $this->cloudflare->getBaseDomain();
            $full = "{$subdomain}.{$base}";
            if (Domain::where('domain', $full)->exists()) {
                Log::warning('Signup: subdomeniul a fost luat între timp', ['tenant_id' => $tenant->id, 'domain' => $full]);

                return [];
            }

            $recordId = null;
            $notes = null;
            try {
                $recordId = $this->cloudflare->createSubdomainRecord($subdomain)['id'] ?? null;
            } catch (\Throwable $e) {
                $notes = 'DNS record creation skipped: ' . $e->getMessage();
                Log::error('Signup: înregistrarea DNS a eșuat', ['tenant_id' => $tenant->id, 'subdomain' => $subdomain, 'error' => $e->getMessage()]);
            }

            return [Domain::create([
                'tenant_id' => $tenant->id,
                'domain' => $full,
                'is_primary' => true,
                'is_active' => true,
                'is_managed_subdomain' => true,
                'subdomain' => $subdomain,
                'base_domain' => $base,
                'cloudflare_record_id' => $recordId,
                'notes' => $notes,
                'activated_at' => now(),
            ])];
        }

        if ($mode === 'own' && ! empty($site['domain']) && ! Domain::where('domain', $site['domain'])->exists()) {
            $domain = Domain::create([
                'tenant_id' => $tenant->id,
                'domain' => $site['domain'],
                'is_primary' => true,
                'is_active' => false,
                'is_managed_subdomain' => false,
            ]);
            $domain->verifications()->create([
                'tenant_id' => $tenant->id,
                'verification_method' => 'dns_txt',
                'status' => 'pending',
            ]);

            return [$domain];
        }

        return [];
    }

    /** Site-ul rămâne ciornă; îl publică adminul din Tenants → Site, după ce tenantul are logo și conținut. */
    private function createSiteDraft(Tenant $tenant): void
    {
        $template = $tenant->signup_choices['template']['slug'] ?? null;
        if (! $template || ! $this->templates->availableTo($template, $tenant->id)) {
            return;
        }

        TenantSite::firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['template' => $template, 'draft' => $this->siteBuilder->defaultDraft($tenant, $template)]
        );
    }

    private function sendContract(Tenant $tenant): void
    {
        try {
            $path = app(ContractPdfService::class)->generate($tenant);
            $name = 'Contract-' . ($tenant->contract_number ?? 'CTR-' . $tenant->id) . '.pdf';
            if (app(SignupMailer::class)->send($tenant->contact_email, new ContractMail($tenant->fresh(), $path), 'contract', $tenant->id, [$name => $path])) {
                $tenant->update(['contract_sent_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::error('Signup: contractul nu s-a generat / trimis', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
        }
    }
}
