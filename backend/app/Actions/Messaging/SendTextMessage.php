<?php

namespace App\Actions\Messaging;

use App\Events\ConversationMessagesChanged;
use App\Models\Conversation;
use App\Models\TutorProfile;
use App\Models\User;
use App\Support\ApiErrorResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use stdClass;

class SendTextMessage
{
    /**
     * The payload has already passed SendTextMessageRequest validation.
     *
     * @param  array{client_message_id:string,message_type:string,body:string,file_ids?:array}  $data
     * @return array{message:stdClass,created:bool}
     */
    public function execute(User $actor, int $conversationId, array $data, string $requestId): array
    {
        return DB::transaction(function () use ($actor, $conversationId, $data, $requestId): array {
            $userIds = $this->memberIds($conversationId);

            if (! in_array((int) $actor->id, $userIds, true)) {
                $this->fail(404, 'conversation_not_found', 'Conversation not found.', $requestId);
            }

            if (count($userIds) !== 2) {
                $this->fail(409, 'conversation_membership_conflict', 'Conversation membership requires review.', $requestId);
            }

            $users = User::withTrashed()->whereIn('id', $userIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $currentActor = $users->get($actor->id);

            if ($currentActor === null || $currentActor->deleted_at !== null || $currentActor->status !== 'active') {
                $this->fail(403, 'account_inactive', 'This account is not available.', $requestId);
            }

            if (! in_array($currentActor->role, ['customer', 'tutor'], true)) {
                $this->fail(403, 'conversation_forbidden', 'This account cannot send messages.', $requestId);
            }

            $peerId = $userIds[0] === (int) $currentActor->id ? $userIds[1] : $userIds[0];
            $peer = $users->get($peerId);
            $tutor = $users->firstWhere('role', 'tutor');
            $profile = $tutor === null ? null : TutorProfile::where('user_id', $tutor->id)->lockForUpdate()->first();
            $conversation = Conversation::whereKey($conversationId)->lockForUpdate()->first();
            $currentMemberIds = $this->memberIds($conversationId, true);

            if ($conversation === null || ! in_array((int) $currentActor->id, $currentMemberIds, true)) {
                $this->fail(404, 'conversation_not_found', 'Conversation not found.', $requestId);
            }

            if ($currentMemberIds !== $userIds || $conversation->type !== 'direct') {
                $this->fail(409, 'conversation_membership_conflict', 'Conversation membership requires review.', $requestId);
            }

            if (! in_array($conversation->status, ['active', 'cancelled'], true)) {
                $this->fail(409, 'conversation_state_invalid', 'Conversation state requires review.', $requestId);
            }

            $key = strtolower($data['client_message_id']);
            $body = str_replace(["\r\n", "\r"], "\n", $data['body']);
            $hash = $this->payloadHash($conversationId, 'text', $body, []);
            $existing = DB::table('messages')->where('sender_id', $currentActor->id)->where('client_message_id', $key)->first();

            if ($existing !== null) {
                $existingHash = $existing->payload_hash;

                if ($existingHash === null) {
                    $fileIds = DB::table('file_links')->where('resource_type', 'message')
                        ->where('resource_id', $existing->id)->orderBy('file_id')->pluck('file_id')
                        ->map(fn ($id): int => (int) $id)->all();
                    $existingHash = $this->payloadHash(
                        (int) $existing->conversation_id,
                        $existing->message_type,
                        $existing->body,
                        $fileIds,
                    );
                }

                if (! hash_equals($existingHash, $hash)) {
                    $this->fail(409, 'client_message_id_conflict', 'client_message_id was used with a different payload.', $requestId);
                }

                return ['message' => $existing, 'created' => false];
            }

            if ($conversation->status !== 'active') {
                $this->fail(409, 'conversation_not_active', 'Conversation is not active.', $requestId);
            }

            if ($peer === null || $peer->deleted_at !== null || $peer->status !== 'active') {
                $this->fail(409, 'peer_unavailable', 'This contact is not available for new messages.', $requestId);
            }

            if (
                $peer->role === $currentActor->role || ! in_array($peer->role, ['customer', 'tutor'], true)
                || $profile === null || $profile->status === 'suspended' || $profile->suspended_at !== null
            ) {
                $this->fail(409, 'conversation_unavailable', 'This relationship is not available for new messages.', $requestId);
            }

            $now = CarbonImmutable::now('UTC');
            $recent = DB::table('messages')->where('sender_id', $currentActor->id)
                ->whereIn('message_type', ['text', 'file'])
                ->where('created_at', '>', $now->subSeconds(60)->format('Y-m-d H:i:s.uP'))
                ->orderByDesc('created_at')->orderByDesc('id')->limit(30)->get(['created_at']);

            if ($recent->count() === 30) {
                $retryAt = CarbonImmutable::parse($recent->last()->created_at)->addSeconds(60);
                $seconds = max(1, (int) ceil($now->diffInSeconds($retryAt, false)));
                $response = ApiErrorResponse::make(429, 'message_rate_limited', 'Too many new messages. Retry later.', $requestId);
                $response->headers->set('Retry-After', (string) $seconds);
                throw new HttpResponseException($response);
            }

            $latest = DB::table('messages')->where('conversation_id', $conversationId)
                ->orderByDesc('created_at')->orderByDesc('id')->value('created_at');
            $createdAt = $latest === null ? $now : $now->max(CarbonImmutable::parse($latest));
            $timestamp = $createdAt->format('Y-m-d H:i:s.uP');
            $id = DB::table('messages')->insertGetId([
                'conversation_id' => $conversationId,
                'sender_id' => $currentActor->id,
                'client_message_id' => $key,
                'message_type' => 'text',
                'body' => $body,
                'payload_hash' => $hash,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            ConversationMessagesChanged::dispatch($conversationId);

            return ['message' => DB::table('messages')->where('id', $id)->first(), 'created' => true];
        }, 3);
    }

    /** @return list<int> */
    private function memberIds(int $conversationId, bool $lock = false): array
    {
        $query = DB::table('conversation_members')->where('conversation_id', $conversationId)->orderBy('user_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @param list<int> $fileIds */
    private function payloadHash(int $conversationId, string $type, ?string $body, array $fileIds): string
    {
        return hash('sha256', json_encode([
            'conversation_id' => $conversationId,
            'message_type' => $type,
            'body' => $body === null ? null : str_replace(["\r\n", "\r"], "\n", $body),
            'file_ids' => $fileIds,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function fail(int $status, string $code, string $message, string $requestId): never
    {
        throw new HttpResponseException(ApiErrorResponse::make($status, $code, $message, $requestId));
    }
}
