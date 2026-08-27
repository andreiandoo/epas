<?php

namespace App\Services\Chat;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Chat\ChatConversation;
use App\Models\MarketplaceClient;
use Illuminate\Support\Facades\Log;

/**
 * Emails a conversation transcript to the opener when it ends. Uses the
 * marketplace's own mail transport (same path as order confirmations) and is
 * best-effort: any failure is logged, never thrown, so it can't break resolve().
 */
class ChatTranscriptService
{
    public function sendTranscript(ChatConversation $conversation): void
    {
        try {
            $email = $conversation->guest_email;
            if (!$email) {
                return;
            }

            $client = MarketplaceClient::find($conversation->marketplace_client_id);
            if (!$client) {
                return;
            }

            $messages = $conversation->publicMessages()->get();
            if ($messages->isEmpty()) {
                return;
            }

            $html = $this->render($conversation, $messages, $client);

            BaseController::sendViaMarketplace(
                $client,
                $email,
                $conversation->openerName(),
                'Transcriptul conversației tale — ' . ($client->public_name ?? $client->name ?? 'AmBilet'),
                $html,
                ['template_slug' => 'chat_transcript', 'reference' => $conversation->reference]
            );
        } catch (\Throwable $e) {
            Log::warning('Chat transcript email failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function render(ChatConversation $conversation, $messages, MarketplaceClient $client): string
    {
        $rows = '';
        foreach ($messages as $m) {
            $who = $m->isFromStaff() ? ($client->public_name ?? 'Operator') : ($m->isSystem() ? 'Sistem' : $conversation->openerName());
            $align = $m->isFromStaff() ? 'left' : 'right';
            $bg = $m->isFromStaff() ? '#f1f5f9' : '#fde8ee';
            $body = nl2br(e($m->body ?? ''));
            $time = optional($m->created_at)->format('d.m.Y H:i');
            $rows .= '<tr><td style="padding:6px 0;text-align:' . $align . '">'
                . '<div style="display:inline-block;max-width:80%;background:' . $bg . ';border-radius:10px;padding:8px 12px;text-align:left">'
                . '<div style="font-size:11px;color:#64748b;margin-bottom:2px">' . e($who) . ' · ' . $time . '</div>'
                . '<div style="font-size:14px;color:#0f172a">' . $body . '</div>'
                . '</div></td></tr>';
        }

        $title = 'Conversația ta (' . e($conversation->reference) . ')';

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto">'
            . '<h2 style="color:#e11d48">' . $title . '</h2>'
            . '<p style="color:#475569;font-size:14px">Îți mulțumim că ne-ai scris. Mai jos găsești transcriptul conversației.</p>'
            . '<table style="width:100%;border-collapse:collapse">' . $rows . '</table>'
            . '<p style="color:#94a3b8;font-size:12px;margin-top:24px">Acest email a fost trimis automat de ' . e($client->public_name ?? $client->name ?? '') . '.</p>'
            . '</div>';
    }
}
