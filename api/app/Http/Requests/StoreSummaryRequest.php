<?php

namespace App\Http\Requests;

use App\Enums\SourceType;
use App\Enums\SummaryStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::enum(SourceType::class)],

            // url XOR text — each required only for its own source_type, and the
            // other field is prohibited so exactly one source is provided.
            'url' => [
                'required_if:source_type,url',
                'prohibited_unless:source_type,url',
                'url:http,https',
                'max:2048',
            ],
            'text' => [
                'required_if:source_type,text',
                'prohibited_unless:source_type,text',
                'string',
                'max:50000',
            ],

            'style' => ['required', Rule::enum(SummaryStyle::class)],
        ];
    }
}
