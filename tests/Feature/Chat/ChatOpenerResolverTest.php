<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\ChatConversation;
use App\Services\Chat\ChatOpenerResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class ChatOpenerResolverTest extends TestCase
{
    public function test_request_without_bearer_is_a_guest(): void
    {
        $request = Request::create('/api/marketplace-client/chat/conversations', 'POST');

        $resolved = (new ChatOpenerResolver())->resolve($request);

        $this->assertSame(ChatConversation::VISITOR_GUEST, $resolved['visitor_type']);
        $this->assertNull($resolved['opener_type']);
        $this->assertNull($resolved['opener_id']);
    }
}
