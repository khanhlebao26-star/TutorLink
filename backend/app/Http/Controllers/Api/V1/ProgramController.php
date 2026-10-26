<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Programs\CreateProgramDraft;
use App\Actions\Programs\TransitionProgram;
use App\Actions\Programs\UpdateProgramDraft;
use App\Exceptions\ProgramActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProgramRequest;
use App\Http\Requests\ProgramActionRequest;
use App\Http\Requests\ProgramIndexRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Models\User;
use App\Policies\ProgramPolicy;
use App\Support\ApiErrorResponse;
use App\Support\TutorEligibility;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    private const ERROR_RESPONSE_TYPE = 'array{code: string, message: string, request_id: string, errors: array<string, mixed>}';

    /** List Programs visible to the current actor. */
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The account is inactive.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'The query parameters are invalid.', type: self::ERROR_RESPONSE_TYPE)]
    public function index(ProgramIndexRequest $request, ProgramPolicy $policy): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return $this->error($request, 401, 'unauthenticated', 'Authentication is required.');
        }

        if (! $policy->viewAny($actor)) {
            return $this->error($request, 403, 'program_list_forbidden', 'This account cannot list Programs.');
        }

        $canEditDraft = $this->canEditDraft($actor);

        $programs = Program::query()
            ->visibleTo($actor)
            ->withActionContext()
            ->latest('id')
            ->paginate($request->validated('per_page', 20));

        $programs->getCollection()->each(
            fn (Program $program) => $this->setCapabilities($program, $canEditDraft, $actor->role === 'tutor'),
        );

        return ProgramResource::collection($programs)->response();
    }

    /** Create a Program Draft for one Customer. */
    #[IgnoreResponse(status: 200)]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor cannot create a Program.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'The source Listing is under moderation hold.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'The request is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    public function store(
        CreateProgramRequest $request,
        CreateProgramDraft $createProgramDraft,
        ProgramPolicy $policy,
    ): JsonResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return $this->error($request, 401, 'unauthenticated', 'Authentication is required.');
        }

        if (! $policy->create($actor)) {
            return $this->error($request, 403, 'tutor_required', 'Only a Tutor can create a Program.');
        }

        try {
            $program = $createProgramDraft->execute(
                $actor,
                $request->validated(),
                (string) $request->attributes->get('request_id'),
            );
        } catch (ProgramActionException $exception) {
            return $this->actionError($request, $exception);
        }

        $this->setCapabilities($program, true, true);

        return (new ProgramResource($program))
            ->response()
            ->setStatusCode(201);
    }

    /** Retrieve a Program visible to the current actor. */
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The account is inactive.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Program not found in the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    public function show(Request $request, int $program): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return $this->error($request, 401, 'unauthenticated', 'Authentication is required.');
        }

        $record = Program::query()
            ->visibleTo($actor)
            ->withActionContext()
            ->find($program);

        if ($record === null) {
            return $this->error($request, 404, 'program_not_found', 'Program not found.');
        }

        $this->setCapabilities($record, $this->canEditDraft($actor), $actor->role === 'tutor');

        return (new ProgramResource($record))->response();
    }

    /** Update an unlocked Draft using optimistic version control. */
    #[OpenApiResponse(status: 200, description: 'Program updated.', type: 'array{data: ProgramResource}')]
    #[OpenApiResponse(status: 401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 403, description: 'The actor cannot update this Program.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 404, description: 'Program not found in the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 409, description: 'The version is stale or the Draft is locked.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(status: 422, description: 'The request is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    public function update(
        UpdateProgramRequest $request,
        int $program,
        UpdateProgramDraft $updateProgramDraft,
        ProgramPolicy $policy,
    ): JsonResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return $this->error($request, 401, 'unauthenticated', 'Authentication is required.');
        }

        $record = Program::query()
            ->visibleTo($actor)
            ->find($program);

        if ($record === null) {
            return $this->error($request, 404, 'program_not_found', 'Program not found.');
        }

        if (! $policy->update($actor, $record)) {
            return $this->error($request, 403, 'program_update_forbidden', 'Only the Tutor owner can edit this Program.');
        }

        try {
            $updated = $updateProgramDraft->execute(
                $actor,
                $program,
                $request->validated(),
                (string) $request->attributes->get('request_id'),
            );
        } catch (ProgramActionException $exception) {
            return $this->actionError($request, $exception);
        }

        $this->setCapabilities($updated, $this->canEditDraft($actor), true);

        return (new ProgramResource($updated))->response();
    }

    /** Pause an active Program. */
    #[OpenApiResponse(200, description: 'Program paused.', type: 'array{data:ProgramResource}')]
    #[OpenApiResponse(404, description: 'Program not found in the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'The Program cannot be paused from its current status.', type: self::ERROR_RESPONSE_TYPE)]
    public function pause(ProgramActionRequest $request, int $program, TransitionProgram $transition): JsonResponse
    {
        return $this->transition($request, $program, 'pause', $transition);
    }

    /** Resume a paused Program. */
    #[OpenApiResponse(200, description: 'Program resumed.', type: 'array{data:ProgramResource}')]
    #[OpenApiResponse(404, description: 'Program not found in the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'The Program cannot be resumed from its current status.', type: self::ERROR_RESPONSE_TYPE)]
    public function resume(ProgramActionRequest $request, int $program, TransitionProgram $transition): JsonResponse
    {
        return $this->transition($request, $program, 'resume', $transition);
    }

    /** Complete a Program after all Sessions and Tasks are terminal. */
    #[OpenApiResponse(200, description: 'Program completed.', type: 'array{data:ProgramResource}')]
    #[OpenApiResponse(404, description: 'Program not found in the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'The Program cannot be completed yet.', type: self::ERROR_RESPONSE_TYPE)]
    public function complete(ProgramActionRequest $request, int $program, TransitionProgram $transition): JsonResponse
    {
        return $this->transition($request, $program, 'complete', $transition);
    }

    /** Cancel a Draft, Active, or Paused Program. */
    #[OpenApiResponse(200, description: 'Program cancelled.', type: 'array{data:ProgramResource}')]
    #[OpenApiResponse(404, description: 'Program not found in the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'The Program cannot be cancelled from its current state or still has blockers.', type: self::ERROR_RESPONSE_TYPE)]
    public function cancel(ProgramActionRequest $request, int $program, TransitionProgram $transition): JsonResponse
    {
        return $this->transition($request, $program, 'cancel', $transition);
    }

    private function canEditDraft(User $actor): bool
    {
        $profile = $actor->role === 'tutor' ? $actor->tutorProfile : null;

        return $profile !== null && app(TutorEligibility::class)->canManagePrograms(
            $actor,
            $profile,
            CarbonImmutable::now('UTC'),
        );
    }

    private function setCapabilities(Program $program, bool $canEdit, bool $isTutorOwner): void
    {
        $program->setAttribute('can_edit_draft', $canEdit);
        $program->setAttribute('is_tutor_owner', $isTutorOwner);
    }

    private function transition(
        ProgramActionRequest $request,
        int $programId,
        string $action,
        TransitionProgram $transition,
    ): JsonResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return $this->error($request, 401, 'unauthenticated', 'Authentication is required.');
        }

        try {
            $record = $transition->execute(
                $actor,
                $programId,
                $action,
                (string) $request->attributes->get('request_id'),
            );
        } catch (ProgramActionException $exception) {
            return $this->actionError($request, $exception);
        }

        $this->setCapabilities($record, $this->canEditDraft($actor), true);

        return (new ProgramResource($record))->response();
    }

    private function actionError(Request $request, ProgramActionException $exception): JsonResponse
    {
        return $this->error(
            $request,
            $exception->httpStatus,
            $exception->errorCode,
            $exception->getMessage(),
            $exception->errors,
        );
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
