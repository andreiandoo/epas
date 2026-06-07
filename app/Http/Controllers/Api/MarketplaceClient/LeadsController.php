<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Marketplace\OrganizerLead;
use App\Models\Marketplace\OrganizerLeadEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Public-facing endpoints powering the bilete.online (and any future
 * leisure marketplace) /devino-partener + /inregistrare-locatie funnel.
 *
 *   POST /leads        — visitor submits the signup form. Creates a Lead
 *                        row, links any prior anonymous funnel events
 *                        (same session_token) to it, fires `form_submitted`.
 *   POST /leads/track  — anonymous fire-and-forget page-view ping from
 *                        the landing + onboarding pages. Recorded as a
 *                        LeadEvent row keyed by session_token; if a lead
 *                        already exists for this session, also updates
 *                        its `first_*_at` + `*_views` columns.
 *
 * Both routes pass through the marketplace.auth middleware so the row is
 * scoped to the marketplace_client owning the API key — no cross-tenant
 * leakage even if someone POSTs from a different marketplace's domain.
 */
class LeadsController extends BaseController
{
    /** Cap a single session's lead spam: hard-block after N rows per IP per hour. */
    protected const MAX_LEADS_PER_IP_PER_HOUR = 8;

    public function create(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'session_token'   => 'nullable|string|max:80',
            'contact_name'    => 'required|string|max:160',
            'email'           => 'required|email|max:190',
            'phone'           => 'nullable|string|max:40',
            'location_name'   => 'required|string|max:200',
            'city'            => 'required|string|max:120',
            'website'         => 'nullable|string|max:255',
            'category_slug'   => 'nullable|string|max:120',
            'category_name'   => 'nullable|string|max:200',
            'category_other'  => 'nullable|string|max:200',
            'volume_estimate' => 'nullable|string|max:40',
            'notes'           => 'nullable|string|max:2000',
            'prefill_tip'     => 'nullable|string|max:80',
            'prefill_loc'     => 'nullable|string|max:200',
            'referrer'        => 'nullable|string|max:1000',
            'utm'             => 'nullable|array',
            'utm.utm_source'  => 'nullable|string|max:100',
            'utm.utm_medium'  => 'nullable|string|max:100',
            'utm.utm_campaign'=> 'nullable|string|max:150',
            'utm.utm_content' => 'nullable|string|max:150',
            'utm.utm_term'    => 'nullable|string|max:150',
        ]);

        // Cheap rate-limit so a misbehaving client can't flood the
        // pipeline. Counts existing leads from this IP in the past hour
        // for THIS marketplace — global lockout would let one bad apple
        // also block legit signups for unrelated marketplaces.
        $recent = OrganizerLead::query()
            ->where('marketplace_client_id', $client->id)
            ->where('created_at', '>=', now()->subHour())
            ->whereJsonContains('meta->ip', $request->ip())
            ->count();
        if ($recent >= static::MAX_LEADS_PER_IP_PER_HOUR) {
            return $this->error('Too many submissions. Please try again later.', 429);
        }

        $utm = $validated['utm'] ?? [];

        try {
            $lead = DB::transaction(function () use ($client, $validated, $utm, $request) {
                /** @var OrganizerLead $lead */
                $lead = OrganizerLead::create([
                    'marketplace_client_id' => $client->id,
                    'session_token'   => $validated['session_token'] ?? null,
                    'contact_name'    => $validated['contact_name'],
                    'email'           => strtolower(trim($validated['email'])),
                    'phone'           => $validated['phone']           ?? null,
                    'location_name'   => $validated['location_name'],
                    'city'            => $validated['city'],
                    'website'         => $validated['website']         ?? null,
                    'category_slug'   => $validated['category_slug']   ?? null,
                    'category_name'   => $validated['category_name']   ?? null,
                    'category_other'  => $validated['category_other']  ?? null,
                    'volume_estimate' => $validated['volume_estimate'] ?? null,
                    'notes'           => $validated['notes']           ?? null,
                    'status'          => OrganizerLead::STATUS_NEW,
                    'source'          => 'partner_signup',
                    'prefill_tip'     => $validated['prefill_tip']     ?? null,
                    'prefill_loc'     => $validated['prefill_loc']     ?? null,
                    'referrer'        => $validated['referrer']        ?? null,
                    'utm_source'      => $utm['utm_source']   ?? null,
                    'utm_medium'      => $utm['utm_medium']   ?? null,
                    'utm_campaign'    => $utm['utm_campaign'] ?? null,
                    'utm_content'     => $utm['utm_content']  ?? null,
                    'utm_term'        => $utm['utm_term']     ?? null,
                    'submitted_at'    => now(),
                    'meta'            => [
                        'ip'         => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    ],
                ]);

                // Promote all prior anonymous events for this session to
                // the new lead. Visit counts roll up from the existing
                // page_view_* rows so the dashboard can see what fraction
                // of leads bounced multiple times before converting.
                $sessionToken = $validated['session_token'] ?? null;
                if ($sessionToken) {
                    $promoted = OrganizerLeadEvent::query()
                        ->where('session_token', $sessionToken)
                        ->whereNull('lead_id')
                        ->update(['lead_id' => $lead->id]);

                    if ($promoted > 0) {
                        $landingViews = OrganizerLeadEvent::query()
                            ->where('lead_id', $lead->id)
                            ->where('event_type', OrganizerLeadEvent::TYPE_PAGE_VIEW_LANDING)
                            ->count();
                        $onboardingViews = OrganizerLeadEvent::query()
                            ->where('lead_id', $lead->id)
                            ->where('event_type', OrganizerLeadEvent::TYPE_PAGE_VIEW_ONBOARDING)
                            ->count();
                        $firstLanding = OrganizerLeadEvent::query()
                            ->where('lead_id', $lead->id)
                            ->where('event_type', OrganizerLeadEvent::TYPE_PAGE_VIEW_LANDING)
                            ->oldest()->value('created_at');
                        $firstOnboarding = OrganizerLeadEvent::query()
                            ->where('lead_id', $lead->id)
                            ->where('event_type', OrganizerLeadEvent::TYPE_PAGE_VIEW_ONBOARDING)
                            ->oldest()->value('created_at');

                        $lead->update([
                            'landing_views'       => $landingViews,
                            'onboarding_views'    => $onboardingViews,
                            'first_landing_at'    => $firstLanding,
                            'first_onboarding_at' => $firstOnboarding,
                        ]);
                    }
                }

                // form_submitted is the conversion marker — recorded
                // explicitly so dashboards can count "submissions in
                // last 7 days" without filtering by created_at on the
                // lead row (which can be edited later).
                OrganizerLeadEvent::create([
                    'lead_id'               => $lead->id,
                    'marketplace_client_id' => $client->id,
                    'session_token'         => $validated['session_token'] ?? null,
                    'event_type'            => OrganizerLeadEvent::TYPE_FORM_SUBMITTED,
                    'summary'               => "Form submitted for {$lead->location_name} ({$lead->city})",
                    'payload'               => ['category' => $lead->activity_type_label],
                    'ip_address'            => $request->ip(),
                    'user_agent'            => substr((string) $request->userAgent(), 0, 500),
                ]);

                return $lead;
            });

            return $this->success([
                'lead_id'    => $lead->id,
                'status'     => $lead->status,
                'message'    => 'Lead created.',
            ]);
        } catch (\Throwable $e) {
            Log::channel('marketplace')->error('Lead create failed', [
                'marketplace_client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Lead creation failed. Please try again.', 500);
        }
    }

    public function track(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'session_token' => 'required|string|max:80',
            'event_type'    => 'required|string|max:60',
            'page_url'      => 'nullable|string|max:500',
            'referrer'      => 'nullable|string|max:1000',
            'prefill_tip'   => 'nullable|string|max:80',
            'prefill_loc'   => 'nullable|string|max:200',
            'utm'           => 'nullable|array',
            // CTA-click events carry the button identifier so the admin
            // timeline can show *which* CTA the visitor clicked, not just
            // that they clicked something.
            'cta_id'        => 'nullable|string|max:80',
            'cta_label'     => 'nullable|string|max:200',
        ]);

        // Reject types we don't track from the public side — keeps the
        // visitor-facing endpoint a single-purpose tool and stops it from
        // being abused to forge admin-only events (status_changed, etc.).
        $allowedTypes = [
            OrganizerLeadEvent::TYPE_PAGE_VIEW_LANDING,
            OrganizerLeadEvent::TYPE_PAGE_VIEW_ONBOARDING,
            OrganizerLeadEvent::TYPE_CTA_CLICK,
        ];
        if (!in_array($validated['event_type'], $allowedTypes, true)) {
            return $this->error('Event type not allowed from public tracker.', 400);
        }

        try {
            // If a lead already exists for this session, link the event +
            // bump its denormalized funnel counters in the same write.
            $lead = OrganizerLead::query()
                ->where('marketplace_client_id', $client->id)
                ->where('session_token', $validated['session_token'])
                ->orderByDesc('id')
                ->first();

            $isCtaClick = $validated['event_type'] === OrganizerLeadEvent::TYPE_CTA_CLICK;
            $event = OrganizerLeadEvent::create([
                'lead_id'               => $lead?->id,
                'marketplace_client_id' => $client->id,
                'session_token'         => $validated['session_token'],
                'event_type'            => $validated['event_type'],
                // CTA-click events get a human-readable summary so the
                // timeline doesn't just say "cta_click" — it shows which
                // button got clicked, which is the actual signal.
                'summary'               => $isCtaClick
                    ? trim('Click pe „' . ($validated['cta_label'] ?? $validated['cta_id'] ?? 'CTA') . '"')
                    : null,
                'payload'               => [
                    'utm'         => $validated['utm']         ?? null,
                    'referrer'    => $validated['referrer']    ?? null,
                    'prefill_tip' => $validated['prefill_tip'] ?? null,
                    'prefill_loc' => $validated['prefill_loc'] ?? null,
                    'cta_id'      => $validated['cta_id']      ?? null,
                    'cta_label'   => $validated['cta_label']   ?? null,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'page_url'   => $validated['page_url'] ?? null,
            ]);

            if ($lead) {
                if ($validated['event_type'] === OrganizerLeadEvent::TYPE_PAGE_VIEW_LANDING) {
                    $lead->update([
                        'landing_views'    => $lead->landing_views + 1,
                        'first_landing_at' => $lead->first_landing_at ?? $event->created_at,
                    ]);
                } elseif ($validated['event_type'] === OrganizerLeadEvent::TYPE_PAGE_VIEW_ONBOARDING) {
                    $lead->update([
                        'onboarding_views'    => $lead->onboarding_views + 1,
                        'first_onboarding_at' => $lead->first_onboarding_at ?? $event->created_at,
                    ]);
                }
            }

            return $this->success(['event_id' => $event->id]);
        } catch (\Throwable $e) {
            // Tracking must NEVER break the page — return success even
            // on error so the visitor's browser doesn't retry.
            Log::channel('marketplace')->warning('Lead track failed', [
                'marketplace_client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            return $this->success(['event_id' => null]);
        }
    }
}
