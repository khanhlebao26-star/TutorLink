<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \stdClass */
class ConversationReadResource extends JsonResource
{
    /** @return array{id:int,status:string,context_listing_id:int|null,peer:array{id:int,display_name:string},unread_count:int,last_read_message_id:int|null,preview:array{id:int,message_type:string,text:string,created_at:string}|null} */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'status' => $this->status,
            'context_listing_id' => $this->context_listing_id === null ? null : (int) $this->context_listing_id,
            'peer' => ['id' => (int) $this->peer_id, 'display_name' => $this->peer_name],
            'unread_count' => (int) $this->unread_count,
            'last_read_message_id' => $this->last_read_message_id === null ? null : (int) $this->last_read_message_id,
            'preview' => $this->latest_message_id === null ? null : [
                'id' => (int) $this->latest_message_id,
                'message_type' => $this->latest_message_type,
                'text' => $this->latest_message_type === 'file'
                    ? 'Tệp đính kèm'
                    : mb_substr((string) $this->latest_body, 0, 120),
                'created_at' => CarbonImmutable::parse($this->latest_message_at)->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ],
        ];
    }
}
