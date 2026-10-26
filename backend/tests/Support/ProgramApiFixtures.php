<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProgramApiFixtures
{
    public static function eligibleTutor(): User
    {
        $tutor = TestActors::tutor();
        $now = now('UTC');
        DB::table('tutor_profiles')->where('id', $tutor->tutorProfile->id)->update(['status' => 'approved']);
        $fileId = DB::table('files')->insertGetId([
            'owner_id' => $tutor->id,
            'disk' => 'local',
            'object_key' => 'tests/'.Str::uuid().'.pdf',
            'original_name' => 'identity.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'private',
            'checksum_sha256' => null,
            'scan_status' => 'clean',
            'purpose' => 'verification_document',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('verification_documents')->insert([
            'tutor_profile_id' => $tutor->tutorProfile->id,
            'file_id' => $fileId,
            'document_type' => 'identity_card',
            'status' => 'approved',
            'reviewed_by' => null,
            'reviewed_at' => $now,
            'expires_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $tutor->fresh('tutorProfile');
    }

    /** @param array<string, mixed> $overrides */
    public static function draft(User $tutor, User $customer, array $overrides = []): int
    {
        $now = now('UTC');

        return DB::table('programs')->insertGetId(array_replace([
            'owner_tutor_profile_id' => $tutor->tutorProfile->id,
            'source_listing_id' => null,
            'customer_id' => $customer->id,
            'version' => 1,
            'title' => 'English lessons',
            'goal' => 'Build confidence.',
            'level' => 'Intermediate',
            'delivery_mode' => 'online',
            'agreed_fee_minor' => 300000,
            'currency' => 'VND',
            'fee_unit' => 'session',
            'location_text' => null,
            'meeting_url' => null,
            'schedule_note' => null,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'planned_session_count' => 8,
            'status' => 'draft',
            'offer_snapshot' => null,
            'snapshot_locked_at' => null,
            'activated_at' => null,
            'ends_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    public static function invitation(int $programId, User $tutor, User $customer, string $expiresAt): int
    {
        $now = now('UTC');

        return DB::table('program_invitations')->insertGetId([
            'program_id' => $programId,
            'program_version' => 1,
            'invitee_user_id' => $customer->id,
            'invited_by' => $tutor->id,
            'status' => 'pending',
            'offer_snapshot' => json_encode([
                'program_id' => $programId,
                'target_customer_id' => $customer->id,
                'source_listing_id' => null,
                'offer' => [
                    'title' => 'English lessons',
                    'goal' => 'Build confidence.',
                    'level' => 'Intermediate',
                    'delivery_mode' => 'online',
                    'agreed_fee_minor' => 300000,
                    'currency' => 'VND',
                    'fee_unit' => 'session',
                    'location_text' => null,
                    'meeting_url' => null,
                    'schedule_note' => null,
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'planned_session_count' => 8,
                ],
                'source_listing' => null,
            ], JSON_THROW_ON_ERROR),
            'idempotency_key' => (string) Str::uuid(),
            'expires_at' => $expiresAt,
            'responded_at' => null,
            'accepted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function listing(User $tutor): int
    {
        $now = now('UTC');
        $suffix = Str::lower(Str::random(8));
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Languages',
            'slug' => 'languages-'.$suffix,
            'status' => 'active',
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $subcategoryId = DB::table('subcategories')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'English',
            'slug' => 'english-'.$suffix,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $specializationId = DB::table('specializations')->insertGetId([
            'subcategory_id' => $subcategoryId,
            'name' => 'Conversation',
            'slug' => 'conversation-'.$suffix,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('service_listings')->insertGetId([
            'tutor_profile_id' => $tutor->tutorProfile->id,
            'specialization_id' => $specializationId,
            'slug' => 'english-conversation-'.$suffix,
            'title' => 'English conversation',
            'description' => 'Practice speaking.',
            'delivery_mode' => 'online',
            'city' => 'Hanoi',
            'district' => 'Ba Dinh',
            'price_from_minor' => 250000,
            'currency' => 'VND',
            'price_unit' => 'session',
            'status' => 'inactive',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function addMember(int $programId, User $user, string $role): void
    {
        $now = now('UTC');
        DB::table('program_members')->insert([
            'program_id' => $programId,
            'user_id' => $user->id,
            'member_role' => $role,
            'status' => 'active',
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
