<?php

namespace App\Http\Requests\Catalog;

use App\Models\ServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'audience' => ['required', Rule::in(ServiceCategory::AUDIENCES)],
        ];
    }
}
