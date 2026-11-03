<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Admin\ReadAdminDashboard;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /** Read assigned queues and permitted resource totals without personal data or evidence. */
    #[Response(200, description: 'Resources without permission are omitted. Queues are restricted to current assignment or unassigned coordination scope.', type: 'array{data:array{resources:array<string,mixed>,permissions:list<string>}}')]
    #[Response(401, description: 'Browser authentication required.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    #[Response(403, description: 'Active verified Admin with admin.dashboard.view required.', type: 'array{code:string,message:string,request_id:string,errors:object}')]
    public function show(Request $request, ReadAdminDashboard $dashboard): JsonResponse
    {
        $data = $dashboard->execute($request->user());
        $data['resources'] = (object) $data['resources'];

        return response()->json(['data' => $data]);
    }
}
