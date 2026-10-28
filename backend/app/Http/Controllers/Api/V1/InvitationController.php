<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invitations\SendInvitation;
use App\Exceptions\ProgramActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InvitationIndexRequest;
use App\Http\Requests\SendInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\User;
use App\Support\ApiErrorResponse;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    private const ERROR_RESPONSE_TYPE = 'array{code:string,message:string,request_id:string,errors:array<string,mixed>}';

    /** List Invitations owned by or addressed to the current actor. */
    #[OpenApiResponse(200, description: 'A paginated list of scoped Invitations.', type: 'array{data:list<InvitationResource>,links:array<string,mixed>,meta:array<string,mixed>}')]
    #[OpenApiResponse(403, description: 'Only a Tutor or Customer can list Invitations.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'The pagination query is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    public function index(InvitationIndexRequest $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return $this->error($request, 401, 'unauthenticated', 'Authentication is required.');
        }

        if (! in_array($actor->role, ['tutor', 'customer'], true)) {
            return $this->error($request, 403, 'invitation_list_forbidden', 'This account cannot list Invitations.');
        }

        $query = DB::table('program_invitations as invitations')
            ->join('programs', 'programs.id', '=', 'invitations.program_id');

        if ($actor->role === 'tutor') {
            $query->join('tutor_profiles', 'tutor_profiles.id', '=', 'programs.owner_tutor_profile_id')
                ->where('tutor_profiles.user_id', $actor->id);
        } else {
            $query->where('invitations.invitee_user_id', $actor->id);
        }

        $invitations = $query
            ->select('invitations.*')
            ->orderByDesc('invitations.id')
            ->paginate($request->validated('per_page', 20));

        return InvitationResource::collection($invitations)->response();
    }

    /** Send and snapshot an Invitation for a Program Draft. */
    #[IgnoreResponse(status: 200)]
    #[OpenApiResponse(201, description: 'Invitation sent and the Program Draft offer locked.', type: 'array{data:InvitationResource}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'The Tutor is not eligible to send an Invitation.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Program not found in the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'The version is stale, offer is locked, or source Listing is on hold.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'The request or Idempotency-Key is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[HeaderParameter('Idempotency-Key', 'UUID used to replay a successful send request.', required: true, type: 'string', format: 'uuid')]
    public function store(
        SendInvitationRequest $request,
        int $program,
        SendInvitation $sendInvitation,
    ): JsonResponse {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || ! Str::isUuid($idempotencyKey)) {
            return $this->error(
                $request,
                422,
                'invalid_idempotency_key',
                'Idempotency-Key must be a UUID.',
                ['Idempotency-Key' => ['Idempotency-Key must be a UUID.']],
            );
        }

        $actor = $request->user();

        if (! $actor instanceof User) {
            return $this->error($request, 401, 'unauthenticated', 'Authentication is required.');
        }

        try {
            $invitation = $sendInvitation->execute(
                $actor,
                $program,
                $request->validated(),
                $idempotencyKey,
                (string) $request->attributes->get('request_id'),
            );
        } catch (ProgramActionException $exception) {
            return $this->error(
                $request,
                $exception->httpStatus,
                $exception->errorCode,
                $exception->getMessage(),
                $exception->errors,
            );
        }

        return (new InvitationResource((object) $invitation))
            ->response()
            ->setStatusCode(201);
    }

    /** Read one Invitation in the actor's inbox or sent folder. */
    #[OpenApiResponse(200, description: 'Invitation snapshot visible to the current actor.', type: 'array{data:InvitationResource}')]
    #[OpenApiResponse(403, description: 'Only a Tutor or Customer can read Invitations.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Invitation not found in the actor scope.', type: self::ERROR_RESPONSE_TYPE)]
    public function show(Request $request, int $invitation): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return $this->error($request, 401, 'unauthenticated', 'Authentication is required.');
        }

        if (! in_array($actor->role, ['tutor', 'customer'], true)) {
            return $this->error($request, 403, 'invitation_read_forbidden', 'This account cannot read Invitations.');
        }

        $query = DB::table('program_invitations as invitations')
            ->join('programs', 'programs.id', '=', 'invitations.program_id')
            ->where('invitations.id', $invitation);

        if ($actor->role === 'tutor') {
            $query->join('tutor_profiles', 'tutor_profiles.id', '=', 'programs.owner_tutor_profile_id')
                ->where('tutor_profiles.user_id', $actor->id);
        } else {
            $query->where('invitations.invitee_user_id', $actor->id);
        }

        $record = $query->select('invitations.*')->first();

        if ($record === null) {
            return $this->error($request, 404, 'invitation_not_found', 'Invitation not found.');
        }

        return (new InvitationResource($record))->response();
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
