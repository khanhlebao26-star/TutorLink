<?php

namespace App\Http\Resources;

use App\Models\FeeRecord;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read FeeRecord $resource */
class FeeRecordResource extends JsonResource
{
    private const DISCLAIMER = 'Do Tutor khai báo, không phải bằng chứng thanh toán.';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var FeeRecord $fee */
        $fee = $this->resource;

        return [
            'id' => (int) $fee->id,
            'program_id' => (int) $fee->program_id,
            'period' => substr((string) $fee->period_start, 0, 7),
            'period_start' => (string) $fee->period_start,
            'period_end' => (string) $fee->period_end,
            'amount_minor' => (int) $fee->amount_minor,
            'currency' => $fee->currency,
            'status' => $fee->status,
            'tutor_payment_mark' => $fee->tutor_payment_mark,
            'version' => (int) $fee->version,
            'payment_marked_at' => $this->timestamp($fee->payment_marked_at),
            'payment_mark_reason' => $fee->payment_mark_reason,
            'voided_at' => $this->timestamp($fee->voided_at),
            'void_reason' => $fee->void_reason,
            'last_change_reason' => $fee->last_change_reason,
            'disclaimer' => self::DISCLAIMER,
            'created_at' => $this->timestamp($fee->created_at),
            'updated_at' => $this->timestamp($fee->updated_at),
        ];
    }

    private function timestamp(?DateTimeInterface $value): ?string
    {
        return $value === null
            ? null
            : CarbonImmutable::instance($value)->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
