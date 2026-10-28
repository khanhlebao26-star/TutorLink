<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invitations\AcceptInvitation;
use App\Exceptions\InvitationAcceptException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvitationAcceptController extends Controller
{
    #[OpenApiResponse(
        200,
        description: 'Invitation accepted; the response contains allow-listed Program and Invitation data.',
        type: 'array{data:array{program:array{id:int,owner_tutor_profile_id:int,customer_id:int,source_listing_id:?int,status:string,version:int,title:string,goal:?string,level:?string,delivery_mode:string,agreed_fee_minor:int,currency:string,fee_unit:string,location_text:?string,meeting_url:?string,schedule_note:?string,timezone:string,planned_session_count:?int,activated_at:string,allowed_actions:list<string>},invitation:array{id:int,program_id:int,customer_id:int,status:string,program_version:int,offer_snapshot:array<string,mixed>,sent_at:string,expires_at:string,responded_at:string,accepted_at:string}}}',
    )]
    #[OpenApiResponse(
        401,
        description: 'Authentication is required.',
        type: 'array{code:string,message:string,request_id:string,errors:object}',
    )]
    #[OpenApiResponse(
        403,
        description: 'The signed-in account is inactive.',
        type: 'array{code:string,message:string,request_id:string,errors:object}',
    )]
    #[OpenApiResponse(
        404,
        description: 'The Invitation is not visible to this Customer.',
        type: 'array{code:string,message:string,request_id:string,errors:object|array<string,list<string>>}',
    )]
    #[OpenApiResponse(
        409,
        description: 'A current business precondition, snapshot, expiry, hold, or idempotency check failed.',
        type: 'array{code:string,message:string,request_id:string,errors:object|array<string,list<string>>}',
    )]
    #[OpenApiResponse(
        422,
        description: 'The request body or Idempotency-Key is invalid.',
        type: 'array{code:string,message:string,request_id:string,errors:object|array<string,list<string>>}',
    )]
    #[HeaderParameter(
        'Idempotency-Key',
        'UUID used to safely replay a successful Accept request.',
        required: true,
        type: 'string',
        format: 'uuid',
    )]
    #[HeaderParameter(
        'X-Request-ID',
        'Optional UUID for request tracing; the server generates one when omitted or invalid.',
        required: false,
        type: 'string',
        format: 'uuid',
    )]
    public function __invoke(
        Request $request,
        int $invitation,
        AcceptInvitation $acceptInvitation,
    ): JsonResponse {
        $requestId = (string) $request->attributes->get('request_id');

        $rawBody = trim($request->getContent());
        $decodedBody = $rawBody === '' ? null : json_decode($rawBody);
        $isEmptyBody = $rawBody === ''
            || ($decodedBody instanceof \stdClass && get_object_vars($decodedBody) === []);

        if (! $isEmptyBody) {
            return $this->errorResponse(
                422,
                'invalid_request_body',
                'Accept does not accept a request body.',
                $requestId,
                ['body' => ['Accept does not accept a request body.']],
            );
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || ! Str::isUuid($idempotencyKey)) {
            return $this->errorResponse(
                422,
                'invalid_idempotency_key',
                'Idempotency-Key must be a UUID.',
                $requestId,
                ['Idempotency-Key' => ['Idempotency-Key must be a UUID.']],
            );
        }

        $actor = $request->user();

        if (! $actor instanceof User) {
            return $this->errorResponse(
                401,
                'unauthenticated',
                'Authentication is required.',
                $requestId,
            );
        }

        try {
            $data = $acceptInvitation->execute(
                $actor,
                $invitation,
                $idempotencyKey,
                $requestId,
            );
        } catch (InvitationAcceptException $exception) {
            return $this->errorResponse(
                $exception->httpStatus,
                $exception->errorCode,
                $exception->getMessage(),
                $requestId,
                $exception->errors,
            );
        }

        return response()->json(['data' => $data], 200, ['X-Request-ID' => $requestId]);
    }

    /** @param array<string, list<string>> $errors */
    private function errorResponse(
        int $status,
        string $code,
        string $message,
        string $requestId,
        array $errors = [],
    ): JsonResponse {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'request_id' => $requestId,
            'errors' => $errors === [] ? new \stdClass : $errors,
        ], $status, ['X-Request-ID' => $requestId]);
    }
}
