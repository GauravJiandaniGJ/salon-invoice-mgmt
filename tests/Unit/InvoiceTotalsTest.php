<?php

use App\Services\InvoiceTotals;

test('sums line totals with no discount', function () {
    $t = InvoiceTotals::calculate([
        ['unit_price' => 225, 'quantity' => 1],
        ['unit_price' => 60, 'quantity' => 10],
    ], null, 0, 0);

    expect($t['subtotal'])->toBe(825.0)
        ->and($t['discount_amount'])->toBe(0.0)
        ->and($t['total'])->toBe(825.0)
        ->and($t['round_off'])->toBe(0.0)
        ->and($t['items'][1]['line_total'])->toBe(600.0);
});

test('flat discount', function () {
    $t = InvoiceTotals::calculate([['unit_price' => 1000, 'quantity' => 1]], 'flat', 150, 0);

    expect($t['discount_amount'])->toBe(150.0)->and($t['total'])->toBe(850.0);
});

test('percent discount rounds to the paisa and total to the rupee', function () {
    // 10% of 1235 = 123.5 → taxable 1111.5 → total 1112, round_off +0.50
    $t = InvoiceTotals::calculate([['unit_price' => 1235, 'quantity' => 1]], 'percent', 10, 0);

    expect($t['discount_amount'])->toBe(123.5)
        ->and($t['total'])->toBe(1112.0)
        ->and($t['round_off'])->toBe(0.5);
});

test('rounds half up and records negative round off', function () {
    // 333.33 * 1 → total 333, round_off -0.33
    $t = InvoiceTotals::calculate([['unit_price' => 333.33, 'quantity' => 1]], null, 0, 0);
    expect($t['total'])->toBe(333.0)->and($t['round_off'])->toBe(-0.33);

    // 12.5 → 13
    $t = InvoiceTotals::calculate([['unit_price' => 12.5, 'quantity' => 1]], null, 0, 0);
    expect($t['total'])->toBe(13.0)->and($t['round_off'])->toBe(0.5);
});

test('discount is clamped to the subtotal', function () {
    $t = InvoiceTotals::calculate([['unit_price' => 100, 'quantity' => 1]], 'flat', 500, 0);
    expect($t['discount_amount'])->toBe(100.0)->and($t['total'])->toBe(0.0);
});

test('tax is applied on the discounted amount', function () {
    // 1000 - 100 = 900; 18% = 162 → 1062
    $t = InvoiceTotals::calculate([['unit_price' => 1000, 'quantity' => 1]], 'flat', 100, 18);
    expect($t['tax_amount'])->toBe(162.0)->and($t['total'])->toBe(1062.0);
});

test('fractional quantities', function () {
    $t = InvoiceTotals::calculate([['unit_price' => 99.99, 'quantity' => 1.5]], null, 0, 0);
    expect($t['items'][0]['line_total'])->toBe(149.99)->and($t['total'])->toBe(150.0)->and($t['round_off'])->toBe(0.01);
});
