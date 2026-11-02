<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TestActors;
use Tests\TestCase;

class BeD04FeeRecordApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_fee_full_lifecycle_keeps_metadata_and_emits_audit_outbox_receipts(): void
    {
        $program = $this->program('D04 Fixture Active');
        $this->clearProgramFees($program);
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $member = $this->user('be-d04-late-member@tutorlink.test');
        $collectionPath = '/api/v1/programs/'.$program->id.'/fee-records';
        $createKey = (string) Str::uuid();

        $created = $this->request($owner, 'POST', $collectionPath, [
            'period' => '2026-11',
            'amount_minor' => 1200000,
        ], $createKey, 'D04-11-create-default-VND');
        $created->assertCreated()
            ->assertJsonPath('data.period', '2026-11')
            ->assertJsonPath('data.period_start', '2026-11-01')
            ->assertJsonPath('data.period_end', '2026-11-30')
            ->assertJsonPath('data.amount_minor', 1200000)
            ->assertJsonPath('data.currency', 'VND')
            ->assertJsonPath('data.status', 'declared')
            ->assertJsonPath('data.tutor_payment_mark', 'unmarked')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.disclaimer', 'Do Tutor khai báo, không phải bằng chứng thanh toán.');
        $this->assertSame([
            'id', 'program_id', 'period', 'period_start', 'period_end', 'amount_minor', 'currency', 'status',
            'tutor_payment_mark', 'version', 'payment_marked_at', 'payment_mark_reason', 'voided_at', 'void_reason',
            'last_change_reason', 'disclaimer', 'created_at', 'updated_at',
        ], array_keys($created->json('data')));
        $feeId = (int) $created->json('data.id');
        $itemPath = '/api/v1/fee-records/'.$feeId;

        $updated = $this->request($owner, 'PATCH', $itemPath, [
            'expected_version' => 1,
            'amount_minor' => 1000000,
            'reason' => 'Điều chỉnh theo thỏa thuận',
        ], (string) Str::uuid(), 'D04-11-PATCH-amount');
        $updated->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.last_change_reason', 'Điều chỉnh theo thỏa thuận');

        $paid = $this->request($owner, 'POST', $itemPath.'/mark-paid', [
            'expected_version' => 2,
            'reason' => null,
        ], (string) Str::uuid(), 'D04-11-mark-paid');
        $paid->assertOk()->assertJsonPath('data.version', 3)->assertJsonPath('data.tutor_payment_mark', 'marked_paid')
            ->assertJsonPath('data.payment_mark_reason', null)->assertJsonPath('data.last_change_reason', null);
        $paidAt = $paid->json('data.payment_marked_at');
        $paidUpdatedAt = $paid->json('data.updated_at');

        $editPaid = $this->request($owner, 'PATCH', $itemPath, [
            'expected_version' => 3,
            'amount_minor' => 1000000,
            'reason' => 'Even unchanged amount is blocked while marked paid',
        ], (string) Str::uuid(), 'D04-11-edit-marked-paid-blocked');
        $editPaid->assertConflict()->assertJsonPath('code', 'fee_record_state_conflict');

        $auditCount = DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count();
        $outboxCount = DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count();
        $noOpPaidKey = (string) Str::uuid();
        $noOpPaid = $this->request($owner, 'POST', $itemPath.'/mark-paid', [
            'expected_version' => 3,
        ], $noOpPaidKey, 'D04-11-mark-paid-noop');
        $noOpPaid->assertOk()->assertJsonPath('data.version', 3)->assertJsonPath('data.payment_marked_at', $paidAt)
            ->assertJsonPath('data.updated_at', $paidUpdatedAt);
        $this->assertSame($auditCount, DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
        $this->assertSame($outboxCount, DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
        $receiptCountAfterNoop = DB::table('idempotency_receipts')->where('actor_id', $owner->id)->count();
        $this->assertSame(4, $receiptCountAfterNoop, 'Create, amount edit, mark-paid and new no-op key each save one receipt.');
        $noOpReplay = $this->request($owner, 'POST', $itemPath.'/mark-paid', [
            'expected_version' => 3,
        ], $noOpPaidKey, 'D04-11-mark-paid-noop-replay');
        $this->assertEquals($noOpPaid->json(), $noOpReplay->json());
        $this->assertSame(4, DB::table('idempotency_receipts')->where('actor_id', $owner->id)->count());

        $missingPaidToUnpaidReason = $this->request($owner, 'POST', $itemPath.'/mark-unpaid', [
            'expected_version' => 3,
        ], (string) Str::uuid(), 'D04-11-mark-unpaid-reason-required');
        $missingPaidToUnpaidReason->assertUnprocessable()->assertJsonValidationErrors('reason');

        $unpaid = $this->request($owner, 'POST', $itemPath.'/mark-unpaid', [
            'expected_version' => 3,
            'reason' => 'Tutor điều chỉnh lại dấu thu',
        ], (string) Str::uuid(), 'D04-11-mark-unpaid');
        $unpaid->assertOk()->assertJsonPath('data.version', 4)->assertJsonPath('data.tutor_payment_mark', 'marked_unpaid')
            ->assertJsonPath('data.payment_mark_reason', 'Tutor điều chỉnh lại dấu thu');
        $unpaidAt = $unpaid->json('data.payment_marked_at');

        $void = $this->request($owner, 'POST', $itemPath.'/void', [
            'expected_version' => 4,
            'reason' => 'Khai báo nhầm kỳ thỏa thuận',
        ], (string) Str::uuid(), 'D04-11-void');
        $void->assertOk()->assertJsonPath('data.version', 5)->assertJsonPath('data.status', 'voided')
            ->assertJsonPath('data.tutor_payment_mark', 'marked_unpaid')
            ->assertJsonPath('data.payment_marked_at', $unpaidAt)
            ->assertJsonPath('data.payment_mark_reason', 'Tutor điều chỉnh lại dấu thu')
            ->assertJsonPath('data.period', '2026-11')
            ->assertJsonPath('data.void_reason', 'Khai báo nhầm kỳ thỏa thuận')
            ->assertJsonPath('data.last_change_reason', 'Khai báo nhầm kỳ thỏa thuận');

        $this->assertSame(5, DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
        $auditActions = DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->orderBy('id')->pluck('action')->all();
        $this->assertSame(['fee_record.create', 'fee_record.update', 'fee_record.mark', 'fee_record.mark', 'fee_record.void'], $auditActions);
        $events = DB::table('notification_outbox')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->orderBy('event_key')->get();
        $this->assertCount(5, $events);
        $this->assertSame(6, DB::table('idempotency_receipts')->where('actor_id', $owner->id)->count());
        $this->assertSame([
            "fee_record:{$feeId}:v1",
            "fee_record:{$feeId}:v2",
            "fee_record:{$feeId}:v3",
            "fee_record:{$feeId}:v4",
            "fee_record:{$feeId}:v5",
        ], $events->pluck('event_key')->all());
        $feeReceipts = DB::table('idempotency_receipts')->where('actor_id', $owner->id)->where('canonical_path', 'like', '%/fee-records%')->get();
        $this->assertCount(6, $feeReceipts);
        $this->assertSame(0, $feeReceipts->whereNotNull('expires_at')->count(), 'Fee receipts have no time-based expiry.');
        foreach ($events as $event) {
            $this->assertSame($member->id, (int) $event->user_id);
            $this->assertSame('fee_record_changed', $event->type);
            $this->assertEqualsCanonicalizing(['deep_link', 'period'], array_keys(json_decode($event->payload, true)));
            $this->assertStringContainsString('period', $event->payload);
            $this->assertStringNotContainsString('amount_minor', $event->payload);
            $this->assertStringNotContainsString('reason', $event->payload);
        }

        $customerDetail = $this->actingAs($member)->getJson($itemPath);
        $customerDetail->assertOk()->assertJsonPath('data.disclaimer', 'Do Tutor khai báo, không phải bằng chứng thanh toán.')
            ->assertJsonPath('data.void_reason', 'Khai báo nhầm kỳ thỏa thuận');
        $this->assertArrayNotHasKey('voided_by', $customerDetail->json('data'));
        $this->assertArrayNotHasKey('payment_marked_by', $customerDetail->json('data'));

        $duplicatePeriod = $this->request($owner, 'POST', $collectionPath, [
            'period' => '2026-11', 'amount_minor' => 300,
        ], (string) Str::uuid(), 'D04-11-duplicate-voided-month');
        $duplicatePeriod->assertConflict()->assertJsonPath('code', 'fee_period_exists');
        $replay = $this->request($owner, 'POST', $collectionPath, [
            'period' => '2026-11', 'amount_minor' => 1200000,
        ], strtoupper($createKey), 'D04-11-create-replay-after-void');
        $replay->assertCreated();
        $this->assertSame(1, (int) $replay->json('data.version'));
        $this->assertSame('declared', $replay->json('data.status'));
        $this->assertEquals($created->json(), $replay->json());

        $changedPayload = $this->request($owner, 'POST', $collectionPath, [
            'period' => '2026-11', 'amount_minor' => 1200001,
        ], $createKey, 'D04-11-create-key-payload-conflict');
        $changedPayload->assertConflict()->assertJsonPath('code', 'idempotency_conflict');

        $secondVoid = $this->request($owner, 'POST', $itemPath.'/void', [
            'expected_version' => 5,
            'reason' => 'Thử void lần hai',
        ], (string) Str::uuid(), 'D04-11-void-again');
        $secondVoid->assertConflict()->assertJsonPath('code', 'fee_record_state_conflict');
    }

    public function test_fee_period_currency_amount_state_access_and_pagination_are_enforced(): void
    {
        $program = $this->program('D04 Fixture Active');
        $this->clearProgramFees($program);
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $member = $this->user('be-d04-late-member@tutorlink.test');
        $path = '/api/v1/programs/'.$program->id.'/fee-records';

        foreach ([
            ['period' => '2026-13', 'amount_minor' => 10],
            ['period' => '2026-02', 'amount_minor' => 10, 'currency' => 'USD'],
            ['period' => '2021-12', 'amount_minor' => 10],
            ['period' => '2026-12', 'amount_minor' => 10],
            ['period' => '2026-11', 'amount_minor' => -1],
            ['period' => '2026-11', 'amount_minor' => 1.5],
            ['period' => '2026-11', 'amount_minor' => '10'],
            ['period' => '2026-11', 'amount_minor' => null],
        ] as $index => $body) {
            $response = $this->request($owner, 'POST', $path, $body, (string) Str::uuid(), 'D04-11-invalid-'.$index);
            $response->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
        }

        $leap = $this->request($owner, 'POST', $path, [
            'period' => '2024-02', 'amount_minor' => 0,
        ], (string) Str::uuid(), 'D04-11-leap-month-free-record');
        $leap->assertCreated()->assertJsonPath('data.period_end', '2024-02-29')
            ->assertJsonPath('data.amount_minor', 0)->assertJsonPath('data.tutor_payment_mark', 'unmarked');
        $freeId = (int) $leap->json('data.id');
        foreach (['mark-paid', 'mark-unpaid'] as $mark) {
            $blocked = $this->request($owner, 'POST', '/api/v1/fee-records/'.$freeId.'/'.$mark, [
                'expected_version' => 1,
            ], (string) Str::uuid(), 'D04-11-free-'.$mark.'-blocked');
            $blocked->assertConflict()->assertJsonPath('code', 'fee_record_state_conflict');
        }

        $list = $this->actingAs($owner)->getJson($path.'?period=2024-02&status=declared&tutor_payment_mark=unmarked&per_page=1');
        $this->recordApi('D04-11-list-filtered-owner', 'owner', 'GET', $path.'?period=2024-02&status=declared&tutor_payment_mark=unmarked&per_page=1', null, $list);
        $list->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.per_page', 1)->assertJsonPath('data.0.id', $freeId);

        $badFilter = $this->getJson($path.'?status=confirmed');
        $badFilter->assertUnprocessable()->assertJsonValidationErrors('status');
        $unknownFilter = $this->getJson($path.'?sort=amount_minor');
        $unknownFilter->assertUnprocessable()->assertJsonValidationErrors('sort');
        $outsider = TestActors::customer();
        $hidden = $this->actingAs($outsider)->getJson($path);
        $hidden->assertNotFound()->assertJsonPath('code', 'program_not_found');
        $customerList = $this->actingAs($member)->getJson($path);
        $customerList->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $freeId);

        $paused = $this->program('D04 Fixture Paused');
        $pausedCreate = $this->request($owner, 'POST', '/api/v1/programs/'.$paused->id.'/fee-records', [
            'period' => '2026-11', 'amount_minor' => 100,
        ], (string) Str::uuid(), 'D04-11-create-paused-blocked');
        $pausedCreate->assertConflict()->assertJsonPath('code', 'program_state_conflict');

        DB::table('programs')->where('id', $program->id)->update(['status' => 'paused']);
        $pausedEdit = $this->request($owner, 'PATCH', '/api/v1/fee-records/'.$freeId, [
            'expected_version' => 1, 'amount_minor' => 200, 'reason' => 'Đổi mức khai báo khi tạm dừng',
        ], (string) Str::uuid(), 'D04-11-edit-existing-paused-allowed');
        $pausedEdit->assertOk()->assertJsonPath('data.version', 2);
        DB::table('programs')->where('id', $program->id)->update(['status' => 'completed']);
        $terminalEdit = $this->request($owner, 'PATCH', '/api/v1/fee-records/'.$freeId, [
            'expected_version' => 2, 'amount_minor' => 300, 'reason' => 'Terminal must block',
        ], (string) Str::uuid(), 'D04-11-terminal-edit-blocked');
        $terminalEdit->assertConflict()->assertJsonPath('code', 'program_state_conflict');
    }

    private function request(User $actor, string $method, string $path, ?array $body, string $key, string $case): TestResponse
    {
        $response = match ($method) {
            'GET' => $this->actingAs($actor)->getJson($path),
            'PATCH' => $this->actingAs($actor)->patchJson($path, $body ?? [], ['Idempotency-Key' => $key]),
            default => $this->actingAs($actor)->postJson($path, $body ?? [], ['Idempotency-Key' => $key]),
        };
        $this->recordApi($case, $actor->role, $method, $path, $body, $response, $key === '' ? [] : ['Idempotency-Key' => $key]);

        return $response;
    }

    private function program(string $title): Program
    {
        return Program::query()->where('title', $title)->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function clearProgramFees(Program $program): void
    {
        DB::table('fee_records')->where('program_id', $program->id)->delete();
    }

    private function recordApi(string $case, string $actor, string $method, string $path, ?array $body, TestResponse $response, array $headers = []): void
    {
        fwrite(STDOUT, json_encode([
            'case' => $case,
            'actor' => $actor,
            'request' => ['method' => $method, 'path' => $path, 'headers' => $headers, 'body' => $body],
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => ['X-Request-ID' => $response->headers->get('X-Request-ID')],
                'body' => json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR).PHP_EOL);
    }
}
