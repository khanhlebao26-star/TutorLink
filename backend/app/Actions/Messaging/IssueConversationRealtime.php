<?php

namespace App\Actions\Messaging;

use App\Models\User;
use App\Support\ApiErrorResponse;
use App\Support\RealtimeSessionChannels;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IssueConversationRealtime
{
    /** @return array{channel:string,event:string,expires_at:string} */
    public function execute(Request $request, int $conversationId): array
    {
        $sessions = app(RealtimeSessionChannels::class);
        $sessions->requireDatabaseSessions();

        return DB::transaction(function () use ($request, $conversationId, $sessions): array {
            $actor = User::query()
                ->lockForUpdate()
                ->find($request->user()->id);

            $requestId = (string) $request->attributes->get('request_id');

            if (
                $actor === null
                || $actor->status !== 'active'
                || ! in_array($actor->role, ['customer', 'tutor'], true)
            ) {
                throw new HttpResponseException(ApiErrorResponse::make(
                    403,
                    'realtime_forbidden',
                    'This account cannot access realtime.',
                    $requestId,
                ));
            }

            if (! $sessions->canRead((int) $actor->id, $conversationId)) {
                throw new HttpResponseException(ApiErrorResponse::make(
                    404,
                    'conversation_not_found',
                    'Conversation not found.',
                    $requestId,
                ));
            }

            $sessionId = $request->hasSession()
                ? $request->session()->getId()
                : '';

            if (
                $sessionId === ''
                || ! $sessions->sessionIsLive((int) $actor->id, $sessionId)
                || $sessions->isRevoked((int) $actor->id, $sessionId)
            ) {
                throw new HttpResponseException(ApiErrorResponse::make(
                    401,
                    'realtime_session_invalid',
                    'A valid browser session is required.',
                    $requestId,
                ));
            }

            $grant = $sessions->grant((int) $actor->id, $sessionId);

            return [
                'channel' => $sessions->channel(
                    $conversationId,
                    $grant->channel_token,
                ),
                'event' => 'conversation.messages_changed',
                'expires_at' => CarbonImmutable::parse($grant->expires_at)
                    ->utc()
                    ->toISOString(),
            ];
        }, 3);
    }
}
