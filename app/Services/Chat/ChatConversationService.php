<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatConversationUpdated;
use App\Events\Chat\ChatMessageSent;
use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatMessage;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the conversation lifecycle: open, post message, assign, resolve.
 * Ties together routing, scheduling and anti-abuse. Persistence only — real-time
 * fan-out (Reverb) is layered on in F3; F1 clients poll.
 */
class ChatConversationService
{
    public function __construct(
        private ChatRoutingService $routing,
        private ChatScheduleService $schedule,
        private ChatPresenceService $presence,
        private ChatTranscriptService $transcripts,
    ) {
    }

    /**
     * Open a new conversation. Attempts immediate assignment; otherwise leaves
     * it queued (operators scheduled) or as an offline message (out of hours).
     *
     * @param  array<string, mixed>  $data {
     *     visitor_type, opener_type?, opener_id?, guest_name?, guest_email?,
     *     event_id?, support_department_id?, subject?, context?
     * }
     */
    public function open(int $marketplaceClientId, array $data, ?string $firstMessage = null): ChatConversation
    {
        return DB::transaction(function () use ($marketplaceClientId, $data, $firstMessage) {
            $state = $this->schedule->availabilityState($marketplaceClientId);

            $conversation = new ChatConversation();
            $conversation->forceFill([
                'marketplace_client_id' => $marketplaceClientId,
                'visitor_type' => $data['visitor_type'] ?? ChatConversation::VISITOR_GUEST,
                'opener_type' => $data['opener_type'] ?? null,
                'opener_id' => $data['opener_id'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_email' => $data['guest_email'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'support_department_id' => $data['support_department_id'] ?? null,
                'subject' => $data['subject'] ?? null,
                'context' => $data['context'] ?? null,
                'status' => $state === 'offline'
                    ? ChatConversation::STATUS_OFFLINE_MESSAGE
                    : ChatConversation::STATUS_QUEUED,
                'queued_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            if ($firstMessage) {
                $this->postOpenerMessage($conversation, $firstMessage);
            }

            // Optional auto-routing when operators are live. Off by default —
            // new chats wait in the queue for an operator to claim manually.
            if ($state === 'online' && config('chat.operator.auto_assign', false)) {
                $this->routing->assign($conversation);
            }

            // Immediate email acknowledgement for messages left while offline.
            if ($conversation->status === ChatConversation::STATUS_OFFLINE_MESSAGE) {
                $this->transcripts->sendOfflineAck($conversation);
            }

            return $conversation->refresh();
        });
    }

    /**
     * Append a message from the opener (customer/organizer/artist/guest).
     */
    public function postOpenerMessage(ChatConversation $conversation, string $body, array $attachments = []): ChatMessage
    {
        $message = $this->appendMessage($conversation, [
            'author_type' => $conversation->opener_type,
            'author_id' => $conversation->opener_id,
            'author_label' => $conversation->openerName(),
            'type' => ChatMessage::TYPE_TEXT,
            'body' => $body,
            'is_internal' => false,
            'attachments' => $attachments ?: null,
        ]);

        $conversation->markActivity();
        $conversation->save();

        return $message;
    }

    /**
     * Append a message from an operator (staff). Stamps first_response_at.
     */
    public function postOperatorMessage(ChatConversation $conversation, int $adminId, string $adminName, string $body, bool $internal = false, array $attachments = []): ChatMessage
    {
        $message = $this->appendMessage($conversation, [
            'author_type' => 'staff',
            'author_id' => $adminId,
            'author_label' => $adminName,
            'type' => ChatMessage::TYPE_TEXT,
            'body' => $body,
            'is_internal' => $internal,
            'attachments' => $attachments ?: null,
        ]);

        if (!$internal && !$conversation->first_response_at) {
            $conversation->first_response_at = now();
        }
        $conversation->markActivity();
        $conversation->save();

        return $message;
    }

    /**
     * Append a system notice (join/leave/assign/close).
     */
    public function postSystemMessage(ChatConversation $conversation, string $body): ChatMessage
    {
        return $this->appendMessage($conversation, [
            'author_type' => null,
            'author_id' => null,
            'author_label' => null,
            'type' => ChatMessage::TYPE_SYSTEM,
            'body' => $body,
            'is_internal' => false,
            'attachments' => null,
        ]);
    }

    /**
     * Resolve/close a conversation and free the operator slot.
     */
    public function resolve(ChatConversation $conversation, string $status = ChatConversation::STATUS_RESOLVED): void
    {
        $conversation->forceFill([
            'status' => $status,
            'resolved_at' => now(),
            'closed_at' => $status === ChatConversation::STATUS_CLOSED ? now() : $conversation->closed_at,
            'last_activity_at' => now(),
        ])->save();

        $this->routing->release($conversation);
        ChatConversationUpdated::dispatch($conversation);

        // Best-effort transcript email to the opener (non-fatal).
        $this->transcripts->sendTranscript($conversation);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function appendMessage(ChatConversation $conversation, array $attrs): ChatMessage
    {
        $message = new ChatMessage();
        $message->forceFill(array_merge([
            'marketplace_client_id' => $conversation->marketplace_client_id,
            'chat_conversation_id' => $conversation->id,
            'delivered_at' => now(),
        ], $attrs))->save();

        $message->setRelation('conversation', $conversation);
        ChatMessageSent::dispatch($message);

        return $message;
    }
}
