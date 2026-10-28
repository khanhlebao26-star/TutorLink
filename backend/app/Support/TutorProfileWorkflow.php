<?php

namespace App\Support;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TutorProfileWorkflow
{
    /** @return array{User, TutorProfile} */
    public function lock(User $actor, int $profileId, ?int $extraUserId = null): array
    {
        $context = TutorProfile::find($profileId);
        $this->ensure($context !== null, 404, 'tutor_profile_not_found', 'Tutor profile not found.');
        $ids = array_filter([$actor->id, $context->user_id, $context->assigned_admin_id, $extraUserId]);
        $users = User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $actor = $users->get($actor->id);
        $this->ensure($actor !== null && $actor->status === 'active', 403, 'permission_denied', 'Permission denied.');
        $profile = TutorProfile::query()->lockForUpdate()->findOrFail($profileId);
        $owner = $users->get($profile->user_id);
        $this->ensure($owner !== null, 404, 'tutor_profile_not_found', 'Tutor profile not found.');
        $profile->setRelation('user', $owner);

        return [$actor, $profile];
    }

    public function ensure(bool $condition, int $status, string $code, string $message): void
    {
        if (! $condition) {
            throw new HttpResponseException(ApiErrorResponse::make($status, $code, $message, $this->requestId()));
        }
    }

    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     */
    public function audit(User $actor, string $action, string $type, int $id, array $before, array $after, ?string $reason = null): int
    {
        return DB::table('audit_logs')->insertGetId([
            'actor_id' => $actor->id, 'action' => $action, 'resource_type' => $type, 'resource_id' => $id,
            'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($after, JSON_THROW_ON_ERROR), 'reason' => $reason,
            'request_id' => $this->requestId(), 'created_at' => now(),
        ]);
    }

    private function requestId(): string
    {
        $request = request();
        $id = $request->attributes->get('request_id') ?: $request->header('X-Request-ID');
        if (! is_string($id) || ! Str::isUuid($id)) {
            $id = (string) str()->uuid();
        }
        $request->attributes->set('request_id', $id);

        return $id;
    }

    /** @return array<string, mixed> */
    public function assignment(TutorProfile $profile): array
    {
        return $profile->only(['assigned_admin_id', 'assigned_by', 'assigned_at', 'assignment_version']);
    }
}
