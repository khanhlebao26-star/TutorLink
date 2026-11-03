<?php

namespace App\Actions\Reports;

use App\Models\User;
use App\Policies\ReportPolicy;
use App\Support\TutorProfileWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssignReport
{
    /** @param array<string,mixed> $data
     * @return array{id:int,assigned_admin_id:?int,assignment_version:int} */
    public function execute(User $actor, int $reportId, array $data): array
    {
        $data = Validator::make($data, ['assigned_admin_id' => 'present|nullable|integer:strict|min:1', 'assignment_version' => 'required|integer:strict|min:0', 'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u']])->validate();

        return DB::transaction(function () use ($actor, $reportId, $data): array {
            $flow = app(TutorProfileWorkflow::class);
            $policy = app(ReportPolicy::class);
            $users = User::whereIn('id', array_filter([$actor->id, $data['assigned_admin_id']]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($actor->id);
            $flow->ensure($actor !== null && $policy->assign($actor), 403, 'permission_denied', 'Permission denied.');
            $target = $data['assigned_admin_id'] === null ? null : $users->get($data['assigned_admin_id']);
            $flow->ensure($data['assigned_admin_id'] === null || ($target !== null && $policy->eligibleAssignee($target)), 422, 'invalid_assigned_admin', 'Assigned Admin must be active with Report view and decide permissions.');
            $row = DB::table('reports')->where('id', $reportId)->lockForUpdate()->first();
            $flow->ensure($row !== null, 404, 'report_not_found', 'Report not found.');
            $flow->ensure((int) $row->assignment_version === $data['assignment_version'], 409, 'stale_assignment_version', 'Report assignment has changed.');
            $result = ['id' => $reportId, 'assigned_admin_id' => $data['assigned_admin_id'], 'assignment_version' => $row->assignment_version + 1];
            DB::table('reports')->where('id', $reportId)->update(['assigned_admin_id' => $data['assigned_admin_id'], 'assigned_by' => $actor->id, 'assigned_at' => now('UTC'), 'assignment_version' => $result['assignment_version'], 'updated_at' => now('UTC')]);
            $flow->audit($actor, 'assignment.change', 'report', $reportId,
                ['assigned_admin_id' => $row->assigned_admin_id, 'assignment_version' => (int) $row->assignment_version],
                ['assigned_admin_id' => $result['assigned_admin_id'], 'assignment_version' => $result['assignment_version']], $data['reason']);

            return $result;
        }, 3);
    }
}
