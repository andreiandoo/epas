<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatConversation;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerTeamMember;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Resolves who is opening a chat from the request's Sanctum bearer token.
 *
 * The public marketplace-client route group runs `marketplace.auth` (X-API-Key)
 * but NOT `auth:sanctum`, so $request->user() is not populated. We resolve the
 * bearer manually against the shared personal_access_tokens table and map the
 * tokenable model to a visitor_type + polymorphic opener. No token => anonymous
 * guest (identity comes from the pre-chat form instead).
 *
 * Security: the opener identity is derived from the signed token, never trusted
 * from client-supplied fields.
 */
class ChatOpenerResolver
{
    /**
     * @return array{visitor_type:string, opener_type:?string, opener_id:?int, name:?string, email:?string}
     */
    public function resolve(Request $request): array
    {
        $guest = [
            'visitor_type' => ChatConversation::VISITOR_GUEST,
            'opener_type' => null,
            'opener_id' => null,
            'name' => null,
            'email' => null,
        ];

        $bearer = $request->bearerToken();
        if (!$bearer) {
            return $guest;
        }

        $token = PersonalAccessToken::findToken($bearer);
        $model = $token?->tokenable;
        if (!$model) {
            return $guest;
        }

        // Organizer team members act on behalf of their parent organizer.
        if ($model instanceof MarketplaceOrganizerTeamMember) {
            $organizer = $model->organizer ?? null;
            if ($organizer instanceof MarketplaceOrganizer) {
                return [
                    'visitor_type' => ChatConversation::VISITOR_ORGANIZER,
                    'opener_type' => $organizer->getMorphClass(),
                    'opener_id' => $organizer->getKey(),
                    'name' => $model->name ?? $organizer->name ?? null,
                    'email' => $model->email ?? $organizer->email ?? null,
                ];
            }
            return $guest;
        }

        $visitorType = match (true) {
            $model instanceof MarketplaceCustomer => ChatConversation::VISITOR_CUSTOMER,
            $model instanceof MarketplaceOrganizer => ChatConversation::VISITOR_ORGANIZER,
            default => null,
        };

        // Artist accounts (morph alias 'artist') and any other future opener.
        if ($visitorType === null) {
            $morph = $model->getMorphClass();
            if ($morph === 'artist') {
                $visitorType = ChatConversation::VISITOR_ARTIST;
            }
        }

        if ($visitorType === null) {
            return $guest;
        }

        $morphClass = $model->getMorphClass();
        // opener_type column is 32 chars — only store short morph aliases.
        if (strlen($morphClass) > 32) {
            return array_merge($guest, ['visitor_type' => $visitorType]);
        }

        return [
            'visitor_type' => $visitorType,
            'opener_type' => $morphClass,
            'opener_id' => $model->getKey(),
            'name' => $model->name ?? null,
            'email' => $model->email ?? null,
        ];
    }
}
