<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'group_name' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'price_max' => ['nullable', 'numeric', 'gte:price', 'max:99999999'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'group_name' => $this->filled('group_name') ? trim((string) $this->group_name) : null,
            'price_max' => $this->filled('price_max') ? $this->price_max : null,
            'duration_minutes' => $this->filled('duration_minutes') ? $this->duration_minutes : null,
        ]);
    }
}
