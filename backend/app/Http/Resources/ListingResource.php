<?php

namespace App\Http\Resources;

use App\Models\ServiceListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceListing
 */
class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tutor_profile_id' => $this->tutor_profile_id,
            'specialization_id' => $this->specialization_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'delivery_mode' => $this->delivery_mode,
            'city' => $this->city,
            'district' => $this->district,
            'price_from_minor' => $this->price_from_minor,
            'currency' => $this->currency,
            'price_unit' => $this->price_unit,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'allowed_actions' => $this->when(
                $request->is('api/v1/me/listings', 'api/v1/me/listings/*')
                && $request->user() !== null,
                fn () => $this->resource->allowedOwnerActions($request->user()),
            ),
            'tutor_name' => $this->whenLoaded(
                'tutorProfile',
                fn () => $this->tutorProfile->user?->full_name,
            ),
            'tutor_slug' => $this->whenLoaded(
                'tutorProfile',
                fn () => $this->tutorProfile->public_slug,
            ),

            'specialization_name' => $this->whenLoaded(
                'specialization',
                fn () => $this->specialization->name,
            ),

            'subcategory_name' => $this->whenLoaded(
                'specialization',
                fn () => $this->specialization->subcategory?->name,
            ),

            'category_name' => $this->whenLoaded(
                'specialization',
                fn () => $this->specialization
                    ->subcategory
                    ?->category
                    ?->name,
            ),
        ];
    }
}
