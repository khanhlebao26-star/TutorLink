<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\IssueConversationRealtime;
use App\Http\Controllers\Controller;
use App\Http\Requests\IssueConversationRealtimeRequest;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;

class ConversationRealtimeController extends Controller
{
    /**
     * Issue or renew a Conversation channel for the current browser session.
     *
     * Requires session authentication and SPA CSRF protection.
     * The request body must be empty. The returned channel excludes
     * the private- transport prefix.
     */
    #[OpenApiResponse(
        200,
        description: 'Session-scoped realtime channel; never returns the HTTP session ID.',
        type: 'array{data:array{channel:string,event:string,expires_at:string}}',
    )]
    #[OpenApiResponse(
        401,
        description: 'Missing, expired or revoked browser session.',
        type: 'array{code:string,message:string,request_id:string,errors:object}',
    )]
    #[OpenApiResponse(
        403,
        description: 'Inactive account or unsupported participant role.',
        type: 'array{code:string,message:string,request_id:string,errors:object}',
    )]
    #[OpenApiResponse(
        404,
        description: 'Conversation outside current membership scope.',
        type: 'array{code:string,message:string,request_id:string,errors:object}',
    )]
    #[OpenApiResponse(
        422,
        description: 'The request contains unsupported fields.',
        type: 'array{code:string,message:string,request_id:string,errors:object}',
    )]
    public function __invoke(
        IssueConversationRealtimeRequest $request,
        int $conversation,
        IssueConversationRealtime $action,
    ): JsonResponse {
        return response()->json([
            'data' => $action->execute($request, $conversation),
        ], 200, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
