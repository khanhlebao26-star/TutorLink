<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class FileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $maxBytes = (int) config('file-validation.max_bytes', 5_000_000);

        $mimeRule = $this->input('purpose') === 'avatar'
            ? 'mimes:jpg,jpeg,png'
            : 'mimes:pdf,jpg,jpeg,png,doc,docx';

        return [
            /**
             * Non-empty file, maximum 5,000,000 bytes (5 MB decimal), for every supported purpose.
             * Avatar: JPG/JPEG/PNG; documents: PDF/JPG/JPEG/PNG/DOC/DOCX.
             * Upload creates a pending file; complete performs technical validation, not an antivirus scan.
             */
            'file' => [
                'bail',
                'required',
                'file',
                function (string $attribute, mixed $value, Closure $fail) use ($maxBytes): void {
                    if (! $value instanceof UploadedFile) {
                        $fail('File upload không hợp lệ.');

                        return;
                    }

                    $size = $value->getSize();

                    if (
                        $size === false
                        || $size <= 0
                        || $size > $maxBytes
                    ) {
                        $fail(
                            'File phải có dung lượng từ 1 đến 5.000.000 byte (5 MB).'
                        );
                    }
                },
                $mimeRule,
            ],
            'purpose' => [
                'required',
                'string',
                'in:avatar,verification_document,task_attachment,profile_document',
            ],
        ];
    }
}
