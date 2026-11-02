<?php

namespace App\Http\Requests;

use App\Support\BeD04Workspace;
use Illuminate\Validation\Validator;

class FeeRecordIndexRequest extends BeD04ApiRequest
{
    public function authorize(): bool
    {
        $program = app(BeD04Workspace::class)->visible(
            $this->user(),
            (int) $this->route('program'),
            (string) $this->attributes->get('request_id'),
        );
        $this->attributes->set('d04_program_id', (int) $program->id);

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'period' => ['sometimes', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/D'],
            'status' => ['sometimes', 'in:declared,voided'],
            'tutor_payment_mark' => ['sometimes', 'in:unmarked,marked_paid,marked_unpaid'],
        ];
    }

    protected function validatePayload(Validator $validator, array $input): void
    {
        if (isset($input['period']) && ! self::validPeriod($input['period'])) {
            $validator->errors()->add('period', 'The period must be a valid calendar month in YYYY-MM format.');
        }
    }

    public static function validPeriod(mixed $period): bool
    {
        if (! is_string($period) || preg_match('/^(\d{4})-(\d{2})$/D', $period, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], 1, (int) $matches[1]);
    }
}
