<?php

use App\Models\Customer;
use App\Support\PhoneNumber;

test('normalises indian numbers', function (string $input, string $expected) {
    expect(PhoneNumber::normalise($input))->toBe($expected);
})->with([
    ['9876543210', '919876543210'],
    ['09876543210', '919876543210'],
    ['919876543210', '919876543210'],
    ['+91 98765 43210', '919876543210'],
    ['+91-98765-43210', '919876543210'],
]);

test('rejects invalid numbers', function (string $input) {
    expect(fn () => PhoneNumber::normalise($input))->toThrow(InvalidArgumentException::class);
    expect(PhoneNumber::isValid($input))->toBeFalse();
})->with(['', '12345', '5876543210', '1234567890', '9188765432101', 'abc']);

test('formats for display and masks', function () {
    expect(PhoneNumber::display('919876543210'))->toBe('+91 98765 43210')
        ->and(PhoneNumber::masked('919876543210'))->toBe('98XXXX3210');
});

test('masked never exposes more than the last four digits for malformed input', function () {
    expect(PhoneNumber::masked('91947638869'))->toBe('XXXXXXX8869')
        ->and(PhoneNumber::masked('123'))->toBe('123')
        ->and(PhoneNumber::masked(null))->toBe('');
});

test('customer factory generates valid normalised phones', function () {
    $customer = Customer::factory()->make();
    expect(PhoneNumber::isValid($customer->phone))->toBeTrue()
        ->and(strlen($customer->phone))->toBe(12);
});
