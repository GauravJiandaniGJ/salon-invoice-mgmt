<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;

    public const GENDERS = ['female', 'male', 'other'];

    protected $fillable = ['name', 'phone', 'gender', 'notes', 'last_visit_at', 'total_spent'];

    protected $appends = ['phone_display', 'first_name'];

    protected function casts(): array
    {
        return [
            'last_visit_at' => 'datetime',
            'total_spent' => 'decimal:2',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('invoice_date')->latest('id');
    }

    /** "+91 98765 43210" */
    protected function phoneDisplay(): Attribute
    {
        return Attribute::get(fn () => PhoneNumber::display($this->phone));
    }

    /** "98XXXX3210" — for the public invoice page */
    protected function phoneMasked(): Attribute
    {
        return Attribute::get(fn () => PhoneNumber::masked($this->phone));
    }

    protected function firstName(): Attribute
    {
        return Attribute::get(fn () => trim(explode(' ', trim((string) $this->name))[0] ?? ''));
    }
}
