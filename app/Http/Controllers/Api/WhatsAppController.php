<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppOptIn;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSchedule;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * WhatsApp API Controller
 *
 * Endpoints for WhatsApp messaging: confirmations, reminders, promos
 */
class WhatsAppController extends Controller
{
    protected WhatsAppService $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Manage templates
     *
     * POST /api/wa/templates
     *
     * Body:
     * {
     *   "tenant": "tenant_123",
     *   "name": "order_confirmation",
     *   "language": "ro",
     *   "category": "order_confirm",
     *   "body": "Buna {first_name}! Comanda ta {order_code} pentru {event_name} a fost confirmata.",
     *   "variables": ["first_name", "order_code", "event_name"]
     * }
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|string',
            'name' => 'required|string|max:255',
            'language' => 'required|string|max:10',
            'category' => 'required|in:order_confirm,reminder,promo,otp,other',
            'body' => 'required|string',
            'variables' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $template = WhatsAppTemplate::updateOrCreate(
            [
                'tenant_id' => $request->tenant,
                'name' => $request->name,
            ],
            [
                'language' => $request->language,
                'category' => $request->category,
                'body' => $request->body,
                'variables' => $request->variables ?? [],
                'status' => WhatsAppTemplate::STATUS_DRAFT,
            ]
        );

        return response()->json([
            'success' => true,
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'status' => $template->status,
                'message' => 'Template saved as draft. Submit for BSP approval to activate.',
            ],
        ], 201);
    }

    /**
     * Send order confirmation
     *
     * POST /api/wa/send/confirm
     *
     * Body:
     * {
     *   "tenant": "tenant_123",
     *   "order_ref": "ORD-2025-001",
     *   "template_name": "order_confirmation",
     *   "customer_phone": "+40722123456",
     *   "customer_first_name": "Ion",
     *   "customer_last_name": "Popescu",
     *   "event_name": "Concert Rock",
     *   "event_date": "2025-12-01",
     *   "venue_name": "Arena Nationala",
     *   "ticket_count": 2,
     *   "total_amount": "200 RON",
     *   "download_url": "https://example.com/download/..."
     * }
     */
    public function sendConfirmation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|string',
            'order_ref' => 'required|string',
            'customer_phone' => 'required|string',
            'customer_first_name' => 'nullable|string',
            'event_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->whatsAppService->sendOrderConfirmation(
            $request->tenant,
            $request->order_ref,
            $request->all()
        );

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    /**
     * Schedule event reminders
     *
     * POST /api/wa/schedule/reminders
     *
     * Body:
     * {
     *   "tenant": "tenant_123",
     *   "order_ref": "ORD-2025-001",
     *   "template_name": "event_reminder",
     *   "event_start_at": "2025-12-01 19:00:00",
     *   "customer_phone": "+40722123456",
     *   "customer_first_name": "Ion",
     *   "event_name": "Concert Rock",
     *   "event_date": "1 Dec 2025",
     *   "event_time": "19:00",
     *   "venue_name": "Arena Nationala",
     *   "venue_address": "Str. Basarabia 37-39, Bucuresti"
     * }
     */
    public function scheduleReminders(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|string',
            'order_ref' => 'required|string',
            'event_start_at' => 'required|date',
            'customer_phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->whatsAppService->scheduleReminders(
            $request->tenant,
            $request->order_ref,
            $request->all()
        );

        return response()->json($result);
    }

    /**
     * Send promo campaign
     *
     * POST /api/wa/send/promo
     *
     * Body:
     * {
     *   "tenant": "tenant_123",
     *   "campaign_id": "PROMO-2025-001",
     *   "template_name": "promo_discount",
     *   "dry_run": false,
     *   "variables": {
     *     "discount_code": "SUMMER20",
     *     "expiry_date": "31 Dec 2025"
     *   },
     *   "recipients": [
     *     {
     *       "phone": "+40722123456",
     *       "variables": {
     *         "first_name": "Ion"
     *       }
     *     },
     *     {
     *       "phone": "+40722654321",
     *       "variables": {
     *         "first_name": "Maria"
     *       }
     *     }
     *   ]
     * }
     */
    public function sendPromo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|string',
            'template_name' => 'required|string',
            'recipients' => 'required|array|min:1',
            'recipients.*.phone' => 'required|string',
            'dry_run' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->whatsAppService->sendPromo(
            $request->tenant,
            $request->all()
        );

        return response()->json($result);
    }

    /**
     * Webhook endpoint for BSP delivery status
     *
     * POST /api/wa/webhook
     *
     * Receives delivery receipts, read receipts, etc. from BSP
     */
    public function webhook(Request $request): JsonResponse
    {
        // Extract tenant from payload or header
        $tenantId = $request->header('X-Tenant-ID') ?? $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing tenant ID',
            ], 400);
        }

        // SECURITY FIX: Enable webhook signature verification
        $signature = $request->header('X-Webhook-Signature') ?? $request->header('X-Hub-Signature-256');
        $webhookSecret = config('services.whatsapp.webhook_secret');

        if ($webhookSecret) {
            if (!$signature || !$this->verifyWebhookSignature($signature, $request->getContent(), $webhookSecret)) {
                \Log::warning('WhatsApp webhook signature verification failed', [
                    'tenant_id' => $tenantId,
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        } else {
            \Log::warning('WhatsApp webhook secret not configured - signature verification skipped', [
                'tenant_id' => $tenantId,
            ]);
        }

        $result = $this->whatsAppService->handleWebhook(
            $tenantId,
            $request->all()
        );

        return response()->json($result);
    }

    /**
     * Manage opt-in/opt-out
     *
     * POST /api/wa/optin
     *
     * Body:
     * {
     *   "tenant": "tenant_123",
     *   "phone": "+40722123456",
     *   "action": "opt_in",
     *   "source": "checkout",
     *   "user_ref": "user_456"
     * }
     */
    public function manageOptIn(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|string',
            'phone' => 'required|string',
            'action' => 'required|in:opt_in,opt_out',
            'source' => 'nullable|string',
            'user_ref' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = WhatsAppOptIn::normalizePhone($request->phone);

        $optIn = WhatsAppOptIn::findOrCreateForPhone(
            $request->tenant,
            $phone,
            $request->user_ref
        );

        if ($request->action === 'opt_in') {
            $optIn->optIn($request->source ?? 'api', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User opted in to WhatsApp notifications',
                'status' => 'opt_in',
            ]);
        } else {
            $optIn->optOut();

            return response()->json([
                'success' => true,
                'message' => 'User opted out of WhatsApp notifications',
                'status' => 'opt_out',
            ]);
        }
    }

    /**
     * Get statistics
     *
     * GET /api/wa/stats/{tenantId}?days=30
     */
    public function stats(string $tenantId, Request $request): JsonResponse
    {
        $days = min($request->get('days', 30), 90);

        $stats = $this->whatsAppService->getStats($tenantId, $days);

        // Additional stats
        $optInCount = WhatsAppOptIn::optedIn($tenantId)->count();
        $templateCount = WhatsAppTemplate::approved($tenantId)->count();
        $scheduledCount = WhatsAppSchedule::forTenant($tenantId)
            ->where('status', WhatsAppSchedule::STATUS_SCHEDULED)
            ->count();

        return response()->json([
            'success' => true,
            'tenant_id' => $tenantId,
            'period_days' => $days,
            'messages' => $stats,
            'opt_ins' => $optInCount,
            'approved_templates' => $templateCount,
            'scheduled_reminders' => $scheduledCount,
        ]);
    }

    /**
     * List messages
     *
     * GET /api/wa/messages/{tenantId}?type=order_confirm&status=sent&limit=50
     */
    public function listMessages(string $tenantId, Request $request): JsonResponse
    {
        $query = WhatsAppMessage::forTenant($tenantId);

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        $limit = min($request->get('limit', 50), 200);
        $offset = $request->get('offset', 0);

        $messages = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'type' => $message->type,
                    'to_phone' => $message->to_phone,
                    'template_name' => $message->template_name,
                    'status' => $message->status,
                    'error_message' => $message->error_message,
                    'correlation_ref' => $message->correlation_ref,
                    'sent_at' => $message->sent_at?->toIso8601String(),
                    'delivered_at' => $message->delivered_at?->toIso8601String(),
                    'created_at' => $message->created_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * List scheduled reminders
     *
     * GET /api/wa/schedules/{tenantId}?status=scheduled&limit=50
     */
    public function listSchedules(string $tenantId, Request $request): JsonResponse
    {
        $query = WhatsAppSchedule::forTenant($tenantId);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $limit = min($request->get('limit', 50), 200);

        $schedules = $query->orderBy('run_at', 'asc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'schedules' => $schedules->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'message_type' => $schedule->message_type,
                    'run_at' => $schedule->run_at->toIso8601String(),
                    'status' => $schedule->status,
                    'correlation_ref' => $schedule->correlation_ref,
                    'executed_at' => $schedule->executed_at?->toIso8601String(),
                ];
            }),
        ]);
    }
}
