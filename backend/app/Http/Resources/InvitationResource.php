<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * @mixin \stdClass
 *
 * @property int|string $id
 * @property int|string $program_id
 * @property int|string $invitee_user_id
 * @property string $status
 * @property int|string $program_version
 * @property string $created_at
 * @property string $expires_at
 * @property string|null $responded_at
 * @property string|null $accepted_at
 */
class InvitationResource extends JsonResource
{
    private const OFFER_FIELDS = [
        'title',
        'goal',
        'level',
        'delivery_mode',
        'agreed_fee_minor',
        'currency',
        'fee_unit',
        'location_text',
        'meeting_url',
        'schedule_note',
        'timezone',
        'planned_session_count',
    ];

    private const SOURCE_LISTING_FIELDS = [
        'id',
        'title',
        'description',
        'delivery_mode',
        'city',
        'district',
        'price_from_minor',
        'currency',
        'price_unit',
    ];

    /**
     * @return array{
     *     id: int,
     *     program_id: int,
     *     customer_id: int,
     *     status: string,
     *     program_version: int,
     *     offer_snapshot: array{
     *         program_id: int,
     *         target_customer_id: int,
     *         source_listing_id: int|null,
     *         offer: array{
     *             title: string,
     *             goal: ?string,
     *             level: ?string,
     *             delivery_mode: string,
     *             agreed_fee_minor: int,
     *             currency: string,
     *             fee_unit: string,
     *             location_text: ?string,
     *             meeting_url: ?string,
     *             schedule_note: ?string,
     *             timezone: string,
     *             planned_session_count: ?int
     *         },
     *         source_listing: array{id: int, title: string, description: string, delivery_mode: string, city: ?string, district: ?string, price_from_minor: int, currency: string, price_unit: string}|null
     *     },
     *     sent_at: string,
     *     expires_at: string,
     *     responded_at: ?string,
     *     accepted_at: ?string
     * }
     */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource;
        $expiresAt = CarbonImmutable::parse($invitation->expires_at);
        $status = $invitation->status === 'pending' && $expiresAt->lte(now('UTC'))
            ? 'expired'
            : $invitation->status;
        $snapshot = is_array($invitation->offer_snapshot)
            ? $invitation->offer_snapshot
            : json_decode($invitation->offer_snapshot, true, 512, JSON_THROW_ON_ERROR);

        if (
            ! is_array($snapshot)
            || ! is_array($snapshot['offer'] ?? null)
            || ! isset($snapshot['program_id'], $snapshot['target_customer_id'])
            || ! array_key_exists('source_listing_id', $snapshot)
        ) {
            throw new LogicException('Stored Invitation snapshot is invalid.');
        }

        foreach (self::OFFER_FIELDS as $field) {
            if (! array_key_exists($field, $snapshot['offer'])) {
                throw new LogicException('Stored Invitation offer is incomplete.');
            }
        }

        $sourceListing = $snapshot['source_listing'] ?? null;

        if ($snapshot['source_listing_id'] !== null && ! is_array($sourceListing)) {
            throw new LogicException('Stored Invitation source Listing context is incomplete.');
        }

        if (is_array($sourceListing)) {
            foreach (self::SOURCE_LISTING_FIELDS as $field) {
                if (! array_key_exists($field, $sourceListing)) {
                    throw new LogicException('Stored Invitation source Listing context is incomplete.');
                }
            }
        }

        $sourceListingId = $snapshot['source_listing_id'] === null
            ? null
            : (int) $snapshot['source_listing_id'];

        return [
            'id' => (int) $invitation->id,
            'program_id' => (int) $invitation->program_id,
            'customer_id' => (int) $invitation->invitee_user_id,
            'status' => $status,
            'program_version' => (int) $invitation->program_version,
            'offer_snapshot' => [
                'program_id' => (int) $snapshot['program_id'],
                'target_customer_id' => (int) $snapshot['target_customer_id'],
                'source_listing_id' => $sourceListingId,
                'offer' => [
                    'title' => $snapshot['offer']['title'],
                    'goal' => $this->nullableString($snapshot['offer']['goal']),
                    'level' => $this->nullableString($snapshot['offer']['level']),
                    'delivery_mode' => $snapshot['offer']['delivery_mode'],
                    'agreed_fee_minor' => (int) $snapshot['offer']['agreed_fee_minor'],
                    'currency' => $snapshot['offer']['currency'],
                    'fee_unit' => $snapshot['offer']['fee_unit'],
                    'location_text' => $this->nullableString($snapshot['offer']['location_text']),
                    'meeting_url' => $this->nullableString($snapshot['offer']['meeting_url']),
                    'schedule_note' => $this->nullableString($snapshot['offer']['schedule_note']),
                    'timezone' => $snapshot['offer']['timezone'],
                    'planned_session_count' => $snapshot['offer']['planned_session_count'] === null
                        ? null
                        : (int) $snapshot['offer']['planned_session_count'],
                ],
                'source_listing' => is_array($sourceListing) ? [
                    'id' => (int) $sourceListing['id'],
                    'title' => $sourceListing['title'],
                    'description' => $sourceListing['description'],
                    'delivery_mode' => $sourceListing['delivery_mode'],
                    'city' => $this->nullableString($sourceListing['city']),
                    'district' => $this->nullableString($sourceListing['district']),
                    'price_from_minor' => (int) $sourceListing['price_from_minor'],
                    'currency' => $sourceListing['currency'],
                    'price_unit' => $sourceListing['price_unit'],
                ] : null,
            ],
            'sent_at' => $this->isoTimestamp($invitation->created_at),
            'expires_at' => $this->isoTimestamp($invitation->expires_at),
            'responded_at' => $this->nullableIsoTimestamp($invitation->responded_at),
            'accepted_at' => $this->nullableIsoTimestamp($invitation->accepted_at),
        ];
    }

    private function isoTimestamp(string $value): string
    {
        return CarbonImmutable::parse($value)->setTimezone('UTC')->format('Y-m-d\TH:i:s.u\Z');
    }

    private function nullableIsoTimestamp(?string $value): ?string
    {
        return $value === null ? null : $this->isoTimestamp($value);
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
