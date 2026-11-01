<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sessions\SaveSession;
use App\Actions\Sessions\TransitionSession;
use App\Exceptions\SessionActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelSessionRequest;
use App\Http\Requests\CompleteSessionRequest;
use App\Http\Requests\CreateSessionRequest;
use App\Http\Requests\ProgramIndexRequest;
use App\Http\Requests\SessionApiRequest;
use App\Http\Requests\UpdateSessionRequest;
use App\Http\Resources\SessionResource;
use App\Models\Program;
use App\Models\ProgramSession;
use App\Policies\ProgramSessionPolicy;
use App\Support\ApiErrorResponse;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /** List Sessions visible to the Program owner or member. */
    public function index(
        ProgramIndexRequest $request,
        int $program,
        ProgramSessionPolicy $policy,
    ): JsonResponse {
        $record = Program::query()->find($program);

        if (
            $record === null
            || ! $policy->viewAny($request->user(), $record)
        ) {
            return $this->error(
                $request,
                404,
                'program_not_found',
                'Program not found.',
            );
        }

        $sessions = $record->sessions()
            ->with('actual')
            ->orderBy('planned_starts_at')
            ->orderBy('id')
            ->paginate($request->validated('per_page', 20));

        return SessionResource::collection($sessions)->response();
    }

    /** Retrieve a Session using its planned ID. */
    #[OpenApiResponse(
        status: 200,
        type: 'array{data: SessionResource}',
    )]
    public function show(
        Request $request,
        int $session,
        ProgramSessionPolicy $policy,
    ): JsonResponse {
        $record = ProgramSession::query()
            ->with(['program', 'actual'])
            ->find($session);

        if (
            $record === null
            || ! $policy->view($request->user(), $record)
        ) {
            return $this->error(
                $request,
                404,
                'session_not_found',
                'Session not found.',
            );
        }

        return (new SessionResource($record))->response();
    }

    /** Create a scheduled Session without an actual occurrence. */
    #[IgnoreResponse(status: 200)]
    #[OpenApiResponse(
        status: 201,
        type: 'array{data: SessionResource}',
    )]
    #[HeaderParameter(
        'Idempotency-Key',
        'UUID used to replay the same request for 30 days.',
        required: true,
        type: 'string',
        format: 'uuid',
    )]
    public function store(
        CreateSessionRequest $request,
        int $program,
        SaveSession $saveSession,
    ): JsonResponse {
        return $this->save(
            $request,
            $program,
            null,
            $saveSession,
        );
    }

    /** Edit a scheduled Session with an expected version. */
    #[OpenApiResponse(
        status: 200,
        type: 'array{data: SessionResource}',
    )]
    #[HeaderParameter(
        'Idempotency-Key',
        'UUID used to replay the same request for 30 days.',
        required: true,
        type: 'string',
        format: 'uuid',
    )]
    public function update(
        UpdateSessionRequest $request,
        int $session,
        SaveSession $saveSession,
    ): JsonResponse {
        $programId = ProgramSession::query()
            ->whereKey($session)
            ->value('program_id');

        if ($programId === null) {
            return $this->error(
                $request,
                404,
                'session_not_found',
                'Session not found.',
            );
        }

        return $this->save(
            $request,
            (int) $programId,
            $session,
            $saveSession,
        );
    }

    /** Complete a scheduled Session and create its actual record. */
    #[OpenApiResponse(
        status: 200,
        type: 'array{data: SessionResource}',
    )]
    #[HeaderParameter(
        'Idempotency-Key',
        'UUID for request replay; the receipt is retained for 30 days.',
        required: true,
        type: 'string',
        format: 'uuid',
    )]
    public function complete(
        CompleteSessionRequest $request,
        int $session,
        TransitionSession $transitionSession,
    ): JsonResponse {
        return $this->transition(
            $request,
            $session,
            'complete',
            $transitionSession,
        );
    }

    /** Cancel a scheduled Session without creating an actual record. */
    #[OpenApiResponse(
        status: 200,
        type: 'array{data: SessionResource}',
    )]
    #[HeaderParameter(
        'Idempotency-Key',
        'UUID for request replay; the receipt is retained for 30 days.',
        required: true,
        type: 'string',
        format: 'uuid',
    )]
    public function cancel(
        CancelSessionRequest $request,
        int $session,
        TransitionSession $transitionSession,
    ): JsonResponse {
        return $this->transition(
            $request,
            $session,
            'cancel',
            $transitionSession,
        );
    }

    private function transition(
        CompleteSessionRequest $request,
        int $sessionId,
        string $transition,
        TransitionSession $transitionSession,
    ): JsonResponse {
        try {
            $result = $transitionSession->execute(
                $request->user(),
                $sessionId,
                $transition,
                $request->validated(),
                $request->idempotencyKey(),
                (string) $request->attributes->get('request_id'),
            );

            return response()->json(
                $result['body'],
                $result['status'],
            );
        } catch (SessionActionException $exception) {
            return $this->error(
                $request,
                $exception->httpStatus,
                $exception->errorCode,
                $exception->getMessage(),
                $exception->errors,
            );
        }
    }

    private function save(
        SessionApiRequest $request,
        int $programId,
        ?int $sessionId,
        SaveSession $saveSession,
    ): JsonResponse {
        try {
            $result = $saveSession->execute(
                $request->user(),
                $programId,
                $sessionId,
                $request->validated(),
                $request->idempotencyKey(),
                (string) $request->attributes->get('request_id'),
            );

            return response()->json(
                $result['body'],
                $result['status'],
            );
        } catch (SessionActionException $exception) {
            return $this->error(
                $request,
                $exception->httpStatus,
                $exception->errorCode,
                $exception->getMessage(),
                $exception->errors,
            );
        }
    }

    /** @param array<string, mixed> $errors */
    private function error(
        Request $request,
        int $status,
        string $code,
        string $message,
        array $errors = [],
    ): JsonResponse {
        return ApiErrorResponse::make(
            $status,
            $code,
            $message,
            (string) $request->attributes->get('request_id'),
            $errors,
        );
    }
}
