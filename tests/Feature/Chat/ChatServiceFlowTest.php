<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\ChatBlocklistEntry;
use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatOperatorSchedule;
use App\Models\Chat\ChatOperatorStatus;
use App\Services\Chat\ChatAntiAbuseService;
use App\Services\Chat\ChatConversationService;
use App\Services\Chat\ChatScheduleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * End-to-end coverage of the live-chat service layer (routing, scheduling,
 * anti-abuse, retention) on the isolated chat_testing connection.
 */
class ChatServiceFlowTest extends ChatTestCase
{
    private const MC = 1; // marketplace_client_id used across tests

    private function service(): ChatConversationService
    {
        return app(ChatConversationService::class);
    }

    private function guestData(): array
    {
        return [
            'visitor_type' => ChatConversation::VISITOR_GUEST,
            'guest_name' => 'Ana Pop',
            'guest_email' => 'ana@example.ro',
            'context' => ['url' => 'https://ambilet.ro/eveniment/x'],
        ];
    }

    public function test_open_with_no_operators_becomes_offline_message(): void
    {
        $conv = $this->service()->open(self::MC, $this->guestData(), 'Salut, am o întrebare');

        $this->assertSame(ChatConversation::STATUS_OFFLINE_MESSAGE, $conv->status);
        $this->assertNull($conv->assigned_to_marketplace_admin_id);
        $this->assertNotNull($conv->reference);
        $this->assertStringStartsWith('CHAT-', $conv->reference);
        $this->assertDatabaseHas('chat_messages', [
            'chat_conversation_id' => $conv->id,
            'body' => 'Salut, am o întrebare',
        ]);
    }

    public function test_open_with_online_operator_assigns_and_activates(): void
    {
        ChatOperatorStatus::create([
            'marketplace_client_id' => self::MC,
            'marketplace_admin_id' => 7,
            'presence' => ChatOperatorStatus::PRESENCE_ONLINE,
            'active_chats_count' => 0,
        ]);

        $conv = $this->service()->open(self::MC, $this->guestData(), 'Bună');

        $this->assertSame(ChatConversation::STATUS_ACTIVE, $conv->status);
        $this->assertSame(7, $conv->assigned_to_marketplace_admin_id);
        $this->assertSame(1, (int) ChatOperatorStatus::where('marketplace_admin_id', 7)->value('active_chats_count'));
    }

    public function test_open_queues_when_operator_is_at_capacity_but_scheduled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-27 12:00:00'));

        ChatOperatorSchedule::create([
            'marketplace_client_id' => self::MC,
            'marketplace_admin_id' => 7,
            'day_of_week' => (int) Carbon::now()->dayOfWeek,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'is_active' => true,
        ]);
        // Online but full (default capacity is 4).
        ChatOperatorStatus::create([
            'marketplace_client_id' => self::MC,
            'marketplace_admin_id' => 7,
            'presence' => ChatOperatorStatus::PRESENCE_ONLINE,
            'active_chats_count' => 4,
        ]);

        $this->assertSame('queue', app(ChatScheduleService::class)->availabilityState(self::MC));

        $conv = $this->service()->open(self::MC, $this->guestData(), 'Aștept');

        $this->assertSame(ChatConversation::STATUS_QUEUED, $conv->status);
        $this->assertNull($conv->assigned_to_marketplace_admin_id);

        Carbon::setTestNow();
    }

    public function test_operator_reply_sets_first_response_and_resolve_frees_slot(): void
    {
        ChatOperatorStatus::create([
            'marketplace_client_id' => self::MC,
            'marketplace_admin_id' => 7,
            'presence' => ChatOperatorStatus::PRESENCE_ONLINE,
            'active_chats_count' => 0,
        ]);

        $svc = $this->service();
        $conv = $svc->open(self::MC, $this->guestData(), 'Bună');
        $this->assertSame(1, (int) ChatOperatorStatus::where('marketplace_admin_id', 7)->value('active_chats_count'));

        $msg = $svc->postOperatorMessage($conv, 7, 'Operator', 'Cu ce te pot ajuta?');
        $this->assertSame('staff', $msg->author_type);
        $this->assertNotNull($conv->refresh()->first_response_at);

        $svc->resolve($conv);
        $this->assertSame(ChatConversation::STATUS_RESOLVED, $conv->refresh()->status);
        $this->assertSame(0, (int) ChatOperatorStatus::where('marketplace_admin_id', 7)->value('active_chats_count'));
    }

    public function test_internal_note_is_excluded_from_public_messages(): void
    {
        $svc = $this->service();
        $conv = $svc->open(self::MC, $this->guestData(), 'Bună');
        $svc->postOperatorMessage($conv, 7, 'Operator', 'Notă doar pentru echipă', internal: true);

        $this->assertSame(1, $conv->publicMessages()->count()); // only the opener message
        $this->assertSame(2, $conv->messages()->count());       // opener + internal note
    }

    public function test_antiabuse_honeypot_timetrap_and_ratelimit(): void
    {
        config()->set('chat.anti_bot.max_conversations_per_ip', 2);
        $aa = app(ChatAntiAbuseService::class);

        // Honeypot filled → bot.
        $this->assertSame('honeypot', $aa->guardConversationOpen(self::MC, '1.2.3.4', 'a@b.ro', [
            'company_website' => 'http://spam', 'elapsed_seconds' => 5,
        ]));

        // Submitted too fast → bot.
        $this->assertSame('too_fast', $aa->guardConversationOpen(self::MC, '1.2.3.4', 'a@b.ro', [
            'elapsed_seconds' => 0,
        ]));

        // First two clean opens pass, third trips the per-IP rate limit.
        $this->assertNull($aa->guardConversationOpen(self::MC, '5.5.5.5', 'a@b.ro', ['elapsed_seconds' => 5]));
        $this->assertNull($aa->guardConversationOpen(self::MC, '5.5.5.5', 'a@b.ro', ['elapsed_seconds' => 5]));
        $this->assertSame('rate_limited', $aa->guardConversationOpen(self::MC, '5.5.5.5', 'a@b.ro', ['elapsed_seconds' => 5]));
    }

    public function test_antiabuse_blocklist_blocks_open(): void
    {
        ChatBlocklistEntry::create([
            'marketplace_client_id' => self::MC,
            'type' => ChatBlocklistEntry::TYPE_EMAIL,
            'value' => 'bad@x.ro',
        ]);

        $aa = app(ChatAntiAbuseService::class);
        $this->assertTrue($aa->isBlocked(self::MC, null, 'bad@x.ro'));
        $this->assertSame('blocked', $aa->guardConversationOpen(self::MC, '9.9.9.9', 'bad@x.ro', ['elapsed_seconds' => 5]));
    }

    public function test_close_inactive_command_closes_stale_conversations(): void
    {
        $conv = $this->service()->open(self::MC, $this->guestData(), 'Salut');
        // Force it stale.
        $conv->forceFill(['last_activity_at' => now()->subMinutes(10)])->save();

        Artisan::call('chat:close-inactive', ['--minutes' => 1]);

        $this->assertSame(ChatConversation::STATUS_CLOSED, $conv->refresh()->status);
    }

    public function test_purge_transcripts_removes_old_resolved_conversations(): void
    {
        $svc = $this->service();
        $conv = $svc->open(self::MC, $this->guestData(), 'Salut');
        $svc->resolve($conv, ChatConversation::STATUS_CLOSED);
        $conv->forceFill(['closed_at' => now()->subDays(400)])->save();

        Artisan::call('chat:purge-transcripts', ['--days' => 365]);

        $this->assertDatabaseMissing('chat_conversations', ['id' => $conv->id]);
        $this->assertDatabaseMissing('chat_messages', ['chat_conversation_id' => $conv->id]);
    }
}
