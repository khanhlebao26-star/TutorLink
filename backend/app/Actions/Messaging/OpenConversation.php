<?php

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\ServiceListing;
use App\Models\TutorProfile;
use App\Models\User;
use App\Support\ApiErrorResponse;
use App\Support\TutorEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class OpenConversation
{
    /** @return array{conversation: Conversation, peer: User, created: bool} */
    public function execute(
        User $actor,
        int $peerUserId,
        ?int $contextListingId,
        string $requestId,
    ): array {
        return DB::transaction(function () use ($actor, $peerUserId, $contextListingId, $requestId): array {
            if ($actor->id === $peerUserId) {
                $this->fail(422, 'peer_invalid', 'Choose another account.', $requestId);
            }

            $userIds = [$actor->id, $peerUserId];
            sort($userIds, SORT_NUMERIC);

            $users = User::query()
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $currentActor = $users->get($actor->id);
            $peer = $users->get($peerUserId);

            if ($currentActor === null || $currentActor->status !== 'active') {
                $this->fail(
                    403,
                    'account_inactive',
                    'This account is not available.',
                    $requestId,
                );
            }

            if (! in_array($currentActor->role, ['customer', 'tutor'], true)) {
                $this->fail(
                    403,
                    'conversation_forbidden',
                    'Only Customers and Tutors may open a Conversation.',
                    $requestId,
                );
            }

            if (
                $peer === null
                || ! in_array($peer->role, ['customer', 'tutor'], true)
                || $peer->role === $currentActor->role
            ) {
                $this->fail(
                    422,
                    'peer_invalid',
                    'Choose an account with the opposite role.',
                    $requestId,
                );
            }

            if ($peer->status !== 'active') {
                $this->fail(
                    409,
                    'peer_unavailable',
                    'This contact is not available.',
                    $requestId,
                );
            }

            $tutor = $currentActor->role === 'tutor' ? $currentActor : $peer;

            $profile = TutorProfile::query()
                ->where('user_id', $tutor->id)
                ->lockForUpdate()
                ->first();

            $now = CarbonImmutable::now('UTC');

            if (
                $profile === null
                || ! app(TutorEligibility::class)->canManagePrograms($tutor, $profile, $now)
            ) {
                $this->fail(
                    403,
                    'tutor_ineligible',
                    'Tutor is not eligible for a new contact.',
                    $requestId,
                );
            }

            if ($contextListingId !== null) {
                $listing = ServiceListing::query()
                    ->whereKey($contextListingId)
                    ->lockForUpdate()
                    ->first();

                if (
                    $listing === null
                    || (int) $listing->tutor_profile_id !== (int) $profile->id
                ) {
                    $this->fail(
                        422,
                        'context_listing_invalid',
                        'Choose a public Listing owned by this Tutor.',
                        $requestId,
                    );
                }

                $held = DB::table('listing_moderation_holds')
                    ->where('listing_id', $listing->id)
                    ->whereNull('restored_at')
                    ->exists();

                if ($held) {
                    $this->fail(
                        409,
                        'listing_moderation_hold',
                        'This Listing is under moderation hold.',
                        $requestId,
                    );
                }

                if (! ServiceListing::query()->visiblePublic()->whereKey($listing->id)->exists()) {
                    $this->fail(
                        422,
                        'context_listing_invalid',
                        'Choose a public Listing owned by this Tutor.',
                        $requestId,
                    );
                }
            }

            $directKey = implode(':', $userIds);

            $conversation = Conversation::query()
                ->where('direct_key', $directKey)
                ->lockForUpdate()
                ->first();

            $created = $conversation === null;

            if ($created) {
                $conversation = Conversation::create([
                    'type' => 'direct',
                    'direct_key' => $directKey,
                    'status' => 'active',
                    'context_listing_id' => $contextListingId,
                    'activated_at' => $now,
                ]);

                $conversation->members()->attach($userIds, [
                    'joined_at' => $now,
                ]);
            } else {
                $memberIds = DB::table('conversation_members')
                    ->where('conversation_id', $conversation->id)
                    ->orderBy('user_id')
                    ->lockForUpdate()
                    ->pluck('user_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                if (
                    $conversation->type !== 'direct'
                    || $memberIds !== $userIds
                ) {
                    $this->fail(
                        409,
                        'conversation_membership_conflict',
                        'Conversation membership requires review.',
                        $requestId,
                    );
                }

                if (! in_array($conversation->status, ['active', 'cancelled'], true)) {
                    $this->fail(
                        409,
                        'conversation_state_invalid',
                        'Conversation state requires review.',
                        $requestId,
                    );
                }

                if ($conversation->status === 'cancelled') {
                    $conversation->forceFill([
                        'status' => 'active',
                        'ended_at' => null,
                    ])->save();
                }
            }

            return [
                'conversation' => $conversation,
                'peer' => $peer,
                'created' => $created,
            ];
        }, 3);
    }

    private function fail(
        int $status,
        string $code,
        string $message,
        string $requestId,
    ): never {
        throw new HttpResponseException(
            ApiErrorResponse::make($status, $code, $message, $requestId),
        );
    }
}
