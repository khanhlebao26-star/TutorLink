<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BeD04ContractBoundaryMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_br26_defaults_maximums_empty_pages_and_filter_intersection_on_all_lists(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $member = $this->user('be-d04-late-member@tutorlink.test');
        $progressPath = '/api/v1/programs/'.$program->id.'/progress-records';
        $announcementPath = '/api/v1/programs/'.$program->id.'/announcements';
        $feePath = '/api/v1/programs/'.$program->id.'/fee-records';

        $progressDefault = $this->actingAs($member)->getJson($progressPath.'?page=1');
        $this->recordApi('D04-13-progress-default-20', 'member', 'GET', $progressPath.'?page=1', null, $progressDefault);
        $progressDefault->assertOk()->assertJsonPath('meta.per_page', 20)->assertJsonPath('meta.total', 55)->assertJsonPath('meta.last_page', 3);
        $this->assertCount(20, $progressDefault->json('data'));

        $progressMaximum = $this->getJson($progressPath.'?per_page=50');
        $this->recordApi('D04-13-progress-max-50', 'member', 'GET', $progressPath.'?per_page=50', null, $progressMaximum);
        $progressMaximum->assertOk()->assertJsonPath('meta.per_page', 50)->assertJsonPath('meta.total', 55)->assertJsonPath('meta.last_page', 2);
        $this->assertCount(50, $progressMaximum->json('data'));

        $progressPastEnd = $this->getJson($progressPath.'?page=4');
        $this->recordApi('D04-13-progress-page-past-end', 'member', 'GET', $progressPath.'?page=4', null, $progressPastEnd);
        $progressPastEnd->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 55)->assertJsonPath('meta.last_page', 3);

        $progressOrder = DB::table('progress_records')->where('program_id', $program->id)
            ->orderByRaw('recorded_at DESC NULLS LAST')->orderByDesc('id')->limit(20)->pluck('id')->all();
        $this->assertSame($progressOrder, array_column($progressDefault->json('data'), 'id'));

        $announcementRows = [];
        for ($index = 1; $index <= 51; $index++) {
            $announcementRows[] = [
                'program_id' => $program->id,
                'author_id' => $owner->id,
                'title' => sprintf('D04 boundary Draft %02d', $index),
                'body' => 'Synthetic pagination boundary.',
                'published_at' => null,
                'version' => 1,
                'created_at' => '2026-11-02 00:00:00+00:00',
                'updated_at' => '2026-11-02 00:00:00+00:00',
            ];
        }
        DB::table('announcements')->insert($announcementRows);

        $publishedDefault = $this->actingAs($member)->getJson($announcementPath);
        $this->recordApi('D04-13-announcement-default-published', 'member', 'GET', $announcementPath, null, $publishedDefault);
        $publishedDefault->assertOk()->assertJsonPath('meta.per_page', 20)->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 1);

        $draftDefault = $this->actingAs($owner)->getJson($announcementPath.'?state=draft');
        $this->recordApi('D04-13-announcement-draft-default-20', 'owner', 'GET', $announcementPath.'?state=draft', null, $draftDefault);
        $draftDefault->assertOk()->assertJsonPath('meta.per_page', 20)->assertJsonPath('meta.total', 52)->assertJsonPath('meta.last_page', 3);
        $this->assertCount(20, $draftDefault->json('data'));

        $draftMaximum = $this->getJson($announcementPath.'?state=draft&per_page=50');
        $this->recordApi('D04-13-announcement-max-50', 'owner', 'GET', $announcementPath.'?state=draft&per_page=50', null, $draftMaximum);
        $draftMaximum->assertOk()->assertJsonPath('meta.per_page', 50)->assertJsonPath('meta.total', 52)->assertJsonPath('meta.last_page', 2);
        $this->assertCount(50, $draftMaximum->json('data'));

        $draftPastEnd = $this->getJson($announcementPath.'?state=draft&page=3&per_page=50');
        $this->recordApi('D04-13-announcement-page-past-end', 'owner', 'GET', $announcementPath.'?state=draft&page=3&per_page=50', null, $draftPastEnd);
        $draftPastEnd->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 52)->assertJsonPath('meta.last_page', 2);

        $all = $this->getJson($announcementPath.'?state=all&per_page=50');
        $this->recordApi('D04-13-announcement-owner-all-filter', 'owner', 'GET', $announcementPath.'?state=all&per_page=50', null, $all);
        $all->assertOk()->assertJsonPath('meta.total', 54)->assertJsonPath('meta.per_page', 50)->assertJsonPath('data.0.state', 'draft');

        $feeDefault = $this->actingAs($member)->getJson($feePath.'?page=1');
        $this->recordApi('D04-13-fee-default-20', 'member', 'GET', $feePath.'?page=1', null, $feeDefault);
        $feeDefault->assertOk()->assertJsonPath('meta.per_page', 20)->assertJsonPath('meta.total', 59)->assertJsonPath('meta.last_page', 3);
        $this->assertCount(20, $feeDefault->json('data'));

        $feeMaximum = $this->getJson($feePath.'?per_page=50');
        $this->recordApi('D04-13-fee-max-50', 'member', 'GET', $feePath.'?per_page=50', null, $feeMaximum);
        $feeMaximum->assertOk()->assertJsonPath('meta.per_page', 50)->assertJsonPath('meta.total', 59)->assertJsonPath('meta.last_page', 2);
        $this->assertCount(50, $feeMaximum->json('data'));

        $feePastEnd = $this->getJson($feePath.'?page=4');
        $this->recordApi('D04-13-fee-page-past-end', 'member', 'GET', $feePath.'?page=4', null, $feePastEnd);
        $feePastEnd->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 59)->assertJsonPath('meta.last_page', 3);

        $feeOrder = DB::table('fee_records')->where('program_id', $program->id)
            ->orderByDesc('period_start')->orderByDesc('id')->limit(20)->pluck('id')->all();
        $this->assertSame($feeOrder, array_column($feeDefault->json('data'), 'id'));

        $combinedFilter = $this->getJson($feePath.'?period=2024-02&status=declared&tutor_payment_mark=unmarked');
        $this->recordApi('D04-13-fee-filters-and', 'member', 'GET', $feePath.'?period=2024-02&status=declared&tutor_payment_mark=unmarked', null, $combinedFilter);
        $combinedFilter->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.period', '2024-02');

        $filterIntersection = $this->getJson($feePath.'?period=2024-02&status=voided');
        $this->recordApi('D04-13-fee-filter-intersection-empty', 'member', 'GET', $feePath.'?period=2024-02&status=voided', null, $filterIntersection);
        $filterIntersection->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0)->assertJsonPath('meta.last_page', 1);

        foreach ([
            [$progressPath, $member, 'progress-per-page-51'],
            [$announcementPath.'?state=draft', $owner, 'announcement-per-page-51'],
            [$feePath, $member, 'fee-per-page-51'],
        ] as [$path, $actor, $case]) {
            $separator = str_contains($path, '?') ? '&' : '?';
            $invalid = $this->actingAs($actor)->getJson($path.$separator.'per_page=51');
            $this->recordApi('D04-13-'.$case, $actor->role, 'GET', $path.$separator.'per_page=51', null, $invalid);
            $invalid->assertUnprocessable()->assertJsonValidationErrors('per_page');

            $invalidPage = $this->getJson($path.$separator.'page=0');
            $this->recordApi('D04-13-'.$case.'-page-zero', $actor->role, 'GET', $path.$separator.'page=0', null, $invalidPage);
            $invalidPage->assertUnprocessable()->assertJsonValidationErrors('page');
        }

        $overview = $this->actingAs($owner)->getJson('/api/v1/programs/'.$program->id.'/overview?page=1');
        $this->recordApi('D04-13-overview-no-pagination', 'owner', 'GET', '/api/v1/programs/'.$program->id.'/overview?page=1', null, $overview);
        $overview->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    public function test_fee_month_eligibility_changes_at_midnight_in_program_timezone(): void
    {
        $program = $this->program('D04 Fixture Active');
        $this->assertSame('Asia/Ho_Chi_Minh', $program->timezone);
        DB::table('fee_records')->where('program_id', $program->id)->delete();
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $path = '/api/v1/programs/'.$program->id.'/fee-records';

        try {
            Carbon::setTestNow(CarbonImmutable::parse('2026-10-31T16:59:59Z'));
            $october = $this->feeCreate($owner, $path, [
                'period' => '2026-10',
                'amount_minor' => 9007199254740991,
            ], 'D04-13-local-october-before-midnight');
            $october->assertCreated()
                ->assertJsonPath('data.period_start', '2026-10-01')
                ->assertJsonPath('data.period_end', '2026-10-31')
                ->assertJsonPath('data.currency', 'VND');

            $novemberBeforeBoundary = $this->feeCreate($owner, $path, [
                'period' => '2026-11',
                'amount_minor' => 0,
            ], 'D04-13-local-november-too-early');
            $novemberBeforeBoundary->assertUnprocessable()->assertJsonValidationErrors('period');

            Carbon::setTestNow(CarbonImmutable::parse('2026-10-31T17:00:00Z'));
            $novemberAtBoundary = $this->feeCreate($owner, $path, [
                'period' => '2026-11',
                'amount_minor' => 0,
            ], 'D04-13-local-november-at-midnight');
            $novemberAtBoundary->assertCreated()
                ->assertJsonPath('data.period_start', '2026-11-01')
                ->assertJsonPath('data.period_end', '2026-11-30');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_real_apis_normalize_unicode_crlf_and_reject_wrong_json_types_and_unknown_fields(): void
    {
        $program = $this->program('D04 Fixture Active');
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $progressPath = '/api/v1/programs/'.$program->id.'/progress-records';
        $note = "  Tiếng Việt e\u{0301}\r\nline\ralpha  ";
        $expectedNote = "  Tiếng Việt e\u{0301}\nline\nalpha  ";

        $progress = $this->actingAs($owner)->postJson($progressPath, [
            'metric_name' => 'progress_percent',
            'value' => 42,
            'note' => $note,
        ]);
        $this->recordApi('D04-13-progress-unicode-crlf', 'owner', 'POST', $progressPath, [
            'metric_name' => 'progress_percent', 'value' => 42, 'note' => $note,
        ], $progress);
        $progress->assertCreated()->assertJsonPath('data.note', $expectedNote);

        $unknownProgressField = $this->actingAs($owner)->postJson($progressPath, [
            'metric_name' => 'progress_percent', 'value' => 43, 'customer_id' => $program->customer_id,
        ]);
        $this->recordApi('D04-13-progress-unknown-field', 'owner', 'POST', $progressPath, [
            'metric_name' => 'progress_percent', 'value' => 43, 'customer_id' => $program->customer_id,
        ], $unknownProgressField);
        $unknownProgressField->assertUnprocessable()->assertJsonValidationErrors('customer_id');

        foreach (['true', '42.0', '"42"'] as $index => $invalidValue) {
            $body = '{"metric_name":"progress_percent","value":'.$invalidValue.'}';
            $invalid = $this->rawJson($owner, 'POST', $progressPath, $body);
            $this->recordApi('D04-13-progress-non-integer-'.$index, 'owner', 'POST', $progressPath, $body, $invalid);
            $invalid->assertUnprocessable()->assertJsonValidationErrors('value');
        }

        $progressId = (int) $progress->json('data.id');
        $invalidProgressVersion = $this->rawJson($owner, 'PATCH', '/api/v1/progress-records/'.$progressId, '{"expected_version":1.0,"value":43}');
        $this->recordApi('D04-13-progress-float-version', 'owner', 'PATCH', '/api/v1/progress-records/'.$progressId, '{"expected_version":1.0,"value":43}', $invalidProgressVersion);
        $invalidProgressVersion->assertUnprocessable()->assertJsonValidationErrors('expected_version');

        $announcementPath = '/api/v1/programs/'.$program->id.'/announcements';
        $title = "  Lớp e\u{0301}  ";
        $bodyText = "  Dòng một\r\nDòng hai\rcuối  ";
        $announcement = $this->actingAs($owner)->postJson($announcementPath, [
            'title' => $title,
            'body' => $bodyText,
        ]);
        $this->recordApi('D04-13-announcement-unicode-crlf', 'owner', 'POST', $announcementPath, [
            'title' => $title, 'body' => $bodyText,
        ], $announcement);
        $announcement->assertCreated()
            ->assertJsonPath('data.title', "Lớp e\u{0301}")
            ->assertJsonPath('data.body', "  Dòng một\nDòng hai\ncuối  ")
            ->assertJsonPath('data.files', []);

        $announcementId = (int) $announcement->json('data.id');
        $invalidAnnouncementVersion = $this->rawJson($owner, 'PATCH', '/api/v1/announcements/'.$announcementId, '{"expected_version":true,"title":"Updated"}');
        $this->recordApi('D04-13-announcement-boolean-version', 'owner', 'PATCH', '/api/v1/announcements/'.$announcementId, '{"expected_version":true,"title":"Updated"}', $invalidAnnouncementVersion);
        $invalidAnnouncementVersion->assertUnprocessable()->assertJsonValidationErrors('expected_version');

        $unknownAnnouncementField = $this->actingAs($owner)->postJson($announcementPath, [
            'title' => 'Safe title', 'body' => 'Safe body', 'published_at' => '2026-11-01T00:00:00Z',
        ]);
        $this->recordApi('D04-13-announcement-server-field', 'owner', 'POST', $announcementPath, [
            'title' => 'Safe title', 'body' => 'Safe body', 'published_at' => '2026-11-01T00:00:00Z',
        ], $unknownAnnouncementField);
        $unknownAnnouncementField->assertUnprocessable()->assertJsonValidationErrors('published_at');

        DB::table('fee_records')->where('program_id', $program->id)->delete();
        $feePath = '/api/v1/programs/'.$program->id.'/fee-records';
        $createdFee = $this->feeCreate($owner, $feePath, [
            'period' => '2026-11', 'amount_minor' => 200,
        ], 'D04-13-fee-vnd-default');
        $createdFee->assertCreated()->assertJsonPath('data.currency', 'VND');
        $feeId = (int) $createdFee->json('data.id');

        foreach (['true', '200.0', '"200"', '9007199254740992'] as $index => $invalidAmount) {
            $key = (string) Str::uuid();
            $rawPayload = '{"period":"2026-10","amount_minor":'.$invalidAmount.'}';
            $response = $this->rawJson($owner, 'POST', $feePath, $rawPayload, ['Idempotency-Key' => $key]);
            $this->recordApi('D04-13-fee-invalid-number-'.$index, 'owner', 'POST', $feePath, $rawPayload, $response, ['Idempotency-Key' => $key]);
            $response->assertUnprocessable()->assertJsonValidationErrors('amount_minor');
        }

        $key = (string) Str::uuid();
        $invalidFeeVersion = $this->rawJson($owner, 'POST', '/api/v1/fee-records/'.$feeId.'/mark-paid', '{"expected_version":1.0}', ['Idempotency-Key' => $key]);
        $this->recordApi('D04-13-fee-float-version', 'owner', 'POST', '/api/v1/fee-records/'.$feeId.'/mark-paid', '{"expected_version":1.0}', $invalidFeeVersion, ['Idempotency-Key' => $key]);
        $invalidFeeVersion->assertUnprocessable()->assertJsonValidationErrors('expected_version');

        $unknownFeeField = $this->feeCreate($owner, $feePath, [
            'period' => '2026-10', 'amount_minor' => 200, 'period_start' => '2026-10-01',
        ], 'D04-13-fee-server-field');
        $unknownFeeField->assertUnprocessable()->assertJsonValidationErrors('period_start');

        $feeKey = (string) Str::uuid();
        $reason = "  Đã thu e\u{0301}\r\nbiên lai  ";
        $paid = $this->actingAs($owner)->postJson('/api/v1/fee-records/'.$feeId.'/mark-paid', [
            'expected_version' => 1,
            'reason' => $reason,
        ], ['Idempotency-Key' => $feeKey]);
        $this->recordApi('D04-13-fee-reason-unicode-crlf', 'owner', 'POST', '/api/v1/fee-records/'.$feeId.'/mark-paid', [
            'expected_version' => 1, 'reason' => $reason,
        ], $paid, ['Idempotency-Key' => $feeKey]);
        $paid->assertOk()
            ->assertJsonPath('data.payment_mark_reason', "Đã thu e\u{0301}\nbiên lai")
            ->assertJsonPath('data.last_change_reason', "Đã thu e\u{0301}\nbiên lai");

        $overviewQuery = $this->actingAs($owner)->getJson('/api/v1/programs/'.$program->id.'/overview?per_page=20');
        $this->recordApi('D04-13-overview-query-not-supported', 'owner', 'GET', '/api/v1/programs/'.$program->id.'/overview?per_page=20', null, $overviewQuery);
        $overviewQuery->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    private function feeCreate(User $owner, string $path, array $body, string $case): TestResponse
    {
        $key = (string) Str::uuid();
        $response = $this->actingAs($owner)->postJson($path, $body, ['Idempotency-Key' => $key]);
        $this->recordApi($case, 'owner', 'POST', $path, $body, $response, ['Idempotency-Key' => $key]);

        return $response;
    }

    private function rawJson(User $actor, string $method, string $path, string $json, array $headers = []): TestResponse
    {
        $this->actingAs($actor);
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call($method, $path, [], [], [], $server, $json);
    }

    private function program(string $title): Program
    {
        return Program::query()->where('title', $title)->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    /** @param array<string, mixed>|string|null $body */
    private function recordApi(string $case, string $actor, string $method, string $path, array|string|null $body, TestResponse $response, array $headers = []): void
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
