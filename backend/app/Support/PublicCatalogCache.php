<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicCatalogCache
{
    public const TTL_SECONDS = 60;

    public function key(Request $request): ?string
    {
        $version = DB::table('marketplace_cache_versions')->where('id', 1)->value('generation');
        if ($version === null) {
            return null;
        }
        // Time-driven expiry must change the key even without a write/worker.
        $expired = DB::table('verification_documents')->where('status', 'approved')
            ->whereNotNull('expires_at')->where('expires_at', '<=', now('UTC'))
            ->orderByDesc('expires_at')->value('expires_at');
        $query = $request->query();
        ksort($query);

        return 'marketplace:v1:'.hash('sha256', json_encode([$version, $expired, $request->path(), $query], JSON_THROW_ON_ERROR));
    }
}
