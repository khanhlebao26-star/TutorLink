<?php

namespace App\Http\Resources;

use App\Models\TutorProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TutorProfile
 */
class PublicTutorResource extends JsonResource
{
    /**
     * @return array{slug:?string,name:string,headline:string,bio:?string,experience_years:int,specializations:list<array{id:int,name:string,slug:string,subcategory:?string,category:?string}>,availability_rules:list<array{weekday:int,start_time:string,end_time:string,timezone:string}>,avatar:null,listings:list<array{slug:string,title:string,delivery_mode:string,city:?string,district:?string,price_from_minor:int,currency:string,price_unit:string,specialization_name:?string}>,listings_meta:array{page:int,per_page:int,has_more:bool,next_page:?int}}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->public_slug,
            'name' => $this->user?->full_name,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'experience_years' => $this->experience_years,

            'specializations' => $this->specializations
                ->map(fn ($specialization) => [
                    'id' => $specialization->id,
                    'name' => $specialization->name,
                    'slug' => $specialization->slug,
                    'subcategory' => $specialization->subcategory?->name,
                    'category' => $specialization->subcategory?->category?->name,
                ])
                ->values(),

            'availability_rules' => $this->availabilityRules
                ->map(fn ($rule) => [
                    'weekday' => $rule->weekday,
                    'start_time' => substr((string) $rule->start_time, 0, 5),
                    'end_time' => substr((string) $rule->end_time, 0, 5),
                    'timezone' => $rule->timezone,
                ])
                ->values(),

            'avatar' => null,
            'listings' => $this->listings->map(fn ($listing) => [
                'slug' => $listing->slug,
                'title' => $listing->title,
                'delivery_mode' => $listing->delivery_mode,
                'city' => $listing->city,
                'district' => $listing->district,
                'price_from_minor' => $listing->price_from_minor,
                'currency' => $listing->currency,
                'price_unit' => $listing->price_unit,
                'specialization_name' => $listing->specialization?->name,
            ])->values(),
            'listings_meta' => $this->listings_meta,
        ];
    }
}
