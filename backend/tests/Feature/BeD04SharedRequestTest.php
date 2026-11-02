<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveApiActor;
use App\Http\Requests\BeD04ApiRequest;
use App\Models\Program;
use App\Models\User;
use App\Support\ApiErrorResponse;
use App\Support\BeD04Workspace;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BeD04SharedRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', EnsureActiveApiActor::class])
            ->match(['GET', 'POST'], '/api/v1/programs/{program}/progress-records', function (BeD04ProbeRequest $request) {
                if ($request->validated('expected_version') === 2) {
                    return ApiErrorResponse::staleVersion(3, (string) $request->attributes->get('request_id'));
                }

                return response()->json(['data' => array_merge(
                    ['program_id' => (int) $request->route('program')],
                    $request->validated(),
                )]);
            })
            ->whereNumber('program');
    }

    public function test_workspace_scope_hides_outsiders_and_forbids_member_mutation(): void
    {
        $program = $this->activeProgram();
        $member = $this->user('be-d04-late-member@tutorlink.test');
        $outsider = $this->user('be-d04-outsider@tutorlink.test');

        $memberRead = $this->actingAs($member)->getJson($this->path($program));
        $this->recordApi('AU-05-member-read', 'member', 'GET', $this->path($program), null, $memberRead);
        $memberRead
            ->assertOk()
            ->assertJsonPath('data.program_id', $program->id);

        $memberWrite = $this->postJson($this->path($program), ['expected_version' => 1]);
        $this->recordApi('AU-05-member-mutation', 'member', 'POST', $this->path($program), '{"expected_version":1}', $memberWrite);
        $memberWrite
            ->assertForbidden()
            ->assertJsonPath('code', 'workspace_mutation_forbidden');

        $outsiderRead = $this->actingAs($outsider)->getJson($this->path($program));
        $this->recordApi('AU-03-outsider-read', 'outsider', 'GET', $this->path($program), null, $outsiderRead);
        $outsiderRead
            ->assertNotFound()
            ->assertJsonPath('code', 'program_not_found');

        $pausedProgram = Program::query()->where('title', 'D04 Fixture Paused')->firstOrFail();
        $revokedRead = $this->actingAs($this->user('be-d04-revoked@tutorlink.test'))
            ->getJson($this->path($pausedProgram));
        $this->recordApi('AU-06-revoked-member-read', 'revoked-member', 'GET', $this->path($pausedProgram), null, $revokedRead);
        $revokedRead->assertNotFound()->assertJsonPath('code', 'program_not_found');
    }

    public function test_authentication_inactive_account_and_admin_scope_use_api_error_envelope(): void
    {
        $program = $this->activeProgram();

        $unauthenticated = $this->getJson($this->path($program));
        $this->recordApi('AU-01-unauthenticated', 'guest', 'GET', $this->path($program), null, $unauthenticated);
        $unauthenticated
            ->assertUnauthorized()
            ->assertJsonStructure(['code', 'message', 'request_id', 'errors']);

        $inactive = $this->actingAs($this->user('be-d04-inactive@tutorlink.test'))->getJson($this->path($program));
        $this->recordApi('AU-02-inactive-account', 'inactive', 'GET', $this->path($program), null, $inactive);
        $inactive
            ->assertForbidden()
            ->assertJsonPath('code', 'account_inactive');

        $admin = $this->actingAs($this->user('be-d04-admin@tutorlink.test'))->getJson($this->path($program));
        $this->recordApi('AU-04-admin-no-workspace-scope', 'admin', 'GET', $this->path($program), null, $admin);
        $admin
            ->assertNotFound()
            ->assertJsonPath('code', 'program_not_found');
    }

    #[DataProvider('strictIntegerInputs')]
    public function test_expected_version_rejects_non_json_integers(mixed $value): void
    {
        $program = $this->activeProgram();
        $this->actingAs($this->owner());

        $request = is_float($value)
            ? $this->call('POST', $this->path($program), [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ], json_encode(['expected_version' => $value], JSON_PRESERVE_ZERO_FRACTION))
            : $this->postJson($this->path($program), ['expected_version' => $value]);

        $this->recordApi(
            'CM-01-strict-integer',
            'owner',
            'POST',
            $this->path($program),
            json_encode(['expected_version' => $value], JSON_PRESERVE_ZERO_FRACTION),
            $request,
        );

        $request
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('expected_version');
    }

    public static function strictIntegerInputs(): array
    {
        return [
            'numeric string' => ['1'],
            'float' => [1.0],
            'boolean' => [true],
            'zero' => [0],
        ];
    }

    public function test_unknown_fields_and_non_object_json_are_rejected(): void
    {
        $program = $this->activeProgram();
        $owner = $this->owner();

        $unknown = $this->actingAs($owner)->postJson($this->path($program), ['expected_version' => 1, 'owner_id' => $owner->id]);
        $this->recordApi(
            'CM-04-unknown-field',
            'owner',
            'POST',
            $this->path($program),
            json_encode(['expected_version' => 1, 'owner_id' => $owner->id]),
            $unknown,
        );
        $unknown
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('owner_id');

        $nonObject = $this->call('POST', $this->path($program), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '[{"expected_version":1}]');
        $this->recordApi('CM-03-json-array-body', 'owner', 'POST', $this->path($program), '[{"expected_version":1}]', $nonObject);
        $nonObject
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('body');

        $unknownQuery = $this->actingAs($owner)->getJson($this->path($program).'?unexpected=1');
        $this->recordApi('PG-04-unknown-query', 'owner', 'GET', $this->path($program).'?unexpected=1', null, $unknownQuery);
        $unknownQuery->assertUnprocessable()->assertJsonValidationErrors('unexpected');

        $writeQuery = $this->postJson($this->path($program).'?unexpected=1', ['expected_version' => 1]);
        $this->recordApi('CM-04-unknown-write-query', 'owner', 'POST', $this->path($program).'?unexpected=1', '{"expected_version":1}', $writeQuery);
        $writeQuery->assertUnprocessable()->assertJsonValidationErrors('unexpected');
    }

    public function test_normalization_preserves_omission_null_and_body_whitespace(): void
    {
        $program = $this->activeProgram();
        $owner = $this->owner();
        $requestId = '726ed088-140a-4e50-a09d-9d4a655c83a4';

        $normalizePayload = [
            'expected_version' => 1,
            'title' => '  A title  ',
            'body' => " leading\r\nnext ",
            'note' => "  note\rline  ",
            'reason' => '  changed  ',
        ];
        $normalized = $this->actingAs($owner)->postJson($this->path($program), $normalizePayload, ['X-Request-ID' => $requestId]);
        $this->recordApi(
            'CM-05-normalized-payload',
            'owner',
            'POST',
            $this->path($program),
            json_encode($normalizePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $normalized,
        );
        $normalized
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('data.title', 'A title')
            ->assertJsonPath('data.body', " leading\nnext ")
            ->assertJsonPath('data.note', "  note\nline  ")
            ->assertJsonPath('data.reason', 'changed');

        $omitted = $this->postJson($this->path($program), ['expected_version' => 1]);
        $this->recordApi('PR-PATCH-note-omitted', 'owner', 'POST', $this->path($program), '{"expected_version":1}', $omitted);
        $omitted
            ->assertOk()
            ->assertJsonMissingPath('data.note');

        $explicitNull = $this->postJson($this->path($program), ['expected_version' => 1, 'note' => null]);
        $this->recordApi('PR-PATCH-note-null', 'owner', 'POST', $this->path($program), '{"expected_version":1,"note":null}', $explicitNull);
        $explicitNull
            ->assertOk()
            ->assertJsonPath('data.note', null);

        $whitespaceNote = $this->postJson($this->path($program), ['expected_version' => 1, 'note' => '   ']);
        $this->assertSame(422, $whitespaceNote->status(), $whitespaceNote->getContent());
        $this->recordApi('CM-05-whitespace-only', 'owner', 'POST', $this->path($program), '{"expected_version":1,"note":"   "}', $whitespaceNote);
        $whitespaceNote->assertJsonValidationErrors('note');

        $empty = $this->postJson($this->path($program), ['expected_version' => 1, 'note' => '']);
        $this->recordApi('CM-05-empty-string', 'owner', 'POST', $this->path($program), '{"expected_version":1,"note":""}', $empty);
        $empty->assertUnprocessable()->assertJsonValidationErrors('note');
    }

    public function test_stale_version_response_has_only_authorized_current_version_and_request_id(): void
    {
        $program = $this->activeProgram();
        $requestId = '69fdd4f4-dab2-4b45-a746-b1e46c798658';

        $stale = $this->actingAs($this->owner())->postJson($this->path($program), ['expected_version' => 2], ['X-Request-ID' => $requestId]);
        $this->recordApi('PR-NOOP-03-stale-version', 'owner', 'POST', $this->path($program), '{"expected_version":2}', $stale);
        $stale
            ->assertConflict()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertExactJson([
                'code' => 'stale_version',
                'message' => 'The resource changed. Reload before updating.',
                'request_id' => $requestId,
                'errors' => ['current_version' => 3],
            ]);
    }

    public function test_locked_workspace_uses_contract_lock_order_and_locks_files_after_resource(): void
    {
        $program = $this->activeProgram();
        $owner = $this->owner();
        $link = DB::table('file_links')
            ->join('announcements', 'announcements.id', '=', 'file_links.resource_id')
            ->where('file_links.resource_type', 'announcement')
            ->where('announcements.program_id', $program->id)
            ->first(['file_links.file_id', 'file_links.resource_id']);

        $this->assertNotNull($link, 'D04 active Program needs one linked Announcement file fixture.');

        $locks = [];
        DB::listen(function (QueryExecuted $query) use (&$locks): void {
            if (str_contains(strtolower($query->sql), 'for update')) {
                $locks[] = strtolower($query->sql);
            }
        });

        $result = app(BeD04Workspace::class)->withLockedOwnerProgram(
            $owner,
            (int) $program->id,
            'c8a11b3b-2f35-45eb-8fd8-3f1ff167a798',
            function (Program $lockedProgram) use ($link): string {
                DB::table('announcements')->where('id', $link->resource_id)->lockForUpdate()->first();
                app(BeD04Workspace::class)->lockFiles([(int) $link->file_id]);
                app(BeD04Workspace::class)->lockAnnouncementFileLinks((int) $link->resource_id, [(int) $link->file_id]);

                return $lockedProgram->status;
            },
        );

        $this->assertSame('active', $result);
        $expected = [
            'from "users"',
            'from "tutor_profiles"',
            'from "programs"',
            'from "program_members"',
            'from "announcements"',
            'from "files"',
            'from "file_links"',
        ];
        $previous = -1;

        foreach ($expected as $table) {
            $position = $this->firstLockFor($locks, $table);
            $this->assertGreaterThan($previous, $position, "Lock order violated at {$table}.");
            $previous = $position;
        }
    }

    public function test_file_row_locks_require_the_workspace_writer_transaction(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('D04 resource and file locks must share the writer transaction.');

        app(BeD04Workspace::class)->lockFiles([1]);
    }

    private function firstLockFor(array $locks, string $table): int
    {
        foreach ($locks as $index => $sql) {
            if (str_contains($sql, $table)) {
                return $index;
            }
        }

        $this->fail("Missing FOR UPDATE lock for {$table}.");
    }

    private function activeProgram(): Program
    {
        return Program::query()->where('title', 'D04 Fixture Active')->firstOrFail();
    }

    private function owner(): User
    {
        return $this->user('be-d04-owner@tutorlink.test');
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function path(Program $program): string
    {
        return '/api/v1/programs/'.$program->id.'/progress-records';
    }

    private function recordApi(
        string $caseId,
        string $actor,
        string $method,
        string $path,
        ?string $requestBody,
        TestResponse $response,
    ): void {
        fwrite(STDOUT, json_encode([
            'case' => $caseId,
            'actor' => $actor,
            'request' => ['method' => $method, 'path' => $path, 'body' => $requestBody],
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => ['X-Request-ID' => $response->headers->get('X-Request-ID')],
                'body' => json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR).PHP_EOL);
    }
}

class BeD04ProbeRequest extends BeD04ApiRequest
{
    public function authorize(): bool
    {
        $workspace = app(BeD04Workspace::class);
        $requestId = (string) $this->attributes->get('request_id');
        $programId = (int) $this->route('program');

        if ($this->isMethod('GET')) {
            $workspace->visible($this->user(), $programId, $requestId);
        } else {
            $workspace->owner($this->user(), $programId, $requestId);
        }

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'regex:/\A[1-9][0-9]*\z/']];
        }

        return [
            'expected_version' => $this->expectedVersionRules(),
            'title' => ['sometimes', 'required', 'string', 'regex:/[^\s\p{Z}]/u'],
            'body' => ['sometimes', 'required', 'string', 'regex:/[^\s\p{Z}]/u'],
            'note' => ['sometimes', 'nullable', 'string', 'regex:/[^\s\p{Z}]/u'],
            'reason' => ['sometimes', 'nullable', 'string', 'regex:/[^\s\p{Z}]/u'],
        ];
    }
}
