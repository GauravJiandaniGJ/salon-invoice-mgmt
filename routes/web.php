<?php

use App\Http\Controllers\Catalog\ServiceCategoryController;
use App\Http\Controllers\Catalog\ServiceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // ----- Owner only -----
    Route::middleware('role:owner')->group(function () {
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::post('services/reorder', [ServiceController::class, 'reorder'])->name('services.reorder');
        Route::patch('services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

        Route::post('service-categories', [ServiceCategoryController::class, 'store'])->name('service-categories.store');
        Route::post('service-categories/reorder', [ServiceCategoryController::class, 'reorder'])->name('service-categories.reorder');
        Route::patch('service-categories/{serviceCategory}', [ServiceCategoryController::class, 'update'])->name('service-categories.update');
        Route::delete('service-categories/{serviceCategory}', [ServiceCategoryController::class, 'destroy'])->name('service-categories.destroy');
    });
});

require __DIR__.'/account.php';
require __DIR__.'/auth.php';
