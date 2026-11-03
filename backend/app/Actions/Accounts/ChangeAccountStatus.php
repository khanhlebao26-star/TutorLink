<?php

namespace App\Actions\Accounts;

use App\Actions\Auth\RevokeUserSessions;
use App\Models\User;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions;
use App\Support\TutorProfileWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChangeAccountStatus
{
    public function execute(User $actor, int $targetId, bool $suspend, string $reason): User
    {
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:2', 'max:2000', 'regex:/\S/u']])->validate();

        return DB::transaction(function () use ($actor, $targetId, $suspend, $reason): User {
            $flow = app(TutorProfileWorkflow::class);
            $users = User::whereIn('id', [$actor->id, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($actor->id);
            $flow->ensure($actor !== null && app(AdminAuthorization::class)->allows($actor, AdminPermissions::SUSPEND_ACCOUNTS), 403, 'permission_denied', 'Permission denied.');
            $flow->ensure($actor->id !== $targetId, 422, 'self_suspension_forbidden', 'Administrators cannot suspend or restore themselves.');
            $target = $users->get($targetId);
            $flow->ensure($target !== null, 404, 'user_not_found', 'User not found.');
            $flow->ensure($target->status === ($suspend ? 'active' : 'suspended'), 409, 'invalid_account_transition', 'The requested account status transition is not allowed.');
            $before = ['status' => $target->status, 'suspended_at' => $target->suspended_at?->toISOString()];
            $target->forceFill(['status' => $suspend ? 'suspended' : 'active', 'suspended_at' => $suspend ? now() : null, ...($suspend ? ['remember_token' => str()->random(60)] : [])])->save();
            if ($suspend) {
                $target->tokens()->delete();
                app(RevokeUserSessions::class)->handle($target);
            }
            $flow->audit($actor, $suspend ? 'account_suspended' : 'account_restored', 'user', $target->id, $before, ['status' => $target->status, 'suspended_at' => $target->suspended_at?->toISOString()], $reason);

            return $target->refresh();
        }, 3);
    }
}
