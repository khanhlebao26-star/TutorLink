<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class MessageResource extends JsonResource
{
    /** @return array{id:int,conversation_id:int,sender_id:int,client_message_id:string,message_type:string,body:string|null,created_at:string} */
    public function toArray(Request $request): array
    {
        $attachments = $this->attachments ?? DB::table('file_links')
            ->join('files', 'files.id', '=', 'file_links.file_id')
            ->where('file_links.resource_type', 'message')
            ->where('file_links.resource_id', $this->id)
            ->where('file_links.purpose', 'message_attachment')
            ->orderBy('files.id')
            ->get(['files.id', 'files.original_name', 'files.mime_type', 'files.size_bytes']);

        return [
            'id' => (int) $this->id,
            'conversation_id' => (int) $this->conversation_id,
            'sender_id' => (int) $this->sender_id,
            'client_message_id' => $this->client_message_id,
            'message_type' => $this->message_type,
            'body' => $this->body,
            'attachments' => collect($attachments)->map(fn ($file): array => [
                'id' => (int) $file->id,
                'name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size_bytes' => (int) $file->size_bytes,
            ])->values()->all(),
            'created_at' => CarbonImmutable::parse($this->created_at)->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
