<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\OpenConversation;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpenConversationRequest;
use App\Http\Resources\ConversationResource;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    private const ERROR_RESPONSE_TYPE = 'array{code:string,message:string,request_id:string,errors:object|array<string,list<string>>}';

    /**
     * Create, reuse or reopen one Customer–Tutor Conversation.
     *
     * Only peer_user_id and optional context_listing_id are accepted. The stored
     * context is preserved on reuse/reopen. Requires a session and the SPA CSRF
     * flow; responses include X-Request-ID.
     */
    #[OpenApiResponse(200, description: 'Existing Conversation reused or reopened; original context preserved.', type: 'array{data:ConversationResource}')]
    #[OpenApiResponse(201, description: 'Conversation created atomically with exactly two members.', type: 'array{data:ConversationResource}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'The actor or Tutor is not eligible to open contact.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(409, description: 'The peer is unavailable, Listing is held, or existing Conversation membership/state conflicts.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Input, peer or Listing context is invalid; unknown fields are rejected.', type: self::ERROR_RESPONSE_TYPE)]
    #[HeaderParameter('X-Request-ID', 'Optional UUID for tracing; generated when omitted or invalid.', required: false, type: 'string', format: 'uuid')]
    public function store(
        OpenConversationRequest $request,
        OpenConversation $action,
    ): JsonResponse {
        $data = $request->validated();

        $result = $action->execute(
            $request->user(),
            (int) $data['peer_user_id'],
            isset($data['context_listing_id'])
            ? (int) $data['context_listing_id']
            : null,
            (string) $request->attributes->get('request_id'),
        );

        return (new ConversationResource(
            $result['conversation'],
            $result['peer'],
        ))
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }
}
