<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \stdClass */
class ReadCursorResource extends JsonResource
{
    /** @return array{conversation_id:int,last_read_message_id:int,last_read_at:string|null} */
    public function toArray(Request $request): array
    {
        return [
            'conversation_id' => (int) $this->conversation_id,
            'last_read_message_id' => (int) $this->last_read_message_id,
            'last_read_at' => $this->last_read_at === null ? null
                : CarbonImmutable::parse($this->last_read_at)->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
