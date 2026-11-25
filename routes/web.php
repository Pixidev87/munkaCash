<?php

use App\Http\Controllers\BillingContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceImportController;
use App\Http\Controllers\OrganizationCloseoutController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationExportController;
use App\Http\Controllers\ReminderDeliveryLogController;
use App\Http\Controllers\ReminderDeliverySettingsController;
use App\Http\Controllers\ReminderPreviewController;
use App\Http\Controllers\ReminderTestDeliveryController;
use App\Http\Middleware\EnsureOrganizationOperationsAvailable;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'cache.headers:private;no_store'])->group(function () {
    Route::get('dashboard', [OrganizationController::class, 'index'])->name('dashboard');

    Route::get('organizations/{organization}', [OrganizationController::class, 'show'])
        ->whereNumber('organization')
        ->name('organizations.show');

    Route::prefix('organizations/{organization}')
        ->whereNumber('organization')
        ->name('organizations.')
        ->group(function () {
            Route::get('exports/customers', [OrganizationExportController::class, 'customers'])
                ->name('exports.customers');
            Route::get('exports/invoices', [OrganizationExportController::class, 'invoices'])
                ->name('exports.invoices');
            Route::post('closeout', [OrganizationCloseoutController::class, 'store'])->name('closeout');
            Route::middleware(EnsureOrganizationOperationsAvailable::class)->group(function () {
                Route::get('reminder-deliveries', [ReminderDeliveryLogController::class, 'index'])
                    ->name('reminder-deliveries.index');
                Route::patch('reminder-delivery-settings', [ReminderDeliverySettingsController::class, 'organization'])
                    ->name('reminder-delivery-settings');
                Route::patch('customers/{customer}/contact-restriction', [ReminderDeliverySettingsController::class, 'customer'])
                    ->whereNumber('customer')
                    ->name('customers.contact-restriction');
                Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
                Route::prefix('customers/{customer}/billing-contact')->whereNumber('customer')
                    ->name('customers.billing-contact.')->group(function () {
                        Route::get('/', [BillingContactController::class, 'edit'])->name('edit');
                        Route::patch('/', [BillingContactController::class, 'update'])->name('update');
                        Route::post('confirm', [BillingContactController::class, 'confirm'])->name('confirm');
                    });
                Route::get('invoices/{invoice}/reminder-preview', [ReminderPreviewController::class, 'show'])
                    ->whereNumber('invoice')
                    ->name('invoices.reminder-preview');
                Route::post('invoices/{invoice}/reminder-approval', [ReminderPreviewController::class, 'approve'])
                    ->whereNumber('invoice')
                    ->name('invoices.reminder-approval');
                Route::post('invoices/{invoice}/reminder-test-delivery', [ReminderTestDeliveryController::class, 'store'])
                    ->whereNumber('invoice')
                    ->name('invoices.reminder-test-delivery');
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
                Route::patch('invoices/{invoice}/dispute', [InvoiceController::class, 'updateDispute'])
                    ->whereNumber('invoice')
                    ->name('invoices.dispute.update');
            });
        });
});

require __DIR__.'/settings.php';
