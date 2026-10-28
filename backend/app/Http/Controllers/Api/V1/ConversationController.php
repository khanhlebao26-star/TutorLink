<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\OpenConversation;
use App\Actions\Messaging\ReadConversations;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpenConversationRequest;
use App\Http\Resources\ConversationReadResource;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Support\ApiErrorResponse;
use App\Support\MessagingCursor;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

    /** List the current member's inbox; chats with messages come before empty chats. */
    #[OpenApiResponse(200, description: 'Member-scoped data. GET does not change Conversation or read state.', type: 'array{data:list<ConversationReadResource>,meta:array{per_page:int,has_more:bool,next_cursor:string|null}}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'The account is inactive or the actor role is not allowed.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Query or scoped cursor is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[QueryParameter('per_page', 'Page size: 1–50.', type: 'integer', default: 20)]
    #[QueryParameter('cursor', 'Opaque cursor from this actor inbox.', type: 'string')]
    public function index(Request $request, ReadConversations $read, MessagingCursor $tokens): JsonResponse
    {
        if (! in_array($request->user()->role, ['customer', 'tutor'], true)) {
            throw new HttpResponseException(ApiErrorResponse::make(
                403,
                'conversation_forbidden',
                'This account cannot list Conversations.',
                (string) $request->attributes->get('request_id'),
            ));
        }

        $data = $this->readInput($request, false);
        $scope = 'inbox:'.$request->user()->id;
        $cursor = isset($data['cursor']) ? $tokens->decode(
            $data['cursor'],
            $scope,
            ['has_message', 'activity_at', 'activity_id', 'id'],
            (string) $request->attributes->get('request_id'),
        ) : null;

        $page = DB::query()->fromSub($read->query($request->user()), 'inbox')
            ->orderByDesc('has_message')->orderByDesc('activity_at')
            ->orderByDesc('activity_id')->orderByDesc('id')
            ->cursorPaginate((int) ($data['per_page'] ?? 20), ['*'], '_position', $cursor);

        return response()->json([
            'data' => ConversationReadResource::collection($page->getCollection())->resolve($request),
            'meta' => [
                'per_page' => $page->perPage(),
                'has_more' => $page->hasMorePages(),
                'next_cursor' => $page->hasMorePages() ? $tokens->encode($scope, $page->nextCursor()) : null,
            ],
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    /** Read one Conversation without reopening it or changing read state. */
    #[OpenApiResponse(200, description: 'Member-scoped data. GET does not change Conversation or read state.', type: 'array{data:ConversationReadResource}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'The account is inactive or the actor role is not allowed.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Conversation not found in the actor membership scope.', type: self::ERROR_RESPONSE_TYPE)]
    public function show(Request $request, int $conversation, ReadConversations $read): JsonResponse
    {
        $record = $read->find($request->user(), $conversation, (string) $request->attributes->get('request_id'));

        return (new ConversationReadResource($record))->response()
            ->header('Cache-Control', 'private, no-store');
    }

    /** Read older messages with before or poll newer messages with after. */
    #[OpenApiResponse(200, description: 'Member-scoped data. GET does not change Conversation or read state.', type: 'array{data:list<MessageResource>,meta:array{per_page:int,has_more:bool,next_cursor:string|null,polling_cursor:string|null}}')]
    #[OpenApiResponse(401, description: 'Authentication is required.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(403, description: 'The account is inactive or the actor role is not allowed.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(404, description: 'Conversation not found in the actor membership scope.', type: self::ERROR_RESPONSE_TYPE)]
    #[OpenApiResponse(422, description: 'Query or scoped cursor is invalid.', type: self::ERROR_RESPONSE_TYPE)]
    #[QueryParameter('per_page', 'Page size: 1–50.', type: 'integer', default: 20)]
    #[QueryParameter('before', 'Older history, descending (created_at,id). Cannot combine with after.', type: 'string')]
    #[QueryParameter('after', 'Newer messages, ascending (created_at,id). Cannot combine with before.', type: 'string')]
    public function messages(Request $request, int $conversation, ReadConversations $read, MessagingCursor $tokens): JsonResponse
    {
        $requestId = (string) $request->attributes->get('request_id');
        $read->find($request->user(), $conversation, $requestId);
        $data = $this->readInput($request, true);
        $after = array_key_exists('after', $data);
        $before = array_key_exists('before', $data);
        $scope = 'messages:'.$conversation;
        $token = $data['after'] ?? $data['before'] ?? null;
        $cursor = $token === null ? null : $tokens->decode($token, $scope, ['created_at', 'id'], $requestId);

        if ($before && $cursor === null) {
            $tokens->invalid($requestId);
        }

        $direction = $after ? 'asc' : 'desc';
        $page = DB::table('messages')->where('conversation_id', $conversation)
            ->orderBy('created_at', $direction)->orderBy('id', $direction)
            ->cursorPaginate((int) ($data['per_page'] ?? 20), [
                'id',
                'conversation_id',
                'sender_id',
                'client_message_id',
                'message_type',
                'body',
                'created_at',
            ], '_position', $cursor);

        $items = $page->getCollection();
        $pollingCursor = null;

        if (! $before) {
            $latest = $after ? $items->last() : $items->first();
            $pollingCursor = $latest === null
                ? ($after ? $token : $tokens->encode($scope, null))
                : $tokens->encode($scope, $page->getCursorForItem($latest));
        }

        return response()->json([
            'data' => MessageResource::collection($items)->resolve($request),
            'meta' => [
                'per_page' => $page->perPage(),
                'has_more' => $page->hasMorePages(),
                'next_cursor' => $page->hasMorePages() ? $tokens->encode($scope, $page->nextCursor()) : null,
                'polling_cursor' => $pollingCursor,
            ],
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    /** @return array<string, mixed> */
    private function readInput(Request $request, bool $messages): array
    {
        $rules = ['per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:50']];
        $rules += $messages ? [
            'before' => ['sometimes', 'required', 'string', 'max:4096', 'prohibits:after'],
            'after' => ['sometimes', 'required', 'string', 'max:4096', 'prohibits:before'],
        ] : ['cursor' => ['sometimes', 'required', 'string', 'max:4096']];

        $input = $request->query->all();
        $validator = Validator::make($input, $rules);
        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($input, $rules): void {
            foreach (array_diff(array_keys($input), array_keys($rules)) as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }
        });

        if ($validator->fails()) {
            throw new HttpResponseException(ApiErrorResponse::make(
                422,
                'validation_failed',
                'The given data was invalid.',
                (string) $request->attributes->get('request_id'),
                $validator->errors()->messages(),
            ));
        }

        return $validator->validated();
    }
}
