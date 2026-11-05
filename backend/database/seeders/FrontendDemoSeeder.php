<?php

namespace Database\Seeders;

use App\Actions\Invitations\SendInvitation;
use App\Models\ServiceListing;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MessageSendFixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\Support\TestDatabaseGuard;

class FrontendDemoSeeder extends Seeder
{
    public function run(): void
    {
        TestDatabaseGuard::assertSafe(config());
        TestDatabaseGuard::assertConnectedIdentity(config());
        if (User::where('email', 'fe-customer@example.test')->exists()) {
            return;
        }
        $this->call(CatalogSeeder::class);
        DB::transaction(function (): void {
            $tutor = ProgramApiFixtures::eligibleTutor();
            $customer = TestActors::customer();
            $admin = TestActors::admin();
            foreach (['tutor' => $tutor, 'customer' => $customer, 'admin' => $admin] as $role => $actor) {
                $actor->forceFill(['email' => "fe-{$role}@example.test", 'full_name' => ['tutor' => 'Mai Anh · Gia sư demo', 'customer' => 'Minh Khang · Học viên demo', 'admin' => 'Hoàng · Admin demo'][$role], 'password_hash' => Hash::make('Demo-pass-2026')])->save();
            }
            $specialization = DB::table('specializations')->where('status', 'active')->value('id');
            $tutor->tutorProfile->specializations()->sync([$specialization]);
            $listing = ServiceListing::create(['tutor_profile_id' => $tutor->tutorProfile->id, 'specialization_id' => $specialization, 'slug' => 'fe-demo-english', 'title' => 'Tiếng Anh giao tiếp cho người đi làm', 'description' => 'Luyện giao tiếp theo mục tiêu cá nhân cùng gia sư.', 'status' => 'active', 'delivery_mode' => 'online', 'price_from_minor' => 300000, 'currency' => 'VND', 'price_unit' => 'session']);
            $active = ProgramApiFixtures::draft($tutor, $customer, ['title' => 'English for Work · Giao tiếp tự tin', 'status' => 'active', 'source_listing_id' => $listing->id]);
            ProgramApiFixtures::addMember($active, $tutor, 'tutor');
            ProgramApiFixtures::addMember($active, $customer, 'customer');
            $draft = ProgramApiFixtures::draft($tutor, $customer, ['title' => 'English for Interviews · Luyện phỏng vấn', 'source_listing_id' => $listing->id]);
            app(SendInvitation::class)->execute($tutor, $draft, ['expected_version' => 1], (string) Str::uuid(), (string) Str::uuid());
            $chat = MessageSendFixtures::conversation($customer, $tutor);
            DB::table('conversations')->where('id', $chat)->update(['context_listing_id' => $listing->id]);
            $message = MessageSendFixtures::seed($chat, $tutor, ['body' => 'Chào bạn! Mình có thể giúp bạn xây dựng lộ trình giao tiếp phù hợp với công việc.']);
            MessageSendFixtures::seed($chat, $customer, ['body' => 'Mình muốn luyện nói và chuẩn bị cho buổi phỏng vấn.']);
            $pending = TestActors::tutor();
            $pending->forceFill(['full_name' => 'Hồ sơ demo cần xét duyệt'])->save();
            $pending->tutorProfile->forceFill(['headline' => 'Gia sư tiếng Anh giao tiếp', 'status' => 'submitted', 'submitted_at' => now(), 'assigned_admin_id' => $admin->id, 'assignment_version' => 1])->save();
            DB::table('reports')->insert(['reporter_id' => $customer->id, 'resource_type' => 'message', 'resource_id' => $message, 'reason' => 'other', 'details' => 'Báo cáo minh họa để kiểm tra quyền evidence.', 'status' => 'open', 'assigned_admin_id' => $admin->id, 'assignment_version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $completed = ProgramApiFixtures::draft($tutor, $customer, ['title' => 'Chương trình đã hoàn thành', 'status' => 'completed']);
            ProgramApiFixtures::addMember($completed, $tutor, 'tutor');
            ProgramApiFixtures::addMember($completed, $customer, 'customer');
            DB::table('reviews')->insert(['program_id' => $completed, 'customer_id' => $customer->id, 'tutor_profile_id' => $tutor->tutorProfile->id, 'rating' => 5, 'comment' => 'Gia sư tận tâm, lộ trình rõ ràng.', 'visibility' => 'visible', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('files')->where('owner_id', $tutor->id)->get() as $file) {
                Storage::disk($file->disk)->put($file->object_key, '%PDF-1.4 demo fixture');
            }
        });
    }
}
