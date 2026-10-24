<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePersonalAccessTokenRequest;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersonalAccessTokenController extends Controller
{
    #[Endpoint(title: 'Create a personal access token')]
    public function store(
        CreatePersonalAccessTokenRequest $request,
    ): JsonResponse {
        $data = $request->validated();
        $expiresAt = now()->addDays($data['expires_in_days'] ?? 30);

        $newToken = $request->user()->createToken(
            $data['name'],
            $data['abilities'],
            $expiresAt,
        );

        $token = $newToken->accessToken;

        return response()->json([
            'data' => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
            ],
            'token' => $newToken->plainTextToken,
        ], 201);
    }

    #[Endpoint(title: 'List personal access token metadata')]
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->latest('id')
            ->get()
            ->map(fn ($token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
            ])
            ->values();

        return response()->json(['data' => $tokens]);
    }

    #[Endpoint(title: 'Revoke a personal access token')]
    public function destroy(Request $request, int $tokenId): Response
    {
        $request->user()->tokens()
            ->whereKey($tokenId)
            ->firstOrFail()
            ->delete();

        return response()->noContent();
    }
}
