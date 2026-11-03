<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions;

class ReportPolicy
{
    public function assign(User $actor): bool
    {
        return app(AdminAuthorization::class)->allows($actor, AdminPermissions::ASSIGN_REPORTS);
    }

    public function decide(User $actor, object $report): bool
    {
        return app(AdminAuthorization::class)->allows($actor, AdminPermissions::DECIDE_REPORTS)
            && (int) $report->assigned_admin_id === $actor->id;
    }

    public function eligibleAssignee(User $actor): bool
    {
        return app(AdminAuthorization::class)->allows($actor, AdminPermissions::VIEW_REPORTS)
            && app(AdminAuthorization::class)->allows($actor, AdminPermissions::DECIDE_REPORTS);
    }
}
