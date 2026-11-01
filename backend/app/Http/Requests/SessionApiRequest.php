<?php

namespace App\Http\Requests;

use App\Models\Program;
use App\Models\ProgramSession;
use App\Models\User;
use App\Policies\ProgramSessionPolicy;
use App\Support\ApiErrorResponse;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

abstract class SessionApiRequest extends ProgramApiRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User) {
            $this->failApi(
                401,
                'unauthenticated',
                'Authentication is required.',
            );
        }

        if ($actor->status !== 'active' || $actor->deleted_at !== null) {
            $this->failApi(
                403,
                'account_inactive',
                'This account is not available.',
            );
        }

        $sessionId = $this->route('session');

        if ($sessionId !== null) {
            $session = ProgramSession::query()
                ->with('program')
                ->find($sessionId);

            if ($session === null) {
                $this->failApi(
                    404,
                    'session_not_found',
                    'Session not found.',
                );
            }

            $program = $session->program;
        } else {
            $program = Program::query()->find(
                $this->route('program'),
            );
        }

        $policy = app(ProgramSessionPolicy::class);

        if (
            $program === null
            || ! $policy->viewAny($actor, $program)
        ) {
            $this->failApi(
                404,
                'session_not_found',
                'Session not found.',
            );
        }

        if (! $policy->mutate($actor, $program)) {
            $this->failApi(
                403,
                'session_mutation_forbidden',
                'Only the Tutor owner can change Sessions.',
            );
        }

        return true;
    }

    public function idempotencyKey(): string
    {
        return strtolower(
            (string) $this->header('Idempotency-Key'),
        );
    }

    /** @return list<string> */
    protected function allowedFields(): array
    {
        return array_keys($this->rules());
    }

    /** @return array<string, array<mixed>> */
    protected function scheduleRules(bool $creating): array
    {
        $presence = $creating ? 'required' : 'sometimes';

        $timestampRules = [
            'bail',
            $presence,
            'required',
            'string',
            'date',
            'regex:/\A\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])'
            .'T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d'
            .'(?:\.\d{1,6})?'
            .'(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/i',
        ];

        return [
            'title' => [
                'bail',
                $presence,
                'required',
                'string',
                'max:180',
                'regex:/[^\s\p{Z}]/u',
            ],
            'planned_starts_at' => $timestampRules,
            'planned_ends_at' => $timestampRules,
            'delivery_mode' => [
                $presence,
                'required',
                'string',
                'in:online,offline,hybrid',
            ],
            'location_text' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'meeting_url' => [
                'bail',
                'sometimes',
                'nullable',
                'string',
                'max:2048',
                'url:https',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    $parts = parse_url($value);

                    if (
                        $parts === false
                        || array_key_exists('user', $parts)
                        || array_key_exists('pass', $parts)
                    ) {
                        $fail(
                            'The meeting URL must not contain credentials.',
                        );
                    }
                },
            ],
        ];
    }

    /** @return list<string> */
    protected function versionRules(): array
    {
        return [
            'bail',
            'required',
            'integer:strict',
            'min:1',
            'max:2147483647',
        ];
    }

    /** @param array<string, mixed> $input */
    protected function validatePayload(
        Validator $validator,
        array $input,
    ): void {
        $key = $this->header('Idempotency-Key');

        if (! is_string($key) || ! Str::isUuid($key)) {
            $validator->errors()->add(
                'Idempotency-Key',
                'A valid UUID Idempotency-Key header is required.',
            );
        }

        if (
            ! array_key_exists('planned_starts_at', $input)
            || ! array_key_exists('planned_ends_at', $input)
            || $validator->errors()->has('planned_starts_at')
            || $validator->errors()->has('planned_ends_at')
        ) {
            return;
        }

        $startsAt = CarbonImmutable::parse(
            $input['planned_starts_at'],
        );

        $endsAt = CarbonImmutable::parse(
            $input['planned_ends_at'],
        );

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            $validator->errors()->add(
                'planned_ends_at',
                'The end time must be after the start time.',
            );
        }
    }

    private function failApi(
        int $status,
        string $code,
        string $message,
    ): never {
        throw new HttpResponseException(
            ApiErrorResponse::make(
                $status,
                $code,
                $message,
                (string) $this->attributes->get('request_id'),
            ),
        );
    }
}
