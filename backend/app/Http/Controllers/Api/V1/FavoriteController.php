<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TutorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function index(): JsonResponse
    {
        $favorites = TutorProfile::query()
            ->visiblePublic()
            ->join('favorites', 'favorites.tutor_profile_id', '=', 'tutor_profiles.id')
            ->join('users', 'users.id', '=', 'tutor_profiles.user_id')
            ->where('favorites.user_id', auth()->id())
            ->orderByDesc('favorites.created_at')
            ->get([
                'tutor_profiles.public_slug as slug',
                'users.full_name as name',
                'tutor_profiles.headline',
                'favorites.created_at',
            ]);

        return response()->json(['data' => $favorites], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function store(string $slug): JsonResponse
    {
        $profile = TutorProfile::query()
            ->visiblePublic()
            ->where('public_slug', $slug)
            ->firstOrFail();

        DB::table('favorites')->insertOrIgnore([
            'user_id' => auth()->id(),
            'tutor_profile_id' => $profile->id,
            'created_at' => now('UTC'),
        ]);

        return response()->json(['data' => ['slug' => $slug, 'is_favorite' => true]], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function destroy(string $slug): JsonResponse
    {
        $profile = TutorProfile::query()
            ->where('public_slug', $slug)
            ->firstOrFail();

        DB::table('favorites')
            ->where('user_id', auth()->id())
            ->where('tutor_profile_id', $profile->id)
            ->delete();

        return response()->json(['data' => ['slug' => $slug, 'is_favorite' => false]], 200, ['Cache-Control' => 'private, no-store']);
    }
}
