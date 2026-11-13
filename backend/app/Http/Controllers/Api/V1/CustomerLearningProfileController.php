<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerLearningProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'customer', 403);

        return response()->json(['data' => $this->profile($request)->only([
            'subjects_of_interest', 'learning_level', 'learning_goals',
            'preferred_schedule', 'preferred_mode', 'city', 'district',
        ])]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'customer', 403);
        $data = $request->validate([
            'subjects_of_interest' => ['nullable', 'string', 'max:500'],
            'learning_level' => ['nullable', 'string', 'max:120'],
            'learning_goals' => ['nullable', 'string', 'max:2000'],
            'preferred_schedule' => ['nullable', 'string', 'max:500'],
            'preferred_mode' => ['nullable', 'in:online,offline,either'],
            'city' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
        ]);
        $profile = $this->profile($request);
        $profile->fill(array_map(static fn ($value) => $value === '' ? null : $value, $data));
        $profile->save();

        return response()->json(['data' => $profile->only([
            'subjects_of_interest', 'learning_level', 'learning_goals',
            'preferred_schedule', 'preferred_mode', 'city', 'district',
        ])]);
    }

    private function profile(Request $request): CustomerProfile
    {
        return CustomerProfile::firstOrCreate(['user_id' => $request->user()->id]);
    }
}
