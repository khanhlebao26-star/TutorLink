<?php

namespace App\Actions\Admin;

use App\Models\TutorProfile;
use App\Models\User;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions;
use App\Support\TrustWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReadAdminWorkspace
{
    /** @return array<string,mixed> */
    public function execute(Request $request, string $section, ?int $id): array
    {
        $actor = User::find($request->user()->id);
        abort_unless($actor && app(AdminAuthorization::class)->allows($actor, AdminPermissions::VIEW_DASHBOARD), 403);
        $flow = app(TrustWorkflow::class);

        return match ($section) {
            'dashboard' => ['dashboard' => app(ReadAdminDashboard::class)->execute($actor)],
            'profile' => ['profile' => app(ReadAdminProfiles::class)->show($request, TutorProfile::findOrFail($id))->getData(true)['data']],
            'report' => ['report' => app(ReadAdminReports::class)->show($request, (int) $id, $flow)->getData(true)['data'], 'evidence' => DB::table('reports')->where('id', $id)->value('status') === 'open' ? app(ReadAdminReports::class)->evidence($request, (int) $id, $flow)->getData(true)['data'] : null],
            'review' => $this->review($request, (int) $id, $flow),
            'audit' => ['audit' => app(ReadAuditLogs::class)->index($request, $flow)->getData(true)],
            'profile-assignment' => $this->assignment($actor, 'tutor_profiles', (int) $id, AdminPermissions::ASSIGN_TUTOR_PROFILES),
            'report-assignment' => $this->assignment($actor, 'reports', (int) $id, AdminPermissions::ASSIGN_REPORTS),
            default => abort(404),
        };
    }

    /** @return array<string,mixed> */
    private function review(Request $request, int $id, TrustWorkflow $flow): array
    {
        $flow->permission($request, AdminPermissions::MODERATE_REVIEWS);
        $row = DB::table('reviews')->find($id);
        abort_unless($row, 404);

        return ['review' => $flow->reviewData($row)];
    }

    /** @return array<string,mixed> */
    private function assignment(User $actor, string $table, int $id, string $permission): array
    {
        abort_unless(app(AdminAuthorization::class)->allows($actor, $permission), 403);
        $row = DB::table($table)->where('id', $id)->first(['id', 'status', 'assigned_admin_id', 'assignment_version']);
        abort_unless($row, 404);

        return ['assignment' => (array) $row];
    }
}
