<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'payment_mode' => ['required', Rule::in(Expense::PAYMENT_MODES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category' => trim((string) $this->input('category')),
            'description' => trim((string) $this->input('description')),
        ]);
    }
}
