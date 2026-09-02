<?php

use App\Models\Setting;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('favicon serves the bundled default when no logo is uploaded', function () {
    $this->get('/brand/favicon')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=3600, public');
});

test('favicon serves the uploaded salon logo when set', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('logo.png', 64, 64)->store('logos', 'public');
    Setting::set('logo_path', $path);

    $this->get('/brand/favicon')->assertOk()->assertHeader('Content-Type', 'image/png');
});

test('shared props expose brand colour and powered-by', function () {
    $this->seed(SettingsSeeder::class);

    $this->actingAs(owner())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('salon.brand_color', '#C9A24B')
            ->where('powered_by.name', 'TodoIT')
            ->where('powered_by.url', 'https://todoitservices.com')
        );
});
