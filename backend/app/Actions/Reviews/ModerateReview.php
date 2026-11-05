<?php

namespace App\Actions\Reviews;

use App\Models\User;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions;
use App\Support\TrustWorkflow;
use App\Support\TutorProfileWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ModerateReview
{
    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function execute(User $actor, int $id, string $visibility, array $data): array
    {
        $data = Validator::make([...$data, 'visibility' => $visibility], ['visibility' => ['required', Rule::in(['hidden', 'visible'])], 'expected_version' => 'required|integer:strict|min:1', 'reason' => ['required', 'string', 'max:2000', 'regex:/\\S/u']])->validate();

        return DB::transaction(function () use ($actor, $id, $visibility, $data): array {
            $actor = User::whereKey($actor->id)->lockForUpdate()->first();
            $flow = app(TutorProfileWorkflow::class);
            $flow->ensure($actor !== null && app(AdminAuthorization::class)->allows($actor, AdminPermissions::MODERATE_REVIEWS), 403, 'permission_denied', 'Permission denied.');
            $row = DB::table('reviews')->where('id', $id)->lockForUpdate()->first();
            $flow->ensure($row !== null, 404, 'resource_not_found', 'Review not found.');
            $flow->ensure((int) $row->version === $data['expected_version'] && $row->visibility !== $visibility, 409, 'stale_review_version', 'Review has changed.');
            DB::table('reviews')->where('id', $id)->update(['visibility' => $visibility, 'version' => $row->version + 1, 'updated_at' => now('UTC')]);
            $flow->audit($actor, $visibility === 'hidden' ? 'review.hide' : 'review.restore', 'review', $id, ['visibility' => $row->visibility, 'version' => (int) $row->version], ['visibility' => $visibility, 'version' => $row->version + 1], $data['reason']);

            return app(TrustWorkflow::class)->reviewData(DB::table('reviews')->find($id));
        }, 3);
    }
}
