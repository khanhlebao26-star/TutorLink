<?php

namespace App\Http\Controllers;

use App\Support\WorkerHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(WorkerHeartbeat $heartbeats): JsonResponse
    {
        $db = 'down';
        $workers = array_fill_keys(WorkerHeartbeat::PROCESSES, 'unknown');
        try {
            DB::connection()->selectOne('SELECT 1');
            $db = 'ok';
        } catch (Throwable) {
        }
        if ($db === 'ok') {
            try {
                $workers = array_map(fn (array $worker): string => $worker['status'], $heartbeats->snapshot());
            } catch (Throwable) {
            }
        }
        $ready = $db === 'ok' && collect($workers)->every(fn (string $status): bool => $status === 'ok');

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'app' => 'ok',
            'db' => $db,
            'workers' => $workers,
        ], $ready ? 200 : 503, ['Cache-Control' => 'no-store']);
    }
}
