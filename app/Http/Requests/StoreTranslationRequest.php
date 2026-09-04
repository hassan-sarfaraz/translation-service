<?php

namespace App\Http\Requests;

use App\Models\Locale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTranslationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', 'exists:locales,code'],
            'key' => [
                'required',
                'string',
                'max:191',
                Rule::unique('translations')->where(function ($query) {
                    $localeId = Locale::where('code', $this->locale)->value('id');

                    return $query->where('locale_id', $localeId);
                }),
            ],
            'content' => ['required', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
