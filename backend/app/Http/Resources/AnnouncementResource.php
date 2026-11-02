<?php

namespace App\Http\Resources;

use App\Models\Announcement;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Announcement $resource */
class AnnouncementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Announcement $announcement */
        $announcement = $this->resource;

        return [
            'id' => (int) $announcement->id,
            'program_id' => (int) $announcement->program_id,
            'author_id' => (int) $announcement->author_id,
            'state' => $announcement->published_at === null ? 'draft' : 'published',
            'title' => $announcement->title,
            'body' => $announcement->body,
            'files' => $announcement->files->map(fn ($file): array => [
                'id' => (int) $file->id,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size_bytes' => (int) $file->size_bytes,
                'complete' => $file->isComplete(),
            ])->values()->all(),
            'published_at' => $this->timestamp($announcement->published_at),
            'version' => (int) $announcement->version,
            'created_at' => $this->timestamp($announcement->created_at),
            'updated_at' => $this->timestamp($announcement->updated_at),
        ];
    }

    private function timestamp(?DateTimeInterface $value): ?string
    {
        return $value === null
            ? null
            : CarbonImmutable::instance($value)->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
