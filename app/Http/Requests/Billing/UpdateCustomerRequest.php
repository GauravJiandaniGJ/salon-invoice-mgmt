<?php

namespace App\Http\Requests\Billing;

use App\Models\Customer;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class UpdateCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(Customer::GENDERS)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            try {
                $phone = PhoneNumber::normalise($this->input('phone'));
                $exists = Customer::where('phone', $phone)
                    ->whereKeyNot($this->route('customer')?->id)
                    ->exists();

                if ($exists) {
                    $v->errors()->add('phone', 'Another customer already has this phone number.');
                }
            } catch (InvalidArgumentException $e) {
                $v->errors()->add('phone', $e->getMessage());
            }
        });
    }

    /** @return array<string, mixed> */
    public function normalised(): array
    {
        $data = $this->validated();
        $data['phone'] = PhoneNumber::normalise($data['phone']);
        $data['gender'] = $data['gender'] ?? null;
        $data['notes'] = $data['notes'] ?? null;

        return $data;
    }
}
