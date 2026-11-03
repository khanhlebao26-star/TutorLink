<?php

namespace App\Actions\Reports;

use App\Models\User;
use App\Policies\ReportPolicy;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions;
use App\Support\NotificationOutboxWriter;
use App\Support\TutorProfileWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DecideReport
{
    /** @param array<string,mixed> $data */
    public function execute(User $actor, int $reportId, string $status, array $data): object
    {
        $data = Validator::make([...$data, 'status' => $status], ['status' => ['required', Rule::in(['resolved', 'dismissed'])], 'assignment_version' => 'required|integer:strict|min:0', 'resolution_note' => ['required', 'string', 'max:2000', 'regex:/\S/u']])->validate();

        return DB::transaction(function () use ($actor, $reportId, $status, $data): object {
            $flow = app(TutorProfileWorkflow::class);
            $actor = User::whereKey($actor->id)->lockForUpdate()->first();
            $flow->ensure($actor !== null && app(AdminAuthorization::class)->allows($actor, AdminPermissions::DECIDE_REPORTS), 403, 'permission_denied', 'Permission denied.');
            $row = DB::table('reports')->where('id', $reportId)->lockForUpdate()->first();
            $flow->ensure($row !== null && app(ReportPolicy::class)->decide($actor, $row), 404, 'report_not_found', 'Report not found.');
            $flow->ensure($row->status === 'open' && (int) $row->assignment_version === $data['assignment_version'], 409, 'stale_report_decision', 'Report state or assignment has changed.');
            DB::table('reports')->where('id', $reportId)->update(['status' => $status, 'handled_by' => $actor->id, 'handled_at' => now('UTC'), 'resolution_note' => $data['resolution_note'], 'updated_at' => now('UTC')]);
            $flow->audit($actor, $status === 'resolved' ? 'report.resolve' : 'report.dismiss', 'report', $reportId, ['status' => 'open'], ['status' => $status], $data['resolution_note']);
            app(NotificationOutboxWriter::class)->record('report_'.$status, 'report', $reportId, "report:{$reportId}:{$status}", [(int) $row->reporter_id], ['deep_link' => '/reports/'.$reportId]);

            return DB::table('reports')->find($reportId);
        }, 3);
    }
}
