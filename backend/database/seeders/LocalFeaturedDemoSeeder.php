<?php

namespace Database\Seeders;

use App\Models\File;
use App\Models\Program;
use App\Models\ServiceListing;
use App\Models\TutorProfile;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local-only data for showcasing public marketplace listings.
 * The verification records use explicitly synthetic placeholder files.
 */
class LocalFeaturedDemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Demo-pass-2026!';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('LocalFeaturedDemoSeeder can only run in the local environment.');
        }

        $tutors = [
            [
                'email' => 'demo.tutor.education@example.test',
                'name' => 'Nguyễn Mai',
                'headline' => 'Gia sư Toán THCS',
                'bio' => 'Đồng hành cùng học sinh củng cố kiến thức Toán, luyện đề và xây dựng lộ trình học phù hợp.',
                'experience' => 6,
                'specialization' => 'high-school-math',
                'slug' => 'demo-featured-math-online',
                'title' => 'Gia sư Toán lớp 9 online',
                'description' => 'Ôn tập theo lộ trình, củng cố kiến thức và luyện đề bám sát mục tiêu của bạn.',
                'delivery' => 'online',
                'city' => null,
                'price' => 180000,
                'unit' => 'session',
                'availability' => [
                    ['weekday' => 0, 'start_time' => '12:00', 'end_time' => '14:00'],
                    ['weekday' => 0, 'start_time' => '18:00', 'end_time' => '20:00'],
                    ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '11:00'],
                    ['weekday' => 1, 'start_time' => '14:00', 'end_time' => '16:00'],
                    ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '20:00'],
                    ['weekday' => 2, 'start_time' => '15:00', 'end_time' => '18:00'],
                    ['weekday' => 5, 'start_time' => '09:00', 'end_time' => '12:00'],
                ],
                'reviews' => [
                    ['rating' => 5, 'comment' => 'Cô Mai giảng rất dễ hiểu, kiên nhẫn và luôn chuẩn bị bài kỹ. Sau vài buổi mình đã tự tin hơn nhiều!', 'reviewer' => 'main', 'weeks_ago' => 8],
                    ['rating' => 5, 'comment' => 'Con tiến bộ rõ rệt, cô theo sát phần kiến thức còn yếu và giải thích rất dễ nhớ.', 'reviewer' => 'second', 'weeks_ago' => 3],
                ],
            ],
            [
                'email' => 'demo.tutor.fitness@example.test',
                'name' => 'Trần Minh',
                'headline' => 'Huấn luyện viên cá nhân',
                'bio' => 'Xây dựng kế hoạch tập luyện và dinh dưỡng theo mục tiêu, thể trạng và kinh nghiệm của học viên.',
                'experience' => 8,
                'specialization' => 'strength-training',
                'slug' => 'demo-featured-strength-training',
                'title' => 'Huấn luyện cá nhân tăng cơ',
                'description' => 'Giáo án riêng theo thể trạng, theo sát kỹ thuật và tiến độ luyện tập.',
                'delivery' => 'offline',
                'city' => 'Hồ Chí Minh',
                'price' => 250000,
                'unit' => 'session',
                'availability' => [
                    ['weekday' => 0, 'start_time' => '06:00', 'end_time' => '08:00'],
                    ['weekday' => 0, 'start_time' => '17:30', 'end_time' => '20:00'],
                    ['weekday' => 2, 'start_time' => '06:00', 'end_time' => '08:00'],
                    ['weekday' => 2, 'start_time' => '17:30', 'end_time' => '20:00'],
                    ['weekday' => 4, 'start_time' => '06:00', 'end_time' => '08:00'],
                    ['weekday' => 4, 'start_time' => '17:30', 'end_time' => '20:00'],
                    ['weekday' => 5, 'start_time' => '08:00', 'end_time' => '12:00'],
                ],
                'reviews' => [
                    ['rating' => 5, 'comment' => 'Giáo án phù hợp với thể trạng, anh hướng dẫn kỹ thuật rất cẩn thận. Sau một thời gian mình thấy khỏe hơn.', 'reviewer' => 'main', 'weeks_ago' => 7],
                    ['rating' => 4, 'comment' => 'Buổi tập có lộ trình rõ ràng, được điều chỉnh theo tiến độ của mình. Rất đáng để thử!', 'reviewer' => 'second', 'weeks_ago' => 2],
                ],
            ],
            [
                'email' => 'demo.tutor.sports@example.test',
                'name' => 'Lê Thảo',
                'headline' => 'Huấn luyện viên cầu lông',
                'bio' => 'Hướng dẫn kỹ thuật cầu lông từ cơ bản, giúp người mới tự tin luyện tập và thi đấu.',
                'experience' => 5,
                'specialization' => 'badminton',
                'slug' => 'demo-featured-badminton-beginners',
                'title' => 'Cầu lông cho người mới bắt đầu',
                'description' => 'Nắm kỹ thuật nền tảng và tự tin hơn qua từng buổi thực hành cùng huấn luyện viên.',
                'delivery' => 'offline',
                'city' => 'Hồ Chí Minh',
                'price' => 220000,
                'unit' => 'session',
                'availability' => [
                    ['weekday' => 1, 'start_time' => '16:00', 'end_time' => '18:00'],
                    ['weekday' => 3, 'start_time' => '16:00', 'end_time' => '18:00'],
                    ['weekday' => 5, 'start_time' => '09:00', 'end_time' => '12:00'],
                    ['weekday' => 6, 'start_time' => '09:00', 'end_time' => '11:00'],
                ],
                'reviews' => [
                    ['rating' => 5, 'comment' => 'Chị hướng dẫn từng kỹ thuật cơ bản rất kiên nhẫn, buổi học vui mà dễ theo.', 'reviewer' => 'main', 'weeks_ago' => 6],
                    ['rating' => 5, 'comment' => 'Mình tự tin hơn khi ra sân sau vài buổi. Lịch học linh hoạt và chị góp ý rất cụ thể.', 'reviewer' => 'second', 'weeks_ago' => 1],
                ],
            ],
        ];

        DB::transaction(function () use ($tutors): void {
            $this->call(CatalogSeeder::class);
            $demoTutors = [];

            foreach ($tutors as $data) {
                $specializationId = DB::table('specializations')
                    ->where('slug', $data['specialization'])
                    ->where('status', 'active')
                    ->value('id');

                if (! $specializationId) {
                    throw new RuntimeException("Missing active specialization: {$data['specialization']}");
                }

                $user = User::query()->updateOrCreate(
                    ['email' => $data['email']],
                    [
                        'full_name' => $data['name'],
                        'password_hash' => Hash::make(self::DEMO_PASSWORD),
                        'role' => 'tutor',
                        'status' => 'active',
                        'email_verified_at' => now(),
                        'terms_version' => 'local-demo',
                    ],
                );

                $profile = TutorProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'headline' => $data['headline'],
                        'bio' => $data['bio'],
                        'experience_years' => $data['experience'],
                        'status' => 'approved',
                        'approval_status' => 'active',
                        'submitted_at' => now(),
                        'reviewed_at' => now(),
                        'qualification_claims' => [],
                        'suspended_at' => null,
                    ],
                );

                if (! $profile->public_slug) {
                    $profile->forceFill(['public_slug' => Str::slug($data['name']).'-'.$profile->id])->save();
                }

                DB::table('tutor_specializations')->updateOrInsert(
                    ['tutor_profile_id' => $profile->id, 'specialization_id' => $specializationId],
                    ['created_at' => now()],
                );

                $objectKey = 'local-demo/verification/'.Str::slug($data['email']).'.txt';
                $placeholder = "DEMO ONLY\nSynthetic verification placeholder for local UI demonstrations.\nNot a real identity document.\n";
                Storage::disk('local')->put($objectKey, $placeholder);

                $file = File::query()->updateOrCreate(
                    ['object_key' => $objectKey],
                    [
                        'owner_id' => $user->id,
                        'disk' => 'local',
                        'original_name' => 'DEMO ONLY - synthetic verification placeholder.txt',
                        'mime_type' => 'text/plain',
                        'size_bytes' => strlen($placeholder),
                        'purpose' => 'verification_document',
                        'visibility' => 'private',
                        'scan_status' => 'clean',
                    ],
                );

                VerificationDocument::query()->updateOrCreate(
                    ['tutor_profile_id' => $profile->id, 'file_id' => $file->id],
                    [
                        'document_type' => 'identity_card',
                        'status' => 'approved',
                        'reviewed_at' => now(),
                        'expires_at' => null,
                        'review_reason' => 'Local demo fixture only.',
                    ],
                );

                $listing = ServiceListing::query()->updateOrCreate(
                    ['slug' => $data['slug']],
                    [
                        'tutor_profile_id' => $profile->id,
                        'specialization_id' => $specializationId,
                        'title' => $data['title'],
                        'description' => $data['description'],
                        'delivery_mode' => $data['delivery'],
                        'city' => $data['city'],
                        'district' => null,
                        'price_from_minor' => $data['price'],
                        'currency' => 'VND',
                        'price_unit' => $data['unit'],
                        'status' => 'active',
                    ],
                );

                $demoTutors[$data['email']] = ['profile' => $profile, 'listing' => $listing];
                foreach ($data['availability'] as $rule) {
                    DB::table('availability_rules')->updateOrInsert(
                        [
                            'tutor_profile_id' => $profile->id,
                            'weekday' => $rule['weekday'],
                            'start_time' => $rule['start_time'],
                            'end_time' => $rule['end_time'],
                        ],
                        ['timezone' => 'Asia/Ho_Chi_Minh', 'created_at' => now(), 'updated_at' => now()],
                    );
                }
            }

            $customer = User::query()->updateOrCreate(
                ['email' => 'demo.customer@example.test'],
                [
                    'full_name' => 'Minh Khang · Học viên demo',
                    'password_hash' => Hash::make(self::DEMO_PASSWORD),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'terms_version' => 'local-demo',
                ],
            );

            DB::table('customer_profiles')->updateOrInsert(
                ['user_id' => $customer->id],
                ['city' => 'Hồ Chí Minh', 'bio' => 'Tài khoản học viên demo.', 'created_at' => now(), 'updated_at' => now()],
            );

            $secondCustomer = User::query()->updateOrCreate(
                ['email' => 'demo.customer.second@example.test'],
                [
                    'full_name' => 'Lê Minh · Học viên demo',
                    'password_hash' => Hash::make(self::DEMO_PASSWORD),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'terms_version' => 'local-demo',
                ],
            );
            DB::table('customer_profiles')->updateOrInsert(
                ['user_id' => $secondCustomer->id],
                ['city' => 'Hà Nội', 'bio' => 'Tài khoản học viên demo thứ hai.', 'created_at' => now(), 'updated_at' => now()],
            );

            foreach ($tutors as $data) {
                $profile = $demoTutors[$data['email']]['profile'];
                $listing = $demoTutors[$data['email']]['listing'];
                foreach ($data['reviews'] as $reviewIndex => $review) {
                    $reviewer = $review['reviewer'] === 'second' ? $secondCustomer : $customer;
                    $reviewDate = now()->subWeeks($review['weeks_ago']);
                    $title = 'DEMO ONLY - '.$data['name'].' review '.($reviewIndex + 1);
                    $program = Program::query()->updateOrCreate(
                        ['owner_tutor_profile_id' => $profile->id, 'title' => $title],
                        [
                            'customer_id' => $reviewer->id,
                            'source_listing_id' => $listing->id,
                            'version' => 1,
                            'goal' => 'Chương trình minh họa để hiển thị nhận xét demo.',
                            'level' => null,
                            'delivery_mode' => $data['delivery'],
                            'agreed_fee_minor' => $data['price'],
                            'currency' => 'VND',
                            'fee_unit' => $data['unit'],
                            'location_text' => $data['city'],
                            'schedule_note' => 'Dữ liệu demo local, không phải giao dịch thật.',
                            'timezone' => 'Asia/Ho_Chi_Minh',
                            'planned_session_count' => 4,
                            'status' => 'completed',
                            'activated_at' => $reviewDate->copy()->subWeeks(4),
                            'ends_at' => $reviewDate,
                        ],
                    );

                    foreach ([[$profile->user_id, 'tutor'], [$reviewer->id, 'customer']] as [$userId, $role]) {
                        DB::table('program_members')->updateOrInsert(
                            ['program_id' => $program->id, 'user_id' => $userId],
                            ['member_role' => $role, 'status' => 'active', 'joined_at' => $reviewDate->copy()->subWeeks(4), 'left_at' => null, 'created_at' => now(), 'updated_at' => now()],
                        );
                    }

                    DB::table('reviews')->updateOrInsert(
                        ['program_id' => $program->id, 'customer_id' => $reviewer->id],
                        [
                            'tutor_profile_id' => $profile->id,
                            'rating' => $review['rating'],
                            'comment' => $review['comment'],
                            'visibility' => 'visible',
                            'version' => 1,
                            'created_at' => $reviewDate,
                            'updated_at' => $reviewDate,
                        ],
                    );
                }
            }
        });

        $this->command?->info('Local featured demo data is ready: 3 public tutors, 3 active listings, 2 demo customers, weekly availability, and 6 visible demo reviews.');
        $this->command?->info('Demo login password for these local accounts: '.self::DEMO_PASSWORD);
    }
}
