<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    #[Response(200, type: 'array{data:list<array{id:string,type:string,read_at:?string,created_at:string,deep_link:?string}>,meta:array{current_page:int,last_page:int,total:int,per_page:int}}')]
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:50', 'unread' => 'sometimes|in:0,1']);
        $query = DB::table('notifications')->where('user_id', $request->user()->id);
        if (($data['unread'] ?? '0') === '1') {
            $query->whereNull('read_at');
        }
        $page = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($data['per_page'] ?? 20);
        $items = $page->getCollection()->map(function (object $row) use ($request): array {
            $payload = json_decode($row->payload, true);

            return ['id' => $row->id, 'type' => $row->type, 'read_at' => $row->read_at, 'created_at' => $row->created_at, 'deep_link' => $this->target($request, is_array($payload) ? ($payload['deep_link'] ?? null) : null)];
        });

        return response()->json(['data' => $items, 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()]], 200, ['Cache-Control' => 'private, no-store']);
    }

    #[Response(200, type: 'array{data:array{count:int}}')]
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['count' => DB::table('notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->count()]], 200, ['Cache-Control' => 'private, no-store']);
    }

    #[Response(200, type: 'array{data:array{id:string,read_at:string}}')]
    public function read(Request $request, string $notification): JsonResponse
    {
        abort_unless(Str::isUuid($notification), 404);

        return DB::transaction(function () use ($request, $notification): JsonResponse {
            $row = DB::table('notifications')->where('user_id', $request->user()->id)->where('id', $notification)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->read_at === null) {
                DB::table('notifications')->where('id', $notification)->update(['read_at' => now('UTC')]);
            }

            return response()->json(['data' => ['id' => $row->id, 'read_at' => CarbonImmutable::parse(DB::table('notifications')->where('id', $row->id)->value('read_at'))->utc()->toISOString()]], 200, ['Cache-Control' => 'private, no-store']);
        });
    }

    #[Response(200, type: 'array{data:array{updated_count:int}}')]
    public function readAll(Request $request): JsonResponse
    {
        $count = DB::table('notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now('UTC')]);

        return response()->json(['data' => ['updated_count' => $count]], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        abort_unless(Str::isUuid($notification), 404);

        $deleted = DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->where('id', $notification)
            ->delete();

        abort_unless($deleted === 1, 404);

        return response()->json(null, 204, ['Cache-Control' => 'private, no-store']);
    }

    private function target(Request $request, mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }
        if ($path === '/profile') {
            return $request->user()->role === 'tutor' ? $path : null;
        }
        if (preg_match('~^/invitations/([1-9][0-9]*)$~D', $path, $match)) {
            $row = DB::table('program_invitations')->find((int) $match[1]);
            if (! $row) {
                return null;
            }
            $own = (int) $row->invitee_user_id === $request->user()->id;
            $tutor = DB::table('programs as p')->join('tutor_profiles as t', 't.id', '=', 'p.owner_tutor_profile_id')->where('p.id', $row->program_id)->where('t.user_id', $request->user()->id)->exists();

            return $own || $tutor ? $path : null;
        }
        if (preg_match('~^/reports/([1-9][0-9]*)$~D', $path, $match)) {
            return DB::table('reports')->where('id', (int) $match[1])->where('reporter_id', $request->user()->id)->exists() ? '/reports' : null;
        }
        if (preg_match('~^/(programs|tasks|announcements|fee-records)/([1-9][0-9]*)$~D', $path, $match)) {
            $kind = $match[1];
            $id = (int) $match[2];
            $table = ['tasks' => 'tasks', 'announcements' => 'announcements', 'fee-records' => 'fee_records'];
            $programId = $kind === 'programs' ? $id : DB::table($table[$kind])->where('id', $id)->value('program_id');
            if (! $programId || ! Program::visibleTo($request->user())->whereKey($programId)->exists()) {
                return null;
            }

            return match ($kind) {
                'tasks' => "/programs/{$programId}/tasks/{$id}",'announcements' => "/programs/{$programId}/announcements",'fee-records' => "/programs/{$programId}/fees",default => "/programs/{$programId}"
            };
        }

        return null;
    }
}
