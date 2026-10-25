<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class NotificationOutboxWriter
{

    private const TYPES = [
        'listing_moderation_hidden',
        'listing_moderation_restored',
        'tutor_profile_submitted',
        'tutor_profile_approved',
        'tutor_profile_changes_requested',
        'tutor_profile_rejected',
        'tutor_profile_suspended',
        'tutor_profile_restored',
        'invitation_received',
        'invitation_accepted',
        'invitation_rejected',
        'invitation_cancelled',
        'invitation_expired',
        'task_assigned',
        'task_submitted',
        'task_feedback_received',
        'task_revision_requested',
        'announcement_published',
        'fee_record_changed',
        'program_paused',
        'program_resumed',
        'program_completed',
        'program_cancelled',
        'report_resolved',
        'report_dismissed',
    ];
    public function record(
        string $type,
        string $resourceType,
        int $resourceId,
        string $eventKey,
        array $recipientIds,
        array $payload,
    ): void {
        if (DB::connection()->transactionLevel() === 0) {
            throw new LogicException(
                'Outbox event must be recorded inside a transaction.'
            );
        }

        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported notification type.');
        }

        if (
            $resourceType === ''
            || strlen($resourceType) > 40
            || preg_match('/^[a-z][a-z0-9_]*$/', $resourceType) !== 1
            || $resourceId <= 0
        ) {
            throw new InvalidArgumentException('Invalid notification resource.');
        }

        if (
            $eventKey === ''
            || strlen($eventKey) > 191
            || preg_match('/[\x00-\x1F\x7F]/', $eventKey) === 1
        ) {
            throw new InvalidArgumentException('Invalid event key.');
        }

        $uniqueRecipientIds = [];

        foreach ($recipientIds as $recipientId) {
            if (!is_int($recipientId) || $recipientId <= 0) {
                throw new InvalidArgumentException('Invalid recipient ID.');
            }

            $uniqueRecipientIds[$recipientId] = true;
        }

        $allowedKeys = match ($type) {
            'listing_moderation_hidden' => [
                'deep_link',
                'visibility',
                'shareable_reason_code',
                'support_hint',
            ],
            'listing_moderation_restored' => ['deep_link', 'result_status'],
            'fee_record_changed' => ['deep_link', 'period'],
            default => ['deep_link'],
        };

        if (array_diff(array_keys($payload), $allowedKeys) !== []) {
            throw new InvalidArgumentException('Unsupported notification payload field.');
        }

        $deepLink = $payload['deep_link'] ?? null;

        if (
            !is_string($deepLink)
            || !str_starts_with($deepLink, '/')
            || str_starts_with($deepLink, '//')
            || str_contains($deepLink, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $deepLink) === 1
        ) {
            throw new InvalidArgumentException('Invalid notification deep link.');
        }

        $ownerProfileTypes = [
            'tutor_profile_approved',
            'tutor_profile_changes_requested',
            'tutor_profile_rejected',
            'tutor_profile_suspended',
            'tutor_profile_restored',
        ];

        if (in_array($type, $ownerProfileTypes, true) && $deepLink !== '/profile') {
            throw new InvalidArgumentException('Invalid tutor profile deep link.');
        }

        if (
            $type === 'listing_moderation_restored'
            && !in_array($payload['result_status'] ?? null, ['active', 'inactive'], true)
        ) {
            throw new InvalidArgumentException('Invalid listing result status.');
        }

        if (
            $type === 'fee_record_changed'
            && (
                !is_string($payload['period'] ?? null)
                || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $payload['period']) !== 1
            )
        ) {
            throw new InvalidArgumentException('Invalid fee period.');
        }

        ksort($payload);

        foreach (array_keys($uniqueRecipientIds) as $recipientId) {
            $inserted = DB::table('notification_outbox')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'user_id' => $recipientId,
                'type' => $type,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'event_key' => $eventKey,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);

            if ($inserted === 1) {
                continue;
            }

            $existing = DB::table('notification_outbox')
                ->where('user_id', $recipientId)
                ->where('type', $type)
                ->where('event_key', $eventKey)
                ->first();

            if ($existing === null) {
                throw new LogicException('Outbox insert failed without a matching event.');
            }

            $existingPayload = json_decode(
                $existing->payload,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($existingPayload)) {
                throw new LogicException('Existing outbox payload is invalid.');
            }

            ksort($existingPayload);

            if (
                $existing->resource_type !== $resourceType
                || (int) $existing->resource_id !== $resourceId
                || $existingPayload !== $payload
            ) {
                throw new LogicException('Event key was reused with different data.');
            }
        }
    }
}