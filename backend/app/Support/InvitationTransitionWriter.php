<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class InvitationTransitionWriter
{
    public function expire(object $invitation, ?int $actorId, string $requestId, CarbonImmutable $now, ?int $tutorUserId = null): void
    {
        $this->write(
            $invitation,
            'expired',
            CarbonImmutable::parse($invitation->expires_at),
            $actorId,
            'invitation.expire',
            'invitation_expired',
            "/programs/{$invitation->program_id}",
            $requestId,
            $now,
            $tutorUserId ?? $actorId ?? throw new \InvalidArgumentException('Expiry requires a Tutor recipient.'),
        );
    }

    public function cancel(object $invitation, int $actorId, string $requestId, CarbonImmutable $now, ?string $reason = null): void
    {
        $this->write(
            $invitation,
            'cancelled',
            $now,
            $actorId,
            'invitation.cancel',
            'invitation_cancelled',
            "/invitations/{$invitation->id}",
            $requestId,
            $now,
            (int) $invitation->invitee_user_id,
            $reason,
        );
    }

    public function reject(object $invitation, int $actorId, int $tutorUserId, string $requestId, CarbonImmutable $now, ?string $reason = null): void
    {
        $this->write(
            $invitation,
            'rejected',
            $now,
            $actorId,
            'invitation.reject',
            'invitation_rejected',
            "/programs/{$invitation->program_id}",
            $requestId,
            $now,
            $tutorUserId,
            $reason,
        );
    }

    private function write(
        object $invitation,
        string $status,
        CarbonImmutable $respondedAt,
        ?int $actorId,
        string $auditAction,
        string $notificationType,
        string $deepLink,
        string $requestId,
        CarbonImmutable $now,
        int $recipientId,
        ?string $reason = null,
    ): void {
        $timestamp = $now->format('Y-m-d H:i:s.uP');
        $respondedAt = $respondedAt->setTimezone('UTC');

        DB::table('program_invitations')
            ->where('id', $invitation->id)
            ->update([
                'status' => $status,
                'responded_at' => $respondedAt->format('Y-m-d H:i:s.uP'),
                'updated_at' => $timestamp,
            ]);

        DB::table('audit_logs')->insert([
            'actor_id' => $actorId,
            'action' => $auditAction,
            'resource_type' => 'program_invitation',
            'resource_id' => $invitation->id,
            'before' => json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR),
            'after' => json_encode([
                'status' => $status,
                'responded_at' => $respondedAt->format('Y-m-d\TH:i:s.u\Z'),
            ], JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'request_id' => $requestId,
            'created_at' => $timestamp,
        ]);

        app(NotificationOutboxWriter::class)->record(
            $notificationType,
            'program_invitation',
            (int) $invitation->id,
            "invitation:{$invitation->id}:{$status}",
            [$recipientId],
            ['deep_link' => $deepLink],
        );
    }
}
