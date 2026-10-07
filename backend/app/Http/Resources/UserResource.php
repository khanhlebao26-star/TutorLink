<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'profile' => $this->when(
                $this->relationLoaded('customerProfile') || $this->relationLoaded('tutorProfile'),
                fn () => $this->tutorProfile ?? $this->customerProfile,
            ),
        ];
    }
}
