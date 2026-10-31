<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConversationBroadcastAuthRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Broadcast;

class BroadcastAuthController extends Controller
{
    public function __invoke(
        ConversationBroadcastAuthRequest $request,
    ): JsonResponse {
        return response()->json(Broadcast::auth($request));
    }
}
