<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reviews\ModerateReview;
use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Support\TrustWorkflow;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /** Show scoped Review data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{review:?array{id:int,program_id:int,rating:int,comment:?string,visibility:string,version:int,created_at:?string},can_review:bool}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function show(Request $request, int $program, TrustWorkflow $flow): JsonResponse
    {
        $record = Program::visibleTo($request->user())->find($program);
        abort_unless($record, 404);
        $row = DB::table('reviews')->where('program_id', $program)->first();
        if ($row && $row->visibility === 'hidden' && (int) $row->customer_id !== $request->user()->id) {
            $row = null;
        }

        return response()->json(['data' => ['review' => $row ? $flow->reviewData($row) : null,
            'can_review' => $request->user()->role === 'customer' && $record->status === 'completed' && ! DB::table('reviews')->where('program_id', $program)->where('customer_id', $request->user()->id)->exists()]]);
    }

    /** Store scoped Review data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{id:int,program_id:int,rating:int,comment:?string,visibility:string,version:int,created_at:?string}}')]
    #[Response(201, description: 'Created atomically.', type: 'array{data:array{id:int,program_id:int,rating:int,comment:?string,visibility:string,version:int,created_at:?string}}')]
    #[HeaderParameter('Idempotency-Key', 'UUID for a 30-day canonical request receipt.', required: true, type: 'string', format: 'uuid')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function store(Request $request, int $program, TrustWorkflow $flow): JsonResponse
    {
        $flow->fields($request, ['rating', 'comment']);
        $raw = json_decode($request->getContent(), true);
        if (is_array($raw) && isset($raw['comment']) && is_string($raw['comment']) && preg_match('/[^\s\p{Z}]/u', $raw['comment']) !== 1) {
            $flow->fail($request, 422, 'invalid_review_comment', 'A supplied comment must contain non-whitespace text.');
        }
        $data = $request->validate(['rating' => 'required|integer:strict|between:1,5', 'comment' => ['sometimes', 'nullable', 'string', 'max:2000', 'regex:/\S/u']]);

        return DB::transaction(function () use ($request, $program, $flow, $data): JsonResponse {
            $actor = $flow->lockActor($request);
            $record = Program::visibleTo($actor)->whereKey($program)->lockForUpdate()->first();
            abort_unless($record && $actor->role === 'customer' && (int) $record->customer_id === $actor->id, 404);
            if ($record->status !== 'completed') {
                $flow->fail($request, 409, 'review_not_eligible', 'Program must be completed.');
            }
            $payload = ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null];
            if ($snapshot = $flow->replay($request, $payload)) {
                return response()->json($snapshot);
            }
            if (DB::table('reviews')->where('program_id', $program)->where('customer_id', $actor->id)->exists()) {
                $flow->fail($request, 409, 'review_already_exists', 'A Review already exists.');
            }
            $id = DB::table('reviews')->insertGetId([
                ...$payload, 'program_id' => $program, 'customer_id' => $actor->id, 'tutor_profile_id' => $record->owner_tutor_profile_id,
                'visibility' => 'visible', 'version' => 1, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            $flow->audit($request, 'review.create', 'review', $id, null, ['visibility' => 'visible', 'version' => 1]);
            $result = ['data' => $flow->reviewData(DB::table('reviews')->find($id))];
            $flow->receipt($request, $payload, $result);

            return response()->json($result, 201);
        }, 3);
    }

    /** Index scoped Review data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:list<array{id:int,program_id:int,rating:int,comment:?string,visibility:string,version:int,created_at:?string}>,meta:array{current_page:int,per_page:int,total:int}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function index(Request $request, TrustWorkflow $flow): JsonResponse
    {
        $flow->permission($request, 'admin.reviews.moderate');
        $data = $request->validate(['per_page' => 'sometimes|integer|min:1|max:50']);
        $rows = DB::table('reviews')->orderByDesc('id')->paginate($data['per_page'] ?? 20);

        return response()->json(['data' => $rows->getCollection()->map(fn ($row) => $flow->reviewData($row)),
            'meta' => ['current_page' => $rows->currentPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()]]);
    }

    /** Detail scoped Review data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{id:int,program_id:int,rating:int,comment:?string,visibility:string,version:int,created_at:?string}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function detail(Request $request, int $review, TrustWorkflow $flow): JsonResponse
    {
        $flow->permission($request, 'admin.reviews.moderate');
        $row = DB::table('reviews')->find($review);
        abort_unless($row, 404);

        return response()->json(['data' => [...$flow->reviewData($row), 'moderation_history' => DB::table('audit_logs')->where('resource_type', 'review')->where('resource_id', $review)->whereIn('action', ['review.hide', 'review.restore'])->orderByDesc('id')->get(['id', 'action', 'reason', 'created_at'])]]);
    }

    /** Hide scoped Review data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{id:int,program_id:int,rating:int,comment:?string,visibility:string,version:int,created_at:?string}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[BodyParameter('expected_version', required: true, type: 'int')]
    #[BodyParameter('reason', required: true, type: 'string')]
    public function hide(Request $request, int $review, TrustWorkflow $flow): JsonResponse
    {
        return $this->moderate($request, $review, 'hidden', $flow);
    }

    /** Restore scoped Review data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{id:int,program_id:int,rating:int,comment:?string,visibility:string,version:int,created_at:?string}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[BodyParameter('expected_version', required: true, type: 'int')]
    #[BodyParameter('reason', required: true, type: 'string')]
    public function restore(Request $request, int $review, TrustWorkflow $flow): JsonResponse
    {
        return $this->moderate($request, $review, 'visible', $flow);
    }

    private function moderate(Request $request, int $review, string $visibility, TrustWorkflow $flow): JsonResponse
    {
        $flow->permission($request, 'admin.reviews.moderate');
        $flow->fields($request, ['expected_version', 'reason']);
        $result = app(ModerateReview::class)->execute($request->user(), $review, $visibility, $request->all());

        return response()->json(['data' => $result]);
    }
}
