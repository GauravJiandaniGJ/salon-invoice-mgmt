<?php

namespace App\Http\Requests\Settings;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'salon_name' => ['required', 'string', 'max:100'],
            'salon_tagline' => ['nullable', 'string', 'max:150'],
            'salon_address' => ['nullable', 'string', 'max:500'],
            'salon_phone' => ['nullable', 'string', 'max:30'],
            'salon_whatsapp_number' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== null && $value !== '' && ! PhoneNumber::isValid($value)) {
                    $fail('Enter a valid 10-digit Indian mobile number.');
                }
            }],
            'invoice_prefix' => ['required', 'string', 'regex:/^[A-Z]{1,6}$/'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'whatsapp_template' => ['nullable', 'string', 'max:1000'],
            'footer_text' => ['nullable', 'string', 'max:150'],
            'app_url' => ['nullable', 'url', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_prefix.regex' => 'Prefix must be 1–6 uppercase letters, e.g. WS.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'invoice_prefix' => strtoupper(trim((string) $this->invoice_prefix)),
            'app_url' => $this->filled('app_url') ? rtrim(trim((string) $this->app_url), '/') : null,
        ]);
    }

    /** Values ready for Setting::set(). */
    public function settings(): array
    {
        $data = $this->validated();
        $data['tax_rate'] = (string) (float) ($data['tax_rate'] ?? 0);

        if (! empty($data['salon_whatsapp_number'])) {
            $data['salon_whatsapp_number'] = PhoneNumber::normalise($data['salon_whatsapp_number']);
        }

        foreach ($data as $key => $value) {
            $data[$key] = $value === null ? '' : (string) $value;
        }

        return $data;
    }
}
