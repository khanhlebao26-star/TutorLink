<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class RevokeUserSessions
{
    public function handle(
        User $user,
        ?string $exceptSessionId = null,
    ): void {
        if (config('session.driver') !== 'database') {
            throw new LogicException(
                'Session revocation requires the database session driver.',
            );
        }

        $query = DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id);

        if ($exceptSessionId !== null) {
            $query->where('id', '<>', $exceptSessionId);
        }

        $query->delete();
    }
}