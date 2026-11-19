<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ReadAdminProfiles;
use App\Actions\Admin\RunAdminOperation;
use App\Http\Controllers\Controller;
use App\Models\TutorProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssignedTutorProfileController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => 'sometimes|in:submitted,changes_requested,approved,rejected,suspended']);
        $request->query->set('scope', 'mine');
        $data = app(ReadAdminProfiles::class)->index($request)->getData(true);

        return view('admin.assigned-profiles.index', ['items' => $data['data'], 'meta' => $data['meta'], 'status' => $request->query('status', 'submitted')]);
    }

    public function show(Request $request, TutorProfile $tutorProfile): View
    {
        $data = app(ReadAdminProfiles::class)->show($request, $tutorProfile)->getData(true);

        return view('admin.assigned-profiles.show', ['item' => $data['data']]);
    }

    public function update(Request $request, TutorProfile $tutorProfile): RedirectResponse
    {
        $data = $request->validate(['operation' => 'required|in:approve,request-changes,reject,suspend,restore,account-suspend,account-restore', 'assignment_version' => 'required|integer|min:0', 'expected_version' => 'sometimes|integer|min:1', 'reason' => 'required|string|min:2|max:2000']);
        app(ReadAdminProfiles::class)->show($request, $tutorProfile);
        try {
            app(RunAdminOperation::class)->execute($request->user(), 'profile', $tutorProfile->id, $data['operation'], $data);
        } catch (HttpResponseException $exception) {
            if ($exception->getResponse()->getStatusCode() !== 409) {
                throw $exception;
            }

            return back()->withInput()->withErrors(['operation' => 'Dữ liệu đã thay đổi. Tải lại hồ sơ trước khi quyết định.']);
        }

        return to_route('admin.assigned-profiles.show', $tutorProfile)->with('status', 'Đã cập nhật hồ sơ.');
    }
}
