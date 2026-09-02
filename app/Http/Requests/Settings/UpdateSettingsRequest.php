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
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'whatsapp_driver' => ['nullable', 'in:wame,cloud'],
            'whatsapp_cloud_phone_id' => ['nullable', 'string', 'max:40', 'regex:/^[0-9]*$/'],
            'whatsapp_cloud_token' => ['nullable', 'string', 'max:500'],
            'whatsapp_cloud_template' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]*$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_prefix.regex' => 'Prefix must be 1–6 uppercase letters, e.g. WS.',
            'brand_color.regex' => 'Brand colour must be a hex value like #C9A24B.',
            'whatsapp_cloud_phone_id.regex' => 'The phone number ID is numeric (from Meta Business).',
            'whatsapp_cloud_template.regex' => 'Template names are lowercase letters, digits and underscores.',
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

        if (array_key_exists('brand_color', $data) && ! empty($data['brand_color'])) {
            $data['brand_color'] = strtoupper($data['brand_color']);
        }

        // The token is write-only: an empty field means "keep the current one".
        if (array_key_exists('whatsapp_cloud_token', $data) && trim((string) $data['whatsapp_cloud_token']) === '') {
            unset($data['whatsapp_cloud_token']);
        }

        foreach ($data as $key => $value) {
            $data[$key] = $value === null ? '' : (string) $value;
        }

        return $data;
    }
}
