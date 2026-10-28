<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AdminPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class PurgeNotificationData extends Command
{
    protected $signature = 'outbox:purge {--execute} {--limit=500}';

    protected $description = 'Preview or execute audited outbox, notification and audit retention';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);

        if ($limit === false || DB::getDriverName() !== 'pgsql') {
            $this->error('PostgreSQL and a limit between 1 and 1000 are required.');

            return self::INVALID;
        }

        $execute = (bool) $this->option('execute');
        $actor = $execute
            ? User::query()->find((int) config('notification_outbox.purge_actor_id'))
            : null;

        if ($execute && (
            $actor === null || $actor->status !== 'active'
            || ! $actor->hasPermission(AdminPermissions::PURGE_NOTIFICATION_DATA)
            || ! $actor->hasPermission(AdminPermissions::PURGE_AUDIT_LOGS)
        )) {
            $this->error('Configure an active purge operator with both retention permissions.');

            return self::FAILURE;
        }

        try {
            $now = CarbonImmutable::parse(DB::selectOne('SELECT clock_timestamp() AS value')->value);

            foreach (['notification_outbox', 'notifications', 'audit_logs'] as $table) {
                if (! $execute) {
                    $this->line($table.': '.$this->eligible($table, $now)->count().' eligible (preview)');

                    continue;
                }

                $total = 0;

                do {
                    $deleted = DB::transaction(function () use ($table, $now, $limit, $actor): int {
                        $ids = $this->eligible($table, $now)->orderBy('id')->limit($limit)
                            ->lock('FOR UPDATE SKIP LOCKED')->pluck('id')->all();

                        if ($ids === []) {
                            return 0;
                        }

                        $count = DB::table($table)->whereIn('id', $ids)->delete();

                        DB::table('audit_logs')->insert([
                            'actor_id' => $actor->id,
                            'action' => $table === 'audit_logs' ? 'audit.purge' : 'outbox.purge',
                            'resource_type' => 'user',
                            'resource_id' => $actor->id,
                            'before' => json_encode([
                                'target_table' => $table,
                                'record_ids' => $ids,
                            ], JSON_THROW_ON_ERROR),
                            'after' => json_encode([
                                'target_table' => $table,
                                'deleted_count' => $count,
                            ], JSON_THROW_ON_ERROR),
                            'reason' => 'Scheduled retention cleanup under BR-25.',
                        ]);

                        return $count;
                    });

                    $total += $deleted;
                } while ($deleted === $limit);

                $this->line($table.': '.$total.' deleted');
            }
        } catch (Throwable) {
            $this->error('Retention cleanup failed: retention_unavailable.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function eligible(string $table, CarbonImmutable $now): Builder
    {
        $stamp = fn (CarbonImmutable $time): string => $time->format('Y-m-d H:i:s.uP');

        return match ($table) {
            'notification_outbox' => DB::table($table)->where(function ($query) use ($now, $stamp): void {
                $query->where(function ($query) use ($now, $stamp): void {
                    $query->whereNull('failed_at')->whereNotNull('processed_at')
                        ->where('processed_at', '<', $stamp($now->subDays(30)));
                })->orWhere(function ($query) use ($now, $stamp): void {
                    $query->whereNotNull('failed_at')->whereNotNull('resolved_at')
                        ->where('resolved_at', '<', $stamp($now->subDays(90)));
                });
            }),
            'notifications' => DB::table($table)->where('created_at', '<', $stamp($now->subDays(180))),
            'audit_logs' => DB::table($table)->where('created_at', '<', $stamp($now->subMonthsNoOverflow(24))),
        };
    }
}
