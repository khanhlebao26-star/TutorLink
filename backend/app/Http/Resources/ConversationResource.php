<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function __construct(
        Conversation $resource,
        private readonly User $peer,
    ) {
        parent::__construct($resource);
    }

    /** @return array{id: int, status: string, peer: array{id: int, display_name: string}, context_listing_id: int|null} */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'status' => $this->resource->status,
            'peer' => [
                'id' => (int) $this->peer->id,
                'display_name' => $this->peer->full_name,
            ],
            'context_listing_id' => $this->resource->context_listing_id === null
                ? null
                : (int) $this->resource->context_listing_id,
        ];
    }
}
