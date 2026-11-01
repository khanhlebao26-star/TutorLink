<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $session = $this->resource;
        $actual = $session->actual;

        return [
            'id' => (int) $session->id,
            'program_id' => (int) $session->program_id,
            'program_version' => (int) $session->program_version,
            'sequence_number' => (int) $session->sequence_number,
            'title' => $session->title,
            'planned_starts_at' => $this->timestamp(
                $session->planned_starts_at,
            ),
            'planned_ends_at' => $this->timestamp(
                $session->planned_ends_at,
            ),
            'timezone' => $session->timezone,
            'delivery_mode' => $session->delivery_mode,
            'location_text' => $session->location_text,
            'meeting_url' => $session->meeting_url,
            'status' => $session->status,
            'version' => (int) $session->version,
            'created_at' => $this->timestamp($session->created_at),
            'updated_at' => $this->timestamp($session->updated_at),
            'actual' => $actual === null ? null : [
                'id' => (int) $actual->id,
                'status' => $actual->status,
                'started_at' => $this->timestamp($actual->started_at),
                'ended_at' => $this->timestamp($actual->ended_at),
            ],
        ];
    }

    private function timestamp(?DateTimeInterface $value): ?string
    {
        return $value === null
            ? null
            : CarbonImmutable::instance($value)
                ->utc()
                ->format('Y-m-d\TH:i:s.u\Z');
    }
}
