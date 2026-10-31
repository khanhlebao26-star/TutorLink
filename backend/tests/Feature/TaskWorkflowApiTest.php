<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('completes task assignment, versioned submission, revision, and acceptance without losing history', function () {
    $sideEffectCounts = static fn () => [
        'audit_logs' => DB::table('audit_logs')->count(),
        'notification_outbox' => DB::table('notification_outbox')->count(),
        'notifications' => DB::table('notifications')->count(),
    ];
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $outsider = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $tutor, 'tutor');
    ProgramApiFixtures::addMember($program, $customer, 'customer');

    $this->actingAs($tutor, 'web');
    $task = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Bài luyện tập', 'assigned_to' => $customer->id,
    ])->assertCreated()->assertJsonPath('data.version', 1)->json('data.id');
    $this->getJson("/api/v1/programs/{$program}/tasks")->assertOk()->assertJsonCount(1, 'data');
    $this->patchJson("/api/v1/tasks/{$task}", [
        'expected_version' => 1, 'description' => 'Viết đoạn ngắn',
    ])->assertOk()->assertJsonPath('data.version', 2);

    $this->actingAs($outsider, 'web');
    $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => 'Sai người',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();

    $this->actingAs($customer, 'web');
    $this->postJson("/api/v1/tasks/{$task}/start", ['expected_version' => 1])
        ->assertStatus(409);
    $this->postJson("/api/v1/tasks/{$task}/start", ['expected_version' => 2])
        ->assertOk()->assertJsonPath('data.status', 'in_progress');

    $key = (string) Str::uuid();
    $sideEffectsBeforeSubmission = $sideEffectCounts();
    $first = $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => 'Bài lần 1',
    ], ['Idempotency-Key' => $key])->assertCreated()
        ->assertJsonPath('data.submission_version', 1)->json('data.id');
    $sideEffectsAfterSubmission = $sideEffectCounts();
    $this->assertSame($sideEffectsBeforeSubmission, $sideEffectsAfterSubmission);
    $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => 'Bài lần 1',
    ], ['Idempotency-Key' => $key])->assertOk()->assertJsonPath('data.id', $first);
    $this->assertSame($sideEffectsAfterSubmission, $sideEffectCounts());
    $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => 'Đổi nội dung',
    ], ['Idempotency-Key' => $key])->assertStatus(409);
    $this->assertDatabaseCount('task_submissions', 1);

    $this->actingAs($tutor, 'web');
    $revisionKey = (string) Str::uuid();
    $sideEffectsBeforeRevision = $sideEffectCounts();
    $this->postJson("/api/v1/tasks/{$task}/submission/request-revision", [
        'expected_submission_version' => 1, 'feedback' => 'Bổ sung ví dụ',
    ], ['Idempotency-Key' => $revisionKey])->assertOk()->assertJsonPath('data.status', 'revision_requested');
    $sideEffectsAfterRevision = $sideEffectCounts();
    $this->assertSame($sideEffectsBeforeRevision, $sideEffectsAfterRevision);

    $this->actingAs($customer, 'web');
    $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => 'Bản cũ',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
    $second = $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 1, 'content' => 'Bài lần 2 có ví dụ',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()
        ->assertJsonPath('data.submission_version', 2)->json('data.id');
    $this->getJson("/api/v1/tasks/{$task}/submission/versions")
        ->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.feedback', 'Bổ sung ví dụ');

    $this->actingAs($tutor, 'web');
    $sideEffectsBeforeRevisionReplay = $sideEffectCounts();
    $this->postJson("/api/v1/tasks/{$task}/submission/request-revision", [
        'expected_submission_version' => 1, 'feedback' => 'Bổ sung ví dụ',
    ], ['Idempotency-Key' => $revisionKey])->assertOk()
        ->assertJsonPath('data.submission_version', 1);
    $this->assertSame($sideEffectsBeforeRevisionReplay, $sideEffectCounts());
    $this->postJson("/api/v1/tasks/{$task}/submission/review", [
        'expected_submission_version' => 1, 'feedback' => 'Nhận xét nhầm bản',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
    $this->postJson("/api/v1/tasks/{$task}/submission/review", [
        'expected_submission_version' => 2, 'feedback' => 'Đạt',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.status', 'accepted');
    $this->getJson("/api/v1/tasks/{$task}")->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.current_submission_id', $second);
    $this->assertDatabaseHas('task_submissions', [
        'id' => $first, 'status' => 'revision_requested', 'feedback' => 'Bổ sung ví dụ',
    ]);
    $this->assertDatabaseHas('task_submissions', [
        'id' => $second, 'status' => 'accepted', 'feedback' => 'Đạt',
    ]);
});

it('preserves meaningful edge whitespace for submission and feedback HTTP idempotency', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');

    $this->actingAs($tutor, 'web');
    $task = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Giữ khoảng trắng', 'assigned_to' => $customer->id,
    ])->assertCreated()->json('data.id');

    $content = '  Preserve spaces  ';
    $submissionKey = (string) Str::uuid();
    $this->actingAs($customer, 'web');
    $submissionId = $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => $content,
    ], ['Idempotency-Key' => $submissionKey])->assertCreated()
        ->assertJsonPath('data.content', $content)->json('data.id');
    $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => $content,
    ], ['Idempotency-Key' => $submissionKey])->assertOk()
        ->assertJsonPath('data.id', $submissionId)->assertJsonPath('data.content', $content);
    $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => 'Preserve spaces',
    ], ['Idempotency-Key' => $submissionKey])->assertStatus(409);
    $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 1, 'content' => " \t\r\n ",
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable()
        ->assertJsonPath('code', 'empty_submission');
    $this->assertDatabaseHas('task_submissions', ['id' => $submissionId, 'content' => $content]);
    $this->assertDatabaseCount('task_submissions', 1);

    $this->actingAs($tutor, 'web');
    $feedbackKey = (string) Str::uuid();
    $this->postJson("/api/v1/tasks/{$task}/submission/request-revision", [
        'expected_submission_version' => 1, 'feedback' => '   ',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
    $feedback = '  Please revise  ';
    $this->postJson("/api/v1/tasks/{$task}/submission/request-revision", [
        'expected_submission_version' => 1, 'feedback' => $feedback,
    ], ['Idempotency-Key' => $feedbackKey])->assertOk()
        ->assertJsonPath('data.feedback', $feedback);
    $this->postJson("/api/v1/tasks/{$task}/submission/request-revision", [
        'expected_submission_version' => 1, 'feedback' => 'Please revise',
    ], ['Idempotency-Key' => $feedbackKey])->assertStatus(409);
    $this->postJson("/api/v1/tasks/{$task}/submission/request-revision", [
        'expected_submission_version' => 1, 'feedback' => $feedback,
    ], ['Idempotency-Key' => $feedbackKey])->assertOk()
        ->assertJsonPath('data.feedback', $feedback);
    $this->assertDatabaseHas('task_submissions', [
        'id' => $submissionId, 'content' => $content, 'feedback' => $feedback,
    ]);
    $this->assertDatabaseCount('idempotency_receipts', 1);
});

it('serializes Task and Submission timestamps as RFC 3339 UTC through HTTP', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');

    $this->actingAs($tutor, 'web');
    $task = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Timestamp Task', 'assigned_to' => $customer->id,
        'due_at' => '2026-11-02T09:30:00+07:00',
    ])->assertCreated()->assertJsonPath('data.due_at', '2026-11-02T02:30:00Z')
        ->json('data.id');
    $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Task without due date', 'assigned_to' => $customer->id,
    ])->assertCreated()->assertJsonPath('data.due_at', null);

    $this->actingAs($customer, 'web');
    $submission = $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => 'Bài nộp',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()
        ->assertJsonPath('data.reviewed_at', null);
    $submissionId = $submission->json('data.id');
    $this->assertMatchesRegularExpression(
        '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/',
        $submission->json('data.submitted_at'),
    );

    $this->actingAs($tutor, 'web');
    $feedback = $this->postJson("/api/v1/tasks/{$task}/submission/review", [
        'expected_submission_version' => 1,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    $this->assertMatchesRegularExpression(
        '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/',
        $feedback->json('data.reviewed_at'),
    );
    $this->getJson("/api/v1/tasks/{$task}/submission")->assertOk()
        ->assertJsonPath('data.reviewed_at', $feedback->json('data.reviewed_at'));
    $this->assertDatabaseHas('task_submissions', ['id' => $submissionId, 'status' => 'accepted']);
    $this->assertNotNull(DB::table('task_submissions')->where('id', $submissionId)->value('reviewed_at'));
});

it('stores the RFC 8785 JCS hash for special Unicode submission content', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    $this->actingAs($tutor, 'web');
    $task = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Unicode hash', 'assigned_to' => $customer->id,
    ])->assertCreated()->json('data.id');

    $content = "quote: \" slash: / backslash: \\ controls:\u{0001}\t\n separators:\u{2028}\u{2029} emoji: 😀 accents: é/e\u{0301}";
    $key = (string) Str::uuid();
    $this->actingAs($customer, 'web');
    $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => $content,
    ], ['Idempotency-Key' => $key])->assertCreated();

    $this->assertDatabaseHas('task_submissions', [
        'task_id' => $task,
        'idempotency_key' => $key,
        'request_hash' => 'c097b7615de86ef0fc6a10cf62a150db4182e96decceed82d22b262c0c71696a',
    ]);
});

it('allows a new submission while review is pending and rejects feedback for the older version', function () {
    $sideEffectCounts = static fn () => [
        'audit_logs' => DB::table('audit_logs')->count(),
        'notification_outbox' => DB::table('notification_outbox')->count(),
        'notifications' => DB::table('notifications')->count(),
    ];
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    $this->actingAs($tutor, 'web');
    $task = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Bài cập nhật', 'assigned_to' => $customer->id,
    ])->assertCreated()->json('data.id');

    $this->actingAs($customer, 'web');
    $first = $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => 'Bản đầu',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
    $second = $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 1, 'content' => 'Bản cập nhật',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()
        ->assertJsonPath('data.submission_version', 2)->json('data.id');
    $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 1, 'content' => 'Bản đến muộn',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);

    $this->actingAs($tutor, 'web');
    $this->postJson("/api/v1/tasks/{$task}/submission/review", [
        'expected_submission_version' => 1,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
    $reviewKey = (string) Str::uuid();
    $sideEffectsBeforeFeedback = $sideEffectCounts();
    $this->postJson("/api/v1/tasks/{$task}/submission/review", [
        'expected_submission_version' => 2,
    ], ['Idempotency-Key' => $reviewKey])->assertOk();
    $sideEffectsAfterFeedback = $sideEffectCounts();
    $this->assertSame($sideEffectsBeforeFeedback, $sideEffectsAfterFeedback);
    $this->postJson("/api/v1/tasks/{$task}/submission/review", [
        'expected_submission_version' => 2, 'feedback' => '   ',
    ], ['Idempotency-Key' => $reviewKey])->assertOk();
    $this->assertSame($sideEffectsAfterFeedback, $sideEffectCounts());
    $this->assertDatabaseHas('task_submissions', ['id' => $first, 'content' => 'Bản đầu', 'status' => 'submitted']);
    $this->assertDatabaseHas('task_submissions', ['id' => $second, 'content' => 'Bản cập nhật', 'status' => 'accepted']);
    $this->assertDatabaseHas('tasks', ['id' => $task, 'current_submission_id' => $second, 'status' => 'completed', 'version' => 4]);
});

it('allows task owners to cancel only open tasks and restricts transitions to the assigned member', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $otherTutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $outsider = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');

    $this->actingAs($tutor, 'web');
    $todo = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Hủy khi chưa bắt đầu', 'assigned_to' => $customer->id,
    ])->assertCreated()->json('data.id');
    $this->actingAs($outsider, 'web');
    $this->postJson("/api/v1/tasks/{$todo}/start", ['expected_version' => 1])->assertForbidden();
    $this->actingAs($otherTutor, 'web');
    $this->postJson("/api/v1/tasks/{$todo}/cancel", ['expected_version' => 1])->assertForbidden();
    $this->actingAs($tutor, 'web');
    $this->postJson("/api/v1/tasks/{$todo}/cancel", ['expected_version' => 1])
        ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.version', 2);
    $this->postJson("/api/v1/tasks/{$todo}/cancel", ['expected_version' => 1])->assertStatus(409);

    $inProgress = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Hủy sau khi bắt đầu', 'assigned_to' => $customer->id,
    ])->assertCreated()->json('data.id');
    $this->actingAs($customer, 'web');
    $this->postJson("/api/v1/tasks/{$inProgress}/start", ['expected_version' => 1])
        ->assertOk()->assertJsonPath('data.status', 'in_progress');
    $this->actingAs($tutor, 'web');
    $this->postJson("/api/v1/tasks/{$inProgress}/cancel", ['expected_version' => 2])
        ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.version', 3);
});

it('enforces task visibility and mutation permissions by owner and active assigned membership', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $otherTutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $member = TestActors::customer();
    $outsider = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    $otherProgram = ProgramApiFixtures::draft($tutor, $member, ['status' => 'active']);
    ProgramApiFixtures::addMember($otherProgram, $member, 'customer');

    $this->actingAs($tutor, 'web');
    $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Không giao cho member Program khác', 'assigned_to' => $member->id,
    ])->assertUnprocessable();
    $task = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Task có assignee', 'assigned_to' => $customer->id,
    ])->assertCreated()->json('data.id');
    $otherTask = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Task thứ hai', 'assigned_to' => $customer->id,
    ])->assertCreated()->json('data.id');

    $this->actingAs($otherTutor, 'web');
    $this->patchJson("/api/v1/tasks/{$task}", ['expected_version' => 1, 'title' => 'Không có quyền'])
        ->assertForbidden();

    foreach ([$member, $outsider] as $unauthorizedCustomer) {
        $this->actingAs($unauthorizedCustomer, 'web');
        $this->getJson("/api/v1/tasks/{$task}")->assertNotFound();
        $this->postJson("/api/v1/tasks/{$task}/start", ['expected_version' => 1])->assertForbidden();
    }

    $this->actingAs($customer, 'web');
    $this->getJson("/api/v1/tasks/{$task}")->assertOk();
    $this->postJson("/api/v1/tasks/{$task}/start", ['expected_version' => 1])
        ->assertOk()->assertJsonPath('data.status', 'in_progress');

    DB::table('program_members')->where('program_id', $program)->where('user_id', $customer->id)
        ->update(['status' => 'cancelled']);
    $this->postJson("/api/v1/tasks/{$otherTask}/start", ['expected_version' => 1])->assertForbidden();
});

it('applies Program state gates to task creation and actions', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');

    $this->actingAs($tutor, 'web');
    $tasks = [];
    foreach (['edit' => 'Task sửa', 'start' => 'Task bắt đầu', 'submit' => 'Task nộp', 'cancel' => 'Task hủy'] as $key => $title) {
        $tasks[$key] = $this->postJson("/api/v1/programs/{$program}/tasks", [
            'title' => $title, 'assigned_to' => $customer->id,
        ])->assertCreated()->json('data.id');
    }
    $this->patchJson("/api/v1/tasks/{$tasks['edit']}", ['expected_version' => 1, 'title' => 'Đổi khi paused'])
        ->assertOk();

    DB::table('programs')->where('id', $program)->update(['status' => 'paused']);
    $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Không tạo khi paused', 'assigned_to' => $customer->id,
    ])->assertStatus(409);
    $this->patchJson("/api/v1/tasks/{$tasks['edit']}", ['expected_version' => 2, 'title' => 'Không sửa khi paused'])
        ->assertStatus(409);
    $this->actingAs($customer, 'web');
    $this->postJson("/api/v1/tasks/{$tasks['start']}/start", ['expected_version' => 1])
        ->assertOk()->assertJsonPath('data.status', 'in_progress');
    $this->putJson("/api/v1/tasks/{$tasks['submit']}/submission", [
        'expected_submission_version' => 0, 'content' => 'Nộp khi paused',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    $this->actingAs($tutor, 'web');
    $this->postJson("/api/v1/tasks/{$tasks['cancel']}/cancel", ['expected_version' => 1])
        ->assertOk()->assertJsonPath('data.status', 'cancelled');

    foreach (['completed', 'cancelled'] as $programStatus) {
        $terminalProgram = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
        ProgramApiFixtures::addMember($terminalProgram, $customer, 'customer');
        $task = $this->postJson("/api/v1/programs/{$terminalProgram}/tasks", [
            'title' => "Task trước {$programStatus}", 'assigned_to' => $customer->id,
        ])->assertCreated()->json('data.id');
        DB::table('programs')->where('id', $terminalProgram)->update(['status' => $programStatus]);

        $this->postJson("/api/v1/programs/{$terminalProgram}/tasks", [
            'title' => 'Không tạo trong Program terminal', 'assigned_to' => $customer->id,
        ])->assertStatus(409);
        $this->patchJson("/api/v1/tasks/{$task}", ['expected_version' => 1, 'title' => 'Không sửa'])
            ->assertStatus(409);
        $this->postJson("/api/v1/tasks/{$task}/cancel", ['expected_version' => 1])->assertStatus(409);
        $this->actingAs($customer, 'web');
        $this->postJson("/api/v1/tasks/{$task}/start", ['expected_version' => 1])->assertStatus(409);
        $this->putJson("/api/v1/tasks/{$task}/submission", [
            'expected_submission_version' => 0, 'content' => 'Không nộp',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
        $this->actingAs($tutor, 'web');
    }
});

it('rejects task edits and submissions in terminal task states while allowing valid transitions', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');

    $this->actingAs($tutor, 'web');
    $tasks = [];
    foreach (['in_progress', 'submitted', 'completed', 'cancelled'] as $status) {
        $tasks[$status] = $this->postJson("/api/v1/programs/{$program}/tasks", [
            'title' => "Task {$status}", 'assigned_to' => $customer->id,
        ])->assertCreated()->json('data.id');
    }

    $this->actingAs($customer, 'web');
    $this->postJson("/api/v1/tasks/{$tasks['in_progress']}/start", ['expected_version' => 1])->assertOk();
    $this->actingAs($tutor, 'web');
    $this->patchJson("/api/v1/tasks/{$tasks['in_progress']}", ['expected_version' => 2, 'title' => 'Không sửa khi đang làm'])
        ->assertStatus(409);
    $this->actingAs($customer, 'web');
    $submit = function (int $task, string $content, int $version = 0) {
        return $this->putJson("/api/v1/tasks/{$task}/submission", [
            'expected_submission_version' => $version, 'content' => $content,
        ], ['Idempotency-Key' => (string) Str::uuid()]);
    };
    $submit($tasks['in_progress'], 'Được nộp khi in_progress')->assertCreated();
    $submit($tasks['submitted'], 'Được nộp lần đầu')->assertCreated();
    $submit($tasks['completed'], 'Bài được chấp nhận')->assertCreated();

    $this->actingAs($tutor, 'web');
    $this->postJson("/api/v1/tasks/{$tasks['completed']}/submission/review", [
        'expected_submission_version' => 1,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    $this->postJson("/api/v1/tasks/{$tasks['cancelled']}/cancel", ['expected_version' => 1])->assertOk();

    foreach ($tasks as $status => $task) {
        $expectedVersion = in_array($status, ['in_progress', 'completed'], true) ? 3 : 2;
        $this->patchJson("/api/v1/tasks/{$task}", ['expected_version' => $expectedVersion, 'title' => 'Không sửa'])
            ->assertStatus(409);
    }
    $this->postJson("/api/v1/tasks/{$tasks['submitted']}/cancel", ['expected_version' => 2])->assertStatus(409);
    $this->postJson("/api/v1/tasks/{$tasks['completed']}/cancel", ['expected_version' => 3])->assertStatus(409);
    $this->postJson("/api/v1/tasks/{$tasks['cancelled']}/cancel", ['expected_version' => 2])->assertStatus(409);

    $this->actingAs($customer, 'web');
    foreach ($tasks as $status => $task) {
        $expectedVersion = in_array($status, ['in_progress', 'completed'], true) ? 3 : 2;
        $this->postJson("/api/v1/tasks/{$task}/start", ['expected_version' => $expectedVersion])->assertStatus(409);
    }
    $submit($tasks['submitted'], 'Bản cập nhật khi chờ feedback', 1)->assertCreated()
        ->assertJsonPath('data.submission_version', 2);
    foreach (['completed', 'cancelled'] as $status) {
        $submit($tasks[$status], 'Không thể nộp lại', $status === 'completed' ? 1 : 0)->assertStatus(409);
    }
});

it('replays feedback from the shared receipt after authorization without repeating the mutation', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    $this->actingAs($tutor, 'web');
    $task = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Bài feedback', 'assigned_to' => $customer->id,
    ])->assertCreated()->json('data.id');
    $this->actingAs($customer, 'web');
    $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => 'Bài làm',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->actingAs($tutor, 'web');
    $path = "/api/v1/tasks/{$task}/submission/review";
    $payload = ['expected_submission_version' => 1, 'feedback' => "Đạt\r\nTốt"];
    $key = (string) Str::uuid();
    $this->postJson($path, $payload)->assertStatus(422);
    $this->postJson($path, $payload, ['Idempotency-Key' => 'not-a-uuid'])->assertStatus(422);
    $this->postJson($path, [...$payload, 'unexpected' => true], ['Idempotency-Key' => $key])->assertStatus(422);
    $this->assertDatabaseCount('idempotency_receipts', 0);

    if (DB::getDriverName() === 'sqlite') {
        DB::statement("CREATE TRIGGER reject_feedback_receipt BEFORE INSERT ON idempotency_receipts BEGIN SELECT RAISE(ABORT, 'simulated receipt failure'); END");
        $this->postJson($path, $payload, ['Idempotency-Key' => $key])->assertStatus(500);
        $this->assertDatabaseHas('tasks', ['id' => $task, 'status' => 'submitted', 'version' => 2]);
        $this->assertDatabaseHas('task_submissions', ['task_id' => $task, 'status' => 'submitted', 'feedback' => null]);
        $this->assertDatabaseCount('idempotency_receipts', 0);
        DB::statement('DROP TRIGGER reject_feedback_receipt');
    } elseif (DB::getDriverName() === 'pgsql') {
        DB::unprepared("CREATE FUNCTION reject_feedback_receipt() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'simulated receipt failure'; END; $$");
        DB::unprepared('CREATE TRIGGER reject_feedback_receipt BEFORE INSERT ON idempotency_receipts FOR EACH ROW EXECUTE FUNCTION reject_feedback_receipt()');
        try {
            $this->postJson($path, $payload, ['Idempotency-Key' => $key])->assertStatus(500);
            $this->assertDatabaseHas('tasks', ['id' => $task, 'status' => 'submitted', 'version' => 2]);
            $this->assertDatabaseHas('task_submissions', ['task_id' => $task, 'status' => 'submitted', 'feedback' => null]);
            $this->assertDatabaseCount('idempotency_receipts', 0);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS reject_feedback_receipt ON idempotency_receipts');
            DB::statement('DROP FUNCTION IF EXISTS reject_feedback_receipt()');
        }
    }

    $first = $this->postJson($path, $payload, ['Idempotency-Key' => $key])
        ->assertOk()->assertJsonPath('data.feedback', "Đạt\nTốt")
        ->assertJsonPath('data.version', 3)->json();
    $receipt = DB::table('idempotency_receipts')->where('actor_id', $tutor->id)->where('idempotency_key', $key)->first();
    expect($receipt->canonical_path)->toBe($path);
    expect($receipt->response_status)->toBe(200);
    $snapshot = json_decode($receipt->response_snapshot, true);
    expect($snapshot['data'])->toMatchArray($first['data']);
    expect(CarbonImmutable::parse($receipt->expires_at)->equalTo(
        CarbonImmutable::parse($receipt->created_at)->addDays(30),
    ))->toBeTrue();
    $this->postJson($path, ['expected_submission_version' => 1, 'feedback' => "Đạt\nTốt"],
        ['Idempotency-Key' => $key])->assertOk()->assertExactJson($first);
    $this->postJson($path, ['expected_submission_version' => 1, 'feedback' => 'Khác'],
        ['Idempotency-Key' => $key])->assertStatus(409);
    $this->postJson("/api/v1/tasks/{$task}/submission/request-revision", $payload,
        ['Idempotency-Key' => $key])->assertStatus(409);
    $this->postJson($path, $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
    $this->assertDatabaseHas('tasks', ['id' => $task, 'status' => 'completed', 'version' => 3]);
    $this->assertDatabaseCount('idempotency_receipts', 1);

    DB::table('users')->where('id', $tutor->id)->update(['status' => 'suspended']);
    $this->postJson($path, $payload, ['Idempotency-Key' => $key])->assertForbidden();
    $this->assertDatabaseCount('idempotency_receipts', 1);
});

it('links a completed task file and permits only the task participants to download it', function () {
    Storage::fake('local');
    $tutor = ProgramApiFixtures::eligibleTutor();
    $otherTutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $outsider = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    Storage::disk('local')->put('tests/task-instructions.pdf', '%PDF-1.4 task instructions');
    $taskFile = DB::table('files')->insertGetId([
        'owner_id' => $tutor->id, 'disk' => 'local', 'object_key' => 'tests/task-instructions.pdf',
        'original_name' => 'task-instructions.pdf', 'mime_type' => 'application/pdf',
        'size_bytes' => 27, 'visibility' => 'private', 'scan_status' => 'clean',
        'purpose' => 'task_attachment', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->actingAs($tutor, 'web');
    $task = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Bài có tệp', 'assigned_to' => $customer->id, 'file_ids' => [$taskFile],
    ])->assertCreated()->json('data.id');
    $this->assertDatabaseHas('file_links', [
        'file_id' => $taskFile, 'resource_type' => 'task', 'resource_id' => $task, 'purpose' => 'task_attachment',
    ]);
    $this->actingAs($tutor, 'web');
    $this->get("/api/v1/files/{$taskFile}/download")->assertOk();
    $this->actingAs($customer, 'web');
    $this->get("/api/v1/files/{$taskFile}/download")->assertOk();
    DB::table('file_links')->where('file_id', $taskFile)->update(['purpose' => 'verification_document']);
    $this->get("/api/v1/files/{$taskFile}/download")->assertForbidden();
    DB::table('file_links')->where('file_id', $taskFile)->update(['purpose' => 'task_attachment']);
    $this->actingAs($outsider, 'web');
    $this->get("/api/v1/files/{$taskFile}/download")->assertForbidden();
    $this->actingAs($otherTutor, 'web');
    $this->get("/api/v1/files/{$taskFile}/download")->assertForbidden();

    DB::table('program_members')->where('program_id', $program)
        ->where('user_id', $customer->id)->update(['status' => 'cancelled']);
    $this->actingAs($customer, 'web');
    $this->get("/api/v1/files/{$taskFile}/download")->assertForbidden();
    DB::table('program_members')->where('program_id', $program)
        ->where('user_id', $customer->id)->update(['status' => 'active']);

    Storage::disk('local')->put('tests/answer.pdf', '%PDF-1.4 test');
    $file = DB::table('files')->insertGetId([
        'owner_id' => $customer->id, 'disk' => 'local', 'object_key' => 'tests/answer.pdf',
        'original_name' => 'answer.pdf', 'mime_type' => 'application/pdf',
        'size_bytes' => 13, 'visibility' => 'private', 'scan_status' => 'clean',
        'purpose' => 'task_attachment', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->actingAs($customer, 'web');
    $submission = $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'file_ids' => [$file],
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()
        ->assertJsonPath('data.file_ids.0', $file)->json('data.id');
    $this->assertDatabaseHas('file_links', [
        'file_id' => $file, 'resource_type' => 'task_submission', 'resource_id' => $submission,
    ]);
    $this->actingAs($tutor, 'web');
    $this->get("/api/v1/files/{$file}/download")->assertOk();
    $this->actingAs($otherTutor, 'web');
    $this->get("/api/v1/files/{$file}/download")->assertForbidden();
    $this->actingAs($customer, 'web');
    $this->get("/api/v1/files/{$file}/download")->assertOk();
    $this->actingAs($outsider, 'web');
    $this->get("/api/v1/files/{$file}/download")->assertForbidden();
});

it('rolls back submission and task state when a file link cannot be written', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    $this->actingAs($tutor, 'web');
    $task = $this->postJson("/api/v1/programs/{$program}/tasks", [
        'title' => 'Bài có tệp', 'assigned_to' => $customer->id,
    ])->assertCreated()->json('data.id');
    $file = DB::table('files')->insertGetId([
        'owner_id' => $customer->id, 'disk' => 'local', 'object_key' => 'tests/task.pdf',
        'original_name' => 'task.pdf', 'mime_type' => 'application/pdf',
        'size_bytes' => 1024, 'visibility' => 'private', 'scan_status' => 'clean',
        'purpose' => 'task_attachment', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('files')->where('id', $file)->update(['purpose' => 'profile_document']);
    $this->actingAs($customer, 'web');
    $this->putJson("/api/v1/tasks/{$task}/submission", [
        'expected_submission_version' => 0, 'content' => 'Bài', 'file_ids' => [$file],
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
    $this->assertDatabaseCount('task_submissions', 0);
    $this->assertDatabaseCount('file_links', 0);
    $this->assertDatabaseHas('tasks', [
        'id' => $task, 'status' => 'todo', 'version' => 1, 'current_submission_id' => null,
    ]);

    if (DB::getDriverName() === 'sqlite') {
        DB::table('files')->where('id', $file)->update(['purpose' => 'task_attachment']);
        DB::statement("CREATE TRIGGER reject_task_file_link BEFORE INSERT ON file_links BEGIN SELECT RAISE(ABORT, 'simulated file link failure'); END");
        $this->putJson("/api/v1/tasks/{$task}/submission", [
            'expected_submission_version' => 0, 'content' => 'Bài', 'file_ids' => [$file],
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(500);
        $this->assertDatabaseCount('task_submissions', 0);
        $this->assertDatabaseCount('file_links', 0);
        $this->assertDatabaseHas('tasks', [
            'id' => $task, 'status' => 'todo', 'version' => 1, 'current_submission_id' => null,
        ]);
    } elseif (DB::getDriverName() === 'pgsql') {
        DB::table('files')->where('id', $file)->update(['purpose' => 'task_attachment']);
        DB::unprepared("CREATE FUNCTION reject_task_submission_file_link() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.resource_type = 'task_submission' THEN RAISE EXCEPTION 'simulated file_links failure'; END IF; RETURN NEW; END; $$");
        DB::unprepared('CREATE TRIGGER reject_task_submission_file_link BEFORE INSERT ON file_links FOR EACH ROW EXECUTE FUNCTION reject_task_submission_file_link()');
        try {
            $this->putJson("/api/v1/tasks/{$task}/submission", [
                'expected_submission_version' => 0, 'content' => 'Bài', 'file_ids' => [$file],
            ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(500);
            $this->assertDatabaseCount('task_submissions', 0);
            $this->assertDatabaseMissing('file_links', ['file_id' => $file, 'resource_type' => 'task_submission']);
            $this->assertDatabaseHas('tasks', [
                'id' => $task, 'status' => 'todo', 'version' => 1, 'current_submission_id' => null,
            ]);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS reject_task_submission_file_link ON file_links');
            DB::statement('DROP FUNCTION IF EXISTS reject_task_submission_file_link()');
        }
    }
});

it('backfills a legacy submission into version one and the task current pointer', function () {
    $original = DB::getDefaultConnection();
    config()->set('database.connections.issue74_backfill', [
        ...config('database.connections.sqlite'), 'database' => ':memory:',
    ]);
    DB::setDefaultConnection('issue74_backfill');
    try {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assigned_to')->nullable();
        });
        Schema::create('task_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('customer_id');
            $table->unique(['task_id', 'customer_id']);
        });
        DB::table('tasks')->insert(['id' => 7, 'assigned_to' => null]);
        DB::table('task_submissions')->insert(['id' => 9, 'task_id' => 7, 'customer_id' => 11]);

        $migration = require database_path('migrations/2026_10_22_073016_add_submission_versioning_and_idempotency.php');
        $migration->up();

        expect(DB::table('tasks')->where('id', 7)->first())
            ->assigned_to->toBe(11)
            ->current_submission_id->toBe(9);
        $submission = DB::table('task_submissions')->where('id', 9)->first();
        expect($submission->submission_version)->toBe(1);
        expect(Str::isUuid($submission->idempotency_key))->toBeTrue();
        expect(strlen($submission->request_hash))->toBe(64);
    } finally {
        DB::setDefaultConnection($original);
        DB::purge('issue74_backfill');
    }
});
