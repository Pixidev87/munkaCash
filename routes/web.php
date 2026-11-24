<?php

use App\Http\Controllers\BillingContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceImportController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ReminderPreviewController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [OrganizationController::class, 'index'])->name('dashboard');

    Route::get('organizations/{organization}', [OrganizationController::class, 'show'])
        ->whereNumber('organization')
        ->name('organizations.show');

    Route::prefix('organizations/{organization}')
        ->whereNumber('organization')
        ->name('organizations.')
        ->group(function () {
            Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::prefix('customers/{customer}/billing-contact')->whereNumber('customer')
                ->name('customers.billing-contact.')->group(function () {
                    Route::get('/', [BillingContactController::class, 'edit'])->name('edit');
                    Route::patch('/', [BillingContactController::class, 'update'])->name('update');
                    Route::post('confirm', [BillingContactController::class, 'confirm'])->name('confirm');
                });
            Route::get('invoices/{invoice}/reminder-preview', ReminderPreviewController::class)
                ->whereNumber('invoice')
                ->name('invoices.reminder-preview');
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            Route::prefix('invoice-imports')->name('invoice-imports.')->group(function () {
                Route::get('new', [InvoiceImportController::class, 'create'])->name('create');
                Route::get('template', [InvoiceImportController::class, 'template'])->name('template');
                Route::post('/', [InvoiceImportController::class, 'store'])->name('store');
                Route::get('{import}', [InvoiceImportController::class, 'show'])->whereUuid('import')->name('show');
                Route::post('{import}/confirm', [InvoiceImportController::class, 'confirm'])->whereUuid('import')->name('confirm');
            });
            Route::patch('invoices/{invoice}/balance', [InvoiceController::class, 'updateBalance'])
                ->whereNumber('invoice')
                ->name('invoices.balance.update');
        });
});

require __DIR__.'/settings.php';
