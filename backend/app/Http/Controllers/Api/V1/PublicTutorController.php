<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicTutorResource;
use App\Models\TutorProfile;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;

class PublicTutorController extends Controller
{
    /** Public Tutor with a bounded page of Listing cards. */
    #[Response(200, type: 'array{data:array{slug:?string,name:string,headline:string,bio:?string,experience_years:int,specializations:list<array{id:int,name:string,slug:string,subcategory:?string,category:?string}>,availability_rules:list<array{weekday:int,start_time:string,end_time:string,timezone:string}>,avatar:null,listings:list<array{slug:string,title:string,delivery_mode:string,city:?string,district:?string,price_from_minor:int,currency:string,price_unit:string,specialization_name:?string}>,listings_meta:array{page:int,per_page:int,has_more:bool,next_page:?int}}}')]
    public function show(Request $request, string $slug): PublicTutorResource
    {
        $filters = $request->validate(['listings_page' => ['sometimes', 'integer', 'min:1']]);
        $page = (int) ($filters['listings_page'] ?? 1);
        $profile = TutorProfile::query()->select(['id', 'user_id', 'public_slug', 'headline', 'bio', 'experience_years'])
            ->where('public_slug', $slug)
            ->visiblePublic()
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
                ->offset(($page - 1) * 10)->limit(11)
                ->get(),
        );

        $hasMore = $profile->listings->count() > 10;
        $profile->setRelation('listings', $profile->listings->take(10));
        $profile->setAttribute('listings_meta', ['page' => $page, 'per_page' => 10, 'has_more' => $hasMore, 'next_page' => $hasMore ? $page + 1 : null]);

        return new PublicTutorResource($profile);
    }
}
