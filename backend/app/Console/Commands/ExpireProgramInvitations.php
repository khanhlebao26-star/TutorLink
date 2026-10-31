<?php

namespace App\Console\Commands;

use App\Actions\Invitations\ResolveInvitation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ExpireProgramInvitations extends Command
{
    protected $signature = 'invitations:expire';

    protected $description = 'Expire overdue pending Invitations atomically with audit, outbox and chat lifecycle';

    public function handle(ResolveInvitation $resolve): int
    {
        $expired = 0;
        $failed = 0;
        $requestId = (string) Str::uuid();

        DB::table('program_invitations')->where('status', 'pending')
            ->where('expires_at', '<=', now('UTC'))
            ->chunkById(100, function ($invitations) use ($resolve, $requestId, &$expired, &$failed): void {
                foreach ($invitations as $invitation) {
                    try {
                        $expired += (int) $resolve->expire((int) $invitation->id, $requestId);
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                        $this->error("Invitation {$invitation->id}: expiry failed; it will be retried on the next run.");
                    }
                }
            });

        $this->info("Expired {$expired} Invitation(s); {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
