<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TutorProfileRequest;
use App\Http\Resources\TutorProfileResource;
use App\Models\TutorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorProfileController extends Controller
{
    public function show(Request $request): TutorProfileResource|JsonResponse
    {
        $profile = $request->user()->tutorProfile()->first();

        if (! $profile) {
            return response()->json(['message' => 'Tutor profile not found.'], 404);
        }

        return new TutorProfileResource($profile);
    }

    public function store(TutorProfileRequest $request): TutorProfileResource|JsonResponse
    {
        $profile = $request->user()->tutorProfile()->first();

        if ($profile?->submitted_at) {
            return response()->json(['message' => 'Submitted profile cannot be edited.'], 409);
        }

        $profile ??= new TutorProfile(['user_id' => $request->user()->id]);
        $profile->fill($request->validated());
        $profile->approval_status = 'pending';
        $profile->save();

        return new TutorProfileResource($profile);
    }

    public function submit(Request $request): TutorProfileResource|JsonResponse
    {
        $profile = $request->user()->tutorProfile()->first();

        if (! $profile) {
            return response()->json(['message' => 'Create a draft profile first.'], 422);
        }

        if ($profile->submitted_at) {
            return new TutorProfileResource($profile);
        }

        $profile->forceFill(['submitted_at' => now(), 'approval_status' => 'pending'])->save();

        return new TutorProfileResource($profile->refresh());
    }
}
