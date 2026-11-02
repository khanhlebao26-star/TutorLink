<?php

namespace App\Http\Requests;

use App\Support\ApiErrorResponse;
use App\Support\BeD04Workspace;
use Illuminate\Http\Exceptions\HttpResponseException;

class AnnouncementIndexRequest extends BeD04ApiRequest
{
    public function authorize(): bool
    {
        app(BeD04Workspace::class)->visible(
            $this->user(),
            (int) $this->route('program'),
            (string) $this->attributes->get('request_id'),
        );

        if ($this->user()->role === 'customer' && in_array($this->query('state'), ['draft', 'all'], true)) {
            throw new HttpResponseException(ApiErrorResponse::make(
                403,
                'announcement_draft_forbidden',
                'Draft Announcements are available only to the Tutor owner.',
                (string) $this->attributes->get('request_id'),
            ));
        }

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'state' => ['sometimes', 'string', 'in:published,draft,all'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
