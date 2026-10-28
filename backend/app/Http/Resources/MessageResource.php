<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /** @return array{id:int,conversation_id:int,sender_id:int,client_message_id:string,message_type:string,body:string|null,created_at:string} */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'conversation_id' => (int) $this->conversation_id,
            'sender_id' => (int) $this->sender_id,
            'client_message_id' => $this->client_message_id,
            'message_type' => $this->message_type,
            'body' => $this->body,
            'created_at' => CarbonImmutable::parse($this->created_at)->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
