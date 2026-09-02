<?php

namespace App\Http\Requests\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StoreInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
            'customer.phone' => ['required', 'string', 'max:20'],
            'customer.name' => ['nullable', 'string', 'max:120'],
            'customer.gender' => ['nullable', Rule::in(Customer::GENDERS)],
            'staff_member_id' => ['nullable', 'integer', 'exists:staff_members,id'],
            'invoice_date' => ['nullable', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'items.*.description' => ['nullable', 'required_without:items.*.service_id', 'string', 'max:160'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999'],
            'discount_type' => ['nullable', Rule::in(Invoice::DISCOUNT_TYPES)],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'payment_mode' => ['required', Rule::in(Invoice::PAYMENT_MODES)],
            'payment_status' => ['nullable', Rule::in(Invoice::PAYMENT_STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one service to the bill.',
            'items.min' => 'Add at least one service to the bill.',
            'items.*.description.required_without' => 'Each line needs a description.',
            'items.*.unit_price.min' => 'Price cannot be negative.',
            'items.*.quantity.gt' => 'Quantity must be more than 0.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $customer = (array) $this->input('customer', []);

            try {
                $phone = PhoneNumber::normalise($customer['phone'] ?? null);

                if (! Customer::where('phone', $phone)->exists() && trim((string) ($customer['name'] ?? '')) === '') {
                    $v->errors()->add('customer.name', 'Name is required for a new customer.');
                }
            } catch (InvalidArgumentException $e) {
                $v->errors()->add('customer.phone', $e->getMessage());
            }

            $type = $this->input('discount_type');
            $value = (float) $this->input('discount_value', 0);
            $subtotal = collect((array) $this->input('items', []))
                ->sum(fn ($item) => round((float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 0), 2));

            if ($type === 'percent' && $value > 100) {
                $v->errors()->add('discount_value', 'Discount cannot exceed 100%.');
            }

            if ($type === 'flat' && $value > $subtotal) {
                $v->errors()->add('discount_value', 'Discount cannot exceed the subtotal.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discount_type' => $this->filled('discount_type') ? $this->input('discount_type') : null,
            'discount_value' => $this->filled('discount_value') ? $this->input('discount_value') : 0,
            'payment_status' => $this->filled('payment_status') ? $this->input('payment_status') : 'paid',
            'invoice_date' => $this->filled('invoice_date') ? $this->input('invoice_date') : null,
            'staff_member_id' => $this->filled('staff_member_id') ? $this->input('staff_member_id') : null,
        ]);
    }
}
