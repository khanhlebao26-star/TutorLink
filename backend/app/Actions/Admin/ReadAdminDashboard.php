<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions as Permissions;
use App\Support\TutorProfileWorkflow;
use Illuminate\Support\Facades\DB;

class ReadAdminDashboard
{
    /** @return array{resources:array<string,mixed>,permissions:list<string>} */
    public function execute(User $actor): array
    {
        return DB::transaction(function () use ($actor): array {
            $actor = User::whereKey($actor->id)->sharedLock()->first();
            $authorization = app(AdminAuthorization::class);
            app(TutorProfileWorkflow::class)->ensure($actor !== null && $authorization->allows($actor, Permissions::VIEW_DASHBOARD), 403, 'permission_denied', 'Permission denied.');
            $resources = [];
            if ($authorization->allows($actor, Permissions::VIEW_TUTOR_PROFILES)) {
                $resources['tutor_profiles']['mine'] = $this->counts('tutor_profiles', 'status', ['assigned_admin_id' => $actor->id]);
            }
            if ($authorization->allows($actor, Permissions::ASSIGN_TUTOR_PROFILES)) {
                $resources['tutor_profiles']['unassigned'] = $this->counts('tutor_profiles', 'status', ['assigned_admin_id' => null]);
            }
            if ($authorization->allows($actor, Permissions::VIEW_REPORTS)) {
                $resources['reports']['mine'] = $this->counts('reports', 'status', ['assigned_admin_id' => $actor->id]);
            }
            if ($authorization->allows($actor, Permissions::ASSIGN_REPORTS)) {
                $resources['reports']['unassigned'] = $this->counts('reports', 'status', ['assigned_admin_id' => null]);
            }
            if ($authorization->allows($actor, Permissions::MODERATE_REVIEWS)) {
                $resources['reviews'] = $this->counts('reviews', 'visibility');
            }
            if ($authorization->allows($actor, Permissions::VIEW_NOTIFICATION_OUTBOX)) {
                $resources['notification_outbox'] = [
                    'pending' => DB::table('notification_outbox')->whereNull('processed_at')->whereNull('resolved_at')->where(fn ($query) => $query->whereNull('failed_at')->orWhereColumn('replay_requested_at', '>', 'failed_at'))->count(),
                    'failed' => DB::table('notification_outbox')->whereNull('processed_at')->whereNull('resolved_at')->whereNotNull('failed_at')->where(fn ($query) => $query->whereNull('replay_requested_at')->orWhereColumn('replay_requested_at', '<=', 'failed_at'))->count(),
                ];
            }

            return ['resources' => $resources, 'permissions' => array_values(array_filter(Permissions::all(), fn ($permission) => $authorization->allows($actor, $permission)))];
        });
    }

    /** @param array<string,int|null> $scope
     * @return array<string,int> */
    private function counts(string $table, string $column, array $scope = []): array
    {
        return DB::table($table)->where($scope)->selectRaw($column.', count(*) as total')->groupBy($column)
            ->pluck('total', $column)->map(fn ($total) => (int) $total)->all();
    }
}
