<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Admin\ReadAuditLogs;
use App\Http\Controllers\Controller;
use App\Support\TrustWorkflow;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** Index scoped AuditLog data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:list<array{id:int,actor_id:?int,action:string,resource_type:string,resource_id:int,before:?array<string,mixed>,after:?array<string,mixed>,reason:?string,request_id:?string,created_at:?string}>,meta:array{current_page:int,per_page:int,total:int}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function index(Request $request, TrustWorkflow $flow): JsonResponse
    {
        return app(ReadAuditLogs::class)->index($request, $flow);
    }

    /** Show scoped AuditLog data. */
    #[Response(200, description: 'Authorized result.', type: 'array{data:array{id:int,actor_id:?int,action:string,resource_type:string,resource_id:int,before:?array<string,mixed>,after:?array<string,mixed>,reason:?string,request_id:?string,created_at:?string}}')]
    #[Response(401, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(404, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(409, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(422, description: 'Authorization, scope, state or validation failure.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function show(Request $request, int $auditLog, TrustWorkflow $flow): JsonResponse
    {
        return app(ReadAuditLogs::class)->show($request, $auditLog, $flow);
    }
}
