<?php

namespace App\Actions\Programs;

use App\Exceptions\ProgramActionException;
use App\Models\Program;
use App\Models\User;
use App\Support\TutorEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateProgramDraft
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, array $data, string $requestId): Program
    {
        return DB::transaction(function () use ($actor, $data, $requestId): Program {
            $customerId = (int) $data['customer_id'];
            $userIds = array_values(array_unique([$actor->id, $customerId]));
            sort($userIds, SORT_NUMERIC);

            $users = DB::table('users')
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $tutor = $users->get($actor->id);
            $customer = $users->get($customerId);

            if ($tutor === null || $tutor->role !== 'tutor') {
                throw new ProgramActionException(403, 'tutor_required', 'Only a Tutor can create a Program.');
            }

            if (
                $customer === null
                || $customer->deleted_at !== null
                || $customer->role !== 'customer'
                || $customer->status !== 'active'
            ) {
                throw new ProgramActionException(
                    422,
                    'customer_invalid',
                    'customer_id must identify an active Customer.',
                    ['customer_id' => ['Choose an active Customer account.']],
                );
            }

            $profile = DB::table('tutor_profiles')
                ->where('user_id', $tutor->id)
                ->lockForUpdate()
                ->first();
            $now = CarbonImmutable::now('UTC');

            if (
                $profile === null
                || ! app(TutorEligibility::class)->canManagePrograms($tutor, $profile, $now)
            ) {
                throw new ProgramActionException(
                    403,
                    'tutor_ineligible',
                    'Tutor is not eligible to create a Program.',
                );
            }

            $listingId = $data['source_listing_id'] ?? null;

            if ($listingId !== null) {
                $listing = DB::table('service_listings')
                    ->where('id', $listingId)
                    ->lockForUpdate()
                    ->first();

                if ($listing === null || (int) $listing->tutor_profile_id !== (int) $profile->id) {
                    throw new ProgramActionException(
                        422,
                        'source_listing_invalid',
                        'source_listing_id must identify a Listing owned by this Tutor.',
                        ['source_listing_id' => ['Choose one of your Listings.']],
                    );
                }

                $hasActiveHold = DB::table('listing_moderation_holds')
                    ->where('listing_id', $listing->id)
                    ->whereNull('restored_at')
                    ->exists();

                if ($hasActiveHold) {
                    throw new ProgramActionException(
                        409,
                        'listing_moderation_hold',
                        'A Listing under moderation hold cannot be used as a Program source.',
                        ['source_listing_id' => ['This Listing is under moderation hold.']],
                    );
                }
            }

            $timestamp = $now->format('Y-m-d H:i:s.uP');
            $programData = [
                'owner_tutor_profile_id' => (int) $profile->id,
                'source_listing_id' => $listingId,
                'customer_id' => $customerId,
                'version' => 1,
                'title' => $data['title'],
                'goal' => $data['goal'] ?? null,
                'level' => $data['level'] ?? null,
                'delivery_mode' => $data['delivery_mode'],
                'agreed_fee_minor' => $data['agreed_fee_minor'],
                'currency' => $data['currency'] ?? 'VND',
                'fee_unit' => $data['fee_unit'],
                'location_text' => $data['location_text'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
                'schedule_note' => $data['schedule_note'] ?? null,
                'timezone' => $data['timezone'] ?? 'Asia/Ho_Chi_Minh',
                'planned_session_count' => $data['planned_session_count'] ?? null,
                'status' => 'draft',
                'offer_snapshot' => null,
                'snapshot_locked_at' => null,
                'activated_at' => null,
                'ends_at' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            $programId = DB::table('programs')->insertGetId($programData);

            DB::table('audit_logs')->insert([
                'actor_id' => $actor->id,
                'action' => 'program.create',
                'resource_type' => 'program',
                'resource_id' => $programId,
                'before' => null,
                'after' => json_encode([
                    'status' => 'draft',
                    'version' => 1,
                    'fields' => array_values(array_diff(array_keys($data), ['customer_id'])),
                ], JSON_THROW_ON_ERROR),
                'request_id' => $requestId,
                'created_at' => $timestamp,
            ]);

            return Program::query()
                ->withActionContext()
                ->findOrFail($programId);
        }, 3);
    }
}
