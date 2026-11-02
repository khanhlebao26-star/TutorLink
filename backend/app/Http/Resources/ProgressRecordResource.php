<?php

namespace App\Http\Resources;

use App\Models\ProgressRecord;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read ProgressRecord $resource */
class ProgressRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ProgressRecord $record */
        $record = $this->resource;

        return [
            'id' => (int) $record->id,
            'program_id' => (int) $record->program_id,
            'customer_id' => (int) $record->customer_id,
            'metric_name' => $record->metric_name,
            'value' => (int) $record->value,
            'note' => $record->note,
            'recorded_by' => (int) $record->recorded_by,
            'recorded_at' => $this->timestamp($record->recorded_at),
            'version' => (int) $record->version,
            'created_at' => $this->timestamp($record->created_at),
            'updated_at' => $this->timestamp($record->updated_at),
        ];
    }

    private function timestamp(?DateTimeInterface $value): ?string
    {
        return $value === null
            ? null
            : CarbonImmutable::instance($value)->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
