<?php

namespace Database\Seeders;

use App\Models\AvailabilityRule;
use App\Models\CustomerProfile;
use App\Models\ServiceListing;
use App\Models\Specialization;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates the two local walkthrough accounts and their supporting profiles.
 * This is intentionally separate from DatabaseSeeder and is never run
 * automatically by application startup.
 */
class LocalDemoDataSeeder extends Seeder
{
    private const TUTOR_EMAIL = 'tutor.demo@tutorlink.test';

    private const STUDENT_EMAIL = 'student.demo@tutorlink.test';

    private const PASSWORD = 'TutorLinkDemo2026!';

    public function run(): void
    {
        $this->assertSafeLocalDatabase();

        if (config('tutor.verification_documents_required')) {
            throw new RuntimeException(
                'LocalDemoDataSeeder expects TUTOR_PROFILE_DOCUMENTS_REQUIRED=false. '
                .'It does not create fake identity or qualification documents.',
            );
        }

        $this->call(CatalogSeeder::class);

        DB::transaction(function (): void {
            $admin = User::query()
                ->where('role', 'admin')
                ->where('status', 'active')
                ->orderBy('id')
                ->first();

            if (! $admin) {
                throw new RuntimeException(
                    'No active local admin found. Run DatabaseSeeder first '
                    .'with LOCAL_ADMIN_EMAIL and LOCAL_ADMIN_PASSWORD configured.',
                );
            }

            $now = now('UTC');
            $tutor = $this->demoUser(
                self::TUTOR_EMAIL,
                'tutor',
                'Nguyễn Minh Anh',
                $now,
            );
            $student = $this->demoUser(
                self::STUDENT_EMAIL,
                'customer',
                'Trần Gia Huy',
                $now,
            );

            $tutorProfile = TutorProfile::query()->updateOrCreate(['user_id' => $tutor->id], TutorProfile::factory()->raw(array_merge(['user_id' => $tutor->id], [
                'headline' => 'Gia sư tiếng Anh giao tiếp và tiếng Anh công việc',
                'bio' => 'Mình đồng hành cùng người học muốn giao tiếp tiếng Anh tự tin hơn trong đời sống và công việc. Buổi học tập trung vào mục tiêu thực tế, luyện nói có hướng dẫn và phản hồi cụ thể để người học biết mình cần cải thiện điều gì.',
                'experience_years' => 6,
                'approval_status' => 'active',
                'status' => 'approved',
                'submitted_at' => $now->copy()->subDays(5),
                'reviewed_by' => $admin->id,
                'reviewed_at' => $now->copy()->subDays(4),
                'review_reason' => null,
                'suspended_at' => null,
                'avatar_file_id' => null,
                'qualification_claims' => [],
            ])));

            $studentProfile = [
                'city' => 'Hồ Chí Minh',
                'district' => 'Bình Thạnh',
                'bio' => 'Muốn luyện tiếng Anh giao tiếp đều đặn để tự tin hơn khi trao đổi trong công việc. Mục tiêu hiện tại là tăng phản xạ nghe nói, mở rộng vốn từ dùng trong công việc và chuẩn bị để trao đổi với đồng nghiệp quốc tế. Ưu tiên học trực tuyến vào buổi tối hoặc sáng thứ Bảy.',
            ];
            foreach ([
                'subjects_of_interest' => 'Tiếng Anh giao tiếp, tiếng Anh công việc',
                'learning_level' => 'Sơ trung cấp',
                'learning_goals' => 'Tăng phản xạ nghe nói, mở rộng vốn từ dùng trong công việc và chuẩn bị để trao đổi với đồng nghiệp quốc tế.',
                'preferred_schedule' => 'Buổi tối các ngày trong tuần hoặc sáng thứ Bảy',
                'preferred_mode' => 'online',
            ] as $column => $value) {
                if (Schema::hasColumn('customer_profiles', $column)) {
                    $studentProfile[$column] = $value;
                }
            }
            CustomerProfile::query()->updateOrCreate(['user_id' => $student->id], CustomerProfile::factory()->raw(array_merge(['user_id' => $student->id], $studentProfile)));

            $conversation = $this->activeSpecialization('conversation-english');
            $business = $this->activeSpecialization('business-english');
            $tutorProfile->specializations()->sync([
                $conversation->id,
                $business->id,
            ]);

            $this->listing($tutorProfile, $conversation, [
                'slug' => 'demo-english-conversation',
                'title' => 'Tiếng Anh giao tiếp thực tế',
                'description' => 'Luyện nghe nói qua các tình huống đời sống và công việc thường ngày. Gia sư điều chỉnh chủ đề theo mục tiêu của học viên, hướng dẫn cách diễn đạt tự nhiên và tổng kết điểm cần luyện sau mỗi buổi.',
                'delivery_mode' => 'online',
                'city' => null,
                'district' => null,
                'price_from_minor' => 250000,
                'currency' => 'VND',
                'price_unit' => 'session',
                'status' => 'active',
            ]);

            $this->listing($tutorProfile, $business, [
                'slug' => 'demo-business-english',
                'title' => 'Tiếng Anh ứng dụng trong công việc',
                'description' => 'Thực hành tiếng Anh cho họp nhóm, cập nhật tiến độ, trao đổi qua email và giới thiệu ý tưởng. Nội dung được thống nhất với học viên trước khi bắt đầu để bám sát ngành nghề và tình huống cần dùng.',
                'delivery_mode' => 'online',
                'city' => null,
                'district' => null,
                'price_from_minor' => 350000,
                'currency' => 'VND',
                'price_unit' => 'session',
                'status' => 'active',
            ]);

            $this->seedAvailability($tutorProfile, [
                [0, '18:00', '21:00'],
                [2, '18:00', '21:00'],
                [5, '09:00', '12:00'],
            ]);

            $this->seedSupportingMarketplaceTutors($admin->id, $now);
        });

        $this->command?->info('Local demo data is ready.');
        $this->command?->line('Tutor: '.self::TUTOR_EMAIL);
        $this->command?->line('Student: '.self::STUDENT_EMAIL);
        $this->command?->line('Password for both: '.self::PASSWORD);
        $this->command?->line('Three additional public tutor profiles support Education, Fitness, and Sports listings; no credentials are provided for them.');
        $this->command?->line('No conversations, invitations, Programs, or lessons were created.');
    }

    private function assertSafeLocalDatabase(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'LocalDemoDataSeeder only runs when APP_ENV=local.',
            );
        }

        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}", []);

        if (
            ($connection['driver'] ?? null) !== 'pgsql'
            || ($connection['database'] ?? null) !== 'tutorlink'
            || ! in_array($connection['host'] ?? null, ['db', 'localhost', '127.0.0.1'], true)
            || ! empty($connection['url'])
            || ! empty($connection['read'])
            || ! empty($connection['write'])
        ) {
            throw new RuntimeException(
                'Unsafe database configuration. Use the local PostgreSQL database named tutorlink.',
            );
        }

        $identity = DB::connection($connectionName)->selectOne(
            'select current_database() as database, host(inet_server_addr()) as server_address',
        );
        $serverAddress = $identity->server_address ?? null;
        $isPrivateAddress = is_string($serverAddress)
            && filter_var($serverAddress, FILTER_VALIDATE_IP) !== false
            && filter_var(
                $serverAddress,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false;

        if (
            ($identity->database ?? null) !== 'tutorlink'
            || ! $isPrivateAddress
        ) {
            throw new RuntimeException(
                'Connected database identity is not the expected private local tutorlink database.',
            );
        }
    }

    private function demoUser(
        string $email,
        string $role,
        string $name,
        mixed $now,
    ): User {
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user && $user->role !== $role) {
            throw new RuntimeException(
                "Reserved demo email {$email} is already used by a different role.",
            );
        }

        if (! $user) {
            $user = User::factory()->make();
            $user->email = $email;
        }

        if ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill([
            'full_name' => $name,
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => $now,
            'password_hash' => Hash::make(self::PASSWORD),
            'terms_version' => 'local-demo-2026-01',
            'suspended_at' => null,
            'deleted_at' => null,
        ])->save();

        return $user->refresh();
    }

    private function activeSpecialization(string $slug): Specialization
    {
        $specialization = Specialization::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->first();

        if (! $specialization) {
            throw new RuntimeException(
                "Active catalog specialization {$slug} was not found. Run CatalogSeeder first.",
            );
        }

        return $specialization;
    }

    private function seedSupportingMarketplaceTutors(int $adminId, mixed $now): void
    {
        $tutors = [
            [
                'email' => 'demo.math.tutor@tutorlink.test',
                'name' => 'Phạm Quang Huy',
                'headline' => 'Gia sư Toán trung học phổ thông',
                'bio' => 'Hướng dẫn học sinh xây nền tảng đại số và hình học, hiểu cách giải thay vì học thuộc lời giải. Mỗi buổi tập trung vào một mục tiêu nhỏ và có bài luyện phù hợp với trình độ hiện tại.',
                'experience_years' => 5,
                'specialization' => 'high-school-math',
                'listing' => [
                    'slug' => 'demo-high-school-math',
                    'title' => 'Toán THPT: vững nền tảng, hiểu cách giải',
                    'description' => 'Ôn tập đại số, hàm số và hình học theo chương trình THPT. Gia sư kiểm tra phần kiến thức còn hổng, giải thích từng bước và cùng học viên luyện dạng bài đang cần cải thiện.',
                    'delivery_mode' => 'online',
                    'city' => null,
                    'district' => null,
                    'price_from_minor' => 300000,
                    'currency' => 'VND',
                    'price_unit' => 'session',
                    'status' => 'active',
                ],
                'availability' => [
                    [1, '18:00', '21:00'],
                    [3, '18:00', '21:00'],
                    [6, '09:00', '12:00'],
                ],
            ],
            [
                'email' => 'demo.fitness.coach@tutorlink.test',
                'name' => 'Lê Thu Hà',
                'headline' => 'Huấn luyện viên thể lực cá nhân',
                'bio' => 'Đồng hành với người mới bắt đầu rèn luyện sức mạnh an toàn và đều đặn. Kế hoạch tập được điều chỉnh theo thể trạng, mục tiêu và thiết bị người tập đang có.',
                'experience_years' => 4,
                'specialization' => 'strength-training',
                'listing' => [
                    'slug' => 'demo-strength-training',
                    'title' => 'Huấn luyện sức mạnh cá nhân',
                    'description' => 'Buổi tập 1:1 tập trung vào kỹ thuật cơ bản, sức mạnh và thói quen vận động bền vững. Huấn luyện viên trao đổi mục tiêu trước buổi đầu và hướng dẫn điều chỉnh động tác trong suốt buổi tập.',
                    'delivery_mode' => 'offline',
                    'city' => 'Hồ Chí Minh',
                    'district' => 'Bình Thạnh',
                    'price_from_minor' => 400000,
                    'currency' => 'VND',
                    'price_unit' => 'session',
                    'status' => 'active',
                ],
                'availability' => [
                    [0, '17:00', '20:00'],
                    [2, '17:00', '20:00'],
                    [4, '17:00', '20:00'],
                ],
            ],
            [
                'email' => 'demo.badminton.coach@tutorlink.test',
                'name' => 'Võ Minh Quân',
                'headline' => 'Huấn luyện viên cầu lông',
                'bio' => 'Hướng dẫn người mới và người chơi phong trào cải thiện cách cầm vợt, di chuyển, giao cầu và phối hợp động tác. Buổi tập ưu tiên luyện kỹ thuật đúng và an toàn theo mục tiêu của học viên.',
                'experience_years' => 7,
                'specialization' => 'badminton',
                'listing' => [
                    'slug' => 'demo-badminton-coaching',
                    'title' => 'Cầu lông: kỹ thuật nền tảng và di chuyển',
                    'description' => 'Luyện các kỹ thuật cầu lông cơ bản, bộ chân và khả năng giữ nhịp trong pha cầu. Huấn luyện viên điều chỉnh bài tập theo trình độ và mục tiêu của người chơi.',
                    'delivery_mode' => 'offline',
                    'city' => 'Hồ Chí Minh',
                    'district' => 'Bình Thạnh',
                    'price_from_minor' => 300000,
                    'currency' => 'VND',
                    'price_unit' => 'session',
                    'status' => 'active',
                ],
                'availability' => [
                    [1, '18:00', '20:00'],
                    [3, '18:00', '20:00'],
                    [5, '09:00', '12:00'],
                ],
            ],
        ];

        foreach ($tutors as $data) {
            $user = $this->supportingTutorUser(
                $data['email'],
                $data['name'],
                $now,
            );
            $profile = TutorProfile::query()->updateOrCreate(['user_id' => $user->id], TutorProfile::factory()->raw(array_merge(['user_id' => $user->id], [
                'headline' => $data['headline'],
                'bio' => $data['bio'],
                'experience_years' => $data['experience_years'],
                'approval_status' => 'active',
                'status' => 'approved',
                'submitted_at' => $now->copy()->subDays(5),
                'reviewed_by' => $adminId,
                'reviewed_at' => $now->copy()->subDays(4),
                'review_reason' => null,
                'suspended_at' => null,
                'avatar_file_id' => null,
                'qualification_claims' => [],
            ])));
            $specialization = $this->activeSpecialization($data['specialization']);
            $profile->specializations()->sync([$specialization->id]);
            $this->listing($profile, $specialization, $data['listing']);
            $this->seedAvailability($profile, $data['availability']);
        }
    }

    private function supportingTutorUser(
        string $email,
        string $name,
        mixed $now,
    ): User {
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user && $user->role !== 'tutor') {
            throw new RuntimeException(
                "Reserved supporting tutor email {$email} is already used by a different role.",
            );
        }

        if (! $user) {
            $user = User::factory()->make();
            $user->email = $email;
            $user->password_hash = Hash::make(Str::random(64));
        }

        if ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill([
            'full_name' => $name,
            'role' => 'tutor',
            'status' => 'active',
            'email_verified_at' => $now,
            'terms_version' => 'local-demo-2026-01',
            'suspended_at' => null,
            'deleted_at' => null,
        ])->save();

        return $user->refresh();
    }

    /** @param array<string, mixed> $attributes */
    private function listing(
        TutorProfile $profile,
        Specialization $specialization,
        array $attributes,
    ): void {
        $existing = ServiceListing::query()
            ->where('slug', $attributes['slug'])
            ->first();

        if ($existing && $existing->tutor_profile_id !== $profile->id) {
            throw new RuntimeException(
                "Reserved demo service slug {$attributes['slug']} belongs to another tutor.",
            );
        }

        ServiceListing::query()->updateOrCreate(['slug' => $attributes['slug']], ServiceListing::factory()->raw(array_merge(['slug' => $attributes['slug']], [
            ...$attributes,
            'tutor_profile_id' => $profile->id,
            'specialization_id' => $specialization->id,
        ])));
    }

    /** @param list<array{0: int, 1: string, 2: string}> $rules */
    private function seedAvailability(TutorProfile $profile, array $rules): void
    {
        AvailabilityRule::query()
            ->where('tutor_profile_id', $profile->id)
            ->delete();

        foreach ($rules as [$weekday, $start, $end]) {
            AvailabilityRule::factory()->create([
                'tutor_profile_id' => $profile->id,
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
                'timezone' => 'Asia/Ho_Chi_Minh',
            ]);
        }
    }
}
