<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function owner(): User
{
    return User::factory()->owner()->create();
}

function staff(): User
{
    return User::factory()->staff()->create();
}
