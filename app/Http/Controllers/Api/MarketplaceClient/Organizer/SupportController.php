<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceOrganizer;
use App\Models\SupportDepartment;
use App\Models\SupportProblemType;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\Support\RequestContextCapture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupportController extends BaseController
{
    /**
     * GET /support/departments
     * Returns the taxonomy (departments + problem types) the organizer can pick from.
     * Locale-aware: response uses ?lang= or organizer's preferred locale.
     */
    public function departments(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizerWithGate($request);
        $client = $this->requireClient($request);
        $locale = $request->input('lang', $organizer->language ?? $client->language ?? 'ro');

        $departments = SupportDepartment::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['problemTypes' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->get();

        return $this->success([
            'departments' => $departments->map(fn (SupportDepartment $dept) => [
                'id' => $dept->id,
                'slug' => $dept->slug,
                'name' => $this->localized($dept, 'name', $locale),
                'description' => $this->localized($dept, 'description', $locale),
                'problem_types' => $dept->problemTypes
                    ->filter(fn (SupportProblemType $pt) => $pt->isAvailableFor('organizer'))
                    ->values()
                    ->map(fn (SupportProblemType $pt) => [
                        'id' => $pt->id,
                        'slug' => $pt->slug,
                        'name' => $this->localized($pt, 'name', $locale),
                        'description' => $this->localized($pt, 'description', $locale),
                        'required_fields' => $pt->required_fields ?: [],
                    ])->all(),
            ])->all(),
            'attachment_rules' => [
                'max_size_kb' => (int) config('support.attachments.max_size_kb', 3072),
                'allowed_mimes' => (array) config('support.attachments.allowed_mimes', ['jpg', 'png', 'pdf']),
                'max_per_message' => (int) config('support.attachments.max_per_message', 5),
            ],
        ]);
    }

    /**
     * GET /support/tickets
     * Paginated list of the organizer's own tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizerWithGate($request);

        $query = SupportTicket::query()
            ->forOpener('organizer', $organizer->id)
            ->with(['department', 'problemType', 'assignee'])
            ->withCount(['messages as messages_count' => fn ($q) => $q->where('is_internal_note', false)])
            ->orderByDesc('last_activity_at');

        if ($status = $request->input('status')) {
            if ($status === 'open') {
                $query->open();
            } elseif (in_array($status, SupportTicket::STATUSES, true)) {
                $query->where('status', $status);
            }
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $tickets = $query->paginate($perPage);

        return $this->paginated($tickets, fn (SupportTicket $t) => $this->formatTicketSummary($t, $request));
    }

    /**
     * GET /support/tickets/{id}
     * Detail + thread (internal notes filtered out).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $organizer = $this->requireOrganizerWithGate($request);
        $ticket = $this->findOwnTicketOrFail($organizer, $id);

        $ticket->load(['department', 'problemType', 'assignee']);

        $messages = $ticket->publicMessages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (SupportTicketMessage $m) => $this->formatMessage($m));

        return $this->success([
            'ticket' => $this->formatTicketDetail($ticket, $request),
            'messages' => $messages,
        ]);
    }

    /**
     * POST /support/tickets
     * Creates a new ticket. Validates form-specific fields based on the
     * problem type's required_fields list.
     */
    public function store(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizerWithGate($request);
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'support_problem_type_id' => ['required', 'integer', Rule::exists('support_problem_types', 'id')
                ->where('marketplace_client_id', $client->id)
                ->where('is_active', true)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'meta' => ['nullable', 'array'],
            'meta.url' => ['nullable', 'url', 'max:2048'],
            'meta.invoice_series' => ['nullable', 'string', 'max:32'],
            'meta.invoice_number' => ['nullable', 'string', 'max:64'],
            'meta.event_id' => ['nullable', 'integer'],
            'meta.module_name' => ['nullable', 'string', 'max:100'],
            'context.source_url' => ['nullable', 'url', 'max:2048'],
            'context.screen_resolution' => ['nullable', 'string', 'max:32'],
            'attachments' => ['nullable', 'array', 'max:' . (int) config('support.attachments.max_per_message', 5)],
            'attachments.*' => $this->attachmentValidationRule(),
        ]);

        /** @var SupportProblemType $problemType */
        $problemType = SupportProblemType::query()
            ->where('marketplace_client_id', $client->id)
            ->findOrFail($validated['support_problem_type_id']);

        if (!$problemType->isAvailableFor('organizer')) {
            throw ValidationException::withMessages([
                'support_problem_type_id' => __('Acest tip de problemă nu este disponibil pentru organizatori.'),
            ]);
        }

        $this->validateRequiredFields($problemType, $validated['meta'] ?? []);

        // Validate event_id belongs to this organizer when provided
        if (!empty($validated['meta']['event_id'])) {
            $belongs = \App\Models\Event::query()
                ->where('id', (int) $validated['meta']['event_id'])
                ->where('marketplace_client_id', $client->id)
                ->where('marketplace_organizer_id', $organizer->id)
                ->exists();
            if (!$belongs) {
                throw ValidationException::withMessages([
                    'meta.event_id' => __('Evenimentul selectat nu vă aparține.'),
                ]);
            }
        }

        $ticket = new SupportTicket([
            'marketplace_client_id' => $client->id,
            'opener_type' => 'organizer',
            'opener_id' => $organizer->id,
            'support_department_id' => $problemType->support_department_id,
            'support_problem_type_id' => $problemType->id,
            'subject' => $validated['subject'],
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => 'normal',
            'meta' => $validated['meta'] ?? [],
            'context' => $this->captureContext($request, $validated['context'] ?? []),
            'opened_at' => now(),
            'last_activity_at' => now(),
        ]);
        $ticket->save();

        // Initial message = the description
        $attachments = $this->storeAttachments($request, $ticket->id);
        SupportTicketMessage::create([
            'marketplace_client_id' => $client->id,
            'support_ticket_id' => $ticket->id,
            'author_type' => 'organizer',
            'author_id' => $organizer->id,
            'body' => $validated['description'],
            'is_internal_note' => false,
            'attachments' => $attachments ?: null,
        ]);

        $ticket->load(['department', 'problemType']);

        return $this->success([
            'ticket' => $this->formatTicketDetail($ticket, $request),
        ], 'Tichet creat cu succes.', 201);
    }

    /**
     * POST /support/tickets/{id}/messages
     * Organizer reply to an open ticket.
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $organizer = $this->requireOrganizerWithGate($request);
        $client = $this->requireClient($request);
        $ticket = $this->findOwnTicketOrFail($organizer, $id);

        if ($ticket->isClosed()) {
            return $this->error('Tichetul este închis. Redeschideți-l pentru a răspunde.', 409);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:' . (int) config('support.attachments.max_per_message', 5)],
            'attachments.*' => $this->attachmentValidationRule(),
        ]);

        $attachments = $this->storeAttachments($request, $ticket->id);

        $message = SupportTicketMessage::create([
            'marketplace_client_id' => $client->id,
            'support_ticket_id' => $ticket->id,
            'author_type' => 'organizer',
            'author_id' => $organizer->id,
            'body' => $validated['body'],
            'is_internal_note' => false,
            'attachments' => $attachments ?: null,
        ]);

        $ticket->markActivity();
        // Organizer replied — flip back to in_progress so it pops up on staff queues
        if ($ticket->status === SupportTicket::STATUS_AWAITING_ORGANIZER) {
            $ticket->status = SupportTicket::STATUS_IN_PROGRESS;
        }
        $ticket->save();

        return $this->success([
            'message' => $this->formatMessage($message),
        ], 'Mesaj trimis.', 201);
    }

    /**
     * POST /support/tickets/{id}/close — organizer marks own ticket resolved.
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $organizer = $this->requireOrganizerWithGate($request);
        $ticket = $this->findOwnTicketOrFail($organizer, $id);

        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            return $this->error('Tichetul este deja închis.', 409);
        }

        $ticket->status = SupportTicket::STATUS_RESOLVED;
        $ticket->resolved_at = now();
        $ticket->markActivity();
        $ticket->save();

        return $this->success(['ticket' => $this->formatTicketDetail($ticket, $request)]);
    }

    /**
     * POST /support/tickets/{id}/reopen — organizer reopens own ticket.
     */
    public function reopen(Request $request, int $id): JsonResponse
    {
        $organizer = $this->requireOrganizerWithGate($request);
        $ticket = $this->findOwnTicketOrFail($organizer, $id);

        if (!$ticket->isClosed()) {
            return $this->error('Tichetul nu este închis.', 409);
        }

        $ticket->status = SupportTicket::STATUS_OPEN;
        $ticket->resolved_at = null;
        $ticket->closed_at = null;
        $ticket->markActivity();
        $ticket->save();

        return $this->success(['ticket' => $this->formatTicketDetail($ticket, $request)]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Auth + beta gate. Returns the organizer or aborts.
     */
    protected function requireOrganizerWithGate(Request $request): MarketplaceOrganizer
    {
        if (!config('support.enabled', true)) {
            abort(404, 'Support is currently unavailable.');
        }

        $organizer = $request->user();
        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        $allowed = (array) config('support.allowed_opener_ids.organizer', []);
        $hasWildcard = in_array('*', array_map('strval', $allowed), true);
        $idStr = (string) $organizer->id;
        if (!$hasWildcard && !in_array($idStr, array_map('strval', $allowed), true)) {
            abort(403, 'Suportul nu este încă disponibil pentru contul dvs.');
        }

        return $organizer;
    }

    protected function findOwnTicketOrFail(MarketplaceOrganizer $organizer, int $id): SupportTicket
    {
        $ticket = SupportTicket::query()
            ->forOpener('organizer', $organizer->id)
            ->find($id);

        if (!$ticket) {
            abort(404, 'Tichet inexistent.');
        }

        return $ticket;
    }

    protected function validateRequiredFields(SupportProblemType $problemType, array $meta): void
    {
        $required = $problemType->required_fields ?: [];
        $errors = [];
        foreach ($required as $field) {
            $value = $meta[$field] ?? null;
            if ($value === null || $value === '') {
                $errors["meta.$field"] = match ($field) {
                    'url' => 'URL-ul paginii este obligatoriu pentru acest tip de problemă.',
                    'invoice_series' => 'Seria decontului este obligatorie.',
                    'invoice_number' => 'Numărul decontului este obligatoriu.',
                    'event_id' => 'Selectați un eveniment.',
                    'module_name' => 'Specificați modulul afectat.',
                    default => "Câmpul '$field' este obligatoriu.",
                };
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Build the request-context snapshot stored on the ticket.
     * Frontend passes `source_url` + `screen_resolution`; the rest comes
     * from server-side request headers.
     */
    protected function captureContext(Request $request, array $extra = []): array
    {
        return app(RequestContextCapture::class)->capture($request, $extra);
    }

    protected function attachmentValidationRule(): array
    {
        $maxKb = (int) config('support.attachments.max_size_kb', 3072);
        $mimes = implode(',', (array) config('support.attachments.allowed_mimes', ['jpg', 'png', 'pdf']));
        return ['file', "mimes:$mimes", "max:$maxKb"];
    }

    /**
     * Persist uploaded files on the configured disk and return metadata
     * suitable for the attachments JSON column.
     */
    protected function storeAttachments(Request $request, int $ticketId): array
    {
        $files = $request->file('attachments') ?? [];
        if (!is_array($files)) {
            return [];
        }

        $disk = (string) config('support.attachments.storage_disk', 'public');
        $base = trim((string) config('support.attachments.storage_path', 'support-tickets'), '/');
        $folder = "$base/$ticketId";

        $stored = [];
        foreach ($files as $file) {
            if (!$file) continue;
            $original = (string) $file->getClientOriginalName();
            $ext = strtolower((string) $file->getClientOriginalExtension());
            $name = Str::random(24) . '.' . $ext;
            $path = $file->storeAs($folder, $name, $disk);
            $stored[] = [
                'path' => $path,
                'original_name' => $original,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'disk' => $disk,
            ];
        }
        return $stored;
    }

    protected function localized($model, string $field, string $locale): ?string
    {
        return $model->getTranslation($field, $locale)
            ?: $model->getTranslation($field, 'ro')
            ?: $model->getTranslation($field, 'en');
    }

    // -------- Formatters --------

    protected function formatTicketSummary(SupportTicket $t, Request $request): array
    {
        $locale = $request->input('lang', 'ro');
        return [
            'id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'subject' => $t->subject,
            'status' => $t->status,
            'priority' => $t->priority,
            'department' => [
                'id' => $t->support_department_id,
                'name' => $t->department ? $this->localized($t->department, 'name', $locale) : null,
            ],
            'problem_type' => $t->problemType ? [
                'id' => $t->problemType->id,
                'name' => $this->localized($t->problemType, 'name', $locale),
            ] : null,
            'assignee' => $t->assignee ? [
                'id' => $t->assignee->id,
                'name' => $t->assignee->name,
            ] : null,
            'messages_count' => (int) ($t->messages_count ?? 0),
            'opened_at' => $t->opened_at?->toIso8601String(),
            'last_activity_at' => $t->last_activity_at?->toIso8601String(),
            'closed_at' => $t->closed_at?->toIso8601String(),
        ];
    }

    protected function formatTicketDetail(SupportTicket $t, Request $request): array
    {
        return array_merge($this->formatTicketSummary($t, $request), [
            'meta' => $t->meta ?: new \stdClass(),
            'is_closed' => $t->isClosed(),
        ]);
    }

    protected function formatMessage(SupportTicketMessage $m): array
    {
        return [
            'id' => $m->id,
            'author_type' => $m->author_type,
            'author_id' => $m->author_id,
            'author_name' => $this->resolveAuthorName($m),
            'body' => $m->body,
            'attachments' => collect($m->attachments ?: [])
                ->map(fn ($a) => [
                    'original_name' => $a['original_name'] ?? 'file',
                    'mime' => $a['mime'] ?? null,
                    'size' => $a['size'] ?? null,
                    'url' => isset($a['path']) ? Storage::disk($a['disk'] ?? 'public')->url($a['path']) : null,
                ])
                ->all(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    protected function resolveAuthorName(SupportTicketMessage $m): ?string
    {
        $author = $m->author;
        if (!$author) return null;
        return $author->name
            ?? $author->public_name
            ?? $author->email
            ?? null;
    }
}
