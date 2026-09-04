<?php

/*
|--------------------------------------------------------------------------
| Route contract (owned by the orchestrator — see docs/CONTRACT.md)
|--------------------------------------------------------------------------
| Agents implement the referenced controllers; do not rename routes here.
*/

use App\Http\Controllers\Billing\BillController;
use App\Http\Controllers\Billing\CustomerController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\Catalog\ServiceCategoryController;
use App\Http\Controllers\Catalog\ServiceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\Public\PublicInvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\StaffMemberController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// ----- Public (no auth) -----
Route::get('brand/favicon', [BrandController::class, 'favicon'])->name('brand.favicon');

Route::middleware('throttle:60,1')->group(function () {
    Route::get('i/{code}', [PublicInvoiceController::class, 'show'])->name('public.invoice');
    Route::get('i/{code}/pdf', [PublicInvoiceController::class, 'pdf'])->name('public.invoice.pdf');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // ----- Billing -----
    Route::get('bills/new', [BillController::class, 'create'])->name('bills.create');
    Route::post('invoices', [BillController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}/edit', [BillController::class, 'edit'])->name('invoices.edit');
    Route::put('invoices/{invoice}', [BillController::class, 'update'])->name('invoices.update');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/export.csv', [InvoiceController::class, 'exportCsv'])->middleware('role:owner')->name('invoices.export');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent'])->name('invoices.mark-sent');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->middleware('role:owner')->name('invoices.void');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

    // ----- Customers -----
    Route::get('customers/lookup', [CustomerController::class, 'lookup'])->name('customers.lookup');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');

    // ----- Expenses -----
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::patch('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // ----- Reports (daily: everyone, but staff is pinned to today; others: owner) -----
    Route::get('reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
    Route::get('reports/daily/pdf', [ReportController::class, 'dailyPdf'])->name('reports.daily.pdf');
    Route::get('reports/daily.csv', [ReportController::class, 'dailyCsv'])->name('reports.daily.csv');
    Route::middleware('role:owner')->group(function () {
        Route::get('reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('reports/monthly.csv', [ReportController::class, 'monthlyCsv'])->name('reports.monthly.csv');
        Route::get('reports/services', [ReportController::class, 'services'])->name('reports.services');
        Route::get('reports/services.csv', [ReportController::class, 'servicesCsv'])->name('reports.services.csv');
        Route::get('reports/monthly/pdf', [ReportController::class, 'monthlyPdf'])->name('reports.monthly.pdf');
        Route::get('reports/services/pdf', [ReportController::class, 'servicesPdf'])->name('reports.services.pdf');
        Route::get('reports/staff', [ReportController::class, 'staff'])->name('reports.staff');
        Route::get('reports/staff.csv', [ReportController::class, 'staffCsv'])->name('reports.staff.csv');
        Route::get('reports/staff/pdf', [ReportController::class, 'staffPdf'])->name('reports.staff.pdf');
    });

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

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/logo', [SettingsController::class, 'uploadLogo'])->name('settings.logo');
        Route::delete('settings/logo', [SettingsController::class, 'removeLogo'])->name('settings.logo.remove');
        Route::get('settings/whatsapp-preview', [SettingsController::class, 'whatsappPreview'])->name('settings.whatsapp-preview');

        Route::post('settings/users', [UserController::class, 'store'])->name('settings.users.store');
        Route::patch('settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');

        Route::post('settings/staff-members', [StaffMemberController::class, 'store'])->name('settings.staff.store');
        Route::patch('settings/staff-members/{staffMember}', [StaffMemberController::class, 'update'])->name('settings.staff.update');
    });
});

require __DIR__.'/account.php';
require __DIR__.'/auth.php';
