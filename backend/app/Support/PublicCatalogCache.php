<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicCatalogCache
{
    public const TTL_SECONDS = 60;

    public function key(Request $request): ?string
    {
        // Time-driven expiry must change the key even without a write/worker.
        $expired = DB::table('verification_documents')->where('status', 'approved')
            ->whereNotNull('expires_at')->where('expires_at', '<=', now('UTC'))
            ->orderByDesc('expires_at')->limit(1)->select('expires_at');
        $snapshot = DB::table('marketplace_cache_versions')->where('id', 1)
            ->select('generation')->selectSub($expired, 'expired_at')->first();
        if ($snapshot === null) {
            return null;
        }

        $query = $request->query();
        ksort($query);

        return 'marketplace:v1:'.hash('sha256', json_encode([$snapshot->generation, $snapshot->expired_at, config('tutor.verification_documents_required', true), $request->path(), $query], JSON_THROW_ON_ERROR));
    }
}
