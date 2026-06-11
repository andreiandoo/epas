<?php

namespace App\Services\CRM;

use App\Models\CustomerSegment;
use App\Models\EmailCampaign;
use App\Models\CampaignRecipient;
use App\Models\AutomationWorkflow;
use App\Models\AutomationEnrollment;
use App\Models\CustomerActivity;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CRMService
{
    /**
     * Create customer segment
     */
    public function createSegment(array $data): CustomerSegment
    {
        $segment = CustomerSegment::create([
            'tenant_id' => $data['tenant_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'conditions' => $data['conditions'],
            'is_dynamic' => $data['is_dynamic'] ?? true,
        ]);

        if ($segment->is_dynamic) {
            $this->calculateSegmentMembers($segment);
        }

        return $segment;
    }

    /**
     * Calculate segment membership
     */
    public function calculateSegmentMembers(CustomerSegment $segment): int
    {
        $query = Customer::where('tenant_id', $segment->tenant_id);

        foreach ($segment->conditions as $condition) {
            $query = $this->applyCondition($query, $condition);
        }

        $customerIds = $query->pluck('id');

        // Sync members
        $segment->customers()->sync(
            $customerIds->mapWithKeys(fn($id) => [$id => ['added_at' => now()]])->toArray()
        );

        $segment->update([
            'member_count' => $customerIds->count(),
            'last_calculated_at' => now(),
        ]);

        return $customerIds->count();
    }

    /**
     * Create email campaign
     */
    public function createCampaign(array $data): EmailCampaign
    {
        return EmailCampaign::create([
            'tenant_id' => $data['tenant_id'],
            'segment_id' => $data['segment_id'] ?? null,
            'name' => $data['name'],
            'subject' => $data['subject'],
            'content' => $data['content'],
            'from_name' => $data['from_name'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            'status' => 'draft',
        ]);
    }

    /**
     * Schedule campaign
     */
    public function scheduleCampaign(EmailCampaign $campaign, \DateTime $scheduledAt): EmailCampaign
    {
        // Populate recipients from segment
        if ($campaign->segment_id) {
            $segment = CustomerSegment::find($campaign->segment_id);
            foreach ($segment->customers as $customer) {
                CampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'customer_id' => $customer->id,
                    'email' => $customer->email,
                    'status' => 'pending',
                ]);
            }
        }

        $campaign->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'total_recipients' => $campaign->recipients()->count(),
        ]);

        return $campaign->fresh();
    }

    /**
     * Send campaign
     */
    public function sendCampaign(EmailCampaign $campaign): array
    {
        $campaign->update(['status' => 'sending']);

        $sent = 0;
        foreach ($campaign->recipients()->where('status', 'pending')->cursor() as $recipient) {
            // Queue email sending
            // Mail::to($recipient->email)->queue(new CampaignEmail($campaign, $recipient));

            $recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            $sent++;
        }

        $campaign->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_count' => $sent,
        ]);

        return ['success' => true, 'sent' => $sent];
    }

    /**
     * Track email open
     */
    public function trackOpen(int $recipientId): void
    {
        $recipient = CampaignRecipient::find($recipientId);
        if ($recipient && !$recipient->opened_at) {
            $recipient->update([
                'status' => 'opened',
                'opened_at' => now(),
            ]);
            $recipient->campaign->increment('opened_count');
        }
    }

    /**
     * Track email click
     */
    public function trackClick(int $recipientId): void
    {
        $recipient = CampaignRecipient::find($recipientId);
        if ($recipient && !$recipient->clicked_at) {
            $recipient->update([
                'status' => 'clicked',
                'clicked_at' => now(),
            ]);
            $recipient->campaign->increment('clicked_count');
        }
    }

    /**
     * Create automation workflow
     */
    public function createWorkflow(array $data): AutomationWorkflow
    {
        return AutomationWorkflow::create([
            'tenant_id' => $data['tenant_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'trigger_type' => $data['trigger_type'],
            'trigger_conditions' => $data['trigger_conditions'] ?? [],
            'is_active' => false,
        ]);
    }

    /**
     * Add step to workflow
     */
    public function addWorkflowStep(AutomationWorkflow $workflow, array $data): void
    {
        $order = $workflow->steps()->max('order') + 1;

        $workflow->steps()->create([
            'order' => $order,
            'type' => $data['type'],
            'config' => $data['config'],
        ]);
    }

    /**
     * Enroll customer in workflow
     */
    public function enrollInWorkflow(AutomationWorkflow $workflow, Customer $customer): AutomationEnrollment
    {
        $firstStep = $workflow->steps()->first();

        $enrollment = AutomationEnrollment::create([
            'workflow_id' => $workflow->id,
            'customer_id' => $customer->id,
            'current_step_id' => $firstStep?->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $workflow->increment('enrolled_count');

        return $enrollment;
    }

    /**
     * Process workflow triggers
     */
    public function processTrigger(string $tenantId, string $triggerType, Customer $customer, array $context = []): void
    {
        $workflows = AutomationWorkflow::forTenant($tenantId)
            ->active()
            ->where('trigger_type', $triggerType)
            ->get();

        foreach ($workflows as $workflow) {
            if ($this->matchesTriggerConditions($workflow, $context)) {
                $this->enrollInWorkflow($workflow, $customer);
            }
        }
    }

    /**
     * Log customer activity
     */
    public function logActivity(array $data): CustomerActivity
    {
        return CustomerActivity::create([
            'tenant_id' => $data['tenant_id'],
            'customer_id' => $data['customer_id'],
            'user_id' => $data['user_id'] ?? null,
            'type' => $data['type'],
            'content' => $data['content'] ?? null,
            'meta' => $data['meta'] ?? [],
        ]);
    }

    /**
     * Get customer timeline
     */
    public function getCustomerTimeline(int $customerId): array
    {
        return CustomerActivity::forCustomer($customerId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
     * Get campaign stats
     */
    public function getCampaignStats(string $tenantId): array
    {
        return [
            'total_campaigns' => EmailCampaign::forTenant($tenantId)->count(),
            'sent' => EmailCampaign::forTenant($tenantId)->where('status', 'sent')->count(),
            'total_sent' => EmailCampaign::forTenant($tenantId)->sum('sent_count'),
            'total_opened' => EmailCampaign::forTenant($tenantId)->sum('opened_count'),
            'avg_open_rate' => EmailCampaign::forTenant($tenantId)
                ->where('sent_count', '>', 0)
                ->selectRaw('AVG(opened_count / sent_count * 100) as rate')
                ->value('rate') ?? 0,
        ];
    }

    protected function applyCondition($query, array $condition)
    {
        return match ($condition['type'] ?? '') {
            'total_spent' => $query->where('total_spent', $condition['operator'], $condition['value']),
            'orders_count' => $query->where('orders_count', $condition['operator'], $condition['value']),
            'last_order' => $query->where('last_order_at', $condition['operator'], $condition['value']),
            'tag' => $query->whereJsonContains('tags', $condition['value']),
            default => $query,
        };
    }

    protected function matchesTriggerConditions(AutomationWorkflow $workflow, array $context): bool
    {
        if (empty($workflow->trigger_conditions)) {
            return true;
        }

        foreach ($workflow->trigger_conditions as $condition) {
            if (!isset($context[$condition['field']]) ||
                $context[$condition['field']] != $condition['value']) {
                return false;
            }
        }

        return true;
    }
}
