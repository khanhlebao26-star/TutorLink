<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use App\Support\ApiErrorResponse;
use App\Support\BeD04Workspace;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class PublishAnnouncementRequest extends BeD04ApiRequest
{
    public function authorize(): bool
    {
        $requestId = (string) $this->attributes->get('request_id');
        $announcement = Announcement::query()->find((int) $this->route('announcement'));

        if ($announcement === null) {
            $this->notFound($requestId);
        }

        $workspace = app(BeD04Workspace::class);
        $workspace->visible($this->user(), (int) $announcement->program_id, $requestId);
        $program = $workspace->owner($this->user(), (int) $announcement->program_id, $requestId);
        $this->attributes->set('d04_program_id', (int) $program->id);

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['expected_version' => $this->expectedVersionRules()];
    }

    protected function allowedFields(): array
    {
        return ['expected_version'];
    }

    protected function validatePayload(Validator $validator, array $input): void
    {
        $key = $this->header('Idempotency-Key');

        if (! is_string($key) || ! Str::isUuid($key)) {
            $validator->errors()->add('Idempotency-Key', 'A UUID Idempotency-Key header is required.');

            return;
        }

        $this->attributes->set('d04_idempotency_key', strtolower($key));
    }

    private function notFound(string $requestId): never
    {
        throw new HttpResponseException(
            ApiErrorResponse::make(404, 'announcement_not_found', 'Announcement not found.', $requestId),
        );
    }
}
