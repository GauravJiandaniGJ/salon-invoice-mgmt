<?php

namespace App\Http\Requests\Catalog;

use App\Models\ServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'audience' => ['sometimes', 'required', Rule::in(ServiceCategory::AUDIENCES)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
