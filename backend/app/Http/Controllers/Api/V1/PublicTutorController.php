<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicTutorResource;
use App\Models\TutorProfile;
use Illuminate\Database\Eloquent\Builder;

class PublicTutorController extends Controller
{
    public function show(string $slug): PublicTutorResource
    {
        $profile = TutorProfile::query()
            ->where('public_slug', $slug)
            ->where('status', 'approved')
            ->whereHas(
                'user',
                fn(Builder $user) => $user->where('status', 'active'),
            )
            ->with([
                'user:id,full_name',
                'specializations:id,subcategory_id,name,slug',
                'specializations.subcategory:id,category_id,name,slug',
                'specializations.subcategory.category:id,name,slug',
                'availabilityRules:id,tutor_profile_id,weekday,start_time,end_time,timezone',
            ])
            ->firstOrFail();

        $profile->setRelation(
            'listings',
            $profile->listings()
                ->visiblePublic()
                ->with('specialization.subcategory.category')
                ->latest('id')
                ->get(),
        );

        return new PublicTutorResource($profile);
    }
}