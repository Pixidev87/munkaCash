<?php

use App\Http\Controllers\BillingContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceBalanceHistoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceDeadlineController;
use App\Http\Controllers\InvoiceImportController;
use App\Http\Controllers\OrganizationAccessLogController;
use App\Http\Controllers\OrganizationCloseoutController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationExportController;
use App\Http\Controllers\OrganizationInvitationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\ReminderApprovalHistoryController;
use App\Http\Controllers\ReminderApprovalRevocationController;
use App\Http\Controllers\ReminderDeliveryLogController;
use App\Http\Controllers\ReminderDeliverySettingsController;
use App\Http\Controllers\ReminderPreviewController;
use App\Http\Controllers\ReminderTestDeliveryController;
use App\Http\Middleware\EnsureOrganizationOperationsAvailable;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'cache.headers:private;no_store'])->group(function () {
    Route::get('dashboard', [OrganizationController::class, 'index'])->name('dashboard');

    Route::prefix('organization-invitations/{token}')
        ->where(['token' => '[a-f0-9]{64}'])
        ->name('organization-invitations.')
        ->group(function () {
            Route::get('/', [OrganizationInvitationController::class, 'show'])->name('show');
            Route::post('/', [OrganizationInvitationController::class, 'accept'])->name('accept');
        });

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
                Route::get('access-log', [OrganizationAccessLogController::class, 'index'])->name('access-log');
                Route::get('members', [OrganizationMemberController::class, 'index'])->name('members.index');
                Route::post('members/invitations', [OrganizationInvitationController::class, 'store'])
                    ->middleware('throttle:15,1,organization-invitations')
                    ->name('members.invitations.store');
                Route::delete('members/invitations', [OrganizationInvitationController::class, 'destroy'])
                    ->middleware('throttle:15,1,organization-invitations')
                    ->name('members.invitations.destroy');
                Route::delete('members/{member}', [OrganizationMemberController::class, 'destroy'])
                    ->whereNumber('member')
                    ->name('members.destroy');
                Route::get('reminder-deliveries', [ReminderDeliveryLogController::class, 'index'])
                    ->name('reminder-deliveries.index');
                Route::patch('reminder-delivery-settings', [ReminderDeliverySettingsController::class, 'organization'])
                    ->name('reminder-delivery-settings');
                Route::patch('customers/{customer}/contact-restriction', [ReminderDeliverySettingsController::class, 'customer'])
                    ->whereNumber('customer')
                    ->name('customers.contact-restriction');
                Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
                Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
                Route::patch('customers/{customer}', [CustomerController::class, 'update'])
                    ->whereNumber('customer')
                    ->name('customers.update');
                Route::prefix('customers/{customer}/billing-contact')->whereNumber('customer')
                    ->name('customers.billing-contact.')->group(function () {
                        Route::get('/', [BillingContactController::class, 'edit'])->name('edit');
                        Route::patch('/', [BillingContactController::class, 'update'])->name('update');
                        Route::post('confirm', [BillingContactController::class, 'confirm'])->name('confirm');
                        Route::delete('confirm', [BillingContactController::class, 'revoke'])->name('revoke');
                    });
                Route::get('invoices/{invoice}/reminder-preview', [ReminderPreviewController::class, 'show'])
                    ->whereNumber('invoice')
                    ->name('invoices.reminder-preview');
                Route::post('invoices/{invoice}/reminder-approval', [ReminderPreviewController::class, 'approve'])
                    ->whereNumber('invoice')
                    ->name('invoices.reminder-approval');
                Route::get('invoices/{invoice}/reminder-approval-history', [ReminderApprovalHistoryController::class, 'index'])
                    ->whereNumber('invoice')
                    ->name('invoices.reminder-approval-history');
                Route::delete('invoices/{invoice}/reminder-approval', [ReminderApprovalRevocationController::class, 'destroy'])
                    ->whereNumber('invoice')
                    ->name('invoices.reminder-approval.destroy');
                Route::post('invoices/{invoice}/reminder-test-delivery', [ReminderTestDeliveryController::class, 'store'])
                    ->whereNumber('invoice')
                    ->name('invoices.reminder-test-delivery');
                Route::delete('invoices/{invoice}/reminder-test-delivery', [ReminderTestDeliveryController::class, 'destroy'])
                    ->whereNumber('invoice')
                    ->name('invoices.reminder-test-delivery.destroy');
                Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
                Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
                Route::prefix('invoice-imports')->name('invoice-imports.')->group(function () {
                    Route::get('/', [InvoiceImportController::class, 'index'])->name('index');
                    Route::get('new', [InvoiceImportController::class, 'create'])->name('create');
                    Route::get('template', [InvoiceImportController::class, 'template'])->name('template');
                    Route::post('/', [InvoiceImportController::class, 'store'])->name('store');
                    Route::get('{import}', [InvoiceImportController::class, 'show'])->whereUuid('import')->name('show');
                    Route::delete('{import}', [InvoiceImportController::class, 'destroy'])->whereUuid('import')->name('destroy');
                    Route::get('{import}/error-report', [InvoiceImportController::class, 'errorReport'])->whereUuid('import')->name('error-report');
                    Route::post('{import}/confirm', [InvoiceImportController::class, 'confirm'])->whereUuid('import')->name('confirm');
                });
                Route::get('invoices/{invoice}/balance-history', [InvoiceBalanceHistoryController::class, 'index'])
                    ->whereNumber('invoice')
                    ->name('invoices.balance-history');
                Route::patch('invoices/{invoice}/balance', [InvoiceController::class, 'updateBalance'])
                    ->whereNumber('invoice')
                    ->name('invoices.balance.update');
                Route::delete('invoices/{invoice}/balance', [InvoiceController::class, 'resetBalance'])
                    ->whereNumber('invoice')
                    ->name('invoices.balance.destroy');
                Route::get('invoices/{invoice}/deadline-history', [InvoiceDeadlineController::class, 'history'])
                    ->whereNumber('invoice')
                    ->name('invoices.deadline-history');
                Route::patch('invoices/{invoice}/deadline', [InvoiceDeadlineController::class, 'update'])
                    ->whereNumber('invoice')
                    ->name('invoices.deadline.update');
                Route::patch('invoices/{invoice}/dispute', [InvoiceController::class, 'updateDispute'])
                    ->whereNumber('invoice')
                    ->name('invoices.dispute.update');
            });
        });
});

require __DIR__.'/settings.php';
