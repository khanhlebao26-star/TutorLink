<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use App\Support\ApiErrorResponse;
use App\Support\BeD04Workspace;
use Illuminate\Http\Exceptions\HttpResponseException;

class AnnouncementShowRequest extends BeD04ApiRequest
{
    public function authorize(): bool
    {
        $requestId = (string) $this->attributes->get('request_id');
        $announcement = Announcement::query()->find((int) $this->route('announcement'));

        if ($announcement === null) {
            $this->notFound($requestId);
        }

        $workspace = app(BeD04Workspace::class);
        $program = $workspace->visible($this->user(), (int) $announcement->program_id, $requestId);
        $isOwner = $this->user()->role === 'tutor'
            && $program->ownerTutorProfile()->where('user_id', $this->user()->id)->exists();

        if ($announcement->published_at === null && ! $isOwner) {
            $this->notFound($requestId);
        }

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [];
    }

    protected function allowedFields(): array
    {
        return [];
    }

    private function notFound(string $requestId): never
    {
        throw new HttpResponseException(ApiErrorResponse::make(
            404,
            'announcement_not_found',
            'Announcement not found.',
            $requestId,
        ));
    }
}
