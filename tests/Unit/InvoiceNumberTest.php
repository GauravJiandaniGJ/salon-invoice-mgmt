<?php

use App\Models\Invoice;
use App\Services\InvoiceNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('first number is 0001', function () {
    expect(InvoiceNumber::next('WS'))->toBe('WS-0001');
});

test('increments from the highest existing number', function () {
    Invoice::factory()->create(['invoice_number' => 'WS-0007']);
    Invoice::factory()->create(['invoice_number' => 'WS-0003']);

    expect(InvoiceNumber::next('WS'))->toBe('WS-0008');
});

test('grows past 9999 and ignores prefix changes', function () {
    Invoice::factory()->create(['invoice_number' => 'OLD-9999']);

    expect(InvoiceNumber::next('WS'))->toBe('WS-10000');
});
