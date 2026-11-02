<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AdminPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

class BeD04FixtureSeeder extends Seeder
{
    private const FIXTURE_TIME = '2026-11-01 00:00:00+07:00';

    public function run(): void
    {
        $identity = TestDatabaseGuard::assertConnectedIdentity(config());

        if ($identity['database'] !== 'tutorlink_test') {
            throw new RuntimeException('BE-D04 fixtures can only be seeded into tutorlink_test.');
        }

        DB::transaction(function (): void {
            $actors = $this->seedActors();
            $programs = $this->seedPrograms($actors);
            $this->seedMemberships($programs, $actors);
            $announcements = $this->seedAnnouncements($programs, $actors);
            $this->seedFiles($announcements, $actors);
            $this->seedFees($programs['active']);
            $this->seedProgress($programs['active'], $actors);
            $this->seedSessionsAndTasks($programs['active'], $actors);
        });

        $this->seedFileObjects();

        $this->command?->info(
            'BE-D04 fixtures ready: 8 actors, 4 Programs, 59 Fees, 55 Progress records, 4 Announcements, 5 files.',
        );
    }

    /** @return array<string, User> */
    private function seedActors(): array
    {
        return [
            'owner' => $this->user('owner', 'D04 Fixture Owner', 'tutor'),
            'other_tutor' => $this->user('other-tutor', 'D04 Fixture Other Tutor', 'tutor'),
            'member' => $this->user('member', 'D04 Fixture Member', 'customer'),
            'outsider' => $this->user('outsider', 'D04 Fixture Outsider', 'customer'),
            'admin' => $this->user('admin', 'D04 Fixture Admin', 'admin'),
            'inactive' => $this->user('inactive', 'D04 Fixture Inactive', 'customer', 'suspended'),
            'revoked_member' => $this->user('revoked', 'D04 Fixture Revoked Member', 'customer'),
            'late_member' => $this->user('late-member', 'D04 Fixture Late Member', 'customer'),
        ];
    }

    private function user(
        string $handle,
        string $name,
        string $role,
        string $status = 'active',
    ): User {
        $email = "be-d04-{$handle}@tutorlink.test";
        $attributes = [
            'full_name' => $name,
            'email' => $email,
            'email_verified_at' => '2026-01-01 00:00:00+00:00',
            'role' => $role,
            'status' => $status,
            'date_of_birth' => '1990-01-01',
            'terms_version' => 'd04-fixture-v1',
            'suspended_at' => $status === 'suspended' ? '2026-10-01 00:00:00+07:00' : null,
            'permissions' => $role === 'admin' ? AdminPermissions::all() : [],
        ];

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $user = User::factory()->make($attributes);
        } else {
            $user->forceFill($attributes);
        }
        $user->save();

        if ($role === 'customer') {
            DB::table('customer_profiles')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'city' => 'Ha Noi',
                    'district' => 'Ba Dinh',
                    'bio' => 'Synthetic BE-D04 test actor.',
                    'created_at' => self::FIXTURE_TIME,
                    'updated_at' => self::FIXTURE_TIME,
                ],
            );
        }

        if ($role === 'tutor') {
            $user->tutorProfile()->updateOrCreate([], [
                'headline' => $name,
                'bio' => 'Synthetic BE-D04 tutor fixture.',
                'status' => 'approved',
                'approval_status' => 'active',
            ]);
        }

        return $user->fresh('tutorProfile', 'customerProfile');
    }

    /** @param array<string, User> $actors
     * @return array<string, int>
     */
    private function seedPrograms(array $actors): array
    {
        $specs = [
            'active' => ['owner', 'late_member', 'active', '2022-01-01 00:00:00+07:00', null],
            'paused' => ['owner', 'revoked_member', 'paused', '2026-10-01 00:00:00+07:00', null],
            'completed' => ['other_tutor', 'member', 'completed', '2026-08-01 00:00:00+07:00', '2026-10-31 23:59:59+07:00'],
            'cancelled' => ['other_tutor', 'member', 'cancelled', '2026-08-01 00:00:00+07:00', '2026-10-15 12:00:00+07:00'],
        ];
        $programs = [];

        foreach ($specs as $key => [$ownerKey, $customerKey, $status, $activatedAt, $endsAt]) {
            $owner = $actors[$ownerKey];
            $programs[$key] = $this->upsertId(
                'programs',
                [
                    'owner_tutor_profile_id' => $owner->tutorProfile->id,
                    'title' => 'D04 Fixture '.ucfirst($key),
                ],
                [
                    'source_listing_id' => null,
                    'customer_id' => $actors[$customerKey]->id,
                    'version' => 1,
                    'goal' => 'Synthetic workspace fixture.',
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
                    'status' => $status,
                    'offer_snapshot' => null,
                    'snapshot_locked_at' => null,
                    'activated_at' => $activatedAt,
                    'ends_at' => $endsAt,
                    'created_at' => '2026-01-01 00:00:00+00:00',
                    'updated_at' => self::FIXTURE_TIME,
                ],
            );
        }

        return $programs;
    }

    /** @param array<string, int> $programs
     * @param  array<string, User>  $actors
     */
    private function seedMemberships(array $programs, array $actors): void
    {
        $memberships = [
            ['active', 'late_member', 'active', '2026-10-21 00:00:00+07:00', null],
            ['paused', 'revoked_member', 'cancelled', '2026-09-01 00:00:00+07:00', '2026-10-19 00:00:00+07:00'],
            ['completed', 'member', 'active', '2026-08-01 00:00:00+07:00', null],
            ['cancelled', 'member', 'active', '2026-08-01 00:00:00+07:00', null],
        ];

        foreach ($memberships as [$programKey, $actorKey, $status, $joinedAt, $leftAt]) {
            $this->upsertId(
                'program_members',
                [
                    'program_id' => $programs[$programKey],
                    'user_id' => $actors[$actorKey]->id,
                ],
                [
                    'member_role' => 'customer',
                    'status' => $status,
                    'joined_at' => $joinedAt,
                    'left_at' => $leftAt,
                    'created_at' => $joinedAt,
                    'updated_at' => $leftAt ?? $joinedAt,
                ],
            );
        }
    }

    /** @param array<string, int> $programs
     * @param  array<string, User>  $actors
     * @return array<string, int>
     */
    private function seedAnnouncements(array $programs, array $actors): array
    {
        $publishedAt = '2026-10-20 10:00:00+07:00';
        $published = [];

        foreach (['Tie A', 'Tie B'] as $titleSuffix) {
            $published[$titleSuffix] = $this->upsertId(
                'announcements',
                [
                    'program_id' => $programs['active'],
                    'title' => 'D04 Fixture Published '.$titleSuffix,
                ],
                [
                    'author_id' => $actors['owner']->id,
                    'body' => 'Synthetic published announcement for BE-D04.',
                    'published_at' => $publishedAt,
                    'created_at' => $publishedAt,
                    'updated_at' => $publishedAt,
                ],
            );
        }

        $draft = $this->upsertId(
            'announcements',
            [
                'program_id' => $programs['active'],
                'title' => 'D04 Fixture Draft Owner Only',
            ],
            [
                'author_id' => $actors['owner']->id,
                'body' => 'Synthetic draft; customers must not receive it.',
                'published_at' => null,
                'created_at' => '2026-10-22 10:00:00+07:00',
                'updated_at' => '2026-10-22 10:00:00+07:00',
            ],
        );

        $revokedProgramAnnouncement = $this->upsertId(
            'announcements',
            [
                'program_id' => $programs['paused'],
                'title' => 'D04 Fixture Published Before Revocation',
            ],
            [
                'author_id' => $actors['owner']->id,
                'body' => 'Synthetic announcement published before member revocation.',
                'published_at' => '2026-10-18 10:00:00+07:00',
                'created_at' => '2026-10-18 10:00:00+07:00',
                'updated_at' => '2026-10-18 10:00:00+07:00',
            ],
        );

        return [
            'published_tie_a' => $published['Tie A'],
            'published_tie_b' => $published['Tie B'],
            'published_before_revocation' => $revokedProgramAnnouncement,
            'draft' => $draft,
        ];
    }

    /** @param array<string, int> $announcements
     * @param  array<string, User>  $actors
     */
    private function seedFiles(array $announcements, array $actors): void
    {
        $content = $this->fixturePdf();
        $checksum = hash('sha256', $content);
        $fileSpecs = [
            'valid' => [$actors['owner']->id, 'announcement_attachment', 'clean'],
            'wrong_owner' => [$actors['other_tutor']->id, 'announcement_attachment', 'clean'],
            'wrong_purpose' => [$actors['owner']->id, 'verification_document', 'clean'],
            'pending' => [$actors['owner']->id, 'announcement_attachment', 'pending'],
            'rejected' => [$actors['owner']->id, 'announcement_attachment', 'rejected'],
        ];
        $fileIds = [];

        foreach ($fileSpecs as $key => [$ownerId, $purpose, $scanStatus]) {
            $objectKey = "be-d04/fixtures/{$key}.pdf";
            $fileIds[$key] = $this->upsertId(
                'files',
                ['object_key' => $objectKey],
                [
                    'owner_id' => $ownerId,
                    'disk' => 'local',
                    'original_name' => "d04-{$key}.pdf",
                    'mime_type' => 'application/pdf',
                    'size_bytes' => strlen($content),
                    'visibility' => 'private',
                    'checksum_sha256' => $checksum,
                    'scan_status' => $scanStatus,
                    'purpose' => $purpose,
                    'created_at' => self::FIXTURE_TIME,
                    'updated_at' => self::FIXTURE_TIME,
                ],
            );
        }

        DB::table('file_links')->updateOrInsert(
            [
                'file_id' => $fileIds['valid'],
                'resource_type' => 'announcement',
                'resource_id' => $announcements['published_tie_a'],
            ],
            [
                'purpose' => 'announcement_attachment',
                'created_at' => self::FIXTURE_TIME,
            ],
        );
    }

    private function seedFees(int $programId): void
    {
        $month = CarbonImmutable::parse('2022-01-01', 'Asia/Ho_Chi_Minh');
        $lastMonth = CarbonImmutable::parse('2026-11-01', 'Asia/Ho_Chi_Minh');
        $index = 0;

        while ($month <= $lastMonth) {
            $index++;
            $start = $month->startOfMonth()->toDateString();
            $this->upsertId(
                'fee_records',
                ['program_id' => $programId, 'period_start' => $start],
                [
                    'period_end' => $month->endOfMonth()->toDateString(),
                    'amount_minor' => $index === 1 ? 0 : 200000 + (($index % 4) * 25000),
                    'currency' => 'VND',
                    (Schema::hasColumn('fee_records', 'status') ? 'status' : 'declared_status') => 'declared',
                    'disclaimer' => null,
                    'created_at' => $month->setTime(12, 0)->utc()->toDateTimeString(),
                    'updated_at' => self::FIXTURE_TIME,
                ],
            );
            $month = $month->addMonthNoOverflow();
        }
    }

    /** @param array<string, User> $actors */
    private function seedProgress(int $programId, array $actors): void
    {
        for ($index = 1; $index <= 55; $index++) {
            $recordedAt = $index <= 2
                ? '2026-10-22 12:00:00+00:00'
                : CarbonImmutable::parse('2026-10-21 12:00:00+00:00')
                    ->subDays($index - 2)
                    ->toDateTimeString();
            $note = sprintf('D04_FIXTURE_PROGRESS_%03d', $index);

            $this->upsertId(
                'progress_records',
                [
                    'program_id' => $programId,
                    'customer_id' => $actors['late_member']->id,
                    'metric_name' => 'progress_percent',
                    'note' => $note,
                ],
                [
                    'value' => ($index * 7) % 101,
                    'recorded_by' => $actors['owner']->id,
                    'recorded_at' => $recordedAt,
                    'created_at' => $recordedAt,
                    'updated_at' => $recordedAt,
                ],
            );
        }
    }

    /** @param array<string, User> $actors */
    private function seedSessionsAndTasks(int $programId, array $actors): void
    {
        $sessions = [
            [1, 'D04 Fixture Upcoming Session', '2026-11-05 10:00:00+07:00', '2026-11-05 11:00:00+07:00', 'scheduled'],
            [2, 'D04 Fixture Completed Session', '2026-10-20 10:00:00+07:00', '2026-10-20 11:00:00+07:00', 'completed'],
            [3, 'D04 Fixture Cancelled Session', '2026-10-10 10:00:00+07:00', '2026-10-10 11:00:00+07:00', 'cancelled'],
        ];

        foreach ($sessions as [$sequence, $title, $startsAt, $endsAt, $status]) {
            $sessionId = $this->upsertId(
                'program_sessions',
                ['program_id' => $programId, 'sequence_number' => $sequence],
                [
                    'program_version' => 1,
                    'title' => $title,
                    'planned_starts_at' => $startsAt,
                    'planned_ends_at' => $endsAt,
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'delivery_mode' => 'online',
                    'location_text' => null,
                    'meeting_url' => null,
                    'status' => $status,
                    'created_at' => self::FIXTURE_TIME,
                    'updated_at' => self::FIXTURE_TIME,
                ],
            );

            if ($status !== 'scheduled') {
                $completed = $status === 'completed';
                $this->upsertId(
                    'lesson_sessions',
                    ['program_session_id' => $sessionId],
                    [
                        'status' => $status,
                        'started_at' => $completed ? $startsAt : null,
                        'ended_at' => $completed ? $endsAt : null,
                        'tutor_joined_at' => $completed ? $startsAt : null,
                        'customer_joined_at' => $completed ? $startsAt : null,
                        'completion_note' => $completed ? 'Synthetic completed fixture.' : null,
                        'created_at' => self::FIXTURE_TIME,
                        'updated_at' => self::FIXTURE_TIME,
                    ],
                );
            }
        }

        $taskIds = [];
        foreach (['todo', 'in_progress', 'submitted', 'completed', 'cancelled'] as $status) {
            $taskIds[$status] = $this->upsertId(
                'tasks',
                ['program_id' => $programId, 'title' => 'D04 Fixture Task '.ucfirst($status)],
                [
                    'description' => 'Synthetic task for Overview task_counts.',
                    'due_at' => $status === 'todo'
                        ? '2026-11-07 00:00:00+07:00'
                        : '2026-10-28 00:00:00+07:00',
                    'status' => $status,
                    'assigned_to' => $actors['late_member']->id,
                    'version' => 1,
                    'current_submission_id' => null,
                    'created_at' => self::FIXTURE_TIME,
                    'updated_at' => self::FIXTURE_TIME,
                ],
            );
        }

        foreach ([
            ['submitted', 'submitted', '00000000-0000-4000-8000-000000000101'],
            ['completed', 'accepted', '00000000-0000-4000-8000-000000000102'],
        ] as [$taskStatus, $submissionStatus, $idempotencyKey]) {
            $taskId = $taskIds[$taskStatus];
            $submissionId = $this->upsertId(
                'task_submissions',
                [
                    'task_id' => $taskId,
                    'customer_id' => $actors['late_member']->id,
                    'submission_version' => 1,
                ],
                [
                    'content' => 'Synthetic submission fixture.',
                    'status' => $submissionStatus,
                    'submitted_at' => '2026-10-28 12:00:00+07:00',
                    'feedback' => $submissionStatus === 'accepted' ? 'Synthetic accepted fixture.' : null,
                    'reviewed_by' => $submissionStatus === 'accepted' ? $actors['owner']->id : null,
                    'reviewed_at' => $submissionStatus === 'accepted' ? '2026-10-29 12:00:00+07:00' : null,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => hash('sha256', "d04-fixture-{$taskStatus}"),
                    'created_at' => self::FIXTURE_TIME,
                    'updated_at' => self::FIXTURE_TIME,
                ],
            );
            DB::table('tasks')->where('id', $taskId)->update([
                'current_submission_id' => $submissionId,
                'updated_at' => self::FIXTURE_TIME,
            ]);
        }
    }

    /** @param array<string, mixed> $identity
     * @param  array<string, mixed>  $values
     */
    private function upsertId(string $table, array $identity, array $values): int
    {
        DB::table($table)->updateOrInsert($identity, $values);

        return (int) DB::table($table)->where($identity)->value('id');
    }

    private function seedFileObjects(): void
    {
        $content = $this->fixturePdf();
        foreach (['valid', 'wrong_owner', 'wrong_purpose', 'pending', 'rejected'] as $key) {
            Storage::disk('local')->put("be-d04/fixtures/{$key}.pdf", $content);
        }
    }

    private function fixturePdf(): string
    {
        return "%PDF-1.4\n% Synthetic BE-D04 fixture.\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n";
    }
}
