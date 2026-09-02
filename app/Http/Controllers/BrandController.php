<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandController extends Controller
{
    /** Browser-tab icon: the uploaded salon logo when set, otherwise the bundled default. */
    public function favicon(): BinaryFileResponse
    {
        $headers = ['Cache-Control' => 'public, max-age=3600'];

        $logo = Setting::get('logo_path');
        if ($logo && Storage::disk('public')->exists($logo)) {
            return response()->file(Storage::disk('public')->path($logo), $headers);
        }

        return response()->file(public_path('brand/favicon.png'), $headers);
    }
}
