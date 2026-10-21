<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRuleRequest;
use App\Models\TutorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AvailabilityRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $this->profile($request);

        $rules = $profile->availabilityRules()
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->map(fn($rule) => [
                'id' => $rule->id,
                'weekday' => $rule->weekday,
                'start_time' => substr((string) $rule->start_time, 0, 5),
                'end_time' => substr((string) $rule->end_time, 0, 5),
                'timezone' => $rule->timezone,
            ]);

        return response()->json([
            'data' => $rules,
        ]);
    }

    public function replace(
        AvailabilityRuleRequest $request,
    ): JsonResponse {
        $profile = $this->profile($request);
        $rules = array_values($request->validated()['rules']);

        $this->validateNoOverlap($rules);

        DB::transaction(function () use ($profile, $rules): void {
            $profile->availabilityRules()->delete();
            $profile->availabilityRules()->createMany($rules);
        });

        return response()->json([
            'data' => $profile->availabilityRules()
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->get(),
        ]);
    }

    private function profile(Request $request): TutorProfile
    {
        $user = $request->user();

        abort_unless(
            $user
            && $user->role === 'tutor'
            && $user->status === 'active',
            403,
            'Only active tutors can manage availability.',
        );

        $profile = $user->tutorProfile;

        abort_unless($profile, 422, 'Tutor profile not found.');
        abort_if(
            $profile->status === 'suspended',
            403,
            'Suspended tutor profiles cannot manage availability.',
        );

        return $profile;
    }

    private function validateNoOverlap(array $rules): void
    {
        foreach ($rules as $index => $current) {
            if ($current['start_time'] >= $current['end_time']) {
                throw ValidationException::withMessages([
                    "rules.$index.end_time" => [
                        'End time must be after start time.',
                    ],
                ]);
            }

            foreach (array_slice($rules, 0, $index) as $previous) {
                $sameDay = $previous['weekday'] === $current['weekday'];

                $overlap = $current['start_time'] < $previous['end_time']
                    && $current['end_time'] > $previous['start_time'];

                if ($sameDay && $overlap) {
                    throw ValidationException::withMessages([
                        "rules.$index.start_time" => [
                            'Availability overlaps another rule.',
                        ],
                    ]);
                }
            }
        }
    }
}