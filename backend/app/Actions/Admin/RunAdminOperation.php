<?php

namespace App\Actions\Admin;

use App\Actions\Accounts\ChangeAccountStatus;
use App\Actions\Reports\AssignReport;
use App\Actions\Reports\DecideReport;
use App\Actions\Reviews\ModerateReview;
use App\Actions\TutorProfiles\AssignTutorProfile;
use App\Actions\TutorProfiles\DecideTutorProfile;
use App\Actions\TutorProfiles\DecideVerificationDocument;
use App\Actions\TutorProfiles\SuspendTutorProfile;
use App\Models\TutorProfile;
use App\Models\User;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions;

class RunAdminOperation
{
    /** @param array<string,mixed> $data */
    public function execute(User $actor, string $type, int $id, string $action, array $data): void
    {
        $actor = User::findOrFail($actor->id);
        abort_unless(config('admin.panel_enabled') && app(AdminAuthorization::class)->allows($actor, AdminPermissions::VIEW_DASHBOARD), 403);
        if ($type === 'profile') {
            if ($action === 'assign') {
                app(AssignTutorProfile::class)->execute($actor, $id, $data);

                return;
            }
            if (in_array($action, ['approve', 'request-changes', 'reject'], true)) {
                app(DecideTutorProfile::class)->execute($actor, $id, match ($action) {
                    'approve' => 'approved','request-changes' => 'changes_requested','reject' => 'rejected'
                }, $data);

                return;
            }
            if (in_array($action, ['suspend', 'restore'], true)) {
                app(SuspendTutorProfile::class)->execute($actor, $id, $action === 'suspend', (string) ($data['reason'] ?? ''));

                return;
            }
            if (in_array($action, ['account-suspend', 'account-restore'], true)) {
                app(ChangeAccountStatus::class)->execute($actor, TutorProfile::findOrFail($id)->user_id, $action === 'account-suspend', (string) ($data['reason'] ?? ''));

                return;
            }
        }
        if ($type === 'document' && in_array($action, ['approve', 'reject'], true)) {
            app(DecideVerificationDocument::class)->execute($actor, $id, $action === 'approve', $data);

            return;
        }
        if ($type === 'report') {
            if ($action === 'assign') {
                app(AssignReport::class)->execute($actor, $id, $data);

                return;
            }
            if (in_array($action, ['resolve', 'dismiss'], true)) {
                app(DecideReport::class)->execute($actor, $id, $action === 'resolve' ? 'resolved' : 'dismissed', $data);

                return;
            }
        }
        if ($type === 'review' && in_array($action, ['hide', 'restore'], true)) {
            app(ModerateReview::class)->execute($actor, $id, $action === 'hide' ? 'hidden' : 'visible', $data);

            return;
        }
        abort(422, 'Unsupported operation.');
    }
}
