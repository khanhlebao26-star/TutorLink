<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicTutorResource extends JsonResource
{
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

            'listings' => ListingResource::collection(
                $this->listings,
            ),
        ];
    }
}
