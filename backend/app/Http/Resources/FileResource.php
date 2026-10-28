<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin File */
class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'purpose' => $this->purpose,
            'scan_status' => $this->scan_status,
            'complete' => $this->isComplete(),
            'antivirus_scanned' => $this->hasScanEvidence(),
            'scanned_at' => $this->hasScanEvidence()
                ? $this->scanned_at?->utc()->toISOString()
                : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
